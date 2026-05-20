<?php

defined( 'ABSPATH' ) || exit;

use Rootstuff\Relationships\Registry;
use Rootstuff\Relationships\Relation;

$rootstuff_rel_type       = $attributes['relType'] ?? '';
$rootstuff_rel_layout     = $attributes['layout'] ?? 'list';
$rootstuff_rel_columns    = $attributes['columns'] ?? 3;
$rootstuff_rel_show_thumb = $attributes['showThumbnail'] ?? true;
$rootstuff_rel_show_exc   = $attributes['showExcerpt'] ?? false;

if ( empty( $rootstuff_rel_type ) || ! Registry::exists( $rootstuff_rel_type ) ) {
	return;
}

$rootstuff_rel_related = Relation::get( $rootstuff_rel_type, get_the_ID() );

if ( empty( $rootstuff_rel_related ) ) {
	return;
}

$rootstuff_rel_classes = [ 'rootstuff-rel-related-content', 'rootstuff-rel-layout-' . sanitize_html_class( $rootstuff_rel_layout ) ];
$rootstuff_rel_wrapper = get_block_wrapper_attributes( [ 'class' => implode( ' ', $rootstuff_rel_classes ) ] );
?>
<section <?php echo $rootstuff_rel_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns safe, pre-escaped HTML attributes. ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is the rendered InnerBlocks output, already escaped by render_block(). ?>

	<?php if ( 'grid' === $rootstuff_rel_layout ) : ?>
		<div class="rootstuff-rel-related-content__grid" style="--rootstuff-rel-columns: <?php echo (int) $rootstuff_rel_columns; ?>">
			<?php foreach ( $rootstuff_rel_related as $rootstuff_rel_item ) : ?>
				<div class="rootstuff-rel-related-content__card">
					<?php if ( $rootstuff_rel_show_thumb && has_post_thumbnail( $rootstuff_rel_item ) ) : ?>
						<a class="rootstuff-rel-related-content__thumb" href="<?php echo esc_url( get_permalink( $rootstuff_rel_item ) ); ?>">
							<?php echo get_the_post_thumbnail( $rootstuff_rel_item, 'medium', [ 'class' => 'rootstuff-rel-related-content__image' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns pre-escaped <img> HTML. ?>
						</a>
					<?php endif; ?>
					<a class="rootstuff-rel-related-content__link" href="<?php echo esc_url( get_permalink( $rootstuff_rel_item ) ); ?>">
						<?php echo esc_html( get_the_title( $rootstuff_rel_item ) ); ?>
					</a>
					<?php if ( $rootstuff_rel_show_exc && get_the_excerpt( $rootstuff_rel_item ) ) : ?>
						<p class="rootstuff-rel-related-content__excerpt"><?php echo esc_html( get_the_excerpt( $rootstuff_rel_item ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php elseif ( 'inline' === $rootstuff_rel_layout ) : ?>
		<p class="rootstuff-rel-related-content__inline">
			<?php
			$rootstuff_rel_links = array_map( function ( $rootstuff_rel_item ) {
				return sprintf(
					'<a class="rootstuff-rel-related-content__link" href="%s">%s</a>',
					esc_url( get_permalink( $rootstuff_rel_item ) ),
					esc_html( get_the_title( $rootstuff_rel_item ) )
				);
			}, $rootstuff_rel_related );
			echo implode( ', ', $rootstuff_rel_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rootstuff_rel_links members are built from esc_url() + esc_html() above.
			?>
		</p>

	<?php else : ?>
		<ul class="rootstuff-rel-related-content__list">
			<?php foreach ( $rootstuff_rel_related as $rootstuff_rel_item ) : ?>
				<li class="rootstuff-rel-related-content__item">
					<a class="rootstuff-rel-related-content__link" href="<?php echo esc_url( get_permalink( $rootstuff_rel_item ) ); ?>">
						<?php echo esc_html( get_the_title( $rootstuff_rel_item ) ); ?>
					</a>
					<?php if ( $rootstuff_rel_show_exc && get_the_excerpt( $rootstuff_rel_item ) ) : ?>
						<p class="rootstuff-rel-related-content__excerpt"><?php echo esc_html( get_the_excerpt( $rootstuff_rel_item ) ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
