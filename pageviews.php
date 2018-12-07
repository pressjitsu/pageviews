<?php
/**
 * Plugin Name: Pageviews
 * Description: A simple and lightweight pageviews counter for your WordPress posts and pages.
 * Plugin URI: https://pageviews.io
 * Version: 0.11.0
 * Text Domain: pageviews
 * Domain Path: /languages/
 * License: GPLv3 or later
 */

class Pageviews {
	private static $_incr;
	private static $_js_version = 4;
	private static $_config;
	private static $_base = 'https://pv.pjtsu.com/v1';

	public static $_base_sync = 'https://pageviews.io';

	public static function load() {
		require_once( plugin_dir_path( __FILE__ ) . 'includes/rest-controller.php' );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );

		// Admin notices + dismiss handler.
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'wp_ajax_pageviews-dismiss-notice', array( __CLASS__, 'ajax_dismiss_notice' ) );

		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'pageviews', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	public static function template_redirect() {
		if ( is_singular() )
			self::$_incr = get_the_ID();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'pageviews', array( __CLASS__, 'placeholder' ), 10, 1 );

		if ( ! current_theme_supports( 'pageviews' ) ) {
			add_action( 'the_content', array( __CLASS__, 'compat_the_content' ) );
			add_action( 'wp_head', array( __CLASS__, 'compat_wp_head' ) );
		}
	}

	/**
	 * Output admin notices (unless dismissed)
	 */
	public static function admin_notices() {
		$key = 'pageviews-sync-nag';
		$config = self::get_config();

		if ( ! empty( $config['notice-dismissed'] ) )
			return;

		// Display a different notice based on whether the user is new or existing.
		if ( ! empty( $config['account'] ) ) {
			$message = __( 'Thank you for using Pageviews! <strong>Sync your numbers</strong> from Google Analytics and other services with <a href="%s" target="_blank">Pageviews Sync</a>.', 'pageviews' );
		} else {
			$message = __( 'Thank you for using Pageviews! <strong>Don\'t start from scratch!</strong> Import existing numbers from Google Analytics and other services with <a href="%s" target="_blank">Pageviews Sync</a>.', 'pageviews' );
		}

		$message = sprintf( $message, 'https://pageviews.io/sync/?utm_source=wp-admin&utm_medium=admin-notice&utm_campaign=existing' );

		include_once plugin_dir_path( __FILE__ ) . 'templates/admin-notice.php';
	}

	public static function ajax_dismiss_notice() {
		if ( empty( $_REQUEST['nonce'] ) )
			return wp_send_json_error();

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'pageviews-dismiss-notice' ) )
			return wp_send_json_error();

		$config = self::get_config();
		$config['notice-dismissed'] = true;
		self::update_config( $config );

		return wp_send_json_success();
	}

	public static function placeholder( $key = null ) {
		if ( empty( $key ) )
			$key = get_the_ID();

		echo self::get_placeholder( $key );
	}

	public static function get_placeholder( $key ) {
		return sprintf( '<span class="pageviews-placeholder" data-key="%s">%s</span>', esc_attr( $key ), apply_filters( 'pageviews_placeholder_preload', '' ) );
	}

	public static function compat_the_content( $content ) {
		$key = get_the_ID();
		$content .= '<div class="pageviews-wrapper"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1792 1792"><path d="M588.277,896v692.375H280.555V896H588.277z M1049.86,630.363v958.012h-307.72V630.363H1049.86z M1511.446,203.625v1384.75h-307.725V203.625H1511.446z"/></svg>' . self::get_placeholder( $key ) . '</div>';
		return $content;
	}

	/**
	 * Compat styles.
	 */
	public static function compat_wp_head() {
		?>
		<style>
		.pageviews-wrapper { height: 16px; line-height: 16px; font-size: 11px; clear: both; }
		.pageviews-wrapper svg { width: 16px; height: 16px; fill: #aaa; float: left; margin-right: 2px; }
		.pageviews-wrapper span { float: left; }
		</style>
		<?php
	}

	/**
	 * Pageviews front-end scripts.
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		add_action( 'wp_footer', array( __CLASS__, 'wp_footer' ) );
	}

	/**
	 * Output async script in footer.
	 */
	public static function wp_footer() {
		$account = self::get_account_key();
		if ( empty( $account ) )
			return;

		$config = array(
			'account' => $account,
			'incr' => self::$_incr,
			'base' => self::$_base,
		);
		
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$version = apply_filters( 'pageviews_script_version_param', '?v=' . self::$_js_version );
		?>
		<!-- Pageviews SDK -->
		<script>
		var _pv_config = <?php echo json_encode( $config ); ?>;
		<?php do_action( 'pageviews_before_js', $config ); ?>
		(function(){
			var js = document.createElement('script'); js.type = 'text/javascript'; js.async = true;
			js.src = '<?php echo esc_js( plugins_url( '/pageviews' . $suffix. '.js' . $version, __FILE__ ) ); ?>';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(js, s);
		})();
		</script>
		<?php
	}

	/**
	 * Return a configuration array.
	 *
	 * @return array
	 */
	public static function get_config() {
		if ( isset( self::$_config ) )
			return self::$_config;

		$defaults = array(
			'account' => '',
			'secret' => '',
			'notice-dismissed' => false,
		);

		self::$_config = wp_parse_args( get_option( 'pageviews_config', array() ), $defaults );
		return self::$_config;
	}

	/**
	 * Update configuration.
	 *
	 * @param array $config New configuration.
	 */
	public static function update_config( $config ) {
		update_option( 'pageviews_config', $config );
		self::$_config = $config;
	}

	/**
	 * Get the account key
	 *
	 * @return string The account key.
	 */
	public static function get_account_key() {
		$config = self::get_config();
		if ( ! empty( $config['account'] ) )
			return $config['account'];

		// Don't attempt to re-register more frequently than once every 12 hours.
		$can_register = true;
		if ( ! empty( $config['register-error'] ) && time() - $config['register-error'] < 12 * HOUR_IN_SECONDS )
			$can_register = false;

		// Obtain a new account key if necessary.
		if ( empty( $config['account'] ) && $can_register ) {

			// TODO: Better locking.
			$config['register-error'] = time();
			self::update_config( $config );

			$request = wp_remote_post( self::$_base . '/register' );
			if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ) {
				$response = json_decode( wp_remote_retrieve_body( $request ) );
				$config['account'] = $response->account;
				$config['secret'] = $response->secret;
				unset( $config['register-error'] );

				self::update_config( $config );
			}
		}

		return $config['account'];
	}
}

Pageviews::load();
