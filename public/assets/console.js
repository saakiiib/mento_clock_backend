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
