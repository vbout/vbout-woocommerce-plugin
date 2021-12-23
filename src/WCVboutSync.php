<?php

namespace App;

use App\Libraries\Vbout\Services\EcommerceWS;

/**
 * WCVboutSync class
 *
 */
class WCVboutSync
{
	private $domain = '';
	private $apiKey;
	private $vboutApp;
	private static $settings = null;

	public function __construct($domain, $apiKey) {
		$this->domain = $domain;
		$this->apiKey = $apiKey;

		try {
			// Load email marketing object
			$this->vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));
		} catch (\Exception $e) {
			error_log('Caught exception: "' . $e->getMessage() . '" on "' . $e->getFile() . '" line ' . $e->getLine());
		}
	}

	public static function SyncAllCustomers($domain, $apiKey) {
		$count = 0;

		$sync = new self( $domain, $apiKey );
		$sync->updateSettings('customers', array(
			'status'	=> 'in-progress',
		));

		$users = get_users(['role__in' => 'customer']);

		if (count($users) > 0) {

			foreach ($users as $user) {
				$count++;

				$sync->syncCustomer( $user->data );
			}
		}

		$sync->updateSettings('customers', array(
			'status'	=> 'done',
		));

		return $count;
	}

	public static function SyncAllProducts($domain, $apiKey) {
		$count = 0;

		$sync = new self( $domain, $apiKey );
		$sync->updateSettings('products', array(
			'status'	=> 'in-progress',
		));

		$wooArgs = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		);

		# Woocommerce Product
		$loop = new \WP_Query($wooArgs);
		if ($loop->have_posts()) {
			foreach ($loop->posts as $productPost) {
				$count++;

				$sync->syncProduct( $productPost, 'product' );
			}
		}

		# LearnPress Courses
		if (class_exists('LearnPress')) {
			$LpArgs = array(
				'post_type' => 'lp_course',
				'post_status' => 'publish',
				'posts_per_page' => -1,
			);
			$loop = new \WP_Query($LpArgs);
			if ($loop->have_posts()) {
				foreach ($loop->posts as $productPost) {
					$count++;

					$sync->syncProduct( $productPost, 'LearnPress' );
				}
			}
		}

		$sync->updateSettings('products', array(
			'status'	=> 'done',
		));

		return $count;
	}

	public static function SyncAllOrders($domain, $apiKey) {
		$count = 0;

		$sync = new self( $domain, $apiKey );
		$sync->updateSettings('orders', array(
			'status'	=> 'in-progress',
		));

		$orders = wc_get_orders(array(
			'limit'    => -1,
			'type'   => 'shop_order'
		));

		if ( count($orders) > 0 ) {
			foreach ($orders as $order) {
				$count++;

				$sync->syncOrder( $order );
			}
		}

		$sync->updateSettings('orders', array(
			'status'	=> 'done',
		));

		return $count;
	}

	public function syncCustomer($data) {
		$customerData = WCVboutSyncData::getCustomerInfoData( $data, null, false );

		$customerData['sync'] = 1;
		$customerData['domain'] = $this->domain;

		return $this->vboutApp->Customer($customerData, 1);
	}

	public function syncProduct($data, $type) {

		$productID = null;
		$productCategoryID = 'N/A';
		$productCategoryName = 'N/A';

		switch( $type ) {
			case 'LearnPress':
				$productID = $data->ID;

				$productName = $data->post_title;
				$productDescription = $data->post_content;
				$productSku = null;
				$productPrice = (get_post_meta($productID, '_lp_price', true)) ? get_post_meta($productID, '_lp_price', true) : '0.0';
				$productDiscountPrice = (get_post_meta($productID, '_lp_sale_price', true)) ? get_post_meta($productID, '_lp_sale_price', true) : '0.0';
				$terms = get_the_terms($productID, 'course_category');
				break;
			case 'product':
				$productID = $data->ID;
				$product = wc_get_product($productID);

				$productName = $product->get_name();
				$productDescription = $product->get_description();
				$productSku = $product->get_sku();
				$productPrice = ($product->get_regular_price()) ? $product->get_regular_price() : (($product->get_price()) ? $product->get_price() : '0.0');
				$productDiscountPrice = $product->get_sale_price() ? $product->get_sale_price() : '0.0';
				$terms = get_the_terms($productID, 'product_cat');
				break;
		}

		if ( !empty($productID) ) {

			if (count($terms) > 0) {
				$productCategoryID = $terms[0]->term_id;
				$productCategoryName = $terms[0]->name;
			}

			$productData = array(
				'productid' => $productID,
				'name' => $productName,
				'price' => $productPrice,
				'description' => $productDescription,
				'discountprice' => $productDiscountPrice,
				'currency' => get_woocommerce_currency(),
				'sku' => $productSku,
				'categoryid' => $productCategoryID,
				'category' => $productCategoryName,
				'link' => get_permalink($productID),
				'image' => get_the_post_thumbnail_url($productID, 'full'),
			);

			$productData['sync'] = 1;
			$productData['domain'] = $this->domain;

			return $this->vboutApp->Product($productData, 1);
		}

		return null;
	}

	public function syncOrder($order) {

		$orderData = WCVboutSyncData::getOrderData( $order, true );

		$orderData['sync'] = 1;
		$orderData['domain'] = $this->domain;

		$orderData['cartid'] = $orderData['orderid'];

		$orderData['cartcurrency'] = get_woocommerce_currency();
		$orderData['storename'] = $_SERVER['HTTP_HOST'];
		$orderData['abandonurl'] = $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		$orderData['cartproducts'] = array();

		// attach cart's items
		foreach ( $order->get_items() as $item_id => $item ) {
			$cartItemData = WCVboutSyncData::getCartItemData( $item );

			$orderData['cartproducts'][] = $cartItemData;
		}

		return $this->vboutApp->CartOrderCreate($orderData, 1);
	}

	public function updateSettings($section, array $data = array()) {
		return self::updateSyncSettings($section, $data);
	}

	public static function updateSyncSettings($section, array $data = array()) {
		// load settings
		self::getSyncSettings();

		$updates = is_array($section) ? $section : array(
			$section => $data,
		);

		foreach ($updates as $section => $data) {
			if (empty($data) || !is_array($data)) {
				continue;
			}

			if (empty(self::$settings[$section])) {
				self::$settings[$section] = array();
			}

			foreach ($data as $flag => $value) {
				self::$settings[$section][$flag] = $value;
			}
		}

		update_option('woocommerce_' . VBOUT_WOOCOMMERCE_INTEGRATION_ID . '_sync_settings', self::$settings);

		return self::$settings;
	}

	public function getSettings($clearCache = false) {
		return self::getSyncSettings($clearCache);
	}

	public static function getSyncSettings($clearCache = false) {
		if ($clearCache === true || self::$settings === null) {
			self::$settings = get_option('woocommerce_' . VBOUT_WOOCOMMERCE_INTEGRATION_ID . '_sync_settings');
			self::$settings = !empty(self::$settings) ? self::$settings : array();
		}

		return self::$settings;
	}
}
