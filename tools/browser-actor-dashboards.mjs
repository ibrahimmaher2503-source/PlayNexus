import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const base = 'http://127.0.0.1:8841';
const debug = 'http://127.0.0.1:8842';
const output = path.resolve('deliverables/qa/actor-dashboards');
fs.mkdirSync(output, { recursive: true });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const evidence = [];

class Cdp {
    constructor(url) {
        this.ws = new WebSocket(url);
        this.id = 0;
        this.pending = new Map();
        this.errors = [];
        this.failures = [];
        this.navigations = 0;
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
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    }
    async go(url) {
        await this.send('Page.navigate', { url: url.startsWith('http') ? url : base + url });
        await pause(300);
        for (let n = 0; n < 50; n++) {
            if (await this.eval('document.readyState === "complete"')) return;
            await pause(100);
        }
        throw new Error(`Navigation timeout: ${url}`);
    }
    async submit(selector, fields = {}) {
        const before = this.navigations;
        await this.eval(`(() => { const form=document.querySelector(${JSON.stringify(selector)}); if(!form) throw Error('Missing form: '+${JSON.stringify(selector)}); for(const [name,value] of Object.entries(${JSON.stringify(fields)})){const field=form.elements.namedItem(name);if(!field)throw Error('Missing field: '+name);field.value=value;}form.requestSubmit(); })()`);
        for (let n = 0; n < 80; n++) {
            await pause(100);
            if (this.navigations > before && await this.eval('document.readyState === "complete"')) return;
        }
        throw new Error(`Submission timeout: ${selector}`);
    }
    async viewport(width) {
        this.width = width;
        await this.send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
        await pause(100);
    }
    async screenshot(name) {
        const result = await this.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        fs.writeFileSync(path.join(output, name), Buffer.from(result.data, 'base64'));
    }
    async snapshot(journey) {
        const state = await this.eval(`({path:location.pathname,lang:document.documentElement.lang,dir:document.documentElement.dir,actor:document.querySelector('[data-pn-actor]')?.dataset.pnActor||'platform',heading:document.querySelector('h1')?.innerText,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,primary:document.querySelector('[data-pn-primary-action]')?.innerText||null})`);
        evidence.push({ journey, width: this.width, ...state });
        return state;
    }
    probe(url) {
        return this.eval(`fetch(${JSON.stringify(url)},{credentials:'same-origin',headers:{Accept:'application/json'}}).then(response=>response.status)`);
    }
}

