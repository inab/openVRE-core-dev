import { describe, expect, it } from 'vitest';

import { getWorkspaceTableRowClassName } from '../../../../src/components/workspace/WorkspaceTable/workspaceTableRowClassName';
import type { FileItem } from '../../../../src/lib/workspace/FileItem';
import {
  FILE_ITEM_KINDS,
  FILE_ITEM_STATUSES,
  FILE_TYPES,
} from '../../../../src/types/fileItemConstants';

const base = {
  parentId: null,
  format: '',
  dataType: '',
  date: '2026-07-17T11:41:19.000+00:00',
  size: 0,
  actions: [] as FileItem['actions'],
  children: [] as FileItem[],
};

function item(
  partial: Pick<FileItem, 'fileId' | 'filename' | 'path' | 'kind' | 'type'> &
    Partial<FileItem>,
): FileItem {
  return {
    ...base,
    status: FILE_ITEM_STATUSES.ready,
    ...partial,
  };
}

describe('getWorkspaceTableRowClassName', () => {
  it('greys unvalidated files like jQuery row-disabled', () => {
    expect(
      getWorkspaceTableRowClassName(
        item({
          fileId: 'a',
          filename: 'sample_test.dta',
          path: 'p/sample_test.dta',
          type: FILE_TYPES.file,
          kind: FILE_ITEM_KINDS.file,
          status: FILE_ITEM_STATUSES.unvalidated,
        }),
        false,
      ),
    ).toBe('workspaceTableRow workspaceTableRowUnvalidated');
  });

  it('does not grey ready files, including missing-from-disk', () => {
    expect(
      getWorkspaceTableRowClassName(
        item({
          fileId: 'b',
          filename: 'ok.txt',
          path: 'p/ok.txt',
          type: FILE_TYPES.file,
          kind: FILE_ITEM_KINDS.file,
        }),
        false,
      ),
    ).toBe('workspaceTableRow');

    expect(
      getWorkspaceTableRowClassName(
        item({
          fileId: 'c',
          filename: 'gone.txt',
          path: 'p/gone.txt',
          type: FILE_TYPES.file,
          kind: FILE_ITEM_KINDS.file_unavailable,
        }),
        true,
      ),
    ).toBe('workspaceTableRow workspaceTableRowSelected');
  });

  it('does not grey folders', () => {
    expect(
      getWorkspaceTableRowClassName(
        item({
          fileId: 'd',
          filename: 'uploads',
          path: 'p/uploads',
          type: FILE_TYPES.dir,
          kind: FILE_ITEM_KINDS.folder,
          status: FILE_ITEM_STATUSES.unvalidated,
        }),
        false,
      ),
    ).toBe('workspaceTableRow');
  });
});
