<?php
/**
 * Tests section (/tests/ and /tests/{slug}/).
 *
 * To add a new test: drop a config file in inc/tests/{slug}.php (see holland.php),
 * a data file in assets/tests/{slug}.json and, if it needs its own result screen,
 * a renderer in assets/js/tests/{renderer}.js. The shared engine (assets/js/tests-engine.js)
 * handles gates (status/track/goal…), questions, progress, resume and the result hash.
 */

defined( 'ABSPATH' ) || exit;

/** All registered tests, keyed by slug. */
function mb_tests() {
	static $tests = null;
	if ( null === $tests ) {
		$tests = array();
		foreach ( (array) glob( MB_DIR . '/inc/tests/*.php' ) as $file ) {
			$cfg = require $file;
			if ( is_array( $cfg ) && ! empty( $cfg['slug'] ) ) {
				$tests[ $cfg['slug'] ] = $cfg;
			}
		}
		foreach ( (array) glob( MB_DIR . '/inc/tests/*.json' ) as $file ) {
			$cfg = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $cfg ) && ! empty( $cfg['slug'] ) ) {
				$tests[ $cfg['slug'] ] = $cfg;
			}
		}
		uasort( $tests, fn( $a, $b ) => ( $a['order'] ?? 10 ) <=> ( $b['order'] ?? 10 ) );
	}
	return $tests;
}

function mb_test_url( $slug = '' ) {
	return home_url( $slug ? '/tests/' . $slug . '/' : '/tests/' );
}

function mb_current_test() {
	$slug = get_query_var( 'mb_test' );
	$all  = mb_tests();
	return ( $slug && isset( $all[ $slug ] ) ) ? $all[ $slug ] : null;
}

function mb_is_tests_index() {
	return (bool) get_query_var( 'mb_tests_index' );
}

add_action( 'init', function () {
	add_rewrite_rule( '^tests-sitemap\.xml$', 'index.php?mb_tests_sitemap=1', 'top' );
	add_rewrite_rule( '^tests/?$', 'index.php?mb_tests_index=1', 'top' );
	add_rewrite_rule( '^tests/([a-z0-9-]+)/?$', 'index.php?mb_test=$matches[1]', 'top' );
	// Self-heal: flush once whenever the rule set changes (no wp-admin visit needed).
	$ver = '3';
	if ( get_option( 'mb_tests_rewrite' ) !== $ver ) {
		flush_rewrite_rules( false );
		update_option( 'mb_tests_rewrite', $ver, true );
	}
} );

add_filter( 'query_vars', function ( $vars ) {
	array_push( $vars, 'mb_test', 'mb_tests_index', 'mb_tests_sitemap' );
	return $vars;
} );

/** /tests-sitemap.xml — listed in the Rank Math sitemap index and robots.txt. */
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'mb_tests_sitemap' ) ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	$mod  = gmdate( 'c', (int) @filemtime( MB_DIR . '/inc/tests.php' ) );
	$urls = array( array( mb_test_url(), 'weekly', '0.8' ) );
	foreach ( mb_tests() as $t ) {
		$urls[] = array( mb_test_url( $t['slug'] ), 'monthly', '0.9' );
	}
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $urls as $u ) {
		printf( "<url><loc>%s</loc><lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%s</priority></url>\n", esc_url( $u[0] ), $mod, $u[1], $u[2] );
	}
	echo '</urlset>';
	exit;
}, 1 );
add_filter( 'rank_math/sitemap/index', function ( $xml ) {
	return $xml . '<sitemap><loc>' . esc_url( home_url( '/tests-sitemap.xml' ) ) . '</loc><lastmod>' . gmdate( 'c', (int) @filemtime( MB_DIR . '/inc/tests.php' ) ) . '</lastmod></sitemap>';
} );
add_filter( 'robots_txt', function ( $out ) {
	return $out . "\nSitemap: " . home_url( '/tests-sitemap.xml' ) . "\n";
}, 20 );

add_filter( 'template_include', function ( $template ) {
	if ( mb_is_tests_index() || get_query_var( 'mb_test' ) ) {
		global $wp_query;
		if ( mb_is_tests_index() || mb_current_test() ) {
			status_header( 200 );
			$wp_query->is_404 = false;
			return locate_template( mb_is_tests_index() ? 'tests-index.php' : 'tests-single.php' ) ?: $template;
		}
	}
	return $template;
} );

