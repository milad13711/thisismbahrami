<?php
/**
 * Article content enhancements: heading anchors + table of contents,
 * and a mid-article CTA before the 3rd section heading.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Give every <h2> in post content a stable id and collect them for the TOC.
 * Returns [ html, toc_items ].
 */
function mb_content_with_toc( $html ) {
	$toc = array();
	$i   = 0;
	$html = preg_replace_callback(
		'/<h2([^>]*)>(.*?)<\/h2>/su',
		function ( $m ) use ( &$toc, &$i ) {
			$i++;
			$attrs = $m[1];
			if ( preg_match( '/\bid="([^"]+)"/', $attrs, $idm ) ) {
				$id = $idm[1];
			} else {
				$id    = 's' . $i;
				$attrs .= ' id="' . $id . '"';
			}
			$toc[] = array( $id, wp_strip_all_tags( $m[2] ) );
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$html
	);
	return array( $html, $toc );
}

/**
 * Old Elementor posts repeated the post title as their first heading (formerly an
 * in-content H1, now H2). Drop it when it matches the title the template already prints.
 */
function mb_strip_title_heading( $html, $title ) {
	$norm = fn( $t ) => preg_replace( '/[\s\x{200C}\x{200F}\x{00A0}]+/u', '', wp_strip_all_tags( html_entity_decode( $t ) ) );
	return preg_replace_callback( '/^\s*<h2[^>]*>(.*?)<\/h2>/su', function ( $m ) use ( $norm, $title ) {
		$a = $norm( $m[1] );
		$b = $norm( $title );
		return ( $a === $b || ( mb_strlen( $a ) > 12 && ( str_contains( $b, $a ) || str_contains( $a, $b ) ) ) ) ? '' : $m[0];
	}, $html, 1 );
}

/** Insert the service CTA before the 3rd h2 of long articles. */
function mb_inject_inline_cta( $html ) {
	$cta = '<div class="inline-cta"><div><b>سازمان شما کجا نشت می‌کند؟</b><span>در ارزیابی سازمان، گلوگاه‌ها را با داده‌ی واقعی پیدا می‌کنیم.</span></div><a class="btn btn-gold" href="' . esc_url( mb_link( 'assessment' ) ) . '">ارزیابی رایگان اولیه</a></div>';
	$n   = 0;
	return preg_replace_callback( '/<h2\b/u', function ( $m ) use ( &$n, $cta ) {
		$n++;
		return 3 === $n ? $cta . $m[0] : $m[0];
	}, $html );
}

/**
 * Old posts built with Elementor were converted to plain HTML during migration;
 * strip leftover empty wrappers so prose typography applies cleanly.
 */
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular( 'post' ) ) {
		return $html;
	}
	return preg_replace( '/<div[^>]*class="[^"]*elementor[^"]*"[^>]*>\s*<\/div>/u', '', $html );
}, 20 );

/** The template prints the only H1; demote any H1 left inside migrated content. */
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular() || ! in_the_loop() ) {
		return $html;
	}
	return preg_replace( '/<(\/?)h1\b/u', '<$1h2', $html );
}, 8 );

/* ------------------------------------------------------------------ content quality (migrated posts) */

/**
 * Old Phlox/Elementor posts headed their sections with H4 (or a lone H2 + H4s). Re-level the headings
 * in use so the outline is H2 → H3 → H4 without skips; this also feeds the TOC and mid-article CTA.
 */
function mb_normalize_headings( $html ) {
	if ( ! preg_match_all( '/<h([2-6])\b/i', $html, $m ) ) {
		return $html;
	}
	$levels = array_unique( array_map( 'intval', $m[1] ) );
	sort( $levels );
	$map = array();
	foreach ( $levels as $i => $lvl ) {
		$map[ $lvl ] = min( 2 + $i, 6 );
	}
	return preg_replace_callback( '/<(\/?)h([2-6])\b/i', function ( $x ) use ( $map ) {
		return '<' . $x[1] . 'h' . $map[ (int) $x[2] ];
	}, $html );
}

