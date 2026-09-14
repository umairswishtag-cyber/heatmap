import { safeFormMetadata } from './privacy.js';

export function installForms(emit) {
  const focus = (event) => { const field = safeFormMetadata(event.target); if (field) emit('form_focus', field); };
  const submit = (event) => emit('form_submit', { id: event.target.id || '', url: location.href });
  document.addEventListener('focusin', focus, true);
  document.addEventListener('submit', submit, true);
  return () => { document.removeEventListener('focusin', focus, true); document.removeEventListener('submit', submit, true); };
}
