/** Inject a CSS string into <head> once per id (updates in place on HMR). */
export function injectCss(id: string, css: string): void {
  const existing = document.getElementById(id);
  if (existing instanceof HTMLStyleElement) {
    existing.textContent = css;
    return;
  }

  const style = document.createElement('style');
  style.id = id;
  style.textContent = css;
  document.head.appendChild(style);
}
