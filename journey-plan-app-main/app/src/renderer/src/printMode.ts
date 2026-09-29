// Print mode is only ever entered by the hidden PDF-export window
// (main/export/pdf.ts), which loads `#/analytics?planId=X&print=1` once and
// never navigates — so a module-load-time constant is safe and lets leaf
// components (chart panels) read it without prop threading. With the hash
// router the query string lives inside location.hash, not location.search.
export const PRINT_MODE: boolean = (() => {
  const qs = window.location.hash.split('?')[1] ?? '';
  return new URLSearchParams(qs).get('print') === '1';
})();

if (PRINT_MODE) document.body.classList.add('print-mode');
