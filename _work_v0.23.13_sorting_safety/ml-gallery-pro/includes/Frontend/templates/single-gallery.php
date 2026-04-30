<?php
/**
 * Canonical single gallery frontend template.
 *
 * @package MLGalleryPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $mlgp_routed_gallery;

$gallery    = is_array( $mlgp_routed_gallery ?? null ) ? $mlgp_routed_gallery : [];
$gallery_id = (int) ( $gallery['id'] ?? 0 );

if ( $gallery_id <= 0 ) {
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	return;
}

get_header();
?>
<main class="mlgp-route-page">
	<?php echo do_shortcode( sprintf( '[ml_gallery gallery="%d"]', $gallery_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
