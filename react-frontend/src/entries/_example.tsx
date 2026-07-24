/**
 * Template for new islands — rename to `{name}.tsx` (drop the leading underscore).
 *
 * 1. Components import only their own CSS: import './MyComponent.css'
 * 2. Shared utilities (e.g. tooltips) live in theme.css — use class + data attrs
 * 3. This entry mounts the island (no CSS imports needed — components pull styles in)
 * 4. PHP <head>: react_island_theme() + react_island_styles('my-component')
 * 5. PHP body: react_island_root('my-component')
 * 6. PHP before </body>: react_island_scripts('my-component')
 */
import { mountIsland } from '../lib/mount-island';

mountIsland('react-component-root', () => <p>React component island</p>);
