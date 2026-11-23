<?php
/**
 * Plugin Name: LeanCart Free Shipping Progress Bar
 * Plugin URI:  https://sleepclinicsau.com/
 * Description: Shows customers live how much they need to reach the free-shipping threshold on the cart and checkout pages. Raises the average order value.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: free-shipping-progress
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_FREE_SHIPPING_PROGRESS_DEMO_SITE' ) ) {
	define( 'LEANCART_FREE_SHIPPING_PROGRESS_DEMO_SITE', 'https://sleepclinicsau.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Free_Shipping_Progress {

	const SLUG = 'free-shipping-progress';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Free Shipping Progress Bar',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_FREE_SHIPPING_PROGRESS_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'free-shipping-progress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Free Shipping Progress Bar' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'free-shipping-progress' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'threshold', __('Threshold amount (automatic if blank)', 'free-shipping-progress'), __('Example: 500', 'free-shipping-progress'), 'number' );
		LeanCart_Framework::field_text( 'message', __('Success message', 'free-shipping-progress'), __('Shown when the threshold is reached.', 'free-shipping-progress'), 'text' );
	}


	public static function hooks() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'notice' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'notice' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	public static function styles() {
		if ( ! ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) ) { return; }
		wp_register_style( 'lc-free-shipping-progress', false, array(), '1.0' );
		wp_enqueue_style( 'lc-free-shipping-progress' );
		wp_add_inline_style( 'lc-free-shipping-progress', '.lc-bar{height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden}.lc-bar span{display:block;height:100%;background:#3858e9;transition:width .3s}' );
	}

	public static function fragments( $fragments ) {
		ob_start();
		self::notice();
		$html = ob_get_clean();
		if ( $html ) { $fragments['.lc-shipping-progress'] = $html; }
		return $fragments;
	}

	private static function threshold() {
		$raw = LeanCart_Framework::setting( 'threshold' );
		$t = ( '' === $raw || ! is_numeric( $raw ) ) ? 0 : (float) $raw;
		if ( $t > 0 ) { return $t; }
		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Shipping_Zone' ) ) { return 0; }
		// Find the smallest active free-shipping threshold from WC shipping settings (including Zone 0).
		$zones = WC_Shipping_Zones::get_zones();
		$zero  = new WC_Shipping_Zone( 0 );
		$zones[] = array( 'shipping_methods' => $zero->get_shipping_methods() );
		$min = 0;
		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( ! isset( $method->id, $method->enabled ) || 'free_shipping' !== $method->id || 'yes' !== $method->enabled ) { continue; }
				if ( isset( $method->instance_settings['min_amount'] ) && (float) $method->instance_settings['min_amount'] > 0 ) {
					$v = (float) $method->instance_settings['min_amount'];
					$min = $min ? min( $min, $v ) : $v;
				}
			}
		}
		return $min;
	}

	public static function notice() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || WC()->cart->is_empty() ) { return; }
		$threshold = self::threshold();
		if ( $threshold <= 0 ) { return; }
		$total = (float) WC()->cart->get_displayed_subtotal();
		$left  = max( 0, $threshold - $total );
		$pct   = min( 100, round( $total / $threshold * 100 ) );
		echo '<div class="lc-shipping-progress" style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;border-radius:6px;margin:16px 0">';
		if ( $left > 0 ) {
			echo '<p style="margin:0 0 6px"><strong>' . wp_kses_post( sprintf( __( 'Add %s more to get free shipping.', 'free-shipping-progress' ), LeanCart_Framework::money( $left ) ) ) . '</strong></p>';
		} else {
			echo '<p style="margin:0 0 6px"><strong>' . esc_html( LeanCart_Framework::setting( 'message', __( 'Congratulations, shipping is free!', 'free-shipping-progress' ) ) ) . '</strong></p>';
		}
		echo '<div class="lc-bar"><span style="width:' . esc_attr( $pct ) . '%"></span></div></div>';
	}


}

LeanCart_Free_Shipping_Progress::init();

