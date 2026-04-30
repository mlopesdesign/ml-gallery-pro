<?php
/**
 * Gutenberg block integration.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Blocks;

use MLGP\Database\Repository;
use MLGP\Frontend\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GalleryBlock {

	/**
	 * Repository instance.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Shortcode renderer instance.
	 *
	 * @var Shortcodes
	 */
	private $shortcodes;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Shared repository.
	 * @param Shortcodes $shortcodes Shared shortcode renderer.
	 */
	public function __construct( Repository $repository, Shortcodes $shortcodes ) {
		$this->repository = $repository;
		$this->shortcodes = $shortcodes;
	}

	/**
	 * Registers block hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Registers the dynamic block and shared editor assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! wp_style_is( 'mlgp-frontend', 'registered' ) ) {
			wp_register_style(
				'mlgp-frontend',
				MLGP_URL . 'assets/css/frontend.css',
				[],
				MLGP_VERSION
			);
		}

		if ( ! wp_script_is( 'mlgp-frontend', 'registered' ) ) {
			wp_register_script(
				'mlgp-frontend',
				MLGP_URL . 'assets/js/frontend.js',
				[],
				MLGP_VERSION,
				true
			);
		}

		wp_register_script(
			'mlgp-block-editor',
			MLGP_URL . 'assets/js/block-editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ],
			MLGP_VERSION,
			true
		);

		wp_register_style(
			'mlgp-block-editor',
			MLGP_URL . 'assets/css/block-editor.css',
			[ 'wp-edit-blocks' ],
			MLGP_VERSION
		);

		register_block_type(
			'ml-gallery-pro/gallery',
			[
				'api_version'     => 2,
				'editor_script'   => 'mlgp-block-editor',
				'editor_style'    => 'mlgp-block-editor',
				'render_callback' => [ $this, 'render_block' ],
				'attributes'      => $this->get_attributes_schema(),
			]
		);
	}

	/**
	 * Enqueues preview assets and localizes editor data.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		if ( ! wp_script_is( 'mlgp-block-editor', 'registered' ) ) {
			return;
		}

		wp_enqueue_style( 'mlgp-frontend' );
		wp_enqueue_script( 'mlgp-frontend' );
		wp_enqueue_script( 'mlgp-block-editor' );
		wp_enqueue_style( 'mlgp-block-editor' );

		wp_localize_script(
			'mlgp-block-editor',
			'MLGPBlockEditor',
			[
				'galleries'          => $this->get_gallery_options(),
				'albums'             => $this->get_album_options(),
				'tags'               => $this->get_tag_options(),
				'sourceTypes'        => [
					[ 'value' => 'gallery', 'label' => __( 'Galeria', 'ml-gallery-pro' ) ],
					[ 'value' => 'album', 'label' => __( 'Album', 'ml-gallery-pro' ) ],
					[ 'value' => 'tag', 'label' => __( 'Tag', 'ml-gallery-pro' ) ],
				],
				'galleryDisplayTypes' => [
					[ 'value' => 'grid', 'label' => __( 'Grid', 'ml-gallery-pro' ) ],
					[ 'value' => 'tile', 'label' => __( 'Tile', 'ml-gallery-pro' ) ],
					[ 'value' => 'mosaic', 'label' => __( 'Mosaic', 'ml-gallery-pro' ) ],
					[ 'value' => 'masonry', 'label' => __( 'Masonry', 'ml-gallery-pro' ) ],
					[ 'value' => 'justified', 'label' => __( 'Justified', 'ml-gallery-pro' ) ],
					[ 'value' => 'slideshow', 'label' => __( 'Slideshow', 'ml-gallery-pro' ) ],
					[ 'value' => 'filmstrip', 'label' => __( 'Filmstrip', 'ml-gallery-pro' ) ],
					[ 'value' => 'imagebrowser', 'label' => __( 'Image Browser', 'ml-gallery-pro' ) ],
				],
				'albumDisplayTypes'  => [
					[ 'value' => 'compact', 'label' => __( 'Compacto', 'ml-gallery-pro' ) ],
					[ 'value' => 'extended', 'label' => __( 'Estendido', 'ml-gallery-pro' ) ],
					[ 'value' => 'grid_plus', 'label' => __( 'Grid Plus', 'ml-gallery-pro' ) ],
				],
				'strings'            => [
					'blockTitle'            => __( 'ML Gallery Pro', 'ml-gallery-pro' ),
					'blockDescription'      => __( 'Insira galerias, albuns e galerias por tag com preview nativo no editor.', 'ml-gallery-pro' ),
					'placeholderTitle'      => __( 'ML Gallery Pro', 'ml-gallery-pro' ),
					'placeholderDescription'=> __( 'Escolha uma galeria, um album ou uma tag para inserir a exibicao sem shortcode manual.', 'ml-gallery-pro' ),
					'previewLabel'          => __( 'Preview do bloco', 'ml-gallery-pro' ),
					'noGalleries'           => __( 'Nenhuma galeria encontrada.', 'ml-gallery-pro' ),
					'noAlbums'              => __( 'Nenhum album encontrado.', 'ml-gallery-pro' ),
					'noTags'                => __( 'Nenhuma tag encontrada.', 'ml-gallery-pro' ),
					'chooseEntity'          => __( 'Selecione um item para gerar a preview.', 'ml-gallery-pro' ),
					'optionalControl'       => __( 'Opcional. Se vazio, o bloco usa a configuracao da galeria ou o padrao global do plugin.', 'ml-gallery-pro' ),
					'defaultOption'         => __( 'Usar padrao da galeria/plugin', 'ml-gallery-pro' ),
					'enabledOption'         => __( 'Ligado', 'ml-gallery-pro' ),
					'disabledOption'        => __( 'Desligado', 'ml-gallery-pro' ),
					'clearColor'            => __( 'Usar cor padrao', 'ml-gallery-pro' ),
					'clearNumber'           => __( 'Usar valor padrao', 'ml-gallery-pro' ),
					'sourcePanel'           => __( 'Origem', 'ml-gallery-pro' ),
					'layoutPanel'           => __( 'Layout', 'ml-gallery-pro' ),
					'contentPanel'          => __( 'Conteudo', 'ml-gallery-pro' ),
					'navigationPanel'       => __( 'Navegacao', 'ml-gallery-pro' ),
					'typographyPanel'       => __( 'Tipografia', 'ml-gallery-pro' ),
				],
			]
		);
	}

	/**
	 * Renders the dynamic block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_block( array $attributes ): string {
		$atts = $this->map_block_attributes_to_shortcode( $attributes );

		if ( empty( $atts ) ) {
			return '';
		}

		return $this->shortcodes->render( $atts, null, 'ml_gallery' );
	}

	/**
	 * Returns the registered block attributes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_attributes_schema(): array {
		return [
			'sourceType'         => [ 'type' => 'string', 'default' => 'gallery' ],
			'entityId'           => [ 'type' => 'number', 'default' => 0 ],
			'tag'                => [ 'type' => 'string', 'default' => '' ],
			'displayType'        => [ 'type' => 'string', 'default' => '' ],
			'albumDisplayType'   => [ 'type' => 'string', 'default' => '' ],
			'columnsDesktop'     => [ 'type' => 'string', 'default' => '' ],
			'columnsTablet'      => [ 'type' => 'string', 'default' => '' ],
			'columnsMobile'      => [ 'type' => 'string', 'default' => '' ],
			'gap'                => [ 'type' => 'string', 'default' => '' ],
			'rowHeight'          => [ 'type' => 'string', 'default' => '' ],
			'roundedCorners'     => [ 'type' => 'string', 'default' => '' ],
			'pagination'         => [ 'type' => 'string', 'default' => '' ],
			'perPage'            => [ 'type' => 'string', 'default' => '' ],
			'showTitles'         => [ 'type' => 'string', 'default' => '' ],
			'showCaptions'       => [ 'type' => 'string', 'default' => '' ],
			'autoplay'           => [ 'type' => 'string', 'default' => '' ],
			'interval'           => [ 'type' => 'string', 'default' => '' ],
			'showArrows'         => [ 'type' => 'string', 'default' => '' ],
			'showThumbs'         => [ 'type' => 'string', 'default' => '' ],
			'headingFontSize'    => [ 'type' => 'string', 'default' => '' ],
			'headingColor'       => [ 'type' => 'string', 'default' => '' ],
			'itemTitleFontSize'  => [ 'type' => 'string', 'default' => '' ],
			'itemTitleColor'     => [ 'type' => 'string', 'default' => '' ],
		];
	}

	/**
	 * Converts block attributes into shortcode attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private function map_block_attributes_to_shortcode( array $attributes ): array {
		$source_type = sanitize_key( (string) ( $attributes['sourceType'] ?? 'gallery' ) );
		$entity_id   = absint( $attributes['entityId'] ?? 0 );
		$tag         = sanitize_title( (string) ( $attributes['tag'] ?? '' ) );

		if ( 'tag' === $source_type && '' === $tag ) {
			return [];
		}

		if ( in_array( $source_type, [ 'gallery', 'album' ], true ) && $entity_id <= 0 ) {
			return [];
		}

		$atts = [];

		if ( 'album' === $source_type ) {
			$atts['type']  = 'album';
			$atts['album'] = $entity_id;
			$atts['id']    = $entity_id;
			$display_type  = $this->sanitize_choice( (string) ( $attributes['albumDisplayType'] ?? '' ), [ 'compact', 'extended', 'grid_plus' ] );

			if ( '' !== $display_type ) {
				$atts['display_type'] = $display_type;
			}
		} elseif ( 'tag' === $source_type ) {
			$atts['type'] = 'tag';
			$atts['tag']  = $tag;
		} else {
			$atts['type'] = 'gallery';
			$atts['id']   = $entity_id;
		}

		$gallery_display_type = $this->sanitize_choice(
			(string) ( $attributes['displayType'] ?? '' ),
			[ 'grid', 'tile', 'mosaic', 'masonry', 'justified', 'slideshow', 'filmstrip', 'imagebrowser' ]
		);

		if ( '' !== $gallery_display_type && 'album' !== $source_type ) {
			$atts['display_type'] = $gallery_display_type;
		}

		$mapped = [
			'columns_desktop'      => $this->sanitize_optional_integer( $attributes['columnsDesktop'] ?? '', 1, 8 ),
			'columns_tablet'       => $this->sanitize_optional_integer( $attributes['columnsTablet'] ?? '', 1, 6 ),
			'columns_mobile'       => $this->sanitize_optional_integer( $attributes['columnsMobile'] ?? '', 1, 4 ),
			'gap'                  => $this->sanitize_optional_integer( $attributes['gap'] ?? '', 0, 60 ),
			'row_height'           => $this->sanitize_optional_integer( $attributes['rowHeight'] ?? '', 120, 520 ),
			'pagination'           => $this->sanitize_optional_toggle( $attributes['pagination'] ?? '' ),
			'per_page'             => $this->sanitize_optional_integer( $attributes['perPage'] ?? '', 1, 5000 ),
			'show_titles'          => $this->sanitize_optional_toggle( $attributes['showTitles'] ?? '' ),
			'show_captions'        => $this->sanitize_optional_toggle( $attributes['showCaptions'] ?? '' ),
			'rounded_corners'      => $this->sanitize_optional_toggle( $attributes['roundedCorners'] ?? '' ),
			'autoplay'             => $this->sanitize_optional_toggle( $attributes['autoplay'] ?? '' ),
			'interval'             => $this->sanitize_optional_integer( $attributes['interval'] ?? '', 1500, 20000 ),
			'show_arrows'          => $this->sanitize_optional_toggle( $attributes['showArrows'] ?? '' ),
			'show_thumbs'          => $this->sanitize_optional_toggle( $attributes['showThumbs'] ?? '' ),
			'heading_font_size'    => $this->sanitize_optional_integer( $attributes['headingFontSize'] ?? '', 20, 96 ),
			'heading_color'        => $this->sanitize_optional_color( $attributes['headingColor'] ?? '' ),
			'item_title_font_size' => $this->sanitize_optional_integer( $attributes['itemTitleFontSize'] ?? '', 10, 72 ),
			'item_title_color'     => $this->sanitize_optional_color( $attributes['itemTitleColor'] ?? '' ),
		];

		foreach ( $mapped as $key => $value ) {
			if ( '' !== $value ) {
				$atts[ $key ] = $value;
			}
		}

		return $atts;
	}

	/**
	 * Returns gallery select options for the editor.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_gallery_options(): array {
		$options = [];

		foreach ( $this->repository->get_galleries() as $gallery ) {
			$options[] = [
				'value' => (int) ( $gallery['id'] ?? 0 ),
				'label' => sprintf(
					'#%1$d - %2$s',
					(int) ( $gallery['id'] ?? 0 ),
					sanitize_text_field( (string) ( $gallery['title'] ?? '' ) )
				),
			];
		}

		return $options;
	}

	/**
	 * Returns album select options for the editor.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_album_options(): array {
		$options = [];

		foreach ( $this->repository->get_albums() as $album ) {
			$options[] = [
				'value' => (int) ( $album['id'] ?? 0 ),
				'label' => sprintf(
					'#%1$d - %2$s',
					(int) ( $album['id'] ?? 0 ),
					sanitize_text_field( (string) ( $album['title'] ?? '' ) )
				),
			];
		}

		return $options;
	}

	/**
	 * Returns tag select options for the editor.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_tag_options(): array {
		$options = [];
		$report  = $this->repository->get_tag_report();

		foreach ( $report['items'] ?? [] as $tag ) {
			$options[] = [
				'value' => sanitize_title( (string) ( $tag['slug'] ?? '' ) ),
				'label' => sanitize_text_field( (string) ( $tag['name'] ?? $tag['slug'] ?? '' ) ),
			];
		}

		return $options;
	}

	/**
	 * Sanitizes an optional integer value.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum allowed.
	 * @param int   $max   Maximum allowed.
	 * @return string
	 */
	private function sanitize_optional_integer( $value, int $min, int $max ): string {
		if ( '' === trim( (string) $value ) ) {
			return '';
		}

		$number = absint( $value );
		$number = max( $min, $number );
		$number = min( $max, $number );

		return (string) $number;
	}

	/**
	 * Sanitizes an optional tri-state toggle.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_optional_toggle( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( in_array( $value, [ '1', 'true', 'yes', 'on' ], true ) ) {
			return '1';
		}

		if ( in_array( $value, [ '0', 'false', 'no', 'off' ], true ) ) {
			return '0';
		}

		return '';
	}

	/**
	 * Sanitizes an optional color value.
	 *
	 * @param mixed $value Raw color.
	 * @return string
	 */
	private function sanitize_optional_color( $value ): string {
		$color = sanitize_hex_color( (string) $value );

		return is_string( $color ) ? $color : '';
	}

	/**
	 * Sanitizes an optional choice.
	 *
	 * @param string              $value   Raw value.
	 * @param array<int, string>  $allowed Allowed values.
	 * @return string
	 */
	private function sanitize_choice( string $value, array $allowed ): string {
		$value = sanitize_key( $value );

		return in_array( $value, $allowed, true ) ? $value : '';
	}
}
