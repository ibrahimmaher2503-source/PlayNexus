import fs from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8791';
const evidenceDir = path.resolve('deliverables/qa/checkpoint-01-platform');
fs.mkdirSync(evidenceDir, { recursive: true });

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

class Cdp {
    constructor(wsUrl) {
        this.id = 0;
        this.pending = new Map();
        this.ws = new WebSocket(wsUrl);
        this.consoleErrors = [];
    }

    async open() {
        await new Promise((resolve, reject) => {
            this.ws.addEventListener('open', resolve, { once: true });
            this.ws.addEventListener('error', reject, { once: true });
        });
        this.ws.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.method === 'Runtime.exceptionThrown') {
                this.consoleErrors.push(message.params.exceptionDetails?.text ?? 'Runtime exception');
            }
            if (message.method === 'Log.entryAdded' && ['error', 'warning'].includes(message.params.entry?.level)) {
                this.consoleErrors.push(message.params.entry.text);
            }
            if (!message.id || !this.pending.has(message.id)) return;
            const { resolve, reject } = this.pending.get(message.id);
            this.pending.delete(message.id);
            message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        });
        await this.send('Page.enable');
        await this.send('Runtime.enable');
        await this.send('Network.enable');
        await this.send('Log.enable');
    }

    send(method, params = {}) {
        const id = ++this.id;
        this.ws.send(JSON.stringify({ id, method, params }));
        return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
    }

    async evaluate(expression) {
        const result = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    }

    async waitReady(expectedPath = null) {
        for (let attempt = 0; attempt < 40; attempt++) {
            await sleep(125);
            const state = await this.evaluate(`({ready: document.readyState, path: location.pathname})`);
            if (state.ready === 'complete' && (!expectedPath || state.path === expectedPath)) return state;
        }
        throw new Error(`Timed out waiting for ${expectedPath ?? 'page readiness'}`);
    }

    async navigate(url, expectedPath = null) {
        await this.send('Page.navigate', { url });
        await sleep(350);
        await this.waitReady(expectedPath);
    }

    async screenshot(name) {
        const result = await this.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
        fs.writeFileSync(path.join(evidenceDir, name), Buffer.from(result.data, 'base64'));
    }

    async cookies() {
        return (await this.send('Network.getAllCookies')).cookies;
    }

    async useCookies(cookies) {
        await this.send('Network.clearBrowserCookies');
        if (cookies.length > 0) await this.send('Network.setCookies', { cookies });
    }

    close() { this.ws.close(); }
}

async function connect(port) {
    const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    const pages = targets.filter((item) => item.type === 'page');
    const target = pages.find((item) => item.url.startsWith(base))
        ?? pages.find((item) => item.url === 'about:blank')
        ?? pages[0];
    if (!target) throw new Error(`No browser page target on ${port}`);
    const cdp = new Cdp(target.webSocketDebuggerUrl);
    await cdp.open();
    return cdp;
}

async function submitLogin(cdp, platform, email, password) {
    const prefix = platform ? '/platform' : '';
    await cdp.navigate(`${base}${prefix}/login`);
    if (await cdp.evaluate('location.pathname') !== `${prefix}/login`) return;
    await cdp.evaluate(`(() => {
        const form = document.querySelector('form:has([name=password])');
        form.querySelector('[name=email]').value = ${JSON.stringify(email)};
        form.querySelector('[name=password]').value = ${JSON.stringify(password)};
        form.requestSubmit();
    })()`);
    await sleep(500);
    await cdp.waitReady();
}

async function updateTenantStatus(admin, tenantText, status, reason) {
    await admin.navigate(`${base}/platform/tenants`, '/platform/tenants');
    const changed = await admin.evaluate(`(() => {
        const row = [...document.querySelectorAll('tbody tr')].find((candidate) => candidate.innerText.includes(${JSON.stringify(tenantText)}));
        if (!row) return false;
        const form = row.querySelector('form[action*="/status"]');
        form.querySelector('[name=status]').value = ${JSON.stringify(status)};
        const reasonCode = form.querySelector('[name=reason_code]');
        if (reasonCode) reasonCode.value = ${JSON.stringify(status === 'suspended' ? 'access_review' : 'setup_change')};
        form.querySelector('[name=reason]').value = ${JSON.stringify(reason)};
        form.onsubmit = null;
        HTMLFormElement.prototype.submit.call(form);
        return true;
    })()`);
    if (!changed) throw new Error(`Tenant row not found: ${tenantText}`);
    await sleep(500);
    await admin.waitReady('/platform/tenants');
}

const admin = await connect(8810);
const tenant = admin;
const results = [];

