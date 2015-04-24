<?php
/**
 * Plugin Name: Expire Passwords
 * Description: Require certain users to change their passwords on demand or after a specified length of time.
 * Version: 0.1.0
 * Author: Frankie Jarrett
 * Author URI: http://frankiejarrett.com
 * License: GPLv2+
 * Text Domain: expire-passwords
 */

/**
 * Define plugin constants
 */
define( 'EXPIRE_PASSWORDS_VERSION', '0.1.0' );
define( 'EXPIRE_PASSWORDS_PLUGIN', plugin_basename( __FILE__ ) );
define( 'EXPIRE_PASSWORDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXPIRE_PASSWORDS_URL', plugin_dir_url( __FILE__ ) );
define( 'EXPIRE_PASSWORDS_LANG_PATH', dirname( EXPIRE_PASSWORDS_PLUGIN ) . '/languages' );
define( 'EXPIRE_PASSWORDS_META_KEY', 'expass_password_set' );

/**
 * Load languages
 *
 * @action plugins_loaded
 *
 * @return void
 */
function expass_i18n() {
	load_plugin_textdomain( 'expire-passwords', false, EXPIRE_PASSWORDS_LANG_PATH );
}
add_action( 'plugins_loaded', 'expass_i18n' );

/**
 * Translations strings placeholder function
 *
 * Translation strings that are not used elsewhere but Plugin Title and Description
 * are helt here to be picked up by Poedit. Keep these in sync with the actual plugin's
 * title and description.
 *
 * @return void
 */
function expass_i18n_strings() {
	esc_html__( 'Require certain users to change their passwords on demand or after a specified length of time.', 'expire-passwords' );
}

/**
 *
 *
 * @action password_reset
 * @action user_register
 *
 * @param int|WP_User $user
 *
 * @return void
 */
function expass_new_password_set( $user ) {
	$user_id = is_int( $user ) ? $user : $user->ID;

	update_user_meta( $user_id, EXPIRE_PASSWORDS_META_KEY, current_time( 'mysql', 1 ) );
}
add_action( 'user_register', 'expass_new_password_set', 10, 1 );
add_action( 'password_reset', 'expass_new_password_set', 10, 1 );

/**
 *
 *
 * @return int
 */
function expass_get_password_expiration_limit() {
	$option  = get_option( 'expass_settings' );
	$default = apply_filters( 'expass_default_password_expiration_limit', 30 );
	$limit   = empty( $option['limit'] ) ? $default : $option['limit'];

	return absint( $limit );
}

/**
 *
 *
 * @return array
 */
function expass_get_password_expiration_roles() {
	$option = get_option( 'expass_settings' );
	$roles  = empty( $option['roles'] ) ? array() : array_keys( $option['roles'] );

	return (array) $roles;
}

/**
 *
 *
 * @param int    $user_id
 * @param string $date_format
 *
 * @return
 */
function expass_get_password_expiration( $user_id, $date_format = 'U' ) {
	$set = get_user_meta( $user_id, EXPIRE_PASSWORDS_META_KEY, true );

	if ( ! expass_user_has_expirable_role( $user_id ) || empty( $set ) ) {
		return;
	}

	$limit   = expass_get_password_expiration_limit();
	$expires = strtotime( sprintf( '@%d + %d days', strtotime( $set ), $limit ) );

	return gmdate( $date_format, $expires );
}

/**
 *
 *
 * @param int $user_id
 *
 * @return bool
 */
function expass_is_password_expired( $user_id ) {
	if ( ! expass_user_has_expirable_role( $user_id ) ) {
		return false;
	}

	$expires = expass_get_password_expiration();

	if ( ! $expires ) {
		return true;
	}

	return ( $expires > time() );
}

/**
 *
 *
 * @param int $user_id
 *
 * @return bool
 */
function expass_user_has_expirable_role( $user_id ) {
	$user    = get_userdata( $user_id );
	$compare = array_intersect( $user->roles, expass_get_password_expiration_roles() );

	return ! empty( $compare );
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
function expass_custom_user_columns( $columns ) {
	$columns['expass'] = 'Password Expires';

	return $columns;
}
add_filter( 'manage_users_columns', 'expass_custom_user_columns', 10, 1 );

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
function expass_custom_user_columns_content( $value, $column_name, $user_id ) {
	if ( 'expass' === $column_name ) {
		$date  = expass_get_password_expiration( $user_id );
		$value = empty( $date ) ? esc_html__( 'Never', 'expire-passwords' ) : sprintf( esc_html__( 'in %1$s', 'expire-passwords' ), human_time_diff( time(), $date ) );
	}

	return $value;
}
add_action( 'manage_users_custom_column', 'expass_custom_user_columns_content', 10, 3 );





function expass_add_admin_menu() {
	add_submenu_page( 'users.php', esc_html__( 'Expire Passwords', 'expire-passwords' ), esc_html__( 'Expire Passwords', 'expire-passwords' ), 'manage_options', 'expire_passwords', 'expass_options_page' );
}
add_action( 'admin_menu', 'expass_add_admin_menu' );


function expass_settings_init() {
	register_setting( 'expass_settings_page', 'expass_settings' );

	add_settings_section(
		'expass_expass_settings_page_section',
		null,
		'expass_settings_section_callback',
		'expass_settings_page'
	);

	add_settings_field(
		'expass_password_expiration_limit',
		esc_html__( 'Require password reset every', 'expire-passwords' ),
		'expass_password_expiration_limit_render',
		'expass_settings_page',
		'expass_expass_settings_page_section'
	);

	add_settings_field(
		'expass_checkbox_roles',
		esc_html__( 'For users in these roles', 'expire-passwords' ),
		'expass_checkbox_roles_render',
		'expass_settings_page',
		'expass_expass_settings_page_section'
	);
}
add_action( 'admin_init', 'expass_settings_init' );


function expass_password_expiration_limit_render() {
	$options = get_option( 'expass_settings' );
	$value   = isset( $options['limit'] ) ? $options['limit'] : null;
	?>
	<input type="number" min="1" max="365" maxlength="3" name="expass_settings[limit]" placeholder="30" value="<?php echo esc_attr( $value ) ?>">
	<?php
	esc_html_e( 'days', 'expire-passwords' );
}


function expass_checkbox_roles_render() {
	$options = get_option( 'expass_settings' );
	$roles   = get_editable_roles();

	foreach ( $roles as $role => $role_data ) :
		$name  = sanitize_key( $role );
		$value = empty( $options['roles'][ $name ] ) ? 0 : 1;
		?>
		<p>
			<input type="checkbox" name="expass_settings[roles][<?php echo esc_attr( $name ) ?>]" id="expass_settings[roles][<?php echo esc_attr( $name ) ?>]" <?php checked( $value, 1 ) ?> value="1">
			<label for="expass_settings[roles][<?php echo esc_attr( $name ) ?>]"><?php echo esc_html( $role_data['name'] ) ?></label>
		</p>
		<?php
	endforeach;
}


function expass_settings_section_callback() {
	?>
	<p>
		<?php esc_html_e( 'Require certain users to change their passwords after a specified length of time.', 'expire-passwords' ) ?>
	</p>
	<?php
}


function expass_options_page() {
	?>
	<div class="wrap">

		<h2>Expire Passwords</h2>

		<form action='options.php' method='post'>

			<?php settings_fields( 'expass_settings_page' ) ?>

			<?php do_settings_sections( 'expass_settings_page' ) ?>

			<?php submit_button() ?>

		</form>

	</div>
	<?php
}
