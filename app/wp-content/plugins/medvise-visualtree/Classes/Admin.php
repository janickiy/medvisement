<?php


namespace Medvisement\Classes;

class Admin {

	public function setup() {

		add_action( 'admin_enqueue_scripts', [ $this, 'load_scripts' ] );

		add_action( 'admin_menu', [ $this, 'settings_page' ] );

	}

	public function settings_page() {

		add_submenu_page(
			'edit.php?post_type=disease',
			'Древовидность',
			'Древовидность',
			'manage_visualtree-disease',
			'medvise-visualtree-disease',
			[
				$this,
				'tree_page_render'
			]
		);

		add_submenu_page(
			'edit.php?post_type=substance',
			'Древовидность',
			'Древовидность',
			'manage_visualtree-substance',
			'medvise-visualtree-substance',
			[
				$this,
				'tree_page_render'
			]
		);

		add_submenu_page(
			'edit.php?post_type=custom_quiz',
			'Древовидность',
			'Древовидность',
			'manage_visualtree-questionnaire',
			'medvise-visualtree-questionnaire',
			[
				$this,
				'tree_page_render'
			]
		);

	}

	public function tree_page_render() {
		$cpt_object = get_post_type_object( $_GET['post_type'] );

		if ( empty( $cpt_object ) ) {
			echo 'Тип записи не найден!';

			return;
		}

		add_thickbox();
		?>

        <h2>Древовидность - <?= $cpt_object->label; ?></h2>

        <p>
            <label>Поиск:</label>
            <input name="search" placeholder="Введите текст" autocomplete="off">
            <button id="btnResetSearch">&times;</button>
            <span id="matches"></span>
        </p>

        <table id="vt-admin">
            <colgroup>
                <col width="70px"></col>
                <col width="*"></col>
                <col width="50px"></col>
            </colgroup>
            <thead>
            <tr>
                <th>ID</th>
                <th></th>
                <th>Статья</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td class="alignCenter"></td>
                <td></td>
                <td class="alignRight"></td>
            </tr>
            </tbody>
        </table>

        <div id="vt-admin_setup-post_modal" style="display:none;">
            <form id="vt-admin_setup-post" method="POST">
                <input type="hidden" name="key">
                <p>
                    Установка статьи для <strong class="node-name"></strong>
                </p>
                <p>
                    <select name="post_id"></select>
                </p>
                <p>
                    <button type="submit" class="button button-primary">Задать статью</button>
                </p>
            </form>
        </div>

        <a id="vt-admin_setup-post_link" href="/?TB_inline&height=150&width=500&inlineId=vt-admin_setup-post_modal" class="thickbox"></a>

        <script type="text/javascript">
            var vt_nonce = '<?= wp_create_nonce( 'vt-nonce' ); ?>';
            const vt_urlParams = new URLSearchParams(window.location.search);
        </script>

		<?php
	}

	public function load_scripts() {
		wp_enqueue_style('vt-admin', MEDVISETREE_PLUGIN_URL . 'assets/admin.css', array(), MEDVISETREE_PLUGIN_VERSION);
		wp_enqueue_style('vt-fancytree-skin', MEDVISETREE_PLUGIN_URL . 'node_modules/jquery.fancytree/dist/skin-lion/ui.fancytree.min.css', array(), MEDVISETREE_PLUGIN_VERSION);
		wp_enqueue_style('jquery-contextmenu', MEDVISETREE_PLUGIN_URL . 'node_modules/jquery-contextmenu/dist/jquery.contextMenu.min.css', array(), MEDVISETREE_PLUGIN_VERSION);
		wp_enqueue_style('select2', MEDVISETREE_PLUGIN_URL . 'node_modules/select2/dist/css/select2.min.css', array(), MEDVISETREE_PLUGIN_VERSION);


		wp_enqueue_script('vt-fancytree', MEDVISETREE_PLUGIN_URL . 'node_modules/jquery.fancytree/dist/jquery.fancytree-all.min.js', array('jquery'), MEDVISETREE_PLUGIN_VERSION, true);
		wp_enqueue_script('jquery-contextmenu', MEDVISETREE_PLUGIN_URL . 'node_modules/jquery-contextmenu/dist/jquery.contextMenu.min.js', array('vt-fancytree'), MEDVISETREE_PLUGIN_VERSION, true);
		wp_enqueue_script('vt-fancytree-contextmenu', MEDVISETREE_PLUGIN_URL . 'assets/jquery.fancytree.contextMenu.js', array('jquery-contextmenu'), MEDVISETREE_PLUGIN_VERSION, true);
		wp_enqueue_script('select2', MEDVISETREE_PLUGIN_URL . 'node_modules/select2/dist/js/select2.min.js', array('jquery'), MEDVISETREE_PLUGIN_VERSION, true);
		wp_enqueue_script('select2-i18n', MEDVISETREE_PLUGIN_URL . 'node_modules/select2/dist/js/i18n/ru.js', array('jquery'), MEDVISETREE_PLUGIN_VERSION, true);

		wp_enqueue_script('vt-admin', MEDVISETREE_PLUGIN_URL . 'assets/admin.js', array('vt-fancytree-contextmenu'), MEDVISETREE_PLUGIN_VERSION, true);
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