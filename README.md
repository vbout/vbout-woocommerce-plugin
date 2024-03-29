# WooCommerce-Plugin-Vbout
WooCommerce Plugin that link Metadata of orders, cart customers, searches, products and Integration settings.

## The Plugin has the Following Features :

  - Abandoned carts
  - Registering a new customer
  - Adding a new Product
  - Product search 
  - Product visits
  - Category Visit
  - Syncing customers ( For customer data prior the use of the plugin) 
  - Syncing products   ( For Product data prior the use of the plugin)
  - Orders Creation
  ## limitations : 
    1 - Upon registration, the only attribute is sent is user email, first name and last name are not sent (this is handeled once this user signs in).
    2 - Old Customers And Products are synced using a cron, you'll have to wait until data shows under VBOUT.
  
## Variations : 
  
  Variations are sent as an array upon adding a product, syncing a product and viewing a product. 
  But when purchasing a certain variation of a product, we send the new product data to be viewed ( New Category, New Price, New Variation Name , New SKU) to be previewed in Vbout.
 
## Search : 
  
  There is an listener/observer for search and it receives the "term" searched form.
  
## Orders and Abandonded Carts : 
  
  ### Checkout : 
    There is a listener for checkout and does the following since WooCommerce allows you to checkout without registration, and it acts like add-to-cart + order-creation.

  ### Create and Update Cart  : 
    There is a listener for both Cart Update and Cart create and they have the following functionalities : 
      - Get the current logged in customer and it's data. 
      - Create a new cart
      - Add Products to cart ( a loop to handle them ) 
      - Updating Cart : 
      - In the process of updating cart, any update to status( Cancelled, Pending, Paid, Shipped/success), details, products is updated directly.
 
  ### Cart Item Remove : 
    Since the variation is handeled as a product with an independent Product id, upon this function we get the variation id , and the parent ID. we get the variation ( since many products will have the same product id but with different variations) to remove the product id with the same variation ( they will be comapred on VBOUT to check if they are the same product or different).

  ### Orders Create and Update : 
    Both have different listeners, they work the same. An Order is added with Shipping and Biling information, alongside with customer's information.

## Customers Add, Update and Sync :
    - Customer email is added upon registration. We get customers data upon loggin in.
    - Customer's sync adds all the users in the system that had previous orders or registered.

## Product Add Update and Sync :
      products are handeled differently in WooCommerce. Every product having a variation, this variation can have different ( considered an independed product but under a parent product ) : 
      - Descritpion
      - Product id
      - Image
      - quantity 
      - price
      - sale price 

      For this the product Price and sale price will be zero only for parent products with Variations, this is because once you create a variation, you are not allowed to put products price, quantity and sale price.

    - Products are added , updated on an Admin page.
    - Product's sync adds all products that are in the system that were added and are still in stock.
    
## IP and Customer Link: 
    - If a customer is roaming the website, and the features are turned on, the IP will be sent at every event. 
    - Upon User registration, all the searches, carts, products and categories views will be linked to this account through IP.
    
      
## Listeners ---- > Functions Used and Explanation :

      - Handeled and called in the frontend
  
                    Listener                              | Function Name               | Descritpion
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__user_register__*                   | `customerRegisterSuccess`   | `This function is to create users on registrations.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_after_cart_contents__* | `wc_cart_data`              | This function to Add To Cart Function, that creates a cart and Adds the Items`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_thankyou__*            | `onOrderCompleted`          | `Create an order with all it's data (After placing an order).`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_cart_item_removed__*   | `wc_item_remove`            | `Removes cart items from a cart.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_after_single_product__*| `wc_product_data`           | `This function to send the product view by the customer or IP address ( then it can be linked)`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__wp_login__*                        | `wc_customer_update`        | `Create an order with all it's data (After placing an order).`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------

                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_after_main_content__*  | `wc_category_data`          | `Get's the category of the visited product and sends it.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                                 *__pre_get_posts__*      | `wc_product_search`         | `This is a listener sends searched queries.`


        - Handeled and called in the amdin seciton
        
                    Listener                              | Function Name               | Descritpion
               -----------------------------------------  | --------------------------- | ------------------------------------------------------------------
              *__woocommerce_process_product_meta__*      | `wc_product_add`            | `Once a product is added or Updated.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_settings_saved__*      | `onSettingsSaved`        | `Customized configuration for integration settings between WooCommerce and Vbout. This also allows the user to control what features they want in the admin/Configuration/Vbout page.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
     *__woocommerce_admin_order_data_after_order_details__*   | `wc_order_update`           | `When an admin wants to Update order, it updates the order and order status(taking in consideration Billing and Shipping info).`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------

