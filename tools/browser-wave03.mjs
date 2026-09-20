import fs from 'node:fs';
import path from 'node:path';

const base = process.env.WAVE03_BASE_URL || 'http://127.0.0.1:8861';
const debug = process.env.WAVE03_CDP_URL || 'http://127.0.0.1:8862';
const output = path.resolve('deliverables/qa/wave03');
fs.mkdirSync(output, { recursive: true });
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const evidence = [];

class Cdp {
    constructor(url) {
        this.ws = new WebSocket(url);
        this.id = 0;
        this.pending = new Map();
        this.errors = [];
        this.failures = [];
        this.navigations = 0;
        this.width = 1440;
    }
    async open(events = true) {
        await new Promise((resolve, reject) => {
            this.ws.addEventListener('open', resolve, { once: true });
            this.ws.addEventListener('error', reject, { once: true });
        });
        this.ws.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.method === 'Page.frameNavigated' && !data.params.frame.parentId) this.navigations++;
            if (data.method === 'Runtime.exceptionThrown') this.errors.push(data.params.exceptionDetails.text);
            if (data.method === 'Log.entryAdded' && ['warning', 'error'].includes(data.params.entry.level)) this.errors.push(data.params.entry.text);
            if (data.method === 'Network.responseReceived' && data.params.response.status >= 500) this.failures.push({ url: data.params.response.url, status: data.params.response.status });
            if (data.id && this.pending.has(data.id)) {
                const pending = this.pending.get(data.id);
                this.pending.delete(data.id);
                data.error ? pending.reject(new Error(data.error.message)) : pending.resolve(data.result);
            }
        });
        if (events) for (const command of ['Page.enable', 'Runtime.enable', 'Network.enable', 'Log.enable']) await this.send(command);
    }
    send(method, params = {}) {
        const id = ++this.id;
        this.ws.send(JSON.stringify({ id, method, params }));
        return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
    }
    async eval(expression) {
        const result = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        return result.result.value;
    }
    async go(url) {
        await this.send('Page.navigate', { url: url.startsWith('http') ? url : base + url });
        await this.loaded(`navigation ${url}`);
    }
    async loaded(label) {
        await wait(250);
        for (let n = 0; n < 100; n++) {
            if (await this.eval('document.readyState === "complete"')) return;
            await wait(100);
        }
        throw new Error(`Timeout: ${label}`);
    }
    async submit(selector, fields = {}, noValidate = false) {
        const before = this.navigations;
        await this.eval(`(() => {
            const form=document.querySelector(${JSON.stringify(selector)});
            if(!form) throw Error('Missing form: '+${JSON.stringify(selector)});
            form.noValidate=${JSON.stringify(noValidate)};
            for(const [name,value] of Object.entries(${JSON.stringify(fields)})) {
                const field=form.elements.namedItem(name);
                if(!field) throw Error('Missing field: '+name);
                if(field.type==='checkbox'||field.type==='radio') field.checked=Boolean(value); else field.value=value;
                field.dispatchEvent(new Event('change',{bubbles:true}));
                field.dispatchEvent(new Event('input',{bubbles:true}));
            }
            form.requestSubmit();
        })()`);
        for (let n = 0; n < 300; n++) {
            await wait(100);
            if (this.navigations > before && await this.eval('document.readyState === "complete"')) return;
        }
        throw new Error(`Submission timeout: ${selector}`);
    }
    async viewport(width) {
        this.width = width;
        await this.send('Emulation.setDeviceMetricsOverride', { width, height: 1050, deviceScaleFactor: 1, mobile: width <= 390 });
        await wait(100);
    }
    async screenshot(name) {
        const result = await this.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        fs.writeFileSync(path.join(output, name), Buffer.from(result.data, 'base64'));
    }
    async snapshot(journey) {
        const state = await this.eval(`({
            path:location.pathname+location.search,
            lang:document.documentElement.lang,
            dir:document.documentElement.dir,
            heading:document.querySelector('h1')?.innerText||null,
            overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,
            alerts:[...document.querySelectorAll('[role="alert"]')].map(x=>x.innerText.trim()).filter(Boolean)
        })`);
        evidence.push({ journey, width: this.width, ...state });
        return state;
    }
    async post(url, body) {
        return this.eval(`(() => fetch(${JSON.stringify(url)}, {
            method:'POST', credentials:'same-origin',
            headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},
            body:JSON.stringify(${JSON.stringify(body)})
        }).then(async r=>({status:r.status,body:(await r.text()).slice(0,300)})))()`);
    }
}

