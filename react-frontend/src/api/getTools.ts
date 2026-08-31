import type { Tool } from '../types/Tool';

export interface GetToolsResponse {
  tools: Tool[];
}

export const USER_TOOLS_URL = '/auth-bff/tools';

/**
 * Loads the tools catalog from GET /auth-bff/tools.
 * AuthBff serves the `tools` section of workspaceFixtures.json until
 * /api/v1/tools exists (independent of REACT_ISLAND_USE_FIXTURES).
 */
export async function getTools(): Promise<GetToolsResponse> {
  const response = await fetch(USER_TOOLS_URL, {
    credentials: 'same-origin',
  });
  if (!response.ok) {
    throw new Error(`Failed to load tools: ${response.status}`);
  }

  return (await response.json()) as GetToolsResponse;
}