/** Rank Math doesn't know these virtual routes — supply their SEO signals. */
function mb_tests_seo( $field ) {
	if ( mb_is_tests_index() ) {
		$vals = array(
			'title'       => 'تست‌های رایگان شغلی، شخصیت و کسب‌وکار؛ هالند، دیسک، ۱۶ تیپ | میلاد بهرامی',
			'description' => 'تست‌های رایگان آنلاین انتخاب رشته، علایق شغلی (هالند)، شخصیت (دیسک و ۱۶ تیپ)، کارآفرینی و هوش‌های چندگانه؛ تفسیر کامل و بدون پرداخت، بدون ثبت‌نام.',
			'canonical'   => mb_test_url(),
		);
		return $vals[ $field ];
	}
	$t = mb_current_test();
	if ( $t ) {
		$vals = array(
			'title'       => $t['seo_title'],
			'description' => $t['seo_description'],
			'canonical'   => mb_test_url( $t['slug'] ),
		);
		return $vals[ $field ];
	}
	return null;
}
foreach ( array( 'title', 'description', 'canonical' ) as $f ) {
	add_filter( 'rank_math/frontend/' . $f, function ( $v ) use ( $f ) {
		$o = mb_tests_seo( $f );
		return null === $o ? $v : $o;
	} );
}
add_filter( 'pre_get_document_title', function ( $t ) {
	$o = mb_tests_seo( 'title' );
	return null === $o ? $t : $o;
}, 20 );
add_filter( 'rank_math/frontend/robots', function ( $r ) {
	if ( mb_is_tests_index() || mb_current_test() ) {
		$r = array( 'index' => 'index', 'follow' => 'follow' );
	}
	return $r;
} );

/** Assets + structured data for the test pages. */
add_action( 'wp_enqueue_scripts', function () {
	$t = mb_current_test();
	if ( ! $t ) {
		return;
	}
	$ver = MB_VER;
	wp_enqueue_script( 'mb-tests-engine', MB_URI . '/assets/js/tests-engine.js', array(), $ver . '.' . (int) @filemtime( MB_DIR . '/assets/js/tests-engine.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	$deps = array( 'mb-tests-engine' );
	if ( ! empty( $t['renderer_script'] ) ) {
		$f = '/assets/js/tests/' . $t['renderer_script'] . '.js';
		wp_enqueue_script( 'mb-test-renderer', MB_URI . $f, $deps, $ver . '.' . (int) @filemtime( MB_DIR . $f ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
}, 30 );

add_action( 'wp_head', function () {
	$graph = array();
	$t     = mb_current_test();
	$crumb = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => home_url( '/' ) ),
		array( '@type' => 'ListItem', 'position' => 2, 'name' => 'تست‌ها', 'item' => mb_test_url() ),
	);
	if ( $t ) {
		$crumb[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $t['name'], 'item' => mb_test_url( $t['slug'] ) );
		$graph[] = array(
			'@type'               => 'WebApplication',
			'name'                => $t['name'],
			'description'         => $t['seo_description'],
			'url'                 => mb_test_url( $t['slug'] ),
			'inLanguage'          => 'fa-IR',
			'applicationCategory' => 'EducationalApplication',
			'operatingSystem'     => 'Web',
			'isAccessibleForFree' => true,
			'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'IRR' ),
			'author'              => array( '@type' => 'Person', 'name' => 'میلاد بهرامی', 'url' => home_url( '/' ) ),
		);
		if ( ! empty( $t['faq'] ) ) {
			$ent = array();
			foreach ( $t['faq'] as $q ) {
				$ent[] = array( '@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $q[1] ) );
			}
			$graph[] = array( '@type' => 'FAQPage', 'mainEntity' => $ent );
		}
	} elseif ( mb_is_tests_index() ) {
		$graph[] = array( '@type' => 'CollectionPage', 'name' => 'تست‌های شغلی و کسب‌وکار', 'url' => mb_test_url(), 'inLanguage' => 'fa-IR' );
	} else {
		return;
	}
	$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $crumb );
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 5 );

/** Highlight the "تست‌ها" nav item. */
add_filter( 'mb_page_key', fn( $k ) => ( mb_is_tests_index() || get_query_var( 'mb_test' ) ) ? 'tests' : $k );
