<?php
/** @var int[] $allowedSpecialtiesIds */
/** @var WP_Term[] $specialtyTerms */

foreach ($specialtyTerms as $term) {
	?>
	<fieldset>
		<label>
			<input
				type="checkbox"
				name="med_specialties[]"
				value="<?php echo $term->term_id; ?>"
				<?php checked(in_array($term->term_id, $allowedSpecialtiesIds));?>
			>
			<?php echo esc_html($term->name); ?>
		</label>
	</fieldset>
	<?php
}