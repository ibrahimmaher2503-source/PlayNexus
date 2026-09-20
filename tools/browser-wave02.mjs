import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const base = process.env.WAVE02_BASE_URL || 'http://127.0.0.1:8851';
const debug = process.env.WAVE02_CDP_URL || 'http://127.0.0.1:8852';
const output = path.resolve('deliverables/qa/wave02');
fs.mkdirSync(output, { recursive: true });
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const evidence = [];

class Cdp {
    constructor(url) { this.ws = new WebSocket(url); this.id = 0; this.pending = new Map(); this.errors = []; this.failures = []; this.navigations = 0; }
    async open(events = true) {
        await new Promise((resolve, reject) => { this.ws.addEventListener('open', resolve, { once: true }); this.ws.addEventListener('error', reject, { once: true }); });
        this.ws.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.method === 'Page.frameNavigated' && !data.params.frame.parentId) this.navigations++;
            if (data.method === 'Runtime.exceptionThrown') this.errors.push(data.params.exceptionDetails.text);
            if (data.method === 'Log.entryAdded' && ['warning', 'error'].includes(data.params.entry.level)) this.errors.push(data.params.entry.text);
            if (data.method === 'Network.responseReceived' && data.params.response.status >= 500) this.failures.push({ url: data.params.response.url, status: data.params.response.status });
            if (data.id && this.pending.has(data.id)) { const pending = this.pending.get(data.id); this.pending.delete(data.id); data.error ? pending.reject(new Error(data.error.message)) : pending.resolve(data.result); }
        });
        if (events) for (const command of ['Page.enable', 'Runtime.enable', 'Network.enable', 'Log.enable']) await this.send(command);
    }
    send(method, params = {}) { const id = ++this.id; this.ws.send(JSON.stringify({ id, method, params })); return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject })); }
    async eval(expression) { const result = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }); if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text); return result.result.value; }
    async go(url) { await this.send('Page.navigate', { url: url.startsWith('http') ? url : base + url }); await this.loaded(`navigation ${url}`); }
    async loaded(label) { await wait(250); for (let n = 0; n < 80; n++) { if (await this.eval('document.readyState === "complete"')) return; await wait(100); } throw new Error(`Timeout: ${label}`); }
    async submit(selector, fields = {}, noValidate = false) {
        const before = this.navigations;
        await this.eval(`(() => { const form=document.querySelector(${JSON.stringify(selector)}); if(!form) throw Error('Missing form: '+${JSON.stringify(selector)}); form.noValidate=${JSON.stringify(noValidate)}; for(const [name,value] of Object.entries(${JSON.stringify(fields)})){const field=form.elements.namedItem(name);if(!field)throw Error('Missing field: '+name);if(field.type==='checkbox'||field.type==='radio')field.checked=Boolean(value);else field.value=value;} form.requestSubmit(); })()`);
        for (let n = 0; n < 300; n++) { await wait(100); if (this.navigations > before && await this.eval('document.readyState === "complete"')) return; }
        throw new Error(`Submission timeout: ${selector}`);
    }
    async viewport(width) { this.width = width; await this.send('Emulation.setDeviceMetricsOverride', { width, height: 1050, deviceScaleFactor: 1, mobile: width <= 390 }); await wait(100); }
    async screenshot(name) { const result = await this.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); fs.writeFileSync(path.join(output, name), Buffer.from(result.data, 'base64')); }
    async snapshot(journey) {
        const state = await this.eval(`({path:location.pathname+location.search,lang:document.documentElement.lang,dir:document.documentElement.dir,heading:document.querySelector('h1')?.innerText||null,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,errors:[...document.querySelectorAll('[role="alert"]')].map(x=>x.innerText.trim()).filter(Boolean)})`);
        evidence.push({ journey, width: this.width, ...state }); return state;
    }
    probe(url, method = 'GET') { return this.eval(`fetch(${JSON.stringify(url)},{method:${JSON.stringify(method)},credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.status)`); }
}

