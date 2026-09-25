<?php
/**
 * Home: knowledge clusters (3 columns). Category slugs are created during
 * migration; until they exist each column falls back to recent posts.
 */
$clusters = array(
	array( 'systemization', 'سیستم‌سازی', 'خوشه‌ی A' ),
	array( 'process-bpms', 'فرآیند و BPMS', 'خوشه‌ی B' ),
	array( 'erp-sales', 'ERP و فروش', 'خوشه‌ی C' ),
);
$seen = array();
?>
<div class="kn">
<?php foreach ( $clusters as $i => $c ) :
	$posts = mb_cluster_posts( $c[0], 4, $seen );
	$seen  = array_merge( $seen, wp_list_pluck( $posts, 'ID' ) );
	$cat   = get_category_by_slug( $c[0] );
	?>
	<div class="kcol" data-reveal<?php echo $i ? ' style="--d:' . (int) ( $i * 80 ) . 'ms"' : ''; ?>>
		<div class="hd"><h3><?php echo esc_html( $cat ? $cat->name : $c[1] ); ?></h3><span class="n"><?php echo esc_html( $c[2] ); ?></span></div>
		<ul>
		<?php foreach ( $posts as $p ) : ?>
			<li><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?><?php echo mb_arrow(); // phpcs:ignore ?></a></li>
		<?php endforeach; ?>
		</ul>
	</div>
<?php endforeach; ?>
</div>
