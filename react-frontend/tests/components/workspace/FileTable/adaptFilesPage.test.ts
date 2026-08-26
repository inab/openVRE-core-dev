import { describe, expect, it } from 'vitest';

import { adaptFilesPage } from '../../../../src/components/workspace/FileTable/adapter/adaptFilesPage';
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

describe('adaptFilesPage', () => {
  it('builds uploads and repository trees from the workspace fixture', () => {
    const roots = adaptFilesPage(
      orderWorkspaceFiles(workspaceFilesFixture.files),
    );
    const uploads = roots.find((r) => r.filename === 'uploads');
    const repository = roots.find((r) => r.filename === 'repository');

    expect(uploads?.kind).toBe(FILE_ITEM_KINDS.folder_uploads);
    expect(repository?.kind).toBe(FILE_ITEM_KINDS.folder_repository);

    expect(uploads?.children.map((c) => c.filename)).toEqual(
      expect.arrayContaining(['test.csv', 'sample_test.dta']),
    );
    expect(
      uploads?.children.find((c) => c.filename === 'test.csv')?.kind,
    ).toBe(FILE_ITEM_KINDS.file_unvalidated);

    expect(repository?.children.map((c) => c.filename)).toEqual(
      expect.arrayContaining([
        'simulacion_B2F3_BSC_impact.csv',
        '186v2-1.vcf',
      ]),
    );
  });

  it('puts uploads first among roots, then sorts the rest by name', () => {
    const files = [
      api({
        fileId: 'repo',
        parentId: null,
        filename: 'repository',
        path: 'p/repository',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder_repository,
        isSelectable: false,
        isProtected: true,
      }),
      api({
        fileId: 'zebra',
        parentId: null,
        filename: 'zebra',
        path: 'p/zebra',
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
        fileId: 'alpha',
        parentId: null,
        filename: 'alpha',
        path: 'p/alpha',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder,
      }),
    ];

    expect(
      adaptFilesPage(orderWorkspaceFiles(files)).map((r) => r.filename),
    ).toEqual(['uploads', 'alpha', 'repository', 'zebra']);
  });

  it('keeps uploads first on the workspace fixture roots', () => {
    const rootNames = adaptFilesPage(
      orderWorkspaceFiles(workspaceFilesFixture.files),
    ).map((r) => r.filename);
    expect(rootNames[0]).toBe('uploads');
    expect(rootNames.slice(1)).toEqual(
      [...rootNames.slice(1)].sort((a, b) =>
        a.localeCompare(b, undefined, { sensitivity: 'base', numeric: true }),
      ),
    );
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

  it('preserves input order when not pre-sorted', () => {
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
        fileId: 'alpha',
        parentId: null,
        filename: 'alpha',
        path: 'p/alpha',
        type: FILE_TYPES.dir,
        kind: FILE_ITEM_KINDS.folder,
      }),
    ];

    expect(adaptFilesPage(files).map((r) => r.filename)).toEqual([
      'zebra',
      'alpha',
    ]);
  });
});
