<?php
/**
 * Sends each assessment request to Exir ERP as a public form submission
 * (POST /public/forms/{tenant}/{form}/submit). The ERP form creates/links the CRM
 * contact and an automation rule notifies the owner. Only public identifiers are
 * used here — no API key or secret ever lives on this host.
 *
 * Configure in wp-config.php (or leave unset to disable):
 *   define( 'MB_ERP_API',    'https://exirerp.ir/api' );
 *   define( 'MB_ERP_TENANT', '<tenant slug>' );
 *   define( 'MB_ERP_FORM',   '<form slug>' );
 *   define( 'MB_ERP_FIELD',  '<id of the "details" text field>' );
 */

defined( 'ABSPATH' ) || exit;

// Public identifiers of the "ارزیابی سازمان (سایت)" form in Milad's Exir ERP tenant (override in wp-config.php if needed).
defined( 'MB_ERP_API' ) || define( 'MB_ERP_API', 'https://exirerp.ir/api' );
defined( 'MB_ERP_TENANT' ) || define( 'MB_ERP_TENANT', 't7debc5f8d8e' );
defined( 'MB_ERP_FORM' ) || define( 'MB_ERP_FORM', 'site-assessment' );
defined( 'MB_ERP_FIELD' ) || define( 'MB_ERP_FIELD', '7a68504e-8f19-42c1-bfe6-a8d72596b02f' );

add_action( 'mb_lead_created', function ( $post_id, $rows ) {
	foreach ( array( 'MB_ERP_API', 'MB_ERP_TENANT', 'MB_ERP_FORM', 'MB_ERP_FIELD' ) as $c ) {
		if ( ! defined( $c ) || ! constant( $c ) ) {
			return;
		}
	}
	$details = '';
	foreach ( $rows as $label => $val ) {
		if ( 'نام' !== $label && 'موبایل' !== $label && '' !== $val ) {
			$details .= $label . ': ' . $val . "\n";
		}
	}
	$url = untrailingslashit( MB_ERP_API ) . '/public/forms/' . rawurlencode( MB_ERP_TENANT ) . '/' . rawurlencode( MB_ERP_FORM ) . '/submit';
	$res = wp_remote_post( $url, array(
		'timeout' => 8,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'respondentName'  => $rows['نام'] ?? '',
			'respondentPhone' => $rows['موبایل'] ?? '',
			'answers'         => array( array( 'fieldId' => MB_ERP_FIELD, 'valueText' => trim( $details ) ) ),
		), JSON_UNESCAPED_UNICODE ),
	) );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	// Never lose the lead: the private post + e-mail already exist; just record the sync result.
	update_post_meta( $post_id, '_mb_erp_sync', $code >= 200 && $code < 300 ? 'ok' : 'failed:' . ( is_wp_error( $res ) ? $res->get_error_message() : $code ) );
}, 10, 2 );
