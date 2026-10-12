'use strict';
document.querySelector('[data-menu-toggle]')?.addEventListener('click',event=>{
 const open=document.querySelector('#sidebar').classList.toggle('open');
 event.currentTarget.setAttribute('aria-expanded',String(open));
 event.currentTarget.setAttribute('aria-label',open?'Close workspace navigation':'Open workspace navigation');
});
document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{
 const input=document.getElementById(button.dataset.passwordToggle),visible=input.type==='password';
 input.type=visible?'text':'password';button.setAttribute('aria-pressed',String(visible));button.setAttribute('aria-label',visible?'Hide password':'Show password');
}));
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault();}));
document.querySelectorAll('[data-change-save]').forEach(form=>{
 const button=form.querySelector('[data-change-save-button]');
 if(!button)return;
 const controls=[...form.querySelectorAll('select,input:not([type=hidden]),textarea')];
 const value=control=>control.type==='checkbox'||control.type==='radio'?`${control.checked}:${control.value}`:control.value;
 const initial=controls.map(value);
 const update=()=>{button.disabled=controls.every((control,index)=>value(control)===initial[index]);};
 controls.forEach(control=>control.addEventListener('input',update));
 controls.forEach(control=>control.addEventListener('change',update));
 form.addEventListener('submit',()=>{button.disabled=true;});
 update();
});
document.querySelectorAll('[data-leave-calendar]').forEach(calendar=>{
 const form=calendar.closest('form'),employee=form?.querySelector('[data-leave-employee]');
 const startInput=calendar.querySelector('#book_leave_start'),endInput=calendar.querySelector('#book_leave_end');
 const toggle=calendar.querySelector('[data-calendar-toggle]'),panel=calendar.querySelector('[data-calendar-panel]');
 const grid=calendar.querySelector('[data-calendar-grid]'),monthLabel=calendar.querySelector('[data-calendar-month]');
 const summary=calendar.querySelector('[data-calendar-summary]'),countLabel=calendar.querySelector('[data-calendar-count]');
 const leaveType=form?.querySelector('#book_leave_type');
 if(!form||!employee||!startInput||!endInput||!toggle||!panel||!grid)return;
 let start=startInput.value||null,end=endInput.value||null;
 let shown=start?new Date(`${start}T00:00:00Z`):new Date();
 let workdays=['mon','tue','wed','thu','fri'];
 let shifts=[];
 let holidays=[];
 try{holidays=JSON.parse(calendar.dataset.holidays||'[]');}catch{}
 const pad=value=>String(value).padStart(2,'0');
 const dateKey=date=>`${date.getUTCFullYear()}-${pad(date.getUTCMonth()+1)}-${pad(date.getUTCDate())}`;
 const parseDate=value=>new Date(`${value}T00:00:00Z`);
 const dayCode=date=>['sun','mon','tue','wed','thu','fri','sat'][date.getUTCDay()];
 const applicableHoliday=date=>holidays.find(item=>item.date===date&&(item.region==='all'||item.region==='local'||item.region===calendar.dataset.region));
 const shiftsOn=date=>shifts.filter(item=>item.date===date);
 const chosenEmployee=()=>employee.value!=='';
 const updateRange=()=>{
  startInput.value=start||'';endInput.value=end||start||'';
  if(!start){summary.textContent='Choose an employee, then select the first and last date.';toggle.textContent='Choose dates';}
  else if(!end){summary.textContent=`Start: ${parseDate(start).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'})} — now choose the last date.`;toggle.textContent='Continue selecting dates';}
  else{summary.textContent=`${parseDate(start).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'})} – ${parseDate(end).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'})}`;toggle.textContent='Change dates';}
  const last=end||start;
  if(!start||!last||!chosenEmployee()){countLabel.textContent='Select an employee to see their working days and the leave deduction.';return;}
  let charge=0,calendarDays=0;
  for(let date=parseDate(start);date<=parseDate(last);date.setUTCDate(date.getUTCDate()+1)){
   calendarDays++;
   if(workdays.includes(dayCode(date))&&!applicableHoliday(dateKey(date)))charge++;
  }
  countLabel.textContent=leaveType?.value==='annual'
   ?`${charge} working ${charge===1?'day':'days'} will be deducted from the annual allowance (${calendarDays} calendar ${calendarDays===1?'date':'dates'} selected).`
   :`${charge} working ${charge===1?'day':'days'} in this request (${calendarDays} calendar ${calendarDays===1?'date':'dates'} selected).`;
 };
 const render=()=>{
  const year=shown.getUTCFullYear(),month=shown.getUTCMonth();
  monthLabel.textContent=new Date(Date.UTC(year,month,1)).toLocaleDateString('en-GB',{month:'long',year:'numeric',timeZone:'UTC'});
  grid.replaceChildren();
  const first=new Date(Date.UTC(year,month,1)),offset=(first.getUTCDay()+6)%7,monthDays=new Date(Date.UTC(year,month+1,0)).getUTCDate();
  for(let i=0;i<offset;i++){const blank=document.createElement('span');blank.className='leave-calendar-blank';grid.append(blank);}
  for(let day=1;day<=monthDays;day++){
   const date=new Date(Date.UTC(year,month,day)),key=dateKey(date),holiday=applicableHoliday(key),isWork=chosenEmployee()&&workdays.includes(dayCode(date)),dayShifts=shiftsOn(key);
   const button=document.createElement('button');button.type='button';button.className='leave-calendar-day';button.textContent=String(day);
   button.disabled=!chosenEmployee();
   if(isWork)button.classList.add('is-workday');else button.classList.add('is-offday');
   if(holiday)button.classList.add(holiday.closed?'is-closure':'is-holiday');
   if(dayShifts.length)button.classList.add('has-rota-shift');
   if(start&&key===start)button.classList.add('is-range-start');
   if(end&&key===end)button.classList.add('is-range-end');
   if(start&&key>=start&&key<=(end||start))button.classList.add('is-in-range');
   const dayName=date.toLocaleDateString('en-GB',{weekday:'long',timeZone:'UTC'});
   const holidayInfo=holiday?`; ${holiday.closed?'business closed':'holiday'}: ${holiday.name}`:'';
   const shiftInfo=dayShifts.length?`; rota ${dayShifts.map(item=>`${item.time} (${item.status})`).join(', ')}`:'';
   button.title=`${dayName} ${day} ${monthLabel.textContent}: ${!chosenEmployee()?'choose an employee first':(isWork?'normally works':'not a normal working day')}${holidayInfo}${shiftInfo}`;
   button.setAttribute('aria-label',button.title);
   button.addEventListener('click',()=>{
    if(!start||end){start=key;end=null;}
    else if(key<start){end=start;start=key;}
    else end=key;
    updateRange();render();
   });
   grid.append(button);
  }
  while(grid.children.length<42){const blank=document.createElement('span');blank.className='leave-calendar-blank';grid.append(blank);}
  updateRange();
 };
 toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;toggle.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)render();});
 calendar.querySelector('[data-calendar-prev]')?.addEventListener('click',()=>{shown=new Date(Date.UTC(shown.getUTCFullYear(),shown.getUTCMonth()-1,1));render();});
 calendar.querySelector('[data-calendar-next]')?.addEventListener('click',()=>{shown=new Date(Date.UTC(shown.getUTCFullYear(),shown.getUTCMonth()+1,1));render();});
 calendar.querySelector('[data-calendar-done]')?.addEventListener('click',()=>{panel.hidden=true;toggle.setAttribute('aria-expanded','false');});
 employee.addEventListener('change',()=>{
  const selected=employee.selectedOptions[0];
  try{workdays=JSON.parse(selected?.dataset.workingDays||'[]');}catch{workdays=[];}
  try{shifts=JSON.parse(selected?.dataset.shifts||'[]');}catch{shifts=[];}
  render();
 });
 leaveType?.addEventListener('change',updateRange);
 const selected=employee.selectedOptions[0];
 try{workdays=JSON.parse(selected?.dataset.workingDays||'["mon","tue","wed","thu","fri"]');}catch{}
 try{shifts=JSON.parse(selected?.dataset.shifts||'[]');}catch{}
 if(start&&!end)end=start;
 render();
});
document.querySelectorAll('[data-rota-batch]').forEach(form=>{
 const list=form.querySelector('[data-rota-batch-list]'),template=form.querySelector('[data-rota-row-template]'),add=form.querySelector('[data-add-rota-row]');
 if(!list||!template||!add)return;
 let nextIndex=list.querySelectorAll('[data-rota-row]').length;
 const sync=()=>{
  const rows=[...list.querySelectorAll('[data-rota-row]')];
  rows.forEach(buttonRow=>{const remove=buttonRow.querySelector('[data-remove-rota-row]');if(remove)remove.disabled=rows.length<=1;});
  add.disabled=rows.length>=100;
 };
 add.addEventListener('click',()=>{
  if(list.querySelectorAll('[data-rota-row]').length>=100)return;
  const row=template.content.firstElementChild.cloneNode(true);
  row.querySelectorAll('[name]').forEach(control=>{control.name=control.name.replaceAll('__INDEX__',String(nextIndex));});
  nextIndex++;list.append(row);sync();row.querySelector('select')?.focus();
 });
 list.addEventListener('click',event=>{
  const remove=event.target.closest('[data-remove-rota-row]');
  if(!remove||list.querySelectorAll('[data-rota-row]').length<=1)return;
  remove.closest('[data-rota-row]')?.remove();sync();
 });
 sync();
});