const rootVersion = await (await fetch(`${debug}/json/version`)).json();
const root = new Cdp(rootVersion.webSocketDebuggerUrl);
await root.open(false);
const pages = [];
async function page() {
    const { browserContextId } = await root.send('Target.createBrowserContext');
    const { targetId } = await root.send('Target.createTarget', { url: 'about:blank', browserContextId });
    const targets = await (await fetch(`${debug}/json/list`)).json();
    const result = new Cdp(targets.find(target => target.id === targetId).webSocketDebuggerUrl);
    await result.open();
    await result.viewport(1440);
    pages.push(result);
    return result;
}
async function login(p, email) {
    await p.go('/login');
    await p.submit('form:has([name="password"])', { email, password: 'PlayNexusDemoOnly-2026' });
    await p.go('/app');
    if (await p.eval(`Boolean(document.querySelector('form[action*="/branch-context/"]'))`)) await p.submit('form[action*="/branch-context/"]');
}
async function locale(p, value, returnPath) {
    await p.eval(`(() => {
        const form=document.querySelector('form[action$="/locale"]');
        const body=new URLSearchParams(new FormData(form)); body.set('locale',${JSON.stringify(value)});
        return fetch(form.action,{method:'POST',body,credentials:'same-origin'}).then(r=>r.status);
    })()`);
    await p.go(returnPath);
}
async function cardForm(p, child, suffix) {
    return p.eval(`(() => {
        const card=[...document.querySelectorAll('[data-pn-session-card]')].find(node=>node.innerText.includes(${JSON.stringify(child)}));
        const form=card?.querySelector('form[action$=${JSON.stringify(suffix)}]');
        return form?.getAttribute('action')||null;
    })()`);
}
async function chooseBranch(p, formSelector, text) {
    return p.eval(`(() => {
        const select=document.querySelector(${JSON.stringify(formSelector)}).elements.branch_id;
        return [...select.options].find(option=>option.textContent.includes(${JSON.stringify(text)}))?.value||null;
    })()`);
}

