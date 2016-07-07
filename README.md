# Pageviews for WordPress

Pageviews is a simple and lightweight views counter for your WordPress posts and pages. It is based on JavaScript and is compatible with all caching plugins, proxies and complex setups. Pageviews works with a hosted processing service, and performs extremely well under high-traffic and on sites with large amounts of content.

## Installation

Extract the archive contents into your `wp-content/plugins/pageviews` directory and activate the Pageviews plugin through the WordPress plugin management interface or WP-CLI. After activating, your website will start counting and displaying your pageviews immediately.

## Customization

By default, the number of views is displayed at the end of each post with a little bar-graph icon. Some WordPress themes with explicit support for the Pageviews plugin may display the counts differently to match the look and feel of the theme and its icon set.

To change the default behavior you'll need to declare support for the Pageviews plugin in your theme's functions.php file in the `after_setup_theme` action:

    add_action( 'after_setup_theme', function() {
        add_theme_support( 'pageviews' );
    });

This will disable the default behavior of appending the views count to the end of each post, giving you the freedom to display the counts with a simple `pageviews` action anywhere in your theme's template files (within the loop of course):

    Views: <?php do_action( 'pageviews' ); ?>
  
The action will result in a special placeholder in the HTML, which when rendered, will be filled with the actual views count. If you'd like to retrieve the placeholder markup instead of rendering it directly, you may use the `get_placeholder` method like this:

    if ( is_callable( array( 'Pageviews', 'get_placeholder' ) ) ) {
    	$placeholder = Pageviews::get_placeholder( $post->ID );
    	echo 'Views: ' . $placeholder;
    }

The placeholder container class name is `pageviews-placeholder` which can be used for any necessary CSS positioning and styling.

## Support

Feature requests, questions, bug reports and pull requests are always welcome. If you need help integrating this plugin into your theme, or migrating your existing counts from other plugins or services, such as Jetpack, Google Analytics, etc., please feel free to reach out to us by e-mail at support@pressjitsu.com.
