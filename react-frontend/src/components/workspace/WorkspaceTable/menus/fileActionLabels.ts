import type { FileItemAction } from '../../../../types/fileItemConstants';
import { FILE_ITEM_ACTIONS } from '../../../../types/fileItemConstants';

export const FILE_ITEM_ACTION_LABELS: Record<FileItemAction, string> = {
  [FILE_ITEM_ACTIONS.edit_metadata]: 'Edit metadata',
  [FILE_ITEM_ACTIONS.validate_metadata]: 'Validate metadata',
  [FILE_ITEM_ACTIONS.rename]: 'Rename',
  [FILE_ITEM_ACTIONS.move]: 'Move',
  [FILE_ITEM_ACTIONS.download]: 'Download',
  [FILE_ITEM_ACTIONS.delete]: 'Delete',
  [FILE_ITEM_ACTIONS.compress]: 'Compress',
  [FILE_ITEM_ACTIONS.delete_folder]: 'Delete folder',
  [FILE_ITEM_ACTIONS.download_folder]: 'Download folder',
};

/** Stub until CSRF-hardened writes land on /auth-bff. */
export function stubFileAction(action: FileItemAction, fileId: string): void {
  console.info(`[workspace] stub action: ${action}`, { fileId });
}