/** Redirected legacy paths (English slugs, truncated slugs) → their final canonical URL key. */
function mb_legacy_link_map() {
	return apply_filters( 'mb_legacy_link_map', array(
		'moshavereh'                     => array( 'key', 'service' ),
		'moshavereh-2'                   => array( 'key', 'service' ),
		'about-us'                       => array( 'key', 'about' ),
		'author/mbahrami'                => array( 'key', 'about' ),
		'contact-us'                     => array( 'key', 'assessment', 'contact' ),
		'services'                       => array( 'path', '/مشاور-کسب-و-کار-حرفه-ای-سازمان-های-پیشرو/' ),
		'job-diagnostic'                 => array( 'path', '/10-قدم-عارضه-یابی-کسب-و-کار/' ),
		'market-competition'             => array( 'path', '/5-استراتژی-رقابت-در-بازار-رقابتی/' ),
		'appropriate-business-in-iran'   => array( 'path', '/کسب-و-کار-متناسب-با-اقتصاد-ایران-سال-1403/' ),
		'31-مشاغل-خانگی-زنان-خانه-دار-مشاور-کسب-و-ک' => array( 'path', '/مشاغل-خانگی-زنان-خانه-دار-مشاور-کسب-کار/' ),
	) );
}

function mb_path_url( $path ) {
	return home_url( implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) );
}

/**
 * Link hygiene for migrated content: point legacy/redirected internal links at their final URL,
 * open internal links in the same tab, fill "#" placeholder buttons, drop admin/preview links.
 */
function mb_optimize_links( $html ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$map  = mb_legacy_link_map();
	return preg_replace_callback( '/<a\b([^>]*)>(.*?)<\/a>/su', function ( $m ) use ( $host, $map ) {
		$attrs = $m[1];
		$text  = $m[2];
		if ( ! preg_match( '/\bhref="([^"]*)"/u', $attrs, $hm ) ) {
			return $m[0];
		}
		$href = html_entity_decode( $hm[1] );

		if ( '#' === $href || '' === $href ) {
			$plain = wp_strip_all_tags( $text );
			if ( preg_match( '/خرید|تهیه/u', $plain ) ) {
				$new = 'https://funnelking.ir';
			} elseif ( str_contains( $plain, 'بازی' ) ) {
				$new = mb_link( 'book', 'game' );
			} else {
				return $m[0];
			}
			return '<a' . preg_replace( '/\bhref="[^"]*"/u', 'href="' . esc_url( $new ) . '"', $attrs ) . '>' . $text . '</a>';
		}

		$parts = wp_parse_url( $href );
		if ( ! $parts ) {
			return $m[0];
		}
		$h        = isset( $parts['host'] ) ? preg_replace( '/^www\./', '', strtolower( $parts['host'] ) ) : '';
		$internal = ( '' === $h && isset( $parts['path'] ) && str_starts_with( $parts['path'], '/' ) ) || in_array( $h, array( $host, 'thisismbahrami.ir' ), true );
		if ( ! $internal ) {
			return $m[0];
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( preg_match( '#^/(wp-admin/|wp-login)#', $path ) || ( isset( $parts['query'] ) && str_contains( $parts['query'], 'preview' ) ) ) {
			return $text; // stray edit/preview link: keep the words, drop the link.
		}
		$key = trim( rawurldecode( $path ), '/' );
		$new = null;
		if ( isset( $map[ $key ] ) ) {
			$r   = $map[ $key ];
			$new = 'key' === $r[0] ? mb_link( $r[1], $r[2] ?? '' ) : mb_path_url( $r[1] );
		} elseif ( '' !== $h ) {
			$new = home_url( $path ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
		}
		if ( $new ) {
			$attrs = preg_replace( '/\bhref="[^"]*"/u', 'href="' . esc_url( $new ) . '"', $attrs );
		}
		$attrs = preg_replace( '/\s+(target|rel)="[^"]*"/u', '', $attrs );
		return '<a' . $attrs . '>' . $text . '</a>';
	}, $html );
}

add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || ! is_singular() ) {
		return $html;
	}
	$html = mb_optimize_links( $html );
	if ( is_singular( 'post' ) ) {
		$html = preg_replace( '/\s*\*\*\s*/u', ' ', $html );
		$html = mb_normalize_headings( $html );
	}
	return $html;
}, 9 );
