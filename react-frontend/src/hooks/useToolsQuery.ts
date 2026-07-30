import { useQuery } from '@tanstack/react-query';

import { getTools } from '../api/getTools';
import { workspaceQueryKeys } from '../api/queryKeys';

export function useToolsQuery() {
  return useQuery({
    queryKey: workspaceQueryKeys.tools,
    queryFn: getTools,
  });
}
