import type { ApiFileItem } from '../types/ApiFileItem';

export interface GetUserFilesResponse {
  userId: string;
  offset: number;
  limit: number;
  total: number;
  files: ApiFileItem[];
}

/**
 * Query params for GET /files.
 * Omit `offset`/`limit` for the full list (matches FileController).
 * `normalizeUserFilesParams` clamps paging and trims/truncates `q`.
 */
export interface GetUserFilesParams {
  offset?: number;
  limit?: number;
  /** Optional case-insensitive path substring; omitted when empty. */
  q?: string;
}

export const USER_FILES_URL = '/auth-bff/files';

/** Used when the caller opts into paging with only one of offset/limit. */
export const USER_FILES_DEFAULT_OFFSET = 0;
export const USER_FILES_DEFAULT_LIMIT = 50;
export const USER_FILES_MAX_LIMIT = 200;
export const USER_FILES_MAX_Q_LENGTH = 200;

export function normalizeUserFilesParams(
  params: GetUserFilesParams = {},
): GetUserFilesParams {
  const { offset, limit, q } = params;
  const trimmedQ = q?.trim().slice(0, USER_FILES_MAX_Q_LENGTH);

  const normalized: GetUserFilesParams = {};
  if (offset !== undefined || limit !== undefined) {
    normalized.offset = Math.max(0, offset ?? USER_FILES_DEFAULT_OFFSET);
    normalized.limit = Math.min(
      USER_FILES_MAX_LIMIT,
      Math.max(1, limit ?? USER_FILES_DEFAULT_LIMIT),
    );
  }
  if (trimmedQ) {
    normalized.q = trimmedQ;
  }
  return normalized;
}

export function userFilesUrl(params: GetUserFilesParams = {}): string {
  const normalized = normalizeUserFilesParams(params);
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(normalized)) {
    if (value !== undefined) {
      query.set(key, String(value));
    }
  }
  const qs = query.toString();
  return qs ? `${USER_FILES_URL}?${qs}` : USER_FILES_URL;
}

/**
 * Loads the user's files from GET /auth-bff/files.
 *
 * Omit `limit`/`offset` for the full list. Pass either to page on the server.
 */
export async function getUserFiles(
  params: GetUserFilesParams = {},
): Promise<GetUserFilesResponse> {
  const response = await fetch(userFilesUrl(params), {
    credentials: 'same-origin',
  });
  if (!response.ok) {
    throw new Error(`Failed to load files: ${response.status}`);
  }

  return (await response.json()) as GetUserFilesResponse;
}
