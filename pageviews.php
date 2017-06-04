<?php
/**
 * Plugin Name: Pageviews
 * Description: A simple and lightweight pageviews counter for your WordPress posts and pages.
 * Plugin URI: https://pageviews.io
 * Version: 0.9.2
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

	public static function placeholder( $key = null ) {
		if ( empty( $key ) )
			$key = get_the_ID();

		echo self::get_placeholder( $key );
	}

	public static function get_placeholder( $key ) {
		return '<span class="pageviews-placeholder" data-key="' . esc_attr( $key ) . '"></span>';
	}

	public static function compat_the_content( $content ) {
		$key = get_the_ID();
		$content .= '<div class="pageviews-wrapper"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1792 1792"><path d="M588.277,896v692.375H280.555V896H588.277z M1049.86,630.363v958.012h-307.72V630.363H1049.86z M1511.446,203.625v1384.75h-307.725V203.625H1511.446z"/></svg>' . self::get_placeholder( $key ) . '</div>';
		return $content;
	}

	public static function compat_wp_head() {
		?>
		<style>
		.pageviews-wrapper { height: 16px; line-height: 16px; font-size: 11px; clear: both; }
		.pageviews-wrapper svg { width: 16px; height: 16px; fill: #aaa; float: left; margin-right: 2px; }
		.pageviews-wrapper span { float: left; }
		</style>
		<?php
	}

	public static function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		add_action( 'wp_footer', array( __CLASS__, 'wp_footer' ) );
	}

	public static function wp_footer() {
		$account = self::get_account_key();
		if ( empty( $account ) )
			return;

		$config = array(
			'account' => $account,
			'incr' => self::$_incr,
			'base' => self::$_base,
		);
		?>
		<!-- Pageviews SDK -->
		<script>
		var _pv_config = <?php echo json_encode( $config ); ?>;
		(function(){
			var js = document.createElement('script'); js.type = 'text/javascript'; js.async = true;
			js.src = '<?php echo esc_js( plugins_url( '/pageviews.js?v=' . self::$_js_version, __FILE__ ) ); ?>';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(js, s);
		})();
		</script>
		<?php
	}

	public static function get_account_key() {
		if ( isset( self::$_config ) )
			return self::$_config['account'];

		self::$_config = get_option( 'pageviews_config', array(
			'account' => '',
			'secret' => '',
		) );

		// Don't attempt to re-register more frequently than once every 12 hours.
		$can_register = true;
		if ( ! empty( self::$_config['register_error'] ) && time() - self::$_config['register_error'] < 12 * HOUR_IN_SECONDS )
			$can_register = false;

		// Obtain a new account key if necessary.
		if ( empty( self::$_config['account'] ) && $can_register ) {

			// TODO: Better locking.
			self::$_config['register_error'] = time();
			update_option( 'pageviews_config', self::$_config );

			$request = wp_remote_post( self::$_base . '/register' );
			if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ) {
				$response = json_decode( wp_remote_retrieve_body( $request ) );
				self::$_config['account'] = $response->account;
				self::$_config['secret'] = $response->secret;
				unset( self::$_config['register_error'] );

				update_option( 'pageviews_config', self::$_config );
			}
		}

		return self::$_config['account'];
	}
}

Pageviews::load();
