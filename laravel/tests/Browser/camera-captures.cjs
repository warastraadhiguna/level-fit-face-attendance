// Run with: node tests/Browser/camera-captures.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/camera.js'), 'utf8');

async function scenario({failRequest=false, interruptCamera=false}={}) {
    const elements = new Map(), posts = [], waits = [];
    let frame = 0;
    const makeElement = () => ({
        textContent: '', readyState: 4, videoWidth: 1280, videoHeight: 960,
        disabled: true, listeners: {}, dataset: {action:'check_in'}, content:'csrf',
        classList: {add(){},remove(){}}, removeAttribute(){}, replaceChildren(){}, append(){}, setAttribute(){},
        addEventListener(name, callback){this.listeners[name]=callback;},
        async play(){}, async decode(){}, showModal(){this.open=true;},
    });
    const element = selector => {
        if (selector==='#employee'||selector==='#status') return null;
        if (!elements.has(selector)) elements.set(selector,makeElement());
        return elements.get(selector);
    };
    const button = makeElement();
    const context = {
        attendanceConfig: {enroll:false,timezone:'Asia/Jakarta'}, Intl,
        document: {
            querySelector:element, querySelectorAll:()=>[button],
            createElement(tag){
                if(tag!=='canvas')return makeElement();
                return {getContext:()=>({drawImage(){}}),toDataURL(){
                    frame++;
                    if(interruptCamera&&frame===1)element('#video').videoWidth=0;
                    return 'data:image/jpeg;base64,capture-'+frame;
                }};
            },
        },
        navigator:{mediaDevices:{async getUserMedia(){return {getTracks:()=>[{stop(){}}]};}}},
        window:{addEventListener(){},matchMedia:()=>({matches:false})},
        setInterval(){},setTimeout(callback,delay){waits.push(delay);callback();},
        async fetch(url,options){
            posts.push({url,body:JSON.parse(options.body)});
            return {ok:!failRequest,async json(){return {message:failRequest?'Ambil foto ulang.':'Absensi berhasil.'};}};
        },
    };
    vm.runInNewContext(source,context);
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(button.disabled,true);
    const capturing=element('#capture').listeners.click();
    assert.equal(button.disabled,true,'cannot submit an incomplete capture batch');
    await capturing;
    if(interruptCamera){
        assert.equal(button.disabled,true);
        assert.equal(posts.length,0);
        return;
    }
    assert.equal(frame,3);
    assert.deepEqual(waits,[300,300]);
    assert.equal(button.disabled,false);
    await button.listeners.click();
    assert.equal(posts.length,1);
    assert.equal(posts[0].url,'/attendance');
    assert.equal(posts[0].body.photos.length,3);
    assert.equal(new Set(posts[0].body.photos).size,3);
    assert.equal(posts[0].body.photo,posts[0].body.photos[0]);
    assert.equal(button.disabled,true,'success and rejection both discard the capture batch');
    await button.listeners.click();
    assert.equal(posts.length,1,'discarded captures cannot be submitted again');
}

(async()=>{
    await scenario();
    await scenario({failRequest:true});
    await scenario({interruptCamera:true});
    process.stdout.write('Camera captures: 3 scenarios passed (three frames, failed verification, camera interruption).\n');
})().catch(error=>{process.stderr.write(error.stack+'\n');process.exitCode=1;});
