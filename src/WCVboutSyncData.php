<?php

namespace App;

/**
 * WCVboutSyncData class
 *
 */
class WCVboutSyncData
{

	public static function getCartItemData( $cart_item ) {
		$productID = $cart_item['product_id'];
		$productQuantity = $cart_item['quantity'];
		$productVariations = array();
		$categoryID = '';
		$categoryName = '';
		$image = '';
		$category = null;

		try {
			$productObj = new \WC_Product($productID);
			$productName = $productObj->get_name();
			$productDescription = $productObj->get_description();
			$productSku = $productObj->get_sku();
			$productPrice = ($productObj->get_price()) ? $productObj->get_price() : '0.0';
			$productDiscountPrice = ($productObj->get_sale_price()) ? $productObj->get_sale_price() : '0.0';

			if ($cart_item['variation_id'] != 0) {
				$variations = self::getCartItemVariations( $cart_item['variation_id'], true );

				$productVariations = $variations['variations'];

				if( !empty($variations['image']) ) {
					$image = $variations['image'];
				}
			}

			if( empty( $image ) ) {
				$image = get_the_post_thumbnail_url($productID, 'full');
			}

			$category = get_the_terms($productID, 'product_cat');
		}
		catch (\Exception $ex) {
			// Non Woocommerce Products
			# LearPress
			$postType = get_post_type($productID);
			if (class_exists('LearnPress') && $postType == 'lp_course') {
				$productObj = $cart_item['data']->post;
				$productName = $productObj->post_title;
				$productDescription = $productObj->post_content;
				$productSku = null;
				$productDiscountPrice = get_post_meta($productID, '_lp_sale_price', true);
				if ($productDiscountPrice) {
					$productPrice = $productDiscountPrice;
				}
				else {
					$productDiscountPrice = '0.0';
					$productPrice = (get_post_meta($productID, '_lp_price', true)) ? get_post_meta($productID, '_lp_price', true) : '0.0';
				}
				$image = get_the_post_thumbnail_url($productID, 'full');
				$category = get_the_terms($productID, 'course_category');
			}
		}

		if (is_array($category)) {
			$categoryID = $category[0]->term_id;
			$categoryName = $category[0]->name;
		}

		$productData = array(
			'productid' => (string)$productID,
			'name' => $productName,
			'price' => $productPrice,
			'description' => $productDescription,
			'discountprice' => $productDiscountPrice,
			'currency' => get_woocommerce_currency(),
			'quantity' => (string)$productQuantity,
			'categoryid' => $categoryID,
			'variation' => $productVariations,
			'category' => $categoryName,
			'sku' => $productSku,
			'link' => get_permalink($productID),
			'image' => $image,
		);

		return $productData;
	}

	public static function getCartItemVariations( $productVariationId, $getProductVariationImage ) {
		$response = array(
			'variations'	=> array(),
			'image'			=> '',
		);

		$product = wc_get_product( $productVariationId );
		if ($product) {
			$variations = $product->get_attributes();

			foreach ($variations as $key => $variation) {
				if (strpos($key, 'pa_') !== false) {
					$_key = explode('pa_', $key)[1];

					$response['variations'][ $_key ] = $variation;
				}
			}

			if($getProductVariationImage) {
				$response['image'] = wp_get_attachment_image_url($product->get_image_id(), 'full');
			}
		}

		return $response;
	}

