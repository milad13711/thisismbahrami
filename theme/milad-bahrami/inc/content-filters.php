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
