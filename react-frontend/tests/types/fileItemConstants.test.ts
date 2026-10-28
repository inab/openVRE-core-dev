import { describe, expect, it } from 'vitest';

import {
  FILE_ITEM_ACTIONS,
  FILE_ITEM_STATUSES,
} from '../../src/types/fileItemConstants';

describe('FILE_ITEM_ACTIONS', () => {
  it('matches the OpenAPI / FileAction enum values', () => {
    expect(Object.values(FILE_ITEM_ACTIONS).toSorted()).toEqual([
      'compress',
      'delete',
      'delete_folder',
      'download',
      'download_folder',
      'edit_metadata',
      'move',
      'rename',
      'validate_metadata',
    ]);
  });
});

describe('FILE_ITEM_STATUSES', () => {
  it('matches the OpenAPI status enum', () => {
    expect(Object.values(FILE_ITEM_STATUSES).toSorted()).toEqual([
      'ready',
      'unvalidated',
    ]);
  });
});
