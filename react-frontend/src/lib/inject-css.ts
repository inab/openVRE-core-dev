/** Inject a CSS string into <head> once (safe if multiple islands share a theme). */
export function injectCss(id: string, css: string): void {
  if (document.getElementById(id)) {
    return
  }

  const style = document.createElement('style')
  style.id = id
  style.textContent = css
  document.head.appendChild(style)
}
