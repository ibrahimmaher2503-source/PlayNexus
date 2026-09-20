import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const base = 'http://127.0.0.1:8821';
const out = path.resolve('deliverables/qa/wave-01');
fs.mkdirSync(out, { recursive: true });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const results = [];

class Cdp {
    constructor(url) { this.ws = new WebSocket(url); this.id = 0; this.pending = new Map(); this.errors = []; this.serverFailures = []; this.navigationCount = 0; }
    async open(page = true) {
        await new Promise((resolve, reject) => { this.ws.addEventListener('open', resolve, { once: true }); this.ws.addEventListener('error', reject, { once: true }); });
        this.ws.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.method === 'Page.frameNavigated' && !data.params.frame.parentId) this.navigationCount++;
            if (data.method === 'Page.javascriptDialogOpening') {
                results.push({ journey: 'native-confirmation', type: data.params.type, message: data.params.message, accepted: true });
                this.send('Page.handleJavaScriptDialog', { accept: true });
            }
            if (data.method === 'Runtime.exceptionThrown') this.errors.push(data.params.exceptionDetails.text);
            if (data.method === 'Log.entryAdded' && ['warning', 'error'].includes(data.params.entry.level)) this.errors.push(data.params.entry.text);
            if (data.method === 'Network.responseReceived' && data.params.response.status >= 500) this.serverFailures.push({ url: data.params.response.url, status: data.params.response.status });
            if (data.id && this.pending.has(data.id)) { const p = this.pending.get(data.id); this.pending.delete(data.id); data.error ? p.reject(new Error(data.error.message)) : p.resolve(data.result); }
        });
        if (page) for (const command of ['Page.enable', 'Runtime.enable', 'Network.enable', 'Log.enable']) await this.send(command);
    }
    send(method, params = {}) { const id = ++this.id; this.ws.send(JSON.stringify({ id, method, params })); return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject })); }
    async eval(expression) { const r = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }); if (r.exceptionDetails) throw new Error(r.exceptionDetails.text); return r.result.value; }
    async go(url) { await this.send('Page.navigate', { url: url.startsWith('http') ? url : base + url }); await pause(450); for (let n = 0; n < 40; n++) { if (await this.eval('document.readyState === "complete"')) return; await pause(100); } throw new Error(`Navigation timeout: ${url}`); }
    async submit(selector, values = {}, bypassNativeValidation = false) {
        const before = this.navigationCount;
        await this.eval(`(() => { const f=document.querySelector(${JSON.stringify(selector)}); if(!f) throw Error('Form missing'); const detail=f.closest('details'); if(detail) detail.open=true; for(const [key,value] of Object.entries(${JSON.stringify(values)})) { const input=f.elements.namedItem(key); if(!input) throw Error('Input missing: '+key); input.value=value; } if(${JSON.stringify(bypassNativeValidation)}) f.noValidate=true; f.requestSubmit(); })()`);
        for (let n = 0; n < 80; n++) { await pause(100); if (this.navigationCount > before && await this.eval('document.readyState === "complete"')) return; }
        throw new Error(`Form submission did not navigate: ${selector}`);
    }
    async width(width) { this.currentWidth = width; await this.send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false }); await pause(150); }
    async screenshot(name) { const r = await this.send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: this.currentWidth, height: 1000, scale: 1 } }); fs.writeFileSync(path.join(out, name), Buffer.from(r.data, 'base64')); }
    async snapshot(journey) { const r = await this.eval(`({path:location.pathname,lang:document.documentElement.lang,dir:document.documentElement.dir,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,h1:document.querySelector('h1')?.innerText})`); results.push({ journey, width: this.currentWidth, ...r }); return r; }
    async probe(url) { return this.eval(`fetch(${JSON.stringify(url)}, {headers:{Accept:'application/json'},credentials:'same-origin'}).then(r=>r.status)`); }
}

