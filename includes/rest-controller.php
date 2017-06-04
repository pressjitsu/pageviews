<?php
class Pageviews_REST_Controller {
	public static function load() {
		add_action( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );
	}

	/**
	 * REST API Endpoints
	 */
	public static function rest_api_init() {
		register_rest_route( 'pageviews/1.0', '/ping', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'ping' ),
		) );

		register_rest_route( 'pageviews/1.0', '/auth', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'auth' ),
		) );

		register_rest_route( 'pageviews/1.0', '/posts', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'posts' ),
			'permission_callback' => array( __CLASS__, '_permission' ),
		) );
	}

	/**
	 * Check ?token= against a previously set transient.
	 */
	public static function _permission( $request ) {
		$auth = get_transient( 'pageviews:rest-auth' );
		if ( empty( $auth['token'] ) ) {
			return false;
		}

		$token = $request->get_param( 'token' );
		if ( empty( $token ) ) {
			return false;
		}

		// Authentication token is valid.
		if ( hash_equals( $auth['token'], $token ) ) {
			return true;
		}

		return false;
	}

	/**
	 * /pageviews/1.0/ping
	 */
	public static function ping( $request ) {
		return 'pong';
	}

	/**
	 * /pageviews/1.0/posts?token=...&last_id=...
	 */
	public static function posts( $request ) {
		global $wpdb;

		$post_type_in = implode( ',', array_map( function( $i ) {
			return $GLOBALS['wpdb']->prepare( '%s', $i ); },
			array_values( get_post_types( array( 'public' => true ) ) )
		) );

		$last_id = absint( $request->get_param( 'last_id' ) );
		$per_page = 1000;

		$wpdb->queries = array();
		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID > %d AND post_type IN ({$post_type_in}) ORDER BY ID ASC LIMIT %d;", $last_id, $per_page ) );

		if ( empty( $post_ids ) ) {
			return array();
		}

		_prime_post_caches( $post_ids, false, false );

		$post_ids = array_flip( $post_ids );
		foreach ( $post_ids as $post_id => $_ ) {
			$post_ids[ $post_id ] = get_permalink( $post_id );
		}

		return $post_ids;
	}

	/**
	 * /pageviews/1.0/auth (public endpoint, cookie-based auth)
	 */
	public static function auth( $request ) {
		$user_id = apply_filters( 'determine_current_user', false );
		wp_set_current_user( $user_id ? $user_id : 0 );
		$current_user = wp_get_current_user();

		if ( ! is_user_logged_in() ) {
			$redirect_url = rest_url( '/pageviews/1.0/auth' );
			wp_safe_redirect( wp_login_url( $redirect_url ) );
			die();
		}

		$config = get_option( 'pageviews_config' );
		if ( empty( $config['account'] ) ) {
			return new WP_Error( 'pageviews-account-not-found' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cant-manage-options' );
		}

		if ( $request->get_param( 'allow' ) && $request->get_param( 'nonce' ) ) {

			if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'pageviews-auth' ) ) {
				return new WP_Error( 'invalid-nonce' );
			}

			$token = sha1( wp_generate_password( 30 ) );
			set_transient( 'pageviews:rest-auth', array(
				'token' => $token,
				'granted_by' => get_current_user_id(),
				'time' => time(),
			), HOUR_IN_SECONDS );

			$redirect_url = Pageviews::$_base_sync . '/a/1.0/sync/callback/';
			$redirect_url = add_query_arg( array(
				'token' => rawurlencode( $token ),
				'account' => $config['account'],
				'signature' => hash_hmac( 'sha256', $token, $config['secret'] ),
			), $redirect_url );
			wp_redirect( esc_url_raw( $redirect_url ) );
			die();
		}

		header( 'Content-Type: text/html' );
		load_template( dirname( plugin_dir_path( __FILE__ ) ) . '/templates/auth.php', true );
		die();
	}
}

Pageviews_REST_Controller::load();
