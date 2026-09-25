<?php
/**
 * Generic page (legal consulting, customer club, events, …).
 * Service pages use page-templates/service.php; landings use their own templates.
 */
get_header();
the_post();
?>
<section class="phero on-dark" style="padding-bottom:56px">
	<div class="wrap" style="max-width:1040px">
		<div class="rise">
			<?php mb_breadcrumbs(); ?>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		</div>
	</div>
</section>
<section class="sec" style="padding-top:56px">
	<div class="wrap" style="max-width:820px">
		<div class="prose" style="max-width:none"><?php the_content(); ?></div>
	</div>
</section>
<section class="sec" style="padding-top:0;padding-bottom:0">
	<div class="wrap">
		<div class="cta-band on-dark" data-reveal>
			<div><h2>بیایید درباره‌ی کسب‌وکار شما صحبت کنیم</h2><p>اولین قدم یک جلسه‌ی تشخیص است.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'assessment' ) ); ?>">ارزیابی سازمان</a></div>
		</div>
	</div>
</section>
<?php
get_footer();
