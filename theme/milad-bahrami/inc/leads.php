<?php
/**
 * Assessment form → private "lead" records + e-mail notification.
 * No third-party form plugin needed.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_post_type( 'mb_lead', array(
		'labels'          => array( 'name' => 'درخواست‌های ارزیابی', 'singular_name' => 'درخواست ارزیابی' ),
		'public'          => false,
		'show_ui'         => true,
		'menu_icon'       => 'dashicons-clipboard',
		'supports'        => array( 'title', 'editor' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

add_action( 'wp_ajax_mb_lead', 'mb_handle_lead' );
add_action( 'wp_ajax_nopriv_mb_lead', 'mb_handle_lead' );

function mb_handle_lead() {
	if ( ! check_ajax_referer( 'mb_lead', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'نشست منقضی شده؛ صفحه را تازه کنید.' ), 403 );
	}
	// Honeypot: real users never fill this hidden field.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success();
	}
	// Basic rate limit: 5 submissions per IP per hour.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'mb_lead_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		wp_send_json_error( array( 'message' => 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.' ), 429 );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$f = function ( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
	};
	$org  = $f( 'org' );
	$name = $f( 'name' );
	$tel  = preg_replace( '/[^\d+]/', '', strtr( $f( 'tel' ), array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) ) );
	if ( ! $org || ! $name || strlen( $tel ) < 10 ) {
		wp_send_json_error( array( 'message' => 'نام سازمان، نام و شماره موبایل لازم است.' ), 422 );
	}
	$rows = array(
		'سازمان'     => $org,
		'صنعت'       => $f( 'industry' ),
		'تعداد نیرو' => $f( 'size' ),
		'چالش‌ها'    => $f( 'challenges' ),
		'هدف'        => $f( 'goal' ),
		'توضیح'      => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
		'نام'        => $name,
		'سمت'        => $f( 'role' ),
		'موبایل'     => $tel,
		'صفحه'       => esc_url_raw( wp_get_referer() ),
	);
	$body = '';
	foreach ( $rows as $label => $val ) {
		$body .= $label . ': ' . $val . "\n";
	}
	$id = wp_insert_post( array(
		'post_type'    => 'mb_lead',
		'post_status'  => 'private',
		'post_title'   => $org . ' — ' . $name,
		'post_content' => $body,
	) );
	wp_mail( get_option( 'admin_email' ), 'درخواست ارزیابی جدید: ' . $org, $body );
	do_action( 'mb_lead_created', $id, $rows ); // Hook for SMS / CRM (e.g. Exir ERP) later.
	wp_send_json_success();
}
