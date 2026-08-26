import { describe, expect, it } from 'vitest';

import {
  clampPageIndex,
  pageRootFolders,
  pageSelectorItems,
  pageSizeLabel,
  parseWorkspacePageSize,
  resolvePageSize,
  totalPagesForRoots,
  WORKSPACE_PAGE_SIZE_ALL,
  WORKSPACE_ROOT_PAGE_SIZE,
} from '../../../../src/components/workspace/FileTable/pagination';

describe('clampPageIndex', () => {
  it('returns 0 when there are no pages', () => {
    expect(clampPageIndex(0, 0)).toBe(0);
    expect(clampPageIndex(5, 0)).toBe(0);
    expect(clampPageIndex(-1, -2)).toBe(0);
  });

  it('clamps below zero up to the first page', () => {
    expect(clampPageIndex(-3, 4)).toBe(0);
  });

  it('clamps past the last page down to the last index', () => {
    expect(clampPageIndex(99, 3)).toBe(2);
  });

  it('leaves an in-range index unchanged', () => {
    expect(clampPageIndex(1, 3)).toBe(1);
  });
});

describe('pageRootFolders', () => {
  const roots = Array.from({ length: 45 }, (_, i) => `root-${i}`);

  it('slices the first page of roots', () => {
    const result = pageRootFolders(roots, 0);
    expect(result.offset).toBe(0);
    expect(result.total).toBe(45);
    expect(result.pageCount).toBe(WORKSPACE_ROOT_PAGE_SIZE);
    expect(result.page).toEqual(roots.slice(0, WORKSPACE_ROOT_PAGE_SIZE));
  });

  it('slices a middle page of roots', () => {
    const result = pageRootFolders(roots, 1);
    expect(result.offset).toBe(20);
    expect(result.page).toEqual(roots.slice(20, 40));
    expect(result.pageCount).toBe(20);
  });

  it('slices a short final page', () => {
    const result = pageRootFolders(roots, 2);
    expect(result.offset).toBe(40);
    expect(result.page).toEqual(roots.slice(40, 45));
    expect(result.pageCount).toBe(5);
  });

  it('clamps an out-of-range page index', () => {
    const result = pageRootFolders(roots, 99);
    expect(result.offset).toBe(40);
    expect(result.page).toEqual(roots.slice(40, 45));
    expect(result.pageCount).toBe(5);
  });

  it('clamps a negative page index to the first page', () => {
    const result = pageRootFolders(roots, -1);
    expect(result.offset).toBe(0);
    expect(result.page).toEqual(roots.slice(0, WORKSPACE_ROOT_PAGE_SIZE));
  });

  it('returns an empty page when there are no roots', () => {
    const result = pageRootFolders([], 0);
    expect(result).toEqual({
      page: [],
      offset: 0,
      total: 0,
      pageCount: 0,
    });
  });

  it('respects a custom page size', () => {
    const result = pageRootFolders(roots, 1, 10);
    expect(result.offset).toBe(10);
    expect(result.page).toEqual(roots.slice(10, 20));
    expect(result.pageCount).toBe(10);
  });

  it('returns every root when showing all', () => {
    const result = pageRootFolders(roots, 0, WORKSPACE_PAGE_SIZE_ALL);
    expect(result.offset).toBe(0);
    expect(result.page).toEqual(roots);
    expect(result.pageCount).toBe(45);
  });
});

describe('totalPagesForRoots', () => {
  it('returns zero for an empty list', () => {
    expect(totalPagesForRoots(0)).toBe(0);
  });

  it('rounds up by page size', () => {
    expect(totalPagesForRoots(45)).toBe(3);
    expect(totalPagesForRoots(20)).toBe(1);
    expect(totalPagesForRoots(21)).toBe(2);
  });

  it('respects a custom page size', () => {
    expect(totalPagesForRoots(25, 10)).toBe(3);
  });

  it('returns one page when showing all', () => {
    expect(totalPagesForRoots(45, WORKSPACE_PAGE_SIZE_ALL)).toBe(1);
    expect(totalPagesForRoots(0, WORKSPACE_PAGE_SIZE_ALL)).toBe(0);
  });
});

describe('resolvePageSize / pageSizeLabel', () => {
  it('keeps concrete sizes unchanged', () => {
    expect(resolvePageSize(20, 45)).toBe(20);
    expect(resolvePageSize(50, 45)).toBe(50);
  });

  it('resolves All to the total count', () => {
    expect(resolvePageSize(WORKSPACE_PAGE_SIZE_ALL, 45)).toBe(45);
  });

  it('labels All distinctly', () => {
    expect(pageSizeLabel(20)).toBe('20');
    expect(pageSizeLabel(WORKSPACE_PAGE_SIZE_ALL)).toBe('All');
  });
});

describe('parseWorkspacePageSize', () => {
  it('parses valid option ids', () => {
    expect(parseWorkspacePageSize('20')).toBe(20);
    expect(parseWorkspacePageSize('50')).toBe(50);
    expect(parseWorkspacePageSize('-1')).toBe(WORKSPACE_PAGE_SIZE_ALL);
  });

  it('returns null for cleared or invalid values', () => {
    expect(parseWorkspacePageSize(null)).toBeNull();
    expect(parseWorkspacePageSize('99')).toBeNull();
    expect(parseWorkspacePageSize('all')).toBeNull();
  });
});

describe('pageSelectorItems', () => {
  it('returns an empty list when there are no pages', () => {
    expect(pageSelectorItems(0, 0)).toEqual([]);
  });

  it('lists every page when there are few', () => {
    expect(pageSelectorItems(0, 5)).toEqual([0, 1, 2, 3, 4]);
    expect(pageSelectorItems(3, 7)).toEqual([0, 1, 2, 3, 4, 5, 6]);
  });

  it('inserts ellipses for a long range near the middle', () => {
    expect(pageSelectorItems(5, 12)).toEqual([
      0,
      'ellipsis',
      4,
      5,
      6,
      'ellipsis',
      11,
    ]);
  });

  it('shows the start of a long range without a leading ellipsis', () => {
    expect(pageSelectorItems(0, 12)).toEqual([
      0,
      1,
      'ellipsis',
      11,
    ]);
  });

  it('shows the end of a long range without a trailing ellipsis', () => {
    expect(pageSelectorItems(11, 12)).toEqual([
      0,
      'ellipsis',
      10,
      11,
    ]);
  });

  it('keeps neighbors around the current page', () => {
    expect(pageSelectorItems(1, 12)).toEqual([
      0,
      1,
      2,
      'ellipsis',
      11,
    ]);
    expect(pageSelectorItems(10, 12)).toEqual([
      0,
      'ellipsis',
      9,
      10,
      11,
    ]);
  });
});
