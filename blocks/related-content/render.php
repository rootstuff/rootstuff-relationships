<?php

use RelateWP\Registry;
use RelateWP\Relation;

$rel_type       = $attributes['relType'] ?? '';
$layout         = $attributes['layout'] ?? 'list';
$columns        = $attributes['columns'] ?? 3;
$show_thumbnail = $attributes['showThumbnail'] ?? true;
$show_excerpt   = $attributes['showExcerpt'] ?? false;

if ( empty( $rel_type ) || ! Registry::exists( $rel_type ) ) {
	return;
}

$related = Relation::get( $rel_type, get_the_ID() );

if ( empty( $related ) ) {
	return;
}

$classes = [ 'relatewp-related-content', 'relatewp-layout-' . $layout ];
$wrapper = get_block_wrapper_attributes( [ 'class' => implode( ' ', $classes ) ] );
?>
<section <?php echo $wrapper; ?>>
	<?php echo $content; ?>

	<?php if ( 'grid' === $layout ) : ?>
		<div class="relatewp-related-content__grid" style="--relatewp-columns: <?php echo (int) $columns; ?>">
			<?php foreach ( $related as $item ) : ?>
				<div class="relatewp-related-content__card">
					<?php if ( $show_thumbnail && has_post_thumbnail( $item ) ) : ?>
						<a class="relatewp-related-content__thumb" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
							<?php echo get_the_post_thumbnail( $item, 'medium', [ 'class' => 'relatewp-related-content__image' ] ); ?>
						</a>
					<?php endif; ?>
					<a class="relatewp-related-content__link" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
						<?php echo esc_html( get_the_title( $item ) ); ?>
					</a>
					<?php if ( $show_excerpt && get_the_excerpt( $item ) ) : ?>
						<p class="relatewp-related-content__excerpt"><?php echo esc_html( get_the_excerpt( $item ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php elseif ( 'inline' === $layout ) : ?>
		<p class="relatewp-related-content__inline">
			<?php
			$links = array_map( function ( $item ) {
				return sprintf(
					'<a class="relatewp-related-content__link" href="%s">%s</a>',
					esc_url( get_permalink( $item ) ),
					esc_html( get_the_title( $item ) )
				);
			}, $related );
			echo implode( ', ', $links );
			?>
		</p>

	<?php else : ?>
		<ul class="relatewp-related-content__list">
			<?php foreach ( $related as $item ) : ?>
				<li class="relatewp-related-content__item">
					<a class="relatewp-related-content__link" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
						<?php echo esc_html( get_the_title( $item ) ); ?>
					</a>
					<?php if ( $show_excerpt && get_the_excerpt( $item ) ) : ?>
						<p class="relatewp-related-content__excerpt"><?php echo esc_html( get_the_excerpt( $item ) ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
