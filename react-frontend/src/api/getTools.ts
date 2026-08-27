import type { Tool } from '../types/Tool';

export interface GetToolsResponse {
  tools: Tool[];
}

export const USER_TOOLS_URL = '/auth-bff/tools';

/**
 * Loads the tools catalog from GET /auth-bff/tools.
 * With REACT_ISLAND_USE_FIXTURES=1 the BFF serves the `tools` section of
 * src/fixtures/workspaceFixtures.json.
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
