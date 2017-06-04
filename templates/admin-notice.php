<div class="pageviews-notice notice notice-success is-dismissible">
	<p><?php echo $message; ?></p>
</div>
<script>
(function($){
	$('.pageviews-notice').on('click', '.notice-dismiss', function(){
		wp.ajax.post('pageviews-dismiss-notice', {
			nonce: '<?php echo esc_js( wp_create_nonce( 'pageviews-dismiss-notice' ) ); ?>'
		});
	});
}(jQuery));
</script>
