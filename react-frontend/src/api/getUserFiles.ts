export interface GetUserFilesResponse {
  userId: string;
  offset: number;
  limit: number;
  total: number;
  /** Filled when FileItem model exists. */
  files: unknown[];
}

export interface GetUserFilesParams {
  offset?: number;
  limit?: number;
  /** Optional case-insensitive path substring; omitted when empty. */
  q?: string;
}

export const USER_FILES_URL = '/auth-bff/files';

/** Matches FileController defaults. */
export const USER_FILES_DEFAULT_OFFSET = 0;
export const USER_FILES_DEFAULT_LIMIT = 50;

export function userFilesUrl({
  offset = USER_FILES_DEFAULT_OFFSET,
  limit = USER_FILES_DEFAULT_LIMIT,
  q,
}: GetUserFilesParams = {}): string {
  const query = new URLSearchParams({
    offset: String(offset),
    limit: String(limit),
  });
  const trimmedQ = q?.trim();
  if (trimmedQ) {
    query.set('q', trimmedQ);
  }
  return `${USER_FILES_URL}?${query}`;
}

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
