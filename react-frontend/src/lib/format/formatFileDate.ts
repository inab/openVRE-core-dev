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
