<?php
/**
 * Plugin Name: Expire Passwords
 * Description: Require certain users to change their passwords on a regular basis.
 * Version: 0.3.0
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
	 * @var Expire_Passwords_Plugin
	 */
	public static $_instance;

	/**
	 * Generic prefix/key identifier
	 *
	 * @var string
	 */
	public $prefix = 'expass';

	/**
	 * Current user object
	 *
	 * @var WP_User
	 */
	private $user;

	/**
	 * Default limit for password age (in days)
	 *
	 * @var int
	 */
	public $default_limit;

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'EXPIRE_PASSWORDS_PLUGIN', plugin_basename( __FILE__ ) );
		define( 'EXPIRE_PASSWORDS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'EXPIRE_PASSWORDS_URL', plugin_dir_url( __FILE__ ) );
		define( 'EXPIRE_PASSWORDS_INC_DIR', EXPIRE_PASSWORDS_DIR . 'includes/' );
		define( 'EXPIRE_PASSWORDS_LANG_PATH', dirname( EXPIRE_PASSWORDS_PLUGIN ) . '/languages' );

		add_action( 'plugins_loaded', array( $this, 'i18n' ) );

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
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'expire-passwords', false, EXPIRE_PASSWORDS_LANG_PATH );
	}

	/**
	 * Set property values and fire hooks
	 *
	 * @action init
	 *
	 * @return void
	 */
	public function init() {
		/**
		 * Filter the default age limit for passwords (in days)
		 * when the limit settings field is not set or empty.
		 *
		 * @return int
		 */
		$this->default_limit = absint( apply_filters( 'expass_default_limit', 90 ) );

		add_action( 'user_register', array( $this, 'save_user_meta' ) );

		new Expire_Passwords\Login_Screen( $this );

		if ( ! is_user_logged_in() ) {
			return;
		}

		new Expire_Passwords\List_Table( $this );
		new Expire_Passwords\Settings( $this );

		$this->user = wp_get_current_user();

		add_action( 'password_reset', array( $this, 'save_user_meta' ) );
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
	public function save_user_meta( $user = null ) {
		$user_id = is_int( $user ) ? $user : ( isset( $user->ID ) ? $user->ID : ( isset( $this->user->ID ) ? $this->user->ID : null ) );
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
	public function get_user_meta( $user_id ) {
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
	public function get_limit() {
		$options = get_option( $this->prefix . '_settings' );
		$limit   = ( empty( $options['limit'] ) || absint( $options['limit'] ) > 365 ) ? $this->default_limit : $options['limit'];

		return absint( $limit );
	}

	/**
	 * Return the array of expirable roles setting
	 *
	 * @return array
	 */
	public function get_roles() {
		$options = get_option( $this->prefix . '_settings' );

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
	 * @param int    $user_id (optional)
	 * @param string $date_format (optional)
	 *
	 * @return string|bool|WP_Error
	 */
	public function get_expiration( $user_id = null, $date_format = 'U' ) {
		$user_id = is_int( $user_id ) ? $user_id : ( isset( $this->user->ID ) ? $this->user->ID : null );
		$user_id = absint( $user_id );

		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		$reset = $this->get_user_meta( $user_id );

		if ( ! $this->has_expirable_role( $user_id ) || ! $reset ) {
			return false;
		}

		$expires = strtotime( sprintf( '@%d + %d days', $reset, $this->get_limit() ) );

		return gmdate( $date_format, $expires );
	}

	/**
	 * Determine if a user belongs to an expirable role defined in the settings
	 *
	 * @param int $user_id (optional)
	 *
	 * @return bool|WP_Error
	 */
	public function has_expirable_role( $user_id = null ) {
		$user = is_int( $user_id ) ? get_userdata( $user_id ) : $this->user;

		if ( empty( $user ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		if ( empty( $user->roles[0] ) ) {
			return new WP_Error( 'user_has_no_role', esc_html__( 'User has no role assigned.', 'expire-passwords' ) );
		}

		$roles = array_intersect( $user->roles, $this->get_roles() );

		return ! empty( $roles );
	}

	/**
	 * Determine if a user's password has exceeded the age limit
	 *
	 * @param int $user_id (optional)
	 *
	 * @return bool|WP_Error
	 */
	public function is_password_expired( $user_id = null ) {
		$user_id = is_int( $user_id ) ? $user_id : ( isset( $this->user->ID ) ? $this->user->ID : null );
		$user_id = absint( $user_id );

		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_does_not_exist', esc_html__( 'User does not exist.', 'expire-passwords' ) );
		}

		if ( ! $this->has_expirable_role( $user_id ) ) {
			return false;
		}

		$expires = $this->get_expiration( $user_id );

		if ( ! $expires ) {
			return false;
		}

		return ( time() > $expires );
	}

}

if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	function expass_php_version_fail_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'The Expire Passwords plugin requires PHP version 5.3 or higher. Please contact your server administrator.', 'expire-passwords' ) ?></p>
		</div>
		<?php
	}
	add_action( 'all_admin_notices', 'expass_php_version_fail_notice' );
} else {
	$GLOBALS['expire_passwords'] = Expire_Passwords_Plugin::instance();
}
