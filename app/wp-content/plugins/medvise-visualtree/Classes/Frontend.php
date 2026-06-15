<?php


namespace Medvisement\Classes;


class Frontend {

	public function setup() {

		add_action('wp_enqueue_scripts', [$this, 'load_scripts']);

		//Шорткод
		add_shortcode('medvise_tree_taxonomy', [$this, 'tree_shortcode']);
	}

	public function tree_shortcode( $atts ) {

		//Модель
		$model_name = Helpers::getTreeModelNamespace( $atts['type'] );

		ob_start();

		if ( $model_name === NULL ) {
			return 'Неизвестный тип древа!';
		}
		?>

		<?php if ( $atts['type'] == 'disease' ): ?>
            <details id="disease-tree" style="margin: 20px 0;">
            <summary>Древо заболеваний</summary>
		<?php elseif ( $atts['type'] == 'substance' ): ?>
            <details id="substance-tree" style="margin: 20px 0;">
            <summary>Древо препаратов</summary>
		<?php elseif ( $atts['type'] == 'custom_quiz' ): ?>
            <details id="questionnaire-tree" style="margin: 20px 0;" open>
            <summary>Каталог опросников</summary>
		<?php endif; ?>

        <div class="fancytree-container_search">
            <div class="fancytree-container_search__row">
                <input name="vt-search" type="text" placeholder="Поиск по <?= $atts['type'] == 'custom_quiz' ? 'каталогу' : 'древу'; ?>" autocomplete="off">
                <button id="vtBtnResetSearch" class="search-submit">&times;</button>
            </div>
            <span id="vtMatches"></span>
        </div>

        <table id="medvise-tax-tree" data-type="<?= $atts['type']; ?>" data-node="<?= $atts['node'] ?? ''; ?>">
            <colgroup>
                <col width="*"></col>
            </colgroup>
            <thead>
            <tr>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td></td>
            </tr>
            </tbody>
        </table>

        </details>

		<?php
		return ob_get_clean();
	}

	public function load_scripts() {
		wp_enqueue_style( 'vt-fancytree-skin', MEDVISETREE_PLUGIN_URL . 'assets/ui.fancytree.css', array(), MEDVISETREE_PLUGIN_VERSION );

		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'vt-fancytree', MEDVISETREE_PLUGIN_URL . 'node_modules/jquery.fancytree/dist/jquery.fancytree-all.min.js', array(
			'jquery',
			'jquery-ui-core'
		), MEDVISETREE_PLUGIN_VERSION, TRUE );
		wp_enqueue_script( 'vt-front', MEDVISETREE_PLUGIN_URL . 'assets/front.js', array(
			'jquery',
			'bootstrap',
			'vt-fancytree'
		), MEDVISETREE_PLUGIN_VERSION, TRUE );
	}

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

}