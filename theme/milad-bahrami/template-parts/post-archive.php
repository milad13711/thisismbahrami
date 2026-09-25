<?php
/**
 * Shared archive body for the blog (home.php), categories/tags (archive.php)
 * and search (search.php). Design: design/src/pages/blog.html.
 * Topic chips are real links to category archives (crawlable), not JS filters.
 */
global $wp_query;
$is_blog   = is_home();
$current   = is_category() ? get_queried_object_id() : 0;
$cats      = get_categories( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ) );
$title     = $is_blog ? 'دانشنامه‌ی <span class="grad">سیستم‌سازی</span>' : ( is_search() ? 'جستجو: <span class="grad">' . esc_html( get_search_query() ) . '</span>' : esc_html( single_term_title( '', false ) ) );
$lead      = $is_blog ? 'راهنماهای عملی برای مدیرانی که می‌خواهند کسب‌وکارشان بدون وابستگی به افراد رشد کند؛ از فرآیند و ERP تا فروش.' : wp_strip_all_tags( term_description() );
$paged     = max( 1, (int) get_query_var( 'paged' ) );
?>
<!-- ============ ۱ · توجه: هاب موضوعی ============ -->
<section class="phero on-dark">
	<div class="wrap">
		<div class="rise" style="max-width:760px">
			<?php mb_breadcrumbs(); ?>
			<h1><?php echo $title; // phpcs:ignore -- escaped above ?></h1>
			<?php if ( $lead ) : ?><p class="lead"><?php echo esc_html( $lead ); ?></p><?php endif; ?>
		</div>
		<?php if ( $is_blog && 1 === $paged ) : ?>
		<div class="pillars rise" style="--d:150ms;margin-top:40px">
			<?php foreach ( array_slice( $cats, 0, 4 ) as $c ) : ?>
				<a class="pillar" href="<?php echo esc_url( get_category_link( $c ) ); ?>"><b><?php echo esc_html( $c->name ); ?></b><span><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $c->description ), 8, '…' ) ?: mb_fa_digits( $c->count ) . ' مقاله' ); ?></span><span class="n">مشاهده‌ی خوشه ←</span></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</section>

<!-- ============ ۲ · کاوش ============ -->
<section class="sec">
	<div class="wrap">
		<div class="filters">
			<nav class="filter-chips" aria-label="موضوع‌ها">
				<a href="<?php echo esc_url( mb_link( 'blog' ) ); ?>"<?php echo $is_blog ? ' aria-current="page"' : ''; ?>>همه</a>
				<?php foreach ( $cats as $c ) : ?>
					<a href="<?php echo esc_url( get_category_link( $c ) ); ?>"<?php echo $current === $c->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $c->name ); ?> <small style="opacity:.6"><?php echo esc_html( mb_fa_digits( $c->count ) ); ?></small></a>
				<?php endforeach; ?>
			</nav>
			<form class="search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="جستجو در دانشنامه…" aria-label="جستجو در مقالات">
				<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.6"/><path d="m12.5 12.5 3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
			</form>
		</div>

		<?php if ( have_posts() ) : ?>
		<div class="posts">
			<?php
			$i = 0;
			while ( have_posts() ) :
				the_post();
				mb_post_card( null, $is_blog && 1 === $paged && 0 === $i );
				$i++;
				if ( 6 === $i ) : // ۳ · پرورش سرنخ: lead magnet in the middle of the list
					?>
					<div class="magnet" style="grid-column:1/-1" data-reveal>
						<div class="doc">فصل<br>اول<br>رایگان</div>
						<div><h3>فصل اول کتاب سلطان قیف را رایگان بخوانید</h3><p>DNA تفکر استراتژیک، همراه با بوم Strategic DNA برای کسب‌وکار خودتان.</p></div>
						<a class="btn btn-ink" href="<?php echo esc_url( mb_link( 'chapter' ) ); ?>">خواندن رایگان</a>
					</div>
					<?php
				endif;
			endwhile;
			?>
		</div>
		<?php
		$links = paginate_links( array( 'type' => 'array', 'prev_text' => '›', 'next_text' => '‹', 'mid_size' => 1 ) );
		if ( $links ) {
			echo '<nav class="pager" aria-label="صفحه‌بندی">';
			foreach ( $links as $l ) {
				echo str_replace( array( 'page-numbers current', '<span' ), array( 'page-numbers" aria-current="page', '<a' ), $l ); // phpcs:ignore
			}
			echo '</nav>';
		}
		else :
			?>
			<p class="filter-empty">مطلبی پیدا نشد. <a href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>" style="color:var(--gold-text);font-weight:700">سؤال‌تان را مستقیم بپرسید</a></p>
		<?php endif; ?>
	</div>
</section>

<!-- ============ ۴ · اقدام ============ -->
<section>
	<div class="wrap">
		<div class="cta-band on-dark" data-reveal>
			<div><h2>سؤال شما در مقاله‌ها نبود؟</h2><p>مسئله‌ی سازمان‌تان را مستقیم مطرح کنید؛ در جلسه‌ی مشاوره پاسخ مشخص می‌گیرید.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a></div>
		</div>
	</div>
</section>