	public static function getCustomerInfoData( $user, $order, $extendedData ) {

		// Get the $user
		if( !empty( $user ) && is_numeric( $user )) {
			$user = get_userdata( $user );
		}

		$userID = $user ? $user->ID : null;

		// template
		$customerInfo = array();

		// data from specific place
		if ($user) {
			$customerInfo['email'] = $user->user_email;

			$customerInfo['firstname'] = get_user_meta($userID, 'first_name', true);
			$customerInfo['lastname'] = get_user_meta($userID, 'last_name', true);

			$customerInfo['company'] = get_user_meta($userID, 'billing_company', true);

			$customerInfo['country_code'] = get_user_meta($userID, 'billing_country', true);

			if( $extendedData ) {
				$customerInfo['state_code'] = get_user_meta($userID, 'billing_state', true);

				$customerInfo['city'] = get_user_meta($userID, 'billing_city', true);
				$customerInfo['postcode'] = get_user_meta($userID, 'billing_postcode', true);
				$customerInfo['address_1'] = get_user_meta($userID, 'billing_address_1', true);
				$customerInfo['address_2'] = get_user_meta($userID, 'billing_address_2', true);
			}
		}
		else if( $order ) {
			$customerInfo['firstname'] = $order->get_billing_first_name();
			$customerInfo['lastname'] = $order->get_billing_last_name();
			$customerInfo['email'] = $order->get_billing_email();

			$customerInfo['company'] = $order->get_billing_company();

			$customerInfo['country_code'] = $order->get_billing_country();

			if( $extendedData ) {
				$customerInfo['state_code'] = $order->get_billing_state();

				$customerInfo['city'] = $order->get_billing_city();
				$customerInfo['postcode'] = $order->get_billing_postcode();
				$customerInfo['address_1'] = $order->get_billing_address_1();
				$customerInfo['address_2'] = $order->get_billing_address_2();
			}
		}

		// data if available in $user
		if($user) {
			if (empty( $customerInfo['phone'] )) {
				$customerInfo['phone'] = get_user_meta($userID, 'phone_number', true);
			}
			if (empty($customerInfo['phone'])) {
				$customerInfo['phone'] = get_user_meta($userID, 'user_phone', true);
			}
			if (empty($customerInfo['phone'])) {
				$customerInfo['phone'] = get_user_meta($userID, 'phone', true);
			}
		}

		// data if available in $order
		if( $order ) {
			if (empty($customerInfo['phone'])) {
				$customerInfo['phone'] = $order->get_billing_phone();
			}
		}

		self::attachCountryStateNames($customerInfo, 'country_code', 'country', 'state_code', 'state');

		// important, only return elements with values
		return array_filter( $customerInfo );
	}

	public static function getOrderData($order, $includeCustomerInfo = true) {

		$orderData = array(
			'orderid' => $order->get_id(),
			'paymentmethod' => $order->get_payment_method(),
			'grandtotal' => $order->get_total(),
			'orderdate' => strtotime($order->get_date_created()),
			'shippingmethod' => $order->get_shipping_method(),
			'shippingcost' => $order->get_shipping_total(),
			'subtotal' => $order->get_subtotal(),
			'discountvalue' => $order->get_total_discount(),
			'taxcost' => $order->get_tax_totals(),
			'otherfeecost' => $order->get_fees(),
			'currency' => $order->get_currency(),
			'status' => $order->get_status(),
			'notes' => $order->get_customer_note(),
			'storename' => $_SERVER['HTTP_HOST'],
			'billinginfo' => array(
				'firstname' => $order->get_billing_first_name(),
				'lastname' => $order->get_billing_last_name(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
				'company' => $order->get_billing_company(),
				'address' => $order->get_billing_address_1(),
				'address2' => $order->get_billing_address_2(),
				'city' => $order->get_billing_city(),
				'statecode' => $order->get_billing_state(),
				'countrycode' => $order->get_billing_country(),
				'zipcode' => $order->get_billing_postcode(),
			),
			'shippinginfo' => array(
				'firstname' => $order->get_shipping_first_name(),
				'lastname' => $order->get_shipping_last_name(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
				'company' => $order->get_shipping_company(),
				'address' => $order->get_shipping_address_1(),
				'address2' => $order->get_shipping_address_2(),
				'city' => $order->get_shipping_city(),
				'statecode' => $order->get_shipping_state(),
				'countrycode' => $order->get_shipping_country(),
				'zipcode' => $order->get_shipping_postcode(),
			),
		);

		self::attachCountryStateNames($orderData['billinginfo'], 'countrycode', 'countryname', 'statecode', 'statename');
		self::attachCountryStateNames($orderData['shippinginfo'], 'countrycode', 'countryname', 'statecode', 'statename');

		if( $includeCustomerInfo ) {
			$orderData['customerinfo'] = self::getCustomerInfoData( $order->get_customer_id(), $order, false );
		}

		return $orderData;
	}

	public static function attachCountryStateNames(array &$dataArray, $countryCode, $countryName, $stateCode = null, $stateName = null) {

		if( !empty( $dataArray[ $countryCode ] ) ) {
			$country = $dataArray[ $countryCode ];

			if( !empty(WC()->countries->countries[ $country ]) ) {
				$dataArray[ $countryName ] = WC()->countries->countries[ $country ];
			}
			else if( empty($dataArray[ $countryName ]) ) {
				$dataArray[ $countryName ] = $country;
			}

			if( $stateCode && !empty( $dataArray[ $stateCode ] ) ) {
				$state = $dataArray[ $stateCode ];

				$states = WC()->countries->get_states( $country );

				if( !empty($states[ $state ]) ) {
					$dataArray[ $stateName ] = $states[ $state ];
				}
				else if( empty($dataArray[ $stateName ]) ) {
					$dataArray[ $stateName ] = $state;
				}
			}
		}
	}
}
