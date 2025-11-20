const rest = window.catlaqREST || {};
const restRoot = (rest.root || '/wp-json/catlaq/v1').replace(/\/$/, '');
const form = document.querySelector('[data-catlaq-onboarding]');
const statusEl = document.querySelector('[data-catlaq-onboarding-status]');

if (form) {
  form.addEventListener('submit', (event) => {
    const action = event.submitter ? event.submitter.name : '';
    if (action !== 'advance_step') {
      return;
    }

    event.preventDefault();
    const userId = form.dataset.userId;
    const endpoint = `${restRoot}/profiles`;

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': rest.nonce || '',
      },
      body: JSON.stringify({ user_id: userId }),
    })
      .then((res) => {
        if (!res.ok) {
          throw new Error('request_failed');
        }
        return res.json();
      })
      .then((data) => {
        if (statusEl) {
          statusEl.textContent = `Profile created (ID ${data.id}). Advancing...`;
        }
        form.submit();
      })
      .catch(() => {
        if (statusEl) {
          statusEl.textContent = 'Profile creation failed';
        }
      });
  });
}
