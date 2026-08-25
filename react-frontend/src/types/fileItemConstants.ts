export const FILE_TYPES = {
  file: 'file',
  dir: 'dir',
} as const;
export type FileType = (typeof FILE_TYPES)[keyof typeof FILE_TYPES];

export const FILE_ITEM_KINDS = {
  file: 'file',
  file_unvalidated: 'file_unvalidated',
  folder: 'folder',
  folder_empty: 'folder_empty',
  folder_uploads: 'folder_uploads',
  folder_repository: 'folder_repository',
} as const;
export type FileItemKind =
  (typeof FILE_ITEM_KINDS)[keyof typeof FILE_ITEM_KINDS];

export const FILE_ITEM_STATUSES = {
  ready: 'ready',
  unvalidated: 'unvalidated',
  idle: 'idle',
} as const;
export type FileItemStatus =
  (typeof FILE_ITEM_STATUSES)[keyof typeof FILE_ITEM_STATUSES];

export const FILE_ITEM_ACTIONS = {
  edit_metadata: 'edit_metadata',
  validate_metadata: 'validate_metadata',
  rename: 'rename',
  move: 'move',
  download: 'download',
  delete: 'delete',
  compress: 'compress',
  delete_folder: 'delete_folder',
  download_folder: 'download_folder',
} as const;
export type FileItemAction =
  (typeof FILE_ITEM_ACTIONS)[keyof typeof FILE_ITEM_ACTIONS];

/** Default action lists by kind (for fixture / API authors). */
export const ACTIONS_BY_KIND: Record<FileItemKind, readonly FileItemAction[]> =
  {
    [FILE_ITEM_KINDS.file]: [
      FILE_ITEM_ACTIONS.edit_metadata,
      FILE_ITEM_ACTIONS.rename,
      FILE_ITEM_ACTIONS.move,
      FILE_ITEM_ACTIONS.download,
      FILE_ITEM_ACTIONS.delete,
      FILE_ITEM_ACTIONS.compress,
    ],
    [FILE_ITEM_KINDS.file_unvalidated]: [
      FILE_ITEM_ACTIONS.validate_metadata,
      FILE_ITEM_ACTIONS.rename,
      FILE_ITEM_ACTIONS.move,
      FILE_ITEM_ACTIONS.delete,
    ],
    [FILE_ITEM_KINDS.folder]: [
      FILE_ITEM_ACTIONS.rename,
      FILE_ITEM_ACTIONS.move,
      FILE_ITEM_ACTIONS.delete_folder,
      FILE_ITEM_ACTIONS.download_folder,
    ],
    [FILE_ITEM_KINDS.folder_empty]: [
      FILE_ITEM_ACTIONS.delete_folder,
      FILE_ITEM_ACTIONS.download_folder,
    ],
    [FILE_ITEM_KINDS.folder_uploads]: [FILE_ITEM_ACTIONS.download_folder],
    [FILE_ITEM_KINDS.folder_repository]: [FILE_ITEM_ACTIONS.download_folder],
  };
