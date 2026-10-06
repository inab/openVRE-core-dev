import type {
  FileItemAction,
  FileItemKind,
  FileItemStatus,
  FileType,
} from './fileItemConstants';

/**
 * Flat list item from GET /files (no children on the wire).
 *
 * OpenAPI FileDto: identity + path + type + kind + parentId.
 * UI derives indicators and default actions from `kind`.
 * Optional `actions` overrides the defaults; optional `status` is legacy/fixture-only.
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
  status?: FileItemStatus;
  actions?: FileItemAction[];
}
