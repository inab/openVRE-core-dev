import { QueryClientProvider } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { queryClient } from './query-client';

const roots = new WeakMap<Element, Root>();

export function mountIsland(rootId: string, render: () => ReactNode): boolean {
  const el = document.getElementById(rootId);
  if (!el) {
    return false;
  }

  let root = roots.get(el);
  if (!root) {
    root = createRoot(el);
    roots.set(el, root);
  }

  root.render(
    <QueryClientProvider client={queryClient}>{render()}</QueryClientProvider>,
  );
  return true;
}
