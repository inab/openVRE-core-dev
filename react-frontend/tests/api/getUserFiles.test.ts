import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  getUserFiles,
  normalizeUserFilesParams,
  USER_FILES_DEFAULT_LIMIT,
  USER_FILES_DEFAULT_OFFSET,
  USER_FILES_MAX_Q_LENGTH,
  USER_FILES_URL,
  userFilesUrl,
  type GetUserFilesResponse,
} from '../../src/api/getUserFiles';
import { workspaceQueryKeys } from '../../src/api/queryKeys';
import getUserFilesFullMockJson from '../mocks/getUserFilesResponse.json';

/** Single JSON→typed boundary for this file’s stubs. */
const fullMock = getUserFilesFullMockJson as GetUserFilesResponse;
const sampleFile = fullMock.files[1];
if (sampleFile == null) {
  throw new Error('getUserFilesResponse.json must include at least 2 files');
}

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

const pageOffsetLimitMock: GetUserFilesResponse = {
  userId: 'mock-user',
  offset: 50,
  limit: 25,
  total: 100,
  files: [
    {
      ...sampleFile,
      fileId: 'page-file',
      filename: 'page.txt',
      path: 'p/page.txt',
      parentId: null,
      kind: 'file',
      status: 'ready',
      actions: ['download'],
    },
  ],
};

const pageLimitOnlyMock: GetUserFilesResponse = {
  userId: 'mock-user',
  offset: 0,
  limit: 10,
  total: 40,
  files: fullMock.files.slice(0, 1),
};

const pageOffsetOnlyMock: GetUserFilesResponse = {
  userId: 'mock-user',
  offset: 10,
  limit: USER_FILES_DEFAULT_LIMIT,
  total: 80,
  files: fullMock.files.slice(1),
};

const searchPageMock: GetUserFilesResponse = {
  userId: 'mock-user',
  offset: 0,
  limit: 10,
  total: 1,
  files: [
    {
      ...sampleFile,
      path: 'p/uploads/readme.txt',
      filename: 'readme.txt',
    },
  ],
};

describe('userFilesUrl', () => {
  it('omits paging params when none are given (full list)', () => {
    expect(userFilesUrl()).toBe(USER_FILES_URL);
  });

  it('puts the requested page in the query string', () => {
    expect(userFilesUrl({ offset: 50, limit: 25 })).toBe(
      `${USER_FILES_URL}?offset=50&limit=25`,
    );
  });

  it('adds a non-empty search query without injecting paging', () => {
    expect(userFilesUrl({ q: 'fastq' })).toBe(`${USER_FILES_URL}?q=fastq`);
  });

  it('omits empty or whitespace-only search', () => {
    expect(userFilesUrl({ q: '   ' })).toBe(USER_FILES_URL);
  });

  it('truncates a long search query', () => {
    const q = 'a'.repeat(USER_FILES_MAX_Q_LENGTH + 50);
    expect(userFilesUrl({ q })).toBe(
      `${USER_FILES_URL}?q=${'a'.repeat(USER_FILES_MAX_Q_LENGTH)}`,
    );
  });
});

describe('normalizeUserFilesParams', () => {
  it('omits paging and empty q by default', () => {
    expect(normalizeUserFilesParams()).toEqual({});
    expect(normalizeUserFilesParams({ q: '   ' })).toEqual({});
  });

  it('keeps explicit paging', () => {
    expect(normalizeUserFilesParams({ offset: 0, limit: 50 })).toEqual({
      offset: USER_FILES_DEFAULT_OFFSET,
      limit: USER_FILES_DEFAULT_LIMIT,
    });
  });

  it('defaults the missing paging half when only one is set', () => {
    expect(normalizeUserFilesParams({ offset: 10 })).toEqual({
      offset: 10,
      limit: USER_FILES_DEFAULT_LIMIT,
    });
    expect(normalizeUserFilesParams({ limit: 25 })).toEqual({
      offset: USER_FILES_DEFAULT_OFFSET,
      limit: 25,
    });
  });

  it('keeps a trimmed search query', () => {
    expect(normalizeUserFilesParams({ q: '  fastq  ' })).toEqual({
      q: 'fastq',
    });
  });
});

