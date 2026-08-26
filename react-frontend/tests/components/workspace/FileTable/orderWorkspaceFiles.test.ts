import { describe, expect, it } from 'vitest';

import { orderWorkspaceFiles } from '../../../../src/components/workspace/FileTable/adapter/orderWorkspaceFiles';
import { workspaceFilesFixture } from '../../../../src/fixtures/workspaceFiles';
import type { ApiFileItem } from '../../../../src/types/ApiFileItem';
import {
  FILE_ITEM_KINDS,
  FILE_TYPES,
} from '../../../../src/types/fileItemConstants';

const base = {
  format: '',
  dataType: '',
  date: '2026-07-17T11:41:19.000+00:00',
  size: 0,
  status: 'idle' as const,
  actions: [] as ApiFileItem['actions'],
  isSelectable: true,
  isProtected: false,
};

function api(
  partial: Partial<ApiFileItem> &
    Pick<
      ApiFileItem,
      'fileId' | 'filename' | 'path' | 'kind' | 'type' | 'parentId'
    >,
): ApiFileItem {
  return { ...base, ...partial };
}

describe('orderWorkspaceFiles', () => {
  it('puts uploads and its children before other roots, then sorts by name', () => {
    const files = [
      api({
        fileId: 'zebra',
        parentId: null,
        filename: 'zebra',
        path: 'p/zebra',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder,
      }),
      api({
        fileId: 'child-z',
        parentId: 'zebra',
        filename: 'z.txt',
        path: 'p/zebra/z.txt',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file,
        status: 'ready',
      }),
      api({
        fileId: 'alpha',
        parentId: null,
        filename: 'alpha',
        path: 'p/alpha',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder,
      }),
      api({
        fileId: 'uploads',
        parentId: null,
        filename: 'uploads',
        path: 'p/uploads',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder_uploads,
        isSelectable: false,
        isProtected: true,
      }),
      api({
        fileId: 'up-b',
        parentId: 'uploads',
        filename: 'b.csv',
        path: 'p/uploads/b.csv',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file_unvalidated,
        status: 'unvalidated',
      }),
      api({
        fileId: 'up-a',
        parentId: 'uploads',
        filename: 'a.csv',
        path: 'p/uploads/a.csv',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file_unvalidated,
        status: 'unvalidated',
      }),
    ];

    expect(orderWorkspaceFiles(files).map((f) => f.fileId)).toEqual([
      'uploads',
      'up-a',
      'up-b',
      'alpha',
      'zebra',
      'child-z',
    ]);
  });

  it('places uploads first when ordering the raw workspace fixture', () => {
    const ordered = orderWorkspaceFiles(workspaceFilesFixture.files);
    expect(ordered[0]?.filename).toBe('uploads');
    expect(ordered[0]?.kind).toBe(FILE_ITEM_KINDS.folder_uploads);
  });
});
