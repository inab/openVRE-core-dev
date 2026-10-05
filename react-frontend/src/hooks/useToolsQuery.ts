import { useQuery } from '@tanstack/react-query';

import { getTools } from '../api/getTools';
import { isAuthBffUnauthorizedError } from '../api/authBffFetch';
import { workspaceQueryKeys } from '../api/queryKeys';

type UseToolsQueryOptions = {
  /** Delay until files has refreshed the session JWT (avoids single-use RT races). */
  enabled?: boolean;
};

export function useToolsQuery(options: UseToolsQueryOptions = {}) {
  return useQuery({
    queryKey: workspaceQueryKeys.tools,
    queryFn: getTools,
    enabled: options.enabled ?? true,
    retry: (failureCount, error) => {
      if (isAuthBffUnauthorizedError(error)) {
        return false;
      }
      return failureCount < 2;
    },
  });
}
