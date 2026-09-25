<?php
/**
 * Comments: existing reader questions are indexed content (115 on the live site),
 * so they are shown in the design's style, with the author's replies highlighted.
 */
if ( post_password_required() ) {
	return;
}
?>
<section class="comments" id="comments" aria-labelledby="comments-title">
	<?php if ( have_comments() ) : ?>
		<h2 id="comments-title" class="h2" style="font-size:24px"><?php echo esc_html( mb_fa_digits( get_comments_number() ) ); ?> پرسش و نظر</h2>
		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'avatar_size' => 0,
				'short_ping'  => true,
				'callback'    => function ( $comment, $args, $depth ) {
					$is_author = user_can( $comment->user_id, 'edit_posts' ) || ( (int) $comment->user_id && (int) $comment->user_id === (int) get_post_field( 'post_author', $comment->comment_post_ID ) );
					?>
					<li id="comment-<?php comment_ID(); ?>" <?php comment_class( $is_author ? 'by-author' : '' ); ?>>
						<article>
							<header>
								<span class="ini" aria-hidden="true"><?php echo esc_html( mb_substr( get_comment_author(), 0, 1 ) ); ?></span>
								<b><?php comment_author(); ?></b>
								<?php if ( $is_author ) : ?><span class="chip light" style="font-size:12px;padding:2px 10px">نویسنده</span><?php endif; ?>
								<time datetime="<?php comment_time( 'c' ); ?>"><?php comment_date( 'j F Y' ); ?></time>
							</header>
							<div class="body"><?php comment_text(); ?></div>
							<?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'], 'reply_text' => 'پاسخ' ) ) ); ?>
						</article>
					<?php
				},
			) );
			?>
		</ol>
		<?php the_comments_navigation( array( 'prev_text' => 'نظرهای قدیمی‌تر', 'next_text' => 'نظرهای جدیدتر' ) ); ?>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'          => 'سؤال یا نظرتان را بنویسید',
		'title_reply_before'   => '<h2 id="reply-title" class="h2" style="font-size:22px">',
		'title_reply_after'    => '</h2>',
		'label_submit'         => 'ارسال',
		'class_submit'         => 'btn btn-ink',
		'comment_notes_before' => '<p class="help">نشانی ایمیل شما منتشر نمی‌شود.</p>',
		'comment_field'        => '<div class="field"><label for="comment">متن <span class="req">*</span></label><textarea id="comment" name="comment" rows="5" required></textarea></div>',
		'fields'               => array(
			'author' => '<div class="field"><label for="author">نام <span class="req">*</span></label><input id="author" name="author" autocomplete="name" required></div>',
			'email'  => '<div class="field"><label for="email">ایمیل <span class="req">*</span></label><input id="email" name="email" type="email" dir="ltr" autocomplete="email" required></div>',
		),
	) );
	?>
</section>
