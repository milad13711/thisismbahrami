<?php
/**
 * Entity SEO. Rank Math owns titles/descriptions/canonicals; the theme only
 * contributes the Person entity so "میلاد بهرامی" is unambiguous to Google.
 */

defined( 'ABSPATH' ) || exit;

function mb_person_schema() {
	return array(
		'@type'         => 'Person',
		'@id'           => home_url( '/#person' ),
		'name'          => 'میلاد بهرامی',
		'alternateName' => array( 'محمدامین بهرامی', 'Milad Bahrami', 'Mohammad Amin Bahrami' ),
		'url'           => home_url( '/' ),
		'image'         => mb_img( 'portrait-900.jpg' ),
		'jobTitle'      => 'مشاور کسب‌وکار، سیستم‌سازی و تحول سازمانی',
		'worksFor'      => array( '@type' => 'Organization', 'name' => 'اکسیر تجارت امین', 'url' => 'https://eta.co.ir' ),
		'knowsAbout'    => array( 'سیستم‌سازی کسب‌وکار', 'معماری فرآیند', 'BPMS', 'ERP', 'قیف فروش', 'مشاوره کسب‌وکار', 'تحول سازمانی' ),
		'alumniOf'      => array( '@type' => 'CollegeOrUniversity', 'name' => 'دانشگاه فردوسی مشهد' ),
		'address'       => array( '@type' => 'PostalAddress', 'addressLocality' => 'شیراز', 'addressCountry' => 'IR' ),
		'telephone'     => '+989908008011',
		'sameAs'        => array( 'https://www.linkedin.com/in/thisismbahrami/', 'https://instagram.com/thisismbahrami', 'https://funnelking.ir', 'https://eta.co.ir' ),
	);
}

/** Local business entity for "مشاور کسب‌وکار در شیراز" (NAP must match the footer exactly). */
function mb_service_schema() {
	return array(
		'@type'      => 'ProfessionalService',
		'@id'        => home_url( '/#service' ),
		'name'       => 'میلاد بهرامی — مشاوره کسب‌وکار و سیستم‌سازی',
		'url'        => home_url( '/' ),
		'image'      => mb_img( 'portrait-900.jpg' ),
		'telephone'  => '+989908008011',
		'priceRange' => '۲٬۰۰۰٬۰۰۰ – ۳٬۰۰۰٬۰۰۰ تومان',
		'address'    => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => 'حدفاصل تپه تلویزیون و میدان ارم، ساختمان مدیریت دانشگاه شیراز، طبقه ۸، آفیس ۸۰۶',
			'addressLocality' => 'شیراز',
			'addressRegion'   => 'فارس',
			'addressCountry'  => 'IR',
		),
		'founder'    => array( '@id' => home_url( '/#person' ) ),
		'areaServed' => array( 'شیراز', 'ایران' ),
	);
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( is_front_page() || is_page_template( 'page-templates/about.php' ) ) {
		$data['mbPerson']  = mb_person_schema();
		$data['mbService'] = mb_service_schema();
	}
	return $data;
}, 99 );

add_action( 'wp_head', function () {
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return; // Emitted through Rank Math's graph instead.
	}
	if ( is_front_page() || is_page_template( 'page-templates/about.php' ) ) {
		$graph = array( '@context' => 'https://schema.org', '@graph' => array( mb_person_schema(), mb_service_schema() ) );
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
}, 20 );
