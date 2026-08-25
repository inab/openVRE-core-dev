import { describe, expect, it } from 'vitest';

import { adaptFilesPage } from '../../../../src/components/workspace/FileTable/adapter/adaptFilesPage';
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

describe('adaptFilesPage', () => {
  it('builds uploads and repository trees from the workspace fixture', () => {
    const roots = adaptFilesPage(workspaceFilesFixture.files);
    expect(roots.map((r) => r.filename)).toEqual(['uploads', 'repository']);

    const uploads = roots[0];
    expect(uploads?.children.map((c) => c.filename)).toEqual([
      'sample.fastq',
      'raw.csv',
      'analysis',
      'empty-folder',
    ]);
    expect(
      uploads?.children.find((c) => c.filename === 'analysis')?.children,
    ).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ filename: 'counts.tsv' }),
      ]),
    );
    expect(
      uploads?.children.find((c) => c.filename === 'empty-folder')?.children,
    ).toEqual([]);

    const repository = roots[1];
    expect(repository?.children.map((c) => c.filename)).toEqual([
      'reference.fa',
    ]);
  });

  it('nests children under parents present on the page', () => {
    const files = [
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
        fileId: 'child',
        parentId: 'uploads',
        filename: 'sample.fastq',
        path: 'p/uploads/sample.fastq',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file,
        status: 'ready',
        size: 10,
      }),
    ];

    const roots = adaptFilesPage(files);
    expect(roots).toHaveLength(1);
    expect(roots[0]?.fileId).toBe('uploads');
    expect(roots[0]?.children).toHaveLength(1);
    expect(roots[0]?.children[0]?.fileId).toBe('child');
    expect(roots[0]?.children[0]?.children).toEqual([]);
  });

  it('treats orphans whose parent is missing as page roots', () => {
    const files = [
      api({
        fileId: 'orphan',
        parentId: 'missing-parent',
        filename: 'orphan.txt',
        path: 'p/orphan.txt',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file,
        status: 'ready',
      }),
    ];

    const roots = adaptFilesPage(files);
    expect(roots).toHaveLength(1);
    expect(roots[0]?.fileId).toBe('orphan');
    expect(roots[0]?.children).toEqual([]);
  });

  it('preserves kind and actions from the payload', () => {
    const customActions: ApiFileItem['actions'] = ['download'];
    const files = [
      api({
        fileId: 'uploads',
        parentId: null,
        filename: 'uploads',
        path: 'p/uploads',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder_uploads,
        actions: customActions,
        isSelectable: false,
        isProtected: true,
      }),
    ];

    const [root] = adaptFilesPage(files);
    expect(root?.kind).toBe(FILE_ITEM_KINDS.folder_uploads);
    expect(root?.actions).toEqual(customActions);
  });
});
