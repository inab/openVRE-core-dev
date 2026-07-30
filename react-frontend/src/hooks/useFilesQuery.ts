import { useQuery } from '@tanstack/react-query';

import { getUserFiles } from '../api/getUserFiles';
import { workspaceQueryKeys } from '../api/queryKeys';

export function useFilesQuery() {
  return useQuery({
    queryKey: workspaceQueryKeys.files,
    queryFn: getUserFiles,
  });
}
