#!/usr/bin/env node
/**
 * Render the SVG sources in `wporg-assets/` into the PNG sizes that
 * WordPress.org expects for the plugin listing.
 *
 *   icon.svg   -> icon-256x256.png, icon-128x128.png
 *   banner.svg -> banner-1544x500.png, banner-772x250.png
 */

import { readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const here = dirname( fileURLToPath( import.meta.url ) );
const root = resolve( here, '..' );
const assetsDir = resolve( root, 'wporg-assets' );

async function render( svgFile, sizes ) {
	const svgPath = resolve( assetsDir, svgFile );
	const svg = await readFile( svgPath );
	for ( const { width, height, out } of sizes ) {
		const buf = await sharp( svg, { density: 384 } )
			.resize( width, height, { fit: 'fill' } )
			.png()
			.toBuffer();
		const outPath = resolve( assetsDir, out );
		await writeFile( outPath, buf );
		console.log( `wrote ${ outPath }` );
	}
}

async function main() {
	await render( 'icon.svg', [
		{ width: 256, height: 256, out: 'icon-256x256.png' },
		{ width: 128, height: 128, out: 'icon-128x128.png' },
	] );
	await render( 'banner.svg', [
		{ width: 1544, height: 500, out: 'banner-1544x500.png' },
		{ width: 772, height: 250, out: 'banner-772x250.png' },
	] );
}

main().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
