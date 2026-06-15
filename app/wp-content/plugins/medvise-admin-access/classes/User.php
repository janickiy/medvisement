<?php


namespace MedvisementAdminAccess;


class User {

	public static function factory() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		register_activation_hook( MEDVISEADMINACCESS_PLUGIN_FILE, [ $this, 'register_user_caps' ] );

		add_action( 'init', [ $this, 'init' ] );

	}

	public function init() {
		if ( current_user_can( 'administrator' ) ) {
			//Вывод доп. полей в профиле юзера
			add_action( 'show_user_profile', [ $this, 'admin_user_profile' ] );
			add_action( 'edit_user_profile', [ $this, 'admin_user_profile' ] );

			//Сохранение полей
			add_action( 'personal_options_update', [ $this, 'admin_update_user_profile' ] );
			add_action( 'edit_user_profile_update', [ $this, 'admin_update_user_profile' ] );
		}
	}

	// Страница профиля пользователя
	public function admin_user_profile( $user ) {
		//Поля только для авторов и редакторов
		if ( ! in_array( 'author', $user->roles ) && ! in_array( 'editor', $user->roles ) ) {
			return TRUE;
		}

		$specialty_terms = get_terms( array(
			'taxonomy'   => 'specialty',
			'hide_empty' => FALSE,
		) );

		$med_specialty = get_user_meta( $user->ID, 'med_specialty', TRUE );
		?>

        <h2>Доступы к статьям</h2>
		<?php wp_nonce_field( 'update-user_' . $user->ID, 'update-user_' . $user->ID . '_wpnonce' ); ?>
        <table class="form-table" role="presentation">
            <tbody>
            <tr class="user-rich-editing-wrap">
                <th scope="row">Специальности</th>
                <td>
                    <table>
						<?php foreach ( $specialty_terms as $specialty_term ): ?>
                            <tr>
                                <td><?= $specialty_term->name; ?></td>
                                <td>
                                    <input name="user_specialty[<?= $specialty_term->term_id; ?>]"
                                           id="user_specialty-<?= $specialty_term->term_id; ?>"
                                           type="text"
                                           value="<?= isset( $med_specialty[ $specialty_term->term_id ] ) ? $med_specialty[ $specialty_term->term_id ] : ''; ?>"
                                           placeholder="Название роли">
                                </td>
                            </tr>
						<?php endforeach; ?>
                    </table>
                </td>
            </tr>

            <tr>
                <th scope="row">Права доступа</th>
                <td>
                    <label for="med-caps-disease">
                        <input id="med-caps-disease" name="med_caps[edit_disease]" type="checkbox" value="1"
							<?= empty( $user->allcaps['edit_disease'] ) ? '' : 'checked'; ?>>
                        Заболевания
                    </label> <br>
                    <label for="med-caps-substance">
                        <input id="med-caps-substance" name="med_caps[edit_substance]" type="checkbox" value="1"
							<?= empty( $user->allcaps['edit_substance'] ) ? '' : 'checked'; ?>>
                        Препараты
                    </label> <br><br>

                    <label for="med-caps-symptoms">
                        <input id="med-caps-symptoms" name="med_caps[assign_terms]" type="checkbox" value="1"
							<?= empty( $user->allcaps['assign_terms'] ) ? '' : 'checked'; ?>>
                        Симптомы
                    </label> <br>
                    <label for="med-caps-drug-classes">
                        <input id="med-caps-drug-classes" name="med_caps[manage_drug-classes]" type="checkbox" value="1"
							<?= empty( $user->allcaps['manage_drug-classes'] ) ? '' : 'checked'; ?>>
                        Группы лекарственных средств
                    </label> <br><br>

					<?php if ( in_array( 'author', $user->roles ) ): ?>
                        <label for="med-caps-disease-publish">
                            <input id="med-caps-disease-publish" name="med_caps[publish_diseases]" type="checkbox"
                                   value="1"
								<?= empty( $user->allcaps['publish_diseases'] ) ? '' : 'checked'; ?>>
                            Публикация заболеваний
                        </label> <br>

                        <label for="med-caps-substance-publish">
                            <input id="med-caps-substance-publish" name="med_caps[publish_substances]" type="checkbox"
                                   value="1"
								<?= empty( $user->allcaps['publish_substances'] ) ? '' : 'checked'; ?>>
                            Публикация препаратов
                        </label> <br><br>
					<?php endif; ?>

                    <label for="med-caps-manage_visualtree-disease">
                        <input id="med-caps-manage_visualtree-disease" name="med_caps[manage_visualtree-disease]"
                               type="checkbox" value="1"
							<?= empty( $user->allcaps['manage_visualtree-disease'] ) ? '' : 'checked'; ?>>
                        Древовидность - заболевания
                    </label> <br>
                    <label for="med-caps-manage_visualtree-substance">
                        <input id="med-caps-manage_visualtree-substance" name="med_caps[manage_visualtree-substance]"
                               type="checkbox" value="1"
							<?= empty( $user->allcaps['manage_visualtree-substance'] ) ? '' : 'checked'; ?>>
                        Древовидность - препараты
                    </label>
                    <label for="med-caps-manage_visualtree-questionnaire">
                        <input id="med-caps-manage_visualtree-questionnaire" name="med_caps[manage_visualtree-questionnaire]"
                               type="checkbox" value="1"
			                <?= empty( $user->allcaps['manage_visualtree-questionnaire'] ) ? '' : 'checked'; ?>>
                        Древовидность - опросники
                    </label>
                </td>
            </tr>

            </tbody>
        </table>
		<?php
	}

	// Сохранение полей пользователя
	public function admin_update_user_profile( $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		//Поля только для авторов и редакторов
		if ( ! in_array( 'author', $user->roles ) && ! in_array( 'editor', $user->roles ) ) {
			return;
		}

		check_admin_referer( 'update-user_' . $user_id, 'update-user_' . $user_id . '_wpnonce' );

		// Заболевания
		if ( isset( $_POST['med_caps']['edit_disease'] ) ) {
			$user->add_cap( 'edit_disease' );
			$user->add_cap( 'read_disease' );
			$user->add_cap( 'delete_diseases' );
			$user->add_cap( 'edit_diseases' );
			$user->add_cap( 'edit_published_diseases' );

			$user->add_cap( 'assign_specialty' );
			$user->add_cap( 'assign_age' );

			if ( in_array( 'editor', $user->roles ) ) {
				$user->add_cap( 'edit_others_diseases' );
			}
			if ( ! empty( $_POST['med_caps']['publish_diseases'] ) || in_array( 'editor', $user->roles ) ) {
				$user->add_cap( 'publish_diseases' );
			}
		} else {
			$user->remove_cap( 'edit_disease' );
			$user->remove_cap( 'read_disease' );
			$user->remove_cap( 'delete_diseases' );
			$user->remove_cap( 'edit_diseases' );
			$user->remove_cap( 'edit_published_diseases' );

			$user->remove_cap( 'assign_specialty' );
			$user->remove_cap( 'assign_age' );
		}
		// Редактирование чужих заболеваний - только редактор
		if ( ! in_array( 'editor', $user->roles ) ) {
			$user->remove_cap( 'edit_others_diseases' );
		}
		// Публикация
		if ( empty( $_POST['med_caps']['publish_diseases'] ) || ! in_array( 'editor', $user->roles ) ) {
			$user->remove_cap( 'publish_diseases' );
		}

		// Препараты
		if ( isset( $_POST['med_caps']['edit_substance'] ) ) {
			$user->add_cap( 'edit_substance' );
			$user->add_cap( 'read_substance' );
			$user->add_cap( 'delete_substances' );
			$user->add_cap( 'edit_substances' );
			$user->add_cap( 'edit_published_substances' );

			$user->add_cap( 'assign_drug-classes' );

			if ( in_array( 'editor', $user->roles ) ) {
				$user->add_cap( 'edit_others_substances' );
			}
			if ( ! empty( $_POST['med_caps']['publish_substances'] ) || in_array( 'editor', $user->roles ) ) {
				$user->add_cap( 'publish_substances' );
			}
		} else {
			$user->remove_cap( 'edit_substance' );
			$user->remove_cap( 'read_substance' );
			$user->remove_cap( 'delete_substances' );
			$user->remove_cap( 'edit_substances' );
			$user->remove_cap( 'edit_published_substances' );

			$user->remove_cap( 'assign_drug-classes' );
		}
		// Редактирование чужих препаратов
		if ( ! in_array( 'editor', $user->roles ) ) {
			$user->remove_cap( 'edit_others_substances' );
		}
		// Публикация препаратов
		if ( empty( $_POST['med_caps']['publish_substances'] ) || ! in_array( 'editor', $user->roles ) ) {
			$user->remove_cap( 'publish_substances' );
		}

		if ( ! empty( $_POST['med_caps']['assign_terms'] ) ) {
			$user->add_cap( 'manage_terms' );
			$user->add_cap( 'edit_terms' );
			$user->add_cap( 'delete_terms' );
			$user->add_cap( 'assign_terms' );

			$user->add_cap( 'assign_symptoms' );
			$user->add_cap( 'edit_symptoms' );
			$user->add_cap( 'delete_symptoms' );
			$user->add_cap( 'assign_symptoms' );
		} else {
			$user->remove_cap( 'assign_terms' );
		}

		if ( ! empty( $_POST['med_caps']['manage_drug-classes'] ) ) {
			$user->add_cap( 'manage_drug-classes' );
		} else {
			$user->remove_cap( 'manage_drug-classes' );
		}

		// Специальности
		if ( in_array( 'author', $user->roles ) || in_array( 'editor', $user->roles ) ) {
			update_user_meta( $user_id, 'med_specialty', $_POST['user_specialty'] );
		} else {
			delete_user_meta( $user_id, 'med_specialty' );
		}

		// Древовидность
		if ( ! empty( $_POST['med_caps']['manage_visualtree-disease'] ) ) {
			$user->add_cap( 'manage_visualtree-disease' );
		} else {
			$user->remove_cap( 'manage_visualtree-disease' );
		}

		if ( ! empty( $_POST['med_caps']['manage_visualtree-substance'] ) ) {
			$user->add_cap( 'manage_visualtree-substance' );
		} else {
			$user->remove_cap( 'manage_visualtree-substance' );
		}

		if ( ! empty( $_POST['med_caps']['manage_visualtree-questionnaire'] ) ) {
			$user->add_cap( 'manage_visualtree-questionnaire' );
		} else {
			$user->remove_cap( 'manage_visualtree-questionnaire' );
		}

	}

	public function register_user_caps() {
		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );
		$author        = get_role( 'author' );

		// Выдаем администратору возможность редактирования заболеваний
		$administrator->add_cap( 'edit_disease' ); // Редактирование конкретной
		$administrator->add_cap( 'read_disease' ); // Чтение конкретной
		$administrator->add_cap( 'delete_disease' ); // Удаление конкретной
		$administrator->add_cap( 'edit_diseases' ); // Редактирование типа
		$administrator->add_cap( 'edit_others_diseases' ); // Редактирование принадлежат другому пользователю
		$administrator->add_cap( 'delete_diseases' ); // Удаление типа
		$administrator->add_cap( 'publish_diseases' ); // Публикация типа
		$administrator->add_cap( 'read_private_diseases' ); // Чтение личных
		$administrator->add_cap( 'delete_private_diseases' ); // Удаление личных
		$administrator->add_cap( 'delete_published_diseases' ); // Удаление опубликованных
		$administrator->add_cap( 'delete_others_diseases' ); // Удаление принадлежит другому
		$administrator->add_cap( 'edit_private_diseases' ); // Редактирование личных
		$administrator->add_cap( 'edit_published_diseases' ); // Редактирование опубликованных

		// и Препаратов
		$administrator->add_cap( 'edit_substance' ); // Редактирование конкретной
		$administrator->add_cap( 'read_substance' ); // Чтение конкретной
		$administrator->add_cap( 'delete_substance' ); // Удаление конкретной
		$administrator->add_cap( 'edit_substances' ); // Редактирование типа
		$administrator->add_cap( 'edit_others_substances' ); // Редактирование принадлежат другому пользователю
		$administrator->add_cap( 'delete_substances' ); // Удаление типа
		$administrator->add_cap( 'publish_substances' ); // Публикация типа
		$administrator->add_cap( 'read_private_substances' ); // Чтение личных
		$administrator->add_cap( 'delete_private_substances' ); // Удаление личных
		$administrator->add_cap( 'delete_published_substances' ); // Удаление опубликованных
		$administrator->add_cap( 'delete_others_substances' ); // Удаление принадлежит другому
		$administrator->add_cap( 'edit_private_substances' ); // Редактирование личных
		$administrator->add_cap( 'edit_published_substances' ); // Редактирование опубликованных

		// и Таксономии
		$administrator->add_cap( 'manage_symptoms' );
		$administrator->add_cap( 'edit_symptoms' );
		$administrator->add_cap( 'delete_symptoms' );
		$administrator->add_cap( 'assign_symptoms' );

		$administrator->add_cap( 'manage_age' );
		$administrator->add_cap( 'edit_age' );
		$administrator->add_cap( 'delete_age' );
		$administrator->add_cap( 'assign_age' );

		$administrator->add_cap( 'manage_specialty' );
		$administrator->add_cap( 'edit_specialty' );
		$administrator->add_cap( 'delete_specialty' );
		$administrator->add_cap( 'assign_specialty' );

		$administrator->add_cap( 'manage_drug-classes' );
		$administrator->add_cap( 'edit_drug-classes' );
		$administrator->add_cap( 'delete_drug-classes' );
		$administrator->add_cap( 'assign_drug-classes' );

		// Древовидность
		$administrator->add_cap( 'manage_visualtree-disease' );
		$administrator->add_cap( 'manage_visualtree-substance' );
		$administrator->add_cap( 'manage_visualtree-questionnaire' );

		// Редактор - записи
		//$editor->add_cap( 'edit_posts' ); // Иначе админка не будет работать
		//$editor->add_cap( 'delete_posts' ); // Для удаления медиа
		$editor->remove_cap( 'edit_others_posts' );
		$editor->remove_cap( 'edit_published_posts' );
		$editor->remove_cap( 'publish_posts' );
		$editor->remove_cap( 'delete_others_posts' );
		$editor->remove_cap( 'delete_published_posts' );
		$editor->remove_cap( 'delete_private_posts' );
		$editor->remove_cap( 'edit_private_posts' );
		$editor->remove_cap( 'edit_published_posts' );
		$editor->remove_cap( 'read_private_posts' );
		// Редактор - страницы
		$editor->remove_cap( 'edit_pages' );
		$editor->remove_cap( 'edit_others_pages' );
		$editor->remove_cap( 'edit_published_pages' );
		$editor->remove_cap( 'publish_pages' );
		$editor->remove_cap( 'delete_pages' );
		$editor->remove_cap( 'delete_others_pages' );
		$editor->remove_cap( 'delete_published_pages' );
		$editor->remove_cap( 'delete_private_pages' );
		$editor->remove_cap( 'edit_private_pages' );
		$editor->remove_cap( 'read_private_pages' );
		// Редактор - таксономии
		$editor->remove_cap( 'manage_categories' );
		// Редактор - комментарии и другое
		$editor->remove_cap( 'moderate_comments' );
		$editor->remove_cap( 'manage_links' );

		// Автор - отключаем записи
		//$author->add_cap( 'edit_posts' ); // Иначе админка не будет работать
		//$author->add_cap( 'delete_posts' ); // Для удаления медиа
		$author->remove_cap( 'edit_published_posts' );
		$author->remove_cap( 'publish_posts' );
		$author->remove_cap( 'delete_published_posts' );
	}

}