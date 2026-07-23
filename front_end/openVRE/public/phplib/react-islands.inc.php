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
 * CSS model:
 * - Each component imports only its own CSS (import './Button.css').
 * - Vite bundles those into assets/react/{island}.css in production.
 * - Dev: Vite injects CSS once via those imports (do not also <link> them — duplicates).
 * - Prod: react_island_styles() <link>s the built {island}.css from <head> before JS runs.
 *
 * Usage:
 *   <?php react_island_theme(); ?>                         // <head> — tokens
 *   <?php react_island_styles('workspace-file-table'); ?> // <head> — prod island CSS
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
 * Whether islands should load from the Vite dev server.
 */
function react_island_use_vite_dev_server(): bool
{
	$env = getenv('OPENVRE_ENV') ?: 'dev';
	$viteDevServer = getenv('REACT_VITE_DEV_SERVER') ?: '';

	return $viteDevServer !== '' && !in_array($env, ['prod', 'production'], true);
}

/**
 * Emit the mount root for an island when React islands are enabled.
 */
function react_island_root(string $island): void
{
	if (!react_island_enabled()) {
		return;
	}

	echo '<div id="' . htmlspecialchars($island, ENT_QUOTES, 'UTF-8') . '-root"></div>' . "\n";
}

/**
 * Emit theme (design tokens) stylesheet for React islands.
 * Call once from <head> so CSS variables exist before islands paint.
 */
function react_island_theme(): void
{
	static $emitted = false;

	if ($emitted || !react_island_enabled()) {
		return;
	}

	$emitted = true;

	if (react_island_use_vite_dev_server()) {
		$base = rtrim(getenv('REACT_VITE_DEV_SERVER'), '/');
		echo '<link rel="stylesheet" href="' . $base . '/src/styles/theme.css?direct">' . "\n";
		return;
	}

	$cssPath = dirname(__DIR__) . '/assets/react/theme.css';
	if (is_readable($cssPath)) {
		echo '<link rel="stylesheet" href="' . react_island_asset_url('theme.css') . '">' . "\n";
	}
}

/**
 * Emit built island CSS from <head> (production only).
 *
 * In Vite dev, component `import './X.css'` already injects styles once — linking
 * the same files here would duplicate every rule in DevTools.
 */
function react_island_styles(string ...$islands): void
{
	static $emitted = [];

	if (!react_island_enabled() || $islands === [] || react_island_use_vite_dev_server()) {
		return;
	}

	foreach ($islands as $island) {
		if (isset($emitted[$island]) || !preg_match('/^[a-z0-9-]+$/', $island)) {
			continue;
		}
		$emitted[$island] = true;

		$cssPath = dirname(__DIR__) . '/assets/react/' . $island . '.css';
		if (is_readable($cssPath)) {
			echo '<link rel="stylesheet" href="' . react_island_asset_url($island . '.css') . '">' . "\n";
		}
	}
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

	if (react_island_use_vite_dev_server()) {
		$viteDevServer = getenv('REACT_VITE_DEV_SERVER') ?: '';
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
			$prepared = true;
		}

		foreach ($islands as $island) {
			$src = rtrim($viteDevServer, '/') . '/src/entries/' . $island . '.tsx';
			echo '<script type="module" src="' . $src . '"></script>' . "\n";
		}

		return;
	}

	if (!$prepared) {
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
