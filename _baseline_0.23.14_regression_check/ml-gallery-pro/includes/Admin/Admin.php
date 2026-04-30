<?php
/**
 * Admin screens and assets.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Admin;

use MLGP\Database\Repository;
use MLGP\License\Manager;
use MLGP\Media\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

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
	 * Registered hooks.
	 *
	 * @var array<int, string>
	 */
	private $hook_suffixes = [];

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
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_init', [ $this, 'init' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_mlgp_save_gallery_form', [ $this, 'handle_gallery_form_submit' ] );
		add_action( 'admin_post_mlgp_save_album_form', [ $this, 'handle_album_form_submit' ] );
		add_action( 'media_buttons', [ $this, 'add_editor_buttons' ] );
	}

	/**
	 * Initialization.
	 *
	 * @return void
	 */
	public function init(): void {
		// Placeholder for initial setup if needed.
	}

	/**
	 * Registers admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$capability = 'manage_options';

		$this->hook_suffixes[] = add_menu_page(
			__( 'ML Gallery Pro', 'ml-gallery-pro' ),
			__( 'ML Gallery Pro', 'ml-gallery-pro' ),
			$capability,
			'mlgp-dashboard',
			[ $this, 'render_page' ],
			'dashicons-format-gallery',
			58
		);

		$this->hook_suffixes[] = add_submenu_page(
			'mlgp-dashboard',
			__( 'Dashboard', 'ml-gallery-pro' ),
			__( 'Dashboard', 'ml-gallery-pro' ),
			$capability,
			'mlgp-dashboard',
			[ $this, 'render_page' ]
		);

		$this->hook_suffixes[] = add_submenu_page(
			'mlgp-dashboard',
			__( 'Galerias', 'ml-gallery-pro' ),
			__( 'Galerias', 'ml-gallery-pro' ),
			$capability,
			'mlgp-galleries',
			[ $this, 'render_page' ]
		);

		$this->hook_suffixes[] = add_submenu_page(
			'mlgp-dashboard',
			__( 'Add Images', 'ml-gallery-pro' ),
			__( 'Add Images', 'ml-gallery-pro' ),
			$capability,
			'mlgp-add-images',
			[ $this, 'render_page' ]
		);

		$this->hook_suffixes[] = add_submenu_page(
			'mlgp-dashboard',
			__( 'Albuns', 'ml-gallery-pro' ),
			__( 'Albuns', 'ml-gallery-pro' ),
			$capability,
			'mlgp-albums',
			[ $this, 'render_page' ]
		);

		$this->hook_suffixes[] = add_submenu_page(
			'mlgp-dashboard',
			__( 'Tags', 'ml-gallery-pro' ),
			__( 'Tags', 'ml-gallery-pro' ),
			$capability,
			'mlgp-tags',
			[ $this, 'render_page' ]
		);

		$this->hook_suffixes[] = add_submenu_page(
			'mlgp-dashboard',
			__( 'Configuracoes', 'ml-gallery-pro' ),
			__( 'Configuracoes', 'ml-gallery-pro' ),
			$capability,
			'mlgp-settings',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @param string $hook_suffix Current hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$is_post_editor = in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true );

		if ( ! $is_post_editor && ! in_array( $hook_suffix, $this->hook_suffixes, true ) ) {
			return;
		}

		$page           = $this->detect_page();
		$active_gallery = in_array( $page, [ 'mlgp-galleries', 'mlgp-add-images' ], true ) ? $this->get_requested_gallery_id() : 0;
		$active_album   = 'mlgp-albums' === $page ? $this->get_requested_album_id() : 0;
		$notice         = $this->get_notice_payload();

		wp_enqueue_style(
			'mlgp-admin',
			MLGP_URL . 'assets/css/admin.css',
			[],
			MLGP_VERSION
		);

		wp_enqueue_script(
			'mlgp-admin',
			MLGP_URL . 'assets/js/admin.js',
			[],
			MLGP_VERSION,
			true
		);

		wp_enqueue_script(
			'mlgp-gallery-editor',
			MLGP_URL . 'assets/js/gallery-editor.js',
			[ 'mlgp-admin' ],
			MLGP_VERSION,
			true
		);

		if ( 'mlgp-settings' === $page ) {
			wp_enqueue_media();
		}

		wp_localize_script(
			'mlgp-admin',
			'MLGPAdmin',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'mlgp_admin_nonce' ),
				'page'      => $page,
				'pluginUrl' => MLGP_URL,
				'siteUrl'   => home_url(),
				'version'   => MLGP_VERSION,
				'sorting'   => [
					'galleries' => Repository::normalize_sort_mode( (string) get_user_meta( get_current_user_id(), 'mlgp_gallery_sort_mode', true ) ),
					'albums'    => Repository::normalize_sort_mode( (string) get_user_meta( get_current_user_id(), 'mlgp_album_sort_mode', true ) ),
				],
				'pageUrls'  => [
					'dashboard' => $this->get_admin_page_url( 'mlgp-dashboard' ),
					'galleries' => $this->get_admin_page_url( 'mlgp-galleries' ),
					'addImages' => $this->get_admin_page_url( 'mlgp-add-images' ),
					'albums'    => $this->get_admin_page_url( 'mlgp-albums' ),
					'tags'      => $this->get_admin_page_url( 'mlgp-tags' ),
					'settings'  => $this->get_admin_page_url( 'mlgp-settings' ),
				],
				'formEndpoints' => [
					'gallerySave' => admin_url( 'admin-post.php' ),
					'albumSave'   => admin_url( 'admin-post.php' ),
				],
				'formNonce'  => wp_create_nonce( 'mlgp_gallery_form_action' ),
				'albumFormNonce' => wp_create_nonce( 'mlgp_album_form_action' ),
				'activeGalleryId' => $active_gallery,
				'activeAlbumId'   => $active_album,
				'isPostEditor'    => $is_post_editor,
				'uploadBatchSize' => $this->get_upload_batch_size(),
				'storageLabel' => wp_normalize_path( str_replace( wp_normalize_path( trailingslashit( ABSPATH ) ), '', wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . Storage::BASE_DIRNAME ) ) ),
				'serverImportRoots' => $this->repository->get_server_import_sources(),
				'notice'     => $notice,
				'settings'  => $this->repository->get_settings(),
				'license'   => $this->license_manager->build_payload(),
				'strings'   => [
					'confirmDeleteGallery' => __( 'Deseja realmente excluir esta galeria?', 'ml-gallery-pro' ),
					'confirmDeleteAlbum'   => __( 'Deseja realmente excluir este album?', 'ml-gallery-pro' ),
					'confirmDeleteSelectedGalleries' => __( 'Deseja realmente excluir as galerias selecionadas?', 'ml-gallery-pro' ),
					'confirmDeleteAllGalleries' => __( 'Deseja excluir todas as galerias? Esta acao tambem exclui todas as imagens vinculadas e nao pode ser desfeita.', 'ml-gallery-pro' ),
					'confirmDeleteAllImages' => __( 'Deseja excluir todas as imagens do plugin e manter apenas a estrutura das galerias? Esta acao nao pode ser desfeita.', 'ml-gallery-pro' ),
					'confirmDeleteSelectedAlbums' => __( 'Deseja realmente excluir os albuns selecionados?', 'ml-gallery-pro' ),
					'confirmFactoryReset' => __( 'Deseja resetar o plugin para o estado de fabrica? Esta acao exclui galerias, imagens, albuns, tags, configuracoes e armazenamento local do plugin. Esta operacao nao pode ser desfeita.', 'ml-gallery-pro' ),
					'confirmDiscardChanges'=> __( 'Existem alteracoes nao salvas na galeria. Deseja continuar mesmo assim?', 'ml-gallery-pro' ),
					'saveSuccess'          => __( 'Registro salvo com sucesso.', 'ml-gallery-pro' ),
					'deleteSuccess'        => __( 'Registro excluido com sucesso.', 'ml-gallery-pro' ),
					'galleryItemsSaved'    => __( 'Itens da galeria salvos com sucesso.', 'ml-gallery-pro' ),
					'galleryImagesAdded'   => __( 'Imagens enviadas com sucesso.', 'ml-gallery-pro' ),
					'galleryOpenUpload'    => __( 'Galeria salva. Agora envie as imagens para o diretorio proprio do plugin.', 'ml-gallery-pro' ),
					'selectFilesFirst'     => __( 'Selecione ao menos uma imagem valida para enviar.', 'ml-gallery-pro' ),
					'uploadingImages'      => __( 'Enviando imagens...', 'ml-gallery-pro' ),
					'creatingGallery'      => __( 'Criando galeria...', 'ml-gallery-pro' ),
					'creatingGalleryUpload'=> __( 'Criando galeria e enviando imagens...', 'ml-gallery-pro' ),
					'creatingGalleryZip'   => __( 'Criando galeria e importando ZIP...', 'ml-gallery-pro' ),
					'importingZip'         => __( 'Importando ZIP...', 'ml-gallery-pro' ),
					'uploadStorageLabel'   => __( 'As imagens desta galeria ficam em armazenamento proprio do plugin.', 'ml-gallery-pro' ),
					'selectGalleryFirst'   => __( 'Salve ou selecione uma galeria antes de gerenciar imagens.', 'ml-gallery-pro' ),
					'selectItemsFirst'     => __( 'Selecione pelo menos uma imagem da galeria para continuar.', 'ml-gallery-pro' ),
					'selectZipFirst'       => __( 'Selecione um arquivo ZIP valido para importar.', 'ml-gallery-pro' ),
					'searchGalleriesPlaceholder' => __( 'Buscar galerias...', 'ml-gallery-pro' ),
					'galleryCreated'       => __( 'Galeria criada com sucesso.', 'ml-gallery-pro' ),
					'galleryCreatedWithImages' => __( 'Galeria criada e imagens enviadas com sucesso.', 'ml-gallery-pro' ),
					'galleryCreatedWithZip' => __( 'Galeria criada e ZIP importado com sucesso.', 'ml-gallery-pro' ),
					'editorChangesSaved'   => __( 'Alteracoes da galeria salvas com sucesso.', 'ml-gallery-pro' ),
					'copyShortcode'        => __( 'Copiar shortcode', 'ml-gallery-pro' ),
					'shortcodeCopied'      => __( 'Shortcode copiado.', 'ml-gallery-pro' ),
					'newGalleryTitle'      => __( 'Adicionar nova galeria', 'ml-gallery-pro' ),
					'newGalleryDescription'=> __( 'Crie a galeria e envie imagens do computador, de uma pasta local ou de um ZIP no mesmo fluxo, com armazenamento proprio do plugin e shortcode automatico.', 'ml-gallery-pro' ),
					'createGalleryAction'  => __( 'Criar galeria', 'ml-gallery-pro' ),
					'createGalleryAndUploadAction' => __( 'Criar galeria e enviar imagens', 'ml-gallery-pro' ),
					'createGalleryAndImportZipAction' => __( 'Criar galeria e importar ZIP', 'ml-gallery-pro' ),
					'createEmptyGalleryAction' => __( 'Criar galeria vazia', 'ml-gallery-pro' ),
					'computerUploadAction' => __( 'Computador', 'ml-gallery-pro' ),
					'computerUploadHint'   => __( 'Selecionar imagens avulsas', 'ml-gallery-pro' ),
					'importFolderAction'   => __( 'Importar pasta', 'ml-gallery-pro' ),
					'importFolderHint'     => __( 'Ler imagens de uma pasta local', 'ml-gallery-pro' ),
					'importZipAction'      => __( 'Importar ZIP', 'ml-gallery-pro' ),
					'importZipHint'        => __( 'Criar a galeria a partir de um arquivo ZIP', 'ml-gallery-pro' ),
					'serverImportAction'   => __( 'Pasta do servidor', 'ml-gallery-pro' ),
					'serverImportHint'     => __( 'Importar imagens de uma pasta ja existente no servidor', 'ml-gallery-pro' ),
					'serverImportRootLabel'=> __( 'Raiz autorizada', 'ml-gallery-pro' ),
					'serverImportPathLabel'=> __( 'Pasta relativa', 'ml-gallery-pro' ),
					'serverImportPathHint' => __( 'Informe a pasta relativa dentro da raiz selecionada.', 'ml-gallery-pro' ),
					'serverImportPathPlaceholder' => __( 'ex: clientes/evento-a', 'ml-gallery-pro' ),
					'serverImportButton'   => __( 'Importar pasta do servidor', 'ml-gallery-pro' ),
					'createGalleryAndImportServerAction' => __( 'Criar galeria e importar pasta do servidor', 'ml-gallery-pro' ),
					'creatingGalleryServer'=> __( 'Criando galeria e importando pasta do servidor...', 'ml-gallery-pro' ),
					'galleryCreatedWithServer' => __( 'Galeria criada e pasta do servidor importada com sucesso.', 'ml-gallery-pro' ),
					'galleryServerImported'=> __( 'Pasta do servidor importada com sucesso.', 'ml-gallery-pro' ),
					'selectServerPathFirst'=> __( 'Informe a pasta relativa do servidor antes de continuar.', 'ml-gallery-pro' ),
					'dropFilesHint'        => __( 'Arraste imagens ou clique para selecionar', 'ml-gallery-pro' ),
					'dropFolderHint'       => __( 'Selecione uma pasta local com imagens', 'ml-gallery-pro' ),
					'dropZipHint'          => __( 'Arraste um arquivo ZIP ou clique para selecionar', 'ml-gallery-pro' ),
					'comingSoon'           => __( 'Em breve', 'ml-gallery-pro' ),
					'imagesSelected'       => __( 'arquivo(s) selecionado(s)', 'ml-gallery-pro' ),
					'noGalleryFound'       => __( 'Nenhuma galeria encontrada para a busca aplicada.', 'ml-gallery-pro' ),
					'galleryManagerTitle'  => __( 'Gerenciador de galerias', 'ml-gallery-pro' ),
					'galleryManagerDescription' => __( 'Crie, localize e gerencie galerias com shortcode pronto, capa, upload por arquivo, pasta local ou ZIP direto no diretorio proprio do plugin.', 'ml-gallery-pro' ),
					'addImagesPageTitle'   => __( 'Adicionar imagens e gerar galeria', 'ml-gallery-pro' ),
					'addImagesPageDescription' => __( 'Fluxo direto para criar a galeria, subir imagens do computador, pasta ou ZIP e sair com shortcode pronto no mesmo passo.', 'ml-gallery-pro' ),
					'addImagesPrimaryAction' => __( 'Criar galeria com imagens', 'ml-gallery-pro' ),
					'openGalleryManagerAction' => __( 'Abrir manager da galeria', 'ml-gallery-pro' ),
					'backToGalleriesAction' => __( 'Voltar para galerias', 'ml-gallery-pro' ),
					'gallerySettingsSaved' => __( 'Dados da galeria salvos com sucesso.', 'ml-gallery-pro' ),
					'galleryZipImported'   => __( 'ZIP importado com sucesso.', 'ml-gallery-pro' ),
					'bulkSelectAll'        => __( 'Selecionar tudo', 'ml-gallery-pro' ),
					'bulkClearSelection'   => __( 'Limpar selecao', 'ml-gallery-pro' ),
					'bulkShowSelected'     => __( 'Exibir', 'ml-gallery-pro' ),
					'bulkHideSelected'     => __( 'Ocultar', 'ml-gallery-pro' ),
					'bulkDeleteSelected'   => __( 'Excluir', 'ml-gallery-pro' ),
					'bulkAppendTags'       => __( 'Adicionar tags', 'ml-gallery-pro' ),
					'bulkReplaceTags'      => __( 'Substituir tags', 'ml-gallery-pro' ),
					'bulkClearTags'        => __( 'Limpar tags', 'ml-gallery-pro' ),
					'bulkRegenerate'       => __( 'Regenerar previews', 'ml-gallery-pro' ),
					'bulkRotateLeft'       => __( 'Rotacionar 90 esquerda', 'ml-gallery-pro' ),
					'bulkRotateRight'      => __( 'Rotacionar 90 direita', 'ml-gallery-pro' ),
					'bulkTagsPlaceholder'  => __( 'evento, capa, destaque', 'ml-gallery-pro' ),
					'bulkTagsRequired'     => __( 'Informe ao menos uma tag para aplicar em lote.', 'ml-gallery-pro' ),
					'bulkActionSuccess'    => __( 'Acao em massa aplicada com sucesso.', 'ml-gallery-pro' ),
					'confirmDeleteSelectedItems' => __( 'Deseja excluir as imagens selecionadas?', 'ml-gallery-pro' ),
					'confirmRegenerateSelectedItems' => __( 'Deseja regenerar as previews das imagens selecionadas?', 'ml-gallery-pro' ),
					'confirmRotateLeftSelectedItems' => __( 'Deseja rotacionar 90 graus para a esquerda as imagens selecionadas?', 'ml-gallery-pro' ),
					'confirmRotateRightSelectedItems' => __( 'Deseja rotacionar 90 graus para a direita as imagens selecionadas?', 'ml-gallery-pro' ),
					'globalRegenerateAction' => __( 'Regenerar toda a biblioteca local', 'ml-gallery-pro' ),
					'globalRegenerateRunning' => __( 'Regenerando toda a biblioteca local...', 'ml-gallery-pro' ),
					'confirmGlobalRegenerate' => __( 'Deseja regenerar todas as previews locais com os perfis e watermark atuais? Essa operacao pode levar alguns minutos.', 'ml-gallery-pro' ),
					'genericError'         => __( 'Nao foi possivel concluir a operacao.', 'ml-gallery-pro' ),
					'licenseTitle'         => __( 'Licenca / Serial', 'ml-gallery-pro' ),
					'licensePlan'          => __( 'Plano', 'ml-gallery-pro' ),
					'licenseStatus'        => __( 'Status', 'ml-gallery-pro' ),
					'licenseValidate'      => __( 'Validar serial', 'ml-gallery-pro' ),
					'licenseDeactivate'    => __( 'Remover licenca', 'ml-gallery-pro' ),
					'licenseSuccess'       => __( 'Licenca atualizada.', 'ml-gallery-pro' ),
					'licenseStartTrial'    => __( 'Iniciar trial gratis', 'ml-gallery-pro' ),
					'licenseStartTrialHint'=> __( 'Sem serial informado, o botao inicia o trial desta instalacao.', 'ml-gallery-pro' ),
					'licenseProcessing'    => __( 'Validando...', 'ml-gallery-pro' ),
					'licenseRemoving'      => __( 'Removendo...', 'ml-gallery-pro' ),
					'licenseError'         => __( 'Nao foi possivel validar o serial.', 'ml-gallery-pro' ),
					'licensePlaceholder'   => __( 'MLG-XXXXX-XXXXX-XXXXX', 'ml-gallery-pro' ),
					'bulkDeleteSelectedGalleries' => __( 'Excluir galerias selecionadas', 'ml-gallery-pro' ),
					'deleteAllGalleriesAction' => __( 'Excluir todas as galerias', 'ml-gallery-pro' ),
					'deleteAllImagesAction' => __( 'Excluir todas as imagens', 'ml-gallery-pro' ),
					'bulkDeleteSelectedAlbums' => __( 'Excluir albuns selecionados', 'ml-gallery-pro' ),
					'factoryResetAction'  => __( 'Resetar plugin para o estado de fabrica', 'ml-gallery-pro' ),
					'factoryResetRunning' => __( 'Resetando plugin...', 'ml-gallery-pro' ),
					'factoryResetSuccess' => __( 'Plugin resetado com sucesso.', 'ml-gallery-pro' ),
				],
				'licenseNonce' => wp_create_nonce( 'mlgp_license_action_nonce' ),
			]
		);
	}

	/**
	 * Renders admin page shell.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Voce nao tem permissao para acessar esta pagina.', 'ml-gallery-pro' ) );
		}

		$current_page = $this->detect_page();
		$page_meta    = $this->get_page_meta( $current_page );
		$tabs         = $this->get_shell_tabs( $current_page );
		?>
		<div class="wrap mlgp-shell">
			<div class="mlgp-shell__hero">
				<div class="mlgp-shell__brand">
					<div class="mlgp-shell__mark">
						<img src="<?php echo esc_url( MLGP_URL . 'assets/images/logo-wordpress.png?ver=' . rawurlencode( MLGP_VERSION ) ); ?>" alt="<?php esc_attr_e( 'ML Gallery Pro', 'ml-gallery-pro' ); ?>">
					</div>
					<div class="mlgp-shell__copy">
						<span class="mlgp-shell__eyebrow">ML Gallery Pro</span>
						<h1><?php echo esc_html( $page_meta['title'] ); ?></h1>
						<p><?php echo esc_html( $page_meta['description'] ); ?></p>
					</div>
				</div>
				<div class="mlgp-shell__meta">
					<span class="mlgp-shell__version"><?php echo esc_html( 'v' . MLGP_VERSION ); ?></span>
					<div class="mlgp-shell__tags">
						<span class="mlgp-shell__tag"><?php esc_html_e( 'Painel Comercial', 'ml-gallery-pro' ); ?></span>
						<span class="mlgp-shell__tag"><?php esc_html_e( 'Storage Proprio', 'ml-gallery-pro' ); ?></span>
						<span class="mlgp-shell__tag"><?php esc_html_e( 'Shortcodes Nativos', 'ml-gallery-pro' ); ?></span>
					</div>
				</div>
			</div>

			<nav class="mlgp-shell__tabs" aria-label="<?php esc_attr_e( 'Navegacao do plugin', 'ml-gallery-pro' ); ?>">
				<?php foreach ( $tabs as $tab ) : ?>
					<a class="mlgp-shell__tab <?php echo ! empty( $tab['is_active'] ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab['url'] ); ?>">
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="mlgp-shell__content">
				<div id="mlgp-admin-app" data-page="<?php echo esc_attr( $page_meta['page'] ); ?>"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Adds "Add Gallery" and "Add Album" buttons to the editor toolbar.
	 *
	 * @return void
	 */
	public function add_editor_buttons(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->base, [ 'post', 'page' ], true ) ) {
			// Also allow if it's a custom post type that supports editor.
			if ( ! post_type_supports( get_post_type(), 'editor' ) ) {
				return;
			}
		}

		printf(
			'<button type="button" class="button mlgp-editor-button" data-mlgp-trigger-picker="gallery" title="%1$s"><span class="wp-media-buttons-icon dashicons dashicons-lightbulb"></span> %2$s</button>',
			esc_attr__( 'Add ML Gallery', 'ml-gallery-pro' ),
			esc_html__( 'Add Gallery', 'ml-gallery-pro' )
		);

		printf(
			'<button type="button" class="button mlgp-editor-button" data-mlgp-trigger-picker="album" title="%1$s"><span class="wp-media-buttons-icon dashicons dashicons-lightbulb"></span> %2$s</button>',
			esc_attr__( 'Add ML Album', 'ml-gallery-pro' ),
			esc_html__( 'Add Album', 'ml-gallery-pro' )
		);
	}

	/**
	 * Handles gallery form fallback submission.
	 *
	 * @return void
	 */
	public function handle_gallery_form_submit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Voce nao tem permissao para acessar esta pagina.', 'ml-gallery-pro' ) );
		}

		$nonce = isset( $_POST['mlgp_gallery_form_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mlgp_gallery_form_nonce'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'mlgp_gallery_form_action' ) ) {
			$this->redirect_to_galleries(
				[
					'mlgp_notice'  => 'error',
					'mlgp_message' => __( 'Falha de seguranca no cadastro da galeria. Recarregue a tela e tente novamente.', 'ml-gallery-pro' ),
				]
			);
		}

		$gallery = $this->repository->save_gallery(
			[
				'id'          => isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : '',
				'title'       => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
				'slug'        => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'status'      => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'draft',
			]
		);

		if ( is_wp_error( $gallery ) ) {
			$this->redirect_to_galleries(
				[
					'mlgp_notice'  => 'error',
					'mlgp_message' => $gallery->get_error_message(),
				]
			);
		}

		$this->redirect_to_galleries(
			[
				'gallery_id'   => (int) $gallery['id'],
				'mlgp_notice'  => 'success',
				'mlgp_message' => __( 'Galeria salva com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Handles album form fallback submission.
	 *
	 * @return void
	 */
	public function handle_album_form_submit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Voce nao tem permissao para acessar esta pagina.', 'ml-gallery-pro' ) );
		}

		$nonce = isset( $_POST['mlgp_album_form_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mlgp_album_form_nonce'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'mlgp_album_form_action' ) ) {
			$this->redirect_to_albums(
				[
					'mlgp_notice'  => 'error',
					'mlgp_message' => __( 'Falha de seguranca no cadastro do album. Recarregue a tela e tente novamente.', 'ml-gallery-pro' ),
				]
			);
		}

		$album = $this->repository->save_album(
			[
				'id'          => isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : '',
				'title'       => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
				'slug'        => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'status'      => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'draft',
			]
		);

		if ( is_wp_error( $album ) ) {
			$this->redirect_to_albums(
				[
					'mlgp_notice'  => 'error',
					'mlgp_message' => $album->get_error_message(),
				]
			);
		}

		$this->redirect_to_albums(
			[
				'album_id'     => (int) $album['id'],
				'mlgp_notice'  => 'success',
				'mlgp_message' => __( 'Album salvo com sucesso.', 'ml-gallery-pro' ),
			]
		);
	}

	/**
	 * Detects current plugin page.
	 *
	 * @return string
	 */
	private function detect_page(): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'mlgp-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $page, [ 'mlgp-dashboard', 'mlgp-galleries', 'mlgp-add-images', 'mlgp-albums', 'mlgp-tags', 'mlgp-settings' ], true ) ? $page : 'mlgp-dashboard';
	}

	/**
	 * Returns a plugin admin page URL.
	 *
	 * @param string $page_slug Page slug.
	 * @return string
	 */
	private function get_admin_page_url( string $page_slug ): string {
		return admin_url( 'admin.php?page=' . sanitize_key( $page_slug ) );
	}

	/**
	 * Returns the requested gallery ID when present.
	 *
	 * @return int
	 */
	private function get_requested_gallery_id(): int {
		$gallery_id = isset( $_GET['gallery_id'] ) ? absint( wp_unslash( $_GET['gallery_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $gallery_id > 0 ) {
			return $gallery_id;
		}

		return isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns the requested album ID when present.
	 *
	 * @return int
	 */
	private function get_requested_album_id(): int {
		$album_id = isset( $_GET['album_id'] ) ? absint( wp_unslash( $_GET['album_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $album_id > 0 ) {
			return $album_id;
		}

		return isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns a sanitized notice payload from the request.
	 *
	 * @return array<string, string>
	 */
	private function get_notice_payload(): array {
		$type    = isset( $_GET['mlgp_notice'] ) ? sanitize_key( wp_unslash( $_GET['mlgp_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = isset( $_GET['mlgp_message'] ) ? sanitize_text_field( wp_unslash( $_GET['mlgp_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $message || ! in_array( $type, [ 'success', 'error' ], true ) ) {
			return [];
		}

		return [
			'type'    => $type,
			'message' => $message,
		];
	}

	/**
	 * Returns a safe internal batch size for large computer uploads.
	 *
	 * This is not a user-facing gallery limit. It only slices one large upload
	 * into several requests so the plugin can handle galleries with hundreds or
	 * thousands of images without hitting PHP request caps such as max_file_uploads.
	 *
	 * @return int
	 */
	private function get_upload_batch_size(): int {
		$max_file_uploads = (int) ini_get( 'max_file_uploads' );

		if ( $max_file_uploads <= 0 ) {
			$max_file_uploads = 20;
		}

		if ( $max_file_uploads > 3 ) {
			$max_file_uploads -= 1;
		}

		return max( 1, min( 50, $max_file_uploads ) );
	}

	/**
	 * Redirects back to the galleries page with query arguments.
	 *
	 * @param array<string, string|int> $args Redirect query arguments.
	 * @return void
	 */
	private function redirect_to_galleries( array $args = [] ): void {
		$url = add_query_arg( $args, $this->get_admin_page_url( 'mlgp-galleries' ) );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirects back to the albums page with query arguments.
	 *
	 * @param array<string, string|int> $args Redirect query arguments.
	 * @return void
	 */
	private function redirect_to_albums( array $args = [] ): void {
		$url = add_query_arg( $args, $this->get_admin_page_url( 'mlgp-albums' ) );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Returns human page data.
	 *
	 * @param string $page Page slug.
	 * @return array<string, string>
	 */
	private function get_page_meta( string $page ): array {
		$pages = [
			'mlgp-dashboard' => [
				'page'        => 'dashboard',
				'title'       => __( 'Dashboard', 'ml-gallery-pro' ),
				'description' => __( 'Visao geral do produto com atalhos, status da base instalada, galerias recentes e shortcodes prontos para uso.', 'ml-gallery-pro' ),
			],
			'mlgp-galleries' => [
				'page'        => 'galleries',
				'title'       => __( 'Gerenciar galerias', 'ml-gallery-pro' ),
				'description' => __( 'Crie galerias com upload imediato de imagens, capa, ordenacao e shortcode automatico, tudo em storage proprio do plugin.', 'ml-gallery-pro' ),
			],
			'mlgp-add-images' => [
				'page'        => 'add-images',
				'title'       => __( 'Add Images', 'ml-gallery-pro' ),
				'description' => __( 'Crie a galeria e envie as imagens no mesmo fluxo, com storage proprio do plugin e shortcode nativo pronto para uso.', 'ml-gallery-pro' ),
			],
			'mlgp-albums' => [
				'page'        => 'albums',
				'title'       => __( 'Gerenciar albuns', 'ml-gallery-pro' ),
				'description' => __( 'Monte albuns com galerias e subalbuns, controle a ordem dos itens e publique a colecao via shortcode do album.', 'ml-gallery-pro' ),
			],
			'mlgp-tags' => [
				'page'        => 'tags',
				'title'       => __( 'Gerenciar tags', 'ml-gallery-pro' ),
				'description' => __( 'Acompanhe as tags aplicadas nas imagens, reuse shortcodes globais por tag e organize filtros por assunto em todas as galerias.', 'ml-gallery-pro' ),
			],
			'mlgp-settings' => [
				'page'        => 'settings',
				'title'       => __( 'Configuracoes globais', 'ml-gallery-pro' ),
				'description' => __( 'Defina grid, perfis de imagem, watermark, lightbox, lazy load e ferramentas de regeneracao reutilizadas em toda a biblioteca.', 'ml-gallery-pro' ),
			],
		];

		return $pages[ $page ] ?? $pages['mlgp-dashboard'];
	}

	/**
	 * Returns the shell tabs for all plugin dashboards.
	 *
	 * @param string $current_page Current page slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_shell_tabs( string $current_page ): array {
		$items = [
			[
				'label' => __( 'Dashboard', 'ml-gallery-pro' ),
				'page'  => 'mlgp-dashboard',
			],
			[
				'label' => __( 'Galerias', 'ml-gallery-pro' ),
				'page'  => 'mlgp-galleries',
			],
			[
				'label' => __( 'Add Images', 'ml-gallery-pro' ),
				'page'  => 'mlgp-add-images',
			],
			[
				'label' => __( 'Albuns', 'ml-gallery-pro' ),
				'page'  => 'mlgp-albums',
			],
			[
				'label' => __( 'Tags', 'ml-gallery-pro' ),
				'page'  => 'mlgp-tags',
			],
			[
				'label' => __( 'Configuracoes', 'ml-gallery-pro' ),
				'page'  => 'mlgp-settings',
			],
		];

		foreach ( $items as &$item ) {
			$item['url']       = $this->get_admin_page_url( $item['page'] );
			$item['is_active'] = $current_page === $item['page'];
		}
		unset( $item );

		return $items;
	}
}
