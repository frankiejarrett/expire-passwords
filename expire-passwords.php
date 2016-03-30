<?php
/**
 * Plugin Name: Expire Passwords
 * Description: Require certain users to change their passwords on a regular basis.
 * Version: 0.3.0
 * Author: Frankie Jarrett
 * Author URI: http://frankiejarrett.com
 * Text Domain: expire-passwords
 * Domain Path: /languages
 *
 * Copyright: © 2015 Frankie Jarrett.
 * License: GNU General Public License v2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EXPIRE_PASSWORDS_PLUGIN', plugin_basename( __FILE__ ) );
define( 'EXPIRE_PASSWORDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXPIRE_PASSWORDS_URL', plugin_dir_url( __FILE__ ) );
define( 'EXPIRE_PASSWORDS_INC_DIR', EXPIRE_PASSWORDS_DIR . 'includes/' );
define( 'EXPIRE_PASSWORDS_LANG_PATH', dirname( EXPIRE_PASSWORDS_PLUGIN ) . '/languages' );

final class Expire_Passwords_Plugin {

	/**
	 * Plugin version number
	 *
	 * @var string
	 */
	const VERSION = '0.3.0';

	/**
	 * User meta key identifier
	 *
	 * @var string
	 */
	const META_KEY = 'expass_password_reset';

	/**
	 * Plugin instance
	 *
	 * @var object
	 */
	private static $_instance;

	/**
	 * Generic prefix/key identifier
	 *
	 * @var string
	 */
	public static $prefix = 'expass';

	/**
	 * Default limit for password age (in days)
	 *
	 * @var int
	 */
	public static $default_limit;

	/**
	 * Class constructor
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );

		foreach ( glob( EXPIRE_PASSWORDS_INC_DIR . '*.php' ) as $include ) {
			if ( is_readable( $include ) ) {
				require_once $include;
			}
		}

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Get plugin instance
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Load languages
	 *
	 * @action plugins_loaded
	 *
	 * @return null
	 */
	public static function i18n() {
		load_plugin_textdomain( 'expire-passwords', false, EXPIRE_PASSWORDS_LANG_PATH );
	}

	/**
	 * Set property values and fire hooks
	 *
	 * @action init
	 *
	 * @return null
	 */
	public function init() {
		/**
		 * Filter the default age limit for passwords (in days)
		 * when the limit settings field is not set or empty.
		 *
		 * @return int
		 */
		self::$default_limit = absint( apply_filters( 'expass_default_limit', 90 ) );

		add_action( 'user_register', array( __CLASS__, 'save_user_meta' ) );

		new Expire_Passwords\Login_Screen;

		if ( ! is_user_logged_in() ) {
			return;
		}

		new Expire_Passwords\List_Table;
		new Expire_Passwords\Settings;

		add_action( 'password_reset', array( __CLASS__, 'save_user_meta' ) );
	}

	/**
	 * Save password reset user meta to the database
	 *
	 * @action user_register
	 * @action password_reset
	 *
	 * @param WP_User|int $user (optional)
	 *
	 * @return null
	 */
	public static function save_user_meta( $user = null ) {
		$user_id = is_int( $user ) ? $user : ( is_a( $user, 'WP_User' ) ? $user->ID : get_current_user_id() );

		if ( ! get_userdata( $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, self::META_KEY, gmdate( 'U' ) );
	}

	/**
	 * Return password reset user meta from the database
	 *
	 * @param WP_User|int $user
	 *
	 * @return int|bool|WP_Error  Unix timestamp on success, false on failure
	 */
	public static function get_user_meta( $user ) {
		$user_id = is_int( $user ) ? $user : ( is_a( $user, 'WP_User' ) ? $user->ID : get_current_user_id() );

		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		$value = get_user_meta( $user_id, self::META_KEY, true );

		return empty( $value ) ? false : absint( $value );
	}

	/**
	 * Return the password age limit setting
	 *
	 * A hard limit of 365 days is built into this plugin. If
	 * you want to require passwords to be reset less than once
	 * per year then you probably don't need this plugin. :-)
	 *
	 * @return int
	 */
	public static function get_limit() {
		$options = get_option( self::$prefix . '_settings' );
		$limit   = ( empty( $options['limit'] ) || absint( $options['limit'] ) > 365 ) ? self::$default_limit : $options['limit'];

		return absint( $limit );
	}

	/**
	 * Return the array of expirable roles setting
	 *
	 * @return array
	 */
	public static function get_roles() {
		$options = get_option( self::$prefix . '_settings' );

		if ( empty( $options ) ) {
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			$roles = array_keys( get_editable_roles() );

			// Return all roles except admins by default if not set
			if ( isset( $roles['administrator'] ) ) {
				unset( $roles['administrator'] );
			}
		} else {
			$roles = empty( $options['roles'] ) ? array() : array_keys( $options['roles'] );
		}

		return (array) $roles;
	}

	/**
	 * Return the password expiration date for a user
	 *
	 * @param WP_User|int $user        (optional)
	 * @param string      $date_format (optional)
	 *
	 * @return string|bool|WP_Error
	 */
	public static function get_expiration( $user = null, $date_format = 'U' ) {
		$user_id = is_int( $user ) ? $user : ( is_a( $user, 'WP_User' ) ? $user->ID : get_current_user_id() );

		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		$reset = self::get_user_meta( $user_id );

		if ( ! self::has_expirable_role( $user_id ) || ! $reset ) {
			return false;
		}

		$expires = strtotime( sprintf( '@%d + %d days', $reset, self::get_limit() ) );

		return gmdate( $date_format, $expires );
	}

	/**
	 * Determine if a user belongs to an expirable role defined in the settings
	 *
	 * @param WP_User|int $user (optional)
	 *
	 * @return bool|WP_Error
	 */
	public static function has_expirable_role( $user = null ) {
		$user = is_int( $user ) ? get_userdata( $user ) : ( is_a( $user, 'WP_User' ) ? $user : wp_get_current_user() );

		if ( empty( $user ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		if ( empty( $user->roles[0] ) ) {
			return new WP_Error( 'user_has_no_role', esc_html__( 'User has no role assigned.', 'expire-passwords' ) );
		}

		$roles = array_intersect( $user->roles, self::get_roles() );

		return ! empty( $roles );
	}

	/**
	 * Determine if a user's password has exceeded the age limit
	 *
	 * @param WP_User|int $user (optional)
	 *
	 * @return bool|WP_Error
	 */
	public static function is_password_expired( $user = null ) {
		$user_id = is_int( $user ) ? $user : ( is_a( $user, 'WP_User' ) ? $user->ID : get_current_user_id() );

		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		if ( ! self::has_expirable_role( $user_id ) ) {
			return false;
		}

		$expires = self::get_expiration( $user_id );

		if ( ! $expires ) {
			return false;
		}

		return ( time() > $expires );
	}

}

/**
 * Display PHP version notice
 *
 * @return null
 */
function expass_php_version_fail_notice() {
	?>
	<div class="error">
		<p><?php esc_html_e( 'The Expire Passwords plugin requires PHP version 5.3 or higher. Please contact your server administrator.', 'expire-passwords' ) ?></p>
	</div>
	<?php
}

if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	add_action( 'plugins_loaded', array( 'Expire_Passwords_Plugin', 'i18n' ) );
	add_action( 'all_admin_notices', 'expass_php_version_fail_notice' );
} else {
	Expire_Passwords_Plugin::instance();
}
