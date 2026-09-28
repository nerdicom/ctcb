(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.mobile-nav');
  const closeMenu = () => {
    toggle?.setAttribute('aria-expanded', 'false');
    nav?.classList.remove('is-open');
  };
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!expanded));
      nav.classList.toggle('is-open', !expanded);
    });
    nav.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        closeMenu();
        toggle.focus();
      }
    });
    document.addEventListener('click', event => { if (!event.target.closest('.site-header')) closeMenu(); });
    window.matchMedia('(min-width: 851px)').addEventListener('change', closeMenu);
  }

  const dialog = document.getElementById('consultation-modal');
  const form = document.getElementById('consultation-form');
  if (!dialog || !form || typeof dialog.showModal !== 'function') return;
  const intro = document.getElementById('consult-intro');
  const status = document.getElementById('consult-status');
  const success = document.getElementById('consult-success');
  const submit = form.querySelector('button[type="submit"]');
  const submitLabel = submit.querySelector('[data-submit-label]');
  let lastTrigger;
  let sending = false;

  document.querySelectorAll('[data-consult-open]').forEach(trigger => {
    trigger.addEventListener('click', event => {
      event.preventDefault();
      lastTrigger = trigger;
      closeMenu();
      if (!dialog.open) dialog.showModal();
      document.body.classList.add('modal-open');
      document.getElementById(success.hidden ? 'consult-title' : 'consult-success-title').focus({ preventScroll: true });
    });
  });
  dialog.querySelectorAll('[data-consult-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
  dialog.addEventListener('close', () => {
    document.body.classList.remove('modal-open');
    const returnTarget = lastTrigger?.getClientRects().length ? lastTrigger : toggle;
    returnTarget?.focus({ preventScroll: true });
  });
  form.addEventListener('input', event => {
    if (typeof event.target.setCustomValidity === 'function') {
      event.target.setCustomValidity('');
      event.target.removeAttribute('aria-invalid');
    }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (sending) return;
    const phone = form.elements.namedItem('phone');
    const number = phone.value.replace(/\D/g, '');
    phone.setCustomValidity(number.length < 7 || number.length > 15 || /[^0-9+().\s-]/.test(phone.value)
      ? 'Please enter a valid phone number, including the area code.' : '');
    if (!form.reportValidity()) return;
    sending = true;
    status.hidden = true;
    status.textContent = '';
    submit.disabled = true;
    submitLabel.textContent = 'Sending your request…';
    form.setAttribute('aria-busy', 'true');
    const data = new FormData(form);
    data.set('page', window.location.pathname);
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: data,
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: controller.signal,
      });
      const result = await response.json();
      if (!response.ok || result.ok !== true) {
        if (result.errors && typeof result.errors === 'object') {
          for (const [field, message] of Object.entries(result.errors)) {
            const input = form.elements.namedItem(field);
            if (input && typeof input.setCustomValidity === 'function' && typeof message === 'string') {
              input.setCustomValidity(message);
              input.setAttribute('aria-invalid', 'true');
            }
          }
          form.reportValidity();
        }
        throw new Error(typeof result.message === 'string' ? result.message : 'We could not send your request. Please try again or email info@ctcustombuilders.com.');
      }
      form.reset();
      form.hidden = true;
      intro.hidden = true;
      success.hidden = false;
      document.getElementById('consult-success-title').focus({ preventScroll: true });
    } catch (error) {
      status.textContent = error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
        ? 'We couldn’t confirm your request. Your details are still here. Please try again, or email info@ctcustombuilders.com.'
        : error.message;
      status.hidden = false;
      status.focus({ preventScroll: false });
    } finally {
      window.clearTimeout(timeout);
      sending = false;
      submit.disabled = false;
      submitLabel.textContent = 'Request a consultation';
      form.removeAttribute('aria-busy');
    }
  });
})();
