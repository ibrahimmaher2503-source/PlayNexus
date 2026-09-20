import fs from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8821';
const out = path.resolve('deliverables/qa/checkpoint-02-tenant-branch');
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

class Cdp {
    constructor(wsUrl) { this.id = 0; this.pending = new Map(); this.errors = []; this.ws = new WebSocket(wsUrl); }
    async open() {
        await new Promise((resolve, reject) => { this.ws.addEventListener('open', resolve, { once: true }); this.ws.addEventListener('error', reject, { once: true }); });
        this.ws.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.method === 'Runtime.exceptionThrown') this.errors.push(message.params.exceptionDetails?.text ?? 'Runtime exception');
            if (message.method === 'Log.entryAdded' && ['error', 'warning'].includes(message.params.entry?.level)) this.errors.push(message.params.entry.text);
            if (!message.id || !this.pending.has(message.id)) return;
            const pending = this.pending.get(message.id); this.pending.delete(message.id);
            message.error ? pending.reject(new Error(message.error.message)) : pending.resolve(message.result);
        });
        for (const domain of ['Page.enable', 'Runtime.enable', 'Network.enable', 'Log.enable']) await this.send(domain);
        await this.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
    }
    send(method, params = {}) { const id = ++this.id; this.ws.send(JSON.stringify({ id, method, params })); return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject })); }
    async eval(expression) { const result = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }); if (result.exceptionDetails) throw new Error(result.exceptionDetails.text); return result.result.value; }
    async navigate(url) { await this.send('Page.navigate', { url }); await sleep(500); for (let i = 0; i < 40; i++) { const ready = await this.eval('document.readyState'); if (ready === 'complete') return; await sleep(100); } throw new Error(`Navigation timeout: ${url}`); }
    async shot(name) { const image = await this.send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: 1440, height: 1000, scale: 1 } }); fs.writeFileSync(path.join(out, name), Buffer.from(image.data, 'base64')); }
    async clear() { await this.send('Network.clearBrowserCookies'); }
}

async function connect() {
    const pages = (await (await fetch('http://127.0.0.1:8822/json/list')).json()).filter((target) => target.type === 'page');
    const page = pages.find((target) => target.url.startsWith(base)) ?? pages.find((target) => target.url === 'about:blank') ?? pages[0];
    const cdp = new Cdp(page.webSocketDebuggerUrl); await cdp.open(); return cdp;
}

async function login(cdp, email) {
    await cdp.clear(); await cdp.navigate(`${base}/login`);
    await cdp.eval(`(() => { const form=document.querySelector('form:has([name=password])'); form.querySelector('[name=email]').value=${JSON.stringify(email)}; form.querySelector('[name=password]').value='PlayNexusDemoOnly-2026'; form.requestSubmit(); })()`);
    for (let i = 0; i < 30; i++) {
        if ((await cdp.eval('location.pathname')) === '/app') return;
        await sleep(100);
    }
    throw new Error(`Login did not reach the tenant app for ${email}`);
}

