<?php
/**
 * Local test import: rebuild the live site's content (same IDs, slugs, titles,
 * categories) from the REST exports + cleaned HTML, then run the migration setup.
 * Runs inside WordPress Playground (see ../blueprint.json).
 */
$dir   = __DIR__;
$j     = fn( $f ) => json_decode( file_get_contents( "$dir/$f" ), true );
$clean = $j( 'cleaned.json' );
$raw   = array();
foreach ( $j( 'export-all.json' ) as $r ) {
	$raw[ $r['id'] ] = $r['content'];
}
$body = fn( $id ) => $clean[ (string) $id ] ?? ( $raw[ $id ] ?? '' );

// Categories (keep slugs; map old → new term IDs).
$catmap = array();
foreach ( $j( 'cats.json' ) as $c ) {
	$t = wp_insert_term( $c['name'], 'category', array( 'slug' => urldecode( $c['slug'] ), 'description' => $c['description'] ) );
	$catmap[ $c['id'] ] = is_wp_error( $t ) ? ( $t->get_error_data()['term_id'] ?? 1 ) : $t['term_id'];
}

$insert = function ( $p, $type, $extra = array() ) use ( $body ) {
	$id = wp_insert_post( array_merge( array(
		'import_id'    => $p['id'],
		'post_type'    => $type,
		'post_status'  => 'publish',
		'post_name'    => urldecode( $p['slug'] ),
		'post_title'   => html_entity_decode( $p['title']['rendered'] ),
		'post_excerpt' => wp_strip_all_tags( html_entity_decode( $p['excerpt']['rendered'] ?? '' ) ),
		'post_content' => $body( $p['id'] ),
		'post_date'    => $p['date'],
	), $extra ), true );
	if ( is_wp_error( $id ) ) {
		echo "ERR {$p['id']}: " . $id->get_error_message() . "\n";
	}
	return $id;
};

foreach ( $j( 'pages-meta.json' ) as $p ) {
	$insert( $p, 'page', array( 'post_parent' => $p['parent'], 'menu_order' => $p['menu_order'] ) );
}
foreach ( $j( 'posts-meta.json' ) as $p ) {
	$insert( $p, 'post', array( 'post_category' => array_map( fn( $c ) => $catmap[ $c ] ?? 1, $p['categories'] ) ) );
}
$pcat = wp_insert_term( 'مشاوره و منتورینگ', 'portfolio-cat', array( 'slug' => 'consult' ) );
foreach ( $j( 'portfolio.json' ) as $p ) {
	$id = $insert( $p, 'portfolio' );
	if ( ! is_wp_error( $id ) && ! is_wp_error( $pcat ) ) {
		wp_set_object_terms( $id, array( (int) $pcat['term_id'] ), 'portfolio-cat' );
	}
}

// FAQ: the one real live FAQ + a sample group.
$g  = wp_insert_term( 'شروع همکاری', 'faq-group' );
$fq = wp_insert_post( array(
	'post_type'    => 'faq',
	'post_status'  => 'publish',
	'post_name'    => 'مشاوره-کسب-و-کار-از-صفر-تا-100',
	'post_title'   => 'فرایند مشاوره کسب و کار سازمانی از 0 تا 100 چگونه است؟',
	'post_content' => '<p>مشاوره کسب و کار عموما مربوط به تمام افرادی می شود که می خواهند به صورت مستقل یا در قالب یک شرکت به کسب درآمد بپردازند.</p>',
) );
if ( ! is_wp_error( $g ) ) {
	wp_set_object_terms( $fq, array( (int) $g['term_id'] ), 'faq-group' );
}

update_option( 'permalink_structure', '/%postname%/' );
foreach ( mb_mig_setup() as $l ) {
	echo $l . "\n";
}
echo "posts: " . wp_count_posts()->publish . " pages: " . wp_count_posts( 'page' )->publish . " portfolio: " . wp_count_posts( 'portfolio' )->publish . "\n";