// Only synthetic browser-fixture OTP generation, never application verification.
function otp(secret) {
    let bits = ''; for (const letter of secret) bits += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(letter).toString(2).padStart(5, '0');
    const bytes = []; for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    const counter = Buffer.alloc(8); counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const digest = crypto.createHmac('sha1', Buffer.from(bytes)).update(counter).digest(); const offset = digest[19] & 15;
    return String((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).padStart(6, '0');
}

const version = await (await fetch('http://127.0.0.1:8822/json/version')).json();
const browser = new Cdp(version.webSocketDebuggerUrl); await browser.open(false);
const pages = [];
async function page() {
    const { browserContextId } = await browser.send('Target.createBrowserContext');
    const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank', browserContextId });
    const targets = await (await fetch('http://127.0.0.1:8822/json/list')).json();
    const cdp = new Cdp(targets.find(t => t.id === targetId).webSocketDebuggerUrl); await cdp.open(); await cdp.width(1440); pages.push(cdp); return cdp;
}
async function login(cdp, email, platform = false) {
    await cdp.go(platform ? '/platform/login' : '/login');
    await cdp.submit('form:has([name=password])', { email, password: 'PlayNexusDemoOnly-2026' });
    for (let n = 0; n < 40; n++) {
        if ((await cdp.eval('location.pathname')) !== (platform ? '/platform/login' : '/login')) break;
        await pause(150);
    }
    if (platform) {
        await cdp.go('/platform/tenants');
        const key = await cdp.eval(`document.querySelector('[data-mfa-setup-key]')?.innerText.trim()`);
        if (!key) throw new Error('Expected fresh disposable Platform MFA enrollment');
        await cdp.submit('form:has([name=code])', { current_password: 'PlayNexusDemoOnly-2026', code: otp(key) });
        for (let n = 0; n < 40; n++) { if (await cdp.eval(`Boolean(document.querySelector('[data-mfa-recovery]'))`)) break; await pause(150); }
        results.push({ journey: 'platform-mfa-enrollment', recoveryVisible: await cdp.eval(`Boolean(document.querySelector('[data-mfa-recovery]'))`) });
        await cdp.go('/platform/tenants');
    }
    await cdp.snapshot(platform ? 'platform-login-regression' : email.split('@')[0] + '-login');
}
async function locale(cdp, value) {
    await cdp.eval(`(() => { const f=document.querySelector('form[action$="/locale"]'); const body=new URLSearchParams(new FormData(f)); body.set('locale',${JSON.stringify(value)}); return fetch(f.action,{method:'POST',body,credentials:'same-origin'}).then(r=>r.status); })()`);
}
async function status(cdp, form, value) {
    const before = cdp.navigationCount;
    await cdp.eval(`(() => { const f=document.querySelector(${JSON.stringify(form)}); if(${JSON.stringify(value)}!==null) f.querySelector('[name=status]').value=${JSON.stringify(value)}; const detail=f.closest('details'); if(detail) detail.open=true; f.querySelector('button[type=submit]').click(); })()`);
    await pause(150);
    const dialog = await cdp.eval(`Boolean(document.querySelector('dialog[open] [data-pn-confirm-submit]'))`);
    if (dialog) await cdp.eval(`document.querySelector('dialog[open] [data-pn-confirm-submit]').click()`);
    for (let n = 0; n < 80; n++) { await pause(100); if (cdp.navigationCount > before && await cdp.eval('document.readyState === "complete"')) break; if (n === 79) throw new Error('Status submission did not navigate'); }
    results.push({ journey: 'status-confirmation', customDialogClicked: dialog, form });
}

try {
    const admin = await page(); await login(admin, 'wave01.platform@playnexus.test', true); await admin.screenshot('platform-regression.png');
    const owner = await page(); await login(owner, 'demo.alpha.owner@playnexus.test');
    await owner.go('/app/setup'); await owner.snapshot('tenant-setup-guide');
    await owner.go('/app/tenant/settings'); await owner.submit('form[action$="/app/tenant/settings"]', { timezone: 'Invalid/Zone' });
    results.push({ journey: 'profile-invalid', fieldError: await owner.eval(`Boolean(document.querySelector('[aria-invalid=true]'))`) });
    await owner.submit('form[action$="/app/tenant/settings"]', { timezone: 'America/New_York', legal_name: 'Wave 01 QA LLC' }); await owner.snapshot('profile-saved');
    await owner.go('/app/branches/manage'); const unique = Date.now(); const name = `Wave Branch ${unique}`;
    await owner.submit('form[action$="/app/branches"]', { name });
    const settings = await owner.eval(`[...document.querySelectorAll('tr')].find(r=>r.innerText.includes(${JSON.stringify(name)}))?.querySelector('a[href*="/settings"]')?.href`);
    if (!settings) throw new Error('Created branch missing'); const branchId = Number(new URL(settings).pathname.split('/')[3]);
    await owner.go(settings); await owner.submit('form[action*="/settings"]', { code: `W${unique}`, timezone: 'America/New_York', capacity: '0', tax_rate_bps: '1400', tax_mode: 'exclusive', receipt_prefix: 'WAVE' }, true);
    results.push({ journey: 'branch-invalid-capacity', hasError: await owner.eval(`Boolean(document.querySelector('[role=alert]'))`) });
    await owner.submit('form[action*="/settings"]', { capacity: '25' }); await owner.snapshot('branch-settings-saved');
    await owner.go('/app/branches/manage'); await status(owner, `#branch-status-${branchId}`, null);
    await owner.go('/app'); await owner.eval(`document.querySelector('form[action$="/branch-context/${branchId}"] button').click()`); await pause(700); await owner.snapshot('branch-selector');
    await owner.go('/app/branches/manage'); await status(owner, `#branch-status-${branchId}`, null);
    results.push({ journey: 'inactive-operational-access', status: await owner.probe(`/branches/${branchId}`) });
    await owner.go(`/app/reports/revenue?branch_id=${branchId}`); await owner.snapshot('inactive-history');
    await owner.go('/app/branches/manage'); await status(owner, `#branch-status-${branchId}`, null);

    await owner.go('/app/staff/create'); const email = `wave.staff.${unique}@playnexus.test`;
    await owner.submit('form[action$="/app/staff"]', { name: 'Wave Staff QA', email });
    const assignment = await owner.eval(`[...document.querySelectorAll('tr')].find(r=>r.innerText.includes(${JSON.stringify(email)}))?.querySelector('a[href*="assignments"]')?.href`);
    if (!assignment) throw new Error('New staff linkage absent'); const staffId = new URL(assignment).searchParams.get('user_id');
    await owner.go(`/app/staff/${staffId}/edit`);
    await owner.submit('form[action$="/identity"]', { name: 'Wave Staff QA Edited' }); await owner.snapshot('staff-identity-edited');
    await owner.go(assignment); const formSelector = `form[action$="/app/assignments/${staffId}/${branchId}"]`;
    await owner.submit(formSelector, { role: 'cashier', is_active: '1' }); await owner.snapshot('staff-assigned-cashier');
    await owner.submit(formSelector, { role: 'reception_staff', is_active: '1' }); await owner.snapshot('staff-role-changed');
    await owner.go('/app/roles'); await owner.submit('form[action$="/app/roles"]', { name: `Wave observer ${unique}` }); await owner.snapshot('custom-role-created');

    const manager = await page(); await login(manager, 'demo.alpha.branch_manager@playnexus.test'); await manager.go('/app/branches/manage'); await manager.snapshot('manager-scoped-branches');
    results.push({ journey: 'manager-unassigned-denied', status: await manager.probe(`/app/branches/${branchId}/settings`) });
    const reception = await page(); await login(reception, 'demo.alpha.reception_staff@playnexus.test'); await reception.go('/app/families'); await reception.snapshot('reception-permitted-families');
    results.push({ journey: 'reception-admin-denied', status: await reception.probe('/app/tenant/settings') });
    const cashier = await page(); await login(cashier, 'demo.alpha.cashier@playnexus.test'); await cashier.go('/app/pos'); await cashier.snapshot('cashier-permitted-pos');
    results.push({ journey: 'cashier-admin-denied', branch: await cashier.probe('/app/branches/manage'), staff: await cashier.probe('/app/staff') });

    await owner.go('/app/staff'); const receptionAction = await owner.eval(`[...document.querySelectorAll('tr')].find(r=>r.innerText.includes('demo.alpha.reception_staff@playnexus.test'))?.querySelector('form[action$="/status"]')?.getAttribute('action')`);
    await status(owner, `form[action=${JSON.stringify(receptionAction)}]`, 'suspended'); await reception.go('/app/families'); await reception.snapshot('established-staff-session-revoked');
    await owner.go('/app/staff'); await status(owner, `form[action=${JSON.stringify(receptionAction)}]`, 'active');
    await reception.go('/app/families'); await reception.snapshot('reactivation-does-not-restore-session');

    await owner.go('/app/assignments'); const managerLink = await owner.eval(`[...document.querySelectorAll('a[href*="user_id"]')].find(r=>r.innerText.includes('demo.alpha.branch_manager@playnexus.test'))?.href`);
    await owner.go(managerLink); const revokeForm = await owner.eval(`[...document.querySelectorAll('form[action*="/app/assignments/"]')].find(f=>f.querySelector('[name=expected_role]')?.value==='branch_manager' && f.querySelector('[name=expected_is_active]')?.value==='1')?.getAttribute('action')`);
    if (!revokeForm) throw new Error('Manager assignment form missing');
    const revokedBranchId = Number(new URL(revokeForm).pathname.split('/').at(-1));
    await owner.submit(`form[action=${JSON.stringify(revokeForm)}]`, { is_active: '0', role: 'branch_manager' });
    await manager.go('/app/branches/manage'); results.push({ journey: 'established-manager-assignment-revoked', status: await manager.probe(`/app/branches/${revokedBranchId}/settings`), remainingBranchPage: await manager.probe('/app/branches/manage') });

    for (const language of ['en', 'ar']) {
        await owner.go('/app'); await locale(owner, language);
        for (const width of [390, 820, 1440]) {
            await owner.width(width);
            for (const url of ['/app/setup', '/app/tenant/settings', '/app/branches/manage', settings, '/app/staff', '/app/staff/create', `/app/staff/${staffId}/edit`, assignment, '/app/roles', '/app/account/mfa']) {
                await owner.go(url); await owner.snapshot(`responsive-${language}-${url}`);
            }
            await owner.screenshot(`staff-${language}-${width}.png`);
        }
    }
    const guest = await page(); await guest.go('/forgot-password'); await guest.snapshot('reset-request');
    await guest.submit('form:has([name=email])', { email: 'demo.alpha.cashier@playnexus.test' }); await guest.snapshot('reset-generic-response');
    await guest.go('/reset-password/tampered?email=demo.alpha.cashier%40playnexus.test');
    await guest.submit('form:has([name=password_confirmation])', { password: 'WaveResetPassword2026', password_confirmation: 'WaveResetPassword2026' });
    results.push({ journey: 'reset-tampered-rejected', hasError: await guest.eval(`Boolean(document.querySelector('[role=alert]'))`) });
    const consoleNetwork = { journey: 'console-network', errors: pages.flatMap(p => p.errors).filter(e => !/40[134] \(/.test(e)), serverFailures: pages.flatMap(p => p.serverFailures) };
    results.push(consoleNetwork);
    if (consoleNetwork.errors.length || consoleNetwork.serverFailures.length) throw new Error('Unexpected browser console/server failures');
    if (results.some(r => r.overflow)) throw new Error('Horizontal overflow found');
    for (const [journey, expected] of Object.entries({ 'manager-unassigned-denied': 404, 'reception-admin-denied': 403, 'established-manager-assignment-revoked': 404, 'inactive-operational-access': 404 })) {
        if (results.find(r => r.journey === journey)?.status !== expected) throw new Error(`Unexpected access result: ${journey}`);
    }
    if (results.find(r => r.journey === 'established-manager-assignment-revoked')?.remainingBranchPage !== 200) throw new Error('Remaining authorized Manager branch access was lost');
    const cashierDenial = results.find(r => r.journey === 'cashier-admin-denied');
    if (cashierDenial?.branch !== 403 || cashierDenial?.staff !== 403) throw new Error('Cashier administration was not denied');
    for (const journey of ['established-staff-session-revoked', 'reactivation-does-not-restore-session']) if (results.find(r => r.journey === journey)?.path !== '/login') throw new Error(`Old staff session remains usable: ${journey}`);
    for (const journey of ['profile-invalid', 'branch-invalid-capacity', 'reset-tampered-rejected']) { const r = results.find(r => r.journey === journey); if (!r?.fieldError && !r?.hasError) throw new Error(`Validation not shown: ${journey}`); }
    fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify(results, null, 2));
    process.stdout.write(JSON.stringify(results, null, 2));
} catch (error) {
    fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify({ results, error: error.message }, null, 2));
    throw error;
} finally { for (const p of pages) p.ws.close(); browser.ws.close(); }
