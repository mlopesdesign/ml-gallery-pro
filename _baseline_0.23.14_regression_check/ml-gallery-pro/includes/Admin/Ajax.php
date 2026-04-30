<?php
/**
 * Admin AJAX endpoints.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Admin;

use MLGP\Database\Repository;
use MLGP\License\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ajax {

	/**
	 * Repository instance.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * License manager.
	 *
	 * @var Manager
	 */
	private $license_manager;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Shared repository.
	 * @param Manager    $license_manager Shared license manager.
	 */
	public function __construct( Repository $repository, Manager $license_manager ) {
		$this->repository      = $repository;
		$this->license_manager = $license_manager;
	}

	/**
	 * Registers AJAX hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_ajax_mlgp_get_dashboard', [ $this, 'get_dashboard' ] );
		add_action( 'wp_ajax_mlgp_list_galleries', [ $this, 'list_galleries' ] );
		add_action( 'wp_ajax_mlgp_get_gallery_editor', [ $this, 'get_gallery_editor' ] );
		add_action( 'wp_ajax_mlgp_save_gallery', [ $this, 'save_gallery' ] );
		add_action( 'wp_ajax_mlgp_create_gallery_with_uploads', [ $this, 'create_gallery_with_uploads' ] );
		add_action( 'wp_ajax_mlgp_upload_gallery_images', [ $this, 'upload_gallery_images' ] );
		add_action( 'wp_ajax_mlgp_import_gallery_zip', [ $this, 'import_gallery_zip' ] );
		add_action( 'wp_ajax_mlgp_import_gallery_directory', [ $this, 'import_gallery_directory' ] );
		add_action( 'wp_ajax_mlgp_add_gallery_items', [ $this, 'add_gallery_items' ] );
		add_action( 'wp_ajax_mlgp_save_gallery_items', [ $this, 'save_gallery_items' ] );
		add_action( 'wp_ajax_mlgp_bulk_update_gallery_items', [ $this, 'bulk_update_gallery_items' ] );
		add_action( 'wp_ajax_mlgp_delete_gallery', [ $this, 'delete_gallery' ] );
		add_action( 'wp_ajax_mlgp_delete_galleries_bulk', [ $this, 'delete_galleries_bulk' ] );
		add_action( 'wp_ajax_mlgp_delete_all_galleries', [ $this, 'delete_all_galleries' ] );
		add_action( 'wp_ajax_mlgp_delete_all_gallery_images', [ $this, 'delete_all_gallery_images' ] );
		add_action( 'wp_ajax_mlgp_list_albums', [ $this, 'list_albums' ] );
		add_action( 'wp_ajax_mlgp_get_album_editor', [ $this, 'get_album_editor' ] );
		add_action( 'wp_ajax_mlgp_save_album', [ $this, 'save_album' ] );
		add_action( 'wp_ajax_mlgp_save_album_items', [ $this, 'save_album_items' ] );
		add_action( 'wp_ajax_mlgp_delete_album', [ $this, 'delete_album' ] );
		add_action( 'wp_ajax_mlgp_delete_albums_bulk', [ $this, 'delete_albums_bulk' ] );
		add_action( 'wp_ajax_mlgp_list_tags', [ $this, 'list_tags' ] );
		add_action( 'wp_ajax_mlgp_get_settings', [ $this, 'get_settings' ] );
		add_action( 'wp_ajax_mlgp_get_validation_report', [ $this, 'get_validation_report' ] );
		add_action( 'wp_ajax_mlgp_validate_license', [ $this, 'validate_license' ] );
		add_action( 'wp_ajax_mlgp_deactivate_license', [ $this, 'deactivate_license' ] );
		add_action( 'wp_ajax_mlgp_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_mlgp_apply_settings_to_all_galleries', [ $this, 'apply_settings_to_all_galleries' ] );
		add_action( 'wp_ajax_mlgp_apply_settings_to_all_albums', [ $this, 'apply_settings_to_all_albums' ] );
		add_action( 'wp_ajax_mlgp_regenerate_all_local_items', [ $this, 'regenerate_all_local_items' ] );
		add_action( 'wp_ajax_mlgp_regenerate_local_items_batch', [ $this, 'regenerate_local_items_batch' ] );
		add_action( 'wp_ajax_mlgp_factory_reset', [ $this, 'factory_reset' ] );
	}

	/**
	 * Returns dashboard payload.
	 *
	 * @return void
	 */
	public function get_dashboard(): void {
		$this->authorize();

		wp_send_json_success(
			[
				'stats'            => $this->repository->get_stats(),
				'recent_galleries' => $this->repository->get_recent_galleries(),
				'recent_albums'    => $this->repository->get_recent_albums(),
				'validation'       => $this->repository->get_validation_report(),
				'license'          => $this->license_manager->build_payload(),
			]
		);
	}

	/**
	 * Validates a license key via the license manager.
	 *
	 * @return void
	 */
	public function validate_license(): void {
		$this->authorize();
		$this->verify_license_request_nonce();

		$license_key = $this->post( 'license_key' );
		$state       = $this->license_manager->validate_license( $license_key );

		wp_send_json_success(
			[
				'license' => $state,
			]
		);
	}

	/**
	 * Deactivates the current license.
	 *
	 * @return void
	 */
	/**
	 * Verifies the nonce used by the license actions.
	 *
	 * @return void
	 */
	private function verify_license_request_nonce(): void {
		$nonce = '';

		if ( isset( $_POST['license_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['license_nonce'] ) );
		} elseif ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'mlgp_license_action_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Falha de segurança na validação da licença.', 'ml-gallery-pro' ) ], 403 );
		}
	}

	public function deactivate_license(): void {
		$this->authorize();
		$this->verify_license_request_nonce();

		$state = $this->license_manager->deactivate_license();

		wp_send_json_success(
			[
				'license' => $state,
			]
		);
	}

	/**
	 * Returns gallery list.
	 *
	 * @return void
	 */
	public function list_galleries(): void {
		$this->authorize();
		$sort_mode = $this->resolve_collection_sort_mode( 'sort_mode', 'mlgp_gallery_sort_mode' );

		wp_send_json_success(
			[
				'items'     => $this->repository->get_galleries( $sort_mode ),
				'sort_mode' => $sort_mode,
			]
		);
	}

	/**
	 * Returns gallery editor payload.
	 *
	 * @return void
	 */
	public function get_gallery_editor(): void {
		$this->authorize();

		$gallery_id = absint( $this->post( 'gallery_id' ) );
		$editor     = $this->repository->get_gallery_editor( $gallery_id );

		if ( empty( $editor ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Galeria nao encontrada.', 'ml-gallery-pro' ),
				],
				404
			);
		}

		wp_send_json_success( $editor );
	}

	/**
	 * Creates or updates a gallery.
	 *
	 * @return void
	 */
	public function save_gallery(): void {
		$this->authorize();

		$gallery = $this->repository->save_gallery(
			[
				'id'          => $this->post( 'id' ),
				'title'       => $this->post( 'title' ),
				'slug'        => $this->post( 'slug' ),
				'description' => $this->post( 'description' ),
				'status'      => $this->post( 'status' ),
				'display_type'=> $this->post( 'display_type' ),
				'settings'    => $this->decode_json_object( $this->post( 'settings' ) ),
			]
		);

		if ( is_wp_error( $gallery ) ) {
			wp_send_json_error(
				[
					'message' => $gallery->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'item'    => $gallery,
				'editor'  => $this->repository->get_gallery_editor( (int) $gallery['id'] ),
				'message' => __( 'Galeria salva com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Creates a gallery and optionally uploads images in the same request.
	 *
	 * @return void
	 */
	public function create_gallery_with_uploads(): void {
		$this->authorize();

		$gallery = $this->repository->save_gallery(
			[
				'title'       => $this->post( 'title' ),
				'slug'        => $this->post( 'slug' ),
				'description' => $this->post( 'description' ),
				'status'      => $this->post( 'status' ),
			]
		);

		if ( is_wp_error( $gallery ) ) {
			wp_send_json_error(
				[
					'message' => $gallery->get_error_message(),
				],
				422
			);
		}

		$gallery_id = (int) $gallery['id'];
		$files      = $_FILES['files'] ?? [];
		$zip_file   = $_FILES['zip_file'] ?? [];
		$source     = sanitize_key( $this->post( 'source' ) );
		$server_root = $this->post( 'server_root' );
		$server_path = $this->post( 'server_path' );
		$has_files  = ! empty( $files['name'] );
		$has_zip    = ! empty( $zip_file['name'] );
		$has_server = 'server' === $source && '' !== trim( $server_path );
		$editor     = $this->repository->get_gallery_editor( $gallery_id );

		if ( $has_zip ) {
			$editor = $this->repository->import_gallery_zip( $gallery_id, $zip_file );

			if ( is_wp_error( $editor ) ) {
				wp_send_json_error(
					[
						'message'    => $editor->get_error_message(),
						'gallery_id' => $gallery_id,
						'editor'     => $this->repository->get_gallery_editor( $gallery_id ),
					],
					422
				);
			}
		} elseif ( $has_files ) {
			$editor = $this->repository->upload_gallery_files( $gallery_id, $files );

			if ( is_wp_error( $editor ) ) {
				wp_send_json_error(
					[
						'message'    => $editor->get_error_message(),
						'gallery_id' => $gallery_id,
						'editor'     => $this->repository->get_gallery_editor( $gallery_id ),
					],
					422
				);
			}
		} elseif ( $has_server ) {
			$editor = $this->repository->import_gallery_directory( $gallery_id, $server_root, $server_path );

			if ( is_wp_error( $editor ) ) {
				wp_send_json_error(
					[
						'message'    => $editor->get_error_message(),
						'gallery_id' => $gallery_id,
						'editor'     => $this->repository->get_gallery_editor( $gallery_id ),
					],
					422
				);
			}
		}

		wp_send_json_success(
			[
				'item'    => $gallery,
				'editor'  => $editor,
				'message' => $has_zip
					? __( 'Galeria criada e ZIP importado com sucesso.', 'ml-gallery-pro' )
					: ( $has_files
						? __( 'Galeria criada e imagens enviadas com sucesso.', 'ml-gallery-pro' )
						: ( $has_server
							? __( 'Galeria criada e pasta do servidor importada com sucesso.', 'ml-gallery-pro' )
							: __( 'Galeria criada com sucesso.', 'ml-gallery-pro' )
						)
					),
			]
		);
	}

	/**
	 * Adds media items to a gallery.
	 *
	 * @return void
	 */
	public function add_gallery_items(): void {
		$this->authorize();

		$gallery_id     = absint( $this->post( 'gallery_id' ) );
		$attachment_ids = $this->decode_json_array( $this->post( 'attachment_ids' ) );
		$editor         = $this->repository->add_gallery_items( $gallery_id, $attachment_ids );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message'    => $editor->get_error_message(),
					'gallery_id' => $gallery_id,
					'editor'     => $this->repository->get_gallery_editor( $gallery_id ),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => __( 'Imagens adicionadas com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Uploads local image files to the gallery storage.
	 *
	 * @return void
	 */
	public function upload_gallery_images(): void {
		$this->authorize();

		$gallery_id = absint( $this->post( 'gallery_id' ) );
		$editor     = $this->repository->upload_gallery_files( $gallery_id, $_FILES['files'] ?? [] );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message'    => $editor->get_error_message(),
					'gallery_id' => $gallery_id,
					'editor'     => $this->repository->get_gallery_editor( $gallery_id ),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => __( 'Imagens enviadas com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Imports one ZIP file into the current gallery.
	 *
	 * @return void
	 */
	public function import_gallery_zip(): void {
		$this->authorize();

		$gallery_id = absint( $this->post( 'gallery_id' ) );
		$editor     = $this->repository->import_gallery_zip( $gallery_id, $_FILES['zip_file'] ?? [] );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message'    => $editor->get_error_message(),
					'gallery_id' => $gallery_id,
					'editor'     => $this->repository->get_gallery_editor( $gallery_id ),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => __( 'ZIP importado com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Imports one allowed server directory into the current gallery.
	 *
	 * @return void
	 */
	public function import_gallery_directory(): void {
		$this->authorize();

		$gallery_id = absint( $this->post( 'gallery_id' ) );
		$editor     = $this->repository->import_gallery_directory(
			$gallery_id,
			$this->post( 'server_root' ),
			$this->post( 'server_path' )
		);

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message' => $editor->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => __( 'Pasta do servidor importada com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Saves gallery item ordering and metadata.
	 *
	 * @return void
	 */
	public function save_gallery_items(): void {
		$this->authorize();

		$gallery_id    = absint( $this->post( 'gallery_id' ) );
		$cover_item_id = absint( $this->post( 'cover_item_id' ) );
		$items         = $this->decode_json_array( $this->post( 'items' ) );
		$editor        = $this->repository->save_gallery_items( $gallery_id, $items, $cover_item_id );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message' => $editor->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => __( 'Itens da galeria salvos com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Applies one bulk action to selected gallery items.
	 *
	 * @return void
	 */
	public function bulk_update_gallery_items(): void {
		$this->authorize();

		$gallery_id = absint( $this->post( 'gallery_id' ) );
		$item_ids   = $this->decode_json_array( $this->post( 'item_ids' ) );
		$action     = $this->post( 'bulk_action' );
		$options    = $this->decode_json_object( $this->post( 'bulk_payload' ) );
		$editor     = $this->repository->bulk_update_gallery_items( $gallery_id, $item_ids, $action, $options );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message' => $editor->get_error_message(),
				],
				422
			);
		}

		$message = __( 'Acoes em massa aplicadas com sucesso.', 'ml-gallery-pro' );

		if ( 'delete' === $action ) {
			$message = __( 'Imagens excluidas com sucesso.', 'ml-gallery-pro' );
		} elseif ( 'hide' === $action ) {
			$message = __( 'Imagens ocultadas com sucesso.', 'ml-gallery-pro' );
		} elseif ( 'show' === $action ) {
			$message = __( 'Imagens exibidas com sucesso.', 'ml-gallery-pro' );
		} elseif ( 'replace_titles' === $action ) {
			$message = __( 'Titulos substituidos com sucesso nas imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'clear_titles' === $action ) {
			$message = __( 'Titulos removidos com sucesso das imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'replace_alts' === $action ) {
			$message = __( 'ALT substituido com sucesso nas imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'clear_alts' === $action ) {
			$message = __( 'ALT removido com sucesso das imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'replace_captions' === $action ) {
			$message = __( 'Legendas substituidas com sucesso nas imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'clear_captions' === $action ) {
			$message = __( 'Legendas removidas com sucesso das imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'append_tags' === $action ) {
			$message = __( 'Tags adicionadas com sucesso nas imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'replace_tags' === $action ) {
			$message = __( 'Tags substituidas com sucesso nas imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'clear_tags' === $action ) {
			$message = __( 'Tags removidas com sucesso das imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'regenerate' === $action ) {
			$message = __( 'Previews regeneradas com sucesso para as imagens selecionadas.', 'ml-gallery-pro' );
		} elseif ( 'rotate_left' === $action ) {
			$message = __( 'Imagens rotacionadas com sucesso 90 graus para a esquerda.', 'ml-gallery-pro' );
		} elseif ( 'rotate_right' === $action ) {
			$message = __( 'Imagens rotacionadas com sucesso 90 graus para a direita.', 'ml-gallery-pro' );
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => $message,
			]
		);
	}

	/**
	 * Deletes a gallery.
	 *
	 * @return void
	 */
	public function delete_gallery(): void {
		$this->authorize();

		$deleted = $this->repository->delete_gallery( absint( $this->post( 'id' ) ) );

		if ( ! $deleted ) {
			wp_send_json_error(
				[
					'message' => __( 'Nao foi possivel excluir a galeria.', 'ml-gallery-pro' ),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'Galeria excluida com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Deletes multiple galleries in one request.
	 *
	 * @return void
	 */
	public function delete_galleries_bulk(): void {
		$this->authorize();

		$ids     = array_map( 'absint', $this->decode_json_array( $this->post( 'ids' ) ) );
		$deleted = $this->repository->delete_galleries_bulk( $ids );

		if ( is_wp_error( $deleted ) ) {
			wp_send_json_error(
				[
					'message' => $deleted->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'deleted_count' => (int) $deleted,
				'message'       => __( 'Galerias selecionadas excluidas com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Deletes every gallery and every image.
	 *
	 * @return void
	 */
	public function delete_all_galleries(): void {
		$this->authorize();

		$deleted = $this->repository->delete_all_galleries();

		if ( is_wp_error( $deleted ) ) {
			wp_send_json_error(
				[
					'message' => $deleted->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'deleted_count' => (int) $deleted,
				'message'       => __( 'Todas as galerias foram excluidas com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Deletes every image while keeping gallery records.
	 *
	 * @return void
	 */
	public function delete_all_gallery_images(): void {
		$this->authorize();

		$deleted = $this->repository->delete_all_gallery_images();

		if ( is_wp_error( $deleted ) ) {
			wp_send_json_error(
				[
					'message' => $deleted->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'deleted_count' => (int) $deleted,
				'message'       => __( 'Todas as imagens foram excluidas com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Returns album list.
	 *
	 * @return void
	 */
	public function list_albums(): void {
		$this->authorize();
		$sort_mode = $this->resolve_collection_sort_mode( 'sort_mode', 'mlgp_album_sort_mode' );

		wp_send_json_success(
			[
				'items'     => $this->repository->get_albums( $sort_mode ),
				'sort_mode' => $sort_mode,
			]
		);
	}

	/**
	 * Returns album editor payload.
	 *
	 * @return void
	 */
	public function get_album_editor(): void {
		$this->authorize();

		$album_id = absint( $this->post( 'album_id' ) );
		$editor   = $this->repository->get_album_editor( $album_id );

		if ( empty( $editor ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Album nao encontrado.', 'ml-gallery-pro' ),
				],
				404
			);
		}

		wp_send_json_success( $editor );
	}

	/**
	 * Creates or updates an album.
	 *
	 * @return void
	 */
	public function save_album(): void {
		$this->authorize();
		$settings = json_decode( wp_unslash( (string) $this->post( 'settings' ) ), true );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$album = $this->repository->save_album(
			[
				'id'          => $this->post( 'id' ),
				'title'       => $this->post( 'title' ),
				'slug'        => $this->post( 'slug' ),
				'description' => $this->post( 'description' ),
				'status'      => $this->post( 'status' ),
				'display_type'=> $this->post( 'display_type' ),
				'settings'    => $settings,
			]
		);

		if ( is_wp_error( $album ) ) {
			wp_send_json_error(
				[
					'message' => $album->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'item'    => $album,
				'editor'  => $this->repository->get_album_editor( (int) $album['id'] ),
				'message' => __( 'Album salvo com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Saves album structure items.
	 *
	 * @return void
	 */
	public function save_album_items(): void {
		$this->authorize();

		$album_id = absint( $this->post( 'album_id' ) );
		$items    = $this->decode_json_array( $this->post( 'items' ) );
		$editor   = $this->repository->save_album_items( $album_id, $items );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error(
				[
					'message' => $editor->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'editor'  => $editor,
				'message' => __( 'Estrutura do album salva com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Deletes an album.
	 *
	 * @return void
	 */
	public function delete_album(): void {
		$this->authorize();

		$deleted = $this->repository->delete_album( absint( $this->post( 'id' ) ) );

		if ( ! $deleted ) {
			wp_send_json_error(
				[
					'message' => __( 'Nao foi possivel excluir o album.', 'ml-gallery-pro' ),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'Album excluido com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Deletes multiple albums in one request.
	 *
	 * @return void
	 */
	public function delete_albums_bulk(): void {
		$this->authorize();

		$ids     = array_map( 'absint', $this->decode_json_array( $this->post( 'ids' ) ) );
		$deleted = $this->repository->delete_albums_bulk( $ids );

		if ( is_wp_error( $deleted ) ) {
			wp_send_json_error(
				[
					'message' => $deleted->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'deleted_count' => (int) $deleted,
				'message'       => __( 'Albuns selecionados excluidos com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Returns the tag catalog for admin use.
	 *
	 * @return void
	 */
	public function list_tags(): void {
		$this->authorize();

		wp_send_json_success( $this->repository->get_tag_report() );
	}

	/**
	 * Returns saved settings.
	 *
	 * @return void
	 */
	public function get_settings(): void {
		$this->authorize();

		wp_send_json_success(
			[
				'settings'   => $this->repository->get_settings(),
				'validation' => $this->repository->get_validation_report(),
			]
		);
	}

	/**
	 * Returns only the diagnostics report used by the validation tab.
	 *
	 * @return void
	 */
	public function get_validation_report(): void {
		$this->authorize();

		wp_send_json_success(
			[
				'validation' => $this->repository->get_validation_report(),
			]
		);
	}

	/**
	 * Saves settings.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->authorize();

		$settings = $this->repository->save_settings(
			[
				'columns_desktop'       => $this->post( 'columns_desktop' ),
				'columns_tablet'        => $this->post( 'columns_tablet' ),
				'columns_mobile'        => $this->post( 'columns_mobile' ),
				'card_gap'              => $this->post( 'card_gap' ),
				'card_padding'          => $this->post( 'card_padding' ),
				'card_margin'           => $this->post( 'card_margin' ),
				'card_border_width'     => $this->post( 'card_border_width' ),
				'card_border_color'     => $this->post( 'card_border_color' ),
				'card_border_opacity'   => $this->post( 'card_border_opacity' ),
				'gap_background_color'  => $this->post( 'gap_background_color' ),
				'gap_background_opacity'=> $this->post( 'gap_background_opacity' ),
				'wrapper_padding'       => $this->post( 'wrapper_padding' ),
				'wrapper_radius'        => $this->post( 'wrapper_radius' ),
				'wrapper_border_width'  => $this->post( 'wrapper_border_width' ),
				'wrapper_border_color'  => $this->post( 'wrapper_border_color' ),
				'wrapper_border_opacity'=> $this->post( 'wrapper_border_opacity' ),
				'wrapper_background_color'  => $this->post( 'wrapper_background_color' ),
				'wrapper_background_opacity'=> $this->post( 'wrapper_background_opacity' ),
				'wrapper_shadow_opacity'=> $this->post( 'wrapper_shadow_opacity' ),
				'wrapper_max_width'     => $this->post( 'wrapper_max_width' ),
				'default_gallery_preset'=> $this->post( 'default_gallery_preset' ),
				'default_album_display_type' => $this->post( 'default_album_display_type' ),
				'album_columns_desktop' => $this->post( 'album_columns_desktop' ),
				'album_columns_tablet' => $this->post( 'album_columns_tablet' ),
				'album_columns_mobile' => $this->post( 'album_columns_mobile' ),
				'album_card_gap' => $this->post( 'album_card_gap' ),
				'album_card_padding' => $this->post( 'album_card_padding' ),
				'album_card_margin' => $this->post( 'album_card_margin' ),
				'album_card_border_width' => $this->post( 'album_card_border_width' ),
				'album_card_border_color' => $this->post( 'album_card_border_color' ),
				'album_card_border_opacity' => $this->post( 'album_card_border_opacity' ),
				'album_gap_background_color' => $this->post( 'album_gap_background_color' ),
				'album_gap_background_opacity' => $this->post( 'album_gap_background_opacity' ),
				'album_card_radius' => $this->post( 'album_card_radius' ),
				'album_pagination_enabled' => $this->post( 'album_pagination_enabled' ),
				'album_items_per_page' => $this->post( 'album_items_per_page' ),
				'album_show_titles' => $this->post( 'album_show_titles' ),
				'album_show_captions' => $this->post( 'album_show_captions' ),
				'album_show_heading' => $this->post( 'album_show_heading' ),
				'album_show_description' => $this->post( 'album_show_description' ),
				'album_item_title_font_size' => $this->post( 'album_item_title_font_size' ),
				'album_item_title_color' => $this->post( 'album_item_title_color' ),
				'enable_frontend_filters' => $this->post( 'enable_frontend_filters' ),
				'items_per_page'        => $this->post( 'items_per_page' ),
				'pagination_enabled'    => $this->post( 'pagination_enabled' ),
				'show_titles'          => $this->post( 'show_titles' ),
				'show_captions'        => $this->post( 'show_captions' ),
				'show_item_tags'       => $this->post( 'show_item_tags' ),
				'hide_all_titles'      => $this->post( 'hide_all_titles' ),
				'show_gallery_heading' => $this->post( 'show_gallery_heading' ),
				'show_gallery_description' => $this->post( 'show_gallery_description' ),
				'image_quality'         => $this->post( 'image_quality' ),
				'thumb_width'           => $this->post( 'thumb_width' ),
				'thumb_height'          => $this->post( 'thumb_height' ),
				'thumb_crop'            => $this->post( 'thumb_crop' ),
				'medium_width'          => $this->post( 'medium_width' ),
				'medium_height'         => $this->post( 'medium_height' ),
				'large_width'           => $this->post( 'large_width' ),
				'large_height'          => $this->post( 'large_height' ),
				'album_cover_width'     => $this->post( 'album_cover_width' ),
				'album_cover_height'    => $this->post( 'album_cover_height' ),
				'album_cover_fit'       => $this->post( 'album_cover_fit' ),
				'album_cover_lock_ratio' => $this->post( 'album_cover_lock_ratio' ),
				'watermark_enabled'     => $this->post( 'watermark_enabled' ),
				'watermark_text'        => $this->post( 'watermark_text' ),
				'watermark_opacity'     => $this->post( 'watermark_opacity' ),
				'watermark_position'    => $this->post( 'watermark_position' ),
				'rounded_corners'       => $this->post( 'rounded_corners' ),
				'slideshow_show_arrows' => $this->post( 'slideshow_show_arrows' ),
				'slideshow_show_thumbs' => $this->post( 'slideshow_show_thumbs' ),
				'nav_arrow_prev_url'    => $this->post( 'nav_arrow_prev_url' ),
				'nav_arrow_next_url'    => $this->post( 'nav_arrow_next_url' ),
				'heading_font_size'     => $this->post( 'heading_font_size' ),
				'heading_color'         => $this->post( 'heading_color' ),
				'item_title_font_size'  => $this->post( 'item_title_font_size' ),
				'item_title_color'      => $this->post( 'item_title_color' ),
				'enable_lightbox'       => $this->post( 'enable_lightbox' ),
				'enable_lazy_load'      => $this->post( 'enable_lazy_load' ),
				'label_view_gallery'    => $this->post( 'label_view_gallery' ),
				'label_back_to_album'   => $this->post( 'label_back_to_album' ),
				'empty_gallery_message' => $this->post( 'empty_gallery_message' ),
				'empty_album_message'   => $this->post( 'empty_album_message' ),
			]
		);

		wp_send_json_success(
			[
				'settings'   => $settings,
				'validation' => $this->repository->get_validation_report(),
				'message'    => __( 'Configuracoes salvas com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}


	/**
	 * Saves current settings and applies album defaults to all existing albums.
	 *
	 * @return void
	 */
	public function apply_settings_to_all_albums(): void {
		$this->authorize();

		$settings = $this->repository->save_settings(
			[
				'columns_desktop'       => $this->post( 'columns_desktop' ),
				'columns_tablet'        => $this->post( 'columns_tablet' ),
				'columns_mobile'        => $this->post( 'columns_mobile' ),
				'card_gap'              => $this->post( 'card_gap' ),
				'card_padding'          => $this->post( 'card_padding' ),
				'card_margin'           => $this->post( 'card_margin' ),
				'card_border_width'     => $this->post( 'card_border_width' ),
				'card_border_color'     => $this->post( 'card_border_color' ),
				'card_border_opacity'   => $this->post( 'card_border_opacity' ),
				'gap_background_color'  => $this->post( 'gap_background_color' ),
				'gap_background_opacity'=> $this->post( 'gap_background_opacity' ),
				'wrapper_padding'       => $this->post( 'wrapper_padding' ),
				'wrapper_radius'        => $this->post( 'wrapper_radius' ),
				'wrapper_border_width'  => $this->post( 'wrapper_border_width' ),
				'wrapper_border_color'  => $this->post( 'wrapper_border_color' ),
				'wrapper_border_opacity'=> $this->post( 'wrapper_border_opacity' ),
				'wrapper_background_color'  => $this->post( 'wrapper_background_color' ),
				'wrapper_background_opacity'=> $this->post( 'wrapper_background_opacity' ),
				'wrapper_shadow_opacity'=> $this->post( 'wrapper_shadow_opacity' ),
				'wrapper_max_width'     => $this->post( 'wrapper_max_width' ),
				'default_gallery_preset'=> $this->post( 'default_gallery_preset' ),
				'default_album_display_type' => $this->post( 'default_album_display_type' ),
				'album_columns_desktop' => $this->post( 'album_columns_desktop' ),
				'album_columns_tablet' => $this->post( 'album_columns_tablet' ),
				'album_columns_mobile' => $this->post( 'album_columns_mobile' ),
				'album_card_gap' => $this->post( 'album_card_gap' ),
				'album_card_padding' => $this->post( 'album_card_padding' ),
				'album_card_margin' => $this->post( 'album_card_margin' ),
				'album_card_border_width' => $this->post( 'album_card_border_width' ),
				'album_card_border_color' => $this->post( 'album_card_border_color' ),
				'album_card_border_opacity' => $this->post( 'album_card_border_opacity' ),
				'album_gap_background_color' => $this->post( 'album_gap_background_color' ),
				'album_gap_background_opacity' => $this->post( 'album_gap_background_opacity' ),
				'album_card_radius' => $this->post( 'album_card_radius' ),
				'album_pagination_enabled' => $this->post( 'album_pagination_enabled' ),
				'album_items_per_page' => $this->post( 'album_items_per_page' ),
				'album_show_titles' => $this->post( 'album_show_titles' ),
				'album_show_captions' => $this->post( 'album_show_captions' ),
				'album_show_heading' => $this->post( 'album_show_heading' ),
				'album_show_description' => $this->post( 'album_show_description' ),
				'album_item_title_font_size' => $this->post( 'album_item_title_font_size' ),
				'album_item_title_color' => $this->post( 'album_item_title_color' ),
				'enable_frontend_filters' => $this->post( 'enable_frontend_filters' ),
				'items_per_page'        => $this->post( 'items_per_page' ),
				'pagination_enabled'    => $this->post( 'pagination_enabled' ),
				'show_titles'          => $this->post( 'show_titles' ),
				'show_captions'        => $this->post( 'show_captions' ),
				'show_item_tags'       => $this->post( 'show_item_tags' ),
				'hide_all_titles'      => $this->post( 'hide_all_titles' ),
				'show_gallery_heading' => $this->post( 'show_gallery_heading' ),
				'show_gallery_description' => $this->post( 'show_gallery_description' ),
				'image_quality'         => $this->post( 'image_quality' ),
				'thumb_width'           => $this->post( 'thumb_width' ),
				'thumb_height'          => $this->post( 'thumb_height' ),
				'thumb_crop'            => $this->post( 'thumb_crop' ),
				'medium_width'          => $this->post( 'medium_width' ),
				'medium_height'         => $this->post( 'medium_height' ),
				'large_width'           => $this->post( 'large_width' ),
				'large_height'          => $this->post( 'large_height' ),
				'album_cover_width'     => $this->post( 'album_cover_width' ),
				'album_cover_height'    => $this->post( 'album_cover_height' ),
				'album_cover_fit'       => $this->post( 'album_cover_fit' ),
				'album_cover_lock_ratio' => $this->post( 'album_cover_lock_ratio' ),
				'watermark_enabled'     => $this->post( 'watermark_enabled' ),
				'watermark_text'        => $this->post( 'watermark_text' ),
				'watermark_opacity'     => $this->post( 'watermark_opacity' ),
				'watermark_position'    => $this->post( 'watermark_position' ),
				'rounded_corners'       => $this->post( 'rounded_corners' ),
				'slideshow_show_arrows' => $this->post( 'slideshow_show_arrows' ),
				'slideshow_show_thumbs' => $this->post( 'slideshow_show_thumbs' ),
				'nav_arrow_prev_url'    => $this->post( 'nav_arrow_prev_url' ),
				'nav_arrow_next_url'    => $this->post( 'nav_arrow_next_url' ),
				'heading_font_size'     => $this->post( 'heading_font_size' ),
				'heading_color'         => $this->post( 'heading_color' ),
				'item_title_font_size'  => $this->post( 'item_title_font_size' ),
				'item_title_color'      => $this->post( 'item_title_color' ),
				'enable_lightbox'       => $this->post( 'enable_lightbox' ),
				'enable_lazy_load'      => $this->post( 'enable_lazy_load' ),
				'label_view_gallery'    => $this->post( 'label_view_gallery' ),
				'label_back_to_album'   => $this->post( 'label_back_to_album' ),
				'empty_gallery_message' => $this->post( 'empty_gallery_message' ),
				'empty_album_message'   => $this->post( 'empty_album_message' ),
			]
		);

		$updated = $this->repository->apply_settings_to_all_albums( $settings );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error(
				[ 'message' => $updated->get_error_message() ],
				500
			);
		}

		wp_send_json_success(
			[
				'settings'   => $settings,
				'updated'    => (int) $updated,
				'validation' => $this->repository->get_validation_report(),
				'message'    => sprintf(
					/* translators: %d: number of updated albums. */
					__( 'Configuracao aplicada a %d albuns.', 'ml-gallery-pro' ),
					(int) $updated
				),
			]
		);
	}

	/**
	 * Saves current settings and applies them to all existing galleries.
	 *
	 * @return void
	 */
	public function apply_settings_to_all_galleries(): void {
		$this->authorize();

		$settings = $this->repository->save_settings(
			[
				'columns_desktop'       => $this->post( 'columns_desktop' ),
				'columns_tablet'        => $this->post( 'columns_tablet' ),
				'columns_mobile'        => $this->post( 'columns_mobile' ),
				'card_gap'              => $this->post( 'card_gap' ),
				'card_padding'          => $this->post( 'card_padding' ),
				'card_margin'           => $this->post( 'card_margin' ),
				'card_border_width'     => $this->post( 'card_border_width' ),
				'card_border_color'     => $this->post( 'card_border_color' ),
				'card_border_opacity'   => $this->post( 'card_border_opacity' ),
				'gap_background_color'  => $this->post( 'gap_background_color' ),
				'gap_background_opacity'=> $this->post( 'gap_background_opacity' ),
				'wrapper_padding'       => $this->post( 'wrapper_padding' ),
				'wrapper_radius'        => $this->post( 'wrapper_radius' ),
				'wrapper_border_width'  => $this->post( 'wrapper_border_width' ),
				'wrapper_border_color'  => $this->post( 'wrapper_border_color' ),
				'wrapper_border_opacity'=> $this->post( 'wrapper_border_opacity' ),
				'wrapper_background_color'  => $this->post( 'wrapper_background_color' ),
				'wrapper_background_opacity'=> $this->post( 'wrapper_background_opacity' ),
				'wrapper_shadow_opacity'=> $this->post( 'wrapper_shadow_opacity' ),
				'wrapper_max_width'     => $this->post( 'wrapper_max_width' ),
				'default_gallery_preset'=> $this->post( 'default_gallery_preset' ),
				'default_album_display_type' => $this->post( 'default_album_display_type' ),
				'album_columns_desktop' => $this->post( 'album_columns_desktop' ),
				'album_columns_tablet' => $this->post( 'album_columns_tablet' ),
				'album_columns_mobile' => $this->post( 'album_columns_mobile' ),
				'album_card_gap' => $this->post( 'album_card_gap' ),
				'album_card_padding' => $this->post( 'album_card_padding' ),
				'album_card_margin' => $this->post( 'album_card_margin' ),
				'album_card_border_width' => $this->post( 'album_card_border_width' ),
				'album_card_border_color' => $this->post( 'album_card_border_color' ),
				'album_card_border_opacity' => $this->post( 'album_card_border_opacity' ),
				'album_gap_background_color' => $this->post( 'album_gap_background_color' ),
				'album_gap_background_opacity' => $this->post( 'album_gap_background_opacity' ),
				'album_card_radius' => $this->post( 'album_card_radius' ),
				'album_pagination_enabled' => $this->post( 'album_pagination_enabled' ),
				'album_items_per_page' => $this->post( 'album_items_per_page' ),
				'album_show_titles' => $this->post( 'album_show_titles' ),
				'album_show_captions' => $this->post( 'album_show_captions' ),
				'album_show_heading' => $this->post( 'album_show_heading' ),
				'album_show_description' => $this->post( 'album_show_description' ),
				'album_item_title_font_size' => $this->post( 'album_item_title_font_size' ),
				'album_item_title_color' => $this->post( 'album_item_title_color' ),
				'enable_frontend_filters' => $this->post( 'enable_frontend_filters' ),
				'items_per_page'        => $this->post( 'items_per_page' ),
				'pagination_enabled'    => $this->post( 'pagination_enabled' ),
				'show_titles'          => $this->post( 'show_titles' ),
				'show_captions'        => $this->post( 'show_captions' ),
				'show_item_tags'       => $this->post( 'show_item_tags' ),
				'hide_all_titles'      => $this->post( 'hide_all_titles' ),
				'show_gallery_heading' => $this->post( 'show_gallery_heading' ),
				'show_gallery_description' => $this->post( 'show_gallery_description' ),
				'image_quality'         => $this->post( 'image_quality' ),
				'thumb_width'           => $this->post( 'thumb_width' ),
				'thumb_height'          => $this->post( 'thumb_height' ),
				'thumb_crop'            => $this->post( 'thumb_crop' ),
				'medium_width'          => $this->post( 'medium_width' ),
				'medium_height'         => $this->post( 'medium_height' ),
				'large_width'           => $this->post( 'large_width' ),
				'large_height'          => $this->post( 'large_height' ),
				'album_cover_width'     => $this->post( 'album_cover_width' ),
				'album_cover_height'    => $this->post( 'album_cover_height' ),
				'album_cover_fit'       => $this->post( 'album_cover_fit' ),
				'album_cover_lock_ratio' => $this->post( 'album_cover_lock_ratio' ),
				'watermark_enabled'     => $this->post( 'watermark_enabled' ),
				'watermark_text'        => $this->post( 'watermark_text' ),
				'watermark_opacity'     => $this->post( 'watermark_opacity' ),
				'watermark_position'    => $this->post( 'watermark_position' ),
				'rounded_corners'       => $this->post( 'rounded_corners' ),
				'slideshow_show_arrows' => $this->post( 'slideshow_show_arrows' ),
				'slideshow_show_thumbs' => $this->post( 'slideshow_show_thumbs' ),
				'nav_arrow_prev_url'    => $this->post( 'nav_arrow_prev_url' ),
				'nav_arrow_next_url'    => $this->post( 'nav_arrow_next_url' ),
				'heading_font_size'     => $this->post( 'heading_font_size' ),
				'heading_color'         => $this->post( 'heading_color' ),
				'item_title_font_size'  => $this->post( 'item_title_font_size' ),
				'item_title_color'      => $this->post( 'item_title_color' ),
				'enable_lightbox'       => $this->post( 'enable_lightbox' ),
				'enable_lazy_load'      => $this->post( 'enable_lazy_load' ),
				'label_view_gallery'    => $this->post( 'label_view_gallery' ),
				'label_back_to_album'   => $this->post( 'label_back_to_album' ),
				'empty_gallery_message' => $this->post( 'empty_gallery_message' ),
				'empty_album_message'   => $this->post( 'empty_album_message' ),
			]
		);

		$updated = $this->repository->apply_settings_to_all_galleries( $settings );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( [ 'message' => $updated->get_error_message() ], 400 );
		}

		wp_send_json_success(
			[
				'settings'   => $settings,
				'updated'    => (int) $updated,
				'validation' => $this->repository->get_validation_report(),
				'message'    => __( 'Configurações aplicadas a todas as galerias.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Regenerates every local item using the current image settings.
	 *
	 * @return void
	 */
		/**
	 * Resets plugin data to factory defaults.
	 *
	 * @return void
	 */
	public function factory_reset(): void {
		$this->authorize();

		$result = $this->repository->factory_reset();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'message'    => __( 'Plugin resetado com sucesso.', 'ml-gallery-pro' ),
				'settings'   => $this->repository->get_settings(),
				'validation' => $this->repository->get_validation_report(),
				'license'    => $this->license_manager->build_payload(),
			]
		);
	}


	public function regenerate_local_items_batch(): void {
		$this->authorize();

		$offset = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
		$limit  = isset( $_POST['limit'] ) ? max( 1, min( 100, absint( wp_unslash( $_POST['limit'] ) ) ) ) : 20;

		$result = $this->repository->regenerate_local_items_batch( $offset, $limit );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
				],
				422
			);
		}

		wp_send_json_success(
			[
				'message'     => __( 'Lote de regeneracao processado com sucesso.', 'ml-gallery-pro' ),
				'processed'   => (int) ( $result['processed'] ?? 0 ),
				'failed'      => (int) ( $result['failed'] ?? 0 ),
				'total'       => (int) ( $result['total'] ?? 0 ),
				'next_offset' => (int) ( $result['next_offset'] ?? 0 ),
				'done'        => ! empty( $result['done'] ),
				'validation'  => $this->repository->get_validation_report(),
			]
		);
	}


	public function regenerate_all_local_items(): void {
		$this->authorize();

		$result = $this->repository->regenerate_all_local_items();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
				],
				422
			);
		}

		$processed = (int) ( $result['processed'] ?? 0 );
		$failed    = (int) ( $result['failed'] ?? 0 );
		$total     = (int) ( $result['total'] ?? 0 );
		$message   = sprintf(
			/* translators: 1: processed images, 2: total images. */
			__( 'Regeneracao global concluida: %1$d de %2$d imagens locais processadas com sucesso.', 'ml-gallery-pro' ),
			$processed,
			$total
		);

		if ( $failed > 0 ) {
			$message = sprintf(
				/* translators: 1: processed images, 2: total images, 3: failed images. */
				__( 'Regeneracao global concluida com ressalvas: %1$d de %2$d imagens processadas e %3$d falhas.', 'ml-gallery-pro' ),
				$processed,
				$total,
				$failed
			);
		}

		wp_send_json_success(
			[
				'processed'  => $processed,
				'failed'     => $failed,
				'total'      => $total,
				'validation' => $this->repository->get_validation_report(),
				'message'    => $message,
			]
		);
	}

	/**
	 * Basic auth for admin AJAX calls.
	 *
	 * @return void
	 */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Voce nao tem permissao para esta operacao.', 'ml-gallery-pro' ),
				],
				403
			);
		}

		$nonce = $this->post( 'nonce' );

		if ( empty( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'mlgp_admin_nonce' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Falha de seguranca. Recarregue a pagina e tente novamente.', 'ml-gallery-pro' ),
				],
				403
			);
		}
	}

	/**
	 * Reads a POST field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function post( string $key ): string {
		return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
	}

	/**
	 * Resolves and persists one admin collection sort mode.
	 *
	 * @param string $post_key POST field name.
	 * @param string $meta_key User meta key.
	 * @return string
	 */
	private function resolve_collection_sort_mode( string $post_key, string $meta_key ): string {
		$posted = $this->post( $post_key );

		if ( '' !== $posted ) {
			$sort_mode = Repository::normalize_sort_mode( $posted );
			update_user_meta( get_current_user_id(), $meta_key, $sort_mode );

			return $sort_mode;
		}

		$saved = get_user_meta( get_current_user_id(), $meta_key, true );

		return Repository::normalize_sort_mode( is_string( $saved ) ? $saved : '' );
	}

	/**
	 * Decodes a JSON array payload.
	 *
	 * @param string $value Raw JSON string.
	 * @return array<int, mixed>
	 */
	private function decode_json_array( string $value ): array {
		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? array_values( $decoded ) : [];
	}

	/**
	 * Decodes a JSON object payload.
	 *
	 * @param string $value Raw JSON string.
	 * @return array<string, mixed>
	 */
	private function decode_json_object( string $value ): array {
		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : [];
	}
}
