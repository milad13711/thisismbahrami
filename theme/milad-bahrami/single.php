<?php
/**
 * Single article (design: design/src/pages/article.html).
 * Funnel: attention (title + author credibility) → content with TOC and a
 * mid-article CTA → author box (E-E-A-T) → same-cluster posts → action.
 */

get_header();
the_post();

list( $content, $toc ) = mb_content_with_toc( apply_filters( 'the_content', get_the_content() ) );
if ( count( $toc ) >= 3 ) {
	$content = mb_inject_inline_cta( $content );
}
$cats = get_the_category();
$cat  = $cats ? $cats[0] : null;
// Optional "خلاصه در ۳۰ ثانیه" box: one takeaway per line in the mb_tldr custom field.
$tldr = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( get_the_ID(), 'mb_tldr', true ) ) ) );
?>

<!-- ============ ۱ · توجه: عنوان و اعتبار نویسنده ============ -->
<section class="phero on-dark" style="padding-bottom:56px">
	<div class="wrap" style="max-width:1040px">
		<div class="rise">
			<?php mb_breadcrumbs(); ?>
			<?php if ( $cat ) : ?>
				<div class="chips" style="margin-top:22px"><a class="chip gold" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></div>
			<?php endif; ?>
			<h1 style="max-width:900px"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="lead" style="max-width:760px"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<div class="a-meta">
				<span class="who"><?php echo mb_avatar(); // phpcs:ignore ?>میلاد بهرامی</span>
				<span>به‌روزرسانی: <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'j F Y' ) ); ?></time></span>
				<span><?php echo esc_html( mb_reading_time() ); ?> دقیقه مطالعه</span>
			</div>
		</div>
	</div>
</section>

<!-- ============ ۲ · محتوا ============ -->
<section class="sec" style="padding-top:56px">
	<div class="wrap a-layout" style="max-width:1100px">
		<article <?php post_class(); ?>>
			<?php if ( $tldr ) : ?>
				<div class="tldr"><b>خلاصه در ۳۰ ثانیه</b><ul>
				<?php foreach ( $tldr as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
				</ul></div>
			<?php endif; ?>

			<?php if ( $toc ) : ?>
				<details class="toc-m"><summary>فهرست مطالب</summary><ol>
				<?php foreach ( $toc as $t ) : ?><li><a href="#<?php echo esc_attr( $t[0] ); ?>"><?php echo esc_html( $t[1] ); ?></a></li><?php endforeach; ?>
				</ol></details>
			<?php endif; ?>

			<div class="prose">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content ?>
			</div>

			<p class="updated">آخرین به‌روزرسانی: <?php echo esc_html( get_the_modified_date( 'j F Y' ) ); ?> · بازبینی تخصصی: میلاد بهرامی</p>
			<?php mb_author_box(); ?>
		</article>

		<aside class="a-side" aria-label="ابزارهای مقاله">
			<?php if ( $toc ) : ?>
				<nav class="toc" aria-label="فهرست مطالب"><h2>فهرست مطالب</h2><ol>
				<?php foreach ( $toc as $t ) : ?><li><a href="#<?php echo esc_attr( $t[0] ); ?>"><?php echo esc_html( $t[1] ); ?></a></li><?php endforeach; ?>
				</ol></nav>
			<?php endif; ?>
			<div class="side-cta">
				<img src="<?php echo esc_url( mb_img( 'funnel-king-360.jpg' ) ); ?>" alt="" width="360" height="500" loading="lazy" style="width:96px;height:auto;border-radius:4px 8px 8px 4px;margin-bottom:14px;box-shadow:-6px 8px 20px rgba(0,0,0,.4)">
				<b>کتاب سلطان قیف</b>
				<p>فصل اول را رایگان بخوانید.</p>
				<a class="btn btn-gold" href="<?php echo esc_url( mb_link( 'chapter' ) ); ?>">خواندن فصل اول</a>
			</div>
			<div class="share" aria-label="اشتراک‌گذاری">
				<button type="button" data-copy aria-label="کپی لینک مقاله"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M7.5 10.5a3 3 0 0 0 4.2 0l2.6-2.6a3 3 0 0 0-4.2-4.2l-.9.9M10.5 7.5a3 3 0 0 0-4.2 0L3.7 10.1a3 3 0 0 0 4.2 4.2l.9-.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></button>
				<a href="<?php echo esc_url( 'https://t.me/share/url?url=' . rawurlencode( get_permalink() ) ); ?>" target="_blank" rel="noopener" aria-label="اشتراک در تلگرام"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M15.5 3 2.5 8.2l4.2 1.5L8.4 15l2.3-3 3.4 2.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></a>
				<a href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( get_permalink() ) ); ?>" target="_blank" rel="noopener" aria-label="اشتراک در لینکدین"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="13" height="13" rx="3" stroke="currentColor" stroke-width="1.5"/><path d="M6 8v4.5M6 5.8v.1M9 12.5V10a1.8 1.8 0 0 1 3.6 0v2.5M9 8v4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></a>
			</div>
		</aside>
	</div>
</section>

<?php
// ============ ۳ · پرورش: مقالات هم‌خوشه ============
$related = get_posts( array(
	'posts_per_page'      => 3,
	'post__not_in'        => array( get_the_ID() ),
	'category__in'        => $cat ? array( $cat->term_id ) : array(),
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
) );
if ( $related ) :
	?>
<section class="sec band">
	<div class="wrap">
		<div class="sec-head split" data-reveal>
			<div style="display:grid;gap:14px"><span class="eyebrow">در همین خوشه</span><h2 class="h2">ادامه‌ی مسیر یادگیری</h2></div>
			<a class="btn btn-line" href="<?php echo esc_url( mb_link( 'blog' ) ); ?>">همه‌ی مقالات</a>
		</div>
		<div class="posts"><?php foreach ( $related as $r ) { mb_post_card( $r ); } ?></div>
	</div>
</section>
<?php endif; ?>

<!-- ============ ۴ · اقدام ============ -->
<section class="sec" style="padding-bottom:0">
	<div class="wrap">
		<div class="cta-band on-dark" data-reveal>
			<div><h2>این موضوع را برای سازمان خودتان بررسی کنیم؟</h2><p>در جلسه‌ی تشخیص، همین چارچوب روی داده‌های کسب‌وکار شما پیاده می‌شود.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a></div>
		</div>
	</div>
</section>

<?php
get_footer();
