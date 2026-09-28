<?php
/**
 * Plugin Name: MB Migration (موقت)
 * Description: ابزار یک‌باره‌ی مهاجرت به قالب جدید: خروجی محتوای المنتور، ورود محتوای تمیز، تنظیم قالب‌ها، و بازگردانی. بعد از مهاجرت حذف شود.
 * Version: 1.0.0
 * Requires PHP: 7.4
 *
 * Steps (Tools → مهاجرت قالب):
 *  1. Export  — download rendered HTML of every Elementor-built post/page (Elementor must still be active).
 *  2. (offline) python3 migration/elementor_to_html.py export.json cleaned.json   ← integrity-checked
 *  3. Import  — upload cleaned.json; original content is backed up in post meta first.
 *  4. Setup   — assign templates, front/blog pages, create new pages, flush permalinks.
 *  R. Rollback — restore every backed-up post exactly as it was.
 * Also available as WP-CLI: wp mb-migrate export|import <file>|setup|rollback
 */

defined( 'ABSPATH' ) || exit;

const MB_MIG_BACKUP = '_mb_backup_content';
const MB_MIG_TYPES  = array( 'post', 'page', 'portfolio', 'faq' );

/* ------------------------------------------------------------------ core ops */

function mb_mig_elementor_ids() {
	return get_posts( array(
		'post_type'      => MB_MIG_TYPES,
		'post_status'    => array( 'publish', 'private', 'draft', 'future' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_elementor_edit_mode',
		'meta_value'     => 'builder',
	) );
}

function mb_mig_export() {
	@ini_set( 'memory_limit', '512M' ); // phpcs:ignore
	@set_time_limit( 300 ); // phpcs:ignore
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return new WP_Error( 'mb', 'المنتور غیرفعال است؛ خروجی باید قبل از غیرفعال کردن المنتور گرفته شود.' );
	}
	$out = array();
	foreach ( mb_mig_elementor_ids() as $id ) {
		$html  = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, false );
		$out[] = array(
			'id'      => $id,
			'type'    => get_post_type( $id ),
			'slug'    => urldecode( get_post_field( 'post_name', $id ) ),
			'content' => $html,
		);
	}
	return $out;
}

function mb_mig_import( array $cleaned ) {
	$done = 0;
	foreach ( $cleaned as $id => $html ) {
		$id = (int) $id;
		if ( ! $id || ! get_post( $id ) || ! is_string( $html ) || '' === trim( $html ) ) {
			continue;
		}
		if ( '' === get_post_meta( $id, MB_MIG_BACKUP, true ) ) {
			add_post_meta( $id, MB_MIG_BACKUP, wp_slash( wp_json_encode( array(
				'post_content'        => get_post_field( 'post_content', $id ),
				'_elementor_edit_mode' => get_post_meta( $id, '_elementor_edit_mode', true ),
				'_wp_page_template'   => get_post_meta( $id, '_wp_page_template', true ),
			), JSON_UNESCAPED_UNICODE ) ), true );
		}
		// Keep post_modified unchanged: content is the same, only the markup is cleaner.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => $html ), array( 'ID' => $id ) );
		delete_post_meta( $id, '_elementor_edit_mode' );
		clean_post_cache( $id );
		$done++;
	}
	return $done;
}

function mb_mig_setup() {
	$log = array();
	$tpl = array(
		771  => 'page-templates/service-consulting.php', // /مشاوره-کسب-و-کار/
		100  => 'page-templates/about.php',              // /میلاد-بهرامی-مشاور-عارضه-یابی-توسعه/
		2702 => 'page-templates/book.php',               // /کتاب-سلطان-قیف-funnel-king/ (post)
		101  => 'default',                               // front page → front-page.php
		97   => 'default',                               // blog → home.php
	);
	foreach ( $tpl as $id => $file ) {
		if ( get_post( $id ) ) {
			update_post_meta( $id, '_wp_page_template', $file );
			$log[] = "#$id → $file";
		}
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', 101 );
	update_option( 'page_for_posts', 97 );

	$new = array(
		'ارزیابی-سازمان'      => array( 'ارزیابی سیستم و فرآیند سازمان', 'page-templates/assessment.php' ),
		'سلطان-قیف-فصل-اول'   => array( 'فصل اول رایگان کتاب سلطان قیف: DNA تفکر استراتژیک', 'page-templates/chapter.php' ),
	);
	foreach ( $new as $slug => $def ) {
		$existing = get_page_by_path( $slug );
		$id       = $existing ? $existing->ID : wp_insert_post( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $def[0],
			'post_name'   => $slug,
		) );
		update_post_meta( $id, '_wp_page_template', $def[1] );
		$log[] = ( $existing ? 'exists' : 'created' ) . " /$slug/ → {$def[1]}";
	}
	flush_rewrite_rules();
	$log[] = 'permalinks flushed';
	return $log;
}

function mb_mig_rollback() {
	$ids = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => MB_MIG_BACKUP ) );
	global $wpdb;
	foreach ( $ids as $id ) {
		$b = json_decode( get_post_meta( $id, MB_MIG_BACKUP, true ), true );
		if ( ! $b ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $b['post_content'] ), array( 'ID' => $id ) );
		if ( $b['_elementor_edit_mode'] ) {
			update_post_meta( $id, '_elementor_edit_mode', $b['_elementor_edit_mode'] );
		}
		update_post_meta( $id, '_wp_page_template', $b['_wp_page_template'] ?: 'default' );
		delete_post_meta( $id, MB_MIG_BACKUP );
		clean_post_cache( $id );
	}
	return count( $ids );
}

