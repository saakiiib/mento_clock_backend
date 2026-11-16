'use strict';
const menu = document.querySelector('.menu-toggle'), nav = document.querySelector('#main-nav');
menu?.addEventListener('click', () => { const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Close navigation':'Open navigation'); });
nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{nav.classList.remove('open');menu?.setAttribute('aria-expanded','false');}));

const setDemoScreen = (screenName) => {
  document.querySelectorAll('[data-screen]').forEach(screen => { screen.hidden = screen.dataset.screen !== screenName; });
  document.querySelectorAll('[data-demo-screen]').forEach(button => {
    const active = button.dataset.demoScreen === screenName;
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
  });
};
document.querySelectorAll('[data-demo-role]').forEach(button => button.addEventListener('click', () => {
  const role = button.dataset.demoRole;
  document.querySelectorAll('[data-demo-role]').forEach(other => {
    const active = other === button;
    other.classList.toggle('active', active);
    other.setAttribute('aria-pressed', String(active));
  });
  document.querySelectorAll('[data-demo-nav]').forEach(navGroup => { navGroup.hidden = navGroup.dataset.demoNav !== role; });
  setDemoScreen(role === 'employee' ? 'employee-clock' : 'manager-overview');
  const heading = document.querySelector('[data-demo-heading]');
  if (heading) heading.textContent = role === 'employee' ? 'EMPLOYEE APP PREVIEW' : 'BUSINESS OWNER APP PREVIEW';
}));
document.querySelectorAll('[data-demo-screen]').forEach(button => button.addEventListener('click', () => setDemoScreen(button.dataset.demoScreen)));

let demoClockedIn = false;
document.querySelector('#demo-punch')?.addEventListener('click', event => {
  demoClockedIn = !demoClockedIn;
  const status = document.querySelector('[data-clock-status]');
  const time = document.querySelector('[data-clock-time]');
  const copy = document.querySelector('[data-clock-copy]');
  if (status) status.textContent = demoClockedIn ? 'You’re clocked in · since 09:02' : 'Shift complete · 7h 30m worked';
  if (time) time.textContent = demoClockedIn ? '09:02' : '16:32';
  if (copy) copy.textContent = demoClockedIn ? 'Your clock-in is recorded in this sample. Clock out when your shift ends.' : 'Sample shift complete. Break time is shown separately in attendance reports.';
  event.currentTarget.innerHTML = demoClockedIn ? 'Clock out →' : 'Clock in again →';
});

document.querySelector('[data-demo-holiday-form]')?.addEventListener('submit', event => {
  event.preventDefault();
  const feedback = document.querySelector('[data-holiday-feedback]');
  if (feedback) { feedback.textContent = 'Sample request ready · 14–16 Dec · 3 working days. Nothing has been sent.'; feedback.hidden = false; }
});
document.querySelectorAll('[data-demo-action]').forEach(button => button.addEventListener('click', () => {
  const feedback = button.closest('[data-screen]')?.querySelector('[data-manager-feedback]');
  if (feedback) { feedback.textContent = button.dataset.demoAction; feedback.hidden = false; }
}));

const money=new Intl.NumberFormat('en-GB',{style:'currency',currency:'GBP',minimumFractionDigits:2});
document.querySelectorAll('[data-billing]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-billing]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',String(b===button));});document.querySelectorAll('.price-value').forEach(price=>{const value=price.dataset[button.dataset.billing];price.querySelector('strong').textContent=value?money.format(Number(value)):'Let’s talk';price.querySelector('span').textContent=value?(button.dataset.billing==='annual'?'per year':'per month'):'A quote for your team';});}));


const trialDialog=document.querySelector('#trial-dialog');
const openTrial=()=>{if(trialDialog&&!trialDialog.open)trialDialog.showModal();};
document.querySelectorAll('[data-open-trial]').forEach(button=>button.addEventListener('click',event=>{event.preventDefault();openTrial();}));
document.querySelectorAll('[data-close-trial]').forEach(button=>button.addEventListener('click',()=>trialDialog?.close()));
trialDialog?.addEventListener('click',event=>{if(event.target===trialDialog)trialDialog.close();});
if(trialDialog&&location.hash==='#trial-request')openTrial();
