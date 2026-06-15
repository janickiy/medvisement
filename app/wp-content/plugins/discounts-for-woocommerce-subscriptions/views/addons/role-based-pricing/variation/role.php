<?php use MeowCrew\SubscriptionsDiscounts\Core\FileManager;

defined( 'ABSPATH' ) || die;

/**
 * Available variables
 *
 * @var int $loop
 * @var string $role
 * @var string $type
 * @var float $sale_price
 * @var float $regular_price
 * @var array $price_rules_fixed
 * @var array $price_rules_percentage
 * @var FileManager $fileManager
 *
 */

global $wp_roles;

$roleName = isset( $wp_roles->role_names[ $role ] ) ? translate_user_role( $wp_roles->role_names[ $role ] ) : $role;
?>

<div class="dfws-role-based-role dfws-role-based-role--<?php echo esc_attr( $role ); ?>"
	 data-role-slug="<?php echo esc_attr( $role ); ?>" data-role-name="<?php echo esc_attr( $roleName ); ?>">
	<div class="dfws-role-based-role__header">
		<div class="dfws-role-based-role__name">
			<b><?php echo esc_attr( $roleName ); ?></b>
		</div>
		<div class="dfws-role-based-role__actions">
			<span class="dfws-role-based-role__action-toggle-view dfws-role-based-role__action-toggle-view--open"></span>
			<a href="#" class="dfws-role-based-role-action--delete"><?php esc_attr_e( 'Remove', 'woocommerce' ); ?></a>
		</div>
	</div>
	<div class="dfws-role-based-role__content">
		<?php

		$fileManager->includeTemplate( 'addons/role-based-pricing/variation/add-price-rules.php', array(
			'price_rules_fixed'      => $price_rules_fixed,
			'price_rules_percentage' => $price_rules_percentage,
			'type'                   => $type,
			'role'                   => $role,
			'loop'                   => $loop,
			'sale_price'             => $sale_price,
			'regular_price'          => $regular_price,
		) );

		?>
	</div>
</div>
