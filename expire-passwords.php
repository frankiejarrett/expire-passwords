<?php
/**
 * Plugin Name: Expire Passwords
 * Description: Require certain users to change their passwords on a regular basis.
 * Version: 0.2.2
 * Author: Frankie Jarrett
 * Author URI: http://frankiejarrett.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: expire-passwords
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Expire_Passwords {

	/**
	 * Plugin version number
	 *
	 * @const string
	 */
	const VERSION = '0.2.2';

	/**
	 * Generic prefix/key identifier
	 *
	 * @const string
	 */
	const PREFIX = 'expass';

	/**
	 * User meta key identifier
	 *
	 * @const string
	 */
	const META_KEY = 'expass_password_reset';

	/**
	 * Hold current user object
	 *
	 * @var WP_User
	 */
	public static $user;

	/**
	 * Hold default limit for password age (in days)
	 *
	 * @var int
	 */
	public static $default_limit;

	/**
	 * Hold plugin instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'EXPIRE_PASSWORDS_PLUGIN', plugin_basename( __FILE__ ) );
		define( 'EXPIRE_PASSWORDS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'EXPIRE_PASSWORDS_URL', plugin_dir_url( __FILE__ ) );
		define( 'EXPIRE_PASSWORDS_INC_DIR', EXPIRE_PASSWORDS_DIR . 'includes/' );
		define( 'EXPIRE_PASSWORDS_LANG_PATH', dirname( EXPIRE_PASSWORDS_PLUGIN ) . '/languages' );

		require_once EXPIRE_PASSWORDS_INC_DIR . 'class-expire-passwords-list-table.php';
		require_once EXPIRE_PASSWORDS_INC_DIR . 'class-expire-passwords-login-screen.php';
		require_once EXPIRE_PASSWORDS_INC_DIR . 'class-expire-passwords-settings.php';

		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );
		add_action( 'init', array( __CLASS__, 'load' ) );
		add_action( 'init', array( 'Expire_Passwords_List_Table', 'load' ) );
		add_action( 'init', array( 'Expire_Passwords_Login_Screen', 'load' ) );
		add_action( 'init', array( 'Expire_Passwords_Settings', 'load' ) );
	}

	/**
	 * Load languages
	 *
	 * @action plugins_loaded
	 *
	 * @return void
	 */
	public static function i18n() {
		load_plugin_textdomain( 'expire-passwords', false, EXPIRE_PASSWORDS_LANG_PATH );
	}

	/**
	 * Set property values and fire hooks
	 *
	 * @action init
	 *
	 * @return void
	 */
	public static function load() {
		/**
		 * Filter the default age limit for passwords (in days)
		 * when the limit settings field is not set or empty.
		 *
		 * @return int
		 */
		self::$default_limit = apply_filters( 'expass_default_limit', 90 );

		add_action( 'user_register', array( __CLASS__, 'save_user_meta' ) );

		if ( ! is_user_logged_in() ) {
			return;
		}

		self::$user = wp_get_current_user();

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
	 * @return void
	 */
	public static function save_user_meta( $user = null ) {
		$user_id = is_int( $user ) ? $user : ( isset( $user->ID ) ? $user->ID : ( isset( self::$user->ID ) ? self::$user->ID : null ) );
		$user_id = absint( $user_id );

		if ( ! get_userdata( $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, self::META_KEY, gmdate( 'U' ) );
	}

	/**
	 * Return password reset user meta from the database
	 *
	 * @param int $user_id
	 *
	 * @return int|bool  Unix timestamp on success, false on failure
	 */
	public static function get_user_meta( $user_id ) {
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
		$options = get_option( self::PREFIX . '_settings' );
		$limit   = ( empty( $options['limit'] ) || absint( $options['limit'] ) > 365 ) ? self::$default_limit : $options['limit'];

		return absint( $limit );
	}

	/**
	 * Return the array of expirable roles setting
	 *
	 * @return array
	 */
	public static function get_roles() {
		$options = get_option( self::PREFIX . '_settings' );

		if ( empty( $options ) ) {
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			$roles = get_editable_roles();

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
	 * @param int    $user_id (optional)
	 * @param string $date_format (optional)
	 *
	 * @return string|bool|WP_Error
	 */
	public static function get_expiration( $user_id = null, $date_format = 'U' ) {
		$user_id = is_int( $user_id ) ? $user_id : ( isset( self::$user->ID ) ? self::$user->ID : null );
		$user_id = absint( $user_id );

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
	 * @param int $user_id (optional)
	 *
	 * @return bool|WP_Error
	 */
	public static function has_expirable_role( $user_id = null ) {
		$user = is_int( $user_id ) ? get_userdata( $user_id ) : self::$user;

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
	 * @param int $user_id (optional)
	 *
	 * @return bool|WP_Error
	 */
	public static function is_password_expired( $user_id = null ) {
		$user_id = is_int( $user_id ) ? $user_id : ( isset( self::$user->ID ) ? self::$user->ID : null );
		$user_id = absint( $user_id );

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

	/**
	 * Return active instance of Expire_Passwords, create one if it doesn't exist
	 *
	 * @return Expire_Passwords
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

}

$GLOBALS['expire_passwords'] = Expire_Passwords::get_instance();
