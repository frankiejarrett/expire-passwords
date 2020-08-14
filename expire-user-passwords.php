<?php
/**
 * Plugin Name: Expire User Passwords
 * Description: Require certain users to change their passwords on a regular basis.
 * Version: 1.1.1
 * Author: Miller Media
 * Author URI: https://www.millermedia.io
 * License: GPL-2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: expire-user-passwords
 * Domain Path: /languages
 *
 * This plugin, like WordPress, is licensed under the GPL.
 * Use it to make something cool, have fun, and share what you've learned with others.
 *
 * Copyright © 2019 Miller Media. All Rights Reserved.
 */
namespace MillerMedia\ExpireUserPasswords;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

define( 'EXPIRE_USER_PASSWORDS_VERSION', '1.0.0' );
define( 'EXPIRE_USER_PASSWORDS_PLUGIN', plugin_basename( __FILE__ ) );
define( 'EXPIRE_USER_PASSWORDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXPIRE_USER_PASSWORDS_URL', plugin_dir_url( __FILE__ ) );
define( 'EXPIRE_USER_PASSWORDS_INC_DIR', EXPIRE_USER_PASSWORDS_DIR . 'includes/' );
define( 'EXPIRE_USER_PASSWORDS_LANG_PATH', dirname( EXPIRE_USER_PASSWORDS_PLUGIN ) . '/languages' );

final class Expire_User_Passwords {

	/**
	 * Plugin instance.
	 *
	 * @var Expire_User_Passwords
	 */
	private static $instance;

	/**
	 * Default limit for password age (in days).
	 *
	 * @var int
	 */
	public static $default_limit;

	/**
	 * Class constructor.
	 */
	private function __construct() {

		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );

		foreach ( glob( EXPIRE_USER_PASSWORDS_INC_DIR . '*.php' ) as $include ) {

			if ( is_readable( $include ) ) {

				require_once $include;

			}

		}

		add_action( 'init', array( $this, 'init' ) );

	}

	/**
	 * Return the plugin instance.
	 *
	 * @return Expire_User_Passwords
	 */
	public static function instance() {

		if ( ! self::$instance ) {

			self::$instance = new self();

		}

		return self::$instance;

	}

	/**
	 * Load languages.
	 *
	 * @action plugins_loaded
	 */
	public static function i18n() {

		load_plugin_textdomain( 'expire-user-passwords', false, EXPIRE_USER_PASSWORDS_LANG_PATH );

	}

	/**
	 * Set property values and fire hooks.
	 *
	 * @action init
	 */
	public function init() {

		/**
		 * Filter the default age limit for passwords (in days)
		 * when the limit settings field is not set or empty.
		 *
		 * @return int
		 */
		self::$default_limit = absint( apply_filters( 'user_expass_default_limit', 90 ) );

		add_action( 'user_register',  array( __CLASS__, 'save_user_meta' ) );
		add_action( 'password_reset', array( __CLASS__, 'save_user_meta' ) );

		new Expire_User_Passwords_Login_Screen;

		if ( ! is_user_logged_in() ) {

			return;

		}

		new Expire_User_Passwords_List_Table;
		new Expire_User_Passwords_Settings;

	}

	/**
	 * Save password reset user meta to the database.
	 *
	 * @action user_register
	 * @action password_reset
	 *
	 * @param mixed $user (optional)
	 */
	public static function save_user_meta( $user = null ) {

		if ( false === ( $user_id = self::get_user_id( $user ) ) ) {

			return;

		}

		update_user_meta( $user_id, 'user_expass_password_reset', gmdate( 'U' ) );

	}

	/**
	 * Return password reset user meta from the database.
	 *
	 * @param  mixed $user (optional)
	 *
	 * @return mixed|false
	 */
	public static function get_user_meta( $user = null ) {

		if ( false === ( $user_id = self::get_user_id( $user ) ) ) {

			return false;

		}

		$value = get_user_meta( $user_id, 'user_expass_password_reset', true );

		return ( $value ) ? absint( $value ) : false;

	}

	/**
	 * Return the password age limit setting.
	 *
	 * A hard limit of 365 days is built into this plugin. If
	 * you want to require passwords to be reset less than once
	 * per year then you probably don't need this plugin. :-)
	 *
	 * @return int
	 */
	public static function get_limit() {

		$options = (array) get_option( 'user_expass_settings', array() );

		return ( empty( $options['limit'] ) || absint( $options['limit'] ) > 365 ) ? self::$default_limit : absint( $options['limit'] );

	}

	/**
	 * Return the array of expirable roles setting.
	 *
	 * @return array
	 */
	public static function get_roles() {

		$options = (array) get_option( 'user_expass_settings', array() );

		if ( ! empty( $options['roles'] ) ) {

			return array_keys( $options['roles'] );

		}

		if ( ! function_exists( 'get_editable_roles' ) ) {

			require_once ABSPATH . 'wp-admin/includes/user.php';

		}

		$roles = array_keys( get_editable_roles() );

		// Return all roles except admins by default if not set
		if ( isset( $roles['administrator'] ) ) {

			unset( $roles['administrator'] );

		}

		return $roles;

	}

	/**
	 * Return the password expiration date for a user.
	 *
	 * @param  mixed $user        (optional)
	 * @param  string      $date_format (optional)
	 *
	 * @return string|false
	 */
	public static function get_expiration( $user = null, $date_format = 'U' ) {

		if (
			! self::has_expirable_role( $user )
			||
			false === ( $reset = self::get_user_meta( $user ) )
		) {

			return false;

		}

		$expires = strtotime( sprintf( '@%d + %d days', $reset, self::get_limit() ) );

		return gmdate( $date_format, $expires );

	}

	/**
	 * Determine if a user belongs to an expirable role defined in the settings.
	 *
	 * @param  mixed $user (optional)
	 *
	 * @return bool
	 */
	public static function has_expirable_role( $user = null ) {

		if ( false === ( $user_id = self::get_user_id( $user ) ) ) {

			return false;

		}

		$user  = get_user_by( 'ID', $user_id );
		$roles = array_intersect( $user->roles, self::get_roles() );

		return empty( $user->roles[0] ) ? false : ! empty( $roles );

	}

	/**
	 * Determine if a user's password has exceeded the age limit.
	 *
	 * @param  mixed $user (optional)
	 *
	 * @return bool
	 */
	public static function is_expired( $user = null ) {

		return ( false === ( $expires = self::get_expiration( $user ) ) ) ? false : ( time() > $expires );

	}

	/**
	 * Return the user ID for a give user.
	 *
	 * @param  mixed $user
	 *
	 * @return int|false
	 */
	private static function get_user_id( $user = null ) {

		switch ( true ) {

			case is_numeric( $user ) :

				$user_id = absint( $user );

				break;

			case is_a( $user, 'WP_User' ) :

				$user_id = $user->ID;

				break;

			default :

				$user_id = get_current_user_id();

		}

		if ( ! get_user_by( 'ID', $user_id ) ) {

			return false;

		}

		return $user_id;

	}

}

Expire_User_Passwords::instance();
