import type { LucideIcon } from 'lucide-react';
import {
  ArrowLeftRight,
  Download,
  FileArchive,
  Pencil,
  TextCursorInput,
  Trash2,
  TriangleAlert,
} from 'lucide-react';

import type { FileItemAction } from '../../../../types/fileItemConstants';
import { FILE_ITEM_ACTIONS } from '../../../../types/fileItemConstants';

export const FILE_ITEM_ACTION_ICONS: Record<FileItemAction, LucideIcon> = {
  [FILE_ITEM_ACTIONS.edit_metadata]: Pencil,
  [FILE_ITEM_ACTIONS.validate_metadata]: TriangleAlert,
  [FILE_ITEM_ACTIONS.rename]: TextCursorInput,
  [FILE_ITEM_ACTIONS.move]: ArrowLeftRight,
  [FILE_ITEM_ACTIONS.download]: Download,
  [FILE_ITEM_ACTIONS.delete]: Trash2,
  [FILE_ITEM_ACTIONS.compress]: FileArchive,
  [FILE_ITEM_ACTIONS.delete_folder]: Trash2,
  [FILE_ITEM_ACTIONS.download_folder]: Download,
};
