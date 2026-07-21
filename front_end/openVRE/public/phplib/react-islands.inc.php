<?php

/**
 * React island helpers.
 *
 * Gated by REACT_ISLAND_ENABLED=true|false (default: false).
 *
 * Controlled by OPENVRE_ENV:
 * - dev (default): Vite dev server with hot reload (REACT_VITE_DEV_SERVER)
 * - prod: static built assets from assets/react/
 *
 * Theme CSS is loaded once for all islands; component CSS ships inside each island JS.
 *
 * Usage:
 *   <?php react_island_root('workspace-file-table'); ?>
 *   <?php react_island_scripts('workspace-file-table'); ?>
 */

/**
 * Whether React islands are enabled via REACT_ISLAND_ENABLED (default: false).
 */
function react_island_enabled(): bool
{
	$value = getenv('REACT_ISLAND_ENABLED');

	if ($value === false || $value === '') {
		return false;
	}

	return strtolower($value) === 'true';
}

/**
 * Emit the mount root for an island when React islands are enabled.
 */
function react_island_root(string $island): void
{
	if (!react_island_enabled()) {
		return;
	}

	echo '<div id="' . $island . '-root"></div>' . "\n";
}

/**
 * Emit script tags for React island entry bundles (no-op when disabled).
 */
function react_island_scripts(string ...$islands): void
{
	static $prepared = false;

	if (!react_island_enabled() || $islands === []) {
		return;
	}

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
			echo '<script type="module" src="' . $base . '/@vite/client"></script>' . "\n";
			echo '<link rel="stylesheet" href="' . $base . '/src/styles/theme.css?direct">' . "\n";
			$prepared = true;
		}

		foreach ($islands as $island) {
			$src = rtrim($viteDevServer, '/') . '/src/entries/' . $island . '.tsx';
			echo '<script type="module" src="' . $src . '"></script>' . "\n";
		}

		return;
	}

	if (!$prepared) {
		$cssPath = dirname(__DIR__) . '/assets/react/theme.css';
		if (is_readable($cssPath)) {
			$cssSrc = react_island_asset_url('theme.css');
			echo '<link rel="stylesheet" href="' . $cssSrc . '">' . "\n";
		}

		$vendorSrc = react_island_asset_url('react-vendor.js');
		echo '<link rel="modulepreload" href="' . $vendorSrc . '">' . "\n";
		$prepared = true;
	}

	foreach ($islands as $island) {
		$src = react_island_asset_url($island . '.js');
		echo '<script type="module" src="' . $src . '"></script>' . "\n";
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