try {
    const reception = await page();
    const manager = await page();
    const cashier = await page();
    const beta = await page();
    await login(reception, 'demo.alpha.reception_staff@playnexus.test');
    await login(manager, 'demo.alpha.branch_manager@playnexus.test');
    await login(cashier, 'demo.alpha.cashier@playnexus.test');
    await login(beta, 'demo.beta.owner@playnexus.test');

    await reception.go('/app');
    evidence.push({ journey: 'reception-dashboard-session-action', sessionsLink: await reception.eval(`Boolean(document.querySelector('a[href*="/app/sessions"]'))`) });
    await reception.go('/app/sessions');
    await reception.snapshot('reception-active-board');
    const scanForm = 'form[data-pn-session-check-in]';
    const mainBranch = await chooseBranch(reception, scanForm, 'Main');
    await reception.submit(scanForm, { branch_id: mainBranch, code: 'w3_valid_opaque_checkin_token_000000000000000001' });
    if (!await reception.eval(`document.body.innerText.includes('[W3] Check-in Child')`)) throw new Error('Valid check-in did not create the operational session');
    await reception.snapshot('valid-ticket-check-in');
    await reception.submit(scanForm, { branch_id: mainBranch, code: 'w3_valid_opaque_checkin_token_000000000000000001' });
    evidence.push({ journey: 'repeat-ticket-check-in', result: await reception.eval(`document.querySelector('#sessions-check-in-result')?.innerText||''`) });
    await reception.submit(scanForm, { branch_id: mainBranch, code: 'w3_wrong_branch_token_000000000000000000001' });
    evidence.push({ journey: 'wrong-branch-ticket', result: await reception.eval(`document.querySelector('#sessions-check-in-result')?.innerText||''`) });

    await manager.go('/app/sessions');
    const managerScan = 'form[data-pn-session-check-in]';
    const northBranch = await chooseBranch(manager, managerScan, 'North');
    await manager.submit(managerScan, { branch_id: northBranch, code: 'w3_capacity_token_00000000000000000000001' });
    evidence.push({ journey: 'capacity-full-denial', result: await manager.eval(`document.querySelector('#sessions-check-in-result')?.innerText||''`) });

    await manager.go('/app/sessions?q=PN-W3-CHECKOUT');
    if (!await manager.eval(`document.body.innerText.includes('[W3] Checkout Child')`)) throw new Error('Ticket display-code search did not find the session');
    const extensionAction = await cardForm(manager, '[W3] Checkout Child', '/extend');
    if (!extensionAction) throw new Error('Authorized extension control was missing');
    await manager.submit(`form[action="${extensionAction}"]`, { extension_units: '1' });
    await manager.go('/app/sessions?q=%5BW3%5D%20Checkout%20Child');
    evidence.push({ journey: 'authorized-extension', rendered: await manager.eval(`document.body.innerText.includes('30')`) });

    const checkoutAction = await cardForm(manager, '[W3] Checkout Child', '/checkout');
    const checkoutFacts = await manager.eval(`(() => {
        const form=document.querySelector(${JSON.stringify(`form[action="${checkoutAction}"]`)});
        return {guardian_id:[...form.elements.guardian_id.options].find(o=>o.value)?.value,lock:Number(form.elements.expected_lock_version.value),session:Number(form.elements.checkout_session_id.value)};
    })()`);
    const wrongVerifier = await manager.post(checkoutAction, { ...checkoutFacts, verification_method: 'phone_last_four', phone_last_four: '0000', idempotency_key: crypto.randomUUID(), expected_lock_version: checkoutFacts.lock });
    evidence.push({ journey: 'wrong-release-verifier-denied', status: wrongVerifier.status });
    await manager.submit(`form[action="${checkoutAction}"]`, { verification_method: 'phone_last_four', guardian_id: String(checkoutFacts.guardian_id), phone_last_four: '5678' });
    if (!await manager.eval(`Boolean(document.querySelector('[data-pn-frozen-invoice]'))`)) throw new Error('Checkout did not freeze an explainable quote');
    await manager.snapshot('pending-payment-frozen-quote');
    await manager.screenshot('pending-payment-frozen-quote-en-1440.png');
    evidence.push({ journey: 'repeat-checkout-safe', result: await manager.post(checkoutAction, { ...checkoutFacts, verification_method: 'phone_last_four', guardian_id: checkoutFacts.guardian_id, phone_last_four: '5678', idempotency_key: crypto.randomUUID(), expected_lock_version: checkoutFacts.lock }) });

    await cashier.go('/app/pos');
    const pendingCard = await cashier.eval(`(() => { const card=[...document.querySelectorAll('[data-pending-session]')].find(x=>x.innerText.includes('[W3] Checkout Child')); return card ? {amount:card.dataset.amountMinor,currency:card.dataset.currency} : null; })()`);
    if (!pendingCard) throw new Error('Pending-payment session was absent from cashier handoff');
    evidence.push({ journey: 'cashier-exact-handoff', ...pendingCard });
    await cashier.eval(`(() => { const card=[...document.querySelectorAll('[data-pending-session]')].find(x=>x.innerText.includes('[W3] Checkout Child')); card.querySelector('[data-settle]').click(); })()`);
    for (let n = 0; n < 100; n++) { if (await cashier.eval(`Boolean([...document.querySelectorAll('[data-pending-session]')].find(x=>x.innerText.includes('[W3] Checkout Child'))?.querySelector('[data-settle-success]:not(.hidden)'))`)) break; await wait(100); }
    if (!await cashier.eval(`Boolean([...document.querySelectorAll('[data-pending-session]')].find(x=>x.innerText.includes('[W3] Checkout Child'))?.querySelector('[data-settle-success]:not(.hidden)'))`)) throw new Error('Cash settlement did not complete in the real UI');
    await cashier.snapshot('cashier-settlement-completed');

    await manager.go('/app/sessions?q=%5BW3%5D%20Checkout%20Child&status=completed');
    if (!await manager.eval(`Boolean(document.querySelector('[data-pn-release-history]'))`)) throw new Error('Completed session omitted guardian release evidence');
    await manager.snapshot('completed-release-history');

    await manager.go('/app/sessions?q=%5BW3%5D%20Override%20Child');
    const overrideAction = await cardForm(manager, '[W3] Override Child', '/checkout');
    await manager.submit(`form[action="${overrideAction}"]`, { verification_method: 'manager_override', override_reason: 'Guardian verifier unavailable; manager confirmed approved handoff.' });
    if (!await manager.eval(`Boolean(document.querySelector('[data-pn-manager-override-history]'))`)) throw new Error('Manager override evidence was not displayed');
    await manager.snapshot('manager-override-audited');

    await reception.go('/app/sessions?q=%5BW3%5D%20Check-in%20Child');
    const receptionCheckoutAction = await cardForm(reception, '[W3] Check-in Child', '/checkout');
    const receptionCheckoutFacts = await reception.eval(`(() => { const form=document.querySelector(${JSON.stringify(`form[action="${receptionCheckoutAction}"]`)}); return {lock:Number(form.elements.expected_lock_version.value)}; })()`);
    const overrideSession = Number((receptionCheckoutAction.match(/sessions\/(\d+)\/checkout/) || [])[1]);
    const receptionOverride = await reception.post(`/app/sessions/${overrideSession}/checkout`, { verification_method: 'manager_override', override_reason: 'Unauthorized reception attempt must be rejected.', expected_lock_version: receptionCheckoutFacts.lock, idempotency_key: crypto.randomUUID() });
    evidence.push({ journey: 'reception-manager-override-denied', status: receptionOverride.status });
    const cashierMutation = await cashier.post(`/app/sessions/${overrideSession}/extend`, { extension_units: 1, expected_lock_version: 1, idempotency_key: crypto.randomUUID() });
    evidence.push({ journey: 'cashier-session-mutation-denied', status: cashierMutation.status });
    const foreignSession = await beta.post(`/app/sessions/${checkoutFacts.session}/checkout`, { verification_method: 'manager_override', override_reason: 'Cross-tenant attempt must not disclose the record.', expected_lock_version: 1, idempotency_key: crypto.randomUUID() });
    evidence.push({ journey: 'foreign-tenant-session-concealed', status: foreignSession.status });

    const today = new Date().toISOString().slice(0, 10);
    await manager.go(`/app/sessions?service_date=${today}&status=paused`);
    evidence.push({ journey: 'paused-filter-rejected', status: await manager.eval(`document.querySelector('#sessions-errors')?422:200`), pausedOption: await manager.eval(`Boolean(document.querySelector('#sessions-status option[value="paused"]'))`) });

    const screens = [[manager, '/app/sessions', 'sessions'], [cashier, '/app/pos', 'cashier-pos']];
    for (const lang of ['en', 'ar']) for (const [p, url, name] of screens) {
        await p.go(url); await locale(p, lang, url);
        for (const width of [390, 820, 1440]) {
            await p.viewport(width);
            await p.eval('scrollTo(0,0)');
            const state = await p.snapshot(`${name}-${lang}-${width}`);
            await p.screenshot(`${name}-${lang}-${width}.png`);
            if (state.overflow) throw new Error(`Horizontal overflow: ${name} ${lang} ${width}`);
        }
    }

    const denials = evidence.filter(row => /denied|concealed/.test(row.journey));
    if (denials.some(row => ![403, 404, 409, 422].includes(row.status))) throw new Error(`Unexpected security response: ${JSON.stringify(denials)}`);
    const diagnostics = { journey: 'console-network', errors: pages.flatMap(p => p.errors).filter(message => !/status of 4\d\d|40[134]/.test(message)), serverFailures: pages.flatMap(p => p.failures) };
    evidence.push(diagnostics);
    if (diagnostics.errors.length || diagnostics.serverFailures.length) throw new Error(`Browser console or HTTP 5xx failures: ${JSON.stringify(diagnostics)}`);
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify(evidence, null, 2));
    process.stdout.write(JSON.stringify({ result: 'passed', checks: evidence.length, screenshots: 13, pendingAmountMinor: pendingCard.amount }, null, 2));
} catch (error) {
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ evidence, error: error.message }, null, 2));
    throw error;
} finally {
    for (const p of pages) p.ws.close();
    root.ws.close();
}
