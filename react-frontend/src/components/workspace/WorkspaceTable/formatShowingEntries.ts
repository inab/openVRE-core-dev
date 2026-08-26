export function formatShowingEntries(
  offset: number,
  pageCount: number,
  total: number,
): string {
  if (total === 0 || pageCount === 0) {
    return 'Showing 0 to 0 of 0 entries';
  }

  const from = offset + 1;
  const to = offset + pageCount;
  return `Showing ${from} to ${to} of ${total} entries`;
}
