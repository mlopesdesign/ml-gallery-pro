<?php
/**
 * Repository layer for plugin data.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Database;

use MLGP\Media\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {

	public const DEFAULT_SORT_MODE = 'id_desc';

	/**
	 * Table names.
	 *
	 * @var array<string, string>
	 */
	private $tables;

	/**
	 * Storage manager.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->tables  = Installer::get_tables();
		$this->storage = new Storage();
	}

	/**
	 * Returns the Storage instance.
	 *
	 * @return Storage
	 */
	public function get_storage(): Storage {
		return $this->storage;
	}

	/**
	 * Returns a single table name.
	 *
	 * @param string $key Table key.
	 * @return string
	 */
	public function table( string $key ): string {
		return $this->tables[ $key ] ?? '';
	}

	/**
	 * Returns the allowed admin list sort modes.
	 *
	 * @return array<string, string>
	 */
	public static function get_sort_modes(): array {
		return [
			'id_desc'         => 'ID DESC',
			'id_asc'          => 'ID ASC',
			'created_at_desc' => 'created_at DESC',
			'created_at_asc'  => 'created_at ASC',
			'updated_at_desc' => 'updated_at DESC',
			'updated_at_asc'  => 'updated_at ASC',
		];
	}

	/**
	 * Normalizes one admin list sort mode.
	 *
	 * @param string $sort_mode Requested sort mode.
	 * @return string
	 */
	public static function normalize_sort_mode( string $sort_mode ): string {
		$sort_mode = sanitize_key( $sort_mode );

		return array_key_exists( $sort_mode, self::get_sort_modes() ) ? $sort_mode : self::DEFAULT_SORT_MODE;
	}

	/**
	 * Builds a safe ORDER BY clause for collection lists.
	 *
	 * @param string $alias     SQL table alias.
	 * @param string $sort_mode Requested sort mode.
	 * @return string
	 */
	private function get_collection_order_by_clause( string $alias, string $sort_mode ): string {
		$alias     = preg_replace( '/[^a-z]/', '', strtolower( $alias ) ) ?: 'g';
		$sort_mode = self::normalize_sort_mode( $sort_mode );

		switch ( $sort_mode ) {
			case 'id_asc':
				return "ORDER BY {$alias}.id ASC";

			case 'created_at_desc':
				return "ORDER BY {$alias}.created_at DESC, {$alias}.id DESC";

			case 'created_at_asc':
				return "ORDER BY {$alias}.created_at ASC, {$alias}.id ASC";

			case 'updated_at_asc':
				return "ORDER BY {$alias}.updated_at ASC, {$alias}.id ASC";

			case 'id_desc':
				return "ORDER BY {$alias}.id DESC";

			case 'updated_at_desc':
			default:
				return "ORDER BY {$alias}.updated_at DESC, {$alias}.id DESC";
		}
	}

	/**
	 * Returns dashboard stats.
	 *
	 * @return array<string, int>
	 */
	public function get_stats(): array {
		global $wpdb;

		return [
			'galleries'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('galleries')}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'albums'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('albums')}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'gallery_items' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('gallery_items')}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'album_items'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('album_items')}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		];
	}

	/**
	 * Returns all galleries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_galleries( string $sort_mode = self::DEFAULT_SORT_MODE ): array {
		global $wpdb;

		$order_by = $this->get_collection_order_by_clause( 'g', $sort_mode );

		$sql = "
			SELECT g.*, COALESCE(item_totals.item_count, 0) AS item_count
			FROM {$this->table('galleries')} g
			LEFT JOIN (
				SELECT gallery_id, COUNT(*) AS item_count
				FROM {$this->table('gallery_items')}
				GROUP BY gallery_id
			) item_totals ON item_totals.gallery_id = g.id
			{$order_by}
		";

		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$items   = $this->normalize_collection( $results );

		// Batch-load album memberships for all galleries in one query.
		$album_map = [];
		$album_rows = $wpdb->get_results(
			"SELECT album_id, item_id FROM {$this->table('album_items')} WHERE item_type = 'gallery'",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( is_array( $album_rows ) ) {
			foreach ( $album_rows as $row ) {
				$gid = (int) $row['item_id'];
				$album_map[ $gid ][] = (int) $row['album_id'];
			}
		}

		foreach ( $items as &$item ) {
			$item = $this->decorate_gallery_summary( $item );
			$item['album_ids'] = $album_map[ (int) ( $item['id'] ?? 0 ) ] ?? [];
		}
		unset( $item );

		return $items;
	}

	/**
	 * Returns recent galleries.
	 *
	 * @param int $limit Number of items.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_galleries( int $limit = 5 ): array {
		return array_slice( $this->get_galleries(), 0, max( 1, $limit ) );
	}

	/**
	 * Returns one gallery.
	 *
	 * @param int $id Gallery ID.
	 * @return array<string, mixed>|null
	 */
	public function get_gallery( int $id ): ?array {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM {$this->table('galleries')} WHERE id = %d LIMIT 1", $id );
		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$gallery = $this->normalize_entity( $row );

		return $gallery ? $this->decorate_gallery_summary( $gallery, false ) : null;
	}


	/**
	 * Returns one gallery by slug.
	 *
	 * @param string $slug Gallery slug.
	 * @return array<string, mixed>|null
	 */
	public function get_gallery_by_slug( string $slug ): ?array {
		global $wpdb;

		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			return null;
		}

		$sql = $wpdb->prepare( "SELECT * FROM {$this->table('galleries')} WHERE slug = %s LIMIT 1", $slug );
		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$gallery = $this->normalize_entity( $row );

		return $gallery ? $this->decorate_gallery_summary( $gallery, false ) : null;
	}

	/**
	 * Returns gallery editor payload.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return array<string, mixed>|null
	 */
	public function get_gallery_editor( int $gallery_id ): ?array {
		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return null;
		}

		$items          = $this->get_gallery_items_rows( $gallery_id, false );
		$cover_item_id  = $this->resolve_gallery_cover_item_id_from_rows( $items, (int) ( $gallery['cover_item_id'] ?? 0 ), (int) ( $gallery['cover_attachment_id'] ?? 0 ) );
		$hydrated_items = $this->hydrate_gallery_items( $items, $cover_item_id, (int) ( $gallery['cover_attachment_id'] ?? 0 ) );

		$gallery['shortcode']        = $this->gallery_shortcode( $gallery_id );
		$gallery['legacy_shortcode']  = sprintf( '[ml_gallery_pro gallery="%d"]', $gallery_id );
		$gallery['public_url']       = $this->get_gallery_public_url_by_slug( (string) ( $gallery['slug'] ?? '' ) );
		$gallery['cover_item_id'] = $cover_item_id;
		$gallery['cover']         = $this->extract_cover_payload( $hydrated_items );

		return [
			'gallery' => $gallery,
			'items'   => $hydrated_items,
		];
	}

	/**
	 * Creates or updates a gallery.
	 *
	 * @param array<string, mixed> $data Gallery data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save_gallery( array $data ) {
		global $wpdb;

		$id       = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$title    = isset( $data['title'] ) ? sanitize_text_field( wp_unslash( (string) $data['title'] ) ) : '';
		$slug     = isset( $data['slug'] ) ? sanitize_title( wp_unslash( (string) $data['slug'] ) ) : '';
		$status   = $this->sanitize_status( $data['status'] ?? 'draft' );
		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : [];

		if ( '' === $title ) {
			return new \WP_Error( 'mlgp_missing_title', __( 'Informe um titulo para a galeria.', 'ml-gallery-pro' ) );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		$slug = $this->ensure_unique_slug( $this->table( 'galleries' ), $slug, $id );

		$payload = [
			'title'               => $title,
			'slug'                => $slug,
			'description'         => isset( $data['description'] ) ? wp_kses_post( wp_unslash( (string) $data['description'] ) ) : '',
			'status'              => $status,
			'cover_attachment_id' => isset( $data['cover_attachment_id'] ) ? absint( $data['cover_attachment_id'] ) : 0,
			'cover_item_id'       => isset( $data['cover_item_id'] ) ? absint( $data['cover_item_id'] ) : 0,
			'display_type'        => $this->sanitize_display_type( $data['display_type'] ?? $this->get_default_gallery_display_type() ),
			'settings_json'       => wp_json_encode( $settings ),
			'updated_at'          => current_time( 'mysql' ),
		];

		// published_at is user-editable (event date can differ from creation date).
		if ( isset( $data['published_at'] ) ) {
			$published_at = sanitize_text_field( (string) $data['published_at'] );
			$payload['published_at'] = '' !== $published_at ? $published_at : null;
		}

		if ( isset( $data['created_at'] ) && '' !== trim( (string) $data['created_at'] ) ) {
			$payload['created_at'] = sanitize_text_field( (string) $data['created_at'] );
		}

		if ( $id > 0 ) {
			$updated = $wpdb->update( $this->table( 'galleries' ), $payload, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $updated ) {
				return new \WP_Error( 'mlgp_gallery_update_failed', __( 'Nao foi possivel atualizar a galeria.', 'ml-gallery-pro' ) );
			}
		} else {
			$payload['created_by'] = get_current_user_id();
			$payload['created_at'] = current_time( 'mysql' );

			if ( ! isset( $payload['published_at'] ) ) {
				$payload['published_at'] = current_time( 'mysql' );
			}

			$inserted = $wpdb->insert( $this->table( 'galleries' ), $payload ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $inserted ) {
				return new \WP_Error( 'mlgp_gallery_insert_failed', __( 'Nao foi possivel criar a galeria.', 'ml-gallery-pro' ) );
			}

			$id = (int) $wpdb->insert_id;
		}

		$gallery_dir = $this->storage->ensure_gallery_dir( $id );

		if ( is_wp_error( $gallery_dir ) ) {
			return $gallery_dir;
		}

		return $this->get_gallery( $id );
	}

	/**
	 * Uploads local files into the gallery storage directory.
	 *
	 * @param int   $gallery_id Gallery ID.
	 * @param array $files      Uploaded files array.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function upload_gallery_files( int $gallery_id, array $files ) {
		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$uploads = $this->storage->store_gallery_uploads( $gallery_id, $files );

		if ( is_wp_error( $uploads ) ) {
			return $uploads;
		}

		return $this->persist_local_upload_payloads( $gallery_id, $gallery, $uploads );
	}

	/**
	 * Imports all valid images from one ZIP file into the gallery storage.
	 *
	 * @param int   $gallery_id Gallery ID.
	 * @param array $file       Uploaded ZIP file data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function import_gallery_zip( int $gallery_id, array $file ) {
		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$uploads = $this->storage->store_gallery_zip( $gallery_id, $file );

		if ( is_wp_error( $uploads ) ) {
			return $uploads;
		}

		return $this->persist_local_upload_payloads( $gallery_id, $gallery, $uploads );
	}

	/**
	 * Imports all valid images from one allowed server directory.
	 *
	 * @param int    $gallery_id    Gallery ID.
	 * @param string $root_key      Allowed root key.
	 * @param string $relative_path Relative server path.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function import_gallery_directory( int $gallery_id, string $root_key, string $relative_path ) {
		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$uploads = $this->storage->import_gallery_directory( $gallery_id, $root_key, $relative_path );

		if ( is_wp_error( $uploads ) ) {
			return $uploads;
		}

		return $this->persist_local_upload_payloads( $gallery_id, $gallery, $uploads );
	}

	/**
	 * Imports one small server-directory batch to avoid timeout and memory exhaustion.
	 *
	 * @param int    $gallery_id    Gallery ID.
	 * @param string $root_key      Allowed import root key.
	 * @param string $relative_path Relative directory path.
	 * @param int    $offset        Current offset.
	 * @param int    $limit         Batch size.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function import_gallery_directory_batch( int $gallery_id, string $root_key, string $relative_path, int $offset = 0, int $limit = 10 ) {
		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$offset = max( 0, $offset );
		$limit  = max( 1, min( 20, $limit ) );
		$files  = $this->storage->collect_server_import_files( $root_key, $relative_path );

		if ( is_wp_error( $files ) ) {
			return $files;
		}

		$total = count( $files );

		if ( 0 === $total ) {
			return new \WP_Error( 'mlgp_server_import_empty', __( 'Nenhuma imagem valida foi encontrada na pasta do servidor informada.', 'ml-gallery-pro' ) );
		}

		$batch_files = array_slice( $files, $offset, $limit );

		if ( empty( $batch_files ) ) {
			return [
				'editor'      => $this->get_gallery_editor( $gallery_id ),
				'imported'    => 0,
				'total'       => $total,
				'offset'      => $offset,
				'next_offset' => $offset,
				'done'        => true,
			];
		}

		$uploads = $this->storage->import_gallery_file_batch( $gallery_id, $batch_files );

		if ( is_wp_error( $uploads ) ) {
			error_log( '[ML Gallery Pro] Server import error: gallery_id=' . $gallery_id . ' root=' . $root_key . ' path=' . $relative_path . ' offset=' . $offset . ' limit=' . $limit . ' message=' . $uploads->get_error_message() );
			return $uploads;
		}

		if ( empty( $uploads ) ) {
			$next_offset = min( $total, $offset + $limit );
			return [
				'editor'      => $this->get_gallery_editor( $gallery_id ),
				'imported'    => 0,
				'total'       => $total,
				'offset'      => $offset,
				'next_offset' => $next_offset,
				'done'        => $next_offset >= $total,
			];
		}

		$editor = $this->persist_local_upload_payloads( $gallery_id, $gallery, $uploads );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$next_offset = min( $total, $offset + $limit );

		return [
			'editor'      => $editor,
			'imported'    => count( $uploads ),
			'total'       => $total,
			'offset'      => $offset,
			'next_offset' => $next_offset,
			'done'        => $next_offset >= $total,
		];
	}


	/**
	 * Adds image attachments to a gallery.
	 *
	 * @param int               $gallery_id Gallery ID.
	 * @param array<int, mixed> $attachment_ids Attachment IDs.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_gallery_items( int $gallery_id, array $attachment_ids ) {
		global $wpdb;

		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$attachment_ids = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );

		if ( empty( $attachment_ids ) ) {
			return new \WP_Error( 'mlgp_missing_attachments', __( 'Selecione ao menos uma imagem valida.', 'ml-gallery-pro' ) );
		}

		$existing_ids = array_map(
			'absint',
			wp_list_pluck(
				$this->get_gallery_items_rows( $gallery_id, false ),
				'attachment_id'
			)
		);

		$sort_order   = $this->get_next_gallery_item_order( $gallery_id );
		$current_time = current_time( 'mysql' );
		$created_ids  = [];

		foreach ( $attachment_ids as $attachment_id ) {
			if ( in_array( $attachment_id, $existing_ids, true ) || ! $this->is_valid_image_attachment( $attachment_id ) ) {
				continue;
			}

			$attachment_post = get_post( $attachment_id );

			if ( ! $attachment_post ) {
				continue;
			}

			$payload = [
				'gallery_id'     => $gallery_id,
				'attachment_id'  => $attachment_id,
				'storage'        => 'attachment',
				'original_name'  => $this->truncate_database_filename( basename( (string) get_attached_file( $attachment_id ) ), 255 ),
				'file_name'      => '',
				'file_path'      => '',
				'file_url'       => '',
				'thumb_path'     => '',
				'thumb_url'      => '',
				'medium_path'    => '',
				'medium_url'     => '',
				'large_path'     => '',
				'large_url'      => '',
				'mime_type'      => (string) get_post_mime_type( $attachment_id ),
				'width'          => 0,
				'height'         => 0,
				'file_size'      => 0,
				'item_title'     => $this->truncate_database_text( sanitize_text_field( $attachment_post->post_title ), 255 ),
				'item_caption'   => wp_kses_post( $attachment_post->post_excerpt ),
				'item_alt'       => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
				'item_link'      => '',
				'item_tags'      => '',
				'is_visible'     => 1,
				'sort_order'     => $sort_order,
				'created_at'     => $current_time,
				'updated_at'     => $current_time,
			];

			$inserted = $wpdb->insert( $this->table( 'gallery_items' ), $payload ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $inserted ) {
				continue;
			}

			$created_ids[] = (int) $wpdb->insert_id;
			++$sort_order;
		}

		if ( empty( $created_ids ) ) {
			return new \WP_Error( 'mlgp_no_new_gallery_items', __( 'Nenhuma imagem nova foi adicionada a galeria.', 'ml-gallery-pro' ) );
		}

		$cover_item_id = ! empty( $gallery['cover_item_id'] ) ? absint( $gallery['cover_item_id'] ) : (int) $created_ids[0];
		$cover_row     = $this->get_gallery_item_row( $gallery_id, $cover_item_id );

		$this->update_gallery_meta(
			$gallery_id,
			[
				'cover_item_id'       => $cover_item_id,
				'cover_attachment_id' => ! empty( $cover_row['attachment_id'] ) ? absint( $cover_row['attachment_id'] ) : 0,
				'updated_at'          => current_time( 'mysql' ),
			]
		);

		return $this->get_gallery_editor( $gallery_id );
	}

	/**
	 * Saves ordering and metadata for gallery items.
	 *
	 * @param int               $gallery_id Gallery ID.
	 * @param array<int, mixed> $items Gallery items payload.
	 * @param int               $cover_item_id Cover gallery item ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save_gallery_items( int $gallery_id, array $items, int $cover_item_id = 0 ) {
		global $wpdb;

		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$existing_rows = $this->get_gallery_items_rows( $gallery_id, false );
		$existing_map  = [];

		foreach ( $existing_rows as $row ) {
			$existing_map[ (int) $row['id'] ] = $row;
		}

		$processed_ids  = [];
		$remaining_ids  = [];
		$sort_order     = 0;
		$current_time   = current_time( 'mysql' );

		foreach ( $items as $item_input ) {
			$item_id = isset( $item_input['id'] ) ? absint( $item_input['id'] ) : 0;

			if ( ! $item_id || ! isset( $existing_map[ $item_id ] ) ) {
				continue;
			}

			$existing = $existing_map[ $item_id ];

			$payload = [
				'item_title'   => $this->truncate_database_text( sanitize_text_field( (string) ( $item_input['item_title'] ?? $existing['item_title'] ?? '' ) ), 255 ),
				'item_caption' => wp_kses_post( (string) ( $item_input['item_caption'] ?? $existing['item_caption'] ?? '' ) ),
				'item_alt'     => sanitize_text_field( (string) ( $item_input['item_alt'] ?? $existing['item_alt'] ?? '' ) ),
				'item_link'    => $this->sanitize_item_link( $item_input['item_link'] ?? $existing['item_link'] ?? '' ),
				'item_tags'    => $this->sanitize_item_tags( $item_input['item_tags'] ?? $existing['item_tags'] ?? '' ),
				'is_visible'   => $this->sanitize_visibility_flag( $item_input['is_visible'] ?? $existing['is_visible'] ?? 1 ),
				'sort_order'   => $sort_order,
				'updated_at'   => $current_time,
			];

			$updated = $wpdb->update(
				$this->table( 'gallery_items' ),
				$payload,
				[
					'id'         => $item_id,
					'gallery_id' => $gallery_id,
				]
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $updated ) {
				return new \WP_Error( 'mlgp_gallery_items_update_failed', __( 'Nao foi possivel salvar os itens da galeria.', 'ml-gallery-pro' ) );
			}

			$processed_ids[] = $item_id;
			$remaining_ids[] = $item_id;
			++$sort_order;
		}

		foreach ( $existing_map as $existing_id => $existing_row ) {
			if ( in_array( $existing_id, $processed_ids, true ) ) {
				continue;
			}

			if ( 'local' === ( $existing_row['storage'] ?? '' ) || ! empty( $existing_row['file_path'] ) ) {
				$this->storage->remove_item_files( $existing_row );
			}

			$wpdb->delete(
				$this->table( 'gallery_items' ),
				[
					'id'         => $existing_id,
					'gallery_id' => $gallery_id,
				]
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		$new_cover_item_id = $this->resolve_cover_item_id_from_ids( $remaining_ids, $cover_item_id, (int) ( $gallery['cover_item_id'] ?? 0 ) );
		$cover_row         = $new_cover_item_id > 0 ? $this->get_gallery_item_row( $gallery_id, $new_cover_item_id ) : null;

		$this->update_gallery_meta(
			$gallery_id,
			[
				'cover_item_id'       => $new_cover_item_id,
				'cover_attachment_id' => ! empty( $cover_row['attachment_id'] ) ? absint( $cover_row['attachment_id'] ) : 0,
				'updated_at'          => current_time( 'mysql' ),
			]
		);

		return $this->get_gallery_editor( $gallery_id );
	}

	/**
	 * Applies one bulk action to selected gallery items.
	 *
	 * @param int               $gallery_id Gallery ID.
	 * @param array<int, mixed> $item_ids   Selected gallery item IDs.
	 * @param string            $action     Bulk action key.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function bulk_update_gallery_items( int $gallery_id, array $item_ids, string $action, array $options = [] ) {
		global $wpdb;

		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$action   = sanitize_key( $action );
		$item_ids = array_values( array_unique( array_filter( array_map( 'absint', $item_ids ) ) ) );

		if ( empty( $item_ids ) ) {
			return new \WP_Error( 'mlgp_bulk_items_missing', __( 'Selecione pelo menos uma imagem da galeria para continuar.', 'ml-gallery-pro' ) );
		}

		if ( ! in_array( $action, [ 'show', 'hide', 'delete', 'append_tags', 'replace_tags', 'clear_tags', 'replace_titles', 'clear_titles', 'replace_alts', 'clear_alts', 'replace_captions', 'clear_captions', 'regenerate', 'rotate_left', 'rotate_right' ], true ) ) {
			return new \WP_Error( 'mlgp_bulk_action_invalid', __( 'A acao em massa solicitada nao e valida.', 'ml-gallery-pro' ) );
		}

		$existing_rows = $this->get_gallery_items_rows( $gallery_id, false );
		$selected_rows = [];

		foreach ( $existing_rows as $row ) {
			if ( in_array( (int) $row['id'], $item_ids, true ) ) {
				$selected_rows[] = $row;
			}
		}

		if ( empty( $selected_rows ) ) {
			return new \WP_Error( 'mlgp_bulk_items_not_found', __( 'Nenhuma imagem valida foi encontrada para a acao em massa.', 'ml-gallery-pro' ) );
		}

		$current_time = current_time( 'mysql' );

		if ( 'delete' === $action ) {
			foreach ( $selected_rows as $row ) {
				if ( 'local' === ( $row['storage'] ?? '' ) || ! empty( $row['file_path'] ) ) {
					$this->storage->remove_item_files( $row );
				}

				$wpdb->delete(
					$this->table( 'gallery_items' ),
					[
						'id'         => (int) $row['id'],
						'gallery_id' => $gallery_id,
					]
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
		} elseif ( in_array( $action, [ 'show', 'hide' ], true ) ) {
			$visibility = 'show' === $action ? 1 : 0;

			foreach ( $selected_rows as $row ) {
				$updated = $wpdb->update(
					$this->table( 'gallery_items' ),
					[
						'is_visible' => $visibility,
						'updated_at' => $current_time,
					],
					[
						'id'         => (int) $row['id'],
						'gallery_id' => $gallery_id,
					]
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

				if ( false === $updated ) {
					return new \WP_Error( 'mlgp_bulk_update_failed', __( 'Nao foi possivel atualizar as imagens selecionadas.', 'ml-gallery-pro' ) );
				}
			}
		} elseif ( in_array( $action, [ 'append_tags', 'replace_tags', 'clear_tags' ], true ) ) {
			$incoming_tags = $this->parse_item_tags( (string) ( $options['tags'] ?? '' ) );

			if ( in_array( $action, [ 'append_tags', 'replace_tags' ], true ) && empty( $incoming_tags ) ) {
				return new \WP_Error( 'mlgp_bulk_tags_missing', __( 'Informe pelo menos uma tag para aplicar na edicao em lote.', 'ml-gallery-pro' ) );
			}

			foreach ( $selected_rows as $row ) {
				$current_tags = $this->parse_item_tags( (string) ( $row['item_tags'] ?? '' ) );
				$next_tags    = [];

				if ( 'append_tags' === $action ) {
					$next_tags = $this->merge_tag_sets( $current_tags, $incoming_tags );
				} elseif ( 'replace_tags' === $action ) {
					$next_tags = $incoming_tags;
				}

				$updated = $wpdb->update(
					$this->table( 'gallery_items' ),
					[
						'item_tags'  => implode( ', ', $next_tags ),
						'updated_at' => $current_time,
					],
					[
						'id'         => (int) $row['id'],
						'gallery_id' => $gallery_id,
					]
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

				if ( false === $updated ) {
					return new \WP_Error( 'mlgp_bulk_tags_failed', __( 'Nao foi possivel atualizar as tags das imagens selecionadas.', 'ml-gallery-pro' ) );
				}
			}
		} elseif ( in_array( $action, [ 'replace_titles', 'clear_titles', 'replace_alts', 'clear_alts', 'replace_captions', 'clear_captions' ], true ) ) {
			$incoming_title   = $this->truncate_database_text( sanitize_text_field( wp_unslash( (string) ( $options['title'] ?? '' ) ) ), 255 );
			$incoming_alt     = $this->truncate_database_text( sanitize_text_field( wp_unslash( (string) ( $options['alt'] ?? '' ) ) ), 255 );
			$incoming_caption = wp_kses_post( wp_unslash( (string) ( $options['caption'] ?? '' ) ) );

			if ( 'replace_titles' === $action && '' === $incoming_title ) {
				return new \WP_Error( 'mlgp_bulk_title_missing', __( 'Informe um titulo para substituir em lote.', 'ml-gallery-pro' ) );
			}

			if ( 'replace_alts' === $action && '' === $incoming_alt ) {
				return new \WP_Error( 'mlgp_bulk_alt_missing', __( 'Informe um ALT para substituir em lote.', 'ml-gallery-pro' ) );
			}

			if ( 'replace_captions' === $action && '' === trim( wp_strip_all_tags( $incoming_caption ) ) ) {
				return new \WP_Error( 'mlgp_bulk_caption_missing', __( 'Informe uma legenda para substituir em lote.', 'ml-gallery-pro' ) );
			}

			foreach ( $selected_rows as $row ) {
				$updates = [
					'updated_at' => $current_time,
				];

				if ( in_array( $action, [ 'replace_titles', 'clear_titles' ], true ) ) {
					$updates['item_title'] = 'clear_titles' === $action ? '' : $incoming_title;
				} elseif ( in_array( $action, [ 'replace_alts', 'clear_alts' ], true ) ) {
					$updates['item_alt'] = 'clear_alts' === $action ? '' : $incoming_alt;
				} else {
					$updates['item_caption'] = 'clear_captions' === $action ? '' : $incoming_caption;
				}

				$updated = $wpdb->update(
					$this->table( 'gallery_items' ),
					$updates,
					[
						'id'         => (int) $row['id'],
						'gallery_id' => $gallery_id,
					]
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

				if ( false === $updated ) {
					return new \WP_Error( 'mlgp_bulk_text_failed', __( 'Nao foi possivel atualizar os textos das imagens selecionadas.', 'ml-gallery-pro' ) );
				}
			}
		} elseif ( in_array( $action, [ 'regenerate', 'rotate_left', 'rotate_right' ], true ) ) {
			$processed = 0;

			foreach ( $selected_rows as $row ) {
				if ( 'local' !== ( $row['storage'] ?? '' ) && empty( $row['file_path'] ) ) {
					continue;
				}

				$payload = 'regenerate' === $action
					? $this->storage->regenerate_item_payload( $row )
					: $this->storage->rotate_item_payload( $row, 'rotate_left' === $action ? 90 : -90 );

				if ( is_wp_error( $payload ) ) {
					continue;
				}

				if ( ! $this->update_local_item_from_payload( $row, $payload, $current_time ) ) {
					return new \WP_Error(
						'regenerate' === $action ? 'mlgp_bulk_regenerate_failed' : 'mlgp_bulk_rotate_failed',
						'regenerate' === $action
							? __( 'Nao foi possivel regenerar as previews das imagens selecionadas.', 'ml-gallery-pro' )
							: __( 'Nao foi possivel rotacionar as imagens selecionadas.', 'ml-gallery-pro' )
					);
				}

				++$processed;
			}

			if ( 0 === $processed ) {
				return new \WP_Error(
					'regenerate' === $action ? 'mlgp_bulk_regenerate_empty' : 'mlgp_bulk_rotate_empty',
					'regenerate' === $action
						? __( 'Nenhuma imagem local valida foi encontrada para regenerar as previews.', 'ml-gallery-pro' )
						: __( 'Nenhuma imagem local valida foi encontrada para rotacionar.', 'ml-gallery-pro' )
				);
			}
		}

		$remaining_rows     = $this->get_gallery_items_rows( $gallery_id, false );
		$new_cover_item_id  = $this->resolve_gallery_cover_item_id_from_rows( $remaining_rows, (int) ( $gallery['cover_item_id'] ?? 0 ), (int) ( $gallery['cover_attachment_id'] ?? 0 ) );
		$cover_row          = $new_cover_item_id > 0 ? $this->get_gallery_item_row( $gallery_id, $new_cover_item_id ) : null;

		$this->update_gallery_meta(
			$gallery_id,
			[
				'cover_item_id'       => $new_cover_item_id,
				'cover_attachment_id' => ! empty( $cover_row['attachment_id'] ) ? absint( $cover_row['attachment_id'] ) : 0,
				'updated_at'          => $current_time,
			]
		);

		return $this->get_gallery_editor( $gallery_id );
	}

	/**
	 * Deletes a gallery and its items.
	 *
	 * @param int $id Gallery ID.
	 * @return bool
	 */
	public function delete_gallery( int $id ): bool {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		foreach ( $this->get_gallery_items_rows( $id, false ) as $item ) {
			if ( 'local' === ( $item['storage'] ?? '' ) || ! empty( $item['file_path'] ) ) {
				$this->storage->remove_item_files( $item );
			}
		}

		$this->storage->delete_gallery_directory( $id );

		$wpdb->delete( $this->table( 'gallery_items' ), [ 'gallery_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->delete( $this->table( 'galleries' ), [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $deleted;
	}

	/**
	 * Deletes multiple galleries in one request.
	 *
	 * @param array<int, int> $ids Gallery IDs.
	 * @return int|\WP_Error
	 */
	public function delete_galleries_bulk( array $ids ) {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return new \WP_Error( 'mlgp_galleries_bulk_missing', __( 'Selecione pelo menos uma galeria para excluir.', 'ml-gallery-pro' ) );
		}

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( $this->delete_gallery( $id ) ) {
				++$deleted;
			}
		}

		if ( $deleted <= 0 ) {
			return new \WP_Error( 'mlgp_galleries_bulk_failed', __( 'Nao foi possivel excluir as galerias selecionadas.', 'ml-gallery-pro' ) );
		}

		return $deleted;
	}

	/**
	 * Deletes every gallery and every image stored in the plugin.
	 *
	 * @return int|\WP_Error
	 */
	public function delete_all_galleries() {
		$ids = array_map(
			'absint',
			wp_list_pluck( $this->get_galleries(), 'id' )
		);

		if ( empty( $ids ) ) {
			return new \WP_Error( 'mlgp_all_galleries_empty', __( 'Nao existem galerias para excluir.', 'ml-gallery-pro' ) );
		}

		return $this->delete_galleries_bulk( $ids );
	}

	/**
	 * Deletes every image item while keeping gallery records.
	 *
	 * @return int|\WP_Error
	 */
	public function delete_all_gallery_images() {
		global $wpdb;

		$rows = $this->normalize_collection(
			$wpdb->get_results( "SELECT * FROM {$this->table('gallery_items')} ORDER BY gallery_id ASC, sort_order ASC, id ASC" ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);

		if ( empty( $rows ) ) {
			return new \WP_Error( 'mlgp_all_images_empty', __( 'Nao existem imagens para excluir.', 'ml-gallery-pro' ) );
		}

		$deleted = 0;

		foreach ( $rows as $row ) {
			if ( 'local' === ( $row['storage'] ?? '' ) || ! empty( $row['file_path'] ) ) {
				$this->storage->remove_item_files( $row );
			}
			++$deleted;
		}

		$wpdb->query( "TRUNCATE TABLE {$this->table('gallery_items')}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$this->table('galleries')} SET cover_item_id = 0, cover_attachment_id = 0, updated_at = '" . esc_sql( current_time( 'mysql' ) ) . "'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( array_map( 'absint', wp_list_pluck( $this->get_galleries(), 'id' ) ) as $gallery_id ) {
			$this->storage->delete_gallery_directory( $gallery_id );
			$this->storage->ensure_gallery_dir( $gallery_id );
		}

		return $deleted;
	}

	/**
	 * Returns gallery items.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_gallery_items( int $gallery_id ): array {
		$gallery = $this->get_gallery( $gallery_id );
		$items   = $this->get_gallery_items_rows( $gallery_id, true );

		return $this->hydrate_gallery_items(
			$items,
			$this->resolve_gallery_cover_item_id_from_rows( $items, (int) ( $gallery['cover_item_id'] ?? 0 ), (int) ( $gallery['cover_attachment_id'] ?? 0 ) ),
			(int) ( $gallery['cover_attachment_id'] ?? 0 )
		);
	}

	/**
	 * Returns the current tag report for admin use.
	 *
	 * @return array<string, mixed>
	 */
	public function get_tag_report(): array {
		$rows        = $this->get_tag_rows( false );
		$tag_index   = array_values( $this->build_tag_index_from_rows( $rows ) );
		$gallery_map = [];
		$image_count = 0;

		foreach ( $rows as $row ) {
			$tags = $this->parse_item_tags( (string) ( $row['item_tags'] ?? '' ) );

			if ( empty( $tags ) ) {
				continue;
			}

			++$image_count;

			if ( ! empty( $row['gallery_id'] ) ) {
				$gallery_map[ (int) $row['gallery_id'] ] = true;
			}
		}

		return [
			'items' => $tag_index,
			'stats' => [
				'tags'      => count( $tag_index ),
				'images'    => $image_count,
				'galleries' => count( $gallery_map ),
			],
		];
	}

	/**
	 * Returns visible gallery items filtered by tags across galleries.
	 *
	 * @param array<int, string> $tag_filters Requested tag filters.
	 * @param bool               $published_only Whether to restrict to published galleries.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tagged_items( array $tag_filters, bool $published_only = true ): array {
		$tag_filters = array_values( array_filter( array_map( 'sanitize_title', $tag_filters ) ) );

		if ( empty( $tag_filters ) ) {
			return [];
		}

		$matched_rows = [];

		foreach ( $this->get_tag_rows( $published_only ) as $row ) {
			$item_tags = array_map(
				'sanitize_title',
				$this->parse_item_tags( (string) ( $row['item_tags'] ?? '' ) )
			);

			if ( empty( $item_tags ) || empty( array_intersect( $tag_filters, $item_tags ) ) ) {
				continue;
			}

			$matched_rows[] = $row;
		}

		if ( empty( $matched_rows ) ) {
			return [];
		}

		$items = $this->hydrate_gallery_items( $matched_rows, 0, 0 );

		foreach ( $items as &$item ) {
			$gallery_id              = (int) ( $item['gallery_id'] ?? 0 );
			$item['gallery_title']   = (string) ( $item['gallery_title'] ?? '' );
			$item['gallery_slug']    = (string) ( $item['gallery_slug'] ?? '' );
			$item['gallery_status']  = (string) ( $item['gallery_status'] ?? '' );
			$item['gallery_shortcode'] = $gallery_id > 0 ? $this->gallery_shortcode( $gallery_id ) : '';
		}
		unset( $item );

		return $items;
	}

	/**
	 * Returns the gallery cover payload.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return array<string, mixed>|null
	 */
	public function get_gallery_cover_payload( int $gallery_id ): ?array {
		$editor = $this->get_gallery_editor( $gallery_id );

		return isset( $editor['gallery']['cover'] ) && is_array( $editor['gallery']['cover'] ) ? $editor['gallery']['cover'] : null;
	}

	/**
	 * Returns the album cover payload using its first valid child.
	 *
	 * @param int $album_id Album ID.
	 * @return array<string, mixed>|null
	 */
	public function get_album_cover_payload( int $album_id ): ?array {
		return $this->get_album_cover_payload_recursive( $album_id, [] );
	}

	/**
	 * Returns all albums.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_albums( string $sort_mode = self::DEFAULT_SORT_MODE ): array {
		global $wpdb;

		$order_by = $this->get_collection_order_by_clause( 'a', $sort_mode );

		$sql = "
			SELECT a.*, COALESCE(item_totals.item_count, 0) AS item_count
			FROM {$this->table('albums')} a
			LEFT JOIN (
				SELECT album_id, COUNT(*) AS item_count
				FROM {$this->table('album_items')}
				GROUP BY album_id
			) item_totals ON item_totals.album_id = a.id
			{$order_by}
		";

		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$items   = $this->normalize_collection( $results );

		foreach ( $items as &$item ) {
			$item = $this->decorate_album_summary( $item );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Returns recent albums.
	 *
	 * @param int $limit Number of items.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_albums( int $limit = 5 ): array {
		return array_slice( $this->get_albums(), 0, max( 1, $limit ) );
	}

	/**
	 * Returns one album.
	 *
	 * @param int $id Album ID.
	 * @return array<string, mixed>|null
	 */
	public function get_album( int $id ): ?array {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM {$this->table('albums')} WHERE id = %d LIMIT 1", $id );
		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$album = $this->normalize_entity( $row );

		return $album ? $this->decorate_album_summary( $album, false ) : null;
	}

	/**
	 * Returns album editor payload.
	 *
	 * @param int $album_id Album ID.
	 * @return array<string, mixed>|null
	 */
	public function get_album_editor( int $album_id ): ?array {
		$album = $this->get_album( $album_id );

		if ( empty( $album ) ) {
			return null;
		}

		$items              = $this->hydrate_album_items_for_editor( $this->get_album_items( $album_id ), $album_id );
		$available_galleries = $this->get_galleries();
		$available_albums    = array_values(
			array_filter(
				$this->get_albums(),
				function ( array $candidate ) use ( $album_id ): bool {
					$candidate_id = (int) ( $candidate['id'] ?? 0 );

					if ( $candidate_id <= 0 || $candidate_id === $album_id ) {
						return false;
					}

					return ! $this->album_contains_album( $candidate_id, $album_id, [] );
				}
			)
		);

		$album['shortcode']        = $this->album_shortcode( $album_id );
		$album['legacy_shortcode'] = sprintf( '[ml_gallery_pro album="%d"]', $album_id );
		$album['cover']            = $this->get_album_cover_payload( $album_id );

		return [
			'album'               => $album,
			'items'               => $items,
			'available_galleries' => $available_galleries,
			'available_albums'    => $available_albums,
		];
	}

	/**
	 * Creates or updates an album.
	 *
	 * @param array<string, mixed> $data Album data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save_album( array $data ) {
		global $wpdb;

		$id       = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$title    = isset( $data['title'] ) ? sanitize_text_field( wp_unslash( (string) $data['title'] ) ) : '';
		$slug     = isset( $data['slug'] ) ? sanitize_title( wp_unslash( (string) $data['slug'] ) ) : '';
		$status   = $this->sanitize_status( $data['status'] ?? 'draft' );
		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : [];

		if ( '' === $title ) {
			return new \WP_Error( 'mlgp_missing_album_title', __( 'Informe um titulo para o album.', 'ml-gallery-pro' ) );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		$slug = $this->ensure_unique_slug( $this->table( 'albums' ), $slug, $id );

		$payload = [
			'title'               => $title,
			'slug'                => $slug,
			'description'         => isset( $data['description'] ) ? wp_kses_post( wp_unslash( (string) $data['description'] ) ) : '',
			'status'              => $status,
			'cover_attachment_id' => isset( $data['cover_attachment_id'] ) ? absint( $data['cover_attachment_id'] ) : 0,
			'cover_item_id'       => isset( $data['cover_item_id'] ) ? absint( $data['cover_item_id'] ) : 0,
			'display_type'        => isset( $data['display_type'] ) ? sanitize_key( (string) $data['display_type'] ) : 'grid',
			'settings_json'       => wp_json_encode( $settings ),
			'updated_at'          => current_time( 'mysql' ),
		];

		if ( isset( $data['published_at'] ) ) {
			$published_at = sanitize_text_field( (string) $data['published_at'] );
			$payload['published_at'] = '' !== $published_at ? $published_at : null;
		}

		if ( $id > 0 ) {
			$updated = $wpdb->update( $this->table( 'albums' ), $payload, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $updated ) {
				return new \WP_Error( 'mlgp_album_update_failed', __( 'Nao foi possivel atualizar o album.', 'ml-gallery-pro' ) );
			}
		} else {
			$payload['created_by'] = get_current_user_id();
			$payload['created_at'] = current_time( 'mysql' );

			if ( ! isset( $payload['published_at'] ) ) {
				$payload['published_at'] = current_time( 'mysql' );
			}

			$inserted = $wpdb->insert( $this->table( 'albums' ), $payload ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $inserted ) {
				return new \WP_Error( 'mlgp_album_insert_failed', __( 'Nao foi possivel criar o album.', 'ml-gallery-pro' ) );
			}

			$id = (int) $wpdb->insert_id;
		}

		return $this->get_album( $id );
	}

	/**
	 * Saves album item ordering and structure.
	 *
	 * @param int               $album_id Album ID.
	 * @param array<int, mixed> $items    Album items payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save_album_items( int $album_id, array $items ) {
		global $wpdb;

		$album = $this->get_album( $album_id );

		if ( empty( $album ) ) {
			return new \WP_Error( 'mlgp_album_not_found', __( 'Album nao encontrado.', 'ml-gallery-pro' ) );
		}

		$normalized_items = [];
		$seen_keys        = [];

		foreach ( $items as $input ) {
			$item_type = sanitize_key( (string) ( $input['item_type'] ?? 'gallery' ) );
			$item_type = in_array( $item_type, [ 'gallery', 'album' ], true ) ? $item_type : 'gallery';
			$item_id   = absint( $input['item_id'] ?? 0 );

			if ( $item_id <= 0 ) {
				continue;
			}

			if ( 'album' === $item_type ) {
				if ( $item_id === $album_id ) {
					return new \WP_Error( 'mlgp_album_self_reference', __( 'Um album nao pode conter a si mesmo.', 'ml-gallery-pro' ) );
				}

				if ( ! $this->get_album( $item_id ) ) {
					continue;
				}

				if ( $this->album_contains_album( $item_id, $album_id, [] ) ) {
					return new \WP_Error( 'mlgp_album_recursive_reference', __( 'Nao foi possivel salvar porque isso criaria um ciclo entre albuns.', 'ml-gallery-pro' ) );
				}
			} else {
				if ( ! $this->get_gallery( $item_id ) ) {
					continue;
				}
			}

			$item_key = $item_type . ':' . $item_id;

			if ( isset( $seen_keys[ $item_key ] ) ) {
				continue;
			}

			$seen_keys[ $item_key ] = true;
			$normalized_items[]     = [
				'item_type' => $item_type,
				'item_id'   => $item_id,
			];
		}

		$wpdb->delete( $this->table( 'album_items' ), [ 'album_id' => $album_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$current_time = current_time( 'mysql' );

		foreach ( $normalized_items as $index => $item ) {
			$inserted = $wpdb->insert(
				$this->table( 'album_items' ),
				[
					'album_id'   => $album_id,
					'item_type'  => $item['item_type'],
					'item_id'    => (int) $item['item_id'],
					'sort_order' => (int) $index,
					'created_at' => $current_time,
					'updated_at' => $current_time,
				]
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $inserted ) {
				return new \WP_Error( 'mlgp_album_items_save_failed', __( 'Nao foi possivel salvar a estrutura do album.', 'ml-gallery-pro' ) );
			}
		}

		$wpdb->update(
			$this->table( 'albums' ),
			[
				'updated_at' => $current_time,
			],
			[
				'id' => $album_id,
			]
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $this->get_album_editor( $album_id );
	}

	/**
	 * Deletes an album and its items.
	 *
	 * @param int $id Album ID.
	 * @return bool
	 */
	public function delete_album( int $id ): bool {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		$wpdb->delete( $this->table( 'album_items' ), [ 'album_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete(
			$this->table( 'album_items' ),
			[
				'item_type' => 'album',
				'item_id'   => $id,
			]
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->delete( $this->table( 'albums' ), [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $deleted;
	}

	/**
	 * Deletes multiple albums.
	 *
	 * @param array<int, int> $ids Album IDs.
	 * @return int|\WP_Error
	 */
	public function delete_albums_bulk( array $ids ) {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return new \WP_Error( 'mlgp_albums_bulk_missing', __( 'Selecione pelo menos um album para excluir.', 'ml-gallery-pro' ) );
		}

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( $this->delete_album( $id ) ) {
				++$deleted;
			}
		}

		if ( $deleted <= 0 ) {
			return new \WP_Error( 'mlgp_albums_bulk_failed', __( 'Nao foi possivel excluir os albuns selecionados.', 'ml-gallery-pro' ) );
		}

		return $deleted;
	}

	/**
	 * Returns album items.
	 *
	 * @param int $album_id Album ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_album_items( int $album_id ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table('album_items')} WHERE album_id = %d ORDER BY sort_order ASC, id ASC",
			$album_id
		);

		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->normalize_collection( $results );
	}

	/**
	 * Returns plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		return wp_parse_args( (array) get_option( 'mlgp_settings', [] ), Installer::default_settings() );
	}

	/**
	 * Returns allowed server import roots for admin UI.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_server_import_sources(): array {
		return $this->storage->get_server_import_roots();
	}

	/**
	 * Returns a validation report for the admin diagnostics tab.
	 *
	 * @return array<string, mixed>
	 */
	public function get_validation_report(): array {
		global $wpdb;

		$code_version    = (string) MLGP_VERSION;
		$stored_version  = (string) get_option( 'mlgp_version', '' );
		$plugin_basename = plugin_basename( MLGP_FILE );
		$plugin_dir_name = basename( dirname( MLGP_FILE ) );
		$storage_dir     = wp_normalize_path( $this->storage->base_dir() );
		$storage_url     = $this->storage->base_url();
		$storage_exists  = is_dir( $storage_dir );
		$storage_writable = $storage_exists && ( function_exists( 'wp_is_writable' ) ? wp_is_writable( $storage_dir ) : is_writable( $storage_dir ) );
		$protection_files = [
			trailingslashit( $storage_dir ) . 'index.php'  => 'index.php',
			trailingslashit( $storage_dir ) . '.htaccess'  => '.htaccess',
			trailingslashit( $storage_dir ) . 'web.config' => 'web.config',
		];
		$present_protection = [];

		foreach ( $protection_files as $file_path => $label ) {
			if ( file_exists( $file_path ) ) {
				$present_protection[] = $label;
			}
		}

		$existing_tables = [];
		$missing_tables  = [];

		foreach ( $this->tables as $table_key => $table_name ) {
			$found = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $table_name === $found ) {
				$existing_tables[] = $table_key;
			} else {
				$missing_tables[] = $table_key;
			}
		}

		$database_ready = count( $existing_tables ) === count( $this->tables );
		$stats          = $database_ready ? $this->get_stats() : [
			'galleries'     => 0,
			'albums'        => 0,
			'gallery_items' => 0,
			'album_items'   => 0,
		];
		$local_items_count = 0;
		$local_items_empty = 0;

		if ( $database_ready ) {
			$local_items_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('gallery_items')} WHERE storage = 'local' OR file_path != ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$local_items_empty = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('gallery_items')} WHERE (storage = 'local' OR file_path != '') AND (file_path IS NULL OR file_path = '')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$server_import_sources = $this->get_server_import_sources();
		$wp_version            = (string) get_bloginfo( 'version' );
		$php_version           = (string) PHP_VERSION;
		$max_upload_size       = size_format( (int) wp_max_upload_size() );
		$image_engine          = class_exists( '\Imagick' ) ? 'Imagick' : ( extension_loaded( 'gd' ) ? 'GD' : __( 'Nao detectado', 'ml-gallery-pro' ) );
		$has_image_engine      = class_exists( '\Imagick' ) || extension_loaded( 'gd' );

		$sections = [
			[
				'key'   => 'identity',
				'title' => __( 'Versao e update', 'ml-gallery-pro' ),
				'intro' => __( 'Confirma se a instalacao carregada continua na mesma linha de update do plugin.', 'ml-gallery-pro' ),
				'items' => [
					$this->build_validation_item(
						__( 'Versao em execucao', 'ml-gallery-pro' ),
						$code_version,
						sprintf(
							/* translators: %s: plugin basename. */
							__( 'Plugin carregado em %s.', 'ml-gallery-pro' ),
							$plugin_basename
						),
						'ok'
					),
					$this->build_validation_item(
						__( 'Versao registrada', 'ml-gallery-pro' ),
						$stored_version ? $stored_version : __( 'Nao registrada', 'ml-gallery-pro' ),
						$stored_version === $code_version
							? __( 'Banco e codigo estao sincronizados para a mesma atualizacao.', 'ml-gallery-pro' )
							: __( 'O banco ainda nao reflete a mesma versao carregada pelo plugin.', 'ml-gallery-pro' ),
						$stored_version === $code_version ? 'ok' : 'warning'
					),
					$this->build_validation_item(
						__( 'Basename do plugin', 'ml-gallery-pro' ),
						$plugin_basename,
						sprintf(
							/* translators: %s: plugin directory name. */
							__( 'Pasta atual: %s.', 'ml-gallery-pro' ),
							$plugin_dir_name
						),
						( 'ml-gallery-pro/ml-gallery-pro.php' === $plugin_basename && 'ml-gallery-pro' === $plugin_dir_name ) ? 'ok' : 'error'
					),
				],
			],
			[
				'key'   => 'storage',
				'title' => __( 'Storage proprio', 'ml-gallery-pro' ),
				'intro' => __( 'Verifica diretorio base, gravacao local e arquivos de protecao do acervo.', 'ml-gallery-pro' ),
				'items' => [
					$this->build_validation_item(
						__( 'Diretorio base', 'ml-gallery-pro' ),
						$storage_exists ? __( 'Ativo', 'ml-gallery-pro' ) : __( 'Ausente', 'ml-gallery-pro' ),
						$storage_dir,
						$storage_exists ? 'ok' : 'error'
					),
					$this->build_validation_item(
						__( 'Permissao de escrita', 'ml-gallery-pro' ),
						$storage_exists ? ( $storage_writable ? __( 'Liberada', 'ml-gallery-pro' ) : __( 'Bloqueada', 'ml-gallery-pro' ) ) : __( 'Indisponivel', 'ml-gallery-pro' ),
						$storage_exists
							? ( $storage_writable ? __( 'Uploads e derivados locais podem ser gravados normalmente.', 'ml-gallery-pro' ) : __( 'O diretorio existe, mas nao esta gravavel para o plugin.', 'ml-gallery-pro' ) )
							: __( 'Crie ou recupere o diretorio base do storage para continuar usando uploads locais.', 'ml-gallery-pro' ),
						$storage_exists ? ( $storage_writable ? 'ok' : 'error' ) : 'error'
					),
					$this->build_validation_item(
						__( 'Arquivos de protecao', 'ml-gallery-pro' ),
						sprintf( '%1$d/%2$d', count( $present_protection ), count( $protection_files ) ),
						count( $present_protection ) === count( $protection_files )
							? __( 'index.php, .htaccess e web.config presentes no storage base.', 'ml-gallery-pro' )
							: sprintf(
								/* translators: %s: protection file list. */
								__( 'Presentes: %s.', 'ml-gallery-pro' ),
								! empty( $present_protection ) ? implode( ', ', $present_protection ) : __( 'nenhum arquivo', 'ml-gallery-pro' )
							),
						count( $present_protection ) === count( $protection_files ) ? 'ok' : 'warning'
					),
					$this->build_validation_item(
						__( 'URL operacional', 'ml-gallery-pro' ),
						$storage_url,
						sprintf(
							/* translators: %d: import root count. */
							__( '%d origem(ns) de importacao disponivel(is) para o admin.', 'ml-gallery-pro' ),
							count( $server_import_sources )
						),
						! empty( $server_import_sources ) ? 'ok' : 'warning'
					),
				],
			],
			[
				'key'   => 'database',
				'title' => __( 'Banco e conteudo', 'ml-gallery-pro' ),
				'intro' => __( 'Confirma as tabelas principais e a consistencia do acervo cadastrado.', 'ml-gallery-pro' ),
				'items' => [
					$this->build_validation_item(
						__( 'Tabelas do plugin', 'ml-gallery-pro' ),
						sprintf( '%1$d/%2$d', count( $existing_tables ), count( $this->tables ) ),
						$database_ready
							? __( 'As quatro tabelas principais do ML Gallery Pro estao disponiveis.', 'ml-gallery-pro' )
							: sprintf(
								/* translators: %s: missing table keys. */
								__( 'Faltando: %s.', 'ml-gallery-pro' ),
								implode( ', ', $missing_tables )
							),
						$database_ready ? 'ok' : 'error'
					),
					$this->build_validation_item(
						__( 'Conteudo cadastrado', 'ml-gallery-pro' ),
						sprintf(
							/* translators: 1: gallery count, 2: album count. */
							__( '%1$d galerias / %2$d albuns', 'ml-gallery-pro' ),
							(int) $stats['galleries'],
							(int) $stats['albums']
						),
						sprintf(
							/* translators: 1: image count, 2: album item count. */
							__( '%1$d imagens e %2$d vinculos de album registrados.', 'ml-gallery-pro' ),
							(int) $stats['gallery_items'],
							(int) $stats['album_items']
						),
						$database_ready ? 'ok' : 'warning'
					),
					$this->build_validation_item(
						__( 'Itens locais rastreados', 'ml-gallery-pro' ),
						(string) $local_items_count,
						$local_items_empty > 0
							? sprintf(
								/* translators: %d: local items missing file path. */
								__( '%d item(ns) local(is) estao sem caminho fisico salvo e merecem revisao.', 'ml-gallery-pro' ),
								$local_items_empty
							)
							: ( $local_items_count > 0
								? __( 'Todos os itens locais possuem caminho fisico registrado.', 'ml-gallery-pro' )
								: __( 'Ainda nao ha itens locais, mas a estrutura esta pronta para receber imagens.', 'ml-gallery-pro' ) ),
						$local_items_empty > 0 ? 'warning' : 'ok'
					),
				],
			],
			[
				'key'   => 'environment',
				'title' => __( 'Ambiente do servidor', 'ml-gallery-pro' ),
				'intro' => __( 'Mostra os requisitos principais para uploads, processamento e execucao do plugin.', 'ml-gallery-pro' ),
				'items' => [
					$this->build_validation_item(
						__( 'WordPress', 'ml-gallery-pro' ),
						$wp_version,
						__( 'Requisito minimo do plugin: 6.0.', 'ml-gallery-pro' ),
						version_compare( $wp_version, '6.0', '>=' ) ? 'ok' : 'warning'
					),
					$this->build_validation_item(
						__( 'PHP', 'ml-gallery-pro' ),
						$php_version,
						__( 'Requisito minimo do plugin: 7.4.', 'ml-gallery-pro' ),
						version_compare( $php_version, '7.4', '>=' ) ? 'ok' : 'warning'
					),
					$this->build_validation_item(
						__( 'Limite de upload', 'ml-gallery-pro' ),
						$max_upload_size,
						__( 'Valor considerado no envio de imagens pelo admin.', 'ml-gallery-pro' ),
						wp_max_upload_size() >= ( 5 * MB_IN_BYTES ) ? 'ok' : 'warning'
					),
					$this->build_validation_item(
						__( 'Editor de imagem', 'ml-gallery-pro' ),
						$image_engine,
						$has_image_engine
							? __( 'Ha suporte ativo para gerar thumbs, medios, grandes e regeneracao.', 'ml-gallery-pro' )
							: __( 'Sem GD ou Imagick o processamento de imagens locais pode falhar.', 'ml-gallery-pro' ),
						$has_image_engine ? 'ok' : 'error'
					),
				],
			],
		];

		return [
			'summary'  => $this->count_validation_statuses( $sections ),
			'sections' => $sections,
		];
	}

	/**
	 * Saves plugin settings.
	 *
	 * @param array<string, mixed> $input Settings input.
	 * @return array<string, mixed>
	 */
	/**
	 * Resets all plugin data to factory defaults.
	 *
	 * @return true|\WP_Error
	 */
	public function factory_reset() {
		global $wpdb;

		$tables = [
			$this->table( 'album_items' ),
			$this->table( 'gallery_items' ),
			$this->table( 'albums' ),
			$this->table( 'galleries' ),
		];

		foreach ( $tables as $table ) {
			if ( '' === $table ) {
				continue;
			}

			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$storage_reset = $this->storage->reset_all_storage();

		if ( is_wp_error( $storage_reset ) ) {
			return $storage_reset;
		}

		update_option( 'mlgp_settings', Installer::default_settings() );
		update_option( 'mlgp_version', MLGP_VERSION );

		return true;
	}

	public function save_settings( array $input ): array {
		$defaults = Installer::default_settings();
		$current  = $this->get_settings();

		$settings = [
			'columns_desktop'       => $this->sanitize_integer( $input['columns_desktop'] ?? $current['columns_desktop'], 1, 8 ),
			'columns_tablet'        => $this->sanitize_integer( $input['columns_tablet'] ?? $current['columns_tablet'], 1, 6 ),
			'columns_mobile'        => $this->sanitize_integer( $input['columns_mobile'] ?? $current['columns_mobile'], 1, 4 ),
			'card_gap'              => $this->sanitize_integer( $input['card_gap'] ?? $current['card_gap'], 0, 48 ),
			'card_padding'          => $this->sanitize_integer( $input['card_padding'] ?? $current['card_padding'], 0, 80 ),
			'card_margin'           => $this->sanitize_integer( $input['card_margin'] ?? $current['card_margin'], 0, 40 ),
			'card_border_width'     => $this->sanitize_integer( $input['card_border_width'] ?? $current['card_border_width'], 0, 20 ),
			'card_border_color'     => $this->sanitize_hex_color_value( $input['card_border_color'] ?? $current['card_border_color'], '#d7e0ea' ),
			'card_border_opacity'   => $this->sanitize_integer( $input['card_border_opacity'] ?? $current['card_border_opacity'], 0, 100 ),
			'gap_background_color'  => $this->sanitize_hex_color_value( $input['gap_background_color'] ?? $current['gap_background_color'], '#ffffff' ),
			'gap_background_opacity'=> $this->sanitize_integer( $input['gap_background_opacity'] ?? $current['gap_background_opacity'], 0, 100 ),
			'wrapper_padding'       => $this->sanitize_integer( $input['wrapper_padding'] ?? $current['wrapper_padding'], 0, 120 ),
			'wrapper_radius'        => $this->sanitize_integer( $input['wrapper_radius'] ?? $current['wrapper_radius'], 0, 80 ),
			'wrapper_border_width'  => $this->sanitize_integer( $input['wrapper_border_width'] ?? $current['wrapper_border_width'], 0, 20 ),
			'wrapper_border_color'  => $this->sanitize_hex_color_value( $input['wrapper_border_color'] ?? $current['wrapper_border_color'], '#ffffff' ),
			'wrapper_border_opacity'=> $this->sanitize_integer( $input['wrapper_border_opacity'] ?? $current['wrapper_border_opacity'], 0, 100 ),
			'wrapper_background_color'  => $this->sanitize_hex_color_value( $input['wrapper_background_color'] ?? $current['wrapper_background_color'], '#ffffff' ),
			'wrapper_background_opacity'=> $this->sanitize_integer( $input['wrapper_background_opacity'] ?? $current['wrapper_background_opacity'], 0, 100 ),
			'wrapper_shadow_opacity'=> $this->sanitize_integer( $input['wrapper_shadow_opacity'] ?? $current['wrapper_shadow_opacity'], 0, 100 ),
			'wrapper_max_width'     => $this->sanitize_integer( $input['wrapper_max_width'] ?? $current['wrapper_max_width'], 0, 3840 ),
			'default_gallery_preset'=> $this->sanitize_choice(
				$input['default_gallery_preset'] ?? $current['default_gallery_preset'],
				[ 'masonry-default', 'clean-grid', 'editorial-tile', 'impact-mosaic', 'story-justified', 'showcase-filmstrip' ],
				'masonry-default'
			),
			'default_album_display_type' => $this->sanitize_choice(
				$input['default_album_display_type'] ?? $current['default_album_display_type'] ?? $defaults['default_album_display_type'],
				[ 'grid_plus', 'grid', 'masonry', 'mosaic', 'tile', 'justified' ],
				'grid'
			),
			'album_columns_desktop'   => $this->sanitize_integer( $input['album_columns_desktop'] ?? $current['album_columns_desktop'] ?? $defaults['album_columns_desktop'], 1, 8 ),
			'album_columns_tablet'    => $this->sanitize_integer( $input['album_columns_tablet'] ?? $current['album_columns_tablet'] ?? $defaults['album_columns_tablet'], 1, 6 ),
			'album_columns_mobile'    => $this->sanitize_integer( $input['album_columns_mobile'] ?? $current['album_columns_mobile'] ?? $defaults['album_columns_mobile'], 1, 4 ),
			'album_card_gap'          => $this->sanitize_integer( $input['album_card_gap'] ?? $current['album_card_gap'] ?? $defaults['album_card_gap'], 0, 48 ),
			'album_card_padding'      => $this->sanitize_integer( $input['album_card_padding'] ?? $current['album_card_padding'] ?? $defaults['album_card_padding'], 0, 80 ),
			'album_card_margin'       => $this->sanitize_integer( $input['album_card_margin'] ?? $current['album_card_margin'] ?? $defaults['album_card_margin'], 0, 40 ),
			'album_card_border_width' => $this->sanitize_integer( $input['album_card_border_width'] ?? $current['album_card_border_width'] ?? $defaults['album_card_border_width'], 0, 20 ),
			'album_card_border_color' => $this->sanitize_hex_color_value( $input['album_card_border_color'] ?? $current['album_card_border_color'] ?? $defaults['album_card_border_color'], '#d7e0ea' ),
			'album_card_border_opacity' => $this->sanitize_integer( $input['album_card_border_opacity'] ?? $current['album_card_border_opacity'] ?? $defaults['album_card_border_opacity'], 0, 100 ),
			'album_gap_background_color' => $this->sanitize_hex_color_value( $input['album_gap_background_color'] ?? $current['album_gap_background_color'] ?? $defaults['album_gap_background_color'], '#ffffff' ),
			'album_gap_background_opacity' => $this->sanitize_integer( $input['album_gap_background_opacity'] ?? $current['album_gap_background_opacity'] ?? $defaults['album_gap_background_opacity'], 0, 100 ),
			'album_card_radius'       => $this->sanitize_integer( $input['album_card_radius'] ?? $current['album_card_radius'] ?? $defaults['album_card_radius'], 0, 80 ),
			'album_pagination_enabled' => $this->resolve_setting_flag( $input, 'album_pagination_enabled', $current['album_pagination_enabled'] ?? $defaults['album_pagination_enabled'] ?? 1 ),
			'album_items_per_page'    => $this->sanitize_integer( $input['album_items_per_page'] ?? $current['album_items_per_page'] ?? $defaults['album_items_per_page'], 1, 5000 ),
			'album_show_titles'       => $this->resolve_setting_flag( $input, 'album_show_titles', $current['album_show_titles'] ?? $defaults['album_show_titles'] ?? 1 ),
			'album_show_captions'     => $this->resolve_setting_flag( $input, 'album_show_captions', $current['album_show_captions'] ?? $defaults['album_show_captions'] ?? 0 ),
			'album_show_heading'      => $this->resolve_setting_flag( $input, 'album_show_heading', $current['album_show_heading'] ?? $defaults['album_show_heading'] ?? 0 ),
			'album_show_description'  => $this->resolve_setting_flag( $input, 'album_show_description', $current['album_show_description'] ?? $defaults['album_show_description'] ?? 0 ),
			'album_item_title_font_size' => $this->sanitize_integer( $input['album_item_title_font_size'] ?? $current['album_item_title_font_size'] ?? $defaults['album_item_title_font_size'], 10, 48 ),
			'album_item_title_color' => $this->sanitize_hex_color_value( $input['album_item_title_color'] ?? $current['album_item_title_color'] ?? $defaults['album_item_title_color'], '#172033' ),
			'album_nav_button_enabled' => $this->resolve_setting_flag( $input, 'album_nav_button_enabled', $current['album_nav_button_enabled'] ?? $defaults['album_nav_button_enabled'] ?? 1 ),
			'album_nav_button_bg_color' => $this->sanitize_optional_hex_color_value( $input['album_nav_button_bg_color'] ?? $current['album_nav_button_bg_color'] ?? $defaults['album_nav_button_bg_color'] ?? '' ),
			'album_nav_button_text_color' => $this->sanitize_optional_hex_color_value( $input['album_nav_button_text_color'] ?? $current['album_nav_button_text_color'] ?? $defaults['album_nav_button_text_color'] ?? '' ),
			'album_nav_button_border_color' => $this->sanitize_optional_hex_color_value( $input['album_nav_button_border_color'] ?? $current['album_nav_button_border_color'] ?? $defaults['album_nav_button_border_color'] ?? '' ),
			'album_nav_button_hover_bg_color' => $this->sanitize_optional_hex_color_value( $input['album_nav_button_hover_bg_color'] ?? $current['album_nav_button_hover_bg_color'] ?? $defaults['album_nav_button_hover_bg_color'] ?? '' ),
			'album_nav_button_hover_text_color' => $this->sanitize_optional_hex_color_value( $input['album_nav_button_hover_text_color'] ?? $current['album_nav_button_hover_text_color'] ?? $defaults['album_nav_button_hover_text_color'] ?? '' ),
			'album_nav_button_align' => $this->sanitize_choice(
				$input['album_nav_button_align'] ?? $current['album_nav_button_align'] ?? $defaults['album_nav_button_align'] ?? 'left',
				[ 'left', 'center', 'right' ],
				'left'
			),
			'album_nav_button_position' => $this->sanitize_choice(
				$input['album_nav_button_position'] ?? $current['album_nav_button_position'] ?? $defaults['album_nav_button_position'] ?? 'top',
				[ 'top', 'bottom', 'both' ],
				'top'
			),
			'enable_frontend_filters' => $this->resolve_setting_flag( $input, 'enable_frontend_filters', $current['enable_frontend_filters'] ?? 0 ),
			'items_per_page'        => $this->sanitize_integer( $input['items_per_page'] ?? $current['items_per_page'], 1, 5000 ),
			'pagination_enabled'    => $this->resolve_setting_flag( $input, 'pagination_enabled', $current['pagination_enabled'] ?? 0 ),
			'show_titles'          => $this->resolve_setting_flag( $input, 'show_titles', $current['show_titles'] ?? 0 ),
			'show_captions'        => $this->resolve_setting_flag( $input, 'show_captions', $current['show_captions'] ?? 0 ),
			'show_item_tags'       => $this->resolve_setting_flag( $input, 'show_item_tags', $current['show_item_tags'] ?? 0 ),
			'hide_all_titles'      => $this->resolve_setting_flag( $input, 'hide_all_titles', $current['hide_all_titles'] ?? 0 ),
			'show_gallery_heading' => $this->resolve_setting_flag( $input, 'show_gallery_heading', $current['show_gallery_heading'] ?? 1 ),
			'show_gallery_description' => $this->resolve_setting_flag( $input, 'show_gallery_description', $current['show_gallery_description'] ?? 1 ),
			'image_quality'         => $this->sanitize_integer( $input['image_quality'] ?? $current['image_quality'], 30, 100 ),
			'thumb_width'           => $this->sanitize_integer( $input['thumb_width'] ?? $current['thumb_width'], 80, 2400 ),
			'thumb_height'          => $this->sanitize_integer( $input['thumb_height'] ?? $current['thumb_height'], 80, 2400 ),
			'thumb_crop'            => $this->resolve_setting_flag( $input, 'thumb_crop', $current['thumb_crop'] ?? 0 ),
			'medium_width'          => $this->sanitize_integer( $input['medium_width'] ?? $current['medium_width'], 120, 3600 ),
			'medium_height'         => $this->sanitize_integer( $input['medium_height'] ?? $current['medium_height'], 120, 3600 ),
			'large_width'           => $this->sanitize_integer( $input['large_width'] ?? $current['large_width'], 240, 5200 ),
			'large_height'          => $this->sanitize_integer( $input['large_height'] ?? $current['large_height'], 240, 5200 ),
			'album_cover_width'     => $this->sanitize_integer( $input['album_cover_width'] ?? $current['album_cover_width'], 120, 1800 ),
			'album_cover_height'    => $this->sanitize_integer( $input['album_cover_height'] ?? $current['album_cover_height'], 120, 1200 ),
			'album_cover_fit'       => $this->sanitize_choice(
				$input['album_cover_fit'] ?? $current['album_cover_fit'],
				[ 'contain', 'cover' ],
				'contain'
			),
			'album_cover_lock_ratio' => $this->resolve_setting_flag( $input, 'album_cover_lock_ratio', $current['album_cover_lock_ratio'] ?? 1 ),
			'watermark_enabled'     => $this->resolve_setting_flag( $input, 'watermark_enabled', $current['watermark_enabled'] ?? 0 ),
			'watermark_text'        => sanitize_text_field( wp_unslash( (string) ( $input['watermark_text'] ?? $current['watermark_text'] ) ) ),
			'watermark_opacity'     => $this->sanitize_integer( $input['watermark_opacity'] ?? $current['watermark_opacity'], 10, 95 ),
			'watermark_position'    => $this->sanitize_choice(
				$input['watermark_position'] ?? $current['watermark_position'],
				[ 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center' ],
				'bottom-right'
			),
			'rounded_corners'       => $this->resolve_setting_flag( $input, 'rounded_corners', $current['rounded_corners'] ?? 0 ),
			'slideshow_show_arrows' => $this->resolve_setting_flag( $input, 'slideshow_show_arrows', $current['slideshow_show_arrows'] ?? 1 ),
			'slideshow_show_thumbs' => $this->resolve_setting_flag( $input, 'slideshow_show_thumbs', $current['slideshow_show_thumbs'] ?? 1 ),
			'nav_arrow_prev_url'    => esc_url_raw( trim( (string) ( $input['nav_arrow_prev_url'] ?? $current['nav_arrow_prev_url'] ?? '' ) ) ),
			'nav_arrow_next_url'    => esc_url_raw( trim( (string) ( $input['nav_arrow_next_url'] ?? $current['nav_arrow_next_url'] ?? '' ) ) ),
			'heading_font_size'     => $this->sanitize_integer( $input['heading_font_size'] ?? $current['heading_font_size'], 20, 96 ),
			'heading_color'         => $this->sanitize_hex_color_value( $input['heading_color'] ?? $current['heading_color'], '#172033' ),
			'item_title_font_size'  => $this->sanitize_integer( $input['item_title_font_size'] ?? $current['item_title_font_size'], 10, 48 ),
			'item_title_color'      => $this->sanitize_hex_color_value( $input['item_title_color'] ?? $current['item_title_color'], '#172033' ),
			'enable_lightbox'       => $this->resolve_setting_flag( $input, 'enable_lightbox', $current['enable_lightbox'] ?? 1 ),
			'enable_lazy_load'      => $this->resolve_setting_flag( $input, 'enable_lazy_load', $current['enable_lazy_load'] ?? 1 ),
			'label_view_gallery'    => sanitize_text_field( wp_unslash( (string) ( $input['label_view_gallery'] ?? $current['label_view_gallery'] ) ) ),
			'label_back_to_album'   => sanitize_text_field( wp_unslash( (string) ( $input['label_back_to_album'] ?? $current['label_back_to_album'] ) ) ),
			'empty_gallery_message' => sanitize_text_field( wp_unslash( (string) ( $input['empty_gallery_message'] ?? $current['empty_gallery_message'] ) ) ),
			'empty_album_message'   => sanitize_text_field( wp_unslash( (string) ( $input['empty_album_message'] ?? $current['empty_album_message'] ) ) ),
		];

		$settings = wp_parse_args( $settings, $defaults );

		update_option( 'mlgp_settings', $settings );

		return $settings;
	}


	/**
	 * Applies the current global album defaults to all existing albums.
	 *
	 * @param array<string, mixed> $settings Sanitized global settings.
	 * @return int|\WP_Error
	 */
	public function apply_settings_to_all_albums( array $settings ) {
		global $wpdb;

		$albums = $this->get_albums();

		if ( empty( $albums ) ) {
			return 0;
		}

		$updated_count = 0;
		$album_defaults = [
			'columns_desktop'      => (int) ( $settings['album_columns_desktop'] ?? 4 ),
			'columns_tablet'       => (int) ( $settings['album_columns_tablet'] ?? 3 ),
			'columns_mobile'       => (int) ( $settings['album_columns_mobile'] ?? 2 ),
			'card_gap'             => (int) ( $settings['album_card_gap'] ?? 18 ),
			'card_padding'         => (int) ( $settings['album_card_padding'] ?? 0 ),
			'card_margin'          => (int) ( $settings['album_card_margin'] ?? 0 ),
			'card_border_width'    => (int) ( $settings['album_card_border_width'] ?? 0 ),
			'card_border_color'    => (string) ( $settings['album_card_border_color'] ?? '#d7e0ea' ),
			'card_border_opacity'  => (int) ( $settings['album_card_border_opacity'] ?? 100 ),
			'gap_background_color' => (string) ( $settings['album_gap_background_color'] ?? '#ffffff' ),
			'gap_background_opacity' => (int) ( $settings['album_gap_background_opacity'] ?? 100 ),
			'card_radius'          => (int) ( $settings['album_card_radius'] ?? 0 ),
			'rounded_corners'      => ! empty( $settings['album_card_radius'] ) ? 1 : 0,
			'pagination_enabled'   => ! empty( $settings['album_pagination_enabled'] ) ? 1 : 0,
			'items_per_page'       => (int) ( $settings['album_items_per_page'] ?? 18 ),
			'show_heading'         => ! empty( $settings['album_show_heading'] ) ? 1 : 0,
			'show_description'     => ! empty( $settings['album_show_description'] ) ? 1 : 0,
			'album_item_title_font_size' => (int) ( $settings['album_item_title_font_size'] ?? 18 ),
			'album_item_title_color' => (string) ( $settings['album_item_title_color'] ?? '#172033' ),
			'show_titles'          => ! empty( $settings['album_show_titles'] ) ? 1 : 0,
			'show_captions'        => ! empty( $settings['album_show_captions'] ) ? 1 : 0,
			'album_cover_width'    => (int) ( $settings['album_cover_width'] ?? 360 ),
			'album_cover_height'   => (int) ( $settings['album_cover_height'] ?? 280 ),
			'album_cover_fit'      => (string) ( $settings['album_cover_fit'] ?? 'contain' ),
			'album_cover_lock_ratio' => ! empty( $settings['album_cover_lock_ratio'] ) ? 1 : 0,
			'heading_font_size'    => (int) ( $settings['heading_font_size'] ?? 34 ),
			'heading_color'        => (string) ( $settings['heading_color'] ?? '#172033' ),
			'item_title_font_size' => (int) ( $settings['album_item_title_font_size'] ?? $settings['item_title_font_size'] ?? 18 ),
			'item_title_color'     => (string) ( $settings['album_item_title_color'] ?? $settings['item_title_color'] ?? '#172033' ),
			'justified_row_height' => 220,
		];

		foreach ( $albums as $album ) {
			$album_id = (int) ( $album['id'] ?? 0 );
			if ( $album_id <= 0 ) {
				continue;
			}
			$existing_settings = isset( $album['settings'] ) && is_array( $album['settings'] ) ? $album['settings'] : [];
			$merged_settings = array_merge( $existing_settings, $album_defaults );
			$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$this->table( 'albums' ),
				[
					'display_type'  => $this->sanitize_choice( $settings['default_album_display_type'] ?? 'grid', [ 'grid_plus', 'grid', 'masonry', 'mosaic', 'tile', 'justified' ], 'grid' ),
					'settings_json' => wp_json_encode( $merged_settings ),
					'updated_at'    => current_time( 'mysql' ),
				],
				[ 'id' => $album_id ]
			);
			if ( false === $updated ) {
				return new \WP_Error( 'mlgp_album_bulk_update_failed', __( 'Não foi possível aplicar a configuração em todos os álbuns.', 'ml-gallery-pro' ) );
			}
			++$updated_count;
		}

		return $updated_count;
	}

	/**
	 * Applies the current global display defaults to all existing galleries.
	 *
	 * @param array<string, mixed> $settings Sanitized global settings.
	 * @return int|\WP_Error
	 */
	public function apply_settings_to_all_galleries( array $settings ) {
		global $wpdb;

		$galleries = $this->get_galleries();

		if ( empty( $galleries ) ) {
			return 0;
		}

		$preset_id = (string) ( $settings['default_gallery_preset'] ?? 'masonry-default' );
		$preset_map = [
			'masonry-default' => [
				'display_type' => 'masonry',
				'columns_desktop' => 4,
				'columns_tablet' => 3,
				'columns_mobile' => 2,
				'card_gap' => 0,
				'card_padding' => 0,
				'card_margin' => 0,
				'card_border_width' => 0,
				'card_border_color' => '#d7e0ea',
				'card_border_opacity' => 100,
				'gap_background_color' => '#ffffff',
				'gap_background_opacity' => 100,
				'rounded_corners' => 0,
				'pagination_enabled' => 1,
				'items_per_page' => 24,
				'show_titles' => 0,
				'show_captions' => 0,
				'show_item_tags' => 0,
				'show_heading' => 0,
				'show_description' => 0,
			],
			'clean-grid' => [ 'display_type' => 'grid' ],
			'editorial-tile' => [ 'display_type' => 'tile' ],
			'impact-mosaic' => [ 'display_type' => 'mosaic' ],
			'story-justified' => [ 'display_type' => 'justified' ],
			'showcase-filmstrip' => [ 'display_type' => 'filmstrip' ],
		];

		$gallery_settings = [
			'columns_desktop' => (int) ( $settings['columns_desktop'] ?? 4 ),
			'columns_tablet' => (int) ( $settings['columns_tablet'] ?? 3 ),
			'columns_mobile' => (int) ( $settings['columns_mobile'] ?? 2 ),
			'card_gap' => (int) ( $settings['card_gap'] ?? 0 ),
			'card_padding' => (int) ( $settings['card_padding'] ?? 0 ),
			'card_margin' => (int) ( $settings['card_margin'] ?? 0 ),
			'card_border_width' => (int) ( $settings['card_border_width'] ?? 0 ),
			'card_border_color' => (string) ( $settings['card_border_color'] ?? '#d7e0ea' ),
			'card_border_opacity' => (int) ( $settings['card_border_opacity'] ?? 100 ),
			'gap_background_color' => (string) ( $settings['gap_background_color'] ?? '#ffffff' ),
			'gap_background_opacity' => (int) ( $settings['gap_background_opacity'] ?? 100 ),
			'wrapper_padding' => (int) ( $settings['wrapper_padding'] ?? 0 ),
			'wrapper_radius' => (int) ( $settings['wrapper_radius'] ?? 0 ),
			'wrapper_border_width' => (int) ( $settings['wrapper_border_width'] ?? 0 ),
			'wrapper_border_color' => (string) ( $settings['wrapper_border_color'] ?? '#ffffff' ),
			'wrapper_border_opacity' => (int) ( $settings['wrapper_border_opacity'] ?? 0 ),
			'wrapper_background_color' => (string) ( $settings['wrapper_background_color'] ?? '#ffffff' ),
			'wrapper_background_opacity' => (int) ( $settings['wrapper_background_opacity'] ?? 0 ),
			'wrapper_shadow_opacity' => (int) ( $settings['wrapper_shadow_opacity'] ?? 0 ),
			'wrapper_max_width' => (int) ( $settings['wrapper_max_width'] ?? 0 ),
			'rounded_corners' => ! empty( $settings['rounded_corners'] ) ? 1 : 0,
			'enable_frontend_filters' => ! empty( $settings['enable_frontend_filters'] ) ? 1 : 0,
			'pagination_enabled' => ! empty( $settings['pagination_enabled'] ) ? 1 : 0,
			'items_per_page' => (int) ( $settings['items_per_page'] ?? 24 ),
			'show_heading' => ! empty( $settings['show_gallery_heading'] ) ? 1 : 0,
			'show_description' => ! empty( $settings['show_gallery_description'] ) ? 1 : 0,
			'show_titles' => ! empty( $settings['show_titles'] ) ? 1 : 0,
			'show_captions' => ! empty( $settings['show_captions'] ) ? 1 : 0,
			'show_item_tags' => ! empty( $settings['show_item_tags'] ) ? 1 : 0,
			'heading_font_size' => (int) ( $settings['heading_font_size'] ?? 34 ),
			'heading_color' => (string) ( $settings['heading_color'] ?? '#172033' ),
			'item_title_font_size' => (int) ( $settings['item_title_font_size'] ?? 18 ),
			'item_title_color' => (string) ( $settings['item_title_color'] ?? '#172033' ),
			'justified_row_height' => 220,
			'slideshow_autoplay' => 1,
			'slideshow_show_arrows' => ! empty( $settings['slideshow_show_arrows'] ) ? 1 : 0,
			'slideshow_show_thumbs' => ! empty( $settings['slideshow_show_thumbs'] ) ? 1 : 0,
			'slideshow_interval' => 4000,
		];

		if ( isset( $preset_map[ $preset_id ] ) ) {
			$preset_settings = $preset_map[ $preset_id ];
			$gallery_settings = array_merge( $preset_settings, $gallery_settings );
		}

		$display_type = isset( $gallery_settings['display_type'] ) ? $this->sanitize_display_type( $gallery_settings['display_type'] ) : 'masonry';
		unset( $gallery_settings['display_type'] );

		$updated = 0;
		$current_time = current_time( 'mysql' );

		foreach ( $galleries as $gallery ) {
			$gallery_id = (int) ( $gallery['id'] ?? 0 );
			if ( $gallery_id <= 0 ) {
				continue;
			}

			$result = $wpdb->update(
				$this->table( 'galleries' ),
				[
					'display_type' => $display_type,
					'settings_json' => wp_json_encode( $gallery_settings ),
					'updated_at' => $current_time,
				],
				[ 'id' => $gallery_id ]
			);

			if ( false === $result ) {
				return new \WP_Error( 'mlgp_apply_defaults_failed', __( 'Nao foi possivel aplicar a configuracao em todas as galerias.', 'ml-gallery-pro' ) );
			}

			$updated++;
		}

		return $updated;
	}

	/**
	 * Builds a single diagnostics item for the validation tab.
	 *
	 * @param string $label  Human label.
	 * @param string $value  Main value.
	 * @param string $detail Supporting detail.
	 * @param string $status ok|warning|error
	 * @return array<string, string>
	 */
	private function build_validation_item( string $label, string $value, string $detail, string $status ): array {
		return [
			'label'  => $label,
			'value'  => $value,
			'detail' => $detail,
			'status' => in_array( $status, [ 'ok', 'warning', 'error' ], true ) ? $status : 'warning',
		];
	}

	/**
	 * Counts diagnostics items by status.
	 *
	 * @param array<int, array<string, mixed>> $sections Report sections.
	 * @return array<string, int>
	 */
	private function count_validation_statuses( array $sections ): array {
		$summary = [
			'ok'      => 0,
			'warning' => 0,
			'error'   => 0,
		];

		foreach ( $sections as $section ) {
			$items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : [];

			foreach ( $items as $item ) {
				$status = isset( $item['status'] ) ? (string) $item['status'] : 'warning';

				if ( isset( $summary[ $status ] ) ) {
					$summary[ $status ]++;
				}
			}
		}

		return $summary;
	}


	/**
	 * Regenerates a batch of local image variants using the current global settings.
	 *
	 * @param int $offset Query offset.
	 * @param int $limit Query limit.
	 * @return array<string, int|bool>|\WP_Error
	 */
	public function regenerate_local_items_batch( int $offset = 0, int $limit = 20 ) {
		global $wpdb;

		$offset = max( 0, $offset );
		$limit  = max( 1, min( 100, $limit ) );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('gallery_items')} WHERE storage = 'local' OR file_path != ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $total <= 0 ) {
			return new \WP_Error( 'mlgp_global_regenerate_empty', __( 'Nenhuma imagem local foi encontrada para regeneracao global.', 'ml-gallery-pro' ) );
		}

		$rows = $this->normalize_collection(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table('gallery_items')} WHERE storage = 'local' OR file_path != '' ORDER BY gallery_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);

		if ( empty( $rows ) ) {
			return [
				'processed'   => 0,
				'failed'      => 0,
				'total'       => $total,
				'next_offset' => $offset,
				'done'        => true,
			];
		}

		$current_time      = current_time( 'mysql' );
		$processed         = 0;
		$failed            = 0;
		$touched_galleries = [];

		foreach ( $rows as $row ) {
			if ( 'local' !== ( $row['storage'] ?? '' ) && empty( $row['file_path'] ) ) {
				++$failed;
				continue;
			}

			$payload = $this->storage->regenerate_item_payload( $row );

			if ( is_wp_error( $payload ) ) {
				++$failed;
				continue;
			}

			if ( ! $this->update_local_item_from_payload( $row, $payload, $current_time ) ) {
				++$failed;
				continue;
			}

			$gallery_id = (int) ( $row['gallery_id'] ?? 0 );

			if ( $gallery_id > 0 ) {
				$touched_galleries[ $gallery_id ] = true;
			}

			++$processed;
		}

		foreach ( array_keys( $touched_galleries ) as $gallery_id ) {
			$this->update_gallery_meta(
				(int) $gallery_id,
				[
					'updated_at' => $current_time,
				]
			);
		}

		$next_offset = $offset + count( $rows );

		return [
			'processed'   => $processed,
			'failed'      => $failed,
			'total'       => $total,
			'next_offset' => $next_offset,
			'done'        => $next_offset >= $total,
		];
	}

	/**
	 * Regenerates all local image variants using the current global settings.
	 *
	 * @return array<string, int>|\WP_Error
	 */
	public function regenerate_all_local_items() {
		global $wpdb;

		$rows = $this->normalize_collection(
			$wpdb->get_results( "SELECT * FROM {$this->table('gallery_items')} WHERE storage = 'local' OR file_path != '' ORDER BY gallery_id ASC, sort_order ASC, id ASC" ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);

		if ( empty( $rows ) ) {
			return new \WP_Error( 'mlgp_global_regenerate_empty', __( 'Nenhuma imagem local foi encontrada para regeneracao global.', 'ml-gallery-pro' ) );
		}

		$current_time      = current_time( 'mysql' );
		$processed         = 0;
		$failed            = 0;
		$touched_galleries = [];

		foreach ( $rows as $row ) {
			if ( 'local' !== ( $row['storage'] ?? '' ) && empty( $row['file_path'] ) ) {
				++$failed;
				continue;
			}

			$payload = $this->storage->regenerate_item_payload( $row );

			if ( is_wp_error( $payload ) ) {
				++$failed;
				continue;
			}

			if ( ! $this->update_local_item_from_payload( $row, $payload, $current_time ) ) {
				++$failed;
				continue;
			}

			$gallery_id = (int) ( $row['gallery_id'] ?? 0 );

			if ( $gallery_id > 0 ) {
				$touched_galleries[ $gallery_id ] = true;
			}

			++$processed;
		}

		if ( 0 === $processed ) {
			return new \WP_Error( 'mlgp_global_regenerate_failed', __( 'Nao foi possivel regenerar nenhuma imagem local com as configuracoes atuais.', 'ml-gallery-pro' ) );
		}

		foreach ( array_keys( $touched_galleries ) as $gallery_id ) {
			$this->update_gallery_meta(
				(int) $gallery_id,
				[
					'updated_at' => $current_time,
				]
			);
		}

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'total'     => count( $rows ),
		];
	}

	/**
	 * Returns gallery items, optionally filtering visible items only.
	 *
	 * @param int  $gallery_id Gallery ID.
	 * @param bool $only_visible Whether to include only visible items.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_gallery_items_rows( int $gallery_id, bool $only_visible ): array {
		global $wpdb;

		$sql = "SELECT * FROM {$this->table('gallery_items')} WHERE gallery_id = %d";

		if ( $only_visible ) {
			$sql .= ' AND is_visible = 1';
		}

		$sql .= ' ORDER BY sort_order ASC, id ASC';
		$query = $wpdb->prepare( $sql, $gallery_id );

		return $this->normalize_collection(
			$wpdb->get_results( $query ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/**
	 * Returns the next sort order for gallery items.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return int
	 */
	private function get_next_gallery_item_order( int $gallery_id ): int {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT MAX(sort_order) FROM {$this->table('gallery_items')} WHERE gallery_id = %d", $gallery_id );
		$max = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $max + 1;
	}

	/**
	 * Persists one collection of local upload payloads as gallery items.
	 *
	 * @param int                        $gallery_id Gallery ID.
	 * @param array<string, mixed>       $gallery    Current gallery payload.
	 * @param array<int, array<string, mixed>> $uploads Local upload payloads.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function persist_local_upload_payloads( int $gallery_id, array $gallery, array $uploads ) {
		global $wpdb;

		if ( empty( $uploads ) ) {
			return new \WP_Error( 'mlgp_upload_persist_empty', __( 'Nenhuma imagem valida foi preparada para a galeria.', 'ml-gallery-pro' ) );
		}

		$sort_order       = $this->get_next_gallery_item_order( $gallery_id );
		$current_time     = current_time( 'mysql' );
		$created_ids      = [];
		$failed_examples  = [];
		$last_db_error    = '';

		// Build a case-insensitive filename index of existing items in this gallery
		// so duplicate uploads overwrite instead of creating a second row.
		$existing_rows       = $this->get_gallery_items_rows( $gallery_id, false );
		$existing_name_index = [];
		foreach ( $existing_rows as $existing_row ) {
			$key = strtolower( sanitize_file_name( (string) ( $existing_row['original_name'] ?? '' ) ) );
			if ( '' !== $key ) {
				$existing_name_index[ $key ] = $existing_row;
			}
			// Also index by file_name in case original_name differs.
			$key2 = strtolower( sanitize_file_name( (string) ( $existing_row['file_name'] ?? '' ) ) );
			if ( '' !== $key2 && ! isset( $existing_name_index[ $key2 ] ) ) {
				$existing_name_index[ $key2 ] = $existing_row;
			}
		}

		foreach ( $uploads as $upload ) {
			$original_name = $this->truncate_database_filename( (string) ( $upload['original_name'] ?? '' ), 255 );
			$file_name     = $this->truncate_database_filename( (string) ( $upload['file_name'] ?? '' ), 255 );
			$item_title    = $this->truncate_database_text( sanitize_text_field( (string) ( $upload['item_title'] ?? '' ) ), 255 );

			// Check for existing item with same filename in this gallery.
			$lookup_key   = strtolower( sanitize_file_name( $original_name ?: $file_name ) );
			$lookup_key2  = strtolower( sanitize_file_name( $file_name ?: $original_name ) );
			$existing_row = $existing_name_index[ $lookup_key ] ?? $existing_name_index[ $lookup_key2 ] ?? null;

			if ( null !== $existing_row ) {
				// Overwrite: update physical metadata, preserve editorial fields.
				$updated = $this->update_local_item_from_payload( $existing_row, $upload, $current_time );
				if ( $updated ) {
					$created_ids[] = (int) ( $existing_row['id'] ?? 0 );
					// Update index so a second file with same name in same batch also hits this row.
					$existing_name_index[ $lookup_key ]  = array_merge( $existing_row, $upload, [ 'id' => $existing_row['id'] ] );
					$existing_name_index[ $lookup_key2 ] = $existing_name_index[ $lookup_key ];
				}
				continue;
			}

			$payload = [
				'gallery_id'     => $gallery_id,
				'attachment_id'  => 0,
				'storage'        => 'local',
				'original_name'  => $original_name,
				'file_name'      => $file_name,
				'file_path'      => (string) ( $upload['file_path'] ?? '' ),
				'file_url'       => esc_url_raw( (string) ( $upload['file_url'] ?? '' ) ),
				'thumb_path'     => (string) ( $upload['thumb_path'] ?? '' ),
				'thumb_url'      => esc_url_raw( (string) ( $upload['thumb_url'] ?? '' ) ),
				'medium_path'    => (string) ( $upload['medium_path'] ?? '' ),
				'medium_url'     => esc_url_raw( (string) ( $upload['medium_url'] ?? '' ) ),
				'large_path'     => (string) ( $upload['large_path'] ?? '' ),
				'large_url'      => esc_url_raw( (string) ( $upload['large_url'] ?? '' ) ),
				'mime_type'      => sanitize_text_field( (string) ( $upload['mime_type'] ?? '' ) ),
				'width'          => absint( $upload['width'] ?? 0 ),
				'height'         => absint( $upload['height'] ?? 0 ),
				'file_size'      => absint( $upload['file_size'] ?? 0 ),
				'item_title'     => $item_title,
				'item_caption'   => '',
				'item_alt'       => '',
				'item_link'      => '',
				'item_tags'      => '',
				'is_visible'     => 1,
				'sort_order'     => $sort_order,
				'created_at'     => $current_time,
				'updated_at'     => $current_time,
			];

			$inserted = $wpdb->insert( $this->table( 'gallery_items' ), $payload ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false === $inserted ) {
				$last_db_error = (string) $wpdb->last_error;

				if ( count( $failed_examples ) < 3 ) {
					$failed_examples[] = $original_name ?: ( $file_name ?: __( 'imagem-sem-nome', 'ml-gallery-pro' ) );
				}

				$this->storage->remove_item_files( $upload );
				continue;
			}

			$new_id = (int) $wpdb->insert_id;
			$created_ids[] = $new_id;
			// Add newly inserted item to index so subsequent same-name uploads in this batch update it.
			$new_row = array_merge( $payload, [ 'id' => $new_id, 'gallery_id' => $gallery_id ] );
			$existing_name_index[ $lookup_key ]  = $new_row;
			$existing_name_index[ $lookup_key2 ] = $new_row;
			++$sort_order;
		}

		if ( empty( $created_ids ) ) {
			$message = __( 'As imagens foram preparadas, mas nao foi possivel registrar os itens da galeria.', 'ml-gallery-pro' );

			if ( '' !== $last_db_error ) {
				$message .= ' ' . sprintf(
					/* translators: %s: database error detail. */
					__( 'Detalhe do banco: %s', 'ml-gallery-pro' ),
					sanitize_text_field( $last_db_error )
				);
			}

			if ( ! empty( $failed_examples ) ) {
				$message .= ' ' . sprintf(
					/* translators: %s: sample file names. */
					__( 'Arquivos afetados: %s', 'ml-gallery-pro' ),
					implode( ', ', array_map( 'sanitize_text_field', $failed_examples ) )
				);
			}

			return new \WP_Error(
				'mlgp_upload_persist_failed',
				$message,
				[
					'gallery_id' => $gallery_id,
				]
			);
		}

		$cover_item_id = ! empty( $gallery['cover_item_id'] ) ? absint( $gallery['cover_item_id'] ) : (int) $created_ids[0];
		$cover_row     = $this->get_gallery_item_row( $gallery_id, $cover_item_id );

		$this->update_gallery_meta(
			$gallery_id,
			[
				'cover_item_id'       => $cover_item_id,
				'cover_attachment_id' => ! empty( $cover_row['attachment_id'] ) ? absint( $cover_row['attachment_id'] ) : 0,
				'updated_at'          => $current_time,
			]
		);

		$this->fire_after_items_stored_hook_safely( $uploads, $gallery_id );

		return $this->get_gallery_editor( $gallery_id );
	}

	/**
	 * Fires the ML Media Master integration hook without allowing accidental
	 * output/notices from listeners to corrupt admin-ajax JSON responses.
	 *
	 * The hook name and signature are permanent and must not be changed.
	 *
	 * @param array<int, array<string, mixed>> $uploads    Stored upload payloads.
	 * @param int                              $gallery_id Gallery ID.
	 */
	private function fire_after_items_stored_hook_safely( array $uploads, int $gallery_id ): void {
		// During AJAX, schedule the hook to fire in a separate WP-Cron request
		// so the JSON response is fully delivered before any heavy listener
		// (e.g. ML Media Optimizer WebP conversion via exec/Imagick) runs.
		// This completely isolates the hook execution from the HTTP response.
		if ( wp_doing_ajax() ) {
			$transient_key = 'mlgp_hook_' . $gallery_id . '_' . uniqid( '', true );
			set_transient( $transient_key, [ 'uploads' => $uploads, 'gallery_id' => $gallery_id ], 300 );
			wp_schedule_single_event( time(), 'mlgp_fire_after_items_stored', [ $transient_key ] );
			return;
		}

		// Non-AJAX: fire immediately with output buffer guard.
		$buffer_level = ob_get_level();

		try {
			ob_start();

			do_action( 'mlgp_after_items_stored', $uploads, $gallery_id );

			$output = '';

			if ( ob_get_level() > $buffer_level ) {
				$output = (string) ob_get_clean();
			}

			if ( '' !== trim( $output ) ) {
				error_log(
					sprintf(
						'[ML Gallery Pro] mlgp_after_items_stored emitted output during gallery %d import and it was suppressed to preserve JSON: %s',
						$gallery_id,
						wp_strip_all_tags( substr( $output, 0, 500 ) )
					)
				);
			}
		} catch ( \Throwable $throwable ) {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			error_log(
				sprintf(
					'[ML Gallery Pro] mlgp_after_items_stored listener failed for gallery %d: %s in %s:%d',
					$gallery_id,
					$throwable->getMessage(),
					$throwable->getFile(),
					$throwable->getLine()
				)
			);
		}
	}


	/**
	 * Updates one local gallery item from a fresh storage payload.
	 *
	 * @param array<string, mixed> $row          Current item row.
	 * @param array<string, mixed> $payload      Fresh payload from storage.
	 * @param string               $current_time Current timestamp.
	 * @return bool
	 */
	private function update_local_item_from_payload( array $row, array $payload, string $current_time ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table( 'gallery_items' ),
			[
				'original_name' => $this->truncate_database_filename( (string) ( $payload['original_name'] ?? ( $row['original_name'] ?? '' ) ), 255 ),
				'file_name'     => $this->truncate_database_filename( (string) ( $payload['file_name'] ?? ( $row['file_name'] ?? '' ) ), 255 ),
				'file_path'     => (string) ( $payload['file_path'] ?? ( $row['file_path'] ?? '' ) ),
				'file_url'      => esc_url_raw( (string) ( $payload['file_url'] ?? ( $row['file_url'] ?? '' ) ) ),
				'thumb_path'    => (string) ( $payload['thumb_path'] ?? ( $row['thumb_path'] ?? '' ) ),
				'thumb_url'     => esc_url_raw( (string) ( $payload['thumb_url'] ?? ( $row['thumb_url'] ?? '' ) ) ),
				'medium_path'   => (string) ( $payload['medium_path'] ?? ( $row['medium_path'] ?? '' ) ),
				'medium_url'    => esc_url_raw( (string) ( $payload['medium_url'] ?? ( $row['medium_url'] ?? '' ) ) ),
				'large_path'    => (string) ( $payload['large_path'] ?? ( $row['large_path'] ?? '' ) ),
				'large_url'     => esc_url_raw( (string) ( $payload['large_url'] ?? ( $row['large_url'] ?? '' ) ) ),
				'mime_type'     => sanitize_text_field( (string) ( $payload['mime_type'] ?? ( $row['mime_type'] ?? '' ) ) ),
				'width'         => absint( $payload['width'] ?? ( $row['width'] ?? 0 ) ),
				'height'        => absint( $payload['height'] ?? ( $row['height'] ?? 0 ) ),
				'file_size'     => absint( $payload['file_size'] ?? ( $row['file_size'] ?? 0 ) ),
				'updated_at'    => $current_time,
			],
			[
				'id'         => (int) ( $row['id'] ?? 0 ),
				'gallery_id' => (int) ( $row['gallery_id'] ?? 0 ),
			]
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $updated;
	}

	/**
	 * Updates gallery metadata fields.
	 *
	 * @param int                  $gallery_id Gallery ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return void
	 */
	private function update_gallery_meta( int $gallery_id, array $data ): void {
		global $wpdb;

		$wpdb->update( $this->table( 'galleries' ), $data, [ 'id' => $gallery_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Hydrates gallery items with media payloads and cover flags.
	 *
	 * @param array<int, array<string, mixed>> $items                    Raw items.
	 * @param int                              $cover_item_id            Stored cover item ID.
	 * @param int                              $legacy_cover_attachment  Legacy cover attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function hydrate_gallery_items( array $items, int $cover_item_id, int $legacy_cover_attachment ): array {
		foreach ( $items as &$item ) {
			$item['attachment'] = $this->get_item_media_payload( $item );
			$item['is_cover']   = $this->is_cover_item( $item, $cover_item_id, $legacy_cover_attachment );
			$item['tag_list']   = $this->parse_item_tags( (string) ( $item['item_tags'] ?? '' ) );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Resolves the gallery cover item from current rows.
	 *
	 * @param array<int, array<string, mixed>> $rows                    Gallery rows.
	 * @param int                              $stored_cover_item_id    Stored cover item ID.
	 * @param int                              $legacy_cover_attachment Legacy cover attachment ID.
	 * @return int
	 */
	private function resolve_gallery_cover_item_id_from_rows( array $rows, int $stored_cover_item_id, int $legacy_cover_attachment ): int {
		if ( empty( $rows ) ) {
			return 0;
		}

		$item_ids = array_map(
			static function ( array $row ): int {
				return (int) ( $row['id'] ?? 0 );
			},
			$rows
		);

		if ( $stored_cover_item_id > 0 && in_array( $stored_cover_item_id, $item_ids, true ) ) {
			return $stored_cover_item_id;
		}

		if ( $legacy_cover_attachment > 0 ) {
			foreach ( $rows as $row ) {
				if ( $legacy_cover_attachment === (int) ( $row['attachment_id'] ?? 0 ) ) {
					return (int) $row['id'];
				}
			}
		}

		return (int) $rows[0]['id'];
	}

	/**
	 * Resolves a valid cover item ID from an item list.
	 *
	 * @param array<int, int> $item_ids        Item IDs in current order.
	 * @param int             $requested_cover Requested cover item ID.
	 * @param int             $current_cover   Current stored cover item ID.
	 * @return int
	 */
	private function resolve_cover_item_id_from_ids( array $item_ids, int $requested_cover, int $current_cover ): int {
		$item_ids = array_values( array_filter( array_map( 'absint', $item_ids ) ) );

		if ( empty( $item_ids ) ) {
			return 0;
		}

		if ( $requested_cover > 0 && in_array( $requested_cover, $item_ids, true ) ) {
			return $requested_cover;
		}

		if ( $current_cover > 0 && in_array( $current_cover, $item_ids, true ) ) {
			return $current_cover;
		}

		return (int) $item_ids[0];
	}

	/**
	 * Extracts the current cover payload from hydrated items.
	 *
	 * @param array<int, array<string, mixed>> $items Hydrated items.
	 * @return array<string, mixed>|null
	 */
	private function extract_cover_payload( array $items ): ?array {
		foreach ( $items as $item ) {
			if ( ! empty( $item['is_cover'] ) && ! empty( $item['attachment'] ) && is_array( $item['attachment'] ) ) {
				return $item['attachment'];
			}
		}

		foreach ( $items as $item ) {
			if ( ! empty( $item['attachment'] ) && is_array( $item['attachment'] ) ) {
				return $item['attachment'];
			}
		}

		return null;
	}

	/**
	 * Determines whether a gallery item is the cover.
	 *
	 * @param array<string, mixed> $item                     Gallery item row.
	 * @param int                  $cover_item_id           Stored cover item ID.
	 * @param int                  $legacy_cover_attachment Legacy cover attachment ID.
	 * @return bool
	 */
	private function is_cover_item( array $item, int $cover_item_id, int $legacy_cover_attachment ): bool {
		if ( $cover_item_id > 0 ) {
			return $cover_item_id === (int) ( $item['id'] ?? 0 );
		}

		return $legacy_cover_attachment > 0 && $legacy_cover_attachment === (int) ( $item['attachment_id'] ?? 0 );
	}

	/**
	 * Returns a media payload from a local or legacy attachment item.
	 *
	 * @param array<string, mixed> $item Gallery item row.
	 * @return array<string, mixed>|null
	 */
	private function get_item_media_payload( array $item ): ?array {
		if ( 'local' === ( $item['storage'] ?? '' ) || ! empty( $item['file_url'] ) ) {
			return $this->get_local_media_payload( $item );
		}

		$attachment_id = (int) ( $item['attachment_id'] ?? 0 );

		return $attachment_id > 0 ? $this->get_attachment_payload( $attachment_id ) : null;
	}

	/**
	 * Returns local image payload.
	 *
	 * @param array<string, mixed> $item Gallery item row.
	 * @return array<string, mixed>|null
	 */
	private function get_local_media_payload( array $item ): ?array {
		$full_url = ! empty( $item['file_url'] ) ? esc_url_raw( (string) $item['file_url'] ) : '';

		if ( '' === $full_url ) {
			return null;
		}

		$title = ! empty( $item['item_title'] )
			? (string) $item['item_title']
			: (string) pathinfo( (string) ( $item['original_name'] ?: $item['file_name'] ), PATHINFO_FILENAME );

		return [
			'id'         => (int) ( $item['id'] ?? 0 ),
			'title'      => sanitize_text_field( $title ),
			'caption'    => wp_kses_post( (string) ( $item['item_caption'] ?? '' ) ),
			'alt'        => sanitize_text_field( (string) ( $item['item_alt'] ?? '' ) ),
			'filename'   => basename( (string) ( $item['file_name'] ?: $item['original_name'] ?: '' ) ),
			'thumb_url'  => ! empty( $item['thumb_url'] ) ? esc_url_raw( (string) $item['thumb_url'] ) : $full_url,
			'medium_url' => ! empty( $item['medium_url'] ) ? esc_url_raw( (string) $item['medium_url'] ) : ( ! empty( $item['large_url'] ) ? esc_url_raw( (string) $item['large_url'] ) : $full_url ),
			'large_url'  => ! empty( $item['large_url'] ) ? esc_url_raw( (string) $item['large_url'] ) : $full_url,
			'full_url'   => $full_url,
			'mime_type'  => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
			'width'      => (int) ( $item['width'] ?? 0 ),
			'height'     => (int) ( $item['height'] ?? 0 ),
		];
	}

	/**
	 * Returns attachment data for admin and frontend use.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>|null
	 */
	private function get_attachment_payload( int $attachment_id ): ?array {
		$attachment_post = get_post( $attachment_id );

		if ( ! $attachment_post || 'attachment' !== $attachment_post->post_type ) {
			return null;
		}

		return [
			'id'         => $attachment_id,
			'title'      => sanitize_text_field( $attachment_post->post_title ),
			'caption'    => wp_kses_post( $attachment_post->post_excerpt ),
			'alt'        => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'filename'   => basename( (string) get_attached_file( $attachment_id ) ),
			'thumb_url'  => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			'medium_url' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
			'large_url'  => wp_get_attachment_image_url( $attachment_id, 'large' ),
			'full_url'   => wp_get_attachment_image_url( $attachment_id, 'full' ),
			'mime_type'  => get_post_mime_type( $attachment_id ),
			'width'      => 0,
			'height'     => 0,
		];
	}

	/**
	 * Checks if the attachment is a valid image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_valid_image_attachment( int $attachment_id ): bool {
		return 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id );
	}

	/**
	 * Returns one gallery item row.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @param int $item_id    Item ID.
	 * @return array<string, mixed>|null
	 */
	private function get_gallery_item_row( int $gallery_id, int $item_id ): ?array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table('gallery_items')} WHERE gallery_id = %d AND id = %d LIMIT 1",
			$gallery_id,
			$item_id
		);

		return $this->normalize_entity(
			$wpdb->get_row( $sql ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/**
	 * Returns the first valid cover from an album tree.
	 *
	 * @param int             $album_id Album ID.
	 * @param array<int, int> $visited  Visited album IDs.
	 * @return array<string, mixed>|null
	 */
	private function get_album_cover_payload_recursive( int $album_id, array $visited ): ?array {
		if ( in_array( $album_id, $visited, true ) ) {
			return null;
		}

		$visited[] = $album_id;
		$album     = $this->get_album( $album_id );

		if ( empty( $album ) ) {
			return null;
		}

		if ( ! empty( $album['cover_attachment_id'] ) ) {
			$payload = $this->get_attachment_payload( (int) $album['cover_attachment_id'] );

			if ( $payload ) {
				return $payload;
			}
		}

		foreach ( $this->get_album_items( $album_id ) as $item ) {
			$item_type = $item['item_type'] ?? 'gallery';
			$item_id   = (int) ( $item['item_id'] ?? 0 );

			if ( $item_id <= 0 ) {
				continue;
			}

			if ( 'album' === $item_type ) {
				$payload = $this->get_album_cover_payload_recursive( $item_id, $visited );
			} else {
				$payload = $this->get_gallery_cover_payload( $item_id );
			}

			if ( $payload ) {
				return $payload;
			}
		}

		return null;
	}

	/**
	 * Hydrates album items for admin editor use.
	 *
	 * @param array<int, array<string, mixed>> $items    Raw album items.
	 * @param int                              $album_id Current album ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function hydrate_album_items_for_editor( array $items, int $album_id ): array {
		$hydrated = [];

		foreach ( $items as $item ) {
			$item_type = 'album' === ( $item['item_type'] ?? 'gallery' ) ? 'album' : 'gallery';
			$item_id   = (int) ( $item['item_id'] ?? 0 );

			if ( $item_id <= 0 ) {
				continue;
			}

			if ( 'album' === $item_type ) {
				if ( $item_id === $album_id ) {
					continue;
				}

				$entity = $this->get_album( $item_id );
				$cover  = $this->get_album_cover_payload_recursive( $item_id, [ $album_id ] );
			} else {
				$entity = $this->get_gallery( $item_id );
				$cover  = $this->get_gallery_cover_payload( $item_id );
			}

			if ( empty( $entity ) ) {
				continue;
			}

			$hydrated[] = [
				'id'          => (int) ( $item['id'] ?? 0 ),
				'item_type'   => $item_type,
				'item_id'     => $item_id,
				'sort_order'  => (int) ( $item['sort_order'] ?? 0 ),
				'title'       => (string) ( $entity['title'] ?? '' ),
				'slug'        => (string) ( $entity['slug'] ?? '' ),
				'description' => (string) ( $entity['description'] ?? '' ),
				'status'      => (string) ( $entity['status'] ?? 'draft' ),
				'shortcode'   => (string) ( $entity['shortcode'] ?? '' ),
				'public_url'  => (string) ( $entity['public_url'] ?? '' ),
				'cover'       => $cover,
			];
		}

		return $hydrated;
	}

	/**
	 * Checks whether one album tree contains another album.
	 *
	 * @param int             $album_id  Root album ID.
	 * @param int             $search_id Album ID to find.
	 * @param array<int, int> $visited   Visited album IDs.
	 * @return bool
	 */
	private function album_contains_album( int $album_id, int $search_id, array $visited ): bool {
		if ( $album_id <= 0 ) {
			return false;
		}

		if ( in_array( $album_id, $visited, true ) ) {
			return false;
		}

		$visited[] = $album_id;

		foreach ( $this->get_album_items( $album_id ) as $item ) {
			if ( 'album' !== ( $item['item_type'] ?? 'gallery' ) ) {
				continue;
			}

			$item_id = (int) ( $item['item_id'] ?? 0 );

			if ( $item_id === $search_id ) {
				return true;
			}

			if ( $item_id > 0 && $this->album_contains_album( $item_id, $search_id, $visited ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns gallery item rows prepared for tag reporting and tag galleries.
	 *
	 * @param bool $published_only Whether to restrict rows to published galleries.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_tag_rows( bool $published_only ): array {
		global $wpdb;

		$sql = "
			SELECT i.*, g.title AS gallery_title, g.slug AS gallery_slug, g.status AS gallery_status
			FROM {$this->table('gallery_items')} i
			INNER JOIN {$this->table('galleries')} g ON g.id = i.gallery_id
			WHERE i.is_visible = 1
				AND i.item_tags != ''
		";

		if ( $published_only ) {
			$sql .= $wpdb->prepare( ' AND g.status IN (%s, %s)', 'publish', 'published' );
		}

		$sql .= ' ORDER BY g.title ASC, i.sort_order ASC, i.id ASC';

		return $this->normalize_collection(
			$wpdb->get_results( $sql ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/**
	 * Builds the aggregated tag index from gallery item rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Gallery item rows.
	 * @return array<string, array<string, mixed>>
	 */
	private function build_tag_index_from_rows( array $rows ): array {
		$index = [];

		foreach ( $rows as $row ) {
			$gallery_id    = (int) ( $row['gallery_id'] ?? 0 );
			$gallery_title = sanitize_text_field( (string) ( $row['gallery_title'] ?? '' ) );

			foreach ( $this->parse_item_tags( (string) ( $row['item_tags'] ?? '' ) ) as $tag_name ) {
				$tag_slug = sanitize_title( $tag_name );

				if ( '' === $tag_slug ) {
					continue;
				}

				if ( ! isset( $index[ $tag_slug ] ) ) {
					$index[ $tag_slug ] = [
						'name'          => $tag_name,
						'slug'          => $tag_slug,
						'item_count'    => 0,
						'gallery_count' => 0,
						'gallery_ids'   => [],
						'gallery_titles'=> [],
						'shortcode'     => sprintf( '[ml_gallery type="tag" tag="%s"]', $tag_slug ),
					];
				}

				++$index[ $tag_slug ]['item_count'];

				if ( $gallery_id > 0 && ! in_array( $gallery_id, $index[ $tag_slug ]['gallery_ids'], true ) ) {
					$index[ $tag_slug ]['gallery_ids'][] = $gallery_id;

					if ( '' !== $gallery_title ) {
						$index[ $tag_slug ]['gallery_titles'][] = $gallery_title;
					}
				}
			}
		}

		foreach ( $index as &$item ) {
			$item['gallery_count'] = count( $item['gallery_ids'] );
		}
		unset( $item );

		uasort(
			$index,
			static function ( array $left, array $right ): int {
				if ( (int) $left['item_count'] === (int) $right['item_count'] ) {
					return strcasecmp( (string) $left['name'], (string) $right['name'] );
				}

				return (int) $right['item_count'] <=> (int) $left['item_count'];
			}
		);

		return $index;
	}

	/**
	 * Sanitizes a gallery item link.
	 *
	 * @param mixed $value Input value.
	 * @return string
	 */
	private function sanitize_item_link( $value ): string {
		$value = trim( (string) $value );

		return '' === $value ? '' : esc_url_raw( $value );
	}

	/**
	 * Sanitizes one comma-separated tag string.
	 *
	 * @param mixed $value Raw tag string.
	 * @return string
	 */
	private function sanitize_item_tags( $value ): string {
		return implode( ', ', $this->parse_item_tags( (string) $value ) );
	}

	/**
	 * Parses one comma-separated tag string.
	 *
	 * @param string $value Raw tag string.
	 * @return array<int, string>
	 */
	private function parse_item_tags( string $value ): array {
		$parts    = preg_split( '/[\r\n,;|]+/', $value ) ?: [];
		$tags     = [];
		$seen_map = [];

		foreach ( $parts as $part ) {
			$tag = sanitize_text_field( trim( (string) $part ) );

			if ( '' === $tag ) {
				continue;
			}

			$key = sanitize_title( $tag );

			if ( '' === $key || isset( $seen_map[ $key ] ) ) {
				continue;
			}

			$seen_map[ $key ] = true;
			$tags[]           = $tag;
		}

		return $tags;
	}

	/**
	 * Merges two tag collections without duplicates.
	 *
	 * @param array<int, string> $current Current tags.
	 * @param array<int, string> $incoming Incoming tags.
	 * @return array<int, string>
	 */
	private function merge_tag_sets( array $current, array $incoming ): array {
		return $this->parse_item_tags( implode( ', ', array_merge( $current, $incoming ) ) );
	}

	/**
	 * Sanitizes a visibility flag.
	 *
	 * @param mixed $value Input value.
	 * @return int
	 */
	private function sanitize_visibility_flag( $value ): int {
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		return in_array( (string) $value, [ '1', 'true', 'on', 'yes' ], true ) ? 1 : 0;
	}

	/**
	 * Resolves one boolean-like settings flag without zeroing absent keys.
	 *
	 * @param array<string, mixed> $input   Raw settings payload.
	 * @param string               $key     Settings key.
	 * @param mixed                $current Current stored value.
	 * @return int
	 */
	private function resolve_setting_flag( array $input, string $key, $current ): int {
		if ( ! array_key_exists( $key, $input ) ) {
			return $this->sanitize_visibility_flag( $current );
		}

		return $this->sanitize_visibility_flag( $input[ $key ] );
	}

	/**
	 * Truncates one database-bound text value safely.
	 *
	 * @param string $value Input text.
	 * @param int    $limit Max length.
	 * @return string
	 */
	private function truncate_database_text( string $value, int $limit ): string {
		if ( $limit <= 0 || '' === $value ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}

	/**
	 * Truncates one filename for database storage while preserving the extension.
	 *
	 * @param string $filename Input filename.
	 * @param int    $limit    Max length.
	 * @return string
	 */
	private function truncate_database_filename( string $filename, int $limit ): string {
		$sanitized = sanitize_file_name( $filename );

		if ( '' === $sanitized || $limit <= 0 ) {
			return '';
		}

		$extension = pathinfo( $sanitized, PATHINFO_EXTENSION );
		$basename  = pathinfo( $sanitized, PATHINFO_FILENAME );

		if ( '' === $extension ) {
			return $this->truncate_database_text( $sanitized, $limit );
		}

		$extension = sanitize_file_name( $extension );
		$name_room = max( 1, $limit - ( strlen( $extension ) + 1 ) );

		return $this->truncate_database_text( $basename, $name_room ) . '.' . $extension;
	}

	/**
	 * Adds computed fields to a gallery summary.
	 *
	 * @param array<string, mixed> $gallery Gallery row.
	 * @param bool                 $with_cover Whether to resolve the cover payload.
	 * @return array<string, mixed>
	 */
	private function decorate_gallery_summary( array $gallery, bool $with_cover = true ): array {
		global $wpdb;

		$gallery_id                 = (int) ( $gallery['id'] ?? 0 );
		$gallery['shortcode']       = $this->gallery_shortcode( $gallery_id );
		$gallery['legacy_shortcode'] = sprintf( '[ml_gallery_pro gallery="%d"]', $gallery_id );

		if ( $with_cover && $gallery_id > 0 ) {
			$gallery['cover'] = $this->get_gallery_cover_payload( $gallery_id );
		}

		// Attach album IDs this gallery belongs to.
		if ( $gallery_id > 0 ) {
			$album_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT album_id FROM {$this->table('album_items')} WHERE item_type = 'gallery' AND item_id = %d",
					$gallery_id
				)
			); // phpcs:ignore

			$gallery['album_ids'] = array_map( 'absint', $album_ids ?: [] );
		} else {
			$gallery['album_ids'] = [];
		}

		return $gallery;
	}

	/**
	 * Adds computed fields to an album summary.
	 *
	 * @param array<string, mixed> $album Album row.
	 * @param bool                 $with_cover Whether to resolve the cover payload.
	 * @return array<string, mixed>
	 */
	private function decorate_album_summary( array $album, bool $with_cover = true ): array {
		$album_id                  = (int) ( $album['id'] ?? 0 );
		$album['shortcode']        = $this->album_shortcode( $album_id );
		$album['legacy_shortcode'] = sprintf( '[ml_gallery_pro album="%d"]', $album_id );

		if ( $with_cover && $album_id > 0 ) {
			$album['cover'] = $this->get_album_cover_payload( $album_id );
		}

		return $album;
	}


	/**
	 * Returns the official public URL for a gallery ID.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	public function get_gallery_public_url( int $gallery_id ): string {
		global $wpdb;

		if ( $gallery_id <= 0 ) {
			return '';
		}

		$sql  = $wpdb->prepare( "SELECT slug FROM {$this->table('galleries')} WHERE id = %d LIMIT 1", $gallery_id );
		$slug = (string) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->get_gallery_public_url_by_slug( $slug );
	}

	/**
	 * Returns the official public URL for a gallery slug.
	 *
	 * @param string $slug Gallery slug.
	 * @return string
	 */
	public function get_gallery_public_url_by_slug( string $slug ): string {
		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			return '';
		}

		return home_url( user_trailingslashit( 'galeria/' . rawurlencode( $slug ) ) );
	}

	/**
	 * Returns the official shortcode for a gallery.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	public function get_gallery_shortcode( int $gallery_id ): string {
		global $wpdb;

		if ( $gallery_id <= 0 ) {
			return '';
		}

		$sql = $wpdb->prepare( "SELECT id FROM {$this->table('galleries')} WHERE id = %d LIMIT 1", $gallery_id );
		$hit = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $hit <= 0 ) {
			return '';
		}

		return $this->gallery_shortcode( $gallery_id );
	}

	/**
	 * Returns the standard gallery shortcode.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	private function gallery_shortcode( int $gallery_id ): string {
		return sprintf( '[ml_gallery id="%d"]', $gallery_id );
	}

	/**
	 * Returns the standard album shortcode.
	 *
	 * @param int $album_id Album ID.
	 * @return string
	 */
	private function album_shortcode( int $album_id ): string {
		return sprintf( '[ml_gallery type="album" id="%d"]', $album_id );
	}

	/**
	 * Normalizes a collection of rows.
	 *
	 * @param array<int, object> $results Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_collection( array $results ): array {
		$items = [];

		foreach ( $results as $result ) {
			$normalized = $this->normalize_entity( $result );

			if ( null !== $normalized ) {
				$items[] = $normalized;
			}
		}

		return $items;
	}

	/**
	 * Normalizes one database entity.
	 *
	 * @param object|null $entity Raw entity.
	 * @return array<string, mixed>|null
	 */
	private function normalize_entity( $entity ): ?array {
		if ( ! is_object( $entity ) ) {
			return null;
		}

		$data = (array) $entity;

		foreach ( [ 'id', 'created_by', 'cover_attachment_id', 'cover_item_id', 'attachment_id', 'gallery_id', 'album_id', 'item_id', 'item_count', 'sort_order', 'is_visible', 'width', 'height', 'file_size' ] as $int_key ) {
			if ( isset( $data[ $int_key ] ) ) {
				$data[ $int_key ] = (int) $data[ $int_key ];
			}
		}

		if ( isset( $data['status'] ) ) {
			$data['status'] = $this->sanitize_status( $data['status'] );
		}

		if ( isset( $data['gallery_status'] ) ) {
			$data['gallery_status'] = $this->sanitize_status( $data['gallery_status'] );
		}

		$data['settings'] = [];

		if ( ! empty( $data['settings_json'] ) ) {
			$decoded = json_decode( (string) $data['settings_json'], true );

			if ( is_array( $decoded ) ) {
				$data['settings'] = $decoded;
			}
		}

		return $data;
	}

	/**
	 * Sanitizes a status value.
	 *
	 * @param mixed $status Requested status.
	 * @return string
	 */
	private function sanitize_status( $status ): string {
		$allowed = [ 'draft', 'publish', 'private' ];
		$status  = sanitize_key( (string) $status );

		if ( 'published' === $status ) {
			return 'publish';
		}

		return in_array( $status, $allowed, true ) ? $status : 'draft';
	}

	/**
	 * Sanitizes one gallery display type.
	 *
	 * @param mixed $display_type Requested display type.
	 * @return string
	 */
	private function sanitize_display_type( $display_type ): string {
		$allowed      = [ 'grid', 'tile', 'mosaic', 'masonry', 'justified', 'slideshow', 'filmstrip', 'imagebrowser' ];
		$display_type = sanitize_key( (string) $display_type );

		return in_array( $display_type, $allowed, true ) ? $display_type : 'grid';
	}

	/**
	 * Returns the display_type from the configured default gallery preset.
	 *
	 * @return string
	 */
	private function get_default_gallery_display_type(): string {
		$settings = $this->get_settings();
		$preset   = sanitize_key( (string) ( $settings['default_gallery_preset'] ?? 'masonry-default' ) );

		$map = [
			'masonry-default'    => 'masonry',
			'clean-grid'         => 'grid',
			'editorial-tile'     => 'tile',
			'impact-mosaic'      => 'mosaic',
			'story-justified'    => 'justified',
			'showcase-filmstrip' => 'filmstrip',
		];

		return $map[ $preset ] ?? 'masonry';
	}

	/**
	 * Sanitizes an integer with min and max bounds.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min Minimum value.
	 * @param int   $max Maximum value.
	 * @return int
	 */
	private function sanitize_integer( $value, int $min, int $max ): int {
		$value = absint( $value );
		$value = max( $min, $value );

		return min( $max, $value );
	}

	/**
	 * Sanitizes one value against an allowed list.
	 *
	 * @param mixed             $value   Raw value.
	 * @param array<int,string> $allowed Allowed values.
	 * @param string            $default Fallback value.
	 * @return string
	 */
	private function sanitize_choice( $value, array $allowed, string $default ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Sanitizes a HEX color value.
	 *
	 * @param mixed  $value    Raw color value.
	 * @param string $fallback Fallback HEX color.
	 * @return string
	 */
	private function sanitize_hex_color_value( $value, string $fallback ): string {
		$color = sanitize_hex_color( (string) $value );

		return is_string( $color ) && '' !== $color ? $color : $fallback;
	}

	/**
	 * Sanitizes an optional HEX color value.
	 *
	 * @param mixed $value Raw color.
	 * @return string
	 */
	private function sanitize_optional_hex_color_value( $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$color = sanitize_hex_color( $value );

		return is_string( $color ) ? $color : '';
	}

	/**
	 * Ensures a unique slug inside a table.
	 *
	 * @param string $table Table name.
	 * @param string $slug Base slug.
	 * @param int    $exclude_id Current ID to exclude.
	 * @return string
	 */
	private function ensure_unique_slug( string $table, string $slug, int $exclude_id = 0 ): string {
		global $wpdb;

		$base_slug = '' !== $slug ? $slug : 'item';
		$unique    = $base_slug;
		$suffix    = 2;

		while ( true ) {
			$sql  = "SELECT id FROM {$table} WHERE slug = %s";
			$args = [ $unique ];

			if ( $exclude_id > 0 ) {
				$sql   .= ' AND id != %d';
				$args[] = $exclude_id;
			}

			$sql   .= ' LIMIT 1';
			$query  = $wpdb->prepare( $sql, ...$args );
			$exists = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( empty( $exists ) ) {
				break;
			}

			$unique = $base_slug . '-' . $suffix;
			++$suffix;
		}

		return $unique;
	}

	/**
	 * Scans the storage directory for a single gallery and syncs found images.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function scan_and_sync_gallery( int $gallery_id, string $folder_name ) {
		$gallery = $this->get_gallery( $gallery_id );

		if ( empty( $gallery ) ) {
			return new \WP_Error( 'mlgp_gallery_not_found', __( 'Galeria nao encontrada.', 'ml-gallery-pro' ) );
		}

		$payloads = $this->storage->scan_gallery_storage_by_folder( $folder_name );

		if ( is_wp_error( $payloads ) ) {
			return $payloads;
		}

		if ( empty( $payloads ) ) {
			return [
				'synced' => 0,
				'editor' => $this->get_gallery_editor( $gallery_id ),
			];
		}

		$editor = $this->persist_local_upload_payloads( $gallery_id, $gallery, $payloads );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		return [
			'synced' => count( $payloads ),
			'editor' => $editor,
		];
	}

	/**
	 * Returns storage directory listing enriched with gallery titles.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_storage_dirs_with_titles(): array {
		$dirs   = $this->storage->list_gallery_storage_dirs();
		$result = [];

		foreach ( $dirs as $dir ) {
			$name = (string) ( $dir['name'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			$result[] = [
				'name'  => $name,
				'label' => $name,
			];
		}

		return $result;
	}

	/**
	 * Returns a lightweight id+title list of albums for the gallery filter dropdown.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public function get_album_options_for_filter(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT id, title FROM {$this->table('albums')} ORDER BY title ASC",
			ARRAY_A
		); // phpcs:ignore

		$result = [];

		foreach ( ( $rows ?: [] ) as $row ) {
			$result[] = [
				'id'    => (int) $row['id'],
				'title' => (string) ( $row['title'] ?? '' ),
			];
		}

		return $result;
	}
}