try {
    await admin.useCookies([]);
    await submitLogin(admin, true, 'platform.browser@playnexus.test', 'PlayNexusDemoOnly-2026');
    results.push({ journey: 'platform-login', path: await admin.evaluate('location.pathname'), title: await admin.evaluate('document.title') });
    await admin.screenshot('01-platform-tenants-en.png');

    await tenant.useCookies([]);
    await submitLogin(tenant, false, 'demo.alpha.owner@playnexus.test', 'PlayNexusDemoOnly-2026');
    const tenantCookies = await tenant.cookies();
    results.push({ journey: 'tenant-established-session', path: await tenant.evaluate('location.pathname') });

    await admin.useCookies([]);
    await submitLogin(admin, true, 'platform.browser@playnexus.test', 'PlayNexusDemoOnly-2026');

    const unique = Date.now().toString();
    const tenantName = `Checkpoint Venue ${unique}`;
    const invitation = await admin.evaluate(`(async () => {
        const form = document.querySelector('form[action$="/platform/tenants"]');
        const values = {
            internal_identifier: 'CP-${unique}', name: ${JSON.stringify(tenantName)}, plan_reference: 'starter',
            initial_owner_name: 'Checkpoint Owner', initial_owner_email: 'checkpoint-${unique}@example.test'
        };
        for (const [name, value] of Object.entries(values)) form.querySelector('[name="' + name + '"]').value = value;
        form.requestSubmit();
        return true;
    })()`);
    if (!invitation) throw new Error('Provision form missing');
    await sleep(600);
    await admin.waitReady('/platform/tenants');
    const invitationUrl = await admin.evaluate(`document.body.innerText.split(/\\s+/).find((value) => value.includes('/owner-invitations/')) ?? null`);
    if (!invitationUrl) throw new Error('One-time invitation link was not rendered');
    results.push({ journey: 'tenant-created', tenantName, invitationLinkRendered: true });
    await admin.screenshot('02-tenant-created.png');

    const detailHref = await admin.evaluate(`([...document.querySelectorAll('a')].find((a) => a.textContent.includes(${JSON.stringify(tenantName)}))?.href) ?? null`);
    if (!detailHref) throw new Error('Tenant detail link missing');
    await admin.navigate(detailHref);
    results.push({ journey: 'tenant-detail', path: await admin.evaluate('location.pathname'), piiTermsPresent: await admin.evaluate(`document.body.innerText.includes('Guardian') || document.body.innerText.includes('Child')`) });
    await admin.screenshot('03-tenant-detail.png');

    await admin.navigate(`${base}/platform/support-access`, '/platform/support-access');
    const grantSubmitted = await admin.evaluate(`(() => {
        const form = document.querySelector('form[action$="/platform/support-access"]');
        const option = [...form.querySelector('[name=target_tenant_id]').options].find((item) => item.textContent.includes('[DEMO] Nile Play Center Alpha'));
        if (!option) return false;
        form.querySelector('[name=target_tenant_id]').value = option.value;
        form.querySelector('[name=scope]').value = 'branch_configuration_read';
        form.querySelector('[name=duration_minutes]').value = '30';
        form.querySelector('[name=reason]').value = 'Verify branch configuration reported by the pilot tenant.';
        form.querySelector('[name=support_ticket]').value = 'CP01-BROWSER';
        form.querySelector('[name=current_password]').value = 'PlayNexusDemoOnly-2026';
        form.requestSubmit();
        return true;
    })()`);
    if (!grantSubmitted) throw new Error('Support grant form could not be submitted');
    await sleep(600);
    await admin.waitReady();
    results.push({ journey: 'break-glass-grant', path: await admin.evaluate('location.pathname'), containsBranchConfig: await admin.evaluate(`document.body.innerText.includes('ALPHA-MAIN')`) });
    await admin.screenshot('04-break-glass-branch-config.png');

    await updateTenantStatus(admin, '[DEMO] Nile Play Center Alpha', 'suspended', 'Browser checkpoint verifies established-session revocation.');
    await tenant.useCookies(tenantCookies);
    await tenant.navigate(`${base}/app`);
    await sleep(1000);
    const suspendedPath = await tenant.evaluate('location.pathname');
    results.push({ journey: 'suspended-existing-session', path: suspendedPath, loginFormVisible: await tenant.evaluate(`Boolean(document.querySelector('form:has([name=password])'))`) });
    if (suspendedPath !== '/login') throw new Error(`Suspended tenant session remained at ${suspendedPath}`);
    await tenant.screenshot('05-suspended-tenant-redirect.png');

    await admin.useCookies([]);
    await submitLogin(admin, true, 'platform.browser@playnexus.test', 'PlayNexusDemoOnly-2026');
    await updateTenantStatus(admin, '[DEMO] Nile Play Center Alpha', 'active', 'Browser checkpoint restores administrative tenant access.');
    await tenant.useCookies([]);
    await submitLogin(tenant, false, 'demo.alpha.owner@playnexus.test', 'PlayNexusDemoOnly-2026');
    results.push({ journey: 'reactivated-tenant-login', path: await tenant.evaluate('location.pathname') });

    await admin.useCookies([]);
    await submitLogin(admin, true, 'platform.browser@playnexus.test', 'PlayNexusDemoOnly-2026');
    const localeForm = await admin.evaluate(`(() => { const f = document.querySelector('form[action$="/locale"]'); if (!f) return false; f.querySelector('[name=locale]').value='ar'; HTMLFormElement.prototype.submit.call(f); return true; })()`);
    if (localeForm) { await sleep(400); await admin.waitReady(); }
    const platformLanguage = await admin.evaluate('document.documentElement.lang');
    results.push({ journey: 'platform-arabic-rtl', lang: platformLanguage, dir: await admin.evaluate('document.documentElement.dir'), overflow: await admin.evaluate('document.documentElement.scrollWidth > document.documentElement.clientWidth') });
    if (platformLanguage !== 'ar') throw new Error(`Arabic locale did not activate; received ${platformLanguage}`);
    await admin.screenshot('06-platform-tenants-ar.png');

    results.push({ journey: 'browser-console', errors: admin.consoleErrors });
    fs.writeFileSync(path.join(evidenceDir, 'browser-results.json'), JSON.stringify(results, null, 2));
    process.stdout.write(JSON.stringify(results, null, 2));
} finally {
    admin.close();
}
