/**
 * Template for new islands — rename to `{name}.tsx` (drop the leading underscore).
 *
 * 1. Components import only their own CSS: import './MyComponent.css'
 * 2. This entry mounts the island (no CSS imports needed — components pull styles in)
 * 3. PHP <head>: react_island_theme() + react_island_styles('my-component')
 * 4. PHP body: react_island_root('my-component')
 * 5. PHP before </body>: react_island_scripts('my-component')
 */
import { mountIsland } from '../lib/mount-island';

mountIsland('react-component-root', () => <p>React component island</p>);
