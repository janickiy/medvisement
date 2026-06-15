<?php namespace MeowCrew\SubscriptionsDiscounts\Settings;

use MeowCrew\SubscriptionsDiscounts\Addons\Coupons\CouponsAddon;
use MeowCrew\SubscriptionsDiscounts\Integrations\Plugins\AllProductsForSubscriptions\AllProductForSubscriptions;
use MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing\RoleBasedPricingAddon;
use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\SubscriptionsDiscountsPlugin;

/**
 * Class Settings
 *
 * @package MeowCrew\SubscriptionsDiscounts\Settings
 */
class Settings {

	const SETTINGS_PREFIX = 'subscription_discounts_';

	const SETTINGS_PAGE = 'subscription_discounts_settings';

	use ServiceContainerTrait;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Settings constructor.
	 *
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Handle updating settings
	 */
	public function updateSettings() {
		woocommerce_update_options( $this->settings );
	}

	/**
	 * Init all settings
	 */
	public function initSettings() {
		$this->settings = array(
			array(
				'title' => __( 'Discounts for WooCommerce Subscriptions Settings',
					'discounts-for-woocommerce-subscriptions' ),
				'desc'  => __( 'This section controls how the discounts for woocommerce subscriptions will look and behave at your store.',
					'discounts-for-woocommerce-subscriptions' ),
				'id'    => self::SETTINGS_PREFIX . 'options',
				'type'  => 'title',
			),
			array(
				'title'   => __( 'Show discount table on product page', 'discounts-for-woocommerce-subscriptions' ),
				'id'      => self::SETTINGS_PREFIX . 'display',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'    => __( 'Discounts explainer', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . 'table_title',
				'type'     => 'text',
				'default'  => '',
				'desc'     => __( 'Write down a short explanation of how your discounts work, which will be displayed right above the discount table on the product page.',
					'discounts-for-woocommerce-subscriptions' ),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Table position', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . 'position_hook',
				'type'     => 'select',
				'options'  => array(
					'woocommerce_before_add_to_cart_button'     => __( 'Above buy button',
						'discounts-for-woocommerce-subscriptions' ),
					'woocommerce_after_add_to_cart_button'      => __( 'Below buy button',
						'discounts-for-woocommerce-subscriptions' ),
					'woocommerce_before_add_to_cart_form'       => __( 'Above add to cart form',
						'discounts-for-woocommerce-subscriptions' ),
					'woocommerce_after_add_to_cart_form'        => __( 'Below add to cart form',
						'discounts-for-woocommerce-subscriptions' ),
					'woocommerce_single_product_summary'        => __( 'Above product title',
						'discounts-for-woocommerce-subscriptions' ),
					'woocommerce_before_single_product_summary' => __( 'Before product summary',
						'discounts-for-woocommerce-subscriptions' ),
					'woocommerce_after_single_product_summary'  => __( 'After product summary',
						'discounts-for-woocommerce-subscriptions' ),
				),
				'desc'     => __( 'Pick up a suitable location to place a discount table on the subscription product page.',
					'discounts-for-woocommerce-subscriptions' ),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Renewal sequence number column title', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . 'head_quantity_text',
				'type'     => 'text',
				'default'  => __( 'Subscription {period}', 'discounts-for-woocommerce-subscriptions' ),
				'desc'     => __( 'Name of the first column in the table, which indicates when the discount will be applied. Use a {period} variable to clearly indicate at what time discounts start.',
					'discounts-for-woocommerce-subscriptions' ),
				'desc_tip' => false,
			),
			array(
				'title'    => __( 'Price column title', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . 'head_price_text',
				'type'     => 'text',
				'default'  => __( 'Price', 'discounts-for-woocommerce-subscriptions' ),
				'desc'     => __( 'Text for the last column, where the subscription renewal price (incl. applied discount) is indicated.',
					'discounts-for-woocommerce-subscriptions' ),
				'desc_tip' => true,
			),
			array(
				'title'   => __( 'Show column with a percentage discount rate',
					'discounts-for-woocommerce-subscriptions' ),
				'id'      => self::SETTINGS_PREFIX . 'show_discount_column',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'    => __( 'Discount column title', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . 'head_discount_text',
				'type'     => 'text',
				'default'  => __( 'Discount (%)', 'discounts-for-woocommerce-subscriptions' ),
				'desc'     => __( 'ext for the second column, where clients can see percentage discount for each sequence.',
					'discounts-for-woocommerce-subscriptions' ),
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => self::SETTINGS_PREFIX . 'options',
			),

			// Role-based Addon
			array(
				'title' => __( 'Role-based pricing', 'discounts-for-woocommerce-subscriptions' ),
				'id'    => self::SETTINGS_PREFIX . 'role_based_pricing_addon_section',
				'type'  => 'title',
			),
			array(
				'title'    => __( 'Role-based discounts', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . RoleBasedPricingAddon::SETTING_ENABLE_KEY,
				'type'     => 'checkbox',
				'desc'     => __( 'apply specialized discounts for users with specific roles (has higher priority than regular discounts).',
					'discounts-for-woocommerce-subscriptions' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => self::SETTINGS_PREFIX . 'role_based_pricing_addon_section',
			),

			// All Products For Subscriptions
			array(
				'title' => __( 'All Products for Subscriptions', 'discounts-for-woocommerce-subscriptions' ),
				'id'    => self::SETTINGS_PREFIX . 'all_products_for_subscriptions_addon_section',
				'type'  => 'title',
			),
			array(
				'title'    => __( 'Enable All Products for Subscriptions Add-on',
					'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . AllProductForSubscriptions::SETTING_ENABLE_KEY,
				'type'     => 'checkbox',
				'desc'     => __( 'Will enable discount table management for Simple, Variable, and Bundle product types. Please, keep the discount table empty for products w/o subscription plan.',
					'discounts-for-woocommerce-subscriptions' ),
				'default'  => 'no',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => self::SETTINGS_PREFIX . 'all_products_for_subscriptions_addon_section',
			),

			// Role-based Addon
			array(
				'title' => __( 'Subscription discounts for coupons', 'discounts-for-woocommerce-subscriptions' ),
				'id'    => self::SETTINGS_PREFIX . 'coupon_addon_section',
				'type'  => 'title',
			),
			array(
				'title'    => __( 'Subscription discounts for coupons', 'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . CouponsAddon::SETTING_ENABLE_KEY,
				'type'     => 'checkbox',
				'desc'     => __( 'apply specialized discounts users (or admin) uses a special coupon.',
					'discounts-for-woocommerce-subscriptions' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Override discounts by a coupon',
					'discounts-for-woocommerce-subscriptions' ),
				'id'       => self::SETTINGS_PREFIX . CouponsAddon::SETTING_OVERRIDE_DISCOUNTS_KEY,
				'type'     => 'checkbox',
				'desc'     => __( 'Coupon discounts rules will have a higher priority than rules set directly in products.', 'discounts-for-woocommerce-subscriptions' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => self::SETTINGS_PREFIX . 'all_products_for_subscriptions_addon_section_end',
			),
		);
	}

	/**
	 * Register hooks
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'initSettings' ) );

		add_filter( 'woocommerce_settings_tabs_' . self::SETTINGS_PAGE,
			array( $this, 'addSubscriptionDiscountsTableSettings' ) );
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'addSettingsTab' ), 50 );
		add_action( 'woocommerce_update_options_' . self::SETTINGS_PAGE, array( $this, 'updateSettings' ) );
	}

	/**
	 * Add own settings tab
	 *
	 * @param  array  $settings_tabs
	 *
	 * @return mixed
	 */
	public static function addSettingsTab( $settings_tabs ) {

		$settings_tabs[ self::SETTINGS_PAGE ] = __( 'Subscriptions Discounts',
			'discounts-for-woocommerce-subscriptions' );

		return $settings_tabs;
	}

	/**
	 * Add settings to WooCommerce
	 */
	public function addSubscriptionDiscountsTableSettings() {

		wp_enqueue_script( 'quantity-table-settings-js',
			$this->getContainer()->getFileManager()->locateAsset( 'admin/settings.js' ), array( 'jquery' ),
			SubscriptionsDiscountsPlugin::VERSION );

		woocommerce_admin_fields( $this->settings );
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public function getAll() {
		return array(
			'display'              => $this->get( 'display', 'yes' ),
			'show_discount_column' => $this->get( 'show_discount_column', 'yes' ),
			'position_hook'        => $this->get( 'position_hook', 'woocommerce_after_add_to_cart_button' ),
			'head_quantity_text'   => $this->get( 'head_quantity_text',
				__( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ) ),
			'head_price_text'      => $this->get( 'head_price_text',
				__( 'Price', 'discounts-for-woocommerce-subscriptions' ) ),
			'display_type'         => $this->get( 'display_type', 'table' ),
			'table_title'          => $this->get( 'table_title', '' ),
			'head_discount_text'   => $this->get( 'head_discount_text',
				__( 'Discount (%)', 'discounts-for-woocommerce-subscriptions' ) ),
		);
	}

	/**
	 * Get setting by name
	 *
	 * @param  string  $option_name
	 * @param  mixed  $default
	 *
	 * @return mixed
	 */
	public function get( $option_name, $default = null ) {
		return get_option( self::SETTINGS_PREFIX . $option_name, $default );
	}

	/**
	 * Get url to settings page
	 *
	 * @return string
	 */
	public function getLink() {
		return admin_url( 'admin.php?page=wc-settings&tab=' . self::SETTINGS_PAGE );
	}
}
