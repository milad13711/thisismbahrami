<?php
/**
 * Template helpers shared by the dynamic templates.
 */

defined( 'ABSPATH' ) || exit;

function mb_fa_digits( $s ) {
	return strtr( (string) $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}

function mb_arrow( $size = 16 ) {
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13 8H3m0 0 4-4M3 8l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		$size
	);
}

/** Reading time in minutes (Persian digits), ~200 words/minute. */
function mb_reading_time( $post = null ) {
	$text  = wp_strip_all_tags( get_post_field( 'post_content', $post ) );
	$words = count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
	return mb_fa_digits( max( 1, (int) round( $words / 200 ) ) );
}

/** Breadcrumb trail in the design's markup. Rank Math adds BreadcrumbList schema separately. */
function mb_breadcrumbs() {
	$items = array( array( home_url( '/' ), 'خانه' ) );
	if ( is_singular( 'post' ) ) {
		$items[] = array( mb_link( 'blog' ), 'دانشنامه' );
		$cat     = get_the_category();
		if ( $cat ) {
			$items[] = array( get_category_link( $cat[0] ), $cat[0]->name );
		}
	} elseif ( is_singular( 'portfolio' ) ) {
		$items[] = array( get_post_type_archive_link( 'portfolio' ), 'نمونه‌کارها' );
	} elseif ( is_singular( 'faq' ) ) {
		$items[] = array( mb_link( 'faq' ), 'سوالات متداول' );
	} elseif ( is_category() || is_tag() ) {
		$items[] = array( mb_link( 'blog' ), 'دانشنامه' );
	}
	if ( is_singular() ) {
		$current = get_the_title();
	} elseif ( is_home() ) {
		$current = 'دانشنامه';
	} elseif ( is_post_type_archive( 'portfolio' ) ) {
		$current = 'نمونه‌کارها';
	} elseif ( is_search() ) {
		$current = 'جستجو: ' . get_search_query();
	} else {
		$current = wp_strip_all_tags( get_the_archive_title() );
	}
	echo '<ol class="crumbs" aria-label="مسیر صفحه">';
	foreach ( $items as $it ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $it[0] ), esc_html( $it[1] ) );
	}
	printf( '<li aria-current="page">%s</li></ol>', esc_html( $current ) );
}

/**
 * Decorative SVG cover used when a post has no featured image, chosen from the
 * cluster (category slug) so each topic keeps a recognisable look.
 */
function mb_cover_svg( $seed = '', $w = 320, $h = 180 ) {
	$sets = array(
		array( '#0F1B2E', '<g stroke="rgba(230,201,142,.4)" fill="none" stroke-width="1.4"><path d="M40 140h60V90h60V40h120"/><path d="M100 140v30M160 90v80"/></g><g fill="#E6C98E"><circle cx="100" cy="90" r="5"/><circle cx="160" cy="40" r="5"/><circle cx="280" cy="40" r="5"/></g>' ),
		array( '#2A2013', '<g fill="none" stroke="rgba(230,201,142,.5)" stroke-width="1.5"><path d="M150 26h150l-44 36h-62z"/><path d="M196 68h68l-20 26h-28z"/><path d="M216 100h28l-8 22h-12z"/></g>' ),
		array( '#1A2740', '<g stroke="rgba(230,201,142,.35)" fill="none"><circle cx="90" cy="90" r="30"/><circle cx="90" cy="90" r="56"/><circle cx="90" cy="90" r="82"/></g><circle cx="90" cy="90" r="6" fill="#E6C98E"/>' ),
		array( '#0F2A28', '<g fill="rgba(111,211,189,.22)"><rect x="40" y="110" width="26" height="70"/><rect x="76" y="80" width="26" height="100"/><rect x="112" y="60" width="26" height="120"/></g><path d="M40 100 90 76l35-20 150-10" stroke="#6FD3BD" stroke-width="2" fill="none"/>' ),
	);
	$set = $sets[ abs( crc32( (string) $seed ) ) % count( $sets ) ];
	return sprintf(
		'<svg viewBox="0 0 %1$d %2$d" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="%1$d" height="%2$d" fill="%3$s"/>%4$s</svg>',
		$w, $h, $set[0], $set[1]
	);
}

/** A post card in the design's .post markup. */
function mb_post_card( $post = null, $feature = false ) {
	$post = get_post( $post );
	$cat  = get_the_category( $post->ID );
	$cat  = $cat ? $cat[0] : null;
	$thumb = has_post_thumbnail( $post )
		? get_the_post_thumbnail( $post, 'mb-card', array( 'loading' => 'lazy', 'alt' => '' ) )
		: mb_cover_svg( $cat ? $cat->slug : $post->ID );
	printf(
		'<a class="post%1$s" href="%2$s" data-cat="%3$s" data-reveal><div class="th">%4$s%5$s</div><div class="in"><h3>%6$s</h3><p>%7$s</p><div class="meta"><span>%8$s دقیقه مطالعه</span></div>%9$s</div></a>',
		$feature ? ' feature' : '',
		esc_url( get_permalink( $post ) ),
		esc_attr( $cat ? $cat->slug : '' ),
		$thumb, // Core-generated markup / our own SVG.
		$cat ? '<span class="cat">' . esc_html( $cat->name ) . '</span>' : '',
		esc_html( get_the_title( $post ) ),
		esc_html( wp_trim_words( get_the_excerpt( $post ), 26, '…' ) ),
		esc_html( mb_reading_time( $post ) ),
		$feature ? '<span class="btn btn-ink" style="align-self:start;margin-top:14px;min-height:46px">مطالعه‌ی راهنما</span>' : ''
	);
}

function mb_avatar() {
	return '<span class="avatar"><picture><source type="image/webp" srcset="' . esc_url( mb_img( 'avatar-240.webp' ) ) . '"><img src="' . esc_url( mb_img( 'avatar-240.jpg' ) ) . '" alt="" width="240" height="240" loading="lazy"></picture></span>';
}

/** E-E-A-T author box. */
function mb_author_box() {
	?>
	<div class="author">
		<?php echo mb_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<b>میلاد بهرامی</b>
			<div class="role">مشاور سیستم‌سازی و تحول سازمانی · نویسنده‌ی سلطان قیف</div>
			<p>مدیرعامل اکسیر تجارت امین و دارای دکتری حرفه‌ای مدیریت راهبردی کسب‌وکار (DBA)؛ متخصص معماری فرآیند، ERP، BPMS و اتوماسیون.</p>
			<div class="links"><a href="<?php echo esc_url( mb_link( 'about' ) ); ?>">درباره من</a><a href="https://www.linkedin.com/in/thisismbahrami/" target="_blank" rel="noopener">لینکدین</a><a href="https://instagram.com/thisismbahrami" target="_blank" rel="noopener">اینستاگرام</a></div>
		</div>
	</div>
	<?php
}

/** Posts for a knowledge cluster: by category slug, falling back to recent posts. */
function mb_cluster_posts( $cat_slug, $count = 4, $exclude = array() ) {
	$args = array( 'posts_per_page' => $count, 'post__not_in' => $exclude, 'ignore_sticky_posts' => true, 'no_found_rows' => true );
	if ( $cat_slug && get_category_by_slug( $cat_slug ) ) {
		$args['category_name'] = $cat_slug;
	}
	return get_posts( $args );
}
