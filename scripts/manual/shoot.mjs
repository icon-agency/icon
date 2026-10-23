// shoot.mjs — screenshots for the CMS manual (drupal/web/modules/custom/icon_site/manual) over Chrome's DevTools protocol.
// Usage: node scripts/manual/shoot.mjs <plan.json>
// Each step: {url, wait, click, clip (css selector | [x,y,w,h]), out, width, height, eval, scroll}
import { spawn } from "node:child_process";
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname } from "node:path";

const CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome";
const plan = JSON.parse(readFileSync(process.argv[2], "utf8"));
const port = 9333;
const chrome = spawn(CHROME, ["--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run", `--remote-debugging-port=${port}`, `--window-size=${plan.width || 1400},${plan.height || 900}`, "--user-data-dir=/tmp/claude-shots/profile-cdp", "about:blank"], { stdio: "ignore" });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let targets;
for (let i = 0; i < 40; i++) { try { targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); if (targets.length) break; } catch {} await sleep(250); }
const page = targets.find((t) => t.type === "page");
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let id = 0; const pending = new Map(); const events = [];
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pending.has(d.id)) { pending.get(d.id)(d); pending.delete(d.id); } else if (d.method) events.push(d); };
const send = (method, params = {}) => new Promise((res) => { const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });
const evaluate = async (expr) => { const r = await send("Runtime.evaluate", { expression: expr, awaitPromise: true, returnByValue: true }); return r.result?.result?.value; };
await send("Page.enable"); await send("Runtime.enable");
await send("Emulation.setDeviceMetricsOverride", { width: plan.width || 1400, height: plan.height || 900, deviceScaleFactor: plan.scale || 2, mobile: false });
for (const step of plan.steps) {
  if (step.url) { await send("Page.navigate", { url: step.url }); await sleep(step.wait ?? 2500); }
  if (step.width) await send("Emulation.setDeviceMetricsOverride", { width: step.width, height: step.height || plan.height || 900, deviceScaleFactor: plan.scale || 2, mobile: false });
  if (step.eval) { await evaluate(step.eval); await sleep(step.settle ?? 600); }
  if (step.click) {
    const rect = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(step.click)}); if (!e) return null; e.scrollIntoView({block:'center'}); const r = e.getBoundingClientRect(); return [r.left + r.width/2, r.top + r.height/2]; })()`);
    if (rect) { for (const type of ["mouseMoved", "mousePressed", "mouseReleased"]) await send("Input.dispatchMouseEvent", { type, x: rect[0], y: rect[1], button: "left", clickCount: 1 }); }
    else console.error("click target missing:", step.click);
    await sleep(step.settle ?? 1200);
  }
  if (step.clickAt) { for (const type of ["mouseMoved", "mousePressed", "mouseReleased"]) await send("Input.dispatchMouseEvent", { type, x: step.clickAt[0], y: step.clickAt[1], button: "left", clickCount: 1 }); await sleep(step.settle ?? 1200); }
  if (step.rightClickAt) { for (const type of ["mouseMoved", "mousePressed", "mouseReleased"]) await send("Input.dispatchMouseEvent", { type, x: step.rightClickAt[0], y: step.rightClickAt[1], button: "right", clickCount: 1 }); await sleep(step.settle ?? 1200); }
  if (step.hover) { const rect = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(step.hover)}); if (!e) return null; const r = e.getBoundingClientRect(); return [r.left + r.width/2, r.top + r.height/2]; })()`); if (rect) await send("Input.dispatchMouseEvent", { type: "mouseMoved", x: rect[0], y: rect[1] }); await sleep(step.settle ?? 600); }
  if (step.scroll) { await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(step.scroll)}); if (e) e.scrollIntoView({block: ${JSON.stringify(step.block || "start")}}); })()`); await sleep(step.settle ?? 900); }
  if (step.finish) { await evaluate(`(() => { try { document.getAnimations().forEach(a => { try { a.finish(); } catch (e) {} }); } catch (e) {} document.querySelectorAll('[data-animate]').forEach(e => e.classList.add('is-visible')); document.querySelectorAll('[data-reveal-img]').forEach(e => e.classList.add('is-revealed')); document.querySelectorAll('[data-reveal-words]').forEach(e => e.classList.add('is-revealed')); document.querySelectorAll('[data-rule]').forEach(e => e.classList.add('is-drawn')); return true; })()`); await sleep(300); }
  if (step.out) {
    let clip;
    if (Array.isArray(step.clip)) clip = { x: step.clip[0], y: step.clip[1], width: step.clip[2], height: step.clip[3], scale: 1 };
    else if (step.clip) {
      // scroll the element to the top of the viewport, then clip in viewport
      // coordinates: beyond-viewport capture re-lays the page out and shifts
      // the gutters, so a layout-coordinate clip lands off by the gutter
      const r = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(step.clip)}); if (!e) return null; e.scrollIntoView({block:'start'}); window.scrollBy(0, -${step.pad || 0}); const r = e.getBoundingClientRect(); return [r.left, r.top, r.width, r.height, window.innerWidth, window.innerHeight]; })()`);
      if (!r) { console.error("clip target missing:", step.clip); continue; }
      await sleep(step.settle ?? 700);
      if (step.finish) await evaluate(`(() => { try { document.getAnimations().forEach(a => { try { a.finish(); } catch (e) {} }); } catch (e) {} return true; })()`);
      const r2 = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(step.clip)}); const r = e.getBoundingClientRect(); return [r.left, r.top, r.width, r.height, window.innerWidth, window.innerHeight]; })()`);
      const pad = step.pad || 0;
      const x = Math.max(0, r2[0] - pad), y = Math.max(0, r2[1] - pad);
      clip = { x, y, width: Math.min(r2[2] + pad * 2, r2[4] - x), height: Math.min(r2[3] + pad * 2, r2[5] - y), scale: 1 };
    }
    const shot = await send("Page.captureScreenshot", { format: "jpeg", quality: step.quality || 88, ...(clip ? { clip } : {}) });
    mkdirSync(dirname(step.out), { recursive: true });
    writeFileSync(step.out, Buffer.from(shot.result.data, "base64"));
    console.log("wrote", step.out);
  }
}
ws.close(); chrome.kill();
