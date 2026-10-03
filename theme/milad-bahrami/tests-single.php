<?php
/** /tests/{slug}/ — one test: hero + engine mount (assets/js/tests-engine.js) + SEO content + FAQ. */
get_header();
$t    = mb_current_test();
$file = MB_DIR . '/assets/tests/' . $t['data'] . '.json';
$json = is_readable( $file ) ? file_get_contents( $file ) : '{}';
$pm   = '<span class="pm" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>';
$others = array_filter( mb_tests(), fn( $o ) => $o['slug'] !== $t['slug'] );
?>
<section class="phero on-dark tphero">
	<div class="wrap">
		<div class="rise" style="max-width:780px">
			<ol class="crumbs" aria-label="مسیر صفحه"><li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a></li><li><a href="<?php echo esc_url( mb_test_url() ); ?>">تست‌ها</a></li><li aria-current="page"><?php echo esc_html( $t['short'] ); ?></li></ol>
			<h1><?php echo esc_html( $t['name'] ); ?> رایگان: <span class="grad"><?php echo esc_html( $t['h1_tail'] ?? $t['tagline'] ); ?></span></h1>
			<?php foreach ( $t['intro'] as $p ) : ?><p class="lead"><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
			<div class="chips" style="margin-top:22px"><span class="chip gold">رایگان و کامل</span><span class="chip"><?php echo esc_html( mb_fa_digits( $t['minutes'] ) ); ?> دقیقه</span><span class="chip"><?php echo esc_html( mb_fa_digits( $t['count'] ) ); ?> سؤال</span><span class="chip">بدون ثبت‌نام</span></div>
		</div>
	</div>
</section>

<section class="sec tsec">
	<div class="wrap">
		<div class="tapp" id="tapp" data-test="<?php echo esc_attr( $t['slug'] ); ?>" data-renderer="<?php echo esc_attr( $t['renderer'] ); ?>" data-cta="<?php echo esc_url( mb_link( 'service' ) ); ?>" aria-live="polite">
			<noscript><p class="callout">برای انجام این تست به جاوااسکریپت نیاز است. لطفاً آن را در مرورگر فعال کنید.</p></noscript>
			<div class="tloading">در حال آماده‌سازی تست…</div>
		</div>
		<script type="application/json" id="tdata"><?php echo str_replace( '</', '<\/', $json ); ?></script>
	</div>
</section>

<section class="sec band">
	<div class="wrap">
		<div class="sec-head" data-reveal><span class="eyebrow">چطور کار می‌کند؟</span><h2 class="h2">سه قدم تا کارنامه‌ی کامل</h2></div>
		<div class="grid-3">
			<?php foreach ( $t['steps'] as $i => $s ) : ?>
				<div class="card" data-reveal style="--d:<?php echo (int) ( $i * 70 ); ?>ms"><span class="tstep" aria-hidden="true"><?php echo esc_html( $s[0] ); ?></span><h3><?php echo esc_html( $s[1] ); ?></h3><p><?php echo esc_html( $s[2] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="sec">
	<div class="wrap">
		<div class="prose" style="max-width:780px;margin:0 auto">
			<?php foreach ( $t['about'] as $b ) : ?><h2><?php echo esc_html( $b[0] ); ?></h2><p><?php echo esc_html( $b[1] ); ?></p><?php endforeach; ?>
		</div>
	</div>
</section>

<section class="sec band">
	<div class="wrap faq">
		<div class="sec-head" data-reveal><span class="eyebrow">سوالات متداول</span><h2 class="h2">درباره‌ی این تست بیشتر بدانید</h2></div>
		<div data-reveal>
			<?php foreach ( $t['faq'] as $q ) : ?>
				<details><summary><?php echo esc_html( $q[0] ); ?><?php echo $pm; // phpcs:ignore ?></summary><p style="padding:0 0 22px;margin:0;color:var(--text-2)"><?php echo esc_html( $q[1] ); ?></p></details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php if ( $others ) : ?>
<section class="sec"><div class="wrap">
	<div class="sec-head" data-reveal><h2 class="h2">تست‌های دیگر</h2></div>
	<div class="grid-3 tcards">
		<?php foreach ( $others as $o ) : ?><a class="card tcard" href="<?php echo esc_url( mb_test_url( $o['slug'] ) ); ?>"><span class="temoji"><?php echo esc_html( $o['emoji'] ); ?></span><h3><?php echo esc_html( $o['name'] ); ?></h3><p><?php echo esc_html( $o['tagline'] ); ?></p></a><?php endforeach; ?>
	</div>
</div></section>
<?php endif; ?>
<?php get_footer(); ?>
