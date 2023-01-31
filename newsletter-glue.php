<?php
/**
 * Plugin Name: Newsletter Glue Pro
 * Plugin URI: https://newsletterglue.com/
 * Description: Email posts to subscribers from the WordPress editor. Works with Mailchimp, MailerLite, Sendinblue…
 * Author: Newsletter Glue
 * Author URI: https://newsletterglue.com
 * Requires at least: 5.9
 * Requires PHP: 7.0
 * Version: 2.2.0.5
 * Text Domain: newsletter-glue
 * Domain Path: /i18n/languages/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'newsletterglue' ) ) :
	deactivate_plugins( plugin_basename( NGL_PLUGIN_FILE ) );
endif;

if ( ! class_exists( 'Newsletter_Glue' ) ) :

/**
 * Main Class.
 */
final class Newsletter_Glue {
	/** Singleton *************************************************************/

	/**
	 * @var Instance.
	 */
	private static $instance;

	public static $the_lists = null;

	/**
	 * Main Instance.
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Newsletter_Glue ) ) {
			self::$instance = new Newsletter_Glue;
			self::$instance->setup_constants();

			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

			self::$instance->includes();
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'newsletter-glue' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class.
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'newsletter-glue' ), '1.0.0' );
	}

	/**
	 * Setup plugin constants.
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'NGL_VERSION' ) ) {
			define( 'NGL_VERSION', '2.2.0.5' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'NGL_PLUGIN_DIR' ) ) {
			define( 'NGL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'NGL_PLUGIN_URL' ) ) {
			define( 'NGL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'NGL_PLUGIN_FILE' ) ) {
			define( 'NGL_PLUGIN_FILE', __FILE__ );
		}

		// Feedback server.
		if ( ! defined( 'NGL_FEEDBACK_SERVER' ) ) {
			define( 'NGL_FEEDBACK_SERVER', 'https://newsletterglue.com' );
		}
	}

	/**
	 * Include required files.
	 */
	private function includes() {

		if ( ! function_exists( 'file_get_html' ) && ! class_exists( 'simple_html_dom' ) && ! class_exists( 'simple_html_dom_node' ) ) {
			include_once NGL_PLUGIN_DIR . 'includes/libraries/simple_html_dom.php';
		}

		if ( ! class_exists( 'NGL_Abstract_Integration', false ) ) {
			require_once NGL_PLUGIN_DIR . 'includes/abstract-integration.php';
		}

		require_once NGL_PLUGIN_DIR . 'includes/setup-api.php';
		require_once NGL_PLUGIN_DIR . 'includes/setup-options.php';
		require_once NGL_PLUGIN_DIR . 'includes/rest-api.php';

		require_once NGL_PLUGIN_DIR . 'includes/ajax-functions.php';
		require_once NGL_PLUGIN_DIR . 'includes/functions.php';
		require_once NGL_PLUGIN_DIR . 'includes/install.php';
		require_once NGL_PLUGIN_DIR . 'includes/core.php';
		require_once NGL_PLUGIN_DIR . 'includes/compatibility.php';
		require_once NGL_PLUGIN_DIR . 'includes/gutenberg.php';
		require_once NGL_PLUGIN_DIR . 'includes/cpt/cpt.php';
		require_once NGL_PLUGIN_DIR . 'includes/rewrite.php';
		require_once NGL_PLUGIN_DIR . 'includes/pro.php';
		require_once NGL_PLUGIN_DIR . 'includes/automation.php';
		require_once NGL_PLUGIN_DIR . 'includes/log.php';
		require_once NGL_PLUGIN_DIR . 'includes/cron.php';

		foreach ( glob( NGL_PLUGIN_DIR . "includes/renders/*.php" ) as $filename ) {
			include_once $filename;
		}

		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-fields.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-functions.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-automations.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-logs.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-menu.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-notices.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-scripts.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-templates.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/admin-patterns.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/meta-boxes.php';
			require_once NGL_PLUGIN_DIR . 'includes/admin/posts.php';
		}

		// Load blocks.
		$blocks = newsletterglue_get_blocks();
		foreach( $blocks as $block_id => $params ) {
			if ( isset( $params[ 'path' ] ) && file_exists( $params[ 'path' ] ) ) {
				include_once $params[ 'path' ];
			} else if ( file_exists( NGL_PLUGIN_DIR . 'includes/blocks/' . $block_id . '/block.php' ) ) {
				include_once NGL_PLUGIN_DIR . 'includes/blocks/' . $block_id . '/block.php';
			} else {
				
			}
		}

		// Demo templates.
		for ( $i = 1; $i <= 13 ; $i++ ) {
			if ( file_exists( NGL_PLUGIN_DIR . 'includes/default-templates/template-' . $i . '.php' ) ) {
				include_once NGL_PLUGIN_DIR . 'includes/default-templates/template-' . $i . '.php';
			}
		}
	}

	/**
	 * Loads the plugin language files.
	 */
	public function load_textdomain() {
		global $wp_version;

		// Set filter for plugin's languages directory.
		$newsletterglue_lang_dir  = dirname( plugin_basename( NGL_PLUGIN_FILE ) ) . '/i18n/languages/';
		$newsletterglue_lang_dir  = apply_filters( 'newsletterglue_languages_directory', $newsletterglue_lang_dir );

		// Traditional WordPress plugin locale filter.

		$get_locale = get_locale();

		if ( $wp_version >= 4.7 ) {

			$get_locale = get_user_locale();
		}

		unload_textdomain( 'newsletter-glue' );

		/**
		 * Defines the plugin language locale used.
		 *
		 * @var $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
		 *                  otherwise uses `get_locale()`.
		 */
		$locale        = apply_filters( 'plugin_locale',  $get_locale, 'newsletter-glue' );
		$mofile        = sprintf( '%1$s-%2$s.mo', 'newsletter-glue', $locale );

		// Look for wp-content/languages/newsletter-glue/newsletter-glue-{lang}_{country}.mo
		$mofile_global1 = WP_LANG_DIR . '/newsletter-glue/newsletter-glue-' . $locale . '.mo';

		// Look in wp-content/languages/plugins/newsletter-glue
		$mofile_global2 = WP_LANG_DIR . '/plugins/newsletter-glue/' . $mofile;

		if ( file_exists( $mofile_global1 ) ) {

			load_textdomain( 'newsletter-glue', $mofile_global1 );

		} elseif ( file_exists( $mofile_global2 ) ) {

			load_textdomain( 'newsletter-glue', $mofile_global2 );

		} else {

			// Load the default language files.
			load_plugin_textdomain( 'newsletter-glue', false, $newsletterglue_lang_dir );
		}

	}

	/**
	 * Assets URL.
	 */
	public function assets_url() {
		return untrailingslashit( plugins_url( '/', NGL_PLUGIN_FILE ) ) . '/assets/images';
	}

	/**
	 * Current API version.
	 */
	public function api_version() {
		return 'v1';
	}

}

endif; // End if class_exists check.

/**
 * The main function.
 */
if ( ! function_exists( 'newsletterglue' ) ) {
	function newsletterglue() {
		return Newsletter_Glue::instance();
	}
}

// Get Running.
newsletterglue();