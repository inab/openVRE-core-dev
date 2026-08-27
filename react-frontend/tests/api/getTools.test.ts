import { afterEach, describe, expect, it, vi } from 'vitest';

import { getTools, USER_TOOLS_URL } from '../../src/api/getTools';
import type { GetToolsResponse } from '../../src/api/getTools';

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

function stubJson(body: unknown, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  );
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

const toolsMock: GetToolsResponse = {
  tools: [
    {
      id: 'cohort_dataset',
      name: 'Cohort dataset',
      dataTypes: ['cohort_dataset'],
    },
  ],
};

describe('getTools', () => {
  it('fetches /auth-bff/tools with same-origin credentials', async () => {
    const fetchMock = stubJson(toolsMock);

    await expect(getTools()).resolves.toEqual(toolsMock);
    expect(fetchMock).toHaveBeenCalledWith(USER_TOOLS_URL, {
      credentials: 'same-origin',
    });
  });

  it('throws when the response is not ok', async () => {
    stubJson({ message: 'Nope' }, 502);

    await expect(getTools()).rejects.toThrow('Failed to load tools: 502');
  });
});
