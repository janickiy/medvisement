<?php defined( 'ABSPATH' ) || die;

use MeowCrew\SubscriptionsDiscounts\Core\FileManager;
use MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing\RoleBasedPriceManager;
use MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing\RoleBasedPricingAddon;

/**
 * Available variables
 *
 * @var FileManager $fileManager
 * @var int $product_id
 * @var int $loop
 */

?>
<div class="form-row form-row-full show_if_variable_roles_subscriptions_discounts_form-field">
	<div class="form-field dfws-role-based-block dfws-role-based-block--variation"
		 id="dfws-role-based-block-<?php echo esc_attr( $product_id ); ?>"
		 data-product-type="variation"
		 data-add-action="<?php echo esc_attr( RoleBasedPricingAddon::GET_ROLE_ROW_HTML__ACTION ); ?>"
		 data-add-action-nonce="<?php echo esc_attr( wp_create_nonce( RoleBasedPricingAddon::GET_ROLE_ROW_HTML__ACTION ) ); ?>"
		 data-product-id="<?php echo esc_attr( $product_id ); ?>"
		 data-loop="<?php echo esc_attr( $loop ); ?>"
	>
		<label class="dfws-role-based-block__name"><?php esc_attr_e( 'Role-based discounts', 'discounts-for-woocommerce-subscriptions' ); ?></label>
		<div class="dfws-role-based-block__content">

			<div class="dfws-role-based-roles">

				<?php

				$presentRoles = array();

				foreach ( wp_roles()->roles as $WPRole => $role_data ) {
					if ( RoleBasedPriceManager::roleHasRules( $WPRole, $product_id, 'edit' ) ) {

						$fileManager->includeTemplate( 'addons/role-based-pricing/variation/role.php', array(
							'fileManager'            => $fileManager,
							'price_rules_fixed'      => RoleBasedPriceManager::getFixedDiscounts( $product_id, $WPRole, 'edit' ),
							'price_rules_percentage' => RoleBasedPriceManager::getPercentageDiscounts( $product_id, $WPRole, 'edit' ),
							'type'                   => RoleBasedPriceManager::getDiscountsType( $product_id, $WPRole, 'fixed', 'edit' ),
							'sale_price'             => RoleBasedPriceManager::getSalePrice( $product_id, $WPRole, 'edit' ),
							'regular_price'          => RoleBasedPriceManager::getRegularPrice( $product_id, $WPRole, 'edit' ),
							'role'                   => $WPRole,
							'loop'                   => $loop,
						) );

						$presentRoles[] = $WPRole;
					}
				}
				?>
			</div>

			<div class="dfws-role-based-no-roles"
				 style="<?php echo esc_attr( ! empty( $presentRoles ) ? 'display: none;' : '' ); ?>">
				<span><?php esc_attr_e( 'Set up separate discounts for different roles of customers. Choose a role and click the "Setup for role" button.', 'discounts-for-woocommerce-subscriptions' ); ?></span>
			</div>

			<div class="dfws-role-based-adding-form">
				<select class="dfws-role-based-adding-form__role-selector" style="margin: 0; width: 150px">
					<?php foreach ( wp_roles()->roles as $key => $WPRole ) : ?>
						<?php if ( ! in_array( $key, $presentRoles ) ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_attr( $WPRole['name'] ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>

				<button class="button dfws-role-based-adding-form__add-button"
						style="height: 40px">
					<?php esc_attr_e( 'Setup for role', 'discounts-for-woocommerce-subscriptions' ); ?>
				</button>

				<div class="clear"></div>
			</div>

			<select name="subscriptions_discounts_rules_roles_to_delete_variable[<?php echo esc_attr( $loop ); ?>][]"
					class="subscriptions_discounts_rules_roles_to_delete" multiple
					style="display:none;">
				<?php foreach ( wp_roles()->roles as $key => $WPRole ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_attr( $WPRole['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
</div>
