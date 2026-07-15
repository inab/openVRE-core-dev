<?php

/**
 * Emit script tags for React island entry bundles.
 *
 * Controlled by OPENVRE_ENV:
 * - dev (default): Vite dev server with hot reload (REACT_VITE_DEV_SERVER)
 * - prod: static built assets from assets/react/
 *
 * Usage:
 *   <div id="workspace-file-table-root"></div>
 *   <?php react_island_scripts('workspace-file-table'); ?>
 */
function react_island_scripts(string ...$islands): void
{
	static $prepared = false;

	$env = getenv('OPENVRE_ENV') ?: 'dev';
	$viteDevServer = getenv('REACT_VITE_DEV_SERVER') ?: '';
	$useViteDevServer = $viteDevServer !== '' && !in_array($env, ['prod', 'production'], true);

	if ($useViteDevServer) {
		if (!$prepared) {
			$base = rtrim($viteDevServer, '/');
			// Required when loading Vite entries from PHP pages (not Vite's index.html).
			echo '<script type="module">' . "\n";
			echo 'import { injectIntoGlobalHook } from "' . $base . '/@react-refresh";' . "\n";
			echo 'injectIntoGlobalHook(window);' . "\n";
			echo 'window.$RefreshReg$ = () => {};' . "\n";
			echo 'window.$RefreshSig$ = () => (type) => type;' . "\n";
			echo '</script>' . "\n";
			echo '<script type="module" src="' . htmlspecialchars($base . '/@vite/client', ENT_QUOTES) . '"></script>' . "\n";
			$prepared = true;
		}

		foreach ($islands as $island) {
			if (!preg_match('/^[a-z0-9-]+$/', $island)) {
				continue;
			}

			$src = rtrim($viteDevServer, '/') . '/src/entries/' . $island . '.tsx';
			echo '<script type="module" src="' . htmlspecialchars($src, ENT_QUOTES) . '"></script>' . "\n";
		}

		return;
	}

	if (!$prepared && $islands !== []) {
		$vendorSrc = react_island_asset_url('react-vendor.js');
		echo '<link rel="modulepreload" href="' . htmlspecialchars($vendorSrc, ENT_QUOTES) . '">' . "\n";
		$prepared = true;
	}

	foreach ($islands as $island) {
		if (!preg_match('/^[a-z0-9-]+$/', $island)) {
			continue;
		}

		$src = react_island_asset_url($island . '.js');
		echo '<script type="module" src="' . htmlspecialchars($src, ENT_QUOTES) . '"></script>' . "\n";
	}
}

function react_island_asset_url(string $filename): string
{
	$src = 'assets/react/' . $filename;
	$path = dirname(__DIR__) . '/assets/react/' . $filename;

	if (is_readable($path)) {
		$src .= '?v=' . filemtime($path);
	}

	return $src;
}