describe('workspaceQueryKeys.files', () => {
  it('uses the same key for empty search and no paging', () => {
    expect(workspaceQueryKeys.files()).toEqual(
      workspaceQueryKeys.files({ q: '' }),
    );
    expect(workspaceQueryKeys.files()).toEqual(
      workspaceQueryKeys.files({ q: '   ' }),
    );
  });
});

describe('getUserFiles (fetch stubs)', () => {
  it('requests the full list when no paging params are given', async () => {
    const fetchMock = stubJson(fullMock);

    await expect(getUserFiles({})).resolves.toEqual(fullMock);

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(USER_FILES_URL);
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
  });

  it('requests offset and limit together', async () => {
    const fetchMock = stubJson(pageOffsetLimitMock);

    await expect(getUserFiles({ offset: 50, limit: 25 })).resolves.toEqual(
      pageOffsetLimitMock,
    );

    expect(fetchMock.mock.calls[0]?.[0]).toBe(
      userFilesUrl({ offset: 50, limit: 25 }),
    );
  });

  it('requests limit only (offset defaults to 0 in the URL)', async () => {
    const fetchMock = stubJson(pageLimitOnlyMock);

    await expect(getUserFiles({ limit: 10 })).resolves.toEqual(
      pageLimitOnlyMock,
    );

    expect(fetchMock.mock.calls[0]?.[0]).toBe(userFilesUrl({ limit: 10 }));
  });

  it('requests offset only (limit defaults to 50 in the URL)', async () => {
    const fetchMock = stubJson(pageOffsetOnlyMock);

    await expect(getUserFiles({ offset: 10 })).resolves.toEqual(
      pageOffsetOnlyMock,
    );

    expect(fetchMock.mock.calls[0]?.[0]).toBe(userFilesUrl({ offset: 10 }));
  });

  it('requests q and limit together', async () => {
    const fetchMock = stubJson(searchPageMock);

    await expect(getUserFiles({ q: 'uploads', limit: 10 })).resolves.toEqual(
      searchPageMock,
    );

    expect(fetchMock.mock.calls[0]?.[0]).toBe(
      userFilesUrl({ q: 'uploads', limit: 10 }),
    );
    expect(searchPageMock.files.every((f) => /uploads/i.test(f.path))).toBe(
      true,
    );
  });

  it('returns uploads and linked children from the mock body', async () => {
    stubJson(fullMock);

    const { files } = await getUserFiles({});
    const uploads = files.find((f) => f.filename === 'uploads');
    const child = files.find((f) => f.filename === 'test.csv');

    expect(uploads?.parentId).toBeNull();
    expect(uploads?.kind).toBe('folder_uploads');
    expect(uploads?.actions).toContain('download_folder');
    expect(child?.parentId).toBe(uploads?.fileId);
    expect(child?.kind).toBe('file_unvalidated');
    expect(child?.actions).toContain('validate_metadata');
  });

  it('rejects unauthorized responses and does not send a Bearer token', async () => {
    const fetchMock = stubJson(
      { code: 'UNAUTHORIZED', status: 401 },
      401,
    );

    await expect(getUserFiles({})).rejects.toThrow(
      'Failed to load files: 401',
    );

    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(userFilesUrl());
    expect(url).not.toContain('/api/v1');
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
  });

  it('rejects a missing-user 404', async () => {
    stubJson(
      {
        code: 'NOT_FOUND',
        status: 404,
        message: 'User not found',
      },
      404,
    );

    await expect(getUserFiles({})).rejects.toThrow(
      'Failed to load files: 404',
    );
  });
});
