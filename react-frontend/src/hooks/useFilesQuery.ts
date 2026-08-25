import { useQuery } from '@tanstack/react-query';

import {
  getUserFiles,
  normalizeUserFilesParams,
  type GetUserFilesParams,
} from '../api/getUserFiles';
import { workspaceQueryKeys } from '../api/queryKeys';

export function useFilesQuery(params: GetUserFilesParams = {}) {
  const normalized = normalizeUserFilesParams(params);
  return useQuery({
    queryKey: workspaceQueryKeys.files(normalized),
    queryFn: () => getUserFiles(normalized),
  });
}
