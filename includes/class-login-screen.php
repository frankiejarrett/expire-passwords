<?php

namespace Expire_Passwords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Login_Screen {

	/**
	 * Plugin instance
	 *
	 * @var Expire_Passwords_Plugin
	 */
	private $plugin;

	/**
	 * Class constructor
	 */
	public function __construct( \Expire_Passwords_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'wp_login', array( $this, 'wp_login' ), 10, 2 );
		add_action( 'validate_password_reset', array( $this, 'validate_password_reset' ), 10, 2 );
		add_filter( 'login_message', array( $this, 'lost_password_message' ) );
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
	public function wp_login( $user_login, $user ) {
		$reset = $this->plugin->get_user_meta( $user->ID );

		if ( ! $reset ) {
			$this->plugin->save_user_meta( $user->ID );
		}

		if ( ! $this->plugin->is_password_expired( $user->ID ) ) {
			return;
		}

		wp_destroy_all_sessions();

		$location = add_query_arg(
			array(
				'action'              => 'lostpassword',
				$this->plugin->prefix => 'expired',
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
	public function validate_password_reset( $errors, $user ) {
		$new_pass1 = ! empty( $_POST['pass1'] ) ? $_POST['pass1'] : null;
		$new_pass2 = ! empty( $_POST['pass2'] ) ? $_POST['pass2'] : null;

		if (
			is_null( $new_pass1 )
			||
			is_null( $new_pass2 )
			||
			$new_pass1 !== $new_pass2
			||
			! $this->plugin->has_expirable_role( $user->ID )
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
	public function lost_password_message( $message ) {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : null;
		$status = isset( $_GET[ $this->plugin->prefix ] ) ? $_GET[ $this->plugin->prefix ] : null;

		if ( 'lostpassword' !== $action || 'expired' !== $status ) {
			return $message;
		}

		$limit = $this->plugin->get_limit();

		$message = sprintf(
			'<p id="login_error">%s</p><br><p>%s</p>',
			sprintf(
				_n(
					'Your password must be reset every day.',
					'Your password must be reset every %d days.',
					$limit,
					'expire-passwords'
				),
				$limit
			),
			esc_html__( 'Please enter your username or e-mail below and a password reset link will be sent to you.', 'expire-passwords' )
		);

		return $message; // xss ok
	}

}
