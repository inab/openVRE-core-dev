import type {
  FileItemAction,
  FileItemKind,
  FileItemStatus,
  FileType,
} from './fileItemConstants';

/** Flat list item from GET /files (no children on the wire). */
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
  isSelectable: boolean;
  isProtected: boolean;
}
