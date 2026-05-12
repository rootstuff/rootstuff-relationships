<?php
/**
 * Plugin Name:       RelateWP
 * Plugin URI:        https://rootstuff.io/relatewp
 * Description:       A developer-first relationship layer for WordPress content modeling. Laravel-style belongsTo, hasMany, and belongsToMany on dedicated database tables with Gutenberg integration.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Author:            Rootstuff
 * Author URI:        https://rootstuff.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       relatewp
 *
 * @package RelateWP
 */

defined( 'ABSPATH' ) || exit;

define( 'RELATEWP_VERSION', '0.1.0' );
define( 'RELATEWP_FILE', __FILE__ );
define( 'RELATEWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'RELATEWP_URL', plugin_dir_url( __FILE__ ) );
define( 'RELATEWP_BASENAME', plugin_basename( __FILE__ ) );

require_once RELATEWP_PATH . 'vendor/autoload.php';

use RelateWP\Plugin;

register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ Plugin::class, 'init' ] );
