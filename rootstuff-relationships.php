<?php
/**
 * Plugin Name:       Rootstuff Relationships
 * Plugin URI:        https://github.com/rootstuff/rootstuff-relationships
 * Description:       A developer-first relationship layer for WordPress content modeling. Laravel-style belongsTo, hasMany, and belongsToMany on dedicated database tables with Gutenberg integration.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Author:            Rootstuff
 * Author URI:        https://rootstuff.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rootstuff-relationships
 *
 * @package Rootstuff\Relationships
 */

defined( 'ABSPATH' ) || exit;

// Pro bundles the full core — skip everything when Pro is active.
if ( defined( 'ROOTSTUFF_REL_PRO_VERSION' ) ) {
	return;
}

define( 'ROOTSTUFF_REL_VERSION', '0.1.0' );
define( 'ROOTSTUFF_REL_FILE', __FILE__ );
define( 'ROOTSTUFF_REL_PATH', plugin_dir_path( __FILE__ ) );
define( 'ROOTSTUFF_REL_URL', plugin_dir_url( __FILE__ ) );
define( 'ROOTSTUFF_REL_BASENAME', plugin_basename( __FILE__ ) );

require_once ROOTSTUFF_REL_PATH . 'vendor/autoload.php';

use Rootstuff\Relationships\Plugin;

register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ Plugin::class, 'init' ] );
