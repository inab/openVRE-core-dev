import { describe, expect, it, vi, afterEach } from 'vitest';

import filesFixture from './mocks/files.json';
import {
  getUserFiles,
  USER_FILES_DEFAULT_LIMIT,
  USER_FILES_DEFAULT_OFFSET,
  USER_FILES_URL,
  userFilesUrl,
} from './getUserFiles';

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('userFilesUrl', () => {
  it('uses the API defaults when no page is given', () => {
    expect(userFilesUrl()).toBe(
      `${USER_FILES_URL}?offset=${USER_FILES_DEFAULT_OFFSET}&limit=${USER_FILES_DEFAULT_LIMIT}`,
    );
  });

  it('puts the requested page in the query string', () => {
    expect(userFilesUrl({ offset: 50, limit: 25 })).toBe(
      `${USER_FILES_URL}?offset=50&limit=25`,
    );
  });
});

describe('getUserFiles', () => {
  it('rejects unauthorized responses and does not send a Bearer token', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ code: 'UNAUTHORIZED', status: 401 }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(getUserFiles()).rejects.toThrow('Failed to load files: 401');

    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(userFilesUrl());
    expect(url).toContain(USER_FILES_URL);
    expect(url).not.toContain('/api/v1');
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
  });

  it('rejects a missing-user 404', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(
        JSON.stringify({ code: 'NOT_FOUND', status: 404, message: 'User not found' }),
        {
          status: 404,
          headers: { 'Content-Type': 'application/json' },
        },
      ),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(getUserFiles()).rejects.toThrow('Failed to load files: 404');
  });

  it('returns the JSON body when authorized', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify(filesFixture), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(getUserFiles()).resolves.toEqual(filesFixture);

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(userFilesUrl());
    expect(url).not.toContain('/api/v1');
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
  });

  it('requests the given offset and limit', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify(filesFixture), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await getUserFiles({ offset: 100, limit: 25 });

    const [url] = fetchMock.mock.calls[0] as [string];
    expect(url).toBe(userFilesUrl({ offset: 100, limit: 25 }));
  });
});
