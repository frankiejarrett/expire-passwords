<?php

class Expire_Passwords_Settings {

	/**
	 * Fire hooks
	 *
	 * @action init
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'submenu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'init' ) );
	}

	/**
	 * Add custom submenu page under the Users menu
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public static function submenu_page() {
		add_submenu_page(
			'users.php',
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			'manage_options',
			'expire_passwords',
			array( __CLASS__, 'render_submenu_page' )
		);
	}

	/**
	 * Content for the custom submenu page under the Users menu
	 *
	 * @see self::add_submenu_page()
	 *
	 * @return void
	 */
	public static function render_submenu_page() {
		?>
		<div class="wrap">

			<h2><?php esc_html_e( 'Expire Passwords', 'expire-passwords' ) ?></h2>

			<form method="post" action="options.php">

				<?php settings_fields( Expire_Passwords::PREFIX . '_settings_page' ) ?>

				<?php do_settings_sections( Expire_Passwords::PREFIX . '_settings_page' ) ?>

				<?php submit_button() ?>

			</form>

		</div>
		<?php
	}

	/**
	 * Register custom setting sections and fields
	 *
	 * @action admin_init
	 *
	 * @return void
	 */
	public static function init() {
		register_setting(
			Expire_Passwords::PREFIX . '_settings_page',
			Expire_Passwords::PREFIX . '_settings'
		);

		add_settings_section(
			Expire_Passwords::PREFIX . '_settings_page_section',
			null,
			array( __CLASS__, 'render_section' ),
			Expire_Passwords::PREFIX . '_settings_page'
		);

		add_settings_field(
			Expire_Passwords::PREFIX . '_settings_field_limit',
			esc_html__( 'Require password reset every', 'expire-passwords' ),
			array( __CLASS__, 'render_field_limit' ),
			Expire_Passwords::PREFIX . '_settings_page',
			Expire_Passwords::PREFIX . '_settings_page_section'
		);

		add_settings_field(
			Expire_Passwords::PREFIX . '_settings_field_roles',
			esc_html__( 'For users in these roles', 'expire-passwords' ),
			array( __CLASS__, 'render_field_roles' ),
			Expire_Passwords::PREFIX . '_settings_page',
			Expire_Passwords::PREFIX . '_settings_page_section'
		);
	}

	/**
	 * Content for the custom settings section
	 *
	 * @see self::init()
	 *
	 * @return void
	 */
	public static function render_section() {
		?>
		<p>
			<?php esc_html_e( 'Require certain users to change their passwords on a regular basis.', 'expire-passwords' ) ?>
		</p>
		<?php
	}

	/**
	 * Content for the limit setting field
	 *
	 * @see self::init()
	 *
	 * @return void
	 */
	public static function render_field_limit() {
		$options = get_option( Expire_Passwords::PREFIX . '_settings' );
		$value   = isset( $options['limit'] ) ? $options['limit'] : null;
		?>
		<input type="number" min="1" max="365" maxlength="3" name="<?php printf( '%s_settings[limit]', Expire_Passwords::PREFIX ) ?>" placeholder="<?php echo esc_attr( Expire_Passwords::$default_limit ) ?>" value="<?php echo esc_attr( $value ) ?>">
		<?php
		esc_html_e( 'days', 'expire-passwords' );
	}

	/**
	 * Content for the roles setting field
	 *
	 * @see self::init()
	 *
	 * @return void
	 */
	public static function render_field_roles() {
		$options = get_option( Expire_Passwords::PREFIX . '_settings' );
		$roles   = get_editable_roles();

		foreach ( $roles as $role => $role_data ) :
			$name  = sanitize_key( $role );

			if ( empty( $options ) ) {
				// Select all roles except admins by default if not set
				$value = ( 'administrator' === $role ) ? 0 : 1;
			} else {
				$value = empty( $options['roles'][ $name ] ) ? 0 : 1;
			}
			?>
			<p>
				<input type="checkbox" name="<?php printf( '%s_settings[roles][%s]', Expire_Passwords::PREFIX, esc_attr( $name ) ) ?>" id="<?php printf( '%s_settings[roles][%s]', Expire_Passwords::PREFIX, esc_attr( $name ) ) ?>" <?php checked( $value, 1 ) ?> value="1">
				<label for="<?php printf( '%s_settings[roles][%s]', Expire_Passwords::PREFIX, esc_attr( $name ) ) ?>"><?php echo esc_html( $role_data['name'] ) ?></label>
			</p>
			<?php
		endforeach;
	}

}
