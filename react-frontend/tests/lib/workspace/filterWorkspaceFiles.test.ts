import { describe, expect, it } from 'vitest';

import {
  filterFilesBySearch,
  filterFilesByTool,
} from '../../../src/lib/workspace/filterWorkspaceFiles';
import { adaptFilesPage } from '../../../src/lib/workspace/adaptFilesPage';
import type { ApiFileItem } from '../../../src/types/ApiFileItem';
import {
  FILE_ITEM_KINDS,
  FILE_TYPES,
} from '../../../src/types/fileItemConstants';

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

const files: ApiFileItem[] = [
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
    fileId: 'run',
    parentId: null,
    filename: 'run001',
    path: 'p/run001',
    type: FILE_TYPES.dir,
    kind: FILE_ITEM_KINDS.folder,
  }),
  api({
    fileId: 'sim',
    parentId: 'run',
    filename: 'simulacion_B1.csv',
    path: 'p/run001/simulacion_B1.csv',
    type: FILE_TYPES.file,
    kind: FILE_ITEM_KINDS.file,
    dataType: 'cohort_dataset',
    status: 'ready',
    size: 30,
  }),
];

describe('filterFilesBySearch', () => {
  it('keeps only matching nodes so files appear as roots', () => {
    const filtered = filterFilesBySearch(files, 'sim');
    expect(filtered.map((f) => f.fileId)).toEqual(['sim']);
    expect(adaptFilesPage(filtered).map((r) => r.filename)).toEqual([
      'simulacion_B1.csv',
    ]);
  });

  it('returns all files when the query is blank', () => {
    expect(filterFilesBySearch(files, '  ')).toEqual(files);
  });
});

describe('filterFilesByTool', () => {
  it('returns all files when no tool is selected', () => {
    expect(filterFilesByTool(files, null)).toEqual(files);
  });

  it('returns no files when the tool accepts no data types', () => {
    expect(filterFilesByTool(files, [])).toEqual([]);
  });

  it('keeps matching files and their ancestor folders', () => {
    const filtered = filterFilesByTool(files, ['cohort_dataset']);
    expect(filtered.map((file) => file.fileId)).toEqual(['run', 'sim']);
    expect(adaptFilesPage(filtered).map((row) => row.filename)).toEqual([
      'run001',
    ]);
  });

  it('excludes sibling files with a different data type', () => {
    const withSibling = [
      ...files,
      api({
        fileId: 'log',
        parentId: 'run',
        filename: '.tool.log',
        path: 'p/run001/.tool.log',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file,
        dataType: 'data_log',
        status: 'ready',
      }),
    ];

    expect(
      filterFilesByTool(withSibling, ['cohort_dataset']).map(
        (file) => file.fileId,
      ),
    ).toEqual(['run', 'sim']);
  });

  it('unions files across the tool data types', () => {
    const withLog = [
      ...files,
      api({
        fileId: 'log',
        parentId: 'uploads',
        filename: '.tool.log',
        path: 'p/uploads/.tool.log',
        type: FILE_TYPES.file,
        kind: FILE_ITEM_KINDS.file,
        dataType: 'data_log',
        status: 'ready',
      }),
    ];

    expect(
      filterFilesByTool(withLog, ['cohort_dataset', 'data_log']).map(
        (file) => file.fileId,
      ),
    ).toEqual(['uploads', 'run', 'sim', 'log']);
  });

  it('does not treat folders as matches', () => {
    const folderWithDataType = api({
      fileId: 'typed-dir',
      parentId: null,
      filename: 'typed',
      path: 'p/typed',
      type: FILE_TYPES.dir,
      kind: FILE_ITEM_KINDS.folder,
      dataType: 'cohort_dataset',
    });

    expect(
      filterFilesByTool([...files, folderWithDataType], ['cohort_dataset']).map(
        (file) => file.fileId,
      ),
    ).toEqual(['run', 'sim']);
  });
});
