import type { Tool } from '../types/Tool';
import toolsMock from './mocks/tools.json';

export interface GetToolsResponse {
  tools: Tool[];
}

export async function getTools(): Promise<GetToolsResponse> {
  return toolsMock as GetToolsResponse;
}
