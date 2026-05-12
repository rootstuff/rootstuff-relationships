<?php

use Rootstuff\Relationships\Registry;
use Rootstuff\Relationships\Relation;

$rel_type = $attributes['relType'] ?? '';

if ( empty( $rel_type ) || ! Registry::exists( $rel_type ) ) {
	return;
}

$related = Relation::get( $rel_type, get_the_ID() );

if ( empty( $related ) ) {
	return;
}
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => 'rs-related-content' ] ); ?>>
	<?php echo $content; ?>
	<ul class="rs-related-content__list">
		<?php foreach ( $related as $item ) : ?>
			<li class="rs-related-content__item">
				<a class="rs-related-content__link" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
					<?php echo esc_html( get_the_title( $item ) ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
