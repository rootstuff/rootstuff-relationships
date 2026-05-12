<?php

use RelateWP\Registry;
use RelateWP\Relation;

$rel_type = $attributes['relType'] ?? '';

if (empty($rel_type) || ! Registry::exists($rel_type)) {
	return;
}

$related = Relation::get($rel_type, get_the_ID());

if (empty($related)) {
	return;
}
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'relatewp-related-content']); ?>>
	<?php echo $content; ?>
	<ul class="relatewp-related-content__list">
		<?php foreach ($related as $item) : ?>
			<li class="relatewp-related-content__item">
				<a class="relatewp-related-content__link" href="<?php echo esc_url(get_permalink($item)); ?>">
					<?php echo esc_html(get_the_title($item)); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>