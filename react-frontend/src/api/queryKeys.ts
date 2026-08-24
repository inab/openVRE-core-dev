import type { GetUserFilesParams } from './getUserFiles';

export const workspaceQueryKeys = {
  tools: ['workspace', 'tools'] as const,
  files: (params: GetUserFilesParams = {}) =>
    ['workspace', 'files', params] as const,
};
