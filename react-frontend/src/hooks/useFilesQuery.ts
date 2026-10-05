import { useQuery } from '@tanstack/react-query';

import {
  getUserFiles,
  normalizeUserFilesParams,
  type GetUserFilesParams,
} from '../api/getUserFiles';
import { isAuthBffUnauthorizedError } from '../api/authBffFetch';
import { workspaceQueryKeys } from '../api/queryKeys';

export function useFilesQuery(params: GetUserFilesParams = {}) {
  const normalized = normalizeUserFilesParams(params);
  return useQuery({
    queryKey: workspaceQueryKeys.files(normalized),
    queryFn: () => getUserFiles(normalized),
    // Auth failures must not spam Keycloak refresh (single-use refresh tokens).
    retry: (failureCount, error) => {
      if (isAuthBffUnauthorizedError(error)) {
        return false;
      }
      return failureCount < 2;
    },
  });
}
