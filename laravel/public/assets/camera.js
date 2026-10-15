'use strict';
const video=document.querySelector('#video'), employee=document.querySelector('#employee'), capture=document.querySelector('#capture'), photo=document.querySelector('#photo'), statusBadge=document.querySelector('#status'), result=document.querySelector('#result');
let stream=null, image=null, busy=false;
function tick(){const now=new Date();document.querySelector('#clock').textContent=new Intl.DateTimeFormat('id-ID',{timeZone:attendanceConfig.timezone,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(now);document.querySelector('#today').textContent=new Intl.DateTimeFormat('id-ID',{timeZone:attendanceConfig.timezone,dateStyle:'full'}).format(now);}tick();setInterval(tick,1000);
function update(){if(employee){const enrolled=employee.selectedOptions[0]?.dataset.enrolled==='1';statusBadge.textContent=employee.value?(enrolled?'Wajah sudah terdaftar':'Wajah belum terdaftar'):'Pilih pegawai terlebih dahulu';statusBadge.className='badge '+(employee.value?(enrolled?'text-bg-success':'text-bg-warning'):'text-bg-secondary');employee.disabled=busy;}document.querySelectorAll('.submit').forEach(b=>b.disabled=busy||!image||(attendanceConfig.enroll&&!employee?.value));capture.disabled=busy||!stream||video.readyState<2;}
function reset(){image=null;photo.classList.add('d-none');photo.removeAttribute('src');document.querySelector('#placeholder').classList.remove('d-none');update();}employee?.addEventListener('change',reset);
async function camera(){const message=document.querySelector('#cameraMessage');try{if(!navigator.mediaDevices?.getUserMedia)throw Error('Kamera membutuhkan localhost atau HTTPS.');if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;reset();stream=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:'user',width:{ideal:1280},height:{ideal:960}}});video.srcObject=stream;await video.play();message.classList.add('d-none');update();}catch(e){stream=null;message.classList.remove('d-none');message.textContent='Kamera tidak dapat diakses. Izinkan akses kamera, lalu klik Aktifkan Kamera. '+e.message;update();}}
video.addEventListener('loadeddata',update);document.querySelector('#retry').addEventListener('click',camera);
function showCapturedPhoto(){
    if(!window.matchMedia('(max-width: 991.98px)').matches)return;
    const target=document.querySelector('#captureResult');
    if(!target)return;
    requestAnimationFrame(()=>target.scrollIntoView({
        behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',
        block:'start'
    }));
}
capture.addEventListener('click',()=>{if(!video.videoWidth)return;const canvas=document.createElement('canvas');const scale=Math.min(1,1280/video.videoWidth);canvas.width=Math.round(video.videoWidth*scale);canvas.height=Math.round(video.videoHeight*scale);canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);image=canvas.toDataURL('image/jpeg',0.9);photo.src=image;photo.classList.remove('d-none');document.querySelector('#placeholder').classList.add('d-none');result.replaceChildren();update();photo.decode().catch(()=>{}).then(showCapturedPhoto);});
const resultDialog=document.querySelector('#resultDialog');
resultDialog.addEventListener('click',event=>{
    const bounds=resultDialog.getBoundingClientRect();
    if(event.target===resultDialog && (event.clientX<bounds.left || event.clientX>bounds.right || event.clientY<bounds.top || event.clientY>bounds.bottom)) resultDialog.close();
});
function display(message,ok,data){
    result.replaceChildren();
    resultDialog.className='result-dialog '+(ok?'result-success':'result-failed');
    const icon=document.createElement('div');
    icon.className='result-icon';icon.textContent=ok?'✓':'!';icon.setAttribute('aria-hidden','true');result.append(icon);
    const title=document.createElement('h2');title.id='resultTitle';title.className='h5 fw-bold mt-3 mb-0';title.textContent=message;result.append(title);
    if(data?.name){
        const details=document.createElement('div');details.className='result-details mt-3';
        for(const line of [data.name,'Jenis: '+data.type,'Waktu: '+data.time,'Face Match Score: '+Number(data.score).toFixed(3)]){
            const item=document.createElement('div');item.textContent=line;details.append(item);
        }
        result.append(details);
        const img=document.createElement('img');img.src=data.photo;img.alt='Foto bukti absensi';result.append(img);
        const refresh=document.createElement('a');refresh.href='/';refresh.className='btn btn-sm btn-success mt-3';refresh.textContent='Lihat riwayat terbaru';result.append(refresh);
    }
    if(!resultDialog.open) resultDialog.showModal();
}
document.querySelectorAll('.submit').forEach(button=>button.addEventListener('click',async()=>{if(busy)return;busy=true;update();const label=button.textContent;button.textContent=attendanceConfig.enroll?'Mendaftarkan…':'Mengenali wajah…';try{const response=await fetch(attendanceConfig.enroll?'/enroll':'/attendance',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({...(attendanceConfig.enroll?{employee_id:employee.value}: {}),photo:image,type:button.dataset.action})});const data=await response.json();if(!response.ok)throw Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Permintaan gagal.');display(data.message,true,data);if(attendanceConfig.enroll)employee.selectedOptions[0].dataset.enrolled='1';reset();}catch(e){display(e.message,false);}finally{busy=false;button.textContent=label;update();}}));
window.addEventListener('pagehide',()=>stream?.getTracks().forEach(t=>t.stop()));camera();
