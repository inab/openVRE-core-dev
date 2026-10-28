import type { FileItem } from '../../../lib/workspace/FileItem';
import {
  isFolderKind,
  isUnvalidatedStatus,
} from '../../../lib/workspace/fileItemKind';

/** jQuery `row-disabled` + `#87a2b9` on unvalidated file templates. */
export function getWorkspaceTableRowClassName(
  item: FileItem,
  selected: boolean,
): string {
  const classes = ['workspaceTableRow'];
  if (selected) {
    classes.push('workspaceTableRowSelected');
  }
  if (!isFolderKind(item.kind) && isUnvalidatedStatus(item.status)) {
    classes.push('workspaceTableRowUnvalidated');
  }
  return classes.join(' ');
}
