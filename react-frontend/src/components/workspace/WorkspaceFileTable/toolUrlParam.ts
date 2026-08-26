export function getToolParam(): string | null {
  const tool = new URLSearchParams(window.location.search).get('tool');
  return tool && tool.length > 0 ? tool : null;
}

export function setToolParam(toolId: string | null): void {
  const url = new URL(window.location.href);
  if (toolId) {
    url.searchParams.set('tool', toolId);
  } else {
    url.searchParams.delete('tool');
  }
  window.history.replaceState(null, '', url);
}
