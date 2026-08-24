import { useQuery } from '@tanstack/react-query';

import { getUserFiles, type GetUserFilesParams } from '../api/getUserFiles';
import { workspaceQueryKeys } from '../api/queryKeys';

export function useFilesQuery(params: GetUserFilesParams = {}) {
  return useQuery({
    queryKey: workspaceQueryKeys.files(params),
    queryFn: () => getUserFiles(params),
  });
}
