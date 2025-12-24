<?php
/**
 * Plugin Name: Fundraiser Pro
 * Plugin URI: https://fundraiser.steinmetz.ltd
 * Description: Complete WordPress fundraising platform with WooCommerce integration, campaigns, raffles, cash payment management, AI assistant, and comprehensive analytics.
 * Version: 1.0.1
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fundraiser-pro
 * GitHub Plugin URI: LordLuktor/fundraiser
 * Primary Branch: main
 * Domain Path: /languages
 *
 * @package FundraiserPro
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version.
 */
define( 'FUNDRAISER_PRO_VERSION', '1.0.1' );

/**
 * Plugin directory path.
 */
define( 'FUNDRAISER_PRO_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'FUNDRAISER_PRO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'FUNDRAISER_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Database table prefix.
 */
define( 'FUNDRAISER_PRO_DB_PREFIX', 'fundraiser_' );

/**
 * PSR-4 Autoloader for plugin classes.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'FundraiserPro\\';
	$base_dir = FUNDRAISER_PRO_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * The code that runs during plugin activation.
 */
function activate_fundraiser_pro() {
	require_once FUNDRAISER_PRO_PATH . 'includes/Activator.php';
	FundraiserPro\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_fundraiser_pro() {
	require_once FUNDRAISER_PRO_PATH . 'includes/Deactivator.php';
	FundraiserPro\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_fundraiser_pro' );
register_deactivation_hook( __FILE__, 'deactivate_fundraiser_pro' );

/**
 * Check plugin dependencies before initialization.
 */
function fundraiser_pro_check_dependencies() {
	$dependencies = array();

	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		$dependencies[] = 'WooCommerce';
	}

	// Check PHP version
	if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
		$dependencies[] = sprintf(
			/* translators: %s: Required PHP version */
			__( 'PHP %s or higher', 'fundraiser-pro' ),
			'8.1'
		);
	}

	// Check WordPress version
	global $wp_version;
	if ( version_compare( $wp_version, '6.4', '<' ) ) {
		$dependencies[] = sprintf(
			/* translators: %s: Required WordPress version */
			__( 'WordPress %s or higher', 'fundraiser-pro' ),
			'6.4'
		);
	}

	if ( ! empty( $dependencies ) ) {
		add_action( 'admin_notices', function() use ( $dependencies ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Fundraiser Pro', 'fundraiser-pro' ); ?>:</strong>
					<?php
					printf(
						/* translators: %s: List of missing dependencies */
						esc_html__( 'This plugin requires the following to be installed and activated: %s', 'fundraiser-pro' ),
						'<strong>' . esc_html( implode( ', ', $dependencies ) ) . '</strong>'
					);
					?>
				</p>
			</div>
			<?php
		} );
		return false;
	}

	return true;
}

/**
 * Begin execution of the plugin.
 */
function run_fundraiser_pro() {
	if ( ! fundraiser_pro_check_dependencies() ) {
		return;
	}

	require_once FUNDRAISER_PRO_PATH . 'includes/Core.php';
	$plugin = new FundraiserPro\Core();
	$plugin->run();
}

add_action( 'plugins_loaded', 'run_fundraiser_pro' );

/**
 * Add settings link on plugin page.
 */
add_filter( 'plugin_action_links_' . FUNDRAISER_PRO_BASENAME, function( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=fundraiser-pro-settings' ) ),
		esc_html__( 'Settings', 'fundraiser-pro' )
	);
	array_unshift( $links, $settings_link );
	return $links;
} );

// Load demo login handler
require_once FUNDRAISER_PRO_PATH . "includes/demo-login-handler.php";

// Load demo signup button
require_once FUNDRAISER_PRO_PATH . "includes/demo-signup-button.php";

// Load campaign button handler
require_once FUNDRAISER_PRO_PATH . "includes/campaign-button-handler.php";

// Fix campaign button redirect
require_once FUNDRAISER_PRO_PATH . "includes/fix-campaign-button-js.php";
