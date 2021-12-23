<?php

namespace App;

use App\Libraries\Vbout\Services\EcommerceWS;

class WCVboutIntegration extends \WC_Integration
{
    /**
     * WCVboutIntegration constructor.
     */
    public function __construct()
    {
        $this->id = VBOUT_WOOCOMMERCE_INTEGRATION_ID;
        $this->method_description = __('WooCommerce Vbout Integration Settings', 'woocommerce-vbout-integration');

		$page = !empty( $_REQUEST['page'] ) ? $_REQUEST['page'] : '';
		$section = !empty( $_REQUEST['section'] ) ? $_REQUEST['section'] : '';
		$tab = !empty( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : '';

		if($page == 'wc-settings' && $tab == 'integration' && $section == $this->id) {
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Actions.
			add_action('woocommerce_update_options_integration_' .  $this->id, array($this, 'process_admin_options'));
		}
    }

    /**
     * Initialize form fields
     * @return null
     */
    public function init_form_fields()
    {
        $fields = array();

        $fields['vbout_basic_configuration'] = array(
			'title' => __( 'Basic Configuration', 'woocommerce-vbout-integration' ),
			'type'  => 'title',
			'desc'  => '',
			'id'    => 'vbout_basic_configuration',
		);
        $fields['apiKey'] = array(
            'title' => __('API Key', 'woocommerce-vbout-integration'),
            'type' => 'text',
				'description' => sprintf(
					/* translators: %1$s: Documentation URL */
					__('Use your VBOUT API Key. You can find this in your VBOUT account: <a href="%1$s" target="_blank">Settings > API Integrations</a>.', 'woocommerce-vbout-integration'),
					'https://app.vbout.com/Settings/?goto=APIIntegrations'
				),
            'desc_tip' => false,
            'default' => '',
            'custom_attributes' => array(
                'required' => true
            )
        );

        if ( empty($this->get_option('apiKey')) ) {
			$fields['vbout_sync_configuration'] = array(
				'title'  => '',
				'type'  => 'title',
				'description'  => '<div class="notice notice-warning">'. __( 'Save your VBOUT API Key to activate your sync settings', 'woocommerce-vbout-integration' ) .'</div>',
				'id'    => 'vbout_sync_configuration',
			);
		}
        else {
             $domain  = $this->getDomainVBT($this->get_option('apiKey'));

            if ( empty($domain) ) {
				$fields['vbout_sync_configuration'] = array(
					'title' => '',
					'type'  => 'title',
					'description'  => '<div class="notice notice-warning">'. sprintf(
							__( 'Your domain "%1$s" is not setup properly in your VBOUT account. Please click <a href="%2$s" target="_blank">here</a> to fix it.', 'woocommerce-vbout-integration' ),
							$this->getSiteDomain(),
							'https://app.vbout.com/Settings/?goto=Domains'
						) .'</div>',
					'id'    => 'vbout_sync_configuration',
				);
			}
            else {
				$fields['domain'] = array(
					'title' => '',
					'type' => 'text',
					'default' => ''.$domain,
					'custom_attributes' => array(
						'readonly' => true,
						'hidden' => true,
					)
				);

                $settings = $this->getSettingsMapField();

                if ($settings) {
                    foreach ($settings as $key => $value) {
                    	if( is_numeric($key) ){
							$fields[$key] = array(
								'title' => $value['title'],
								'type' => 'title',
							);
						}
                    	else {
                    		$custom_attributes = array();

							$fields[$key] = array(
								'title' => $value['label'],
								'type' => 'checkbox',
								'label' => $value['description'],
								'description' => '',
								'desc_tip' => false,
								'default' => 'no',
								'custom_attributes' => $custom_attributes,
							);
						}
                    }
                }

				$fields['listTitle'] = array(
					'title' => __('WOOCOMMERCE LISTS', 'woocommerce-vbout-integration'),
					'type' => 'text',
					'default' => 'E-commerce List',
					'css' => 'font-weight: 700; border: 0; background: transparent; box-shadow: none; padding: 0; font-size: 16px;',
					'custom_attributes' => array(
						'readonly' => true
					)
				);
            }
        }
        $this->form_fields = $fields;
    }

    /**
     * Sanitize/transform setting values
     * @param $settings
     * @return mixed
     */
    public function sanitize_settings($settings)
    {
        // We're just going to make the api key all upper case characters since that's how our imaginary API works
        if ( !empty($settings['apiKey']) ) {
            $settings['apiKey'] = strtoupper($settings['apiKey']);
        }

        return $settings;
    }

    /**
     * Get domain code from VBOUT
     * @param $apiKey
     * @return array
     */
    private function getDomainVBT($apiKey)
    {
        $vboutApp = new EcommerceWS(array('api_key' => $apiKey));

        $domainCode = $vboutApp->getDomain(array(
			'domain' => $this->getSiteDomain(),
		));

        $domainCode = is_array($domainCode) ? null : $domainCode;
        return $domainCode;
    }

    /**
     * Added new Settings functionality
     * @return string[]
     */
    private function getSettingsMapField()
    {
		// Check if we are in sync progress
		$syncSettings = WCVboutSync::getSyncSettings();

        return array(
        	1 => array(
				'title'	=> __('Sync Data', 'woocommerce-vbout-integration'),
			),
            'abandoned_carts'       =>  array(
				'label'	=> __('Abandoned carts', 'woocommerce-vbout-integration'),
				'description'	=> __('When a checkout/order is created or updated on  WooCommerce', 'woocommerce-vbout-integration'),
			),
            'search'                =>  array(
				'label'	=> __('Product Search', 'woocommerce-vbout-integration'),
				'description'	=> __('When customers search for a specific product on WooCommerce', 'woocommerce-vbout-integration'),
			),
            'product_visits'        =>  array(
				'label'	=> __('Product Visits', 'woocommerce-vbout-integration'),
				'description'	=> __('When customers visit a product on WooCommerce', 'woocommerce-vbout-integration'),
			),
            'category_visits'       =>  array(
				'label'	=> __('Category Visits' , 'woocommerce-vbout-integration'),
				'description'	=> __('When customers visit a specific category on WooCommerce' , 'woocommerce-vbout-integration'),
			),
            'customers'             =>  array(
				'label'	=> __('Customer data', 'woocommerce-vbout-integration'),
				'description'	=> __('When customers\' profiles are added or updated on WooCommerce', 'woocommerce-vbout-integration'),
			),
			'product_feed'          =>  array(
				'label'	=> __('Product data', 'woocommerce-vbout-integration'),
				'description'	=> __('When products are added or updated on WooCommerce', 'woocommerce-vbout-integration'),
			),

			2 => array(
				'title'	=> __('Historical Data', 'woocommerce-vbout-integration'),
			),
            'current_customers'     =>  array(
				'label'	=> __('Existing Customers', 'woocommerce-vbout-integration'),
				'description'	=> __('Syncs customers\' data before installing the plugin on WooCommerce', 'woocommerce-vbout-integration'),
				'readonly'	=> !empty($syncSettings['customers']['status']),
			),
            'sync_current_products' =>  array(
				'label'	=> __('Existing products', 'woocommerce-vbout-integration'),
				'description'	=> __('Syncs products\' data before installing the plugin on WooCommerce', 'woocommerce-vbout-integration'),
				'readonly'	=> !empty($syncSettings['products']['status']),
			),
            'sync_current_orders' =>  array(
				'label'	=> __('Existing orders', 'woocommerce-vbout-integration'),
				'description'	=> __('Syncs orders\' data before installing the plugin on WooCommerce', 'woocommerce-vbout-integration'),
				'readonly'	=> !empty($syncSettings['orders']['status']),
			),
        );
    }

    private function getSiteDomain() {
		return parse_url( $_SERVER['HTTP_HOST'] )['path'];
	}

}
