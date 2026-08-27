import type { ApiFileItem } from '../types/ApiFileItem';
import workspaceFixtures from './workspaceFixtures.json';

/** Same shape as GetUserFilesResponse; kept local to avoid a cycle with getUserFiles. */
export interface WorkspaceFilesFixture {
  userId: string;
  offset: number;
  limit: number;
  total: number;
  files: ApiFileItem[];
}

/**
 * Semi-real workspace list derived from an IMPaCT VRE `filesAll` dump.
 * Test data only (adapter / order / filter). Live UI loads via GET /auth-bff/files;
 * when REACT_ISLAND_USE_FIXTURES=1 the BFF serves the `files` section of
 * `workspaceFixtures.json`.
 *
 * One cast at the JSON import boundary — trusted fixture data, not a live API body.
 */
export const workspaceFilesAll: ApiFileItem[] =
  workspaceFixtures.files as ApiFileItem[];

export const workspaceFilesFixture: WorkspaceFilesFixture = {
  userId: 'fixture-user',
  offset: 0,
  limit: workspaceFilesAll.length,
  total: workspaceFilesAll.length,
  files: workspaceFilesAll,
};
