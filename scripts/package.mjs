#!/usr/bin/env node
/**
 * Build a clean release ZIP for WordPress.org submission.
 *
 * Pipeline:
 *   1. `npm run build`                              (wp-scripts builds the editor + block JS)
 *   2. `composer install --no-dev --optimize-autoloader`  (clean autoloader)
 *   3. Stage only runtime files into dist/rootstuff-relationships/.
 *   4. Zip the staged folder into dist/rootstuff-relationships.zip.
 *
 * What goes in the ZIP:
 *   - rootstuff-relationships.php
 *   - readme.txt
 *   - uninstall.php
 *   - LICENSE
 *   - src/
 *   - vendor/                       (composer autoloader, required at runtime)
 *   - blocks/                       (block.json, render.php, etc.)
 *   - assets/css/
 *   - assets/js/build/              (wp-scripts output; skips assets/js/src/)
 *   - assets/js/metabox.js          (classic editor fallback; not webpack-built)
 *   - languages/                    (empty placeholder is fine)
 *
 * What stays out (dev artifacts and listing assets that ship via SVN):
 *   - assets/js/src/
 *   - node_modules/
 *   - tests/
 *   - scripts/
 *   - wporg-assets/
 *   - package.json, package-lock.json, composer.json, composer.lock
 *   - webpack.config.js, phpunit.xml, .gitignore, .git/
 *   - README.md, EXTENDING.md
 *   - dist/ itself
 */

import { execSync } from 'node:child_process';
import { cp, mkdir, rm } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname( fileURLToPath( import.meta.url ) );
const root = resolve( here, '..' );
const slug = 'rootstuff-relationships';
const dist = resolve( root, 'dist' );
const stage = resolve( dist, slug );
const zipPath = resolve( dist, `${ slug }.zip` );

const runtimeEntries = [
	'rootstuff-relationships.php',
	'readme.txt',
	'uninstall.php',
	'LICENSE',
	'composer.json',
	'src',
	'vendor',
	'blocks',
	'languages',
];

function run( cmd, options = {} ) {
	execSync( cmd, { stdio: 'inherit', cwd: root, ...options } );
}

async function stageAssets() {
	const assetsStage = resolve( stage, 'assets' );
	await mkdir( assetsStage, { recursive: true } );
	await cp(
		resolve( root, 'assets/css' ),
		resolve( assetsStage, 'css' ),
		{ recursive: true }
	);
	await cp(
		resolve( root, 'assets/js/build' ),
		resolve( assetsStage, 'js/build' ),
		{ recursive: true }
	);
	await cp(
		resolve( root, 'assets/js/metabox.js' ),
		resolve( assetsStage, 'js/metabox.js' )
	);
}

async function main() {
	console.log( '>> npm run build' );
	run( 'npm run build' );

	console.log( '>> composer install --no-dev --optimize-autoloader' );
	run( 'composer install --no-dev --optimize-autoloader' );

	console.log( '>> staging runtime files' );
	await rm( dist, { recursive: true, force: true } );
	await mkdir( stage, { recursive: true } );
	for ( const entry of runtimeEntries ) {
		await cp( resolve( root, entry ), resolve( stage, entry ), {
			recursive: true,
		} );
	}
	await stageAssets();

	console.log( '>> zipping' );
	run( `zip -qr "${ zipPath }" "${ slug }"`, { cwd: dist } );

	console.log( `\nwrote ${ zipPath }` );
}

main().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