function otp(secret) {
    let bits = ''; for (const character of secret) bits += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(character).toString(2).padStart(5, '0');
    const bytes = []; for (let index = 0; index + 8 <= bits.length; index += 8) bytes.push(parseInt(bits.slice(index, index + 8), 2));
    const counter = Buffer.alloc(8); counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const digest = crypto.createHmac('sha1', Buffer.from(bytes)).update(counter).digest(); const offset = digest[19] & 15;
    return String((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).padStart(6, '0');
}

const rootVersion = await (await fetch(`${debug}/json/version`)).json();
const root = new Cdp(rootVersion.webSocketDebuggerUrl); await root.open(false);
const pages = [];
async function page() { const { browserContextId } = await root.send('Target.createBrowserContext'); const { targetId } = await root.send('Target.createTarget', { url: 'about:blank', browserContextId }); const targets = await (await fetch(`${debug}/json/list`)).json(); const result = new Cdp(targets.find(target => target.id === targetId).webSocketDebuggerUrl); await result.open(); await result.viewport(1440); pages.push(result); return result; }

async function login(p, email) {
    await p.go('/login'); await p.submit('form:has([name="password"])', { email, password: 'PlayNexusDemoOnly-2026' });
    const key = await p.eval(`document.querySelector('[data-mfa-setup-key]')?.innerText.trim()||null`);
    if (key) await p.submit('form:has([name="code"])', { current_password: 'PlayNexusDemoOnly-2026', code: otp(key) });
    await p.go('/app');
    if (await p.eval(`Boolean(document.querySelector('form[action*="/branch-context/"]'))`)) await p.submit('form[action*="/branch-context/"]');
}
async function locale(p, value, returnPath) {
    await p.eval(`(() => { const form=document.querySelector('form[action$="/locale"]'); const body=new URLSearchParams(new FormData(form)); body.set('locale',${JSON.stringify(value)}); return fetch(form.action,{method:'POST',body,credentials:'same-origin'}).then(r=>r.status); })()`);
    await p.go(returnPath);
}

try {
    const reception = await page(); const manager = await page(); const owner = await page(); const cashier = await page();
    await login(reception, 'demo.alpha.reception_staff@playnexus.test');
    await login(manager, 'demo.alpha.branch_manager@playnexus.test');
    await login(owner, 'demo.alpha.owner@playnexus.test');
    await login(cashier, 'demo.alpha.cashier@playnexus.test');

    await reception.go('/app'); evidence.push({ journey: 'reception-dashboard-family-action', status: await reception.probe('/app/families') });
    await reception.go('/app/families?q=01009990000'); await reception.snapshot('unknown-family-search');
    await reception.go('/app/families/create?phone=01008887766');
    await reception.submit('form[action$="/app/families"]', {
        guardian_name: 'Wave Two Guardian', phone: '01008887766', email: 'wave02.guardian@example.test', preferred_locale: 'en',
        child_name: 'Wave Two Child', date_of_birth: '2019-06-15', relationship_type: 'legal_guardian',
        emergency_contact_name: 'Wave Emergency Contact', emergency_contact_phone: '+201008887766', safety_notes: 'Operational safety context only', child_data_consent: true,
    });
    const familyPath = await reception.eval(`(() => { const anchor=[...document.querySelectorAll('a[href*="/app/families/"]')].find(a=>a.innerText.trim()==='Wave Two Guardian'); return anchor ? new URL(anchor.href).pathname : location.pathname; })()`);
    if (!/^\/app\/families\/\d+$/.test(familyPath)) throw new Error(`Family creation did not open profile: ${familyPath}`);
    await reception.go(familyPath);
    await reception.snapshot('family-created-profile');
    await reception.go('/app/families?q=%2B20%20100%20888%207766');
    const reuseCount = await reception.eval(`document.body.innerText.includes('Wave Two Guardian') ? 1 : 0`);
    if (reuseCount !== 1) throw new Error('Equivalent normalized phone did not find the canonical guardian');
    evidence.push({ journey: 'equivalent-phone-reuse', canonicalMatches: reuseCount });
    await reception.go(familyPath);
    await reception.submit('form:has([name="child_name"])', { child_name: 'Wave Two Child Updated', date_of_birth: '2019-06-16', emergency_contact_name: 'Wave Emergency Contact', emergency_contact_phone: '+201008887766', safety_notes: 'Updated operational safety context' });
    await reception.submit('form:has([name="consent_type"][value="child_data"])');
    if (!await reception.eval(`document.body.innerText.includes(${JSON.stringify('Wave Two Child Updated')}) && Boolean(document.querySelector('form:has([name="child_data_consent"])'))`)) throw new Error('Withdrawn child was not retained with renewed-consent action');
    await reception.submit('form:has([name="child_data_consent"])', { child_data_consent: true });
    await reception.snapshot('consent-renewed-profile');

    await owner.go('/app/privacy/retention'); await owner.snapshot('retention-dry-run');
    if (!await owner.eval(`[...document.querySelectorAll('tr')].some(row=>row.innerText.includes('Retention Eligible Guardian')&&row.innerText.includes('Eligible'))`)) throw new Error('Three-year retention eligibility was not rendered for the old closed family');
    const holdForm = 'form[action$="/app/privacy/retention/holds"]';
    const eligibleId = await owner.eval(`document.querySelector(${JSON.stringify(holdForm)}).elements.guardian_id.options[1]?.value`);
    await owner.submit(holdForm, { guardian_id: eligibleId, category: 'legal', reason: 'Browser verification preservation requirement.' });
    if (!await owner.eval(`Boolean(document.querySelector('form[action*="/privacy/retention/holds/"]'))`)) throw new Error('Active hold was not rendered');
    await owner.snapshot('retention-hold-active');
    await owner.submit('form[action*="/privacy/retention/holds/"]', { release_reason: 'Browser verification preservation need ended.' });

    await manager.go('/app/pricing'); await manager.snapshot('pricing-authorized');
    const pricingCreate = 'form[action$="/app/pricing"]:has([name="code"])';
    const pricingBranch = await manager.eval(`document.querySelector(${JSON.stringify(pricingCreate)}).elements.branch_id.options[1].value`);
    await manager.submit(pricingCreate, { branch_id: pricingBranch, code: 'W2-BROWSER', name: 'Wave 2 browser price', base_duration_minutes: 0, base_price_egp: '125.50', overtime_price_egp: '60.25' }, true);
    if (!(await manager.eval(`document.querySelector('[role="alert"]')?.innerText.length > 0`))) throw new Error('Invalid pricing edit did not produce a visible validation error');
    await manager.submit(pricingCreate, { branch_id: pricingBranch, code: 'W2-BROWSER', name: 'Wave 2 browser price', base_duration_minutes: 60, base_price_egp: '125.50', overtime_price_egp: '60.25' });
    if (!await manager.eval(`document.body.innerText.includes('Wave 2 browser price')`)) throw new Error('Valid pricing rule was not rendered');
    await manager.snapshot('pricing-valid-create');
    evidence.push({ journey: 'pricing-reception-denied', status: await reception.probe('/app/pricing', 'POST') });

    await reception.go('/app/tickets?tab=issue');
    const issueForm = 'form[action$="/app/tickets"]:has([name="family_assignment"])';
    const issueValues = await reception.eval(`(() => { const f=document.querySelector(${JSON.stringify(issueForm)}); const family=[...f.elements.family_assignment.options].find(o=>o.textContent.includes('Wave Two Child Updated')); return {branch_id:f.elements.branch_id.value||f.elements.branch_id.options[1].value,ticket_type_id:f.elements.ticket_type_id.options[1].value,family_assignment:family?.value,service_date:f.elements.service_date.value}; })()`);
    if (!issueValues.family_assignment) throw new Error('New family was not available for ticket issue');
    await reception.submit(issueForm, issueValues);
    const qrPayload = await reception.eval(`document.querySelector('[data-pn-ticket-qr]')?.dataset.qrPayload||null`);
    if (!qrPayload || !/^pnx_[A-Za-z0-9_-]{40,}$/.test(qrPayload)) {
        const ticketErrors = await reception.eval(`[...document.querySelectorAll('[role="alert"]')].map(x=>x.innerText.trim()).filter(Boolean)`);
        throw new Error(`Ticket did not render an opaque QR payload: ${JSON.stringify({ issueValues, ticketErrors, path: await reception.eval('location.href') })}`);
    }
    if (/Wave|Guardian|Child|0100|2019/.test(qrPayload)) throw new Error('QR payload contains recognizable family PII');
    await reception.snapshot('ticket-issued-qr'); await reception.screenshot('ticket-issued-qr.png');

    await manager.go('/app/tickets?tab=validate');
    const wrongBranch = await manager.eval(`(() => { const f=document.querySelector('form[action$="/app/tickets/scan"]'); const options=[...f.elements.branch_id.options].filter(o=>o.value); return options.at(-1).value; })()`);
    await manager.submit('form[action$="/app/tickets/scan"]', { branch_id: wrongBranch, code: qrPayload });
    evidence.push({ journey: 'ticket-wrong-branch', errors: await manager.eval(`[...document.querySelectorAll('[role="alert"]')].map(x=>x.innerText.trim()).filter(Boolean)`) });
    await reception.go('/app/tickets?tab=validate');
    const scanBranch = await reception.eval(`document.querySelector('form[action$="/app/tickets/scan"]').elements.branch_id.value||document.querySelector('form[action$="/app/tickets/scan"]').elements.branch_id.options[1].value`);
    await reception.submit('form[action$="/app/tickets/scan"]', { branch_id: scanBranch, code: qrPayload });
    await reception.snapshot('ticket-first-scan');
    await reception.submit('form[action$="/app/tickets/scan"]', { branch_id: scanBranch, code: qrPayload });
    await reception.snapshot('ticket-repeat-scan');

    const familyId = Number(familyPath.split('/').at(-1));
    evidence.push({ journey: 'foreign-family-concealed', status: await reception.probe(`/app/families/${familyId + 100000}`) });
    evidence.push({ journey: 'cashier-retention-denied', status: await cashier.probe('/app/privacy/retention') });
    evidence.push({ journey: 'cashier-pricing-mutation-denied', status: await cashier.probe('/app/pricing', 'POST') });

    const screens = [
        [reception, familyPath, 'family-profile'], [reception, '/app/families', 'family-list'], [reception, '/app/tickets?tab=history', 'tickets'],
        [manager, '/app/pricing', 'pricing'], [owner, '/app/privacy/retention', 'privacy'],
    ];
    for (const lang of ['en', 'ar']) for (const [p, url, name] of screens) {
        await p.go(url); await locale(p, lang, url);
        for (const width of [390, 820, 1440]) { await p.viewport(width); const state = await p.snapshot(`${name}-${lang}-${width}`); await p.screenshot(`${name}-${lang}-${width}.png`); if (state.overflow) throw new Error(`Horizontal overflow: ${name} ${lang} ${width}`); }
    }

    const expectedDenials = evidence.filter(row => /denied|concealed/.test(row.journey));
    if (expectedDenials.some(row => ![403, 404, 419, 422].includes(row.status))) throw new Error(`Unexpected authorization probe: ${JSON.stringify(expectedDenials)}`);
    const diagnostics = { journey: 'console-network', errors: pages.flatMap(p => p.errors).filter(message => !/status of 4\d\d|40[134]/.test(message)), serverFailures: pages.flatMap(p => p.failures) };
    evidence.push(diagnostics);
    if (diagnostics.errors.length || diagnostics.serverFailures.length) throw new Error(`Browser console or HTTP 5xx failures: ${JSON.stringify(diagnostics)}`);
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify(evidence, null, 2));
    process.stdout.write(JSON.stringify({ result: 'passed', checks: evidence.length, screenshots: 31, qrOpaque: true }, null, 2));
} catch (error) {
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ evidence, error: error.message }, null, 2)); throw error;
} finally { for (const p of pages) p.ws.close(); root.ws.close(); }
