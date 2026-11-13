// Run with: node laravel/tests/Browser/camera-startup.cjs
// Exercise the real kiosk script without a webcam or a browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/camera.js'), 'utf8');

async function check(timezone, intl = Intl) {
    const elements = new Map();
    const element = selector => {
        if (selector === '#employee' || selector === '#status') return null;
        if (!elements.has(selector)) elements.set(selector, {
            textContent: '', readyState: 4, disabled: true, listeners: {},
            classList: {add() {}, remove() {}}, removeAttribute() {},
            addEventListener(name, callback) { this.listeners[name] = callback; },
            async play() {},
        });
        return elements.get(selector);
    };
    let requested = 0;
    const context = {
        attendanceConfig: {enroll: false, timezone}, Intl: intl,
        document: {querySelector: element, querySelectorAll: () => []},
        navigator: {mediaDevices: {async getUserMedia() {
            requested++;
            return {getTracks: () => [{stop() {}}]};
        }}},
        window: {addEventListener() {}}, setInterval() {},
    };
    vm.runInNewContext(source, context);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requested, 1, `camera must start with timezone ${timezone}`);
    assert.equal(typeof element('#retry').listeners.click, 'function');
    assert.equal(element('#capture').disabled, false);
    if (typeof intl.DateTimeFormat === 'function' && (!timezone || timezone === 'Asia/Jakarta')) {
        assert.notEqual(element('#clock').textContent, '--:--:--');
    }
}

(async () => {
    for (const timezone of ['Asia/Jakarta', null, undefined, '', 'Invalid/Timezone']) await check(timezone);
    await check('Asia/Jakarta', {});
    process.stdout.write('Camera startup: 6 scenarios passed (including stale config and clock failure).\n');
})().catch(error => { process.stderr.write(error.stack + '\n'); process.exitCode = 1; });
