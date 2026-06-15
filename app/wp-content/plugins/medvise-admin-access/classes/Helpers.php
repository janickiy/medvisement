<?php


namespace MedvisementAdminAccess;


class Helpers {

	public static function is_editor_or_author() {
		global $current_user;
		$user_roles = $current_user->roles;

		if ( in_array( 'editor', $user_roles ) || in_array( 'author', $user_roles ) ) {
			return TRUE;
		}
	}

	public static function is_author() {
		global $current_user;
		$user_roles = $current_user->roles;

		if ( in_array( 'author', $user_roles ) ) {
			return TRUE;
		}
	}

	public static function is_editor() {
		global $current_user;
		$user_roles = $current_user->roles;

		if ( in_array( 'editor', $user_roles ) ) {
			return TRUE;
		}
	}
}