/* ------------------------------------------------------------------ admin UI */

add_action( 'admin_menu', function () {
	add_management_page( 'مهاجرت قالب', 'مهاجرت قالب', 'manage_options', 'mb-migration', 'mb_mig_page' );
} );

add_action( 'admin_post_mb_mig', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'forbidden', 403 );
	}
	check_admin_referer( 'mb_mig' );
	$step = sanitize_key( $_POST['step'] ?? '' );
	$msg  = '';
	if ( 'export' === $step ) {
		$data = mb_mig_export();
		if ( is_wp_error( $data ) ) {
			$msg = $data->get_error_message();
		} else {
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=elementor-export-' . gmdate( 'Ymd-His' ) . '.json' );
			echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
			exit;
		}
	} elseif ( 'export_file' === $step ) {
			$data = mb_mig_export();
			if ( is_wp_error( $data ) ) {
				$msg = $data->get_error_message();
			} else {
				$file = WP_CONTENT_DIR . '/mb-export-' . wp_generate_password( 16, false ) . '.json';
				file_put_contents( $file, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore
				$msg = sprintf( '%d items saved to %s (delete after download).', count( $data ), basename( $file ) );
			}
		} elseif ( 'import_file' === $step ) {
			$file = WP_CONTENT_DIR . '/mb-cleaned.json';
			$json = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : null; // phpcs:ignore
			$msg  = is_array( $json ) ? sprintf( '%d posts updated (previous version of each is backed up).', mb_mig_import( $json ) ) : 'mb-cleaned.json missing or invalid.';
		} elseif ( 'import' === $step && ! empty( $_FILES['cleaned']['tmp_name'] ) ) {
		$json = json_decode( file_get_contents( $_FILES['cleaned']['tmp_name'] ), true ); // phpcs:ignore
		$msg  = is_array( $json ) ? sprintf( '%d مطلب به‌روز شد (نسخه‌ی قبلی هر کدام ذخیره شد).', mb_mig_import( $json ) ) : 'فایل JSON نامعتبر است.';
	} elseif ( 'setup' === $step ) {
		$msg = implode( ' · ', mb_mig_setup() );
	} elseif ( 'rollback' === $step ) {
		$msg = sprintf( '%d مطلب به حالت قبل برگشت.', mb_mig_rollback() );
	}
	set_transient( 'mb_mig_msg', $msg, 60 );
	wp_safe_redirect( admin_url( 'tools.php?page=mb-migration' ) );
	exit;
} );

function mb_mig_page() {
	$msg     = get_transient( 'mb_mig_msg' );
	$pending = count( mb_mig_elementor_ids() );
	delete_transient( 'mb_mig_msg' );
	$form = function ( $step, $label, $extra = '', $class = 'button-primary' ) {
		printf(
			'<form method="post" action="%s" enctype="multipart/form-data" style="margin:12px 0">%s<input type="hidden" name="action" value="mb_mig"><input type="hidden" name="step" value="%s">%s <button class="button %s">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ), wp_nonce_field( 'mb_mig', '_wpnonce', true, false ), esc_attr( $step ), $extra, esc_attr( $class ), esc_html( $label )
		);
	};
	echo '<div class="wrap"><h1>مهاجرت به قالب جدید</h1>';
	if ( $msg ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>';
	}
	echo '<p>مطالب ساخته‌شده با المنتور که هنوز تبدیل نشده‌اند: <b>' . (int) $pending . '</b></p>';
	echo '<h2>۱. خروجی</h2><p>المنتور باید فعال باشد.</p>';
	$form( 'export', 'دانلود خروجی المنتور (JSON)' );
	$form( 'export_file', 'Save export on server (for FTP download)', '', 'button-secondary' );
	echo '<h2>۳. ورود محتوای تمیز</h2>';
	$form( 'import', 'بارگذاری cleaned.json', '<input type="file" name="cleaned" accept=".json" required>' );
	$form( 'import_file', 'Import from wp-content/mb-cleaned.json (FTP upload)', '', 'button-secondary' );
	echo '<h2>۴. تنظیم قالب‌ها و صفحات</h2>';
	$form( 'setup', 'اعمال تنظیمات' );
	echo '<h2>بازگردانی</h2><p>همه‌ی مطالب تبدیل‌شده را دقیقاً به حالت قبل برمی‌گرداند.</p>';
	$form( 'rollback', 'بازگردانی کامل', '', 'button-secondary' );
	echo '</div>';
}

/* ------------------------------------------------------------------ WP-CLI */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mb-migrate', function ( $args ) {
		switch ( $args[0] ?? '' ) {
			case 'export':
				$d = mb_mig_export();
				is_wp_error( $d ) ? WP_CLI::error( $d->get_error_message() ) : WP_CLI::line( wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) );
				break;
			case 'import':
				WP_CLI::success( mb_mig_import( json_decode( file_get_contents( $args[1] ), true ) ) . ' updated' ); // phpcs:ignore
				break;
			case 'setup':
				foreach ( mb_mig_setup() as $l ) {
					WP_CLI::line( $l );
				}
				break;
			case 'rollback':
				WP_CLI::success( mb_mig_rollback() . ' restored' );
				break;
			default:
				WP_CLI::error( 'usage: wp mb-migrate export|import <file>|setup|rollback' );
		}
	} );
}
