<?php
/**
 * Link map: the design prototypes link to "service.html", "about.html", …
 * Generated templates call mb_link( 'service' ) instead, which resolves to the
 * real (SEO-preserved) permalink on the live site.
 */

defined( 'ABSPATH' ) || exit;

/** Path on the site for each prototype key. Existing ranking URLs are kept verbatim. */
function mb_link_map() {
	return apply_filters( 'mb_link_map', array(
		'index'      => '/',
		'service'    => '/مشاوره-کسب-و-کار/',                       // existing URL
		'about'      => '/میلاد-بهرامی-مشاور-عارضه-یابی-توسعه/',     // existing URL
		'book'       => '/کتاب-سلطان-قیف-funnel-king/',               // existing URL (a post)
		'blog'       => '/blog/',                                     // existing URL
		'faq'        => '/faq/all/',                                  // existing URL
		'projects'   => '/portfolio/',
		'chapter'    => '/سلطان-قیف-فصل-اول/',                        // new
		'assessment' => '/ارزیابی-سازمان/',                           // new
		'tribute'    => '/کارآفرینی-a-tribute-to-success/',        // existing post
		'exir-case'  => '/portfolio/مدیریت-شرکت-اکسیر-تجارت-امین/',   // existing case
		'article'    => '/قیف-فروش-سلطان-قیف/',                       // existing post (sample link target)
		'case'       => '/portfolio/',                                // case links in static templates fall back to the archive
		'404'        => '/',
		'pages'      => '/',
	) );
}

/**
 * Absolute URL for a prototype key, with each path segment percent-encoded so
 * Persian slugs produce valid, canonical-matching hrefs.
 */
function mb_link( $key, $fragment = '' ) {
	$map  = mb_link_map();
	$path = $map[ $key ] ?? '/';
	$enc  = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
	$url  = home_url( $enc );
	return $fragment ? $url . '#' . $fragment : $url;
}

function mb_img( $file ) {
	return MB_URI . '/assets/img/' . $file;
}

/** Key used by site.js to mark the active nav item (matches data-nav in header). */
function mb_page_key() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'portfolio' ) || is_post_type_archive( 'portfolio' ) || is_tax( array( 'portfolio-cat', 'portfolio-tag', 'portfolio-filter' ) ) ) {
		return 'projects';
	}
	$tpl = get_page_template_slug();
	if ( $tpl ) {
		$map = array(
			'page-templates/service-consulting.php' => 'services',
			'page-templates/service.php'            => 'services',
			'page-templates/about.php'              => 'about',
			'page-templates/book.php'               => 'book',
			'page-templates/chapter.php'            => 'book',
		);
		if ( isset( $map[ $tpl ] ) ) {
			return $map[ $tpl ];
		}
	}
	if ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_search() ) {
		return 'blog';
	}
	return '';
}
