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


/**
 * Featured images: for every published post without one, use the first local uploads image found in its
 * content (registering it in the media library when only the file exists, e.g. uploaded by FTP).
 * Never touches post content; only sets _thumbnail_id and the attachment's alt text.
 */
function mb_mig_autofeature() {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$up    = wp_get_upload_dir();
	$ids   = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) );
	$set   = 0;
	$skip  = array();
	foreach ( $ids as $id ) {
		$html = get_post_field( 'post_content', $id );
		if ( ! preg_match_all( '#<img[^>]+>#i', $html, $imgs ) ) {
			$skip[] = $id;
			continue;
		}
		$done = false;
		foreach ( $imgs[0] as $tag ) {
			if ( ! preg_match( '#src="([^"]+)"#i', $tag, $m ) ) {
				continue;
			}
			$url = html_entity_decode( $m[1] );
			$pos = strpos( $url, '/wp-content/uploads/' );
			if ( false === $pos ) {
				continue;
			}
			$alt = preg_match( '#alt="([^"]*)"#i', $tag, $a ) ? html_entity_decode( $a[1] ) : '';
			$att = attachment_url_to_postid( $url );
			if ( ! $att ) {
				$rel  = rawurldecode( substr( $url, $pos + strlen( '/wp-content/uploads/' ) ) );
				$rel  = preg_replace( '#-\d+x\d+(\.[a-z0-9]+)$#i', '$1', strtok( $rel, '?' ) );
				$file = trailingslashit( $up['basedir'] ) . $rel;
				if ( ! is_readable( $file ) ) {
					continue;
				}
				$type = wp_check_filetype( $file );
				$att  = wp_insert_attachment( array( 'post_mime_type' => $type['type'], 'post_title' => $alt ?: pathinfo( $file, PATHINFO_FILENAME ), 'post_status' => 'inherit' ), $file, $id );
				if ( is_wp_error( $att ) || ! $att ) {
					continue;
				}
				wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
			}
			if ( $alt && '' === get_post_meta( $att, '_wp_attachment_image_alt', true ) ) {
				update_post_meta( $att, '_wp_attachment_image_alt', wp_slash( $alt ) );
			}
			set_post_thumbnail( $id, $att );
			$set++;
			$done = true;
			break;
		}
		if ( ! $done ) {
			$skip[] = $id;
		}
	}
	return array( $set, $skip );
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
	} elseif ( 'autofeature' === $step ) {
		list( $n, $skip ) = mb_mig_autofeature();
		$msg = sprintf( 'featured image set on %d posts; no usable image in: %s', $n, $skip ? implode( ',', $skip ) : '—' );
	} elseif ( 'purge_author' === $step ) {
		// Incident cleanup: delete up to 150 posts of the intruder account per click (time-bounded).
		$uid  = (int) ( $_POST['uid'] ?? 0 );
		$user = $uid > 1 ? get_userdata( $uid ) : null;
		if ( ! $user || user_can( $user, 'manage_options' ) ) {
			$msg = 'refused: pick a non-admin author id other than 1';
		} else {
			@set_time_limit( 25 );
			$ids = get_posts( array( 'author' => $uid, 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => 150, 'fields' => 'ids', 'suppress_filters' => true ) );
			$t   = microtime( true );
			$n   = 0;
			foreach ( $ids as $pid ) {
				wp_delete_post( $pid, true );
				$n++;
				if ( microtime( true ) - $t > 18 ) {
					break;
				}
			}
			$left = (int) ( new WP_Query( array( 'author' => $uid, 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) ) )->found_posts;
			$msg  = sprintf( 'purged %d; remaining for user %d: %d', $n, $uid, $left );
		}
	} elseif ( 'verify_core' === $step ) {
		@set_time_limit( 60 );
		global $wp_version;
		$res = wp_remote_get( 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=fa_IR', array( 'timeout' => 20 ) );
		$j   = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $j['checksums'] ) ) {
			$j = json_decode( wp_remote_retrieve_body( wp_remote_get( 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=en_US', array( 'timeout' => 20 ) ) ), true );
		}
		$sums = $j['checksums'] ?? array();
		$bad  = array();
		$miss = array();
		foreach ( $sums as $f => $md5 ) {
			if ( 0 === strpos( $f, 'wp-content/' ) ) {
				continue;
			}
			$path = ABSPATH . $f;
			if ( ! is_file( $path ) ) {
				$miss[] = $f;
			} elseif ( md5_file( $path ) !== $md5 ) {
				$bad[] = $f;
			}
		}
		$extra = array();
		foreach ( array( 'wp-admin', 'wp-includes' ) as $d ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ABSPATH . $d, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $fi ) {
				$rel = str_replace( ABSPATH, '', $fi->getPathname() );
				if ( ! isset( $sums[ $rel ] ) && preg_match( '/\.(php|phtml|phar|inc)$/i', $rel ) ) {
					$extra[] = $rel;
				}
			}
		}
		foreach ( glob( ABSPATH . '*.php' ) as $f ) {
			$rel = basename( $f );
			if ( ! isset( $sums[ $rel ] ) && 'wp-config.php' !== $rel ) {
				$extra[] = $rel;
			}
		}
		$up = array();
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WP_CONTENT_DIR . '/uploads', FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $fi ) {
			if ( preg_match( '/\.(php|phtml|phar|php[0-9])$/i', $fi->getFilename() ) ) {
				$up[] = str_replace( ABSPATH, '', $fi->getPathname() );
			}
		}
		$msg = sprintf( 'checked %d | modified: %s | missing: %s | extra php: %s | php in uploads: %s', count( $sums ), $bad ? implode( ',', array_slice( $bad, 0, 15 ) ) : 'none', $miss ? implode( ',', array_slice( $miss, 0, 10 ) ) : 'none', $extra ? implode( ',', array_slice( $extra, 0, 15 ) ) : 'none', $up ? implode( ',', array_slice( $up, 0, 15 ) ) : 'none' );
	} elseif ( 'scan_malware' === $step ) {
		@set_time_limit( 120 );
		$needles = array( 'content-sync-connector', 'CSC_Watchdog', 'WP_Runtime_Guard', 'recovery.zip', 'wp-c4624f', 'cachelayer.top', 'Recovery Channel', 'wp-assets-guard', 'runtime-guard-stub', 'watchdog-stub', 'Y2FjaGVsYXllci50b3A' );
		$hits    = array();
		foreach ( array( WP_CONTENT_DIR . '/plugins', WP_CONTENT_DIR . '/themes', WP_CONTENT_DIR . '/uploads', WP_CONTENT_DIR . '/languages', WP_CONTENT_DIR . '/upgrade' ) as $root ) {
			if ( ! is_dir( $root ) ) {
				continue;
			}
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $fi ) {
				if ( ! preg_match( '/\.(php|phtml|inc|ini|json)$/i', $fi->getFilename() ) || $fi->getSize() > 2000000 ) {
					continue;
				}
				$c = @file_get_contents( $fi->getPathname() );
				foreach ( $needles as $nd ) {
					if ( false !== $c && false !== strpos( $c, $nd ) ) {
						$hits[] = str_replace( WP_CONTENT_DIR . '/', '', $fi->getPathname() ) . ' [' . $nd . ']';
						break;
					}
				}
			}
		}
		global $wpdb;
		$opts = array();
		foreach ( array( 'content-sync', 'csc_', 'wp-c4624f', 'cachelayer', 'runtime_guard', 'wlh_', 'recovery' ) as $nd ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s LIMIT 10", '%' . $wpdb->esc_like( $nd ) . '%', '%' . $wpdb->esc_like( $nd ) . '%' ) ) as $o ) {
				$opts[ $o ] = 1;
			}
		}
		$cron = array();
		foreach ( (array) _get_cron_array() as $ts => $hooks ) {
			foreach ( $hooks as $hook => $_ ) {
				$cron[ $hook ] = 1;
			}
		}
		$msg = 'MALWARE SCAN | files: ' . ( $hits ? implode( ' ; ', array_slice( $hits, 0, 25 ) ) : 'none' ) . ' | options: ' . ( $opts ? implode( ',', array_slice( array_keys( $opts ), 0, 25 ) ) : 'none' ) . ' | cron hooks: ' . implode( ',', array_slice( array_keys( $cron ), 0, 60 ) );
	} elseif ( 'clean_db' === $step ) {
		global $wpdb;
		$del = 0;
		foreach ( array( 'csc\_%', 'wlh\_%', 'wp\_runtime\_guard%' ) as $like ) {
			$del += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}
		$ap = (array) get_option( 'active_plugins', array() );
		$ap = array_values( array_filter( $ap, function ( $x ) {
			return 0 !== strpos( $x, 'content-sync-connector' ) && 0 !== strpos( $x, 'wp-file-manager' ) && 0 !== strpos( $x, 'webp-uploads-manager' );
		} ) );
		update_option( 'active_plugins', $ap );
		foreach ( array( 'csc_guardian_healthcheck', 'csc_geogate_refresh' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		$msg = sprintf( 'db cleaned: %d options removed; active plugins now: %s', $del, implode( ',', $ap ) );
	} elseif ( 'purge_fast' === $step ) {
		// Incident cleanup: bulk-remove an intruder (non-admin) account's posts directly in SQL, in 2000-post chunks.
		global $wpdb;
		$uid  = (int) ( $_POST['uid'] ?? 0 );
		$user = $uid > 2 ? get_userdata( $uid ) : null;
		if ( ! $user || user_can( $user, 'manage_options' ) ) {
			$msg = 'refused: pick a non-admin author id above 2';
		} else {
			@set_time_limit( 40 );
			$t    = microtime( true );
			$gone = 0;
			while ( microtime( true ) - $t < 22 ) {
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d LIMIT 2000", $uid ) );
				if ( ! $ids ) {
					break;
				}
				$in = implode( ',', array_map( 'intval', $ids ) );
				$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)" );
				$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($in)" );
				$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ($in)" );
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($in)" );
				$gone += count( $ids );
			}
			$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d", $uid ) );
			$msg  = sprintf( 'fast purge: removed %d; remaining for user %d: %d', $gone, $uid, $left );
		}
	} elseif ( 'flush_sitemap' === $step ) {
		$ok = false;
		if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
			$ok = true;
		}
		do_action( 'rank_math/sitemap/clear_cache' );
		$msg = $ok ? 'sitemap cache flushed' : 'Rank Math cache class not found';
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
	echo '<h2>تصویر شاخص</h2><p>برای مطالب بدون تصویر شاخص، اولین تصویر داخل متن را انتخاب می‌کند.</p>';
	$form( 'autofeature', 'تنظیم خودکار تصویر شاخص' );
	$form( 'purge_author', 'حذف دسته‌ای نوشته‌های کاربر نفوذی', '<input type="number" name="uid" value="6" style="width:80px">', 'button-secondary' );
	$form( 'verify_core', 'تأیید سلامت فایل‌های هسته', '', 'button-secondary' );
	$form( 'scan_malware', 'اسکن بدافزار (فایل و دیتابیس)', '', 'button-secondary' );
	$form( 'clean_db', 'پاک‌سازی ردپای بدافزار در دیتابیس', '', 'button-secondary' );
	$form( 'purge_fast', 'حذف سریع نوشته‌های کاربر نفوذی (SQL)', '<input type="number" name="uid" value="6" style="width:80px">', 'button-secondary' );
	$form( 'flush_sitemap', 'پاک‌کردن کش نقشه‌ی سایت', '', 'button-secondary' );
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
