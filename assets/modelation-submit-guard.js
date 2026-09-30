"use strict";
(() => {
  const app = document.getElementById('app');
  if (!app) return;
  app.addEventListener('submit', event => {
    if (event.target?.id !== 'modelation-form') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    globalThis.MojePenizeModelation?.submitModelation(event.target);
  }, true);
})();
