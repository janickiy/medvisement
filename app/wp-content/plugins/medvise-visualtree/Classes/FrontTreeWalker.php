<?php


namespace Medvisement\Classes;


class FrontTreeWalker {

	private $nodes;

	public function __construct( $nodes ) {
		$this->nodes = $nodes;
	}

	public function display_element( $el, &$output ) {
		if ( ! $el ) {
			return;
		}

		$this->start_el( $el, $output );

		if ( ! empty( $el['children'] ) ) {

			foreach ( $el['children'] as $child_el ) {

				if ( ! isset( $newlevel ) ) {
					$newlevel = true;
					$this->start_lvl( $output );
				}
				$this->display_element( $child_el, $output );
			}

		}

		if ( isset( $newlevel ) && $newlevel ) {
			$this->end_lvl( $output );
		}

		$this->end_el( $el, $output );

	}

	function start_el($el, &$output) {
		ob_start();

		$href = '';
		if ( ! empty($el['post_id']) ) {
			$href = (string) get_permalink($el['post_id']);
		}

		$children_counter = '';
		if ( ! empty( $el['children'] ) ) {
			$children_counter = "(" . count($el['children']) . ")";
		}

		?>
		<li class="<?= ! empty( $el['children'] ) ? 'has-children' : ''; ?>">

			<?php if ( ! empty( $el['children'] ) ): ?>
				<button class="expand-button"><i class="fa-solid fa-caret-right"></i></button>
			<?php endif; ?>

			<?php if ( $href ): ?>
			<a href="<?= $href; ?>" target="_blank">
				<?php endif; ?>
				<?= $el['name']; ?> <?= $children_counter; ?>
				<?php if ( $href ): ?>
			</a>
		<?php endif; ?>
		</li>
		<?php

		$output .= ob_get_clean();
	}

	function end_el($el, &$output) {
		$output .= "</li>";
	}

	function start_lvl(&$output) {
		$output .= "\n<ul>\n";
	}

	function end_lvl(&$output) {
		$output .= "</ul>\n";
	}

	public function render() {

		if ( empty( $this->nodes ) ) {
			return '';
		}

		$output = '';

		foreach ( $this->nodes as $node ) {
			$this->display_element($node, $output);
		}

		return $output;
	}
}