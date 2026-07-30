import filesMock from './mocks/files.json';

export interface GetUserFilesResponse {
  userId: string;
  offset: number;
  limit: number;
  total: number;
  /** Filled when FileItem model exists. */
  files: unknown[];
}

export async function getUserFiles(): Promise<GetUserFilesResponse> {
  return filesMock as GetUserFilesResponse;
}
