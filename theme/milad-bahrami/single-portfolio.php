<?php
/**
 * Case study (design: design/src/pages/case.html).
 * Story comes from the editor content (h2 per chapter: معرفی، مسئله، راهکار، استقرار، نتایج);
 * the fact card and quote come from the "مشخصات پروژه" meta box.
 */
get_header();
the_post();
$m      = fn( $k ) => (string) get_post_meta( get_the_ID(), $k, true );
$terms  = get_the_terms( get_the_ID(), 'portfolio-cat' );
$terms  = ( $terms && ! is_wp_error( $terms ) ) ? $terms : array();
$facts  = array_filter( array(
	'کارفرما' => $m( 'mb_client' ),
	'حوزه'    => $m( 'mb_industry' ),
	'خدمت'    => $m( 'mb_service' ),
	'دامنه'   => $m( 'mb_scope' ),
	'ابزار'   => $m( 'mb_tools' ),
	'سال'     => $m( 'mb_year' ),
) );
$next = get_adjacent_post( false, '', true ) ?: get_adjacent_post( false, '', false );
?>
<!-- ============ ۱ · توجه: نتیجه در عنوان ============ -->
<section class="phero on-dark">
	<div class="wrap">
		<div class="rise" style="max-width:860px">
			<?php mb_breadcrumbs(); ?>
			<div class="chips" style="margin-top:22px"><span class="chip gold">مطالعه‌ی موردی</span><?php foreach ( $terms as $t ) : ?><span class="chip"><?php echo esc_html( $t->name ); ?></span><?php endforeach; ?></div>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		</div>
		<?php if ( $m( 'mb_metric' ) ) : ?>
			<div class="results rise" style="--d:150ms;margin-top:36px;max-width:860px;grid-template-columns:minmax(0,320px)">
				<div class="result"><b><?php echo esc_html( $m( 'mb_metric' ) ); ?></b><span><?php echo esc_html( $m( 'mb_metric_label' ) ); ?></span></div>
			</div>
		<?php endif; ?>
	</div>
</section>

<!-- ============ ۲ · داستان پروژه ============ -->
<section class="sec">
	<div class="wrap case-layout">
		<article <?php post_class( 'prose book-prose case-prose' ); ?> style="max-width:none">
			<?php the_content(); ?>
			<?php if ( $m( 'mb_quote' ) ) : ?>
				<div class="quote-big" style="margin-top:32px"><span class="qm" aria-hidden="true">”</span><div><blockquote><?php echo esc_html( $m( 'mb_quote' ) ); ?></blockquote><cite><?php echo esc_html( $m( 'mb_quote_by' ) ); ?></cite></div></div>
			<?php endif; ?>
		</article>
		<?php if ( $facts ) : ?>
		<aside class="facts-card" aria-label="مشخصات پروژه">
			<dl><?php foreach ( $facts as $k => $v ) : ?><div><dt><?php echo esc_html( $k ); ?></dt><dd><?php echo esc_html( $v ); ?></dd></div><?php endforeach; ?></dl>
			<a class="btn btn-gold" href="https://exirerp.ir/book/coaching">پروژه‌ی مشابه دارید؟</a>
		</aside>
		<?php endif; ?>
	</div>
</section>

<!-- ============ ۳ · ادامه‌ی مسیر ============ -->
<section class="sec band">
	<div class="wrap" style="display:grid;gap:18px">
		<?php if ( $next ) : ?>
			<a class="next-case" href="<?php echo esc_url( get_permalink( $next ) ); ?>" data-reveal><div><small>پروژه‌ی بعدی</small><b><?php echo esc_html( get_the_title( $next ) ); ?></b></div><?php echo mb_arrow( 22 ); // phpcs:ignore ?></a>
		<?php endif; ?>
		<div class="cta-band on-dark" data-reveal>
			<div><h2>سازمان شما هم به یک سیستم نیاز دارد؟</h2><p>با یک جلسه‌ی تشخیص شروع کنیم.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a></div>
		</div>
	</div>
</section>
<?php
get_footer();
