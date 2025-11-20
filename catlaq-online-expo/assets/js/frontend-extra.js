const rest = window.catlaqREST || {};
const restRoot = (rest.root || '/wp-json/catlaq/v1').replace(/\/$/, '');
const endpoint = `${restRoot}/rfq`;

fetch(endpoint, {
  headers: rest.nonce ? { 'X-WP-Nonce': rest.nonce } : {},
})
  .then((res) => res.json())
  .then((data) => console.log('RFQs', data))
  .catch(console.error);
