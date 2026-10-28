import type {
  FileItemAction,
  FileItemKind,
  FileItemStatus,
  FileType,
} from './fileItemConstants';

/**
 * Flat list item from GET /files (no children on the wire).
 *
 * OpenAPI FileDto: identity + path + type + kind + status + actions + parentId.
 * Disk/remote warning comes from `kind`; metadata warning from `status`.
 * Cog menu items come from `actions` (unknown strings are skipped).
 */
export interface ApiFileItem {
  fileId: string;
  parentId: string | null;
  filename: string;
  path: string;
  type: FileType;
  format: string;
  dataType: string;
  date: string;
  size: number;
  kind: FileItemKind;
  status: FileItemStatus;
  actions: FileItemAction[];
}