function otp(secret) {
    let bits = '';
    for (const character of secret) bits += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(character).toString(2).padStart(5, '0');
    const bytes = [];
    for (let index = 0; index + 8 <= bits.length; index += 8) bytes.push(parseInt(bits.slice(index, index + 8), 2));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const digest = crypto.createHmac('sha1', Buffer.from(bytes)).update(counter).digest();
    const offset = digest[19] & 15;
    return String((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).padStart(6, '0');
}

const version = await (await fetch(`${debug}/json/version`)).json();
const browser = new Cdp(version.webSocketDebuggerUrl);
await browser.open(false);
const pages = [];

async function createPage() {
    const { browserContextId } = await browser.send('Target.createBrowserContext');
    const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank', browserContextId });
    const targets = await (await fetch(`${debug}/json/list`)).json();
    const page = new Cdp(targets.find(target => target.id === targetId).webSocketDebuggerUrl);
    await page.open();
    await page.viewport(1440);
    pages.push(page);
    return page;
}

async function login(page, email, platform = false) {
    await page.go(platform ? '/platform/login' : '/login');
    await page.submit('form:has([name="password"])', { email, password: 'PlayNexusDemoOnly-2026' });
    if (platform) {
        await page.go('/platform');
        const key = await page.eval(`document.querySelector('[data-mfa-setup-key]')?.innerText.trim()`);
        if (key) {
            await page.submit('form:has([name="code"])', { current_password: 'PlayNexusDemoOnly-2026', code: otp(key) });
            await page.go('/platform');
        }
    }
}

async function setLocale(page, locale) {
    await page.eval(`(() => { const form=document.querySelector('form[action$="/locale"]'); const body=new URLSearchParams(new FormData(form)); body.set('locale',${JSON.stringify(locale)}); return fetch(form.action,{method:'POST',body,credentials:'same-origin'}).then(response=>response.status); })()`);
    await page.go(await page.eval('location.pathname'));
}

try {
    const actors = {
        platform: { page: await createPage(), email: 'wave01.platform@playnexus.test', platform: true, expected: 'platform' },
        owner: { page: await createPage(), email: 'demo.alpha.owner@playnexus.test', expected: 'owner' },
        manager: { page: await createPage(), email: 'demo.alpha.branch_manager@playnexus.test', expected: 'branch_manager' },
        reception: { page: await createPage(), email: 'demo.alpha.reception_staff@playnexus.test', expected: 'reception' },
        cashier: { page: await createPage(), email: 'demo.alpha.cashier@playnexus.test', expected: 'cashier' },
    };

    for (const actor of Object.values(actors)) await login(actor.page, actor.email, actor.platform);

    await actors.platform.page.go('/platform');
    await actors.platform.page.snapshot('platform-dashboard');
    for (const url of ['/platform/tenants', '/platform/support-access']) {
        await actors.platform.page.go(url);
        evidence.push({ journey: `platform-action-${url}`, path: await actors.platform.page.eval('location.pathname') });
    }

    await actors.owner.page.go('/app');
    await actors.owner.page.snapshot('owner-cross-branch-dashboard');
    for (const url of ['/app/branches/manage', '/app/staff', '/app/reports', '/app/tenant/settings']) {
        await actors.owner.page.go(url);
        evidence.push({ journey: `owner-action-${url}`, path: await actors.owner.page.eval('location.pathname') });
    }

    await actors.manager.page.go('/app');
    if ((await actors.manager.page.eval(`document.querySelector('[data-pn-actor]')?.dataset.pnActor`)) === 'staff') {
        await actors.manager.page.submit('form[action*="/branch-context/"]');
    }
    await actors.manager.page.snapshot('manager-assigned-branch-dashboard');
    evidence.push({ journey: 'manager-query-tamper', actor: await actors.manager.page.eval(`fetch('/app?branch_id=999999',{credentials:'same-origin'}).then(()=>document.querySelector('[data-pn-actor]')?.dataset.pnActor)`) });
    evidence.push({ journey: 'manager-foreign-branch-denied', status: await actors.manager.page.probe('/app/branches/999999/settings') });
    await actors.manager.page.go('/app/sessions');
    evidence.push({ journey: 'manager-operational-action', path: await actors.manager.page.eval('location.pathname') });

    await actors.reception.page.go('/app');
    await actors.reception.page.snapshot('reception-dashboard');
    for (const url of ['/app/families', '/app/tickets', '/app/sessions']) {
        await actors.reception.page.go(url);
        evidence.push({ journey: `reception-action-${url}`, path: await actors.reception.page.eval('location.pathname') });
    }
    evidence.push({ journey: 'reception-admin-denied', status: await actors.reception.page.probe('/app/tenant/settings') });

    await actors.cashier.page.go('/app');
    await actors.cashier.page.snapshot('cashier-dashboard');
    for (const url of ['/app/pos', '/app/sessions?status=pending_payment', '/app/transactions']) {
        await actors.cashier.page.go(url);
        evidence.push({ journey: `cashier-action-${url}`, path: await actors.cashier.page.eval('location.pathname') });
    }
    evidence.push({ journey: 'cashier-staff-denied', status: await actors.cashier.page.probe('/app/staff') });

    for (const [name, actor] of Object.entries(actors)) {
        for (const locale of ['en', 'ar']) {
            await actor.page.go(actor.platform ? '/platform' : '/app');
            await setLocale(actor.page, locale);
            for (const width of [390, 820, 1440]) {
                await actor.page.viewport(width);
                await actor.page.snapshot(`${name}-${locale}-${width}`);
                await actor.page.screenshot(`${name}-${locale}-${width}.png`);
            }
        }
    }

    const mainSnapshots = evidence.filter(item => item.actor);
    for (const [name, actor] of Object.entries(actors)) {
        const actorSnapshots = mainSnapshots.filter(item => typeof item.path === 'string' && (item.journey.startsWith(`${name}-`) || item.journey === `${name}-dashboard`));
        if (!actorSnapshots.length) throw new Error(`No dashboard evidence for ${name}`);
        if (actorSnapshots.some(item => item.path !== (actor.platform ? '/platform' : '/app'))) throw new Error(`Dashboard redirect escaped expected scope for ${name}`);
        if (!actor.platform && !actorSnapshots.some(item => item.actor === actor.expected)) throw new Error(`Wrong role composition for ${name}`);
        if (actor.platform && actorSnapshots.some(item => item.heading !== (item.lang === 'ar' ? 'نظرة عامة على المنصة' : 'Platform overview'))) throw new Error('Platform dashboard was not retained across locale/viewport checks');
    }
    if (evidence.some(item => item.overflow)) throw new Error('Horizontal overflow detected');
    if (evidence.find(item => item.journey === 'reception-admin-denied')?.status !== 403) throw new Error('Reception administration denial failed');
    if (evidence.find(item => item.journey === 'cashier-staff-denied')?.status !== 403) throw new Error('Cashier staff denial failed');
    if (evidence.find(item => item.journey === 'manager-foreign-branch-denied')?.status !== 404) throw new Error('Manager branch isolation failed');
    const diagnostics = { journey: 'console-network', errors: pages.flatMap(page => page.errors).filter(message => !/40[134]/.test(message)), serverFailures: pages.flatMap(page => page.failures) };
    evidence.push(diagnostics);
    if (diagnostics.errors.length || diagnostics.serverFailures.length) throw new Error('Browser console or HTTP 5xx failures detected');

    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify(evidence, null, 2));
    process.stdout.write(JSON.stringify({ result: 'passed', checks: evidence.length, screenshots: 30 }, null, 2));
} catch (error) {
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ evidence, error: error.message }, null, 2));
    throw error;
} finally {
    for (const page of pages) page.ws.close();
    browser.ws.close();
}
