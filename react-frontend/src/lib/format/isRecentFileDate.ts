/** Match jQuery workspace: badge when age is under one hour. */
export const RECENT_FILE_MAX_AGE_MS = 60 * 60 * 1000;

/** IMPD-style label for the recently-added badge. */
export const RECENT_FILE_LABEL = 'new';

/**
 * True when `iso` parses to a time within the last hour relative to `now`.
 * Invalid or empty dates are not recent.
 */
export function isRecentFileDate(
  iso: string,
  now: Date = new Date(),
): boolean {
  if (!iso) {
    return false;
  }

  const then = new Date(iso);
  if (Number.isNaN(then.getTime())) {
    return false;
  }

  const ageMs = now.getTime() - then.getTime();
  return ageMs >= 0 && ageMs < RECENT_FILE_MAX_AGE_MS;
}
