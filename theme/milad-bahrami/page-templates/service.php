<?php
/**
 * Template Name: صفحه‌ی خدمت (Money Page)
 *
 * Hero from the page itself (title, excerpt, featured "در یک نگاه" rows from the
 * mb_glance custom field: one "label|value" per line); the body is assembled in
 * the editor from the "خدمت — …" block patterns (fit, deliverables, process,
 * modes, proof, FAQ, CTA), which follow the same funnel as the consulting page.
 */
get_header();
the_post();
$glance = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( get_the_ID(), 'mb_glance', true ) ) ) );
?>
<!-- ============ ۱ · توجه ============ -->
<section class="phero on-dark">
	<div class="wrap phero-grid">
		<div class="rise">
			<ol class="crumbs" aria-label="مسیر صفحه"><li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a></li><li><a href="<?php echo esc_url( mb_link( 'index', 'services' ) ); ?>">خدمات</a></li><li aria-current="page"><?php the_title(); ?></li></ol>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			<div class="ctas">
				<a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره <?php echo mb_arrow( 18 ); // phpcs:ignore ?></a>
				<a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a>
			</div>
		</div>
		<?php if ( $glance ) : ?>
		<aside class="glass rise" style="--d:150ms" aria-label="خلاصه‌ی خدمت">
			<h2>در یک نگاه</h2>
			<div style="margin-top:10px">
			<?php foreach ( $glance as $row ) : list( $k, $v ) = array_pad( explode( '|', $row, 2 ), 2, '' ); ?>
				<div class="row"><span><?php echo esc_html( trim( $k ) ); ?></span><b><?php echo esc_html( trim( $v ) ); ?></b></div>
			<?php endforeach; ?>
			</div>
		</aside>
		<?php endif; ?>
	</div>
</section>

<?php the_content(); ?>

<?php
get_footer();
