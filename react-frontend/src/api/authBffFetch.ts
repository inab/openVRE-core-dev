/** One-shot page reload guard shared by every AuthBff island fetch. */
export const AUTH_BFF_401_RELOAD_KEY = 'openvre:auth-bff-401-reload';

export class AuthBffUnauthorizedError extends Error {
  readonly status = 401 as const;

  constructor() {
    super('AuthBff unauthorized (401)');
    this.name = 'AuthBffUnauthorizedError';
  }
}

export function isAuthBffUnauthorizedError(error: unknown): boolean {
  return error instanceof AuthBffUnauthorizedError;
}

/**
 * Same-origin fetch for `/auth-bff/*`.
 *
 * On 401, reload the HTML page once so Apache/OIDC can refresh (or re-auth)
 * the session JWT. Does not write the PHP session from React.
 */
export async function authBffFetch(
  input: RequestInfo | URL,
  init?: RequestInit,
): Promise<Response> {
  const response = await fetch(input, {
    ...init,
    credentials: 'same-origin',
  });

  if (response.status === 401) {
    await recoverFromUnauthorized();
  }

  if (response.ok) {
    globalThis.sessionStorage?.removeItem(AUTH_BFF_401_RELOAD_KEY);
  }

  return response;
}

/** @returns never — either reloads (hangs) or throws */
async function recoverFromUnauthorized(): Promise<never> {
  const storage = globalThis.sessionStorage;
  if (storage && storage.getItem(AUTH_BFF_401_RELOAD_KEY) !== '1') {
    storage.setItem(AUTH_BFF_401_RELOAD_KEY, '1');
    globalThis.location.reload();
    await new Promise<never>(() => {
      /* page is reloading */
    });
  }
  storage?.removeItem(AUTH_BFF_401_RELOAD_KEY);
  throw new AuthBffUnauthorizedError();
}
