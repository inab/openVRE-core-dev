/**
 * Template for new islands — rename to `{name}.tsx` (drop the leading underscore).
 *
 * 1. Add a mount point in PHP: <div id="react-component-root"></div>
 * 2. Create src/components/MyComponent.tsx
 * 3. Call react_island_scripts('my-component') before </body>
 */
import { mountIsland } from '../lib/mount-island'

mountIsland('react-component-root', () => <p>React component island</p>)
