<?php
/* Single FAQ answer, e.g. /faq/مشاوره-کسب-و-کار-از-صفر-تا-100/ */
get_header();
the_post();
?>
<section class="phero on-dark" style="padding-bottom:56px">
	<div class="wrap" style="max-width:1040px">
		<div class="rise">
			<?php mb_breadcrumbs(); ?>
			<h1><?php the_title(); ?></h1>
		</div>
	</div>
</section>
<section class="sec" style="padding-top:56px">
	<div class="wrap" style="max-width:820px">
		<div class="prose"><?php the_content(); ?></div>
		<?php mb_author_box(); ?>
		<p style="margin-top:28px"><a class="btn btn-line" href="<?php echo esc_url( mb_link( 'faq' ) ); ?>">همه‌ی سوالات متداول</a></p>
	</div>
</section>
<section class="sec" style="padding-top:0;padding-bottom:0">
	<div class="wrap">
		<div class="cta-band on-dark" data-reveal>
			<div><h2>سؤال بعدی‌تان را مستقیم بپرسید</h2><p>در جلسه‌ی مشاوره، پاسخ مخصوص سازمان خودتان را می‌گیرید.</p></div>
			<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a></div>
		</div>
	</div>
</section>
<?php
get_footer();
