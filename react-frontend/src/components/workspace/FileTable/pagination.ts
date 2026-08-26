export const WORKSPACE_ROOT_PAGE_SIZE = 20 as const;
export const WORKSPACE_PAGE_SIZE_ALL = -1;

/** ComboBox options for workspace page size. */
export const WORKSPACE_PAGE_SIZE_ITEMS = [
  { id: '20', label: '20', size: 20 },
  { id: '50', label: '50', size: 50 },
  { id: '-1', label: 'All', size: WORKSPACE_PAGE_SIZE_ALL },
] as const;

export type WorkspacePageSize =
  (typeof WORKSPACE_PAGE_SIZE_ITEMS)[number]['size'];

export const WORKSPACE_PAGE_SIZE_OPTIONS: readonly WorkspacePageSize[] =
  WORKSPACE_PAGE_SIZE_ITEMS.map((item) => item.size);

export function pageSizeLabel(pageSize: number): string {
  return (
    WORKSPACE_PAGE_SIZE_ITEMS.find((item) => item.size === pageSize)?.label ??
    String(pageSize)
  );
}

/** Parse a ComboBox option id into a page size, or null if invalid/cleared. */
export function parseWorkspacePageSize(
  id: string | null,
): WorkspacePageSize | null {
  if (id == null) {
    return null;
  }
  return (
    WORKSPACE_PAGE_SIZE_ITEMS.find((option) => option.id === id)?.size ?? null
  );
}

/** Resolve a menu page size (including All) to a concrete slice length. */
export function resolvePageSize(pageSize: number, total: number): number {
  return pageSize === WORKSPACE_PAGE_SIZE_ALL ? total : pageSize;
}

/** Clamp a 0-based page index into [0, totalPages - 1], or 0 when empty. */
export function clampPageIndex(
  pageIndex: number,
  totalPages: number,
): number {
  if (totalPages <= 0) {
    return 0;
  }
  return Math.min(Math.max(0, pageIndex), totalPages - 1);
}

/** Slice a flat ordered entry list into a page (folders + files). */
export function pageRootFolders<T>(
  roots: T[],
  pageIndex: number,
  pageSize: number = WORKSPACE_ROOT_PAGE_SIZE,
): { page: T[]; offset: number; total: number; pageCount: number } {
  const total = roots.length;
  const effectiveSize = resolvePageSize(pageSize, total);
  const totalPages = totalPagesForRoots(total, pageSize);
  const safeIndex = clampPageIndex(pageIndex, totalPages);
  const offset = safeIndex * effectiveSize;
  const page = roots.slice(offset, offset + effectiveSize);

  return {
    page,
    offset,
    total,
    pageCount: page.length,
  };
}

/** Calculate the total number of pages for a given number of roots. */
export function totalPagesForRoots(
  totalRoots: number,
  pageSize: number = WORKSPACE_ROOT_PAGE_SIZE,
): number {
  if (totalRoots === 0) {
    return 0;
  }
  if (pageSize === WORKSPACE_PAGE_SIZE_ALL) {
    return 1;
  }
  return Math.ceil(totalRoots / pageSize);
}

/** Compact page number list with ellipsis gaps, e.g. [1, '…', 4, 5, 6, '…', 12]. */
export function pageSelectorItems(
  pageIndex: number,
  totalPages: number,
): Array<number | 'ellipsis'> {
  if (totalPages <= 0) {
    return [];
  }
  if (totalPages <= 7) {
    return Array.from({ length: totalPages }, (_, i) => i);
  }

  const current = pageIndex;
  const items: Array<number | 'ellipsis'> = [];
  const push = (value: number | 'ellipsis') => {
    const last = items[items.length - 1];
    if (value === 'ellipsis' && (last === 'ellipsis' || last === undefined)) {
      return;
    }
    items.push(value);
  };

  for (let i = 0; i < totalPages; i++) {
    const nearCurrent = Math.abs(i - current) <= 1;
    const isEdge = i === 0 || i === totalPages - 1;
    if (isEdge || nearCurrent) {
      push(i);
    } else {
      push('ellipsis');
    }
  }

  return items;
}
