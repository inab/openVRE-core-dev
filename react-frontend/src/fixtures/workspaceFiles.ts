import type { ApiFileItem } from '../types/ApiFileItem';
import {
  ACTIONS_BY_KIND,
  FILE_ITEM_KINDS,
  FILE_ITEM_STATUSES,
  FILE_TYPES,
} from '../types/fileItemConstants';

/** Same shape as GetUserFilesResponse; kept local to avoid a cycle with getUserFiles. */
export interface WorkspaceFilesFixture {
  userId: string;
  offset: number;
  limit: number;
  total: number;
  files: ApiFileItem[];
}

const PROJECT = 'PROJECTUSER_demo';
const UPLOADS_ID = `${PROJECT}_uploads`;
const REPOSITORY_ID = `${PROJECT}_repository`;
const EMPTY_FOLDER_ID = `${PROJECT}_empty`;
const ANALYSIS_FOLDER_ID = `${PROJECT}_analysis`;
const READY_FILE_ID = `${PROJECT}_sample_fastq`;
const UNVALIDATED_FILE_ID = `${PROJECT}_raw_csv`;
const NESTED_FILE_ID = `${PROJECT}_counts_tsv`;
const REPO_FILE_ID = `${PROJECT}_ref_fasta`;

const DATE = '2026-07-17T11:41:19.000+00:00';

function item(
  partial: Omit<ApiFileItem, 'actions'> & { actions?: ApiFileItem['actions'] },
): ApiFileItem {
  return {
    ...partial,
    actions: partial.actions ?? [...ACTIONS_BY_KIND[partial.kind]],
  };
}

/** Flat list fixture for painting the workspace table (not live API). */
export const workspaceFilesFixture: WorkspaceFilesFixture = {
  userId: 'fixture-user',
  offset: 0,
  limit: 50,
  total: 8,
  files: [
    item({
      fileId: UPLOADS_ID,
      parentId: null,
      filename: 'uploads',
      path: `${PROJECT}/uploads`,
      type: FILE_TYPES.dir,
      format: '',
      dataType: '',
      date: DATE,
      size: 3584,
      kind: FILE_ITEM_KINDS.folder_uploads,
      status: FILE_ITEM_STATUSES.idle,
      isSelectable: false,
      isProtected: true,
    }),
    item({
      fileId: REPOSITORY_ID,
      parentId: null,
      filename: 'repository',
      path: `${PROJECT}/repository`,
      type: FILE_TYPES.dir,
      format: '',
      dataType: '',
      date: DATE,
      size: 4096,
      kind: FILE_ITEM_KINDS.folder_repository,
      status: FILE_ITEM_STATUSES.idle,
      isSelectable: false,
      isProtected: true,
    }),
    item({
      fileId: READY_FILE_ID,
      parentId: UPLOADS_ID,
      filename: 'sample.fastq',
      path: `${PROJECT}/uploads/sample.fastq`,
      type: FILE_TYPES.file,
      format: 'fastq',
      dataType: 'fastq',
      date: DATE,
      size: 1024,
      kind: FILE_ITEM_KINDS.file,
      status: FILE_ITEM_STATUSES.ready,
      isSelectable: true,
      isProtected: false,
    }),
    item({
      fileId: UNVALIDATED_FILE_ID,
      parentId: UPLOADS_ID,
      filename: 'raw.csv',
      path: `${PROJECT}/uploads/raw.csv`,
      type: FILE_TYPES.file,
      format: 'csv',
      dataType: '',
      date: DATE,
      size: 512,
      kind: FILE_ITEM_KINDS.file_unvalidated,
      status: FILE_ITEM_STATUSES.unvalidated,
      isSelectable: true,
      isProtected: false,
    }),
    item({
      fileId: ANALYSIS_FOLDER_ID,
      parentId: UPLOADS_ID,
      filename: 'analysis',
      path: `${PROJECT}/uploads/analysis`,
      type: FILE_TYPES.dir,
      format: '',
      dataType: '',
      date: DATE,
      size: 2048,
      kind: FILE_ITEM_KINDS.folder,
      status: FILE_ITEM_STATUSES.idle,
      isSelectable: true,
      isProtected: false,
    }),
    item({
      fileId: NESTED_FILE_ID,
      parentId: ANALYSIS_FOLDER_ID,
      filename: 'counts.tsv',
      path: `${PROJECT}/uploads/analysis/counts.tsv`,
      type: FILE_TYPES.file,
      format: 'tsv',
      dataType: 'matrix',
      date: DATE,
      size: 2048,
      kind: FILE_ITEM_KINDS.file,
      status: FILE_ITEM_STATUSES.ready,
      isSelectable: true,
      isProtected: false,
    }),
    item({
      fileId: EMPTY_FOLDER_ID,
      parentId: UPLOADS_ID,
      filename: 'empty-folder',
      path: `${PROJECT}/uploads/empty-folder`,
      type: FILE_TYPES.dir,
      format: '',
      dataType: '',
      date: DATE,
      size: 0,
      kind: FILE_ITEM_KINDS.folder_empty,
      status: FILE_ITEM_STATUSES.idle,
      isSelectable: false,
      isProtected: false,
    }),
    item({
      fileId: REPO_FILE_ID,
      parentId: REPOSITORY_ID,
      filename: 'reference.fa',
      path: `${PROJECT}/repository/reference.fa`,
      type: FILE_TYPES.file,
      format: 'fasta',
      dataType: 'sequence_dna',
      date: DATE,
      size: 4096,
      kind: FILE_ITEM_KINDS.file,
      status: FILE_ITEM_STATUSES.ready,
      isSelectable: true,
      isProtected: false,
    }),
  ],
};
