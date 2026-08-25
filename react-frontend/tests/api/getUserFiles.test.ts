import { afterEach, describe, expect, it, vi } from 'vitest';

import { workspaceFilesFixture } from '../../src/fixtures/workspaceFiles';
import {
  getUserFiles,
  normalizeUserFilesParams,
  USER_FILES_DEFAULT_LIMIT,
  USER_FILES_DEFAULT_OFFSET,
  USER_FILES_MAX_Q_LENGTH,
  USER_FILES_URL,
  userFilesUrl,
} from '../../src/api/getUserFiles';
import { workspaceQueryKeys } from '../../src/api/queryKeys';
import getUserFilesResponseMock from '../mocks/getUserFilesResponse.json';

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

  it('adds a non-empty search query', () => {
    expect(userFilesUrl({ q: 'fastq' })).toBe(
      `${USER_FILES_URL}?offset=${USER_FILES_DEFAULT_OFFSET}&limit=${USER_FILES_DEFAULT_LIMIT}&q=fastq`,
    );
  });

  it('omits empty or whitespace-only search', () => {
    expect(userFilesUrl({ q: '   ' })).toBe(
      `${USER_FILES_URL}?offset=${USER_FILES_DEFAULT_OFFSET}&limit=${USER_FILES_DEFAULT_LIMIT}`,
    );
  });

  it('truncates a long search query', () => {
    const q = 'a'.repeat(USER_FILES_MAX_Q_LENGTH + 50);
    expect(userFilesUrl({ q })).toBe(
      `${USER_FILES_URL}?offset=${USER_FILES_DEFAULT_OFFSET}&limit=${USER_FILES_DEFAULT_LIMIT}&q=${'a'.repeat(USER_FILES_MAX_Q_LENGTH)}`,
    );
  });
});

describe('normalizeUserFilesParams', () => {
  it('applies defaults and omits empty q', () => {
    expect(normalizeUserFilesParams()).toEqual({
      offset: USER_FILES_DEFAULT_OFFSET,
      limit: USER_FILES_DEFAULT_LIMIT,
    });
    expect(normalizeUserFilesParams({ q: '   ' })).toEqual({
      offset: USER_FILES_DEFAULT_OFFSET,
      limit: USER_FILES_DEFAULT_LIMIT,
    });
    expect(normalizeUserFilesParams({ offset: 0, limit: 50 })).toEqual({
      offset: USER_FILES_DEFAULT_OFFSET,
      limit: USER_FILES_DEFAULT_LIMIT,
    });
  });

  it('keeps a trimmed search query', () => {
    expect(normalizeUserFilesParams({ q: '  fastq  ' })).toEqual({
      offset: USER_FILES_DEFAULT_OFFSET,
      limit: USER_FILES_DEFAULT_LIMIT,
      q: 'fastq',
    });
  });
});

describe('workspaceQueryKeys.files', () => {
  it('uses the same key for equivalent paging and empty search', () => {
    expect(workspaceQueryKeys.files()).toEqual(
      workspaceQueryKeys.files({ offset: 0, limit: 50 }),
    );
    expect(workspaceQueryKeys.files()).toEqual(
      workspaceQueryKeys.files({ q: '' }),
    );
    expect(workspaceQueryKeys.files()).toEqual(
      workspaceQueryKeys.files({ q: '   ' }),
    );
  });
});

describe('getUserFiles (fixture)', () => {
  it('returns the workspace files fixture for the UI (coworker gap)', async () => {
    await expect(getUserFiles()).resolves.toEqual(workspaceFilesFixture);
  });

  it('fixture includes uploads and repository roots with parentId links', async () => {
    const { files, total } = await getUserFiles();
    expect(total).toBe(files.length);

    const uploads = files.find((f) => f.filename === 'uploads');
    const repository = files.find((f) => f.filename === 'repository');
    expect(uploads?.parentId).toBeNull();
    expect(uploads?.kind).toBe('folder_uploads');
    expect(repository?.parentId).toBeNull();
    expect(repository?.kind).toBe('folder_repository');

    const child = files.find((f) => f.filename === 'sample.fastq');
    expect(child?.parentId).toBe(uploads?.fileId);
  });
});

describe('getUserFiles (live fetch)', () => {
  it('rejects unauthorized responses and does not send a Bearer token', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ code: 'UNAUTHORIZED', status: 401 }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(getUserFiles({}, { useFixture: false })).rejects.toThrow(
      'Failed to load files: 401',
    );

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
        JSON.stringify({
          code: 'NOT_FOUND',
          status: 404,
          message: 'User not found',
        }),
        {
          status: 404,
          headers: { 'Content-Type': 'application/json' },
        },
      ),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(getUserFiles({}, { useFixture: false })).rejects.toThrow(
      'Failed to load files: 404',
    );
  });

  it('returns the JSON body when authorized', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify(getUserFilesResponseMock), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(getUserFiles({}, { useFixture: false })).resolves.toEqual(
      getUserFilesResponseMock,
    );

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(userFilesUrl());
    expect(url).not.toContain('/api/v1');
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
  });

  it('requests the given offset and limit', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify(getUserFilesResponseMock), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await getUserFiles({ offset: 100, limit: 25 }, { useFixture: false });

    const [url] = fetchMock.mock.calls[0] as [string];
    expect(url).toBe(userFilesUrl({ offset: 100, limit: 25 }));
  });
});