const cdp = await connect();
const results = [];
try {
    await login(cdp, 'demo.alpha.owner@playnexus.test');
    results.push({ journey: 'owner-login', path: await cdp.eval('location.pathname') });
    results.push({ journey: 'english-ltr', lang: await cdp.eval('document.documentElement.lang'), dir: await cdp.eval('document.documentElement.dir') });

    await cdp.navigate(`${base}/app/tenant/settings`);
    const originalName = await cdp.eval(`document.querySelector('[name=name]').value`);
    await cdp.eval(`(() => { const f=document.querySelector('form[action$="/app/tenant/settings"]'); f.querySelector('[name=timezone]').value='Invalid/Zone'; f.requestSubmit(); })()`);
    await sleep(600);
    results.push({ journey: 'profile-invalid', hasError: await cdp.eval(`document.body.innerText.includes('timezone') || Boolean(document.querySelector('[aria-invalid=true]'))`) });
    await cdp.eval(`(() => { const f=document.querySelector('form[action$="/app/tenant/settings"]'); f.querySelector('[name=name]').value=${JSON.stringify(originalName + ' QA')}; f.querySelector('[name=legal_name]').value='Nile Play Center QA LLC'; f.querySelector('[name=timezone]').value='Africa/Cairo'; f.requestSubmit(); })()`);
    await sleep(700); results.push({ journey: 'profile-valid', path: await cdp.eval('location.pathname') }); await cdp.shot('01-business-profile.png');

    await cdp.navigate(`${base}/app/branches/manage`);
    const unique = Date.now(); const branchName = `Checkpoint Branch ${unique}`;
    await cdp.eval(`(() => { const f=document.querySelector('form[action$="/app/branches"]'); f.querySelector('[name=name]').value=${JSON.stringify(branchName)}; f.requestSubmit(); })()`);
    await sleep(700);
    const settingsHref = await cdp.eval(`([...document.querySelectorAll('tr')].find(r=>r.innerText.includes(${JSON.stringify(branchName)}))?.querySelector('a[href*="/settings"]')?.href) ?? null`);
    if (!settingsHref) throw new Error('Created draft branch not found');
    results.push({ journey: 'branch-draft-created', inactive: await cdp.eval(`([...document.querySelectorAll('tr')].find(r=>r.innerText.includes(${JSON.stringify(branchName)}))?.innerText.includes('Inactive'))`) });

    await cdp.navigate(settingsHref);
    await cdp.eval(`(() => { const f=document.querySelector('form[action*="/settings"]'); f.querySelector('[name=code]').value='CP${unique}'; f.querySelector('[name=address_text]').value='Checkpoint address'; f.querySelector('[name=timezone]').value='Invalid/Zone'; f.querySelector('[name=capacity]').value='25'; f.querySelector('[name=tax_rate_bps]').value='1400'; f.querySelector('[name=tax_mode]').value='exclusive'; f.querySelector('[name=receipt_prefix]').value='CP'; const closed=f.querySelector('[name="opening_hours[0][is_closed]"]'); closed.value='0'; f.querySelector('[name="opening_hours[0][opens_at]"]').value='09:00'; f.querySelector('[name="opening_hours[0][closes_at]"]').value='22:00'; f.requestSubmit(); })()`);
    await sleep(700);
    results.push({ journey: 'branch-invalid', hasError: await cdp.eval(`Boolean(document.querySelector('[role=alert]'))`) });
    await cdp.eval(`(() => { const f=document.querySelector('form[action*="/settings"]'); f.querySelector('[name=timezone]').value='Africa/Cairo'; f.requestSubmit(); })()`);
    await sleep(700); results.push({ journey: 'branch-configured', path: await cdp.eval('location.pathname') }); await cdp.shot('02-branch-settings.png');

    await cdp.navigate(`${base}/app/branches/manage`);
    const branchId = Number(new URL(settingsHref).pathname.split('/')[3]);
    await cdp.eval(`(() => { const f=document.querySelector('#branch-status-${branchId}'); HTMLFormElement.prototype.submit.call(f); })()`);
    await sleep(700); results.push({ journey: 'branch-activated', active: await cdp.eval(`document.querySelector('#branch-status-${branchId} [name=is_active]').value === '0'`) });

    await cdp.navigate(`${base}/app`);
    const selectorVisible = await cdp.eval(`document.body.innerText.includes(${JSON.stringify(branchName)})`);
    await cdp.eval(`document.querySelector('form[action$="/branch-context/${branchId}"] button').click()`);
    await sleep(700);
    results.push({ journey: 'branch-selector', branchVisible: selectorVisible, selected: await cdp.eval(`Boolean(document.querySelector('[data-pn-branch-clock][data-timezone="Africa/Cairo"]'))`) });

    await cdp.navigate(`${base}/app/branches/manage`);
    await cdp.eval(`HTMLFormElement.prototype.submit.call(document.querySelector('#branch-status-${branchId}'))`); await sleep(700);
    await cdp.navigate(`${base}/app/reports/revenue?branch_id=${branchId}`);
    results.push({ journey: 'deactivated-history', reportAvailable: (await cdp.eval('location.pathname')) === '/app/reports/revenue' });

    await cdp.navigate(`${base}/app/branches/manage`);
    await cdp.eval(`HTMLFormElement.prototype.submit.call(document.querySelector('#branch-status-${branchId}'))`); await sleep(700);
    results.push({ journey: 'branch-reactivated', path: await cdp.eval('location.pathname') });

    await login(cdp, 'demo.alpha.branch_manager@playnexus.test');
    await cdp.navigate(`${base}/app/branches/manage`);
    results.push({
        journey: 'manager-scoped-list',
        path: await cdp.eval('location.pathname'),
        assignedVisible: await cdp.eval(`document.body.innerText.includes('[DEMO] Alpha Main Branch')`),
        unassignedHidden: await cdp.eval(`!document.body.innerText.includes(${JSON.stringify(branchName)})`),
        createHidden: await cdp.eval(`!document.querySelector('form[action$="/app/branches"]')`),
    });

    await login(cdp, 'demo.alpha.reception_staff@playnexus.test');
    const deniedStatus = await cdp.eval(`fetch('/app/branches/manage', { credentials: 'same-origin', redirect: 'manual' }).then(r => r.status)`);
    await cdp.navigate(`${base}/app/branches/manage`);
    results.push({ journey: 'lower-role-denied', status: deniedStatus, path: await cdp.eval('location.pathname') });

    await login(cdp, 'demo.alpha.owner@playnexus.test'); await cdp.navigate(`${base}/app/branches/manage`);
    if ((await cdp.eval('location.pathname')) !== '/app/branches/manage') throw new Error('Owner did not reach branch management before locale switch');
    const localeStatus = await cdp.eval(`(() => { const f=document.querySelector('form[action$="/locale"]'); const body=new URLSearchParams(new FormData(f)); body.set('locale', 'ar'); return fetch(f.action, { method: 'POST', credentials: 'same-origin', body }).then(r => r.status); })()`);
    await cdp.navigate(`${base}/app/branches/manage`);
    results.push({ journey: 'arabic-rtl', localeStatus, path: await cdp.eval('location.pathname'), lang: await cdp.eval('document.documentElement.lang'), dir: await cdp.eval('document.documentElement.dir'), overflow: await cdp.eval('document.documentElement.scrollWidth > document.documentElement.clientWidth') });
    await cdp.shot('03-branches-ar.png');
    await cdp.navigate(`${base}/app/tenant/settings`);
    results.push({ journey: 'arabic-profile', path: await cdp.eval('location.pathname'), dir: await cdp.eval('document.documentElement.dir') });
    await cdp.navigate(settingsHref);
    results.push({ journey: 'arabic-branch-settings', path: await cdp.eval('location.pathname'), dir: await cdp.eval('document.documentElement.dir') });
    await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1024, height: 768, deviceScaleFactor: 1, mobile: false });
    results.push({ journey: 'tablet-settings', overflow: await cdp.eval('document.documentElement.scrollWidth > document.documentElement.clientWidth') });
    results.push({
        journey: 'browser-console',
        errors: cdp.errors.filter((error) => !error.includes('403 (Forbidden)')),
        expectedAuthorizationDenials: cdp.errors.filter((error) => error.includes('403 (Forbidden)')).length,
    });
    fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify(results, null, 2));
    process.stdout.write(JSON.stringify(results, null, 2));
} finally { cdp.ws.close(); }
