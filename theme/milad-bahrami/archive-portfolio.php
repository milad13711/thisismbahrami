<?php
/**
 * Portfolio archive (design: design/src/pages/projects.html).
 * Filter chips are client-side (site.js) over the portfolio-cat terms.
 */
get_header();
$terms = get_terms( array( 'taxonomy' => 'portfolio-cat', 'hide_empty' => true ) );
$terms = is_wp_error( $terms ) ? array() : $terms;
$args  = array( 'post_type' => 'portfolio', 'posts_per_page' => -1, 'orderby' => 'menu_order date', 'order' => 'DESC' );
if ( is_tax() ) { // Also serves /portfolio-cat/… and friends.
	$args['tax_query'] = array( array( 'taxonomy' => get_queried_object()->taxonomy, 'terms' => get_queried_object_id() ) );
}
$q     = new WP_Query( $args );
?>
<!-- ============ ۱ · توجه ============ -->
<section class="phero on-dark">
	<div class="wrap">
		<div class="rise" style="max-width:760px">
			<?php mb_breadcrumbs(); ?>
			<h1>نمونه‌کارها و <span class="grad">پروژه‌ها</span></h1>
			<p class="lead">از کارخانه‌ها و سازمان‌های بزرگ تا کسب‌وکارهای در حال رشد؛ هر پروژه با یک مسئله‌ی واقعی شروع شد و با یک سیستم قابل‌اجرا تمام شد.</p>
		</div>
	</div>
</section>

<!-- ============ ۲ · اثبات ============ -->
<section class="sec">
	<div class="wrap">
		<?php if ( $terms ) : ?>
		<div class="filters">
			<div class="filter-chips" data-filter-group="#projGrid" role="group" aria-label="فیلتر نوع پروژه">
				<button type="button" data-filter="all" aria-pressed="true">همه <small style="opacity:.6"><?php echo esc_html( mb_fa_digits( $q->post_count ) ); ?></small></button>
				<?php foreach ( $terms as $t ) : ?>
					<button type="button" data-filter="<?php echo esc_attr( $t->slug ); ?>" aria-pressed="false"><?php echo esc_html( $t->name ); ?> <small style="opacity:.6"><?php echo esc_html( mb_fa_digits( $t->count ) ); ?></small></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="grid-3" id="projGrid">
			<?php
			$i = 0;
			while ( $q->have_posts() ) :
				$q->the_post();
				$pt     = get_the_terms( get_the_ID(), 'portfolio-cat' );
				$pt     = ( $pt && ! is_wp_error( $pt ) ) ? $pt : array();
				$slugs  = implode( ' ', wp_list_pluck( $pt, 'slug' ) );
				$metric = get_post_meta( get_the_ID(), 'mb_metric', true );
				$cover  = has_post_thumbnail() ? get_the_post_thumbnail( null, 'mb-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : mb_cover_svg( $pt ? $pt[0]->slug : get_the_ID(), 360, 140 );
				?>
				<a class="proj" href="<?php the_permalink(); ?>" data-cat="<?php echo esc_attr( $slugs ); ?>" data-reveal<?php echo ( $i % 3 ) ? ' style="--d:' . (int) ( ( $i % 3 ) * 70 ) . 'ms"' : ''; ?>>
					<div class="cover"><?php echo $cover; // phpcs:ignore ?><?php if ( $pt ) : ?><span class="tag"><?php echo esc_html( $pt[0]->name ); ?></span><?php endif; ?><?php if ( $metric ) : ?><b class="cover-metric"><?php echo esc_html( $metric ); ?></b><?php endif; ?></div>
					<div class="in"><h3><?php the_title(); ?></h3><p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p><span class="go">مطالعه‌ی پروژه ←</span></div>
				</a>
				<?php
				$i++;
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<p class="filter-empty" hidden>پروژه‌ای در این دسته نیست.</p>
	</div>
</section>

<!-- ============ ۳ · اقدام ============ -->
<section>
	<div class="wrap">
		<div class="cta-band on-dark" data-reveal>
			<div><h2>پروژه‌ی بعدی می‌تواند سازمان شما باشد</h2><p>با یک جلسه‌ی تشخیص شروع کنیم و ببینیم مسئله‌ی اصلی کجاست.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a></div>
		</div>
	</div>
</section>
<?php
get_footer();
