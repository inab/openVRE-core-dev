import type { ApiFileItem } from '../types/ApiFileItem';
import { workspaceFilesFixture } from '../fixtures/workspaceFiles';

export interface GetUserFilesResponse {
  userId: string;
  offset: number;
  limit: number;
  total: number;
  files: ApiFileItem[];
}

export interface GetUserFilesParams {
  offset?: number;
  limit?: number;
  /** Optional case-insensitive path substring; omitted when empty. */
  q?: string;
}

/** Params after defaults, trim, and empty-q omission — use in URLs and query keys. */
export interface NormalizedUserFilesParams {
  offset: number;
  limit: number;
  q?: string;
}

export const USER_FILES_URL = '/auth-bff/files';

/** Matches FileController defaults. */
export const USER_FILES_DEFAULT_OFFSET = 0;
export const USER_FILES_DEFAULT_LIMIT = 50;
export const USER_FILES_MAX_Q_LENGTH = 200;

export function normalizeUserFilesParams(
  params: GetUserFilesParams = {},
): NormalizedUserFilesParams {
  const offset = params.offset ?? USER_FILES_DEFAULT_OFFSET;
  const limit = params.limit ?? USER_FILES_DEFAULT_LIMIT;
  const trimmedQ = params.q?.trim().slice(0, USER_FILES_MAX_Q_LENGTH);
  if (trimmedQ) {
    return { offset, limit, q: trimmedQ };
  }
  return { offset, limit };
}

export function userFilesUrl(params: GetUserFilesParams = {}): string {
  const { offset, limit, q } = normalizeUserFilesParams(params);
  const query = new URLSearchParams({
    offset: String(offset),
    limit: String(limit),
  });
  if (q) {
    query.set('q', q);
  }
  return `${USER_FILES_URL}?${query}`;
}

/**
 * Returns the workspace files page.
 *
 * Defaults to the typed fixture so the table can ship before FileItem mapping
 * lands on GET /auth-bff/files. Pass `{ useFixture: false }` (or flip the
 * default) to hit the live `/auth-bff/files` fetch.
 */
export async function getUserFiles(
  params: GetUserFilesParams = {},
  { useFixture = true }: { useFixture?: boolean } = {},
): Promise<GetUserFilesResponse> {
  if (useFixture) {
    return workspaceFilesFixture;
  }

  const response = await fetch(userFilesUrl(params), {
    credentials: 'same-origin',
  });
  if (!response.ok) {
    throw new Error(`Failed to load files: ${response.status}`);
  }

  return (await response.json()) as GetUserFilesResponse;
}
