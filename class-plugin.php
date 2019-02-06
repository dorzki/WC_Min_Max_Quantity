<?php
/**
 * Main plugin file.
 *
 * @package    dorzki\WooCommerce\Min_Max_Quantity
 * @subpackage Plugin
 * @author     Dor Zuberi <webmaster@dorzki.co.il>
 * @link       https://www.dorzki.co.il
 * @version    1.0.0
 */

namespace dorzki\WooCommerce\Min_Max_Quantity;

// Block if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class Plugin
 *
 * @package dorzki\WooCommerce\Min_Max_Quantity
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var null|Plugin
	 */
	private static $instance = null;


	/* ------------------------------------------ */


	/**
	 * Plugin constructor.
	 */
	public function __construct() {

		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_min_max_limit_tab' ] );

		add_action( 'woocommerce_product_data_panels', [ $this, 'register_min_max_limit_fields' ] );
		add_action( 'woocommerce_process_product_meta_simple', [ $this, 'save_min_max_values' ] );

		add_filter( 'woocommerce_quantity_input_min', [ $this, 'set_minimum_quantity_limit' ], 10, 2 );
		add_filter( 'woocommerce_quantity_input_max', [ $this, 'set_maximum_quantity_limit' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'check_min_max_limit' ], 10, 3 );

		add_filter( 'woocommerce_quantity_input_args', [ $this, 'set_min_max_values_in_cart' ], 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', [ $this, 'check_cart_min_max_limit' ], 10, 4 );

	}


	/* ------------------------------------------ */


	/**
	 * Add new tab to woocommerce product editing screen.
	 *
	 * @param array $tabs list of tabs and configuration.
	 *
	 * @return array
	 */
	public function add_min_max_limit_tab( $tabs ) {

		$tabs['product_min_max'] = [
			'label'  => __( 'Min Max Quantity', 'dorzki-wc-min-max-quantity' ),
			'target' => 'min_max_options',
			'class'  => [ 'show_if_simple' ],
		];

		return $tabs;

	}


	/**
	 * Register the new minimum maximum fields and display them on the new tab.
	 */
	public function register_min_max_limit_fields() {

		echo '<div id="min_max_options" class="panel woocommerce_options_panel">';
		echo '	<div class="options_group">';

		woocommerce_wp_text_input( [
			'id'                => '_min_quantity',
			'label'             => __( 'Minimum Quantity', 'dorzki-wc-min-max-quantity' ),
			'desc_tip'          => 'true',
			'description'       => __( 'Minimum quantity to purchase, set 1 for no minimum limitation.', 'dorzki-wc-min-max-quantity' ),
			'type'              => 'number',
			'custom_attributes' => [
				'min'  => 1,
				'step' => 1,
			],
		] );

		woocommerce_wp_text_input( [
			'id'                => '_max_quantity',
			'label'             => __( 'Maximum Quantity', 'dorzki-wc-min-max-quantity' ),
			'desc_tip'          => 'true',
			'description'       => __( 'Maximum quantity to purchase, set 0 for no maximum limitation.', 'dorzki-wc-min-max-quantity' ),
			'type'              => 'number',
			'custom_attributes' => [
				'min'  => 0,
				'step' => 1,
			],
		] );

		echo '	</div>';
		echo '</div>';

	}


	/**
	 * Save the minimum maximum values to the database.
	 *
	 * @param int $product_id current product id.
	 */
	public function save_min_max_values( $product_id ) {

		update_post_meta( $product_id, '_min_quantity', $_POST['_min_quantity'] );
		update_post_meta( $product_id, '_max_quantity', $_POST['_max_quantity'] );

	}


	/* ------------------------------------------ */


	/**
	 * Set product minimum quantity limit.
	 *
	 * @param int        $min     minimum product quantity.
	 * @param WP_Product $product current product object.
	 *
	 * @return int
	 */
	public function set_minimum_quantity_limit( $min, $product ) {

		$min_qty = get_post_meta( $product->get_ID(), '_min_quantity', true );

		if ( $min_qty ) {

			$min = (int) $min_qty;

		}

		return $min;

	}


	/**
	 * Set product maximum quantity limit.
	 *
	 * @param int        $max     maximum product quantity.
	 * @param WP_Product $product current product object.
	 *
	 * @return int
	 */
	public function set_maximum_quantity_limit( $max, $product ) {

		$max_qty = get_post_meta( $product->get_ID(), '_max_quantity', true );

		if ( $max_qty ) {

			$max = (int) $max_qty;

		}

		return $max;

	}


	/**
	 * Check if trying to add more or less then configured.
	 *
	 * @param bool $passed     passed validation.
	 * @param int  $product_id product id number.
	 * @param int  $quantity   amount added.
	 *
	 * @return bool
	 */
	public function check_min_max_limit( $passed, $product_id, $quantity ) {

		$product = wc_get_product( $product_id );

		if ( $product->is_type( 'simple' ) ) {

			$min = get_post_meta( $product_id, '_min_quantity', true );
			$max = get_post_meta( $product_id, '_max_quantity', true );

			if ( $quantity < $min ) {

				$passed = false;

				wc_add_notice( sprintf( __( 'You need a quantity of at least %s to add the product to cart.', 'dorzki-wc-min-max-quantity' ), "<strong>{$min}</strong>" ), 'error' );

			} elseif ( ! empty( $max ) && $quantity > $max ) {

				$passed = false;

				wc_add_notice( sprintf( __( 'You can purchase a maximum of %s units of this product.', 'dorzki-wc-min-max-quantity' ), "<strong>{$max}</strong>" ), 'error' );

			}

		}

		return $passed;

	}


	/* ------------------------------------------ */


	/**
	 * Add quantity filed minimum and maximum values according to configuration.
	 *
	 * @param array      $args    quantity field arguments.
	 * @param WC_Product $product current product object.
	 *
	 * @return array
	 */
	public function set_min_max_values_in_cart( $args, $product ) {

		if ( $product->is_type( 'simple' ) ) {

			$min = get_post_meta( $product->get_ID(), '_min_quantity', true );
			$max = get_post_meta( $product->get_ID(), '_max_quantity', true );

			$args['min_value'] = $min;
			$args['max_value'] = ( ! empty( $max ) ) ? $max : $args['max_value'];

		}

		return $args;

	}


	/**
	 * Check if trying to add more or less then configured on cart page.
	 *
	 * @param bool   $passed        passed validation.
	 * @param string $cart_item_key cart item has id.
	 * @param array  $cart_item     cart id data.
	 * @param int    $quantity      product quantity.
	 *
	 * @return bool
	 */
	public function check_cart_min_max_limit( $passed, $cart_item_key, $cart_item, $quantity ) {

		if ( $cart_item['data']->is_type( 'simple' ) ) {

			$min = get_post_meta( $cart_item['data']->get_ID(), '_min_quantity', true );
			$max = get_post_meta( $cart_item['data']->get_ID(), '_max_quantity', true );

			if ( $quantity < $min ) {

				$passed = false;

				wc_add_notice( sprintf( __( 'You need a quantity of at least %s to add the product to cart.', 'dorzki-wc-min-max-quantity' ), "<strong>{$min}</strong>" ), 'error' );

			} elseif ( ! empty( $max ) && $quantity > $max ) {

				$passed = false;

				wc_add_notice( sprintf( __( 'You can purchase a maximum of %s units of this product.', 'dorzki-wc-min-max-quantity' ), "<strong>{$max}</strong>" ), 'error' );

			}

		}

		if ( ! $passed ) {
			add_filter( 'woocommerce_update_cart_action_cart_updated', '__return_false' );
		}

		return $passed;

	}


	/* ------------------------------------------ */


	/**
	 * Retrieve plugin instance.
	 *
	 * @return Plugin|null
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {

			self::$instance = new self();

		}

		return self::$instance;

	}

}

// initiate plugin.
Plugin::get_instance();
