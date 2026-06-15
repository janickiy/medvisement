<?php
namespace CustomProfilePage\Helpers;

function userInRoles($userId, $roles = []){
    $user = new \WP_User($userId);

    if (!$user) {
        return false;
    }

    $sameRoles = array_intersect($user->roles, $roles);

    return count($sameRoles) > 0;
}

function addCapsToRole(string $role, array $caps = []):void
{
    $role = get_role( $role );

    if (!$role) {
        return;
    }

    foreach ( $caps as $cap ) {
        $role->add_cap( $cap );
    }
}

function removeCapsFromRole(string $role, array $caps = []):void
{
    $role = get_role( $role );

    if (!$role) {
        return;
    }

    foreach ( $caps as $cap ) {
        $role->remove_cap( $cap );
    }
}