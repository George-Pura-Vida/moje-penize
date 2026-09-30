"use strict";
(() => {
  function clearUnrelatedNameNoticeAfterSuccess() {
    if (location.hash !== '#modelation') return;
    const preview = document.getElementById('modelation-preview');
    const notice = document.getElementById('notice');
    if (!preview || !notice) return;
    const successful = preview.textContent.includes('Výpočet ověřen CE-1.0.0') && preview.textContent.includes('stav OK');
    if (successful && notice.textContent.trim() === 'Vyplňte název.') notice.textContent = '';
  }
  const observer = new MutationObserver(clearUnrelatedNameNoticeAfterSuccess);
  observer.observe(document.documentElement, {subtree:true, childList:true, characterData:true});
  window.addEventListener('hashchange', clearUnrelatedNameNoticeAfterSuccess);
})();
