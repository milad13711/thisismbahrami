<?php
/**
 * The page's own editor content inside a landing template, so text that already
 * ranks (book post, about page) is kept and stays editable. Hidden when empty.
 */
$html = apply_filters( 'the_content', get_post_field( 'post_content', get_queried_object_id() ) );
if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
	return;
}
?>
<section class="sec" style="padding-top:0">
	<div class="wrap" style="max-width:820px">
		<?php if ( ! empty( $args['title'] ) ) : ?>
			<h2 class="h2" style="font-size:26px;margin-bottom:18px"><?php echo esc_html( $args['title'] ); ?></h2>
		<?php endif; ?>
		<div class="prose"><?php echo mb_strip_title_heading( $html, get_the_title( get_queried_object_id() ) ); // phpcs:ignore ?></div>
	</div>
</section>
