import { describe, expect, it } from 'vitest';

import {
  isFileItemAction,
  isFolderKind,
  isUnavailableKind,
  isUnvalidatedKind,
  isUnvalidatedStatus,
  resolveFileItemActions,
} from '../../../src/lib/workspace/fileItemKind';
import {
  FILE_ITEM_ACTIONS,
  FILE_ITEM_KINDS,
  FILE_ITEM_STATUSES,
  UNAVAILABLE_KINDS,
  UNVALIDATED_KINDS,
  type FileItemKind,
} from '../../../src/types/fileItemConstants';

const ALL_KINDS = Object.values(FILE_ITEM_KINDS);

describe('isUnvalidatedKind', () => {
  it('is true only for kinds in UNVALIDATED_KINDS', () => {
    for (const kind of ALL_KINDS) {
      expect(isUnvalidatedKind(kind)).toBe(
        (UNVALIDATED_KINDS as readonly FileItemKind[]).includes(kind),
      );
    }
  });
});

describe('isUnavailableKind', () => {
  it('is true only for kinds in UNAVAILABLE_KINDS', () => {
    for (const kind of ALL_KINDS) {
      expect(isUnavailableKind(kind)).toBe(
        (UNAVAILABLE_KINDS as readonly FileItemKind[]).includes(kind),
      );
    }
  });
});

describe('isFolderKind', () => {
  it('is true for folder kinds and false for file kinds', () => {
    expect(isFolderKind(FILE_ITEM_KINDS.folder)).toBe(true);
    expect(isFolderKind(FILE_ITEM_KINDS.folder_empty)).toBe(true);
    expect(isFolderKind(FILE_ITEM_KINDS.folder_unavailable)).toBe(true);
    expect(isFolderKind(FILE_ITEM_KINDS.folder_uploads)).toBe(true);
    expect(isFolderKind(FILE_ITEM_KINDS.folder_repository)).toBe(true);

    expect(isFolderKind(FILE_ITEM_KINDS.file)).toBe(false);
    expect(isFolderKind(FILE_ITEM_KINDS.file_unvalidated)).toBe(false);
    expect(isFolderKind(FILE_ITEM_KINDS.file_unavailable)).toBe(false);
  });
});

describe('isUnvalidatedStatus', () => {
  it('is true only for unvalidated metadata status', () => {
    expect(isUnvalidatedStatus(FILE_ITEM_STATUSES.unvalidated)).toBe(true);
    expect(isUnvalidatedStatus(FILE_ITEM_STATUSES.ready)).toBe(false);
  });
});

describe('kind indicators (disjoint)', () => {
  it('never marks the same kind as both unvalidated and unavailable', () => {
    for (const kind of ALL_KINDS) {
      expect(isUnvalidatedKind(kind) && isUnavailableKind(kind)).toBe(false);
    }
  });

  it('maps each status kind to exactly one indicator', () => {
    expect(isUnavailableKind(FILE_ITEM_KINDS.file_unavailable)).toBe(true);
    expect(isUnvalidatedKind(FILE_ITEM_KINDS.file_unavailable)).toBe(false);

    expect(isUnavailableKind(FILE_ITEM_KINDS.folder_unavailable)).toBe(true);
    expect(isUnvalidatedKind(FILE_ITEM_KINDS.folder_unavailable)).toBe(false);

    expect(isUnvalidatedKind(FILE_ITEM_KINDS.file_unvalidated)).toBe(true);
    expect(isUnavailableKind(FILE_ITEM_KINDS.file_unvalidated)).toBe(false);

    expect(isUnvalidatedKind(FILE_ITEM_KINDS.file)).toBe(false);
    expect(isUnavailableKind(FILE_ITEM_KINDS.file)).toBe(false);
    expect(isUnvalidatedKind(FILE_ITEM_KINDS.folder)).toBe(false);
    expect(isUnavailableKind(FILE_ITEM_KINDS.folder)).toBe(false);
  });
});

describe('resolveFileItemActions', () => {
  it('keeps known actions in order, including an empty list', () => {
    expect(
      resolveFileItemActions({
        actions: [FILE_ITEM_ACTIONS.download],
      }),
    ).toEqual([FILE_ITEM_ACTIONS.download]);

    expect(resolveFileItemActions({ actions: [] })).toEqual([]);
  });

  it('skips unknown action strings', () => {
    expect(
      resolveFileItemActions({
        actions: ['download', 'not_a_real_action', 'delete'],
      }),
    ).toEqual([FILE_ITEM_ACTIONS.download, FILE_ITEM_ACTIONS.delete]);
  });
});

describe('isFileItemAction', () => {
  it('accepts FileAction enum values only', () => {
    expect(isFileItemAction('download')).toBe(true);
    expect(isFileItemAction('not_a_real_action')).toBe(false);
  });
});
