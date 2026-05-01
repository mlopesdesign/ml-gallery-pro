<?php
/**
 * Frontend shortcodes.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Frontend;

use MLGP\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	/**
	 * Repository instance.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Routed gallery payload for canonical frontend URLs.
	 *
	 * @var array<string, mixed>|null
	 */
	private $routed_gallery = null;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Shared repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers shortcode hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init', [ $this, 'register_gallery_route' ] );
		add_filter( 'query_vars', [ $this, 'register_gallery_query_var' ] );
		add_filter( 'redirect_canonical', [ $this, 'disable_gallery_route_canonical_redirect' ], 10, 2 );
		add_filter( 'template_include', [ $this, 'template_include' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_shortcode( 'ml_gallery_pro', [ $this, 'render' ] );
		add_shortcode( 'mlgp_gallery', [ $this, 'render' ] );
		add_shortcode( 'ml_gallery', [ $this, 'render' ] );
		add_shortcode( 'ml_album', [ $this, 'render' ] );
		add_shortcode( 'ml_tag_gallery', [ $this, 'render' ] );
	}


	/**
	 * Registers canonical gallery route.
	 *
	 * @return void
	 */
	public function register_gallery_route(): void {
		add_rewrite_rule( '^galeria/([^/]+)/?$', 'index.php?mlgp_gallery=$matches[1]', 'top' );
	}

	/**
	 * Registers gallery query var.
	 *
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public function register_gallery_query_var( array $vars ): array {
		$vars[] = 'mlgp_gallery';

		return $vars;
	}

	/**
	 * Prevents WordPress from redirecting valid canonical gallery routes.
	 *
	 * @param string|false $redirect_url Redirect URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function disable_gallery_route_canonical_redirect( $redirect_url, string $requested_url ) {
		unset( $requested_url );

		if ( '' !== sanitize_title( (string) get_query_var( 'mlgp_gallery' ) ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Loads the canonical single-gallery template.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function template_include( string $template ): string {
		$slug = sanitize_title( (string) get_query_var( 'mlgp_gallery' ) );

		if ( '' === $slug ) {
			return $template;
		}

		$gallery = $this->repository->get_gallery_by_slug( $slug );

		if ( empty( $gallery ) ) {
			global $wp_query;

			if ( isset( $wp_query ) ) {
				$wp_query->set_404();
			}

			status_header( 404 );
			nocache_headers();

			return get_404_template() ?: $template;
		}

		$this->routed_gallery = $gallery;
		$GLOBALS['mlgp_routed_gallery'] = $gallery;

		status_header( 200 );
		nocache_headers();

		return MLGP_DIR . 'includes/Frontend/templates/single-gallery.php';
	}

	/**
	 * Returns the currently routed gallery.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_routed_gallery(): ?array {
		return is_array( $this->routed_gallery ) ? $this->routed_gallery : null;
	}

	/**
	 * Registers frontend assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style(
			'mlgp-frontend',
			MLGP_URL . 'assets/css/frontend.css',
			[],
			MLGP_VERSION
		);

		wp_register_script(
			'mlgp-frontend',
			MLGP_URL . 'assets/js/frontend.js',
			[],
			MLGP_VERSION,
			true
		);
	}

	/**
	 * Shortcode renderer.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @param string|null          $content Shortcode content.
	 * @param string               $tag Shortcode tag.
	 * @return string
	 */
	public function render( $atts, $content = null, string $tag = '' ): string {
		$atts = shortcode_atts(
			[
				'gallery'         => 0,
				'album'           => 0,
				'id'              => 0,
				'type'            => 'gallery',
				'display_type'    => '',
				'tag'             => '',
				'tags'            => '',
				'show_titles'     => '',
				'show_captions'   => '',
				'show_tags'       => '',
				'show_item_tags'  => '',
				'columns_desktop' => '',
				'columns_tablet'  => '',
				'columns_mobile'  => '',
				'gap'             => '',
				'autoplay'        => '',
				'interval'        => '',
				'show_arrows'     => '',
				'show_thumbs'     => '',
				'row_height'      => '',
				'rounded_corners' => '',
				'pagination'      => '',
				'per_page'        => '',
				'page'            => '',
				'filters'         => '',
				'album_cover_width'  => '',
				'album_cover_height' => '',
				'album_cover_fit'    => '',
				'show_heading'    => '',
				'show_description'=> '',
				'heading_font_size' => '',
				'heading_color'     => '',
				'item_title_font_size' => '',
				'item_title_color'     => '',
			],
			(array) $atts,
			$tag ?: 'ml_gallery_pro'
		);

		$gallery_id = absint( $atts['gallery'] );
		$album_id   = absint( $atts['album'] );
		$id         = absint( $atts['id'] );
		$type       = sanitize_key( (string) $atts['type'] );
		$tag_filter = $this->normalize_tag_filters( (string) ( $atts['tag'] ?: $atts['tags'] ) );

		if ( 0 === $album_id && ( 'album' === $type || 'ml_album' === $tag ) ) {
			$album_id = $id;
		}

		if ( 0 === $gallery_id && 0 === $album_id && $id > 0 ) {
			$gallery_id = $id;
		}

		if ( $gallery_id > 0 ) {
			return $this->render_gallery( $gallery_id, (array) $atts );
		}

		if ( $album_id > 0 ) {
			return $this->render_album( $album_id, (array) $atts );
		}

		if ( ! empty( $tag_filter ) && ( 'tag' === $type || 'ml_tag_gallery' === $tag || ( 0 === $gallery_id && 0 === $album_id ) ) ) {
			return $this->render_tag_gallery( $tag_filter, (array) $atts );
		}

		return '';
	}

	/**
	 * Renders one gallery.
	 *
	 * @param int                  $gallery_id Gallery ID.
	 * @param array<string, mixed> $atts       Shortcode attributes.
	 * @return string
	 */
	private function render_gallery( int $gallery_id, array $atts ): string {
		$gallery         = $this->repository->get_gallery( $gallery_id );
		$items           = $this->repository->get_gallery_items( $gallery_id );
		$global_settings = $this->repository->get_settings();

		if ( empty( $gallery ) ) {
			return '';
		}

		$config = $this->build_gallery_config( $gallery, $global_settings, $atts );
		$items  = $this->filter_items_by_tags( $items, $config['tag_filters'] );
		$filter_state = $this->resolve_frontend_filter_state( $items, 'gallery', (string) $gallery_id );
		$items        = $this->apply_frontend_filters( $items, $filter_state );
		$filter_state['filtered_count'] = count( $items );
		$filter_state['total_count']    = (int) ( $filter_state['total_count'] ?? count( $items ) );
		$filter_markup = $this->supports_frontend_filters( $config ) && ( ! empty( $filter_state['total_count'] ) || ! empty( $filter_state['active'] ) )
			? $this->render_gallery_filters( $filter_state, $this->build_pagination_key( 'gallery', (string) $gallery_id ) )
			: '';

		if ( ! empty( $config['pagination_enabled'] ) ) {
			$page  = $this->paginate_items(
				$items,
				(int) $config['items_per_page'],
				$this->resolve_current_page( $atts['page'], $this->build_pagination_key( 'gallery', (string) $gallery_id ) )
			);
			$items = $page['items'];
		} else {
			$page = [
				'items'        => $items,
				'current_page' => 1,
				'total_pages'  => 1,
				'total_items'  => count( $items ),
			];
		}

		wp_enqueue_style( 'mlgp-frontend' );
		wp_enqueue_script( 'mlgp-frontend' );

		$wrapper_style = $this->build_wrapper_style( $config );

		if ( empty( $items ) ) {
			$message = ! empty( $filter_state['active'] )
				? __( 'Nenhuma imagem encontrada para os filtros atuais.', 'ml-gallery-pro' )
				: (string) $global_settings['empty_gallery_message'];
			$content = $filter_markup . '<div class="mlgp-empty-state">' . esc_html( $message ) . '</div>';
		} elseif ( 'slideshow' === $config['display_type'] ) {
			$content = $this->render_slideshow_gallery( $items, $config );
		} elseif ( 'filmstrip' === $config['display_type'] ) {
			$content = $this->render_filmstrip_gallery( $items, $config );
		} elseif ( 'imagebrowser' === $config['display_type'] ) {
			$content = $this->render_imagebrowser_gallery( $items, $config );
		} else {
			$content = $this->render_grid_gallery( $items, $config, $filter_markup );
		}

		$pagination = ! empty( $config['pagination_enabled'] )
			? $this->render_pagination(
				(int) $page['current_page'],
				(int) $page['total_pages'],
				$this->build_pagination_key( 'gallery', (string) $gallery_id )
			)
			: '';

		ob_start();
		?>
		<div class="mlgp-frontend mlgp-gallery mlgp-gallery--<?php echo esc_attr( $config['display_type'] ); ?>" style="<?php echo esc_attr( $wrapper_style ); ?>" data-mlgp-gallery-id="<?php echo esc_attr( (string) $gallery_id ); ?>" data-mlgp-gallery-url="<?php echo esc_url( mlgp_get_gallery_url( $gallery_id ) ); ?>">
			<?php if ( ( empty( $config['hide_all_titles'] ) && ! empty( $config['show_heading'] ) ) || ( ! empty( $config['show_description'] ) && ! empty( $gallery['description'] ) ) ) : ?>
				<div class="mlgp-frontend__header">
					<?php if ( empty( $config['hide_all_titles'] ) && ! empty( $config['show_heading'] ) ) : ?>
						<h2><?php echo esc_html( $gallery['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( ! empty( $config['show_description'] ) && ! empty( $gallery['description'] ) ) : ?>
						<div class="mlgp-frontend__description"><?php echo wp_kses_post( wpautop( $gallery['description'] ) ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders one album.
	 *
	 * @param int $album_id Album ID.
	 * @return string
	 */
	private function render_album( int $album_id, array $atts = [] ): string {
		$album    = $this->repository->get_album( $album_id );
		$settings = $this->repository->get_settings();

		if ( empty( $album ) ) {
			return '';
		}

		$config = $this->build_album_config( $album, $settings, $atts );
		$view   = $this->resolve_album_view( $album_id );

		if ( 'gallery' === $view['type'] && $view['id'] > 0 ) {
			$trail = $this->find_album_path( $album_id, 'gallery', $view['id'] );

			if ( ! empty( $trail ) ) {
				return $this->render_album_gallery_view( $album_id, (int) $view['id'], $trail, $atts, $config );
			}
		}

		$current_album_id = $album_id;
		$trail            = [ $album ];

		if ( 'album' === $view['type'] && $view['id'] > 0 && $view['id'] !== $album_id ) {
			$path = $this->find_album_path( $album_id, 'album', $view['id'] );

			if ( ! empty( $path ) ) {
				$trail            = $path;
				$current_album_id = (int) ( end( $path )['id'] ?? $album_id );
			}
		}

		$current_album = $this->repository->get_album( $current_album_id );
		$items         = $this->repository->get_album_items( $current_album_id );

		if ( empty( $current_album ) ) {
			return '';
		}

		wp_enqueue_style( 'mlgp-frontend' );
		wp_enqueue_script( 'mlgp-frontend' );

		$page_key      = $this->build_pagination_key( 'album', (string) $current_album_id );
		$wrapper_style = $this->build_wrapper_style( $config );

		if ( ! empty( $config['pagination_enabled'] ) ) {
			$page  = $this->paginate_items(
				$items,
				(int) $config['items_per_page'],
				$this->resolve_current_page( $atts['page'], $page_key )
			);
			$items = $page['items'];
		} else {
			$page = [
				'items'        => $items,
				'current_page' => 1,
				'total_pages'  => 1,
				'total_items'  => count( $items ),
			];
		}

		$pagination = ! empty( $config['pagination_enabled'] )
			? $this->render_pagination(
				(int) $page['current_page'],
				(int) $page['total_pages'],
				$page_key
			)
			: '';

		ob_start();
		?>
		<div class="mlgp-frontend mlgp-album mlgp-album--<?php echo esc_attr( $config['display_type'] ); ?>" style="<?php echo esc_attr( $wrapper_style ); ?>">
			<?php echo $this->render_album_breadcrumb( $album_id, $trail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( ( empty( $config['hide_all_titles'] ) && ! empty( $config['show_heading'] ) ) || ( ! empty( $config['show_description'] ) && ! empty( $current_album['description'] ) ) ) : ?>
				<div class="mlgp-frontend__header">
					<?php if ( empty( $config['hide_all_titles'] ) && ! empty( $config['show_heading'] ) ) : ?>
						<h2><?php echo esc_html( (string) $current_album['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( ! empty( $config['show_description'] ) && ! empty( $current_album['description'] ) ) : ?>
						<div class="mlgp-frontend__description"><?php echo wp_kses_post( wpautop( (string) $current_album['description'] ) ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<div class="mlgp-empty-state"><?php echo esc_html( $settings['empty_album_message'] ); ?></div>
			<?php else : ?>
				<?php echo $this->render_album_collection( $album_id, $items, $config, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the collection grid for one album.
	 *
	 * @param int                        $root_album_id Root album ID.
	 * @param array<int, array<string, mixed>> $items   Album items.
	 * @param array<string, mixed>       $config        Render config.
	 * @param array<string, mixed>       $settings      Global settings.
	 * @return string
	 */
	private function render_album_collection( int $root_album_id, array $items, array $config, array $settings ): string {
		$display_type  = $this->sanitize_album_display_type( (string) ( $config['display_type'] ?? 'grid' ) );
		$wrapper_class = 'mlgp-grid mlgp-album-collection';

		if ( in_array( $display_type, [ 'mosaic', 'masonry', 'justified', 'tile' ], true ) ) {
			$wrapper_class .= ' mlgp-grid--' . $display_type;
		}

		$wrapper_class .= ' mlgp-album-collection--' . $display_type;

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<?php echo $this->render_album_collection_item( $root_album_id, $item, $config, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders one item card inside an album collection.
	 *
	 * @param int                  $root_album_id Root album ID.
	 * @param array<string, mixed> $item          Album item payload.
	 * @param array<string, mixed> $config        Render config.
	 * @param array<string, mixed> $settings      Global settings.
	 * @return string
	 */
	private function render_album_collection_item( int $root_album_id, array $item, array $config, array $settings ): string {
		$item_type = sanitize_key( (string) ( $item['item_type'] ?? 'gallery' ) );
		$item_type = 'album' === $item_type ? 'album' : 'gallery';
		$item_id   = (int) ( $item['item_id'] ?? 0 );

		if ( $item_id <= 0 ) {
			return '';
		}

		$child = 'album' === $item_type ? $this->repository->get_album( $item_id ) : $this->repository->get_gallery( $item_id );

		if ( empty( $child ) ) {
			return '';
		}

		$cover = 'album' === $item_type
			? $this->repository->get_album_cover_payload( $item_id )
			: $this->repository->get_gallery_cover_payload( $item_id );
		$item_url   = $this->build_album_item_url( $root_album_id, $item_type, $item_id );
		$item_style = $cover ? $this->build_item_style( [ 'attachment' => $cover ], $config ) : '';
		$caption    = ! empty( $config['show_captions'] ) && ! empty( $child['description'] )
			? wp_strip_all_tags( (string) $child['description'] )
			: '';

		ob_start();
		?>
		<article class="mlgp-card mlgp-card--album mlgp-card--album-entry mlgp-card--<?php echo esc_attr( $config['display_type'] ); ?>" style="<?php echo esc_attr( $item_style ); ?>">
			<a class="mlgp-card__media mlgp-card__media--album<?php echo ( "grid_plus" === (string) ( $config['display_type'] ?? "grid" ) ) ? ' mlgp-card__media--album-grid-plus' : ''; ?><?php echo empty( $cover ) ? ' is-empty' : ''; ?>" href="<?php echo esc_url( $item_url ); ?>">
				<?php if ( $cover ) : ?>
					<?php echo $this->render_media_image( $cover, [], $settings, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="mlgp-card__media-placeholder"><?php echo esc_html( ! empty( $config['hide_all_titles'] ) ? __( 'Álbum', 'ml-gallery-pro' ) : (string) $child['title'] ); ?></span>
				<?php endif; ?>
			</a>
			<?php if ( ( empty( $config['hide_all_titles'] ) && ! empty( $config['show_titles'] ) ) || '' !== $caption ) : ?>
				<div class="mlgp-card__content mlgp-card__content--album">
					<?php if ( empty( $config['hide_all_titles'] ) && ! empty( $config['show_titles'] ) ) : ?>
						<h3><a class="mlgp-card__title-link" href="<?php echo esc_url( $item_url ); ?>"><?php echo $this->render_album_card_title( (string) $child['title'], (string) ( $config['display_type'] ?? 'grid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></h3>
					<?php endif; ?>
					<?php if ( '' !== $caption ) : ?>
						<div class="mlgp-card__caption"><?php echo esc_html( $caption ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}


	/**
	 * Renders the album card title.
	 *
	 * Grid Plus gets structured lines: event / date / credits.
	 *
	 * @param string $title        Raw title.
	 * @param string $display_type Active album display type.
	 * @return string
	 */
	private function render_album_card_title( string $title, string $display_type ): string {
		$title = trim( wp_strip_all_tags( $title ) );

		if ( '' === $title ) {
			return '';
		}

		if ( 'grid_plus' !== $display_type ) {
			return esc_html( $title );
		}

		if ( ! preg_match( '/\b(\d{2}-\d{2}-\d{4})\b/u', $title, $matches, PREG_OFFSET_CAPTURE ) ) {
			return esc_html( $title );
		}

		$date_match  = $matches[1][0] ?? '';
		$date_offset = (int) ( $matches[1][1] ?? -1 );

		if ( '' === $date_match || $date_offset < 0 ) {
			return esc_html( $title );
		}

		$event_line  = trim( substr( $title, 0, $date_offset ) );
		$credit_line = trim( substr( $title, $date_offset + strlen( $date_match ) ) );
		$event_line  = preg_replace( '/\s*-\s*$/u', '', $event_line ) ?: $event_line;
		$credit_line = preg_replace( '/^\s*-\s*/u', '', $credit_line ) ?: $credit_line;

		$lines = [
			'<span class="mlgp-card__title-line mlgp-card__title-line--event">' . esc_html( trim( $event_line ) ) . '</span>',
			'<span class="mlgp-card__title-line mlgp-card__title-line--date">' . esc_html( $date_match ) . '</span>',
		];

		if ( '' !== trim( $credit_line ) ) {
			$lines[] = '<span class="mlgp-card__title-line mlgp-card__title-line--credit">' . esc_html( trim( $credit_line ) ) . '</span>';
		}

		return implode( '', $lines );
	}

	/**
	 * Renders one premium hero block for album collections.
	 *
	 * @param array<string, mixed>           $album    Album payload.
	 * @param array<int, array<string,mixed>> $items    Album children.
	 * @param array<string, mixed>           $settings Global settings.
	 * @return string
	 */
	private function render_album_hero( array $album, array $items, array $settings ): string {
		$gallery_count = 0;
		$album_count   = 0;
		$preview_tiles = [];

		foreach ( $items as $item ) {
			$item_type = (string) ( $item['item_type'] ?? 'gallery' );
			$item_id   = (int) ( $item['item_id'] ?? 0 );

			if ( 'album' === $item_type ) {
				++$album_count;
			} else {
				++$gallery_count;
			}

			if ( $item_id <= 0 || count( $preview_tiles ) >= 4 ) {
				continue;
			}

			$cover = 'album' === $item_type
				? $this->repository->get_album_cover_payload( $item_id )
				: $this->repository->get_gallery_cover_payload( $item_id );

			if ( ! empty( $cover ) ) {
				$preview_tiles[] = $cover;
			}
		}

		$total_items = count( $items );

		ob_start();
		?>
		<section class="mlgp-album-hero">
			<div class="mlgp-album-hero__copy">
				<span class="mlgp-album-hero__eyebrow"><?php esc_html_e( 'Coleção', 'ml-gallery-pro' ); ?></span>
				<h2><?php echo esc_html( (string) ( $album['title'] ?? '' ) ); ?></h2>
				<?php if ( ! empty( $album['description'] ) ) : ?>
					<div class="mlgp-frontend__description"><?php echo wp_kses_post( wpautop( (string) $album['description'] ) ); ?></div>
				<?php endif; ?>
				<div class="mlgp-album-hero__stats">
					<?php if ( $gallery_count > 0 ) : ?>
						<span class="mlgp-album-hero__stat"><?php echo esc_html( sprintf( _n( '%d galeria', '%d galerias', $gallery_count, 'ml-gallery-pro' ), $gallery_count ) ); ?></span>
					<?php endif; ?>
					<?php if ( $album_count > 0 ) : ?>
						<span class="mlgp-album-hero__stat"><?php echo esc_html( sprintf( _n( '%d subálbum', '%d subálbuns', $album_count, 'ml-gallery-pro' ), $album_count ) ); ?></span>
					<?php endif; ?>
					<span class="mlgp-album-hero__stat"><?php echo esc_html( sprintf( _n( '%d item na coleção', '%d itens na coleção', $total_items, 'ml-gallery-pro' ), $total_items ) ); ?></span>
				</div>
			</div>
			<div class="mlgp-album-hero__visual">
				<?php if ( ! empty( $preview_tiles ) ) : ?>
					<div class="mlgp-album-hero__mosaic">
						<?php foreach ( $preview_tiles as $index => $cover ) : ?>
							<div class="mlgp-album-hero__tile mlgp-album-hero__tile--<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
								<?php echo $this->render_media_image( $cover, [], $settings, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="mlgp-album-hero__empty"><?php esc_html_e( 'Coleção pronta para receber galerias e subálbuns.', 'ml-gallery-pro' ); ?></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders a dynamic gallery filtered by tag across published galleries.
	 *
	 * @param array<int, string>   $tag_filters Sanitized tag filters.
	 * @param array<string, mixed> $atts        Shortcode attributes.
	 * @return string
	 */
	private function render_tag_gallery( array $tag_filters, array $atts ): string {
		$global_settings = $this->repository->get_settings();
		$items           = $this->repository->get_tagged_items( $tag_filters, true );
		$config          = $this->build_gallery_config(
			[
				'display_type' => $atts['display_type'] ?: 'grid',
				'settings'     => [],
			],
			$global_settings,
			$atts
		);
		$page_key        = $this->build_pagination_key( 'tag', substr( md5( implode( ',', $tag_filters ) ), 0, 10 ) );
		$filter_state    = $this->resolve_frontend_filter_state( $items, 'tag', substr( md5( implode( ',', $tag_filters ) ), 0, 10 ) );
		$items           = $this->apply_frontend_filters( $items, $filter_state );
		$filter_state['filtered_count'] = count( $items );
		$filter_state['total_count']    = (int) ( $filter_state['total_count'] ?? count( $items ) );
		$filter_markup = $this->supports_frontend_filters( $config ) && ( ! empty( $filter_state['total_count'] ) || ! empty( $filter_state['active'] ) )
			? $this->render_gallery_filters( $filter_state, $page_key )
			: '';

		if ( ! empty( $config['pagination_enabled'] ) ) {
			$page  = $this->paginate_items(
				$items,
				(int) $config['items_per_page'],
				$this->resolve_current_page( $atts['page'], $page_key )
			);
			$items = $page['items'];
		} else {
			$page = [
				'items'        => $items,
				'current_page' => 1,
				'total_pages'  => 1,
				'total_items'  => count( $items ),
			];
		}

		$config['show_source_gallery'] = 1;
		$display_tags                  = array_map(
			static function ( string $tag ): string {
				return ucwords( str_replace( '-', ' ', $tag ) );
			},
			$tag_filters
		);
		$tag_label                     = implode( ', ', $display_tags );
		$title                         = count( $display_tags ) > 1 ? 'Tags: ' . $tag_label : 'Tag: ' . $tag_label;

		wp_enqueue_style( 'mlgp-frontend' );
		wp_enqueue_script( 'mlgp-frontend' );

		$wrapper_style = $this->build_wrapper_style( $config );

		if ( empty( $items ) ) {
			$message = ! empty( $filter_state['active'] )
				? __( 'Nenhuma imagem encontrada para os filtros atuais.', 'ml-gallery-pro' )
				: (string) $global_settings['empty_gallery_message'];
			$content = $filter_markup . '<div class="mlgp-empty-state">' . esc_html( $message ) . '</div>';
		} elseif ( 'slideshow' === $config['display_type'] ) {
			$content = $this->render_slideshow_gallery( $items, $config );
		} elseif ( 'filmstrip' === $config['display_type'] ) {
			$content = $this->render_filmstrip_gallery( $items, $config );
		} elseif ( 'imagebrowser' === $config['display_type'] ) {
			$content = $this->render_imagebrowser_gallery( $items, $config );
		} else {
			$content = $this->render_grid_gallery( $items, $config, $filter_markup );
		}

		$pagination = ! empty( $config['pagination_enabled'] )
			? $this->render_pagination(
				(int) $page['current_page'],
				(int) $page['total_pages'],
				$page_key
			)
			: '';

		ob_start();
		?>
		<div class="mlgp-frontend mlgp-gallery mlgp-gallery--tag mlgp-gallery--<?php echo esc_attr( $config['display_type'] ); ?>" style="<?php echo esc_attr( $wrapper_style ); ?>">
			<div class="mlgp-frontend__header">
				<?php if ( empty( $config['hide_all_titles'] ) ) : ?>
					<h2><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<div class="mlgp-frontend__description"><?php esc_html_e( 'Galeria dinamica gerada a partir das tags selecionadas.', 'ml-gallery-pro' ); ?></div>
			</div>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Builds one normalized gallery config array.
	 *
	 * @param array<string, mixed> $gallery         Gallery payload.
	 * @param array<string, mixed> $global_settings Global plugin settings.
	 * @param array<string, mixed> $atts            Shortcode attributes.
	 * @return array<string, mixed>
	 */
	private function build_gallery_config( array $gallery, array $global_settings, array $atts ): array {
		$gallery_settings = isset( $gallery['settings'] ) && is_array( $gallery['settings'] ) ? $gallery['settings'] : [];

		return [
			'display_type'       => $this->sanitize_display_type( (string) ( $atts['display_type'] ?: ( $gallery['display_type'] ?? 'grid' ) ) ),
			'columns_desktop'    => $this->sanitize_integer( $atts['columns_desktop'] ?: ( $gallery_settings['columns_desktop'] ?? $global_settings['columns_desktop'] ?? 4 ), 1, 8 ),
			'columns_tablet'     => $this->sanitize_integer( $atts['columns_tablet'] ?: ( $gallery_settings['columns_tablet'] ?? $global_settings['columns_tablet'] ?? 3 ), 1, 6 ),
			'columns_mobile'     => $this->sanitize_integer( $atts['columns_mobile'] ?: ( $gallery_settings['columns_mobile'] ?? $global_settings['columns_mobile'] ?? 2 ), 1, 4 ),
			'gap'                => $this->sanitize_integer( $atts['gap'] ?: ( $gallery_settings['card_gap'] ?? $global_settings['card_gap'] ?? 0 ), 0, 48 ),
			'card_padding'       => $this->sanitize_integer( $gallery_settings['card_padding'] ?? $global_settings['card_padding'] ?? 0, 0, 80 ),
			'card_margin'        => $this->sanitize_integer( $gallery_settings['card_margin'] ?? $global_settings['card_margin'] ?? 0, 0, 40 ),
			'card_border_width'  => $this->sanitize_integer( $gallery_settings['card_border_width'] ?? $global_settings['card_border_width'] ?? 0, 0, 20 ),
			'card_border_color'  => $this->sanitize_color( $gallery_settings['card_border_color'] ?? $global_settings['card_border_color'] ?? '#d7e0ea', '#d7e0ea' ),
			'card_border_opacity'=> $this->sanitize_integer( $gallery_settings['card_border_opacity'] ?? $global_settings['card_border_opacity'] ?? 100, 0, 100 ),
			'gap_background_color' => $this->sanitize_color( $gallery_settings['gap_background_color'] ?? $global_settings['gap_background_color'] ?? '#ffffff', '#ffffff' ),
			'gap_background_opacity' => $this->sanitize_integer( $gallery_settings['gap_background_opacity'] ?? $global_settings['gap_background_opacity'] ?? 100, 0, 100 ),
			'wrapper_padding'    => $this->sanitize_integer( $global_settings['wrapper_padding'] ?? 0, 0, 120 ),
			'wrapper_radius'     => $this->sanitize_integer( $global_settings['wrapper_radius'] ?? 0, 0, 80 ),
			'wrapper_border_width' => $this->sanitize_integer( $global_settings['wrapper_border_width'] ?? 0, 0, 20 ),
			'wrapper_border_color' => $this->sanitize_color( $global_settings['wrapper_border_color'] ?? '#ffffff', '#ffffff' ),
			'wrapper_border_opacity' => $this->sanitize_integer( $global_settings['wrapper_border_opacity'] ?? 0, 0, 100 ),
			'wrapper_background_color' => $this->sanitize_color( $global_settings['wrapper_background_color'] ?? '#ffffff', '#ffffff' ),
			'wrapper_background_opacity' => $this->sanitize_integer( $global_settings['wrapper_background_opacity'] ?? 0, 0, 100 ),
			'wrapper_shadow_opacity' => $this->sanitize_integer( $global_settings['wrapper_shadow_opacity'] ?? 0, 0, 100 ),
			'wrapper_max_width'  => $this->sanitize_integer( $global_settings['wrapper_max_width'] ?? 0, 0, 3840 ),
			'rounded_corners'    => $this->bool_value( $atts['rounded_corners'], $gallery_settings['rounded_corners'] ?? $global_settings['rounded_corners'] ?? 1 ),
			'enable_frontend_filters' => $this->bool_value( $atts['filters'], $gallery_settings['enable_frontend_filters'] ?? $global_settings['enable_frontend_filters'] ?? 0 ),
			'pagination_enabled' => $this->bool_value( $atts['pagination'], $gallery_settings['pagination_enabled'] ?? $global_settings['pagination_enabled'] ?? 1 ),
			'show_heading'       => $this->bool_value( $atts['show_heading'], $gallery_settings['show_heading'] ?? $global_settings['show_gallery_heading'] ?? 1 ),
			'show_description'   => $this->bool_value( $atts['show_description'], $gallery_settings['show_description'] ?? $global_settings['show_gallery_description'] ?? 1 ),
			'show_titles'        => $this->bool_value( $atts['show_titles'], $gallery_settings['show_titles'] ?? $global_settings['show_titles'] ?? 0 ),
			'show_captions'      => $this->bool_value( $atts['show_captions'], $gallery_settings['show_captions'] ?? $global_settings['show_captions'] ?? 0 ),
			'show_item_tags'     => $this->bool_value(
				'' !== (string) $atts['show_tags'] ? $atts['show_tags'] : $atts['show_item_tags'],
				$gallery_settings['show_item_tags'] ?? $global_settings['show_item_tags'] ?? 0
			),
			'hide_all_titles'    => ! empty( $global_settings['hide_all_titles'] ) ? 1 : 0,
			'justified_row_height' => $this->sanitize_integer( $atts['row_height'] ?: ( $gallery_settings['justified_row_height'] ?? 220 ), 120, 520 ),
			'slideshow_autoplay' => $this->bool_value( $atts['autoplay'], $gallery_settings['slideshow_autoplay'] ?? 1 ),
			'slideshow_interval' => $this->sanitize_integer( $atts['interval'] ?: ( $gallery_settings['slideshow_interval'] ?? 4000 ), 1500, 20000 ),
			'slideshow_show_arrows' => $this->bool_value( $atts['show_arrows'], $gallery_settings['slideshow_show_arrows'] ?? $global_settings['slideshow_show_arrows'] ?? 1 ),
			'slideshow_show_thumbs' => $this->bool_value( $atts['show_thumbs'], $gallery_settings['slideshow_show_thumbs'] ?? $global_settings['slideshow_show_thumbs'] ?? 1 ),
			'nav_arrow_prev_url' => esc_url_raw( (string) ( $global_settings['nav_arrow_prev_url'] ?? '' ) ),
			'nav_arrow_next_url' => esc_url_raw( (string) ( $global_settings['nav_arrow_next_url'] ?? '' ) ),
			'items_per_page'     => $this->sanitize_integer( $atts['per_page'] ?: ( $gallery_settings['items_per_page'] ?? $global_settings['items_per_page'] ?? 18 ), 1, 5000 ),
			'heading_font_size'  => $this->sanitize_integer( $atts['heading_font_size'] ?: ( $gallery_settings['heading_font_size'] ?? $global_settings['heading_font_size'] ?? 34 ), 20, 96 ),
			'heading_color'      => $this->sanitize_color( $atts['heading_color'] ?: ( $gallery_settings['heading_color'] ?? $global_settings['heading_color'] ?? '#172033' ), '#172033' ),
			'item_title_font_size' => $this->sanitize_integer( $atts['item_title_font_size'] ?: ( $gallery_settings['item_title_font_size'] ?? $global_settings['item_title_font_size'] ?? 18 ), 10, 48 ),
			'item_title_color'     => $this->sanitize_color( $atts['item_title_color'] ?: ( $gallery_settings['item_title_color'] ?? $global_settings['item_title_color'] ?? '#172033' ), '#172033' ),
			'enable_lightbox'    => ! empty( $global_settings['enable_lightbox'] ),
			'enable_lazy_load'   => ! empty( $global_settings['enable_lazy_load'] ),
			'tag_filters'        => $this->normalize_tag_filters( (string) ( $atts['tag'] ?: $atts['tags'] ) ),
		];
	}

	/**
	 * Builds frontend wrapper style tokens.
	 *
	 * @param array<string, mixed> $config Render config.
	 * @return string
	 */
	private function build_wrapper_style( array $config ): string {
		$explicit_card_radius = $this->sanitize_integer( $config['card_radius'] ?? -1, -1, 80 );
		$card_radius        = $explicit_card_radius >= 0 ? $explicit_card_radius : ( ! empty( $config['rounded_corners'] ) ? 16 : 0 );
		$thumb_radius       = $explicit_card_radius >= 0 ? max( 0, $card_radius - 4 ) : ( ! empty( $config['rounded_corners'] ) ? 12 : 0 );
		$card_padding       = $this->sanitize_integer( $config['card_padding'] ?? 0, 0, 80 );
		$card_margin        = $this->sanitize_integer( $config['card_margin'] ?? 0, 0, 40 );
		$card_border_width  = $this->sanitize_integer( $config['card_border_width'] ?? 0, 0, 20 );
		$card_border_color  = $this->sanitize_color( $config['card_border_color'] ?? '#d7e0ea', '#d7e0ea' );
		$card_border_rgba   = $this->hex_to_rgba( $card_border_color, $this->sanitize_integer( $config['card_border_opacity'] ?? 100, 0, 100 ) );
		$gap_background_rgba = $this->hex_to_rgba( $this->sanitize_color( $config['gap_background_color'] ?? '#ffffff', '#ffffff' ), $this->sanitize_integer( $config['gap_background_opacity'] ?? 100, 0, 100 ) );
		$wrapper_padding     = $this->sanitize_integer( $config['wrapper_padding'] ?? 0, 0, 120 );
		$wrapper_radius      = $this->sanitize_integer( $config['wrapper_radius'] ?? 0, 0, 80 );
		$wrapper_border_width = $this->sanitize_integer( $config['wrapper_border_width'] ?? 0, 0, 20 );
		$wrapper_border_rgba = $this->hex_to_rgba( $this->sanitize_color( $config['wrapper_border_color'] ?? '#ffffff', '#ffffff' ), $this->sanitize_integer( $config['wrapper_border_opacity'] ?? 0, 0, 100 ) );
		$wrapper_background_rgba = $this->hex_to_rgba( $this->sanitize_color( $config['wrapper_background_color'] ?? '#ffffff', '#ffffff' ), $this->sanitize_integer( $config['wrapper_background_opacity'] ?? 0, 0, 100 ) );
		$wrapper_shadow_rgba = $this->hex_to_rgba( '#111827', $this->sanitize_integer( $config['wrapper_shadow_opacity'] ?? 0, 0, 100 ) );
		$wrapper_shadow      = sprintf( '0 18px 48px %s', $wrapper_shadow_rgba );
		$wrapper_max_width   = $this->sanitize_integer( $config['wrapper_max_width'] ?? 0, 0, 3840 );
		$wrapper_max_width_css = $wrapper_max_width > 0 ? $wrapper_max_width . 'px' : 'none';
		$gallery_grid_max_width = sprintf( 'min(100%%, calc((100%% - (%1$dpx * 2)) + (%2$dpx * (%1$d - 1))))', max( 1, (int) $config['columns_desktop'] ), (int) $config['gap'] );
		$album_cover_width  = $this->sanitize_integer( $config['album_cover_width'] ?? 360, 120, 1800 );
		$album_cover_height = $this->sanitize_integer( $config['album_cover_height'] ?? 280, 120, 1200 );
		$album_cover_fit    = $this->sanitize_album_cover_fit( (string) ( $config['album_cover_fit'] ?? 'contain' ) );
		$album_cover_ratio  = max( 1, $album_cover_width ) . ' / ' . max( 1, $album_cover_height );

		return sprintf(
			'--mlgp-cols-desktop:%1$d;--mlgp-cols-tablet:%2$d;--mlgp-cols-mobile:%3$d;--mlgp-gap:%4$dpx;--mlgp-card-radius:%5$dpx;--mlgp-thumb-radius:%6$dpx;--mlgp-justified-row-height:%7$dpx;--mlgp-heading-size:%8$dpx;--mlgp-heading-color:%9$s;--mlgp-item-title-size:%10$dpx;--mlgp-item-title-color:%11$s;--mlgp-album-cover-width:%12$dpx;--mlgp-album-cover-height:%13$dpx;--mlgp-album-cover-fit:%14$s;--mlgp-card-padding:%15$dpx;--mlgp-card-margin:%16$dpx;--mlgp-card-border-width:%17$dpx;--mlgp-card-border:%18$s;--mlgp-gap-bg:%19$s;--mlgp-wrapper-padding:%20$dpx;--mlgp-wrapper-radius:%21$dpx;--mlgp-wrapper-border-width:%22$dpx;--mlgp-wrapper-border:%23$s;--mlgp-wrapper-bg:%24$s;--mlgp-wrapper-shadow:%25$s;--mlgp-wrapper-max-width:%26$s;--mlgp-gallery-grid-max-width:%27$s;--mlgp-album-cover-ratio:%28$s;',
			(int) $config['columns_desktop'],
			(int) $config['columns_tablet'],
			(int) $config['columns_mobile'],
			(int) $config['gap'],
			$card_radius,
			$thumb_radius,
			(int) $config['justified_row_height'],
			(int) ( $config['heading_font_size'] ?? 34 ),
			$this->sanitize_color( $config['heading_color'] ?? '#172033', '#172033' ),
			(int) ( $config['item_title_font_size'] ?? 18 ),
			$this->sanitize_color( $config['item_title_color'] ?? '#172033', '#172033' ),
			$album_cover_width,
			$album_cover_height,
			$album_cover_fit,
			$card_padding,
			$card_margin,
			$card_border_width,
			$card_border_rgba,
			$gap_background_rgba,
			$wrapper_padding,
			$wrapper_radius,
			$wrapper_border_width,
			$wrapper_border_rgba,
			$wrapper_background_rgba,
			$wrapper_shadow,
			$wrapper_max_width_css,
			$gallery_grid_max_width,
			$album_cover_ratio
		);
	}

	/**
	 * Builds one normalized album config array.
	 *
	 * @param array<string, mixed> $album           Album payload.
	 * @param array<string, mixed> $global_settings Global settings.
	 * @param array<string, mixed> $atts            Shortcode attributes.
	 * @return array<string, mixed>
	 */
	private function build_album_config( array $album, array $global_settings, array $atts ): array {
		$album_settings = isset( $album['settings'] ) && is_array( $album['settings'] ) ? $album['settings'] : [];
		$display_type   = (string) ( $atts['display_type'] ?: ( $album['display_type'] ?? ( $global_settings['default_album_display_type'] ?? 'grid' ) ) );
		$legacy_mode    = sanitize_key( $display_type );

		return [
			'display_type'         => $this->sanitize_album_display_type( $display_type ),
			'columns_desktop'      => $this->sanitize_integer( $atts['columns_desktop'] ?: ( $album_settings['columns_desktop'] ?? $global_settings['album_columns_desktop'] ?? 4 ), 1, 8 ),
			'columns_tablet'       => $this->sanitize_integer( $atts['columns_tablet'] ?: ( $album_settings['columns_tablet'] ?? $global_settings['album_columns_tablet'] ?? 3 ), 1, 6 ),
			'columns_mobile'       => $this->sanitize_integer( $atts['columns_mobile'] ?: ( $album_settings['columns_mobile'] ?? $global_settings['album_columns_mobile'] ?? 2 ), 1, 4 ),
			'gap'                  => $this->sanitize_integer( $atts['gap'] ?: ( $album_settings['card_gap'] ?? $global_settings['album_card_gap'] ?? 18 ), 0, 48 ),
			'rounded_corners'      => $this->bool_value( $atts['rounded_corners'], $album_settings['rounded_corners'] ?? ( ! empty( $global_settings['album_card_radius'] ) ? 1 : 0 ) ),
			'pagination_enabled'   => $this->bool_value( $atts['pagination'], $album_settings['pagination_enabled'] ?? $global_settings['album_pagination_enabled'] ?? 1 ),
			'items_per_page'       => $this->sanitize_integer( $atts['per_page'] ?: ( $album_settings['items_per_page'] ?? $global_settings['album_items_per_page'] ?? 18 ), 1, 5000 ),
			'show_heading'         => $this->bool_value( $atts['show_heading'], $album_settings['show_heading'] ?? $global_settings['album_show_heading'] ?? 0 ),
			'show_description'     => $this->bool_value( $atts['show_description'], $album_settings['show_description'] ?? $global_settings['album_show_description'] ?? 0 ),
			'show_titles'          => $this->bool_value( $atts['show_titles'], $album_settings['show_titles'] ?? $global_settings['album_show_titles'] ?? 1 ),
			'show_captions'        => $this->bool_value( $atts['show_captions'], $album_settings['show_captions'] ?? $global_settings['album_show_captions'] ?? ( 'extended' === $legacy_mode ? 1 : 0 ) ),
			'hide_all_titles'      => ! empty( $global_settings['hide_all_titles'] ) ? 1 : 0,
			'album_cover_width'    => $this->sanitize_integer( $atts['album_cover_width'] ?: ( $album_settings['album_cover_width'] ?? $global_settings['album_cover_width'] ?? 360 ), 120, 1800 ),
			'album_cover_height'   => $this->sanitize_integer( $atts['album_cover_height'] ?: ( $album_settings['album_cover_height'] ?? $global_settings['album_cover_height'] ?? 280 ), 120, 1200 ),
			'album_cover_fit'      => $this->sanitize_album_cover_fit( (string) ( $atts['album_cover_fit'] ?: ( $album_settings['album_cover_fit'] ?? $global_settings['album_cover_fit'] ?? 'contain' ) ) ),
			'justified_row_height' => $this->sanitize_integer( $atts['row_height'] ?: ( $album_settings['justified_row_height'] ?? 220 ), 120, 520 ),
			'heading_font_size'    => $this->sanitize_integer( $atts['heading_font_size'] ?: ( $album_settings['heading_font_size'] ?? $global_settings['heading_font_size'] ?? 34 ), 20, 96 ),
			'heading_color'        => $this->sanitize_color( $atts['heading_color'] ?: ( $album_settings['heading_color'] ?? $global_settings['heading_color'] ?? '#172033' ), '#172033' ),
			'item_title_font_size' => $this->sanitize_integer( $atts['item_title_font_size'] ?: ( $album_settings['item_title_font_size'] ?? $global_settings['album_item_title_font_size'] ?? $global_settings['item_title_font_size'] ?? 18 ), 10, 48 ),
			'item_title_color'     => $this->sanitize_color( $atts['item_title_color'] ?: ( $album_settings['item_title_color'] ?? $global_settings['album_item_title_color'] ?? $global_settings['item_title_color'] ?? '#172033' ), '#172033' ),
			'card_padding'        => $this->sanitize_integer( $album_settings['card_padding'] ?? $global_settings['album_card_padding'] ?? 0, 0, 80 ),
			'card_margin'         => $this->sanitize_integer( $album_settings['card_margin'] ?? $global_settings['album_card_margin'] ?? 0, 0, 40 ),
			'card_border_width'   => $this->sanitize_integer( $album_settings['card_border_width'] ?? $global_settings['album_card_border_width'] ?? 0, 0, 20 ),
			'card_border_color'   => $this->sanitize_color( $album_settings['card_border_color'] ?? $global_settings['album_card_border_color'] ?? '#d7e0ea', '#d7e0ea' ),
			'card_border_opacity' => $this->sanitize_integer( $album_settings['card_border_opacity'] ?? $global_settings['album_card_border_opacity'] ?? 100, 0, 100 ),
			'gap_background_color' => $this->sanitize_color( $album_settings['gap_background_color'] ?? $global_settings['album_gap_background_color'] ?? '#ffffff', '#ffffff' ),
			'gap_background_opacity' => $this->sanitize_integer( $album_settings['gap_background_opacity'] ?? $global_settings['album_gap_background_opacity'] ?? 100, 0, 100 ),
			'card_radius'         => $this->sanitize_integer( $album_settings['card_radius'] ?? $global_settings['album_card_radius'] ?? -1, -1, 80 ),
			'wrapper_padding' => $this->sanitize_integer( $global_settings['wrapper_padding'] ?? 0, 0, 120 ),
			'wrapper_radius' => $this->sanitize_integer( $global_settings['wrapper_radius'] ?? 0, 0, 80 ),
			'wrapper_border_width' => $this->sanitize_integer( $global_settings['wrapper_border_width'] ?? 0, 0, 20 ),
			'wrapper_border_color' => $this->sanitize_color( $global_settings['wrapper_border_color'] ?? '#ffffff', '#ffffff' ),
			'wrapper_border_opacity' => $this->sanitize_integer( $global_settings['wrapper_border_opacity'] ?? 0, 0, 100 ),
			'wrapper_background_color' => $this->sanitize_color( $global_settings['wrapper_background_color'] ?? '#ffffff', '#ffffff' ),
			'wrapper_background_opacity' => $this->sanitize_integer( $global_settings['wrapper_background_opacity'] ?? 0, 0, 100 ),
			'wrapper_shadow_opacity' => $this->sanitize_integer( $global_settings['wrapper_shadow_opacity'] ?? 0, 0, 100 ),
			'wrapper_max_width' => $this->sanitize_integer( $global_settings['wrapper_max_width'] ?? 0, 0, 3840 ),
		];
	}

	/**
	 * Builds one stable query arg name for gallery pagination.
	 *
	 * @param string $context Pagination context.
	 * @param string $suffix  Unique suffix.
	 * @return string
	 */
	private function build_pagination_key( string $context, string $suffix ): string {
		return sanitize_key( 'mlgp_page_' . $context . '_' . $suffix );
	}

	/**
	 * Resolves current page from shortcode attribute or query string.
	 *
	 * @param mixed  $requested Requested page attribute.
	 * @param string $query_key Query arg name.
	 * @return int
	 */
	private function resolve_current_page( $requested, string $query_key ): int {
		$page = absint( $requested );

		if ( $page < 1 && isset( $_GET[ $query_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = absint( wp_unslash( $_GET[ $query_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return max( 1, $page );
	}

	/**
	 * Splits gallery items into pages.
	 *
	 * @param array<int, array<string, mixed>> $items    Gallery items.
	 * @param int                              $per_page Items per page.
	 * @param int                              $page     Requested page.
	 * @return array<string, mixed>
	 */
	private function paginate_items( array $items, int $per_page, int $page ): array {
		$total_items = count( $items );
		$per_page    = max( 1, $per_page );
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		$page        = min( max( 1, $page ), $total_pages );

		return [
			'items'        => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ),
			'current_page' => $page,
			'total_pages'  => $total_pages,
			'total_items'  => $total_items,
		];
	}

	/**
	 * Renders frontend pagination controls.
	 *
	 * @param int    $current_page Current page.
	 * @param int    $total_pages  Total pages.
	 * @param string $query_key    Query arg name.
	 * @return string
	 */
	private function render_pagination( int $current_page, int $total_pages, string $query_key ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$base_url = remove_query_arg( $query_key, $this->current_request_url() );
		$tokens   = $this->get_pagination_tokens( $current_page, $total_pages );

		ob_start();
		?>
		<nav class="mlgp-pagination" aria-label="<?php esc_attr_e( 'Paginacao da galeria', 'ml-gallery-pro' ); ?>">
			<div class="mlgp-pagination__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page, 2: total pages. */
						__( 'Pagina %1$d de %2$d', 'ml-gallery-pro' ),
						$current_page,
						$total_pages
					)
				);
				?>
			</div>
			<div class="mlgp-pagination__list">
				<?php if ( $current_page > 1 ) : ?>
					<a class="mlgp-pagination__link is-nav" href="<?php echo esc_url( add_query_arg( $query_key, $current_page - 1, $base_url ) ); ?>"><?php esc_html_e( 'Anterior', 'ml-gallery-pro' ); ?></a>
				<?php endif; ?>

				<?php foreach ( $tokens as $token ) : ?>
					<?php if ( 'ellipsis' === $token ) : ?>
						<span class="mlgp-pagination__ellipsis">...</span>
					<?php else : ?>
						<?php $page_url = add_query_arg( $query_key, (int) $token, $base_url ); ?>
						<a class="mlgp-pagination__link <?php echo (int) $token === $current_page ? 'is-active' : ''; ?>" href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( (string) $token ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a class="mlgp-pagination__link is-nav" href="<?php echo esc_url( add_query_arg( $query_key, $current_page + 1, $base_url ) ); ?>"><?php esc_html_e( 'Proxima', 'ml-gallery-pro' ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Returns pagination tokens with ellipsis.
	 *
	 * @param int $current_page Current page.
	 * @param int $total_pages  Total pages.
	 * @return array<int, int|string>
	 */
	private function get_pagination_tokens( int $current_page, int $total_pages ): array {
		$pages = [ 1, $total_pages, $current_page - 1, $current_page, $current_page + 1 ];
		$pages = array_values(
			array_unique(
				array_filter(
					$pages,
					static function ( $page ) use ( $total_pages ): bool {
						return is_int( $page ) && $page >= 1 && $page <= $total_pages;
					}
				)
			)
		);
		sort( $pages );

		$tokens = [];

		foreach ( $pages as $index => $page ) {
			if ( $index > 0 ) {
				$previous = (int) $pages[ $index - 1 ];

				if ( $page - $previous > 1 ) {
					$tokens[] = 'ellipsis';
				}
			}

			$tokens[] = $page;
		}

		return $tokens;
	}

	/**
	 * Resolves current request URL for pagination links.
	 *
	 * @return string
	 */
	private function current_request_url(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return home_url( $request_uri );
	}

	/**
	 * Filters gallery items by tag list.
	 *
	 * @param array<int, array<string, mixed>> $items       Gallery items.
	 * @param array<int, string>               $tag_filters Sanitized tag filters.
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_items_by_tags( array $items, array $tag_filters ): array {
		if ( empty( $tag_filters ) ) {
			return $items;
		}

		return array_values(
			array_filter(
				$items,
				function ( array $item ) use ( $tag_filters ): bool {
					$item_tags = array_map( 'sanitize_title', (array) ( $item['tag_list'] ?? [] ) );

					return ! empty( array_intersect( $tag_filters, $item_tags ) );
				}
			)
		);
	}

	/**
	 * Renders one grid gallery.
	 *
	 * @param array<int, array<string, mixed>> $items  Gallery items.
	 * @param array<string, mixed>             $config Render config.
	 * @return string
	 */
	private function render_grid_gallery( array $items, array $config, string $filter_markup = '' ): string {
		$display_type = $this->sanitize_display_type( (string) ( $config['display_type'] ?? 'grid' ) );
		$wrapper_class = 'mlgp-grid';

		if ( in_array( $display_type, [ 'mosaic', 'masonry', 'justified', 'tile' ], true ) ) {
			$wrapper_class .= ' mlgp-grid--' . $display_type;
		}

		ob_start();
		?>
		<?php echo $filter_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<article class="mlgp-card mlgp-card--<?php echo esc_attr( $display_type ); ?>" style="<?php echo esc_attr( $this->build_item_style( $item, $config ) ); ?>">
					<?php echo $this->render_item_media( $item, $config, 'large', 'mlgp-card__media' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->render_item_content( $item, $config, 'mlgp-card__content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Determines whether the current gallery type supports frontend filters.
	 *
	 * @param array<string, mixed> $config Render config.
	 * @return bool
	 */
	private function supports_frontend_filters( array $config ): bool {
		$display_type = $this->sanitize_display_type( (string) ( $config['display_type'] ?? 'grid' ) );

		return ! empty( $config['enable_frontend_filters'] ) && ! in_array( $display_type, [ 'slideshow', 'filmstrip', 'imagebrowser' ], true );
	}

	/**
	 * Resolves the frontend filter state for one gallery render context.
	 *
	 * @param array<int, array<string, mixed>> $items   Gallery items.
	 * @param string                           $context Render context.
	 * @param string                           $suffix  Unique suffix.
	 * @return array<string, mixed>
	 */
	private function resolve_frontend_filter_state( array $items, string $context, string $suffix ): array {
		$search_key     = $this->build_filter_key( 'search', $context, $suffix );
		$tag_key        = $this->build_filter_key( 'tag', $context, $suffix );
		$available_tags = $this->collect_filter_tags( $items );
		$search         = isset( $_GET[ $search_key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $search_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tag            = isset( $_GET[ $tag_key ] ) ? sanitize_title( wp_unslash( (string) $_GET[ $tag_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $tag && ! isset( $available_tags[ $tag ] ) ) {
			$tag = '';
		}

		return [
			'search_key'     => $search_key,
			'tag_key'        => $tag_key,
			'search'         => $search,
			'tag'            => $tag,
			'available_tags' => $available_tags,
			'active'         => '' !== $search || '' !== $tag,
			'total_count'    => count( $items ),
			'filtered_count' => count( $items ),
		];
	}

	/**
	 * Applies frontend search and tag filters.
	 *
	 * @param array<int, array<string, mixed>> $items        Gallery items.
	 * @param array<string, mixed>             $filter_state Filter state.
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_frontend_filters( array $items, array $filter_state ): array {
		$search = strtolower( trim( (string) ( $filter_state['search'] ?? '' ) ) );
		$tag    = sanitize_title( (string) ( $filter_state['tag'] ?? '' ) );

		if ( '' === $search && '' === $tag ) {
			return $items;
		}

		return array_values(
			array_filter(
				$items,
				function ( array $item ) use ( $search, $tag ): bool {
					if ( '' !== $tag ) {
						$item_tags = $this->extract_item_filter_tags( $item );

						if ( ! in_array( $tag, array_keys( $item_tags ), true ) ) {
							return false;
						}
					}

					if ( '' === $search ) {
						return true;
					}

					$attachment = isset( $item['attachment'] ) && is_array( $item['attachment'] ) ? $item['attachment'] : [];
					$haystack   = strtolower(
						implode(
							' ',
							array_filter(
								[
									(string) ( $item['item_title'] ?? '' ),
									(string) ( $item['item_caption'] ?? '' ),
									(string) ( $item['item_alt'] ?? '' ),
									(string) ( $item['item_tags'] ?? '' ),
									(string) ( $item['gallery_title'] ?? '' ),
									(string) ( $attachment['title'] ?? '' ),
									(string) ( $attachment['filename'] ?? '' ),
								]
							)
						)
					);

					return false !== strpos( $haystack, $search );
				}
			)
		);
	}

	/**
	 * Renders the frontend filter form for gallery grids.
	 *
	 * @param array<string, mixed> $filter_state Filter state.
	 * @param string               $page_key     Pagination query key.
	 * @return string
	 */
	private function render_gallery_filters( array $filter_state, string $page_key ): string {
		$base_url   = remove_query_arg( array_keys( $_GET ), $this->current_request_url() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$clear_url  = remove_query_arg(
			[
				(string) ( $filter_state['search_key'] ?? '' ),
				(string) ( $filter_state['tag_key'] ?? '' ),
				$page_key,
			],
			$this->current_request_url()
		);
		$hidden     = $this->render_hidden_query_fields(
			[
				(string) ( $filter_state['search_key'] ?? '' ),
				(string) ( $filter_state['tag_key'] ?? '' ),
				$page_key,
			]
		);
		$search     = (string) ( $filter_state['search'] ?? '' );
		$active_tag = (string) ( $filter_state['tag'] ?? '' );
		$tags       = isset( $filter_state['available_tags'] ) && is_array( $filter_state['available_tags'] ) ? $filter_state['available_tags'] : [];
		$filtered   = (int) ( $filter_state['filtered_count'] ?? 0 );
		$total      = (int) ( $filter_state['total_count'] ?? $filtered );

		ob_start();
		?>
		<form class="mlgp-gallery-filters" method="get" action="<?php echo esc_url( $base_url ); ?>">
			<?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="mlgp-gallery-filters__field mlgp-gallery-filters__field--search">
				<label for="<?php echo esc_attr( (string) $filter_state['search_key'] ); ?>"><?php esc_html_e( 'Buscar nesta galeria', 'ml-gallery-pro' ); ?></label>
				<input id="<?php echo esc_attr( (string) $filter_state['search_key'] ); ?>" type="search" name="<?php echo esc_attr( (string) $filter_state['search_key'] ); ?>" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por titulo, legenda ou tag...', 'ml-gallery-pro' ); ?>">
			</div>
			<?php if ( ! empty( $tags ) ) : ?>
				<div class="mlgp-gallery-filters__field">
					<label for="<?php echo esc_attr( (string) $filter_state['tag_key'] ); ?>"><?php esc_html_e( 'Tag', 'ml-gallery-pro' ); ?></label>
					<select id="<?php echo esc_attr( (string) $filter_state['tag_key'] ); ?>" name="<?php echo esc_attr( (string) $filter_state['tag_key'] ); ?>">
						<option value=""><?php esc_html_e( 'Todas as tags', 'ml-gallery-pro' ); ?></option>
						<?php foreach ( $tags as $tag_slug => $tag_name ) : ?>
							<option value="<?php echo esc_attr( (string) $tag_slug ); ?>" <?php selected( $active_tag, $tag_slug ); ?>><?php echo esc_html( (string) $tag_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>
			<div class="mlgp-gallery-filters__actions">
				<button type="submit" class="mlgp-gallery-filters__button"><?php esc_html_e( 'Filtrar', 'ml-gallery-pro' ); ?></button>
				<?php if ( ! empty( $filter_state['active'] ) ) : ?>
					<a class="mlgp-gallery-filters__link" href="<?php echo esc_url( $clear_url ); ?>"><?php esc_html_e( 'Limpar', 'ml-gallery-pro' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="mlgp-gallery-filters__summary">
				<?php
				echo esc_html(
					sprintf(
						_n( '%1$d imagem pronta para exibicao', '%1$d imagens prontas para exibicao', $filtered, 'ml-gallery-pro' ),
						$filtered
					)
				);
				if ( $filtered !== $total ) {
					echo ' ';
					echo esc_html(
						sprintf(
							/* translators: %d: total images before filtering. */
							__( 'de %d no total', 'ml-gallery-pro' ),
							$total
						)
					);
				}
				?>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Builds one stable query arg name for frontend filters.
	 *
	 * @param string $type    Filter type.
	 * @param string $context Render context.
	 * @param string $suffix  Unique suffix.
	 * @return string
	 */
	private function build_filter_key( string $type, string $context, string $suffix ): string {
		return sanitize_key( 'mlgp_filter_' . $type . '_' . $context . '_' . $suffix );
	}

	/**
	 * Collects all available tags from gallery items for frontend filters.
	 *
	 * @param array<int, array<string, mixed>> $items Gallery items.
	 * @return array<string, string>
	 */
	private function collect_filter_tags( array $items ): array {
		$tags = [];

		foreach ( $items as $item ) {
			foreach ( $this->extract_item_filter_tags( $item ) as $tag_slug => $tag_name ) {
				$tags[ $tag_slug ] = $tag_name;
			}
		}

		natcasesort( $tags );

		return $tags;
	}

	/**
	 * Extracts normalized tag pairs from one gallery item.
	 *
	 * @param array<string, mixed> $item Gallery item.
	 * @return array<string, string>
	 */
	private function extract_item_filter_tags( array $item ): array {
		$raw_tags = [];

		if ( ! empty( $item['tag_list'] ) && is_array( $item['tag_list'] ) ) {
			$raw_tags = $item['tag_list'];
		} elseif ( ! empty( $item['item_tags'] ) ) {
			$raw_tags = preg_split( '/[\r\n,;|]+/', (string) $item['item_tags'] ) ?: [];
		}

		$tags = [];

		foreach ( $raw_tags as $raw_tag ) {
			$tag_name = sanitize_text_field( trim( (string) $raw_tag ) );
			$tag_slug = sanitize_title( $tag_name );

			if ( '' === $tag_name || '' === $tag_slug ) {
				continue;
			}

			$tags[ $tag_slug ] = $tag_name;
		}

		return $tags;
	}

	/**
	 * Preserves unrelated query args when using the gallery filter form.
	 *
	 * @param array<int, string> $exclude_keys Keys to ignore.
	 * @return string
	 */
	private function render_hidden_query_fields( array $exclude_keys ): string {
		if ( empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		ob_start();

		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$sanitized_key = sanitize_key( (string) $key );

			if ( '' === $sanitized_key || in_array( $sanitized_key, $exclude_keys, true ) ) {
				continue;
			}

			printf(
				'<input type="hidden" name="%1$s" value="%2$s">',
				esc_attr( $sanitized_key ),
				esc_attr( sanitize_text_field( wp_unslash( (string) $value ) ) )
			);
		}

		return (string) ob_get_clean();
	}

	/**
	 * Renders one gallery opened from an album collection view.
	 *
	 * @param int                  $root_album_id Root album ID.
	 * @param int                  $gallery_id    Gallery ID.
	 * @param array<int, array<string, mixed>> $trail Breadcrumb album trail.
	 * @param array<string, mixed> $atts         Shortcode attributes.
	 * @param array<string, mixed> $album_config Album config.
	 * @return string
	 */
	private function render_album_gallery_view( int $root_album_id, int $gallery_id, array $trail, array $atts, array $album_config ): string {
		$back_album_id = (int) ( end( $trail )['id'] ?? $root_album_id );
		$back_url      = $this->build_album_item_url( $root_album_id, 'album', $back_album_id );
		$gallery_html  = $this->render_gallery( $gallery_id, $atts );
		$settings      = $this->repository->get_settings();
		$button_html   = $this->render_album_navigation_button( $back_url, $settings );
		$button_pos    = $this->sanitize_album_navigation_position( $settings['album_nav_button_position'] ?? 'top' );
		$wrapper_style = $this->build_wrapper_style(
			[
				'columns_desktop'      => 3,
				'columns_tablet'       => 2,
				'columns_mobile'       => 1,
				'gap'                  => 18,
				'rounded_corners'      => $album_config['rounded_corners'],
				'justified_row_height' => 220,
			]
		);

		if ( '' === $gallery_html ) {
			return '';
		}

		ob_start();
		?>
		<div class="mlgp-frontend mlgp-collection-view" style="<?php echo esc_attr( $wrapper_style ); ?>">
			<?php echo $this->render_album_breadcrumb( $root_album_id, $trail, $gallery_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo in_array( $button_pos, [ 'top', 'both' ], true ) ? $button_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $gallery_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo in_array( $button_pos, [ 'bottom', 'both' ], true ) ? $button_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the album collection navigation button.
	 *
	 * @param string               $back_url Back URL.
	 * @param array<string, mixed> $settings Global settings.
	 * @return string
	 */
	private function render_album_navigation_button( string $back_url, array $settings ): string {
		if ( empty( $settings['album_nav_button_enabled'] ) ) {
			return '';
		}

		$align   = $this->sanitize_album_navigation_align( $settings['album_nav_button_align'] ?? 'left' );
		$justify = [
			'left'   => 'flex-start',
			'center' => 'center',
			'right'  => 'flex-end',
		][ $align ];

		$button_styles = [];
		$bg_color      = sanitize_hex_color( (string) ( $settings['album_nav_button_bg_color'] ?? '' ) );
		$text_color    = sanitize_hex_color( (string) ( $settings['album_nav_button_text_color'] ?? '' ) );
		$border_color  = sanitize_hex_color( (string) ( $settings['album_nav_button_border_color'] ?? '' ) );
		$hover_bg      = sanitize_hex_color( (string) ( $settings['album_nav_button_hover_bg_color'] ?? '' ) );
		$hover_text    = sanitize_hex_color( (string) ( $settings['album_nav_button_hover_text_color'] ?? '' ) );

		if ( is_string( $bg_color ) && '' !== $bg_color ) {
			$button_styles[] = '--mlgp-album-nav-bg:' . $bg_color;
		}

		if ( is_string( $text_color ) && '' !== $text_color ) {
			$button_styles[] = '--mlgp-album-nav-color:' . $text_color;
		}

		if ( is_string( $border_color ) && '' !== $border_color ) {
			$button_styles[] = '--mlgp-album-nav-border:' . $border_color;
		}

		if ( is_string( $hover_bg ) && '' !== $hover_bg ) {
			$button_styles[] = '--mlgp-album-nav-hover-bg:' . $hover_bg;
			$button_styles[] = '--mlgp-album-nav-hover-border:' . $hover_bg;
		}

		if ( is_string( $hover_text ) && '' !== $hover_text ) {
			$button_styles[] = '--mlgp-album-nav-hover-color:' . $hover_text;
		}

		$button_style = implode( ';', $button_styles );
		$label        = __( 'Voltar para a coleção', 'ml-gallery-pro' );

		ob_start();
		?>
		<div class="mlgp-collection-view__toolbar mlgp-collection-view__toolbar--<?php echo esc_attr( $align ); ?>" style="<?php echo esc_attr( 'display:flex;justify-content:' . $justify ); ?>">
			<a class="mlgp-collection-back mlgp-collection-back--configurable" href="<?php echo esc_url( $back_url ); ?>"<?php echo '' !== $button_style ? ' style="' . esc_attr( $button_style ) . '"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Sanitizes album navigation alignment.
	 *
	 * @param mixed $align Raw alignment.
	 * @return string
	 */
	private function sanitize_album_navigation_align( $align ): string {
		$align = sanitize_key( (string) $align );

		return in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : 'left';
	}

	/**
	 * Sanitizes album navigation position.
	 *
	 * @param mixed $position Raw position.
	 * @return string
	 */
	private function sanitize_album_navigation_position( $position ): string {
		$position = sanitize_key( (string) $position );

		return in_array( $position, [ 'top', 'bottom', 'both' ], true ) ? $position : 'top';
	}

	/**
	 * Renders one slideshow gallery.
	 *
	 * @param array<int, array<string, mixed>> $items  Gallery items.
	 * @param array<string, mixed>             $config Render config.
	 * @return string
	 */
	private function render_slideshow_gallery( array $items, array $config ): string {
		$show_arrows = ! empty( $config['slideshow_show_arrows'] ) && count( $items ) > 1;
		$show_thumbs = ! empty( $config['slideshow_show_thumbs'] ) && count( $items ) > 1;
		$prev_icon   = $this->render_nav_button_inner( (string) ( $config['nav_arrow_prev_url'] ?? '' ), 'prev' );
		$next_icon   = $this->render_nav_button_inner( (string) ( $config['nav_arrow_next_url'] ?? '' ), 'next' );

		ob_start();
		?>
		<div class="mlgp-slideshow<?php echo $show_thumbs ? '' : ' mlgp-slideshow--compact'; ?>" data-mlgp-slideshow="1" data-autoplay="<?php echo esc_attr( $config['slideshow_autoplay'] ? '1' : '0' ); ?>" data-interval="<?php echo esc_attr( (string) $config['slideshow_interval'] ); ?>">
			<div class="mlgp-slideshow__viewport">
				<?php if ( $show_arrows ) : ?>
					<button type="button" class="mlgp-nav-button mlgp-slideshow__nav mlgp-slideshow__nav--prev" data-mlgp-slide-prev="1" aria-label="<?php esc_attr_e( 'Slide anterior', 'ml-gallery-pro' ); ?>"><?php echo $prev_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php endif; ?>
				<?php foreach ( $items as $index => $item ) : ?>
					<article class="mlgp-slide <?php echo 0 === $index ? 'is-active' : ''; ?>" data-mlgp-slide="<?php echo esc_attr( (string) $index ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
						<?php echo $this->render_item_media( $item, $config, 'large', 'mlgp-slide__media' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_item_content( $item, $config, 'mlgp-slide__content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
				<?php endforeach; ?>
				<?php if ( $show_arrows ) : ?>
					<button type="button" class="mlgp-nav-button mlgp-slideshow__nav mlgp-slideshow__nav--next" data-mlgp-slide-next="1" aria-label="<?php esc_attr_e( 'Proximo slide', 'ml-gallery-pro' ); ?>"><?php echo $next_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php endif; ?>
			</div>
			<?php if ( $show_thumbs ) : ?>
				<div class="mlgp-slideshow__footer">
					<div class="mlgp-slideshow__thumbbar<?php echo $show_arrows ? '' : ' mlgp-slideshow__thumbbar--bare'; ?>" data-mlgp-slide-thumbbar="1">
						<?php if ( $show_arrows ) : ?>
							<button type="button" class="mlgp-nav-button mlgp-slideshow__thumb-nav mlgp-slideshow__thumb-nav--prev" data-mlgp-slide-thumb-prev="1" aria-label="<?php esc_attr_e( 'Miniaturas anteriores', 'ml-gallery-pro' ); ?>"><?php echo $prev_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
						<div class="mlgp-slideshow__thumbs-viewport">
							<div class="mlgp-slideshow__thumbs" data-mlgp-slide-thumb-track="1">
								<?php foreach ( $items as $index => $item ) : ?>
									<?php
									$media     = isset( $item['attachment'] ) && is_array( $item['attachment'] ) ? $item['attachment'] : [];
									$thumb_url = ! empty( $media['thumb_url'] ) ? (string) $media['thumb_url'] : (string) ( ! empty( $media['large_url'] ) ? $media['large_url'] : ( $media['medium_url'] ?? '' ) );
									$thumb_alt = (string) ( $item['item_alt'] ?? $media['alt'] ?? $media['title'] ?? '' );
									?>
									<button type="button" class="mlgp-slideshow__thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-mlgp-slide-thumb="<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Ir para slide %d', $index + 1 ) ); ?>">
										<?php if ( $thumb_url ) : ?>
											<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $thumb_alt ); ?>">
										<?php else : ?>
											<span><?php echo esc_html( sprintf( '%d', $index + 1 ) ); ?></span>
										<?php endif; ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
						<?php if ( $show_arrows ) : ?>
							<button type="button" class="mlgp-nav-button mlgp-slideshow__thumb-nav mlgp-slideshow__thumb-nav--next" data-mlgp-slide-thumb-next="1" aria-label="<?php esc_attr_e( 'Proximas miniaturas', 'ml-gallery-pro' ); ?>"><?php echo $next_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders one filmstrip gallery.
	 *
	 * @param array<int, array<string, mixed>> $items  Gallery items.
	 * @param array<string, mixed>             $config Render config.
	 * @return string
	 */
	private function render_filmstrip_gallery( array $items, array $config ): string {
		$show_arrows = ! empty( $config['slideshow_show_arrows'] ) && count( $items ) > 1;
		$show_strip  = count( $items ) > 1;
		$prev_icon   = $this->render_nav_button_inner( (string) ( $config['nav_arrow_prev_url'] ?? '' ), 'prev' );
		$next_icon   = $this->render_nav_button_inner( (string) ( $config['nav_arrow_next_url'] ?? '' ), 'next' );

		ob_start();
		?>
		<div class="mlgp-slideshow mlgp-filmstrip<?php echo $show_strip ? '' : ' mlgp-filmstrip--single'; ?>" data-mlgp-slideshow="1" data-autoplay="0" data-interval="<?php echo esc_attr( (string) $config['slideshow_interval'] ); ?>">
			<div class="mlgp-slideshow__viewport">
				<?php if ( $show_arrows ) : ?>
					<button type="button" class="mlgp-nav-button mlgp-slideshow__nav mlgp-slideshow__nav--prev" data-mlgp-slide-prev="1" aria-label="<?php esc_attr_e( 'Slide anterior', 'ml-gallery-pro' ); ?>"><?php echo $prev_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php endif; ?>
				<?php foreach ( $items as $index => $item ) : ?>
					<article class="mlgp-slide <?php echo 0 === $index ? 'is-active' : ''; ?>" data-mlgp-slide="<?php echo esc_attr( (string) $index ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
						<?php echo $this->render_item_media( $item, $config, 'large', 'mlgp-slide__media' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_item_content( $item, $config, 'mlgp-slide__content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
				<?php endforeach; ?>
				<?php if ( $show_arrows ) : ?>
					<button type="button" class="mlgp-nav-button mlgp-slideshow__nav mlgp-slideshow__nav--next" data-mlgp-slide-next="1" aria-label="<?php esc_attr_e( 'Proximo slide', 'ml-gallery-pro' ); ?>"><?php echo $next_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php endif; ?>
			</div>
			<?php if ( $show_strip ) : ?>
				<div class="mlgp-slideshow__footer">
					<div class="mlgp-slideshow__thumbbar<?php echo $show_arrows ? '' : ' mlgp-slideshow__thumbbar--bare'; ?>" data-mlgp-slide-thumbbar="1">
						<?php if ( $show_arrows ) : ?>
							<button type="button" class="mlgp-nav-button mlgp-slideshow__thumb-nav mlgp-slideshow__thumb-nav--prev" data-mlgp-slide-thumb-prev="1" aria-label="<?php esc_attr_e( 'Miniaturas anteriores', 'ml-gallery-pro' ); ?>"><?php echo $prev_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
						<div class="mlgp-slideshow__thumbs-viewport">
							<div class="mlgp-slideshow__thumbs" data-mlgp-slide-thumb-track="1">
								<?php foreach ( $items as $index => $item ) : ?>
									<?php
									$media     = isset( $item['attachment'] ) && is_array( $item['attachment'] ) ? $item['attachment'] : [];
									$thumb_url = ! empty( $media['thumb_url'] ) ? (string) $media['thumb_url'] : (string) ( ! empty( $media['large_url'] ) ? $media['large_url'] : ( $media['medium_url'] ?? '' ) );
									$thumb_alt = (string) ( $item['item_alt'] ?? $media['alt'] ?? $media['title'] ?? '' );
									?>
									<button type="button" class="mlgp-slideshow__thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-mlgp-slide-thumb="<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Ir para slide %d', $index + 1 ) ); ?>">
										<?php if ( $thumb_url ) : ?>
											<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $thumb_alt ); ?>">
										<?php else : ?>
											<span><?php echo esc_html( sprintf( '%d', $index + 1 ) ); ?></span>
										<?php endif; ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
						<?php if ( $show_arrows ) : ?>
							<button type="button" class="mlgp-nav-button mlgp-slideshow__thumb-nav mlgp-slideshow__thumb-nav--next" data-mlgp-slide-thumb-next="1" aria-label="<?php esc_attr_e( 'Proximas miniaturas', 'ml-gallery-pro' ); ?>"><?php echo $next_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders one image browser gallery.
	 *
	 * @param array<int, array<string, mixed>> $items  Gallery items.
	 * @param array<string, mixed>             $config Render config.
	 * @return string
	 */
	private function render_imagebrowser_gallery( array $items, array $config ): string {
		$prev_icon = $this->render_nav_button_inner( (string) ( $config['nav_arrow_prev_url'] ?? '' ), 'prev' );
		$next_icon = $this->render_nav_button_inner( (string) ( $config['nav_arrow_next_url'] ?? '' ), 'next' );

		ob_start();
		?>
		<div class="mlgp-imagebrowser" data-mlgp-imagebrowser="1">
			<div class="mlgp-imagebrowser__stage">
				<?php foreach ( $items as $index => $item ) : ?>
					<article class="mlgp-imagebrowser__slide <?php echo 0 === $index ? 'is-active' : ''; ?>" data-mlgp-browser-slide="<?php echo esc_attr( (string) $index ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
						<?php echo $this->render_item_media( $item, $config, 'large', 'mlgp-imagebrowser__media' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_item_content( $item, $config, 'mlgp-imagebrowser__content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
				<?php endforeach; ?>
			</div>
			<div class="mlgp-imagebrowser__footer">
				<div class="mlgp-imagebrowser__thumbs">
					<?php foreach ( $items as $index => $item ) : ?>
						<?php
						$media     = isset( $item['attachment'] ) && is_array( $item['attachment'] ) ? $item['attachment'] : [];
						$thumb_url = ! empty( $media['thumb_url'] ) ? (string) $media['thumb_url'] : (string) ( ! empty( $media['large_url'] ) ? $media['large_url'] : ( $media['medium_url'] ?? '' ) );
						?>
						<button type="button" class="mlgp-imagebrowser__thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-mlgp-browser-thumb="<?php echo esc_attr( (string) $index ); ?>">
							<?php if ( $thumb_url ) : ?>
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( (string) ( $item['item_alt'] ?? $media['alt'] ?? $media['title'] ?? '' ) ); ?>">
							<?php else : ?>
								<span><?php echo esc_html( sprintf( '%d', $index + 1 ) ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="mlgp-imagebrowser__nav">
					<button type="button" class="mlgp-nav-button" data-mlgp-browser-prev="1" aria-label="<?php esc_attr_e( 'Imagem anterior', 'ml-gallery-pro' ); ?>"><?php echo $prev_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					<button type="button" class="mlgp-nav-button" data-mlgp-browser-next="1" aria-label="<?php esc_attr_e( 'Proxima imagem', 'ml-gallery-pro' ); ?>"><?php echo $next_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the inner visual for navigation buttons.
	 *
	 * @param string $icon_url  Optional custom icon URL.
	 * @param string $direction Arrow direction.
	 * @return string
	 */
	private function render_nav_button_inner( string $icon_url, string $direction ): string {
		$icon_url = esc_url_raw( trim( $icon_url ) );

		if ( '' !== $icon_url ) {
			return sprintf(
				'<span class="mlgp-nav-button__icon" aria-hidden="true"><img src="%1$s" alt=""></span>',
				esc_url( $icon_url )
			);
		}

		$path = 'prev' === $direction ? 'M14.5 5.5L8 12l6.5 6.5' : 'M9.5 5.5L16 12l-6.5 6.5';

		return sprintf(
			'<span class="mlgp-nav-button__icon mlgp-nav-button__icon--svg" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="%1$s" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"/></svg></span>',
			esc_attr( $path )
		);
	}

	/**
	 * Renders one item's text block.
	 *
	 * @param array<string, mixed> $item          Gallery item.
	 * @param array<string, mixed> $config        Render config.
	 * @param string               $content_class Wrapper class.
	 * @return string
	 */
	private function render_item_content( array $item, array $config, string $content_class ): string {
		$raw_title   = ! empty( $item['item_title'] ) ? (string) $item['item_title'] : '';
		$raw_caption = ! empty( $item['item_caption'] ) ? (string) $item['item_caption'] : '';
		$tags        = ! empty( $config['show_item_tags'] ) ? $this->render_item_tags( (array) ( $item['tag_list'] ?? [] ) ) : '';
		$hide_titles = ! empty( $config['hide_all_titles'] );
		$source      = ! $hide_titles && ! empty( $config['show_source_gallery'] ) && ! empty( $item['gallery_title'] )
			? (string) $item['gallery_title']
			: '';
		$title       = ! $hide_titles && ! empty( $config['show_titles'] ) ? $raw_title : '';
		$caption     = ! empty( $config['show_captions'] ) ? $raw_caption : '';

		if ( '' === $title && '' === $caption && '' === $tags && '' === $source ) {
			return '';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $content_class ); ?>">
			<?php if ( '' !== $source ) : ?>
				<div class="mlgp-card__source"><?php echo esc_html( $source ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $tags ) ) : ?>
				<?php echo $tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h3><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<?php if ( '' !== $caption ) : ?>
				<div class="mlgp-card__caption"><?php echo wp_kses_post( wpautop( $caption ) ); ?></div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders tag chips for one item.
	 *
	 * @param array<int, string> $tags Tag names.
	 * @return string
	 */
	private function render_item_tags( array $tags ): string {
		if ( empty( $tags ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="mlgp-tag-list">
			<?php foreach ( $tags as $tag ) : ?>
				<span class="mlgp-tag"><?php echo esc_html( $tag ); ?></span>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders one media wrapper for a gallery item.
	 *
	 * @param array<string, mixed> $item        Gallery item.
	 * @param array<string, mixed> $config      Render config.
	 * @param string               $size        Size key.
	 * @param string               $media_class Wrapper class.
	 * @return string
	 */
	private function render_item_media( array $item, array $config, string $size, string $media_class ): string {
		$media        = isset( $item['attachment'] ) && is_array( $item['attachment'] ) ? $item['attachment'] : null;
		$image_markup = $media ? $this->render_media_image( $media, $item, $config, $size ) : '';
		$image_url    = $media['full_url'] ?? '';
		$caption      = ! empty( $item['item_caption'] ) ? wp_strip_all_tags( (string) $item['item_caption'] ) : '';
		$item_link    = ! empty( $item['item_link'] ) ? esc_url( (string) $item['item_link'] ) : '';
		$wrapper_url  = ! empty( $config['enable_lightbox'] ) ? $image_url : $item_link;

		if ( '' === $image_markup ) {
			return '';
		}

		if ( $wrapper_url ) {
			return sprintf(
				'<a href="%1$s" class="%2$s" %3$s data-caption="%4$s">%5$s</a>',
				esc_url( $wrapper_url ),
				esc_attr( $media_class ),
				! empty( $config['enable_lightbox'] ) ? 'data-mlgp-lightbox="1"' : '',
				esc_attr( $caption ),
				$image_markup
			);
		}

		return sprintf(
			'<div class="%1$s">%2$s</div>',
			esc_attr( $media_class ),
			$image_markup
		);
	}

	/**
	 * Renders one image tag from a normalized media payload.
	 *
	 * @param array<string, mixed> $media    Media payload.
	 * @param array<string, mixed> $item     Gallery item data.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param string               $size     Size key.
	 * @return string
	 */
	private function render_media_image( array $media, array $item, array $settings, string $size = 'large' ): string {
		$size_key = $size . '_url';
		$src      = ! empty( $media[ $size_key ] ) ? (string) $media[ $size_key ] : (string) ( $media['full_url'] ?? '' );

		if ( '' === $src ) {
			return '';
		}

		$alt = ! empty( $item['item_alt'] )
			? (string) $item['item_alt']
			: (string) ( $media['alt'] ?? $media['title'] ?? '' );

		return sprintf(
			'<img src="%1$s" alt="%2$s" loading="%3$s">',
			esc_url( $src ),
			esc_attr( $alt ),
			! empty( $settings['enable_lazy_load'] ) ? 'lazy' : 'eager'
		);
	}

	/**
	 * Renders the breadcrumb for album navigation.
	 *
	 * @param int                            $root_album_id Root album ID.
	 * @param array<int, array<string,mixed>> $trail         Album trail.
	 * @param int                            $gallery_id    Optional gallery ID.
	 * @return string
	 */
	private function render_album_breadcrumb( int $root_album_id, array $trail, int $gallery_id = 0 ): string {
		if ( empty( $trail ) || ( count( $trail ) <= 1 && $gallery_id <= 0 ) ) {
			return '';
		}

		ob_start();
		?>
		<nav class="mlgp-collection-breadcrumb" aria-label="<?php esc_attr_e( 'Navegação da coleção', 'ml-gallery-pro' ); ?>">
			<?php foreach ( $trail as $index => $album ) : ?>
				<?php
				$album_id = (int) ( $album['id'] ?? 0 );
				$is_last  = $index === array_key_last( $trail ) && $gallery_id <= 0;
				?>
				<?php if ( $index > 0 ) : ?>
					<span class="mlgp-collection-breadcrumb__sep">&rsaquo;</span>
				<?php endif; ?>
				<?php if ( $is_last ) : ?>
					<span class="mlgp-collection-breadcrumb__current"><?php echo esc_html( (string) ( $album['title'] ?? '' ) ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( $this->build_album_item_url( $root_album_id, 'album', $album_id ) ); ?>"><?php echo esc_html( (string) ( $album['title'] ?? '' ) ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $gallery_id > 0 ) : ?>
				<?php $gallery = $this->repository->get_gallery( $gallery_id ); ?>
				<?php if ( ! empty( $gallery ) ) : ?>
					<span class="mlgp-collection-breadcrumb__sep">&rsaquo;</span>
					<span class="mlgp-collection-breadcrumb__current"><?php echo esc_html( (string) $gallery['title'] ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</nav>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Resolves the current album view token.
	 *
	 * @param int $root_album_id Root album ID.
	 * @return array<string, mixed>
	 */
	private function resolve_album_view( int $root_album_id ): array {
		$query_key = $this->build_album_view_key( $root_album_id );
		$raw       = isset( $_GET[ $query_key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( preg_match( '/^(album|gallery)-(\d+)$/', $raw, $matches ) ) {
			return [
				'type' => $matches[1],
				'id'   => absint( $matches[2] ),
			];
		}

		return [
			'type' => 'album',
			'id'   => $root_album_id,
		];
	}

	/**
	 * Builds the album view query key.
	 *
	 * @param int $root_album_id Root album ID.
	 * @return string
	 */
	private function build_album_view_key( int $root_album_id ): string {
		return sanitize_key( 'mlgp_album_view_' . $root_album_id );
	}

	/**
	 * Builds one URL for album navigation.
	 *
	 * @param int    $root_album_id Root album ID.
	 * @param string $type          Item type.
	 * @param int    $item_id       Item ID.
	 * @return string
	 */
	private function build_album_item_url( int $root_album_id, string $type, int $item_id ): string {
		$query_key = $this->build_album_view_key( $root_album_id );
		$base_url  = remove_query_arg( $query_key, $this->current_request_url() );

		if ( 'album' === $type && $item_id === $root_album_id ) {
			return $base_url;
		}

		return add_query_arg( $query_key, sanitize_key( $type ) . '-' . absint( $item_id ), $base_url );
	}

	/**
	 * Finds the breadcrumb path for one nested album/gallery.
	 *
	 * @param int                $album_id     Current album ID.
	 * @param string             $target_type  Target type.
	 * @param int                $target_id    Target item ID.
	 * @param array<int, array<string,mixed>> $trail      Current trail.
	 * @param array<int, int>    $visited      Visited album IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_album_path( int $album_id, string $target_type, int $target_id, array $trail = [], array $visited = [] ): array {
		if ( in_array( $album_id, $visited, true ) ) {
			return [];
		}

		$current_album = $this->repository->get_album( $album_id );

		if ( empty( $current_album ) ) {
			return [];
		}

		$visited[] = $album_id;
		$trail[]   = $current_album;

		if ( 'album' === $target_type && $album_id === $target_id ) {
			return $trail;
		}

		foreach ( $this->repository->get_album_items( $album_id ) as $item ) {
			$item_type = (string) ( $item['item_type'] ?? 'gallery' );
			$item_id   = (int) ( $item['item_id'] ?? 0 );

			if ( $item_id <= 0 ) {
				continue;
			}

			if ( 'gallery' === $target_type && 'gallery' === $item_type && $item_id === $target_id ) {
				return $trail;
			}

			if ( 'album' === $item_type ) {
				$path = $this->find_album_path( $item_id, $target_type, $target_id, $trail, $visited );

				if ( ! empty( $path ) ) {
					return $path;
				}
			}
		}

		return [];
	}

	/**
	 * Returns preview tiles for album cards in Grid Plus mode.
	 *
	 * @param string $item_type Album child type.
	 * @param int    $item_id   Child entity ID.
	 * @param int    $limit     Maximum number of tiles.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_album_item_preview_tiles( string $item_type, int $item_id, int $limit = 6 ): array {
		$limit = max( 1, min( 6, $limit ) );

		if ( 'album' === $item_type ) {
			return $this->collect_album_preview_tiles_recursive( $item_id, $limit, [] );
		}

		$editor = $this->repository->get_gallery_editor( $item_id );

		if ( empty( $editor['items'] ) || ! is_array( $editor['items'] ) ) {
			return [];
		}

		$tiles = [];

		foreach ( $editor['items'] as $editor_item ) {
			if ( ! isset( $editor_item['attachment'] ) || ! is_array( $editor_item['attachment'] ) ) {
				continue;
			}

			$tiles[] = $editor_item['attachment'];

			if ( count( $tiles ) >= $limit ) {
				break;
			}
		}

		return $tiles;
	}

	/**
	 * Recursively collects preview tiles from nested album items.
	 *
	 * @param int        $album_id Album ID.
	 * @param int        $limit    Maximum number of tiles.
	 * @param array<int> $visited  Visited album IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_album_preview_tiles_recursive( int $album_id, int $limit, array $visited ): array {
		if ( $album_id <= 0 || in_array( $album_id, $visited, true ) ) {
			return [];
		}

		$visited[] = $album_id;
		$tiles     = [];

		foreach ( $this->repository->get_album_items( $album_id ) as $album_item ) {
			$child_type = sanitize_key( (string) ( $album_item['item_type'] ?? 'gallery' ) );
			$child_id   = (int) ( $album_item['item_id'] ?? 0 );

			if ( $child_id <= 0 ) {
				continue;
			}

			$batch = 'album' === $child_type
				? $this->collect_album_preview_tiles_recursive( $child_id, $limit - count( $tiles ), $visited )
				: $this->get_album_item_preview_tiles( 'gallery', $child_id, $limit - count( $tiles ) );

			if ( ! empty( $batch ) ) {
				$tiles = array_merge( $tiles, $batch );
			}

			if ( count( $tiles ) >= $limit ) {
				break;
			}
		}

		return array_slice( $tiles, 0, $limit );
	}

	/**
	 * Sanitizes a display type value.
	 *
	 * @param string $display_type Requested display type.
	 * @return string
	 */
	private function sanitize_display_type( string $display_type ): string {
		$display_type = sanitize_key( $display_type );
		$allowed      = [ 'grid', 'tile', 'mosaic', 'masonry', 'justified', 'slideshow', 'filmstrip', 'imagebrowser' ];

		return in_array( $display_type, $allowed, true ) ? $display_type : 'grid';
	}

	/**
	 * Sanitizes album display modes.
	 *
	 * @param string $display_type Requested album display type.
	 * @return string
	 */
	private function sanitize_album_display_type( string $display_type ): string {
		$display_type = sanitize_key( $display_type );

		if ( in_array( $display_type, [ 'compact', 'extended' ], true ) ) {
			return 'grid';
		}

		$allowed = [ 'grid', 'grid_plus', 'masonry', 'mosaic', 'tile', 'justified' ];

		return in_array( $display_type, $allowed, true ) ? $display_type : 'grid';
	}

	/**
	 * Sanitizes the album cover fit mode.
	 *
	 * @param string $fit Requested fit mode.
	 * @return string
	 */
	private function sanitize_album_cover_fit( string $fit ): string {
		$fit = sanitize_key( $fit );

		return in_array( $fit, [ 'contain', 'cover' ], true ) ? $fit : 'contain';
	}

	/**
	 * Builds one inline style string for grid-like cards.
	 *
	 * @param array<string, mixed> $item   Gallery item.
	 * @param array<string, mixed> $config Render config.
	 * @return string
	 */
	private function build_item_style( array $item, array $config ): string {
		if ( 'justified' !== ( $config['display_type'] ?? 'grid' ) ) {
			return '';
		}

		$attachment = isset( $item['attachment'] ) && is_array( $item['attachment'] ) ? $item['attachment'] : [];
		$width      = (int) ( $attachment['width'] ?? 0 );
		$height     = (int) ( $attachment['height'] ?? 0 );
		$ratio      = ( $width > 0 && $height > 0 ) ? round( $width / max( 1, $height ), 4 ) : 1.3333;

		return '--mlgp-item-ratio:' . $ratio . ';';
	}

	/**
	 * Sanitizes a bounded integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum value.
	 * @param int   $max   Maximum value.
	 * @return int
	 */
	private function sanitize_integer( $value, int $min, int $max ): int {
		$value = absint( $value );
		$value = max( $min, $value );

		return min( $max, $value );
	}

	/**
	 * Converts one HEX color plus opacity into RGBA.
	 *
	 * @param string $hex     HEX color.
	 * @param int    $opacity Opacity 0-100.
	 * @return string
	 */
	private function hex_to_rgba( string $hex, int $opacity ): string {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = preg_replace( '/(.)/', '$1$1', $hex );
		}

		if ( ! is_string( $hex ) || 6 !== strlen( $hex ) ) {
			return 'rgba(215, 224, 234, 1)';
		}

		$red   = hexdec( substr( $hex, 0, 2 ) );
		$green = hexdec( substr( $hex, 2, 2 ) );
		$blue  = hexdec( substr( $hex, 4, 2 ) );
		$alpha = max( 0, min( 100, $opacity ) ) / 100;

		return sprintf( 'rgba(%1$d, %2$d, %3$d, %4$s)', $red, $green, $blue, rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) ?: '0' );
	}

	/**
	 * Sanitizes a HEX color value.
	 *
	 * @param mixed  $value    Raw color.
	 * @param string $fallback Fallback color.
	 * @return string
	 */
	private function sanitize_color( $value, string $fallback ): string {
		$color = sanitize_hex_color( (string) $value );

		return is_string( $color ) && '' !== $color ? $color : $fallback;
	}

	/**
	 * Reads a boolean-like shortcode/settings value.
	 *
	 * @param mixed $value    Requested value.
	 * @param mixed $fallback Fallback value.
	 * @return int
	 */
	private function bool_value( $value, $fallback ): int {
		if ( '' === (string) $value ) {
			$value = $fallback;
		}

		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true ) ? 1 : 0;
	}

	/**
	 * Normalizes one tag filter string.
	 *
	 * @param string $value Raw tag filter string.
	 * @return array<int, string>
	 */
	private function normalize_tag_filters( string $value ): array {
		$parts = preg_split( '/[\r\n,;|]+/', $value ) ?: [];
		$tags  = [];

		foreach ( $parts as $part ) {
			$slug = sanitize_title( trim( (string) $part ) );

			if ( '' !== $slug && ! in_array( $slug, $tags, true ) ) {
				$tags[] = $slug;
			}
		}

		return $tags;
	}
}
