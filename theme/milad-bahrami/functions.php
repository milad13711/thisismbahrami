<?php
/**
 * Milad Bahrami theme — bootstrap.
 *
 * Design source of truth lives in /design (static prototypes). Static landing
 * templates are generated from it by theme/build-theme.py; dynamic templates
 * (single, archives, portfolio, faq) are hand-written and use the same CSS.
 */

defined( 'ABSPATH' ) || exit;

define( 'MB_VER', '1.0.0' );
define( 'MB_DIR', get_template_directory() );
define( 'MB_URI', get_template_directory_uri() );

require MB_DIR . '/inc/links.php';
require MB_DIR . '/inc/content-types.php';
require MB_DIR . '/inc/template-tags.php';
require MB_DIR . '/inc/content-filters.php';
require MB_DIR . '/inc/seo.php';
require MB_DIR . '/inc/leads.php';
require MB_DIR . '/inc/erp.php';
require MB_DIR . '/inc/tests.php';

/* ---------------------------------------------------------------------------
 * Setup
 * ------------------------------------------------------------------------- */
add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'mb', MB_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
	add_image_size( 'mb-card', 720, 405, true );
	register_nav_menus( array( 'footer-legal' => 'منوی قانونی فوتر' ) );
} );

add_action( 'init', function () {
	register_block_pattern_category( 'mb-sections', array( 'label' => 'بخش‌های سایت (قیف)' ) );
} );

/* ---------------------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'mb-site', MB_URI . '/assets/css/site.css', array(), MB_VER . '.' . (int) @filemtime( MB_DIR . '/assets/css/site.css' ) );
	wp_enqueue_script( 'mb-site', MB_URI . '/assets/js/site.js', array(), MB_VER . '.' . (int) @filemtime( MB_DIR . '/assets/js/site.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script( 'mb-site', 'MB', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'mb_lead' ),
	) );
	// Core block CSS is not needed on landing templates built from our own markup.
	if ( is_page_template() || is_front_page() || is_404() ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'global-styles' );
	}
}, 20 );

add_action( 'wp_head', function () {
	// Enable reveal animations only when JS runs, so crawlers and no-JS users see all content.
	echo "<script>document.documentElement.classList.add('js')</script>\n";
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( MB_URI . '/assets/fonts/Vazirmatn-var.woff2' )
	);
}, 1 );

/* ---------------------------------------------------------------------------
 * Head cleanup (Rank Math owns titles, descriptions, canonicals and schema)
 * ------------------------------------------------------------------------- */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'the_generator', '__return_empty_string' );

/* The site is Persian regardless of the admin UI language: always RTL + fa-IR. */
add_filter( 'language_attributes', function ( $out ) {
	$out = preg_replace( '/\s*(lang|dir)="[^"]*"/', '', $out );
	return trim( 'lang="fa-IR" dir="rtl" ' . $out );
} );

/* Excerpts */
add_filter( 'excerpt_length', fn() => 28 );
add_filter( 'excerpt_more', fn() => '…' );
