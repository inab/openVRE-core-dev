import { describe, expect, it } from 'vitest';

import {
  isFolderKind,
  isUnavailableKind,
  isUnvalidatedKind,
  resolveFileItemActions,
} from '../../../src/lib/workspace/fileItemKind';
import {
  ACTIONS_BY_KIND,
  FILE_ITEM_ACTIONS,
  FILE_ITEM_KINDS,
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
  it('uses ACTIONS_BY_KIND when actions are omitted', () => {
    expect(
      resolveFileItemActions({ kind: FILE_ITEM_KINDS.file_unvalidated }),
    ).toEqual(ACTIONS_BY_KIND[FILE_ITEM_KINDS.file_unvalidated]);
  });

  it('uses provided actions when present, including empty', () => {
    expect(
      resolveFileItemActions({
        kind: FILE_ITEM_KINDS.file,
        actions: [FILE_ITEM_ACTIONS.download],
      }),
    ).toEqual([FILE_ITEM_ACTIONS.download]);

    expect(
      resolveFileItemActions({
        kind: FILE_ITEM_KINDS.file,
        actions: [],
      }),
    ).toEqual([]);
  });
});
