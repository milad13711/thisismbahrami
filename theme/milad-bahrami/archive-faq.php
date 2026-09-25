<?php
/**
 * FAQ index at /faq/all/ (design: design/src/pages/faq.html).
 * Grouped by the faq-group taxonomy; client-side search via site.js; FAQPage schema.
 */
get_header();
$faqs   = get_posts( array( 'post_type' => 'faq', 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
$groups = array();
foreach ( $faqs as $f ) {
	$t   = get_the_terms( $f->ID, 'faq-group' );
	$key = ( $t && ! is_wp_error( $t ) ) ? $t[0]->name : 'عمومی';
	$groups[ $key ][] = $f;
}
$pm = '<span class="pm" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>';
$schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array() );
?>
<!-- ============ ۱ · توجه + جستجو ============ -->
<section class="phero on-dark">
	<div class="wrap">
		<div class="rise" style="max-width:760px">
			<ol class="crumbs" aria-label="مسیر صفحه"><li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a></li><li aria-current="page">سوالات متداول</li></ol>
			<h1>سوالات <span class="grad">متداول</span></h1>
			<p class="lead">پاسخ کوتاه و روشن به پرسش‌هایی که مدیران قبل از شروع همکاری می‌پرسند.</p>
			<div style="margin-top:26px">
				<div data-filter-group="#faqList" hidden></div>
				<label class="search" style="display:block;max-width:520px"><input type="search" placeholder="پرسش خود را جستجو کنید…" data-filter-search aria-label="جستجو در پرسش‌ها" style="height:54px;background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.14);color:#fff"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.6"/><path d="m12.5 12.5 3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></label>
			</div>
		</div>
	</div>
</section>

<!-- ============ ۲ · رفع تردید ============ -->
<section class="sec">
	<div class="wrap faq-page">
		<nav class="faq-nav" aria-label="دسته‌ها">
			<?php $g = 0; foreach ( array_keys( $groups ) as $name ) : $g++; ?>
				<a href="#g-<?php echo (int) $g; ?>"><?php echo esc_html( $name ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div id="faqList">
			<?php
			$g = 0;
			foreach ( $groups as $name => $items ) :
				$g++;
				?>
				<div class="faq-group" id="g-<?php echo (int) $g; ?>" data-reveal>
					<h2><?php echo esc_html( $name ); ?></h2>
					<div class="faq" style="display:block">
					<?php
					foreach ( $items as $f ) :
						$answer = apply_filters( 'the_content', $f->post_content );
						$schema['mainEntity'][] = array(
							'@type'          => 'Question',
							'name'           => get_the_title( $f ),
							'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $answer ) ),
						);
						?>
						<details data-cat="faq"><summary><?php echo esc_html( get_the_title( $f ) ); ?><?php echo $pm; // phpcs:ignore ?></summary>
							<div class="prose" style="padding-bottom:18px;font-size:16px"><?php echo wp_kses_post( $answer ); ?>
							<p><a href="<?php echo esc_url( get_permalink( $f ) ); ?>">پاسخ کامل ←</a></p></div>
						</details>
					<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<p class="filter-empty" hidden>پرسشی پیدا نشد. <a href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>" style="color:var(--gold-text);font-weight:700">سؤال‌تان را مستقیم بپرسید</a></p>
		</div>
	</div>
</section>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<!-- ============ ۳ · اقدام ============ -->
<section>
	<div class="wrap">
		<div class="cta-band on-dark" data-reveal>
			<div><h2>پاسخ سؤال‌تان را پیدا نکردید؟</h2><p>در جلسه‌ی مشاوره، مستقیم درباره‌ی سازمان خودتان جواب می‌گیرید.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a></div>
		</div>
	</div>
</section>
<?php
get_footer();
