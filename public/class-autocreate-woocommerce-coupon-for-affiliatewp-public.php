<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/jsphpl/autocreate-woocommerce-coupon-for-affiliatewp
 *
 * @package    Autocreate_WooCommerce_Coupon_for_AffiliateWP
 * @subpackage Autocreate_WooCommerce_Coupon_for_AffiliateWP/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Autocreate_WooCommerce_Coupon_for_AffiliateWP
 * @subpackage Autocreate_WooCommerce_Coupon_for_AffiliateWP/public
 * @author     Joseph Paul <mail@jsph.pl>
 */
class Autocreate_WooCommerce_Coupon_for_AffiliateWP_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @access   private
	 * @var      string    $autocreate_woocommerce_coupon_for_affiliatewp    The ID of this plugin.
	 */
	private $autocreate_woocommerce_coupon_for_affiliatewp;

	/**
	 * The version of this plugin.
	 *
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Which meta keys to include when copying template to actual coupon.
	 *
	 * @access	private
	 * @var		array	$meta_whitelist		Which meta keys to include when copying template to actual coupon.
	 */
	private $meta_whitelist = [
		'_mepr_coupons_should_expire',
		'_mepr_coupons_expires_on',
		'_mepr_coupons_usage_count',
		'_mepr_coupons_usage_amount',
		'_mepr_coupons_discount_type',
		'_mepr_coupons_discount_amount',
		'_mepr_coupons_valid_products',
		'_mepr_coupons_discount_mode',
		'_mepr_coupons_trial_days',
		'_mepr_coupons_first_payment_discount_type',
		'_mepr_coupons_first_payment_discount_amount',
	];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param      string    $autocreate_woocommerce_coupon_for_affiliatewp       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $autocreate_woocommerce_coupon_for_affiliatewp, $version ) {

		$this->autocreate_woocommerce_coupon_for_affiliatewp = $autocreate_woocommerce_coupon_for_affiliatewp;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Autocreate_WooCommerce_Coupon_for_AffiliateWP_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Autocreate_WooCommerce_Coupon_for_AffiliateWP_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->autocreate_woocommerce_coupon_for_affiliatewp, plugin_dir_url( __FILE__ ) . 'css/autocreate-woocommerce-coupon-for-affiliatewp-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Autocreate_WooCommerce_Coupon_for_AffiliateWP_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Autocreate_WooCommerce_Coupon_for_AffiliateWP_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->autocreate_woocommerce_coupon_for_affiliatewp, plugin_dir_url( __FILE__ ) . 'js/autocreate-woocommerce-coupon-for-affiliatewp-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Create a coupon for the specified affiliate
	 *
	 */
	public function create_coupon($affiliate_id, $code_length = 6)
	{
		global $wpdb;

		// Generate random code
		$coupon_code = static::random_code($code_length);
		$coupon_code = strtoupper($coupon_code);

		// Check if code already exists in db
		while (true)
		{
			$wpdb->get_results(
				"
				SELECT ID
				FROM $wpdb->posts
				WHERE
					post_type = 'memberpresscoupon'
					AND post_name = '$coupon_code'
				"
			);

			if (0 === $wpdb->num_rows)
			{
				// Unique code found
				break;
			}
			else
			{
				// Code exists, generate new one
				$coupon_code = static::random_code($code_length);
				$coupon_code = strtoupper($coupon_code);
			}
		}

		// Add coupon
		$coupon = [
			'post_title'	=> $coupon_code,
			'post_content'	=> '',
			'post_status'	=> 'publish',
			'post_author'	=> 1,
			'post_type'		=> 'memberpresscoupon'
		];
		$new_coupon_id = wp_insert_post( $coupon );

		// Attach to affiliate
		update_post_meta($new_coupon_id, 'affwp_discount_affiliate', $affiliate_id);
		update_post_meta($new_coupon_id, 'acwccawp_version', $this->version);

		// Fetch template meta
		$template_id = get_option('acwccawp_template_id');
		$template = get_post_meta($template_id);

		// Copy meta values over from template
		foreach ($this->meta_whitelist as $key) {
			$value = maybe_unserialize($template[$key][0]);
			update_post_meta($new_coupon_id, $key, $value);
		}

		// Copy over excerpt from template
		$template_excerpt = get_post($template_id)->post_excerpt;
		wp_update_post([
			'ID'			=> $new_coupon_id,
			'post_excerpt'	=> $template_excerpt
		]);
	}

	/**
	 * Delete all coupons for the specified affiliate
	 *
	 */
	public function delete_coupons($affiliate_id)
	{
		global $wpdb;

		$posts = $wpdb->get_results(
			"
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE
				meta_key = 'affwp_discount_affiliate'
				AND meta_value = $affiliate_id
			"
		);

		$post_ids = wp_list_pluck($posts, 'post_id');

		foreach ($post_ids as $post_id) {
			wp_delete_post($post_id, false);
		}
	}

	/**
	 * Hook that gets triggered after creation of a new affiliate
	 * Creates a coupon code for the new affiliate
	 *
	 */
	public function after_insert_affiliate($affiliate_id)
	{
		$length = get_option('acwccawp_code_length');
		$this->create_coupon($affiliate_id, $length);
	}

	/**
	 * Hook that gets triggered after deletion of an affiliate
	 * Deletes all coupons for the affiliate
	 *
	 */
	public function after_delete_affiliate($affiliate_id, $delete_data)
	{
		if ($delete_data)
		{
			$this->delete_coupons($affiliate_id);
		}
	}

	/**
	 * Generate a random coupon code of given length
	 *
	 * @return string a random code of given length
	 */
	public static function random_code($length = 6, $chars = '0123456789abcdefghijklmnopqrstuvwxyz')
	{
		$chars_len = strlen($chars);
		$result = '';

		for ($i = 0; $i < $length; $i++) {
			$result .= $chars[rand(0, $chars_len - 1)];
		}

		return $result;
	}

}
