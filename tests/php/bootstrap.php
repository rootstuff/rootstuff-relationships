<?php
/**
 * PHPUnit bootstrap for RelateWP tests.
 *
 * Loads the WordPress test suite and the plugin. Expects the
 * WP_TESTS_DIR environment variable to point to the WordPress
 * test library (wordpress-develop/tests/phpunit).
 *
 * @package RelateWP\Tests
 */

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$tests_dir}/includes/functions.php. Set WP_TESTS_DIR.\n"; // phpcs:ignore
	exit( 1 );
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	require dirname( __DIR__, 2 ) . '/relatewp.php';
} );

require $tests_dir . '/includes/bootstrap.php';
