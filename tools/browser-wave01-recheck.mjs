import fs from 'node:fs';
import path from 'node:path';

const output = path.resolve('deliverables/qa/wave-01');
const version = await (await fetch('http://127.0.0.1:8822/json/version')).json();
const browserSocket = new WebSocket(version.webSocketDebuggerUrl);
await new Promise(resolve => browserSocket.addEventListener('open', resolve, { once: true }));
let browserId = 0;
const browserPending = new Map();
browserSocket.addEventListener('message', event => { const data = JSON.parse(event.data); const call = browserPending.get(data.id); if (call) { browserPending.delete(data.id); data.error ? call.reject(new Error(data.error.message)) : call.resolve(data.result); } });
const browserSend = (method, params = {}) => { const next = ++browserId; browserSocket.send(JSON.stringify({ id: next, method, params })); return new Promise((resolve, reject) => browserPending.set(next, { resolve, reject })); };
const { browserContextId } = await browserSend('Target.createBrowserContext');
const { targetId } = await browserSend('Target.createTarget', { url: 'http://127.0.0.1:8821/login', browserContextId });
await new Promise(resolve => setTimeout(resolve, 700));
const targets = await (await fetch('http://127.0.0.1:8822/json/list')).json();
const owner = targets.find(t => t.id === targetId);
const socket = new WebSocket(owner.webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
let id = 0;
const pending = new Map();
socket.addEventListener('message', event => { const data = JSON.parse(event.data); const call = pending.get(data.id); if (call) { pending.delete(data.id); data.error ? call.reject(new Error(data.error.message)) : call.resolve(data.result); } });
const send = (method, params = {}) => { const next = ++id; socket.send(JSON.stringify({ id: next, method, params })); return new Promise((resolve, reject) => pending.set(next, { resolve, reject })); };
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const evaluate = async expression => { const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (result.exceptionDetails) throw new Error(result.exceptionDetails.text); return result.result.value; };
try {
    await send('Page.enable');
    for (let attempt = 0; attempt < 40; attempt++) {
        if (await evaluate(`Boolean(document.querySelector('form:has([name=password])'))`)) break;
        await pause(100);
        if (attempt === 39) throw new Error('Login form did not render');
    }
    await evaluate(`(() => { const form=document.querySelector('form:has([name=password])'); form.elements.email.value='demo.alpha.owner@playnexus.test'; form.elements.password.value='PlayNexusDemoOnly-2026'; form.requestSubmit(); })()`);
    await pause(1200);
    const results = [];
    for (const language of ['en', 'ar']) {
        await evaluate(`(() => { const form=document.querySelector('form[action$="/locale"]'); const body=new URLSearchParams(new FormData(form)); body.set('locale',${JSON.stringify(language)}); return fetch(form.action,{method:'POST',body,credentials:'same-origin'}).then(r=>r.status); })()`);
        for (const width of [390, 820, 1440]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
            for (const [surface, url] of [['assignment', '/app/assignments?user_id=10'], ['profile', '/app/tenant/settings']]) {
                await send('Page.navigate', { url: `http://127.0.0.1:8821${url}` });
                await pause(900);
                const result = await evaluate(`({surface:${JSON.stringify(surface)},width:${width},path:location.pathname,lang:document.documentElement.lang,dir:document.documentElement.dir,scrollWidth:document.documentElement.scrollWidth,contactVisible:document.body.innerText.includes('demo.alpha.owner@playnexus.test'),countryVisible:document.body.innerText.includes(${JSON.stringify(language === 'ar' ? 'مصر' : 'Egypt')}),overflow:[...document.querySelectorAll('main *')].map(e=>({tag:e.tagName,id:e.id,classes:e.className,rect:e.getBoundingClientRect().toJSON(),minWidth:getComputedStyle(e).minWidth})).filter(e=>e.rect.width>0&&(e.rect.left < -1 || e.rect.right>innerWidth+1)).slice(0,25)})`);
                results.push(result);
                if (result.overflow.length) throw new Error(`${surface} overflow at ${language}/${width}`);
                if (surface === 'profile' && (!result.contactVisible || !result.countryVisible)) throw new Error(`Profile context missing at ${language}/${width}`);
                const screenshot = await send('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(output, `${surface}-recheck-${language}-${width}.png`), Buffer.from(screenshot.data, 'base64'));
            }
        }
    }
    fs.writeFileSync(path.join(output, 'assignment-recheck.json'), JSON.stringify(results, null, 2));
    console.log(JSON.stringify(results, null, 2));
} finally { socket.close(); await browserSend('Target.disposeBrowserContext', { browserContextId }); browserSocket.close(); }
