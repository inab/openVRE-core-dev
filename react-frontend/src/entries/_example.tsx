/**
 * Template for new islands — rename to `{name}.tsx` (drop the leading underscore).
 *
 * 1. Components import their own CSS (import './MyComponent.css')
 * 2. Add src/entries/{name}.css that @imports those same stylesheets for <head> preload
 * 3. import './{name}.css' here; call react_island_styles('{name}') from PHP <head>
 * 4. Mount: <?php react_island_root('my-component'); ?>
 * 5. Scripts: <?php react_island_scripts('my-component'); ?> before </body>
 */
import { mountIsland } from '../lib/mount-island';

mountIsland('react-component-root', () => <p>React component island</p>);
