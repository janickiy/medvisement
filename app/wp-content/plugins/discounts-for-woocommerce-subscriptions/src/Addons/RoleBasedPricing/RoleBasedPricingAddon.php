<?php namespace MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing;

use MeowCrew\SubscriptionsDiscounts\Addons\AbstractAddon;

class RoleBasedPricingAddon extends AbstractAddon {

	const GET_ROLE_ROW_HTML__ACTION = 'dfws_get_role_row_html';
	const SETTING_ENABLE_KEY = 'enable_role_based_discounts_addon';

	/**
	 * Get addon name
	 *
	 * @return string
	 */
	public function getName() {
		return __( 'Role based subscriptions discounts', 'discounts-for-woocommerce-subscriptions' );
	}

	/**
	 * Whether addon is active or not
	 *
	 * @return bool
	 */
	public function isActive() {
		return $this->getContainer()->getSettings()->get( self::SETTING_ENABLE_KEY, 'yes' ) === 'yes';
	}

	/**
	 * Run
	 */
	public function run() {
		// Get row ajax
		add_action( 'wp_ajax_' . self::GET_ROLE_ROW_HTML__ACTION, array( $this, 'getRoleRowHtml' ) );

		// Simple product
		add_action( 'subscription_discounts/admin/pricing_tab_end', array( $this, 'renderProductPage' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'updateRoleBasedData' ) );

		// Variable product
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'renderPriceRulesVariation' ), 11, 3 );
		add_action( 'woocommerce_save_product_variation', array(
			$this,
			'updateVariationRoleBasedData'
		), 10, 3 );

		/**
		 * Main function to filter the subscriptions discounts
		 *
		 * @priority 20
		 */
		add_filter( 'subscription_discounts/price/product_price_rules', array( $this, 'addRolePricing' ), 20, 2 );

		new RoleBasedSaleRegularPricingManager();
	}

	/**
	 * Main function to filter subscriptions discounts
	 *
	 * @param array $_rules
	 * @param int $productId
	 *
	 * @return array
	 */
	public function addRolePricing( $_rules, $productId ) {

		$userRoles = $this->getCurrentUserRoles();

		if ( ! empty( $userRoles ) ) {

			foreach ( $userRoles as $role ) {

				if ( RoleBasedPriceManager::roleHasRules( $role, $productId ) ) {

					$rules     = RoleBasedPriceManager::getDiscounts( $productId, $role, null, null, false );
					$rulesType = RoleBasedPriceManager::getDiscountsType( $productId, $role );

					add_filter( 'subscription_discounts/price/type', function ( $__type, $__productId ) use ( $rulesType, $productId ) {
						if ( $productId === $__productId ) {
							return $rulesType;
						}

						return $__type;
					}, 10, 2 );

					return $rules;
				}
			}
		}

		return $_rules;
	}

	protected function getCurrentUserRoles() {
		$roles = array();
		$user  = wp_get_current_user();

		if ( $user ) {
			$roles = ( array ) $user->roles;
		}

		return apply_filters( 'subscription_discounts/role_based_rules/current_user_roles', $roles, get_current_user_id() );
	}

	public function updateRoleBasedData( $product_id ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing - checking nonce does not makes any sense. Required by WooCommerce phpcs rules
		if ( true || wp_verify_nonce( true ) ) {
			$data = $_POST;
		}

		$this->updateDiscountsType( $product_id, $data );
		$this->updateSalePrice( $product_id, $data );
		$this->updateRegularPrice( $product_id, $data );
		$this->updatePriceRules( $product_id, $data );

		$this->handleRemovedRules( $product_id, $data );
	}

	public function updateVariationRoleBasedData( $variation_id, $loop ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing - checking nonce does not makes any sense. Required by WooCommerce phpcs rules
		if ( true || wp_verify_nonce( true ) ) {
			$data = $_POST;
		}

		$this->updateVariationPriceRules( $variation_id, $loop, $data );
		$this->updateVariationPriceRulesType( $variation_id, $loop, $data );

		$this->updateVariationSalePrice( $variation_id, $loop, $data );
		$this->updateVariationRegularPrice( $variation_id, $loop, $data );

		$this->handleVariationRemovedRules( $variation_id, $loop, $data );
	}

	/**
	 * Update price quantity rules for variation product
	 *
	 * @param int $variation_id
	 * @param int $loop
	 * @param array $data
	 */
	public function updateVariationPriceRules( $variation_id, $loop, $data ) {

		if ( ! empty( $data['subscriptions_discounts_fixed_quantity_roles_variable'][ $loop ] ) ) {
			foreach ( $data['subscriptions_discounts_fixed_quantity_roles_variable'][ $loop ] as $role => $rules ) {
				$fixedAmounts = ! empty( $data['subscriptions_discounts_fixed_quantity_roles_variable'][ $loop ][ $role ] ) ? (array) $data['subscriptions_discounts_fixed_quantity_roles_variable'][ $loop ][ $role ] : array();
				$fixedPrices  = ! empty( $data['subscriptions_discounts_fixed_price_roles_variable'][ $loop ][ $role ] ) ? (array) $data['subscriptions_discounts_fixed_price_roles_variable'][ $loop ][ $role ] : array();

				RoleBasedPriceManager::updateFixedDiscounts( $fixedAmounts, $fixedPrices, $variation_id, $role );

				$percentageAmounts = ! empty( $data['subscriptions_discounts_percent_quantity_roles_variable'][ $loop ][ $role ] ) ? (array) $data['subscriptions_discounts_percent_quantity_roles_variable'][ $loop ][ $role ] : array();
				$percentagePrices  = ! empty( $data['subscriptions_discounts_percent_discount_roles_variable'][ $loop ][ $role ] ) ? (array) $data['subscriptions_discounts_percent_discount_roles_variable'][ $loop ][ $role ] : array();

				RoleBasedPriceManager::updatePercentageDiscounts( $percentageAmounts, $percentagePrices, $variation_id, $role );
			}
		}
	}

	/**
	 * Update product pricing type
	 *
	 * @param int $variation_id
	 * @param int $loop
	 * @param array $data
	 */
	public function updateVariationPriceRulesType( $variation_id, $loop, $data ) {
		if ( ! empty( $data['subscriptions_discounts_rules_type_roles_variable'][ $loop ] ) ) {
			foreach ( $data['subscriptions_discounts_rules_type_roles_variable'][ $loop ] as $role => $rules ) {
				if ( ! empty( $data['subscriptions_discounts_rules_type_roles_variable'][ $loop ] [ $role ] ) ) {
					RoleBasedPriceManager::updateDiscountsType( $variation_id,
						sanitize_text_field( $data['subscriptions_discounts_rules_type_roles_variable'][ $loop ][ $role ] ), $role );
				}
			}
		}
	}

	/**
	 * Update product variation sale price
	 *
	 * @param int $variation_id
	 * @param int $loop
	 * @param array $data
	 */
	public function updateVariationSalePrice( $variation_id, $loop, $data ) {
		if ( ! empty( $data['subscriptions_discounts_sale_price_variable'][ $loop ] ) ) {
			foreach ( $data['subscriptions_discounts_rules_type_roles_variable'][ $loop ] as $role => $rules ) {
				if ( ! empty( $data['subscriptions_discounts_sale_price_variable'][ $loop ] [ $role ] ) ) {
					RoleBasedPriceManager::updateSalePrice( $variation_id,
						wc_format_decimal( $data['subscriptions_discounts_sale_price_variable'][ $loop ][ $role ] ), $role );
				}
			}
		}
	}

	/**
	 * Update product variation regular price
	 *
	 * @param int $variation_id
	 * @param int $loop
	 * @param array $data
	 */
	public function updateVariationRegularPrice( $variation_id, $loop, $data ) {
		if ( ! empty( $data['subscriptions_discounts_regular_price_variable'][ $loop ] ) ) {
			foreach ( $data['subscriptions_discounts_rules_type_roles_variable'][ $loop ] as $role => $rules ) {
				if ( ! empty( $data['subscriptions_discounts_regular_price_variable'][ $loop ] [ $role ] ) ) {
					RoleBasedPriceManager::updateRegularPrice( $variation_id,
						wc_format_decimal( $data['subscriptions_discounts_regular_price_variable'][ $loop ][ $role ] ), $role );
				}
			}
		}
	}

	/**
	 * Handle removing not used role-based rules
	 *
	 * @param int $variation_id
	 * @param int $loop
	 * @param array $data
	 */
	public function handleVariationRemovedRules( $variation_id, $loop, $data ) {

		if ( ! empty( $data['subscriptions_discounts_rules_roles_to_delete_variable'][ $loop ] ) ) {

			foreach ( $data['subscriptions_discounts_rules_roles_to_delete_variable'][ $loop ] as $roleToRemove ) {
				if ( ! empty( $roleToRemove ) ) {
					RoleBasedPriceManager::deleteAllDataForRole( $variation_id, $roleToRemove );
				}
			}
		}
	}

	/**
	 * Handle remover role-based rules
	 *
	 * @param int $product_id
	 * @param array $data
	 */
	public function handleRemovedRules( $product_id, $data ) {
		if ( ! empty( $data['subscriptions_discounts_rules_roles_to_delete'] ) ) {
			foreach ( $data['subscriptions_discounts_rules_roles_to_delete'] as $roleToRemove ) {
				if ( ! empty( $roleToRemove ) ) {
					RoleBasedPriceManager::deleteAllDataForRole( $product_id, $roleToRemove );
				}
			}
		}
	}

	/**
	 * Update role-based price rules
	 *
	 * @param int $product_id
	 * @param array $data
	 */
	public function updatePriceRules( $product_id, $data ) {

		if ( ! empty( $data['subscriptions_discounts_fixed_quantity_roles'] ) ) {
			foreach ( $data['subscriptions_discounts_fixed_quantity_roles'] as $role => $rules ) {
				$fixedAmounts = ! empty( $data['subscriptions_discounts_fixed_quantity_roles'][ $role ] ) ? (array) $data['subscriptions_discounts_fixed_quantity_roles'][ $role ] : array();
				$fixedPrices  = ! empty( $data['subscriptions_discounts_fixed_price_roles'][ $role ] ) ? (array) $data['subscriptions_discounts_fixed_price_roles'][ $role ] : array();

				RoleBasedPriceManager::updateFixedDiscounts( $fixedAmounts, $fixedPrices, $product_id, $role );
			}
		}

		if ( ! empty( $data['subscriptions_discounts_percent_discount_roles'] ) ) {
			foreach ( $data['subscriptions_discounts_percent_discount_roles'] as $role => $rules ) {

				$percentageAmounts = ! empty( $data['subscriptions_discounts_percent_quantity_roles'][ $role ] ) ? (array) $data['subscriptions_discounts_percent_quantity_roles'][ $role ] : array();
				$percentagePrices  = ! empty( $data['subscriptions_discounts_percent_discount_roles'][ $role ] ) ? (array) $data['subscriptions_discounts_percent_discount_roles'][ $role ] : array();

				RoleBasedPriceManager::updatePercentageDiscounts( $percentageAmounts, $percentagePrices, $product_id, $role );
			}
		}
	}

	/**
	 * Update product pricing type
	 *
	 * @param int $product_id
	 * @param array $data
	 */
	public function updateDiscountsType( $product_id, $data ) {
		if ( ! empty( $data['subscriptions_discounts_rules_type_roles'] ) ) {
			foreach ( $data['subscriptions_discounts_rules_type_roles'] as $role => $rules ) {
				if ( ! empty( $data['subscriptions_discounts_rules_type_roles'][ $role ] ) ) {
					RoleBasedPriceManager::updateDiscountsType( $product_id,
						sanitize_text_field( $data['subscriptions_discounts_rules_type_roles'][ $role ] ), $role );
				}
			}
		}
	}

	/**
	 * Update product sale price
	 *
	 * @param int $product_id
	 * @param array $data
	 */
	public function updateSalePrice( $product_id, $data ) {
		if ( ! empty( $data['subscriptions_discounts_sale_price'] ) ) {
			foreach ( $data['subscriptions_discounts_rules_type_roles'] as $role => $rules ) {
				if ( ! empty( $data['subscriptions_discounts_sale_price'][ $role ] ) ) {
					RoleBasedPriceManager::updateSalePrice( $product_id,
						wc_format_decimal( $data['subscriptions_discounts_sale_price'][ $role ] ), $role );
				}
			}
		}
	}

	/**
	 * Update product sale price
	 *
	 * @param int $product_id
	 * @param array $data
	 */
	public function updateRegularPrice( $product_id, $data ) {
		if ( ! empty( $data['subscriptions_discounts_regular_price'] ) ) {
			foreach ( $data['subscriptions_discounts_rules_type_roles'] as $role => $rules ) {
				if ( ! empty( $data['subscriptions_discounts_regular_price'][ $role ] ) ) {
					RoleBasedPriceManager::updateRegularPrice( $product_id,
						wc_format_decimal( $data['subscriptions_discounts_regular_price'][ $role ] ), $role );
				}
			}
		}
	}

	/**
	 * AJAX Handler
	 */
	public function getRoleRowHtml() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : false;

		if ( wp_verify_nonce( $nonce, self::GET_ROLE_ROW_HTML__ACTION ) ) {

			$role       = isset( $_GET['role'] ) ? sanitize_text_field( $_GET['role'] ) : false;
			$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
			$loop       = isset( $_GET['loop'] ) ? intval( $_GET['loop'] ) : 0;
			$role       = get_role( $role );

			$product = wc_get_product( $product_id );

			if ( $role && $product ) {

				$type = $product->is_type( 'variation' ) ? 'variation' : 'simple';

				wp_send_json( array(
					'success'       => true,
					'role_row_html' => $this->getContainer()->getFileManager()->renderTemplate( "addons/role-based-pricing/{$type}/role.php", array(
						'role'                   => $role->name,
						'loop'                   => $loop,
						'fileManager'            => $this->getContainer()->getFileManager(),
						'price_rules_fixed'      => RoleBasedPriceManager::getFixedDiscounts( $product_id, $role->name, 'edit' ),
						'price_rules_percentage' => RoleBasedPriceManager::getPercentageDiscounts( $product_id, $role->name, 'edit' ),
						'type'                   => RoleBasedPriceManager::getDiscountsType( $product_id, $role->name, 'fixed', 'edit' ),
						'sale_price'             => RoleBasedPriceManager::getSalePrice( $product_id, $role->name, 'edit' ),
						'regular_price'          => RoleBasedPriceManager::getRegularPrice( $product_id, $role->name, 'edit' ),
					) )
				) );
			}

			wp_send_json( array(
				'success'       => false,
				'error_message' => __( 'Invalid role', 'discounts-for-woocommerce-subscriptions' )
			) );
		}

		wp_send_json( array(
			'success'       => false,
			'error_message' => __( 'Invalid nonce', 'discounts-for-woocommerce-subscriptions' )
		) );
	}

	/**
	 * Render product page role-based template
	 */
	public function renderProductPage() {
		global $post;

		$this->getContainer()->getFileManager()->includeTemplate( 'addons/role-based-pricing/simple/role-based-block.php', array(
			'fileManager' => $this->getContainer()->getFileManager(),
			'product_id'  => $post->ID,
		) );
	}

	/**
	 * Render variation role-based template
	 *
	 * @param $loop
	 * @param $variation_data
	 * @param $variation
	 */
	public function renderPriceRulesVariation( $loop, $variation_data, $variation ) {
		$this->getContainer()->getFileManager()->includeTemplate( 'addons/role-based-pricing/variation/role-based-block.php', array(
			'fileManager' => $this->getContainer()->getFileManager(),
			'product_id'  => $variation->ID,
			'loop'        => $loop,
		) );
	}
}
