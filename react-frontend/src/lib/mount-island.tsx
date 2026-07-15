import type { ReactNode } from 'react'
import { createRoot, type Root } from 'react-dom/client'

const roots = new WeakMap<Element, Root>()

export function mountIsland(rootId: string, render: () => ReactNode): boolean {
  const el = document.getElementById(rootId)
  if (!el) {
    return false
  }

  let root = roots.get(el)
  if (!root) {
    root = createRoot(el)
    roots.set(el, root)
  }

  root.render(render())
  return true
}
