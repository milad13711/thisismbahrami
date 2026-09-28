<?php
/**
 * Portfolio + FAQ content types.
 *
 * The old Phlox theme's plugin registered these. We register them with the same
 * post type keys, taxonomy keys and rewrite slugs so every existing URL
 * (/portfolio/…, /faq/…) keeps resolving after Phlox is removed.
 * If the old plugin is still active, we don't re-register (avoids conflicts).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! post_type_exists( 'portfolio' ) ) {
		register_post_type( 'portfolio', array(
			'labels'       => array(
				'name'          => 'نمونه‌کارها',
				'singular_name' => 'نمونه‌کار',
				'add_new_item'  => 'افزودن نمونه‌کار',
				'edit_item'     => 'ویرایش نمونه‌کار',
			),
			'public'       => true,
			'has_archive'  => 'portfolio',
			'rewrite'      => array( 'slug' => 'portfolio', 'with_front' => false ),
			'menu_icon'    => 'dashicons-portfolio',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			'show_in_rest' => true,
		) );
		register_taxonomy( 'portfolio-cat', 'portfolio', array(
			'label'        => 'دسته‌ی نمونه‌کار',
			'hierarchical' => true,
			'show_in_rest' => true,
			'rewrite'      => array( 'slug' => 'portfolio-cat', 'with_front' => false ),
		) );
		register_taxonomy( 'portfolio-tag', 'portfolio', array( 'label' => 'برچسب نمونه‌کار', 'show_in_rest' => true, 'rewrite' => array( 'slug' => 'portfolio-tag', 'with_front' => false ) ) );
		register_taxonomy( 'portfolio-filter', 'portfolio', array( 'label' => 'فیلتر نمونه‌کار', 'hierarchical' => true, 'show_in_rest' => true, 'rewrite' => array( 'slug' => 'portfolio-filter', 'with_front' => false ) ) );
	}

	if ( ! post_type_exists( 'faq' ) ) {
		register_post_type( 'faq', array(
			'labels'       => array( 'name' => 'سوالات متداول', 'singular_name' => 'سوال' ),
			'public'       => true,
			// The live archive lives at /faq/all/ — see mb_faq_all_rewrite() below.
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'faq', 'with_front' => false ),
			'menu_icon'    => 'dashicons-editor-help',
			'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'show_in_rest' => true,
		) );
		register_taxonomy( 'faq-group', 'faq', array(
			'label'        => 'گروه سوال',
			'hierarchical' => true,
			'show_in_rest' => true,
			'rewrite'      => false,
		) );
	}

	// Case-study fields shown in the portfolio fact card.
	foreach ( mb_portfolio_fields() as $key => $label ) {
		register_post_meta( 'portfolio', $key, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
		) );
	}
} );

/**
 * /faq/all/ was the live FAQ index. Serve our FAQ archive template there.
 * NOTE: confirm against the live DB during migration — if "all" turns out to be a
 * real faq post/term, this rule is replaced by that object's own URL.
 */
add_action( 'init', function () {
	add_rewrite_rule( '^faq/all/?$', 'index.php?mb_faq_index=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'mb_faq_index';
	return $vars;
} );
add_filter( 'template_include', function ( $template ) {
	if ( get_query_var( 'mb_faq_index' ) ) {
		status_header( 200 );
		global $wp_query;
		$wp_query->is_404 = false;
		return locate_template( 'archive-faq.php' ) ?: $template;
	}
	return $template;
} );

/* Rank Math sees the /faq/all/ query as the posts index; give it its own signals. */
add_filter( 'rank_math/frontend/canonical', fn( $c ) => get_query_var( 'mb_faq_index' ) ? home_url( '/faq/all/' ) : $c );
add_filter( 'rank_math/frontend/title', fn( $t ) => get_query_var( 'mb_faq_index' ) ? 'سوالات متداول - ' . get_bloginfo( 'name' ) : $t );
add_filter( 'rank_math/frontend/description', fn( $d ) => get_query_var( 'mb_faq_index' ) ? 'پاسخ پرسش‌های پرتکرار درباره‌ی مشاوره کسب‌وکار، عارضه‌یابی و سیستم‌سازی سازمانی.' : $d );

function mb_portfolio_fields() {
	return array(
		'mb_client'       => 'کارفرما',
		'mb_industry'     => 'حوزه',
		'mb_service'      => 'خدمت',
		'mb_scope'        => 'دامنه',
		'mb_tools'        => 'ابزار',
		'mb_year'         => 'سال',
		'mb_metric'       => 'عدد شاخص (مثلاً ۲.۳×)',
		'mb_metric_label' => 'توضیح عدد شاخص',
		'mb_quote'        => 'نقل‌قول مشتری',
		'mb_quote_by'     => 'گوینده‌ی نقل‌قول',
	);
}

/* Simple meta box so fields are editable without extra plugins. */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'mb-case-facts', 'مشخصات پروژه (کارت کنار صفحه)', function ( $post ) {
		wp_nonce_field( 'mb_case_facts', 'mb_case_facts_nonce' );
		echo '<table class="form-table" role="presentation">';
		foreach ( mb_portfolio_fields() as $key => $label ) {
			$val = get_post_meta( $post->ID, $key, true );
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td><input class="widefat" id="%1$s" name="%1$s" value="%3$s"></td></tr>',
				esc_attr( $key ), esc_html( $label ), esc_attr( $val )
			);
		}
		echo '</table>';
	}, 'portfolio', 'normal', 'high' );
} );

add_action( 'save_post_portfolio', function ( $post_id ) {
	if ( ! isset( $_POST['mb_case_facts_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['mb_case_facts_nonce'] ), 'mb_case_facts' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array_keys( mb_portfolio_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
} );
