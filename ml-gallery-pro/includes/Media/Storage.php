<?php
/**
 * Local storage manager for gallery images.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Storage {

	/**
	 * Base directory name inside wp-content.
	 */
	public const BASE_DIRNAME = 'ml-gallery';

	/**
	 * Dedicated server import directory name inside wp-content.
	 */
	public const IMPORT_DIRNAME = 'ml-gallery-import';

	/**
	 * Ensures the base storage structure exists.
	 *
	 * @return true|\WP_Error
	 */
	public function ensure_base_structure() {
		$base_dir = $this->base_dir();

		if ( ! is_dir( $base_dir ) && ! wp_mkdir_p( $base_dir ) ) {
			return new \WP_Error( 'mlgp_storage_dir_failed', __( 'Nao foi possivel criar o diretorio base de imagens do plugin.', 'ml-gallery-pro' ) );
		}

		$this->write_protection_files( $base_dir );

		return true;
	}

	/**
	 * Ensures the gallery directory exists.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string|\WP_Error
	 */
	public function ensure_gallery_dir( int $gallery_id ) {
		if ( $gallery_id <= 0 ) {
			return new \WP_Error( 'mlgp_invalid_gallery_dir', __( 'Galeria invalida para criacao de diretorio.', 'ml-gallery-pro' ) );
		}

		$prepared = $this->ensure_base_structure();

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$gallery_dir = $this->gallery_dir( $gallery_id );

		if ( ! is_dir( $gallery_dir ) && ! wp_mkdir_p( $gallery_dir ) ) {
			return new \WP_Error( 'mlgp_gallery_dir_failed', __( 'Nao foi possivel criar o diretorio da galeria.', 'ml-gallery-pro' ) );
		}

		$this->write_protection_files( $gallery_dir );

		return $gallery_dir;
	}

	/**
	 * Stores uploaded files in the plugin storage directory.
	 *
	 * @param int   $gallery_id Gallery ID.
	 * @param array $files      Uploaded files array.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function store_gallery_uploads( int $gallery_id, array $files ) {
		$gallery_dir = $this->ensure_gallery_dir( $gallery_id );

		if ( is_wp_error( $gallery_dir ) ) {
			return $gallery_dir;
		}

		$normalized_files = $this->normalize_uploads( $files );

		if ( empty( $normalized_files ) ) {
			return new \WP_Error( 'mlgp_empty_upload', __( 'Selecione pelo menos uma imagem valida para envio.', 'ml-gallery-pro' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$gallery_url = $this->gallery_url( $gallery_id );
		$payloads    = [];

		foreach ( $normalized_files as $file ) {
			$validation = $this->validate_upload( $file );

			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$filename    = $this->prepare_storage_filename( $gallery_dir, $validation['filename'] );
			$target_path = trailingslashit( $gallery_dir ) . $filename;

			if ( ! move_uploaded_file( $validation['tmp_name'], $target_path ) ) {
				return new \WP_Error( 'mlgp_move_upload_failed', __( 'Nao foi possivel mover a imagem enviada para o diretorio da galeria.', 'ml-gallery-pro' ) );
			}

			$payload = $this->build_payload_for_stored_file( $target_path, $filename, $validation['original_name'], $validation['mime_type'], $gallery_dir, $gallery_url );

			if ( is_wp_error( $payload ) ) {
				$this->safe_unlink( $target_path );
				return $payload;
			}

			$payloads[] = $payload;
		}

		return $payloads;
	}

	/**
	 * Imports all valid images from one uploaded ZIP file.
	 *
	 * @param int   $gallery_id Gallery ID.
	 * @param array $file       Uploaded ZIP file data.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function store_gallery_zip( int $gallery_id, array $file ) {
		$gallery_dir = $this->ensure_gallery_dir( $gallery_id );

		if ( is_wp_error( $gallery_dir ) ) {
			return $gallery_dir;
		}

		$validation = $this->validate_zip_upload( $file );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'mlgp_zip_archive_missing', __( 'A extensao ZipArchive nao esta disponivel neste servidor para importar arquivos ZIP.', 'ml-gallery-pro' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $validation['tmp_name'] ) ) {
			return new \WP_Error( 'mlgp_zip_open_failed', __( 'Nao foi possivel abrir o arquivo ZIP enviado.', 'ml-gallery-pro' ) );
		}

		$temp_dir = trailingslashit( $gallery_dir ) . '_tmp-import-' . wp_generate_password( 10, false, false );

		if ( ! wp_mkdir_p( $temp_dir ) ) {
			$zip->close();
			return new \WP_Error( 'mlgp_zip_temp_dir_failed', __( 'Nao foi possivel preparar a area temporaria para importar o ZIP.', 'ml-gallery-pro' ) );
		}

		$this->write_protection_files( $temp_dir );

		$gallery_url = $this->gallery_url( $gallery_id );
		$payloads    = [];

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$entry_name = (string) $zip->getNameIndex( $index );

			if ( ! $this->is_valid_zip_entry( $entry_name ) ) {
				continue;
			}

			$basename = sanitize_file_name( wp_basename( $entry_name ) );

			if ( '' === $basename ) {
				continue;
			}

			$stream = $zip->getStream( $entry_name );

			if ( ! is_resource( $stream ) ) {
				continue;
			}

			$temp_path = trailingslashit( $temp_dir ) . wp_unique_filename( $temp_dir, $basename );
			$handle    = fopen( $temp_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

			if ( ! is_resource( $handle ) ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				continue;
			}

			stream_copy_to_stream( $stream, $handle );
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			$payload = $this->store_image_from_path( $temp_path, $basename, $gallery_dir, $gallery_url, true );

			if ( is_wp_error( $payload ) ) {
				$this->safe_unlink( $temp_path );
				continue;
			}

			$payloads[] = $payload;
		}

		$zip->close();
		$this->delete_directory( $temp_dir );

		if ( empty( $payloads ) ) {
			return new \WP_Error( 'mlgp_zip_no_images', __( 'Nenhuma imagem valida foi encontrada dentro do ZIP enviado.', 'ml-gallery-pro' ) );
		}

		return $payloads;
	}

	/**
	 * Returns allowed roots for server-side imports.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_server_import_roots(): array {
		$upload_dir       = wp_get_upload_dir();
		$uploads_base_dir = ! empty( $upload_dir['basedir'] ) ? wp_normalize_path( (string) $upload_dir['basedir'] ) : wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . 'uploads' );
		$imports_base_dir = wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . self::IMPORT_DIRNAME );

		if ( ! is_dir( $imports_base_dir ) ) {
			wp_mkdir_p( $imports_base_dir );
		}

		$this->write_protection_files( $imports_base_dir );

		return [
			[
				'value'   => 'uploads',
				'label'   => __( 'Uploads do WordPress', 'ml-gallery-pro' ),
				'path'    => $uploads_base_dir,
				'example' => 'clientes/evento-a',
			],
			[
				'value'   => 'imports',
				'label'   => __( 'Pasta dedicada ml-gallery-import', 'ml-gallery-pro' ),
				'path'    => $imports_base_dir,
				'example' => 'lote-abril/ensaio-01',
			],
		];
	}

	/**
	 * Imports images recursively from one allowed server directory.
	 *
	 * @param int    $gallery_id     Gallery ID.
	 * @param string $root_key       Allowed root key.
	 * @param string $relative_path  Relative folder path inside the chosen root.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */

	/**
	 * Collects valid server import image files without copying them yet.
	 *
	 * @param string $root_key      Allowed import root key.
	 * @param string $relative_path Relative directory path.
	 * @return array<int,string>|\WP_Error
	 */
	public function collect_server_import_files( string $root_key, string $relative_path ) {
		$source_dir = $this->resolve_server_import_directory( $root_key, $relative_path );

		if ( is_wp_error( $source_dir ) ) {
			return $source_dir;
		}

		$files = [];

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $source_dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $entry ) {
				try {
					if ( ! $entry->isFile() ) {
						continue;
					}

					$path = wp_normalize_path( $entry->getPathname() );

					if ( ! $this->is_supported_server_import_image( $path ) ) {
						continue;
					}

					$files[] = $path;
				} catch ( \Throwable $throwable ) {
					error_log( '[ML Gallery Pro][server-import][skip-entry] ' . $throwable->getMessage() );
					continue;
				}
			}
		} catch ( \Throwable $throwable ) {
			error_log( '[ML Gallery Pro][server-import][iterator] ' . $throwable->getMessage() );

			return new \WP_Error(
				'mlgp_server_import_iterator_failed',
				__( 'Nao foi possivel ler a pasta do servidor com seguranca.', 'ml-gallery-pro' )
			);
		}

		sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

		return $files;
	}

	/**
	 * Imports a small batch of server image paths into the gallery storage.
	 *
	 * @param int               $gallery_id Gallery ID.
	 * @param array<int,string> $files      Source file paths.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function import_gallery_file_batch( int $gallery_id, array $files ) {
		$gallery_dir = $this->ensure_gallery_dir( $gallery_id );

		if ( is_wp_error( $gallery_dir ) ) {
			return $gallery_dir;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$gallery_url = $this->gallery_url( $gallery_id );
		$payloads    = [];

		foreach ( $files as $source_path ) {
			$source_path = wp_normalize_path( (string) $source_path );
			$basename    = sanitize_file_name( basename( $source_path ) );

			if ( '' === $basename || ! is_readable( $source_path ) || ! $this->is_supported_server_import_image( $source_path ) ) {
				continue;
			}

			$payload = $this->store_image_from_path( $source_path, $basename, $gallery_dir, $gallery_url, false );

			if ( is_wp_error( $payload ) ) {
				error_log( '[ML Gallery Pro][server-import][file] ' . $source_path . ' - ' . $payload->get_error_message() );
				continue;
			}

			$payloads[] = $payload;
		}

		if ( empty( $payloads ) ) {
			return [];
		}

		return $payloads;
	}

	public function import_gallery_directory( int $gallery_id, string $root_key, string $relative_path ) {
		$gallery_dir = $this->ensure_gallery_dir( $gallery_id );

		if ( is_wp_error( $gallery_dir ) ) {
			return $gallery_dir;
		}

		$source_dir = $this->resolve_server_import_directory( $root_key, $relative_path );

		if ( is_wp_error( $source_dir ) ) {
			return $source_dir;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$gallery_url = $this->gallery_url( $gallery_id );
		$payloads    = [];
		$iterator    = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $entry ) {
			if ( ! $entry->isFile() ) {
				continue;
			}

			$source_path = wp_normalize_path( $entry->getPathname() );
			$basename    = sanitize_file_name( $entry->getFilename() );

			if ( '' === $basename ) {
				continue;
			}

			$payload = $this->store_image_from_path( $source_path, $basename, $gallery_dir, $gallery_url, false );

			if ( is_wp_error( $payload ) ) {
				continue;
			}

			$payloads[] = $payload;
		}

		if ( empty( $payloads ) ) {
			return new \WP_Error( 'mlgp_server_import_empty', __( 'Nenhuma imagem valida foi encontrada na pasta do servidor informada.', 'ml-gallery-pro' ) );
		}

		return $payloads;
	}

	/**
	 * Rebuilds local preview variants for one gallery item.
	 *
	 * @param array<string, mixed> $item Gallery item row.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function regenerate_item_payload( array $item ) {
		$gallery_id = isset( $item['gallery_id'] ) ? absint( $item['gallery_id'] ) : 0;
		$file_path  = isset( $item['file_path'] ) ? wp_normalize_path( (string) $item['file_path'] ) : '';

		if ( $gallery_id <= 0 || '' === $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'mlgp_regenerate_missing_file', __( 'Nao foi possivel localizar o arquivo original para regenerar as previews.', 'ml-gallery-pro' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Use the actual directory where the file lives, not gallery-{ID}.
		$actual_dir = wp_normalize_path( dirname( $file_path ) );
		$actual_url = $this->resolve_gallery_real_url( $actual_dir );

		$filename      = ! empty( $item['file_name'] ) ? sanitize_file_name( (string) $item['file_name'] ) : basename( $file_path );
		$original_name = ! empty( $item['original_name'] ) ? sanitize_file_name( (string) $item['original_name'] ) : $filename;
		$mime_type     = ! empty( $item['mime_type'] ) ? sanitize_text_field( (string) $item['mime_type'] ) : '';

		$payload = $this->build_payload_for_stored_file(
			$file_path,
			$filename,
			$original_name,
			$mime_type,
			$actual_dir,
			$actual_url
		);

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$payload['item_title'] = sanitize_text_field( (string) ( $item['item_title'] ?? $payload['item_title'] ?? '' ) );

		return $payload;
	}

	/**
	 * Rotates one local original image and rebuilds its variants.
	 *
	 * @param array<string, mixed> $item    Gallery item row.
	 * @param int                  $degrees Rotation degrees.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function rotate_item_payload( array $item, int $degrees ) {
		$gallery_id = isset( $item['gallery_id'] ) ? absint( $item['gallery_id'] ) : 0;
		$file_path  = isset( $item['file_path'] ) ? wp_normalize_path( (string) $item['file_path'] ) : '';

		if ( $gallery_id <= 0 || '' === $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'mlgp_rotate_missing_file', __( 'Nao foi possivel localizar o arquivo original para rotacionar a imagem.', 'ml-gallery-pro' ) );
		}

		$editor = wp_get_image_editor( $file_path );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		if ( ! method_exists( $editor, 'rotate' ) ) {
			return new \WP_Error( 'mlgp_rotate_unsupported', __( 'O editor de imagem deste servidor nao suporta rotacao do arquivo.', 'ml-gallery-pro' ) );
		}

		$editor->rotate( $degrees );
		$this->apply_editor_quality( $editor );

		$saved = $editor->save( $file_path );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		clearstatcache( true, $file_path );

		return $this->regenerate_item_payload( $item );
	}

	/**
	 * Removes the local file set for a gallery item.
	 *
	 * @param array<string, mixed> $item Gallery item row.
	 * @return void
	 */
	public function remove_item_files( array $item ): void {
		$paths = [
			$item['file_path'] ?? '',
			$item['thumb_path'] ?? '',
			$item['medium_path'] ?? '',
			$item['large_path'] ?? '',
		];

		foreach ( array_unique( array_filter( array_map( 'strval', $paths ) ) ) as $path ) {
			$this->safe_unlink( $path );
		}
	}

	/**
	 * Deletes a gallery directory recursively.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return void
	 */
	public function delete_gallery_directory( int $gallery_id ): void {
		if ( $gallery_id <= 0 ) {
			return;
		}

		$base_dir    = wp_normalize_path( trailingslashit( $this->base_dir() ) );
		$gallery_dir = wp_normalize_path( trailingslashit( $this->gallery_dir( $gallery_id ) ) );

		if ( 0 !== strpos( $gallery_dir, $base_dir ) || ! is_dir( $gallery_dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $gallery_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				continue;
			}

			@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		@rmdir( $gallery_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Returns the base directory absolute path.
	 *
	 * @return string
	 */
	/**
	 * Deletes the entire local plugin storage and recreates the protected base.
	 *
	 * @return true|\WP_Error
	 */
	public function reset_all_storage() {
		$base_dir = wp_normalize_path( $this->base_dir() );

		if ( is_dir( $base_dir ) ) {
			$this->delete_directory( $base_dir );
		}

		return $this->ensure_base_structure();
	}

	public function base_dir(): string {
		return trailingslashit( WP_CONTENT_DIR ) . self::BASE_DIRNAME;
	}

	/**
	 * Returns the base URL.
	 *
	 * @return string
	 */
	public function base_url(): string {
		return content_url( self::BASE_DIRNAME );
	}

	/**
	 * Returns one gallery directory absolute path.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	public function gallery_dir( int $gallery_id ): string {
		return trailingslashit( $this->base_dir() ) . 'gallery-' . absint( $gallery_id );
	}

	/**
	 * Returns one gallery URL.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	public function gallery_url( int $gallery_id ): string {
		return trailingslashit( $this->base_url() ) . 'gallery-' . absint( $gallery_id );
	}

	/**
	 * Normalizes the PHP multiple upload array.
	 *
	 * @param array $files Raw upload array.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_uploads( array $files ): array {
		if ( empty( $files['name'] ) ) {
			return [];
		}

		$names     = is_array( $files['name'] ) ? $files['name'] : [ $files['name'] ];
		$tmp_names = is_array( $files['tmp_name'] ) ? $files['tmp_name'] : [ $files['tmp_name'] ];
		$types     = is_array( $files['type'] ) ? $files['type'] : [ $files['type'] ?? '' ];
		$errors    = is_array( $files['error'] ) ? $files['error'] : [ $files['error'] ?? UPLOAD_ERR_NO_FILE ];
		$sizes     = is_array( $files['size'] ) ? $files['size'] : [ $files['size'] ?? 0 ];

		$normalized = [];

		foreach ( $names as $index => $name ) {
			$normalized[] = [
				'name'     => (string) $name,
				'tmp_name' => isset( $tmp_names[ $index ] ) ? (string) $tmp_names[ $index ] : '',
				'type'     => isset( $types[ $index ] ) ? (string) $types[ $index ] : '',
				'error'    => isset( $errors[ $index ] ) ? (int) $errors[ $index ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $sizes[ $index ] ) ? (int) $sizes[ $index ] : 0,
			];
		}

		return $normalized;
	}

	/**
	 * Validates one uploaded file.
	 *
	 * @param array<string, mixed> $file Uploaded file data.
	 * @return array<string, string>|\WP_Error
	 */
	private function validate_upload( array $file ) {
		$error_code = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error_code ) {
			return new \WP_Error( 'mlgp_upload_error', __( 'Falha no envio da imagem. Verifique o arquivo e tente novamente.', 'ml-gallery-pro' ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$name     = isset( $file['name'] ) ? (string) $file['name'] : '';

		if ( '' === $tmp_name || '' === $name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'mlgp_invalid_upload', __( 'Arquivo de imagem invalido para upload.', 'ml-gallery-pro' ) );
		}

		$filetype = wp_check_filetype_and_ext( $tmp_name, $name, wp_get_mime_types() );
		$mime     = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';

		if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
			return new \WP_Error( 'mlgp_invalid_image_type', __( 'Envie apenas arquivos de imagem validos.', 'ml-gallery-pro' ) );
		}

		$filename = ! empty( $filetype['proper_filename'] ) ? (string) $filetype['proper_filename'] : $name;

		return [
			'original_name' => sanitize_file_name( $name ),
			'filename'      => sanitize_file_name( $filename ),
			'mime_type'     => $mime,
			'tmp_name'      => $tmp_name,
		];
	}

	/**
	 * Validates one uploaded ZIP file.
	 *
	 * @param array<string, mixed> $file Uploaded ZIP file data.
	 * @return array<string, string>|\WP_Error
	 */
	private function validate_zip_upload( array $file ) {
		$error_code = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error_code ) {
			return new \WP_Error( 'mlgp_zip_upload_error', __( 'Falha no envio do arquivo ZIP. Verifique o arquivo e tente novamente.', 'ml-gallery-pro' ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$name     = isset( $file['name'] ) ? (string) $file['name'] : '';

		if ( '' === $tmp_name || '' === $name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'mlgp_invalid_zip_upload', __( 'Arquivo ZIP invalido para importacao.', 'ml-gallery-pro' ) );
		}

		$extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( 'zip' !== $extension ) {
			return new \WP_Error( 'mlgp_invalid_zip_type', __( 'Envie apenas arquivos ZIP validos para importacao.', 'ml-gallery-pro' ) );
		}

		return [
			'original_name' => sanitize_file_name( $name ),
			'tmp_name'      => $tmp_name,
		];
	}

	/**
	 * Stores one already-extracted image file in the gallery directory.
	 *
	 * @param string $source_path Source file path.
	 * @param string $original_name Original file name.
	 * @param string $gallery_dir Gallery directory.
	 * @param string $gallery_url Gallery base URL.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function store_image_from_path( string $source_path, string $original_name, string $gallery_dir, string $gallery_url, bool $move_source = true ) {
		if ( '' === $source_path || ! file_exists( $source_path ) ) {
			return new \WP_Error( 'mlgp_import_source_missing', __( 'Nao foi possivel localizar uma imagem do arquivo importado.', 'ml-gallery-pro' ) );
		}

		$filetype = wp_check_filetype_and_ext( $source_path, $original_name, wp_get_mime_types() );
		$mime     = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';

		if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
			return new \WP_Error( 'mlgp_import_invalid_image', __( 'O arquivo importado nao e uma imagem valida.', 'ml-gallery-pro' ) );
		}

		if ( ! wp_getimagesize( $source_path ) ) {
			return new \WP_Error( 'mlgp_import_invalid_dimensions', __( 'A imagem importada nao pode ser processada.', 'ml-gallery-pro' ) );
		}

		$desired_name = ! empty( $filetype['proper_filename'] ) ? (string) $filetype['proper_filename'] : $original_name;
		$safe_name    = $this->truncate_filename( sanitize_file_name( $desired_name ), 180 );
		// If a file with this exact sanitized name already exists in the gallery
		// directory, overwrite it instead of generating a suffixed duplicate.
		$overwrite = file_exists( trailingslashit( $gallery_dir ) . $safe_name );
		$filename    = $this->prepare_storage_filename( $gallery_dir, $desired_name, $overwrite );
		$target_path = trailingslashit( $gallery_dir ) . $filename;

		if ( wp_normalize_path( $source_path ) !== wp_normalize_path( $target_path ) ) {
			if ( $move_source ) {
				$moved = @rename( $source_path, $target_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

				if ( ! $moved ) {
					$copied = @copy( $source_path, $target_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy,WordPress.PHP.NoSilencedErrors.Discouraged

					if ( ! $copied ) {
						return new \WP_Error( 'mlgp_import_move_failed', __( 'Nao foi possivel mover a imagem importada para a galeria.', 'ml-gallery-pro' ) );
					}

					$this->safe_unlink( $source_path );
				}
			} else {
				$copied = @copy( $source_path, $target_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy,WordPress.PHP.NoSilencedErrors.Discouraged

				if ( ! $copied ) {
					return new \WP_Error( 'mlgp_import_copy_failed', __( 'Nao foi possivel copiar a imagem da pasta do servidor para a galeria.', 'ml-gallery-pro' ) );
				}
			}
		}

		return $this->build_payload_for_stored_file( $target_path, $filename, $original_name, $mime, $gallery_dir, $gallery_url );
	}

	/**
	 * Builds the metadata payload for one stored local file.
	 *
	 * @param string $target_path Final file path.
	 * @param string $filename    Final file name.
	 * @param string $original_name Original file name.
	 * @param string $mime_type   MIME type.
	 * @param string $gallery_dir Gallery directory.
	 * @param string $gallery_url Gallery base URL.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function build_payload_for_stored_file( string $target_path, string $filename, string $original_name, string $mime_type, string $gallery_dir, string $gallery_url ) {
		$dimensions = wp_getimagesize( $target_path );

		if ( false === $dimensions ) {
			return new \WP_Error( 'mlgp_invalid_stored_image', __( 'Nao foi possivel ler a imagem armazenada na galeria.', 'ml-gallery-pro' ) );
		}

		$variants = $this->create_image_variants( $target_path, $filename, $gallery_dir, $gallery_url );

		return [
			'original_name' => sanitize_file_name( $original_name ),
			'file_name'     => $filename,
			'file_path'     => $target_path,
			'file_url'      => trailingslashit( $gallery_url ) . $filename,
			'thumb_path'    => $variants['thumb_path'] ?? '',
			'thumb_url'     => $variants['thumb_url'] ?? '',
			'medium_path'   => $variants['medium_path'] ?? '',
			'medium_url'    => $variants['medium_url'] ?? '',
			'large_path'    => $variants['large_path'] ?? '',
			'large_url'     => $variants['large_url'] ?? '',
			'mime_type'     => $mime_type,
			'width'         => isset( $dimensions[0] ) ? absint( $dimensions[0] ) : 0,
			'height'        => isset( $dimensions[1] ) ? absint( $dimensions[1] ) : 0,
			'file_size'     => file_exists( $target_path ) ? (int) filesize( $target_path ) : 0,
			'item_title'    => sanitize_text_field( pathinfo( $original_name, PATHINFO_FILENAME ) ),
		];
	}

	/**
	 * Determines whether a ZIP entry can be imported.
	 *
	 * @param string $entry_name Entry name.
	 * @return bool
	 */
	private function is_valid_zip_entry( string $entry_name ): bool {
		if ( '' === $entry_name ) {
			return false;
		}

		$normalized = str_replace( '\\', '/', $entry_name );
		$basename   = wp_basename( $normalized );

		if ( '' === $basename || '/' === substr( $normalized, -1 ) ) {
			return false;
		}

		if ( 0 === strpos( $normalized, '__MACOSX/' ) || '.ds_store' === strtolower( $basename ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Creates local image variants for admin and frontend usage.
	 *
	 * @param string $source_path Source image path.
	 * @param string $filename    Final file name.
	 * @param string $gallery_dir Gallery directory.
	 * @param string $gallery_url Gallery URL.
	 * @return array<string, string>
	 */
	private function create_image_variants( string $source_path, string $filename, string $gallery_dir, string $gallery_url ): array {
		$editor = wp_get_image_editor( $source_path );

		if ( is_wp_error( $editor ) ) {
			return [];
		}

		$path_info = pathinfo( $filename );
		$basename  = isset( $path_info['filename'] ) ? (string) $path_info['filename'] : $filename;
		$extension = isset( $path_info['extension'] ) ? '.' . strtolower( (string) $path_info['extension'] ) : '';

		$settings    = $this->image_processing_settings();
		$definitions = [
			'thumb' => [ (int) $settings['thumb_width'], (int) $settings['thumb_height'], ! empty( $settings['thumb_crop'] ) ],
			'large' => [ (int) $settings['large_width'], (int) $settings['large_height'], false ],
		];

		$variants = [];

		foreach ( $definitions as $key => $definition ) {
			$variant_editor = wp_get_image_editor( $source_path );

			if ( is_wp_error( $variant_editor ) ) {
				continue;
			}

			$variant_editor->resize( (int) $definition[0], (int) $definition[1], (bool) $definition[2] );
			$this->apply_editor_quality( $variant_editor );

			$variant_name = $basename . '-' . $key . $extension;
			$variant_path = trailingslashit( $gallery_dir ) . $variant_name;
			$saved        = $variant_editor->save( $variant_path );

			if ( is_wp_error( $saved ) ) {
				continue;
			}

			$this->maybe_apply_watermark_to_file( $variant_path, $key, $settings );

			$variants[ $key . '_path' ] = $variant_path;
			$variants[ $key . '_url' ]  = trailingslashit( $gallery_url ) . $variant_name;
		}

		return $variants;
	}

	/**
	 * Resolves a safe import directory inside one allowed root.
	 *
	 * @param string $root_key      Allowed root key.
	 * @param string $relative_path Relative path.
	 * @return string|\WP_Error
	 */
	private function resolve_server_import_directory( string $root_key, string $relative_path ) {
		$relative_path = trim( str_replace( '\\', '/', $relative_path ) );
		$roots         = [];

		foreach ( $this->get_server_import_roots() as $root ) {
			if ( ! empty( $root['value'] ) && ! empty( $root['path'] ) ) {
				$roots[ (string) $root['value'] ] = wp_normalize_path( (string) $root['path'] );
			}
		}

		if ( '' === $relative_path ) {
			return new \WP_Error( 'mlgp_server_import_path_missing', __( 'Informe a pasta relativa do servidor para importar as imagens.', 'ml-gallery-pro' ) );
		}

		if ( ! isset( $roots[ $root_key ] ) ) {
			return new \WP_Error( 'mlgp_server_import_root_invalid', __( 'A raiz selecionada para importacao nao e valida.', 'ml-gallery-pro' ) );
		}

		$segments = array_filter(
			explode( '/', trim( $relative_path, '/' ) ),
			static function ( string $segment ): bool {
				return '' !== $segment && '.' !== $segment;
			}
		);

		foreach ( $segments as $segment ) {
			if ( '..' === $segment ) {
				return new \WP_Error( 'mlgp_server_import_path_invalid', __( 'A pasta relativa informada nao e valida para importacao.', 'ml-gallery-pro' ) );
			}
		}

		$root_path   = trailingslashit( $roots[ $root_key ] );
		$source_path = wp_normalize_path( $root_path . implode( '/', $segments ) );

		if ( 0 !== strpos( trailingslashit( $source_path ), wp_normalize_path( $root_path ) ) ) {
			return new \WP_Error( 'mlgp_server_import_outside_root', __( 'A pasta informada esta fora da raiz autorizada para importacao.', 'ml-gallery-pro' ) );
		}

		if ( ! is_dir( $source_path ) ) {
			return new \WP_Error( 'mlgp_server_import_not_found', __( 'A pasta do servidor informada nao foi encontrada.', 'ml-gallery-pro' ) );
		}

		return $source_path;
	}

	/**
	 * Applies configured image quality to the current editor when supported.
	 *
	 * @param object $editor Image editor instance.
	 * @return void
	 */
	private function apply_editor_quality( $editor ): void {
		if ( is_object( $editor ) && method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( $this->image_quality() );
		}
	}

	/**
	 * Returns normalized processing settings used by local image tools.
	 *
	 * @return array<string, mixed>
	 */
	private function image_processing_settings(): array {
		$settings = (array) get_option( 'mlgp_settings', [] );

		return [
			'thumb_width'        => max( 80, min( 2400, absint( $settings['thumb_width'] ?? 360 ) ) ),
			'thumb_height'       => max( 80, min( 2400, absint( $settings['thumb_height'] ?? 360 ) ) ),
			'thumb_crop'         => ! empty( $settings['thumb_crop'] ) ? 1 : 0,
			'medium_width'       => max( 120, min( 3600, absint( $settings['medium_width'] ?? 900 ) ) ),
			'medium_height'      => max( 120, min( 3600, absint( $settings['medium_height'] ?? 900 ) ) ),
			'large_width'        => max( 240, min( 5200, absint( $settings['large_width'] ?? 1600 ) ) ),
			'large_height'       => max( 240, min( 5200, absint( $settings['large_height'] ?? 1600 ) ) ),
			'watermark_enabled'  => ! empty( $settings['watermark_enabled'] ) ? 1 : 0,
			'watermark_text'     => sanitize_text_field( (string) ( $settings['watermark_text'] ?? '' ) ),
			'watermark_opacity'  => max( 10, min( 95, absint( $settings['watermark_opacity'] ?? 34 ) ) ),
			'watermark_position' => sanitize_key( (string) ( $settings['watermark_position'] ?? 'bottom-right' ) ),
		];
	}

	/**
	 * Applies a safe text watermark to one generated display file when enabled.
	 *
	 * @param string              $file_path Variant file path.
	 * @param string              $variant   Variant key.
	 * @param array<string,mixed> $settings  Processing settings.
	 * @return void
	 */
	private function maybe_apply_watermark_to_file( string $file_path, string $variant, array $settings ): void {
		if ( empty( $settings['watermark_enabled'] ) || empty( $settings['watermark_text'] ) ) {
			return;
		}

		if ( ! in_array( $variant, [ 'medium', 'large' ], true ) || ! file_exists( $file_path ) ) {
			return;
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$this->apply_gd_text_watermark( $file_path, $settings, 'large' === $variant ? 5 : 4 );
		}
	}

	/**
	 * Burns a text watermark into one image file through GD when available.
	 *
	 * @param string              $file_path Variant file path.
	 * @param array<string,mixed> $settings  Processing settings.
	 * @param int                 $font      GD builtin font size.
	 * @return void
	 */
	private function apply_gd_text_watermark( string $file_path, array $settings, int $font ): void {
		$mime = (string) wp_get_image_mime( $file_path );

		switch ( $mime ) {
			case 'image/jpeg':
				$image = function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $file_path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case 'image/png':
				$image = function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $file_path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case 'image/gif':
				$image = function_exists( 'imagecreatefromgif' ) ? @imagecreatefromgif( $file_path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case 'image/webp':
				$image = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file_path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			default:
				$image = false;
		}

		if ( ! is_resource( $image ) && ! ( $image instanceof \GdImage ) ) {
			return;
		}

		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		$text        = $this->truncate_plain_text( (string) $settings['watermark_text'], 42 );
		$font        = max( 2, min( 5, $font ) );
		$text_width  = imagefontwidth( $font ) * strlen( $text );
		$text_height = imagefontheight( $font );
		$position    = $this->resolve_watermark_position(
			imagesx( $image ),
			imagesy( $image ),
			$text_width,
			$text_height,
			(string) $settings['watermark_position']
		);
		$opacity     = max( 10, min( 95, (int) $settings['watermark_opacity'] ) );
		$alpha       = max( 0, min( 127, 127 - (int) round( ( $opacity / 100 ) * 127 ) ) );
		$shadow      = imagecolorallocatealpha( $image, 15, 23, 42, min( 127, $alpha + 26 ) );
		$color       = imagecolorallocatealpha( $image, 255, 255, 255, $alpha );

		imagestring( $image, $font, $position['x'] + 1, $position['y'] + 1, $text, $shadow );
		imagestring( $image, $font, $position['x'], $position['y'], $text, $color );

		switch ( $mime ) {
			case 'image/jpeg':
				@imagejpeg( $image, $file_path, $this->image_quality() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case 'image/png':
				@imagepng( $image, $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case 'image/gif':
				@imagegif( $image, $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case 'image/webp':
				if ( function_exists( 'imagewebp' ) ) {
					@imagewebp( $image, $file_path, $this->image_quality() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				break;
		}

		if ( $image instanceof \GdImage ) {
			imagedestroy( $image );
			return;
		}

		imagedestroy( $image );
	}

	/**
	 * Returns one safe storage filename within the database column limits.
	 *
	 * @param string $directory Target directory.
	 * @param string $filename  Requested file name.
	 * @return string
	 */
	private function prepare_storage_filename( string $directory, string $filename, bool $overwrite = false ): string {
		$safe = $this->truncate_filename( sanitize_file_name( $filename ), 180 );

		if ( $overwrite ) {
			return $safe;
		}

		return wp_unique_filename( $directory, $safe );
	}

	/**
	 * Determines whether a file path points to a supported image for server import.
	 *
	 * @param string $path Absolute file path.
	 * @return bool
	 */
	/**
	 * Resolves the actual storage directory for a gallery.
	 *
	 * Tries in order:
	 * 1. Standard path: gallery-{ID}
	 * 2. Slug-based path: {slug} (migrated galleries)
	 * 3. Path extracted from existing gallery items file_path in DB.
	 *
	 * @param int    $gallery_id Gallery ID.
	 * @param string $slug       Gallery slug (optional).
	 * @return string|null Absolute path or null if nothing found.
	 */
	public function resolve_gallery_real_dir( int $gallery_id, string $slug = '' ): ?string {
		$base = $this->base_dir();

		// 1. Standard directory.
		$standard = $this->gallery_dir( $gallery_id );
		if ( is_dir( $standard ) ) {
			return $standard;
		}

		// 2. Slug-based directory (migrated galleries).
		if ( '' !== $slug ) {
			$slug_dir = trailingslashit( $base ) . sanitize_file_name( $slug );
			if ( is_dir( $slug_dir ) ) {
				return $slug_dir;
			}
		}

		// 3. Detect from existing items file_path.
		global $wpdb;
		$table = $wpdb->prefix . 'mlgp_gallery_items';
		$file_path = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$table} WHERE gallery_id = %d AND storage = 'local' AND file_path != '' LIMIT 1",
				$gallery_id
			)
		); // phpcs:ignore

		if ( ! empty( $file_path ) ) {
			$dir = dirname( wp_normalize_path( $file_path ) );
			if ( '' !== $dir && is_dir( $dir ) ) {
				return $dir;
			}
		}

		return null;
	}

	/**
	 * Resolves the gallery URL from an absolute directory path.
	 *
	 * @param string $gallery_dir Absolute directory path.
	 * @return string Gallery URL.
	 */
	public function resolve_gallery_real_url( string $gallery_dir ): string {
		$base_dir = wp_normalize_path( $this->base_dir() );
		$norm_dir = wp_normalize_path( $gallery_dir );

		// If directory is inside the base storage, compute URL.
		if ( 0 === strpos( $norm_dir, $base_dir ) ) {
			$relative = ltrim( substr( $norm_dir, strlen( $base_dir ) ), '/' );
			return trailingslashit( $this->base_url() ) . $relative;
		}

		// Fallback: try wp_content relative.
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		if ( 0 === strpos( $norm_dir, $content_dir ) ) {
			$relative = ltrim( substr( $norm_dir, strlen( $content_dir ) ), '/' );
			return content_url( $relative );
		}

		return trailingslashit( $this->base_url() ) . basename( $gallery_dir );
	}

	private function is_supported_server_import_image( string $path ): bool {
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$allowed   = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'avif' ];

		return in_array( $extension, $allowed, true );
	}

	/**
	 * Scans a gallery storage directory and builds payloads for every original
	 * image already present on disk, without copying or moving anything.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function scan_gallery_storage_by_folder( string $folder_name ) {
		$base        = $this->base_dir();
		$gallery_dir = trailingslashit( $base ) . sanitize_file_name( $folder_name );

		if ( ! is_dir( $gallery_dir ) ) {
			return new \WP_Error(
				'mlgp_scan_dir_missing',
				sprintf( __( 'A pasta "%s" nao existe no armazenamento.', 'ml-gallery-pro' ), $folder_name )
			);
		}

		$gallery_url = $this->resolve_gallery_real_url( $gallery_dir );
		$payloads    = [];

		try {
			$iterator = new \DirectoryIterator( $gallery_dir );

			foreach ( $iterator as $entry ) {
				if ( $entry->isDot() || ! $entry->isFile() ) {
					continue;
				}

				$filename = $entry->getFilename();

				if ( ! $this->is_supported_server_import_image( $filename ) ) {
					continue;
				}

				if ( preg_match( '/-(thumb|medium|large)\.[a-z]+$/i', $filename ) ) {
					continue;
				}

				$file_path = wp_normalize_path( $entry->getPathname() );

				if ( ! is_readable( $file_path ) ) {
					continue;
				}

				$mime  = (string) ( wp_check_filetype( $filename )['type'] ?? '' );
				$size  = $entry->getSize();
				$title = sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) );

				$payloads[] = [
					'original_name' => sanitize_file_name( $filename ),
					'file_name'     => $filename,
					'file_path'     => $file_path,
					'file_url'      => trailingslashit( $gallery_url ) . $filename,
					'thumb_path'    => '',
					'thumb_url'     => '',
					'medium_path'   => '',
					'medium_url'    => '',
					'large_path'    => '',
					'large_url'     => '',
					'mime_type'     => $mime,
					'width'         => 0,
					'height'        => 0,
					'file_size'     => $size ?: 0,
					'item_title'    => $title,
				];
			}
		} catch ( \Throwable $throwable ) {
			return new \WP_Error(
				'mlgp_scan_iterator_failed',
				__( 'Nao foi possivel ler a pasta.', 'ml-gallery-pro' )
			);
		}

		return $payloads;
	}

	/**
	 * Lists all existing gallery storage subdirectories with their numeric IDs.
	 *
	 * @return array<int, array{gallery_id: int, path: string}>
	 */
	public function list_gallery_storage_dirs(): array {
		$base = $this->base_dir();

		if ( ! is_dir( $base ) ) {
			return [];
		}

		$dirs = [];

		try {
			$iterator = new \DirectoryIterator( $base );

			foreach ( $iterator as $entry ) {
				if ( $entry->isDot() || ! $entry->isDir() ) {
					continue;
				}

				$name = $entry->getFilename();
				$path = wp_normalize_path( $entry->getPathname() );

				$dirs[] = [
					'name' => $name,
					'path' => $path,
				];
			}
		} catch ( \Throwable $throwable ) {
			return [];
		}

		usort( $dirs, static function ( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		} );

		return $dirs;
	}

	/**
	 * Truncates one filename while preserving its extension.
	 *
	 * @param string $filename File name.
	 * @param int    $limit    Max characters.
	 * @return string
	 */
	private function truncate_filename( string $filename, int $limit ): string {
		$filename  = trim( $filename );
		$extension = (string) pathinfo( $filename, PATHINFO_EXTENSION );
		$basename  = (string) pathinfo( $filename, PATHINFO_FILENAME );
		$suffix    = '' !== $extension ? '.' . strtolower( $extension ) : '';
		$max_base  = max( 1, $limit - strlen( $suffix ) );

		return $this->truncate_plain_text( $basename, $max_base ) . $suffix;
	}

	/**
	 * Truncates a plain string to a safe character length.
	 *
	 * @param string $value String value.
	 * @param int    $limit Max characters.
	 * @return string
	 */
	private function truncate_plain_text( string $value, int $limit ): string {
		$value = trim( $value );

		if ( $limit < 1 ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}

	/**
	 * Calculates watermark coordinates inside the current image.
	 *
	 * @param int    $image_width  Image width.
	 * @param int    $image_height Image height.
	 * @param int    $text_width   Text width.
	 * @param int    $text_height  Text height.
	 * @param string $position     Watermark position.
	 * @return array<string,int>
	 */
	private function resolve_watermark_position( int $image_width, int $image_height, int $text_width, int $text_height, string $position ): array {
		$padding = max( 12, (int) round( min( $image_width, $image_height ) * 0.03 ) );
		$left    = $padding;
		$right   = max( $padding, $image_width - $text_width - $padding );
		$top     = $padding;
		$bottom  = max( $padding, $image_height - $text_height - $padding );
		$center_x = max( $padding, (int) floor( ( $image_width - $text_width ) / 2 ) );
		$center_y = max( $padding, (int) floor( ( $image_height - $text_height ) / 2 ) );

		switch ( sanitize_key( $position ) ) {
			case 'top-left':
				return [ 'x' => $left, 'y' => $top ];
			case 'top-right':
				return [ 'x' => $right, 'y' => $top ];
			case 'bottom-left':
				return [ 'x' => $left, 'y' => $bottom ];
			case 'center':
				return [ 'x' => $center_x, 'y' => $center_y ];
			case 'bottom-right':
			default:
				return [ 'x' => $right, 'y' => $bottom ];
		}
	}

	/**
	 * Returns the configured image quality.
	 *
	 * @return int
	 */
	private function image_quality(): int {
		$settings = (array) get_option( 'mlgp_settings', [] );
		$quality  = isset( $settings['image_quality'] ) ? absint( $settings['image_quality'] ) : 82;

		return max( 30, min( 100, $quality ) );
	}

	/**
	 * Writes helper files to avoid directory listing.
	 *
	 * @param string $directory Target directory.
	 * @return void
	 */
	private function write_protection_files( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = [
			trailingslashit( $directory ) . 'index.php'   => "<?php\n// Silence is golden.\n",
			trailingslashit( $directory ) . '.htaccess'   => "Options -Indexes\n",
			trailingslashit( $directory ) . 'web.config'  => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <directoryBrowse enabled=\"false\" />\n  </system.webServer>\n</configuration>\n",
		];

		foreach ( $files as $file_path => $contents ) {
			if ( file_exists( $file_path ) ) {
				continue;
			}

			@file_put_contents( $file_path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Deletes one file only when it belongs to the storage directory.
	 *
	 * @param string $path File path.
	 * @return void
	 */
	private function safe_unlink( string $path ): void {
		$normalized_base = wp_normalize_path( trailingslashit( $this->base_dir() ) );
		$normalized_path = wp_normalize_path( $path );

		if ( '' === $normalized_path || 0 !== strpos( $normalized_path, $normalized_base ) || ! file_exists( $normalized_path ) ) {
			return;
		}

		@unlink( $normalized_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Deletes one directory recursively when it belongs to the storage base.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function delete_directory( string $directory ): void {
		$normalized_base = wp_normalize_path( trailingslashit( $this->base_dir() ) );
		$normalized_dir  = wp_normalize_path( trailingslashit( $directory ) );

		if ( '' === $normalized_dir || 0 !== strpos( $normalized_dir, $normalized_base ) || ! is_dir( $normalized_dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $normalized_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				continue;
			}

			@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		@rmdir( $normalized_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
