<?php
/**
 * Plugin Name: Expire Passwords
 * Description: Require certain users to change their passwords after a specified length of time.
 * Version: 0.1.0
 * Author: Frankie Jarrett
 * Author URI: http://frankiejarrett.com
 * License: GPLv2+
 * Text Domain: expire-passwords
 */

class Expire_Passwords {

	/**
	 * Plugin version number
	 *
	 * @const string
	 */
	const VERSION = '0.1.0';

	/**
	 * User meta key identifier
	 *
	 * @const string
	 */
	const META_KEY = 'expass_password_reset';

	/**
	 * Generic prefix/key identifier
	 *
	 * @const string
	 */
	const PREFIX = 'expass';

	/**
	 * Hold current user object
	 *
	 * @var WP_User
	 */
	public static $user;

	/**
	 * Default limit for password age (in days)
	 *
	 * @var int
	 */
	public static $default_limit = 90;

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
		define( 'EXPIRE_PASSWORDS_LANG_PATH', dirname( EXPIRE_PASSWORDS_PLUGIN ) . '/languages' );

		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );

		add_action( 'init', array( __CLASS__, 'load' ) );
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
	 * Check the dependencies for this plugin
	 *
	 * @action plugins_loaded
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		self::$user = wp_get_current_user();

		/**
		 * Filter the default age limit for passwords (in days)
		 * when the limit settings field is not set or empty.
		 *
		 * @return int
		 */
		self::$default_limit = apply_filters( 'expass_default_limit', 90 );

		add_action( 'admin_menu', array( __CLASS__, 'add_submenu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );

		add_action( 'user_register', array( __CLASS__, 'save_user_meta' ) );
		add_action( 'password_reset', array( __CLASS__, 'save_user_meta' ) );

		add_action( 'wp_login', array( __CLASS__, 'enforce_password_reset' ), 10, 2 );
		add_filter( 'login_message', array( __CLASS__, 'custom_login_message' ) );

		add_action( 'admin_head', array( __CLASS__, 'custom_user_admin_css' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'custom_user_column' ) );
		add_action( 'manage_users_custom_column', array( __CLASS__, 'custom_user_column_content' ), 10, 3 );
	}

	/**
	 *
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public static function add_submenu_page() {
		add_submenu_page(
			'users.php',
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			'manage_options',
			'expire_passwords',
			array( __CLASS__, 'settings_page' )
		);
	}

	/**
	 *
	 *
	 * @see self::add_submenu_page()
	 *
	 * @return void
	 */
	public static function settings_page() {
		?>
		<div class="wrap">

			<h2><?php esc_html_e( 'Expire Passwords', 'expire-passwords' ) ?></h2>

			<form method="post" action="options.php">

				<?php settings_fields( self::PREFIX . '_settings_page' ) ?>

				<?php do_settings_sections( self::PREFIX . '_settings_page' ) ?>

				<?php submit_button() ?>

			</form>

		</div>
		<?php
	}

	/**
	 *
	 *
	 * @action admin_init
	 *
	 * @return void
	 */
	public static function settings_init() {
		register_setting(
			self::PREFIX . '_settings_page',
			self::PREFIX . '_settings'
		);

		add_settings_section(
			self::PREFIX . '_settings_page_section',
			null,
			array( __CLASS__, 'settings_section_render' ),
			self::PREFIX . '_settings_page'
		);

		add_settings_field(
			self::PREFIX . '_settings_field_limit',
			esc_html__( 'Require password reset every', 'expire-passwords' ),
			array( __CLASS__, 'settings_field_limit_render' ),
			self::PREFIX . '_settings_page',
			self::PREFIX . '_settings_page_section'
		);

		add_settings_field(
			self::PREFIX . '_settings_field_roles',
			esc_html__( 'For users in these roles', 'expire-passwords' ),
			array( __CLASS__, 'settings_field_roles_render' ),
			self::PREFIX . '_settings_page',
			self::PREFIX . '_settings_page_section'
		);
	}

	/**
	 *
	 *
	 * @see self::settings_init()
	 *
	 * @return void
	 */
	public static function settings_section_render() {
		?>
		<p>
			<?php esc_html_e( 'Require certain users to change their passwords after a specified length of time.', 'expire-passwords' ) ?>
		</p>
		<?php
	}

	/**
	 *
	 *
	 * @see self::settings_init()
	 *
	 * @return void
	 */
	public static function settings_field_limit_render() {
		$options = get_option( self::PREFIX . '_settings' );
		$value   = isset( $options['limit'] ) ? $options['limit'] : null;
		?>
		<input type="number" min="1" max="365" maxlength="3" name="<?php printf( '%s_settings[limit]', self::PREFIX ) ?>" placeholder="<?php echo esc_attr( self::$default_limit ) ?>" value="<?php echo esc_attr( $value ) ?>">
		<?php
		esc_html_e( 'days', 'expire-passwords' );
	}

	/**
	 *
	 *
	 * @see self::settings_init()
	 *
	 * @return void
	 */
	public static function settings_field_roles_render() {
		$options = get_option( self::PREFIX . '_settings' );
		$roles   = get_editable_roles();

		foreach ( $roles as $role => $role_data ) :
			$name  = sanitize_key( $role );
			$value = empty( $options['roles'][ $name ] ) ? 0 : 1;
			?>
			<p>
				<input type="checkbox" name="<?php printf( '%s_settings[roles][%s]', self::PREFIX, esc_attr( $name ) ) ?>" id="<?php printf( '%s_settings[roles][%s]', self::PREFIX, esc_attr( $name ) ) ?>" <?php checked( $value, 1 ) ?> value="1">
				<label for="<?php printf( '%s_settings[roles][%s]', self::PREFIX, esc_attr( $name ) ) ?>"><?php echo esc_html( $role_data['name'] ) ?></label>
			</p>
			<?php
		endforeach;
	}

	/**
	 *
	 *
	 * @action wp_login
	 *
	 * @param string  $user_login
	 * @param WP_User $user
	 *
	 * @return void
	 */
	public static function enforce_password_reset( $user_login, $user ) {
		var_dump( self::$user ); die();

		$reset = get_user_meta( $user->ID );

		if ( ! $reset ) {
			self::save_user_meta( $user->ID );
		}

		if ( ! self::is_password_expired( $user->ID ) ) {
			return;
		}

		wp_destroy_all_sessions();

		$location = add_query_arg(
			array(
				'action'          => 'lostpassword',
				self::PREFIX => 'expired',
			),
			wp_login_url()
		);

		wp_safe_redirect( $location, 302 );

		exit;
	}

	/**
	 *
	 *
	 * @filter login_message
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	public static function custom_login_message( $message ) {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : null;
		$status = isset( $_GET[ self::PREFIX ] ) ? $_GET[ self::PREFIX ] : null;

		if ( 'lostpassword' !== $action || 'expired' !== $status ) {
			return $message;
		}

		$message = sprintf(
			'<p id="login_error">%s</p><br><p>%s</p>',
			sprintf(
				esc_html__( 'Your password must be reset every %d days.', 'expire-passwords' ),
				self::get_limit()
			),
			esc_html__( 'Please enter your username or e-mail below and a password reset link will be sent to you.', 'expire-passwords' )
		);

		return $message; // xss ok
	}

	/**
 	 *
	 *
	 * @action password_reset
	 * @action user_register
	 *
	 * @param int $user_id (optional)
	 *
	 * @return void
	 */
	public static function save_user_meta( $user_id = null ) {
		$user_id = is_int( $user_id ) ? $user_id : self::$user->ID;

		update_user_meta( self::$user->ID, self::META_KEY, gmdate( 'U' ) );
	}

	/**
 	 *
	 *
	 * @param int $user_id
	 *
	 * @return int|bool
	 */
	public static function get_user_meta( $user_id ) {
		$value = get_user_meta( $user_id, self::META_KEY, true );

		return empty( $value ) ? false : absint( $value );
	}

	/**
	 *
	 *
	 * @return int
	 */
	public static function get_limit() {
		$options = get_option( self::PREFIX . '_settings' );
		$limit   = empty( $options['limit'] ) ? self::$default_limit : $options['limit'];

		return absint( $limit );
	}

	/**
	 *
	 *
	 * @return array
	 */
	public static function get_roles() {
		$options = get_option( self::PREFIX . '_settings' );
		$roles   = empty( $options['roles'] ) ? array() : array_keys( $options['roles'] );

		return (array) $roles;
	}

	/**
	 *
	 *
	 * @param int    $user_id (optional)
	 * @param string $date_format (optional)
	 *
	 * @return string|bool
	 */
	public static function get_expiration( $user_id = null, $date_format = 'U' ) {
		$user_id = is_int( $user_id ) ? $user_id : self::$user->ID;
		$reset   = self::get_user_meta( $user_id );

		if ( ! self::has_expirable_role( $user_id ) || ! $reset ) {
			return false;
		}

		$expires = strtotime( sprintf( '@%d + %d days', $reset, self::get_limit() ) );

		return gmdate( $date_format, $expires );
	}

	/**
	 *
	 *
	 * @param int $user_id (optional)
	 *
	 * @return bool
	 */
	public static function has_expirable_role( $user_id = null ) {
		$user  = is_int( $user_id ) ? get_userdata( $user_id ) : self::$user;
		$roles = array_intersect( $user->roles, self::get_roles() );

		return ! empty( $roles );
	}

	/**
	 *
	 *
	 * @param int $user_id (optional)
	 *
	 * @return bool
	 */
	public static function is_password_expired( $user_id = null ) {
		$user_id = is_int( $user_id ) ? $user_id : self::$user->ID;

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
	 *
	 *
	 * @action admin_head
	 *
	 * @return void
	 */
	public static function custom_user_admin_css() {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users' !== $screen->id ) {
			return;
		}
		?>
		<style type="text/css">
			.fixed .column-<?php echo esc_html( self::PREFIX ) ?> {
				width: 150px;
			}
			@media screen and (max-width: 782px) {
				.fixed .column-<?php echo esc_html( self::PREFIX ) ?> {
					display: none;
				}
			}
			.<?php echo esc_html( self::PREFIX ) ?>-is-expired {
				color: #a00;
			}
		</style>
		<?php
	}

	/**
	 *
	 *
	 * @filter manage_users_columns
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public static function custom_user_column( $columns ) {
		$columns[ self::PREFIX ] = esc_html__( 'Password Reset', 'expire-passwords' );

		return $columns;
	}

	/**
	 *
	 *
	 * @action manage_users_custom_column
	 *
	 * @param string $value
	 * @param string $column_name
	 * @param int    $user_id
	 *
	 * @return string
	 */
	public static function custom_user_column_content( $value, $column_name, $user_id ) {
		if ( self::PREFIX === $column_name ) {
			$reset = self::get_user_meta( $user_id );

			if ( ! self::has_expirable_role( $user_id ) || ! $reset ) {
				$value = '&mdash;';
			} else {
				$time_diff = sprintf( __( '%1$s ago', 'expire-passwords' ), human_time_diff( $reset, time() ) );

				if ( self::is_password_expired( $user_id ) ) {
					$value = sprintf( '<span class="%s-is-expired">%s</span>', esc_attr( self::PREFIX ), esc_html( $time_diff ) );
				} else {
					$value = sprintf( '<span class="%s-not-expired">%s</span>', esc_attr( self::PREFIX ), esc_html( $time_diff ) );
				}
			}
		}

		return $value; // xss ok
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
