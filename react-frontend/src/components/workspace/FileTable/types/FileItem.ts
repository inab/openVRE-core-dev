import type { ApiFileItem } from '../../../../types/ApiFileItem';

/** UI tree node: API fields plus children built by the page adapter. */
export interface FileItem extends ApiFileItem {
  children: FileItem[];
}
