<?php
/**
 * Database installer.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Database;

use MLGP\Media\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_tables();

		$schema = [];

		$schema[] = "CREATE TABLE {$tables['galleries']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			cover_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cover_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			display_type VARCHAR(50) NOT NULL DEFAULT 'grid',
			settings_json LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$tables['albums']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			cover_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cover_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			display_type VARCHAR(50) NOT NULL DEFAULT 'grid',
			settings_json LONGTEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$tables['gallery_items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			gallery_id BIGINT UNSIGNED NOT NULL,
			attachment_id BIGINT UNSIGNED NOT NULL,
			storage VARCHAR(20) NOT NULL DEFAULT 'attachment',
			original_name VARCHAR(255) NULL,
			file_name VARCHAR(255) NULL,
			file_path TEXT NULL,
			file_url TEXT NULL,
			thumb_path TEXT NULL,
			thumb_url TEXT NULL,
			medium_path TEXT NULL,
			medium_url TEXT NULL,
			large_path TEXT NULL,
			large_url TEXT NULL,
			mime_type VARCHAR(120) NULL,
			width INT NOT NULL DEFAULT 0,
			height INT NOT NULL DEFAULT 0,
			file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			item_title VARCHAR(255) NULL,
			item_caption LONGTEXT NULL,
			item_alt TEXT NULL,
			item_link TEXT NULL,
			item_tags TEXT NULL,
			is_visible TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY gallery_id (gallery_id),
			KEY attachment_id (attachment_id),
			KEY storage (storage),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$tables['album_items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			album_id BIGINT UNSIGNED NOT NULL,
			item_type VARCHAR(20) NOT NULL DEFAULT 'gallery',
			item_id BIGINT UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY album_id (album_id),
			KEY item_type (item_type),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		foreach ( $schema as $statement ) {
			dbDelta( $statement );
		}

		$storage = new Storage();
		$storage->ensure_base_structure();

		$settings = wp_parse_args( (array) get_option( 'mlgp_settings', [] ), self::default_settings() );

		update_option( 'mlgp_settings', $settings );
		update_option( 'mlgp_version', MLGP_VERSION );
	}

	/**
	 * Runs upgrade routines when the stored version is outdated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$current_version = (string) get_option( 'mlgp_version', '' );

		if ( in_array( $current_version, [ '0.22.0', '0.22.1' ], true ) ) {
			$settings = (array) get_option( 'mlgp_settings', [] );

			if ( empty( $settings['enable_lightbox'] ) ) {
				$settings['enable_lightbox'] = 1;
				update_option( 'mlgp_settings', $settings );
			}
		}

		if ( MLGP_VERSION !== $current_version ) {
			self::activate();
		}

		self::cleanup_old_installations();
	}
	/**
	 * Removes stale plugin directories created by past installs.
	 *
	 * @return void
	 */
	public static function cleanup_old_installations(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			return;
		}

		global $wp_filesystem;

		if ( ! WP_Filesystem() ) {
			return;
		}

		$base_directory = trailingslashit( WP_PLUGIN_DIR );
		$current_path  = trailingslashit( $base_directory . 'ml-gallery-pro' );
		$pattern       = $base_directory . 'ml-gallery-pro-*';
		$marker_file   = '.mlgp-installer-created';

		if ( $wp_filesystem->is_dir( $current_path ) ) {
			$wp_filesystem->put_contents(
				trailingslashit( $current_path ) . $marker_file,
				'Created by ML Gallery Pro installer cleanup guard.',
				FS_CHMOD_FILE
			);
		}

		$glob = glob( $pattern, GLOB_ONLYDIR );

		if ( ! is_array( $glob ) ) {
			return;
		}

		foreach ( $glob as $candidate ) {
			if ( wp_normalize_path( $candidate ) === wp_normalize_path( $current_path ) ) {
				continue;
			}

			$candidate = trailingslashit( $candidate );
			$basename  = basename( untrailingslashit( $candidate ) );

			if ( ! preg_match( '/^ml-gallery-pro-v?\d+(?:\.\d+){1,3}(?:-[a-z0-9-]+)?$/i', $basename ) ) {
				continue;
			}

			if ( dirname( wp_normalize_path( untrailingslashit( $candidate ) ) ) !== untrailingslashit( wp_normalize_path( $base_directory ) ) ) {
				continue;
			}

			if ( ! $wp_filesystem->exists( $candidate . $marker_file ) ) {
				continue;
			}

			$main_file = $candidate . 'ml-gallery-pro.php';

			if ( ! $wp_filesystem->exists( $main_file ) ) {
				continue;
			}

			$main_file_contents = (string) $wp_filesystem->get_contents( $main_file );

			if (
				false === strpos( $main_file_contents, 'Plugin Name: ML Gallery Pro' )
				|| false === strpos( $main_file_contents, 'Text Domain: ml-gallery-pro' )
				|| false === strpos( $main_file_contents, 'MLGP_VERSION' )
			) {
				continue;
			}

			$wp_filesystem->delete( $candidate, true );
		}
	}

	/**
	 * Returns default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return apply_filters(
			'mlgp_default_settings',
			[
				'columns_desktop'       => 4,
				'columns_tablet'        => 3,
				'columns_mobile'        => 2,
				'card_gap'              => 0,
				'card_padding'          => 0,
				'card_margin'           => 0,
				'card_border_width'     => 0,
				'card_border_color'     => '#d7e0ea',
				'card_border_opacity'   => 100,
				'gap_background_color' => '#ffffff',
				'gap_background_opacity' => 100,
				'wrapper_padding'       => 0,
				'wrapper_radius'        => 0,
				'wrapper_border_width'  => 0,
				'wrapper_border_color'  => '#ffffff',
				'wrapper_border_opacity'=> 0,
				'wrapper_background_color' => '#ffffff',
				'wrapper_background_opacity' => 0,
				'wrapper_shadow_opacity'=> 0,
				'wrapper_max_width'     => 0,
				'default_gallery_preset'=> 'masonry-default',
				'enable_frontend_filters' => 0,
				'items_per_page'        => 24,
				'pagination_enabled'    => 1,
				'show_titles'          => 0,
				'show_captions'        => 0,
				'show_item_tags'       => 0,
				'hide_all_titles'      => 0,
				'show_gallery_heading' => 0,
				'show_gallery_description' => 0,
				'image_quality'         => 82,
				'thumb_width'           => 240,
				'thumb_height'          => 160,
				'thumb_crop'            => 1,
				'medium_width'          => 900,
				'medium_height'         => 900,
				'large_width'           => 1600,
				'large_height'          => 1600,
				'album_cover_width'     => 360,
				'album_cover_height'    => 280,
				'album_cover_fit'       => 'contain',
				'album_cover_lock_ratio' => 1,
				'default_album_display_type' => 'grid',
				'album_columns_desktop' => 4,
				'album_columns_tablet'  => 3,
				'album_columns_mobile'  => 2,
				'album_card_gap'        => 18,
				'album_card_padding'    => 0,
				'album_card_margin'     => 0,
				'album_card_border_width' => 0,
				'album_card_border_color' => '#d7e0ea',
				'album_card_border_opacity' => 100,
				'album_gap_background_color' => '#ffffff',
				'album_gap_background_opacity' => 100,
				'album_card_radius'     => 0,
				'album_pagination_enabled' => 1,
				'album_items_per_page'  => 18,
				'album_show_titles'     => 1,
				'album_show_captions'   => 0,
				'album_show_heading'    => 0,
				'album_show_description' => 0,
				'album_item_title_font_size' => 18,
				'album_item_title_color' => '#172033',
				'watermark_enabled'     => 0,
				'watermark_text'        => '',
				'watermark_opacity'     => 34,
				'watermark_position'    => 'bottom-right',
				'rounded_corners'       => 0,
				'slideshow_show_arrows' => 1,
				'slideshow_show_thumbs' => 1,
				'nav_arrow_prev_url'    => '',
				'nav_arrow_next_url'    => '',
				'heading_font_size'     => 34,
				'heading_color'         => '#172033',
				'item_title_font_size'  => 18,
				'item_title_color'      => '#172033',
				'enable_lightbox'       => 1,
				'enable_lazy_load'      => 1,
				'label_view_gallery'    => 'Ver galeria',
				'label_back_to_album'   => 'Voltar ao álbum',
				'empty_gallery_message' => 'Esta galeria ainda não possui imagens.',
				'empty_album_message'   => 'Este álbum ainda não possui itens.',
			]
		);
	}

	/**
	 * Returns plugin table names.
	 *
	 * @return array<string, string>
	 */
	public static function get_tables(): array {
		global $wpdb;

		return [
			'galleries'     => $wpdb->prefix . 'mlgp_galleries',
			'albums'        => $wpdb->prefix . 'mlgp_albums',
			'gallery_items' => $wpdb->prefix . 'mlgp_gallery_items',
			'album_items'   => $wpdb->prefix . 'mlgp_album_items',
		];
	}
}
