export function formatFileSize(bytes: number): string {
  if (bytes < 0) {
    return '';
  }

  if (bytes === 0) {
    return '0 B';
  }

  const units = ['B', 'KB', 'MB', 'GB', 'TB'] as const;
  let value = bytes;
  let unitIndex = 0;
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }

  const rounded =
    value >= 10 || unitIndex === 0
      ? Math.round(value)
      : Math.round(value * 10) / 10;
  return `${rounded} ${units[unitIndex]}`;
}

function pad2(value: number): string {
  return String(value).padStart(2, '0');
}

export function formatFileDate(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }

  const year = date.getFullYear();
  const month = date.getMonth() + 1;
  const day = date.getDate();
  return `${year}.${month}.${day} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

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
