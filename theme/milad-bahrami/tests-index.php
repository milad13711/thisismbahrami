<?php
/** /tests/ — index of all tests (registry: inc/tests/*.php). */
get_header();
$tests = mb_tests();
?>
<section class="phero on-dark">
	<div class="wrap">
		<div class="rise" style="max-width:760px">
			<ol class="crumbs" aria-label="مسیر صفحه"><li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a></li><li aria-current="page">تست‌ها</li></ol>
			<h1>تست‌های <span class="grad">رایگان</span> شغلی، شخصیت و کسب‌وکار</h1>
			<p class="lead">ابزارهای کاملاً رایگان؛ بدون ثبت‌نام و بدون پرداخت برای تصمیم‌گیری بهتر درباره‌ی رشته، شغل و مسیر کسب‌وکار؛ هر تست با تفسیر کامل و قدم‌های بعدی.</p>
			<div class="chips" style="margin-top:22px"><span class="chip">رایگان</span><span class="chip">بدون ثبت‌نام</span><span class="chip">اطلاعات شما ذخیره نمی‌شود</span></div>
		</div>
	</div>
</section>
<section class="sec">
	<div class="wrap">
		<div class="grid-3 tcards">
			<?php foreach ( $tests as $t ) : ?>
				<a class="card tcard" href="<?php echo esc_url( mb_test_url( $t['slug'] ) ); ?>" data-reveal>
					<span class="temoji" aria-hidden="true"><?php echo esc_html( $t['emoji'] ); ?></span>
					<h2 class="h3"><?php echo esc_html( $t['name'] ); ?></h2>
					<p><?php echo esc_html( $t['tagline'] ); ?></p>
					<div class="chips"><span class="chip gold-l">رایگان</span><span class="chip light"><?php echo esc_html( mb_fa_digits( $t['minutes'] ) ); ?> دقیقه</span><span class="chip light"><?php echo esc_html( mb_fa_digits( $t['count'] ) ); ?> سؤال</span></div>
					<span class="tgo">شروع تست <?php echo mb_arrow(); ?></span>
				</a>
			<?php endforeach; ?>
			
		</div>
	</div>
</section>
<?php get_footer(); ?>
