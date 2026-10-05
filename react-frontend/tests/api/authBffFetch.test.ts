import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  AUTH_BFF_401_RELOAD_KEY,
  AuthBffUnauthorizedError,
  authBffFetch,
  isAuthBffUnauthorizedError,
} from '../../src/api/authBffFetch';

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  globalThis.sessionStorage?.removeItem(AUTH_BFF_401_RELOAD_KEY);
});

function stubFetch(status: number, body: unknown = {}) {
  const fetchMock = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  );
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('authBffFetch', () => {
  it('sends same-origin credentials and returns ok responses', async () => {
    const fetchMock = stubFetch(200, { ok: true });

    const response = await authBffFetch('/auth-bff/files');

    expect(response.ok).toBe(true);
    expect(fetchMock).toHaveBeenCalledWith('/auth-bff/files', {
      credentials: 'same-origin',
    });
  });

  it('reloads once on 401 so HTML/OIDC can recover the session JWT', async () => {
    const store = new Map<string, string>();
    const reload = vi.fn();
    vi.stubGlobal('sessionStorage', {
      getItem: (key: string) => store.get(key) ?? null,
      setItem: (key: string, value: string) => {
        store.set(key, value);
      },
      removeItem: (key: string) => {
        store.delete(key);
      },
    });
    vi.stubGlobal('location', { reload });
    stubFetch(401, { code: 'UNAUTHORIZED' });

    const pending = authBffFetch('/auth-bff/files');
    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledOnce();
    });
    expect(store.get(AUTH_BFF_401_RELOAD_KEY)).toBe('1');
    void pending;
  });

  it('throws AuthBffUnauthorizedError after a failed reload', async () => {
    const store = new Map<string, string>([[AUTH_BFF_401_RELOAD_KEY, '1']]);
    const reload = vi.fn();
    vi.stubGlobal('sessionStorage', {
      getItem: (key: string) => store.get(key) ?? null,
      setItem: (key: string, value: string) => {
        store.set(key, value);
      },
      removeItem: (key: string) => {
        store.delete(key);
      },
    });
    vi.stubGlobal('location', { reload });
    stubFetch(401, { code: 'UNAUTHORIZED' });

    await expect(authBffFetch('/auth-bff/tools')).rejects.toThrow(
      AuthBffUnauthorizedError,
    );
    expect(reload).not.toHaveBeenCalled();
    expect(store.has(AUTH_BFF_401_RELOAD_KEY)).toBe(false);
  });

  it('does not send a Bearer token', async () => {
    const fetchMock = stubFetch(401);
    vi.stubGlobal('sessionStorage', {
      getItem: () => '1',
      setItem: () => undefined,
      removeItem: () => undefined,
    });

    await expect(authBffFetch('/auth-bff/files')).rejects.toThrow(
      AuthBffUnauthorizedError,
    );

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
  });
});

describe('isAuthBffUnauthorizedError', () => {
  it('detects AuthBffUnauthorizedError', () => {
    expect(isAuthBffUnauthorizedError(new AuthBffUnauthorizedError())).toBe(
      true,
    );
    expect(isAuthBffUnauthorizedError(new Error('Failed to load files: 401'))).toBe(
      false,
    );
  });
});
