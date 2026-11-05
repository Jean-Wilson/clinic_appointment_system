const API='/api';
const $=id=>document.getElementById(id);
let patients=[],doctors=[],appointments=[];
const statuses=['Scheduled','Completed','Cancelled','No-show'];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function request(path,options={}) {
  const res=await fetch(`${API}/${path}`,{headers:{'Content-Type':'application/json'},...options});
  let data={};
  try { data=await res.json(); } catch (_) {}
  if(!res.ok) throw new Error(data.error||`Request failed (${res.status})`);
  return data;
}
function message(text,error=false){
  const el=$('message');el.textContent=text;el.className=`show${error?' error':''}`;
  clearTimeout(message.timer);message.timer=setTimeout(()=>el.className='',4500);
}
function showView(view){
  document.querySelectorAll('.view').forEach(x=>x.classList.add('hidden'));
  $(view).classList.remove('hidden');
  document.querySelectorAll('.nav').forEach(x=>x.classList.toggle('active',x.dataset.view===view));
  $('pageTitle').textContent=view[0].toUpperCase()+view.slice(1);
}
document.querySelectorAll('.nav').forEach(b=>b.addEventListener('click',()=>showView(b.dataset.view)));
const empty=n=>`<tr><td colspan="${n}" class="empty">No records found.</td></tr>`;
async function loadAll(){
  try{
    [patients,doctors,appointments]=await Promise.all([request('patients'),request('doctors'),request('appointments')]);
    render();
    const stats=await request('stats');
    $('patientCount').textContent=stats.patients;
    $('doctorCount').textContent=stats.doctors;
    $('appointmentCount').textContent=stats.appointments;
    $('todayCount').textContent=stats.today;
  }catch(e){message(e.message,true)}
}
function fillAppointmentOptions(){
  const patientOptions='<option value="">Choose patient</option>'+patients.map(p=>`<option value="${p.id}">${esc(p.full_name)}</option>`).join('');
  const doctorOptions='<option value="">Choose doctor</option>'+doctors.map(d=>`<option value="${d.id}">${esc(d.full_name)} — ${esc(d.specialty)}</option>`).join('');
  $('appointmentPatient').innerHTML=patientOptions;
  $('appointmentDoctor').innerHTML=doctorOptions;
  $('editAppointmentPatient').innerHTML=patientOptions;
  $('editAppointmentDoctor').innerHTML=doctorOptions;
}
function render(){
  $('patientRows').innerHTML=patients.map(p=>`<tr><td>${esc(p.full_name)}</td><td>${esc(p.phone)}</td><td>${esc(p.email)}</td><td>${esc(p.date_of_birth)}</td><td>${esc(p.gender)}</td></tr>`).join('')||empty(5);
  $('doctorRows').innerHTML=doctors.map(d=>`<tr><td>${esc(d.full_name)}</td><td>${esc(d.specialty)}</td><td>${esc(d.phone)}</td></tr>`).join('')||empty(3);
  fillAppointmentOptions();
  const rows=appointments.map(a=>`<tr>
    <td>${esc(a.patient_name)}</td><td>${esc(a.doctor_name)}</td>
    <td>${esc(a.appointment_date)}</td><td>${esc(String(a.appointment_time).slice(0,5))}</td>
    <td>${esc(a.reason)}</td><td><span class="pill">${esc(a.status)}</span></td>
    <td><div class="actions">
      <button class="small-btn" type="button" onclick="editAppointment(${Number(a.id)})">Edit</button>
      <select class="small-btn" aria-label="Change status" onchange="updateStatus(${Number(a.id)},this.value)">
        <option value="">Status</option>${statuses.map(s=>`<option value="${s}" ${s===a.status?'selected':''}>${s}</option>`).join('')}
      </select>
      <button class="small-btn danger" type="button" onclick="deleteAppointment(${Number(a.id)})">Delete</button>
    </div></td></tr>`).join('');
  $('appointmentRows').innerHTML=rows||empty(7);
  $('upcomingRows').innerHTML=appointments.filter(a=>a.status==='Scheduled').slice(0,6).map(a=>`<tr><td>${esc(a.patient_name)}</td><td>${esc(a.doctor_name)}</td><td>${esc(a.appointment_date)}</td><td>${esc(String(a.appointment_time).slice(0,5))}</td><td><span class="pill">${esc(a.status)}</span></td></tr>`).join('')||empty(5);
}
$('patientForm').addEventListener('submit',async e=>{
 e.preventDefault();
 try{await request('patients',{method:'POST',body:JSON.stringify({full_name:$('patientName').value,phone:$('patientPhone').value,email:$('patientEmail').value,date_of_birth:$('patientDob').value,gender:$('patientGender').value})});e.target.reset();message('Patient registered successfully');await loadAll()}
 catch(err){message(err.message,true)}
});
$('doctorForm').addEventListener('submit',async e=>{
 e.preventDefault();
 try{await request('doctors',{method:'POST',body:JSON.stringify({full_name:$('doctorName').value,specialty:$('doctorSpecialty').value,phone:$('doctorPhone').value})});e.target.reset();message('Doctor added successfully');await loadAll()}
 catch(err){message(err.message,true)}
});
$('appointmentForm').addEventListener('submit',async e=>{
 e.preventDefault();
 try{await request('appointments',{method:'POST',body:JSON.stringify({patient_id:$('appointmentPatient').value,doctor_id:$('appointmentDoctor').value,appointment_date:$('appointmentDate').value,appointment_time:$('appointmentTime').value,reason:$('appointmentReason').value})});e.target.reset();message('Appointment booked successfully');await loadAll()}
 catch(err){message(err.message,true)}
});
function editAppointment(id){
 const a=appointments.find(x=>Number(x.id)===Number(id));
 if(!a){message('Appointment not found. Refresh and try again.',true);return}
 $('editAppointmentId').value=a.id;
 $('editAppointmentPatient').value=a.patient_id;
 $('editAppointmentDoctor').value=a.doctor_id;
 $('editAppointmentDate').value=a.appointment_date;
 $('editAppointmentTime').value=String(a.appointment_time).slice(0,5);
 $('editAppointmentReason').value=a.reason||'';
 $('editAppointmentStatus').value=a.status;
 $('editAppointmentPanel').classList.remove('hidden');
 $('editAppointmentPanel').scrollIntoView({behavior:'smooth',block:'start'});
}
function closeEdit(){$('editAppointmentPanel').classList.add('hidden');$('editAppointmentForm').reset()}
$('cancelEdit').addEventListener('click',closeEdit);
$('editAppointmentForm').addEventListener('submit',async e=>{
 e.preventDefault();
 const id=$('editAppointmentId').value;
 const payload={
  patient_id:$('editAppointmentPatient').value,
  doctor_id:$('editAppointmentDoctor').value,
  appointment_date:$('editAppointmentDate').value,
  appointment_time:$('editAppointmentTime').value,
  reason:$('editAppointmentReason').value,
  status:$('editAppointmentStatus').value
 };
 try{
  await request(`appointments/${id}`,{method:'PUT',body:JSON.stringify(payload)});
  closeEdit();message('Appointment details updated successfully');await loadAll();
 }catch(err){message(err.message,true)}
});
async function updateStatus(id,status){
 if(!status)return;
 try{await request(`appointments/${id}`,{method:'PATCH',body:JSON.stringify({status})});message('Appointment status updated');await loadAll()}
 catch(e){message(e.message,true);await loadAll()}
}
async function deleteAppointment(id){
 if(!confirm('Permanently delete this appointment? This action cannot be undone.'))return;
 try{await request(`appointments/${id}`,{method:'DELETE'});message('Appointment permanently deleted');await loadAll()}
 catch(e){message(e.message,true)}
}
$('today').textContent=new Date().toLocaleDateString(undefined,{weekday:'long',year:'numeric',month:'long',day:'numeric'});
$('appointmentDate').min=new Date().toISOString().slice(0,10);
$('editAppointmentDate').min=new Date().toISOString().slice(0,10);
loadAll();
