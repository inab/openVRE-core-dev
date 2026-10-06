import { describe, expect, it } from 'vitest';

import {
  ACTIONS_BY_KIND,
  FILE_ITEM_ACTIONS,
  FILE_ITEM_KINDS,
} from '../../src/types/fileItemConstants';

describe('ACTIONS_BY_KIND', () => {
  it('defines the default action list for every kind', () => {
    expect(ACTIONS_BY_KIND).toEqual({
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
      [FILE_ITEM_KINDS.file_unavailable]: [FILE_ITEM_ACTIONS.delete],
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
      [FILE_ITEM_KINDS.folder_unavailable]: [FILE_ITEM_ACTIONS.delete_folder],
      [FILE_ITEM_KINDS.folder_uploads]: [FILE_ITEM_ACTIONS.download_folder],
      [FILE_ITEM_KINDS.folder_repository]: [FILE_ITEM_ACTIONS.download_folder],
    });
  });
});
