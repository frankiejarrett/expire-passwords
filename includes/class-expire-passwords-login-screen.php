<?php

class Expire_Passwords_Login_Screen {

	/**
	 * Fire hooks
	 *
	 * @action init
	 *
	 * @return void
	 */
	public static function load() {
		add_action( 'wp_login', array( __CLASS__, 'wp_login' ), 10, 2 );
		add_action( 'validate_password_reset', array( __CLASS__, 'validate_password_reset' ), 10, 2 );
		add_filter( 'login_message', array( __CLASS__, 'lost_password_message' ) );
	}

	/**
	 * Enforce password reset after user login, when applicable
	 *
	 * @action wp_login
	 *
	 * @param string  $user_login
	 * @param WP_User $user
	 *
	 * @return void
	 */
	public static function wp_login( $user_login, $user ) {
		$reset = Expire_Passwords::get_user_meta( $user->ID );

		if ( ! $reset ) {
			Expire_Passwords::save_user_meta( $user->ID );
		}

		if ( ! Expire_Passwords::is_password_expired( $user->ID ) ) {
			return;
		}

		wp_destroy_all_sessions();

		$location = add_query_arg(
			array(
				'action'                 => 'lostpassword',
				Expire_Passwords::PREFIX => 'expired',
			),
			wp_login_url()
		);

		wp_safe_redirect( $location, 302 );

		exit;
	}

	/**
	 * Disallow using the same password as before on reset
	 *
	 * @action validate_password_reset
	 *
	 * @param WP_Error $errors
	 * @param WP_User  $user
	 *
	 * @return void
	 */
	public static function validate_password_reset( $errors, $user ) {
		$new_pass1 = ! empty( $_POST['pass1'] ) ? $_POST['pass1'] : null;
		$new_pass2 = ! empty( $_POST['pass2'] ) ? $_POST['pass2'] : null;

		if (
			is_null( $new_pass1 )
			||
			is_null( $new_pass2 )
			||
			$new_pass1 !== $new_pass2
			||
			! Expire_Passwords::has_expirable_role( $user->ID )
		) {
			return;
		}

		$is_same = wp_check_password( $new_pass1, $user->data->user_pass, $user->ID );

		if ( $is_same ) {
			$errors->add( 'password_already_used', esc_html__( 'You cannot reuse your old password.' ) );
		}
	}

	/**
	 * Display a custom message on the lost password login screen
	 *
	 * @filter login_message
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	public static function lost_password_message( $message ) {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : null;
		$status = isset( $_GET[ Expire_Passwords::PREFIX ] ) ? $_GET[ Expire_Passwords::PREFIX ] : null;

		if ( 'lostpassword' !== $action || 'expired' !== $status ) {
			return $message;
		}

		$message = sprintf(
			'<p id="login_error">%s</p><br><p>%s</p>',
			sprintf(
				esc_html__( 'Your password must be reset every %d days.', 'expire-passwords' ),
				Expire_Passwords::get_limit()
			),
			esc_html__( 'Please enter your username or e-mail below and a password reset link will be sent to you.', 'expire-passwords' )
		);

		return $message; // xss ok
	}

}
