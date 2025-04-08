# OMOM Pre-Orders
This plugins makes it possible to add stock to a product with a certain arrival date. Customers can now pre-order that product.
The customer sees an arrival date in the product description, cart, checkout and all emails. The admin sees it in the admin order window as well.

## Why this plugin was made
We have been using this plugin to sell products which are still in transit to us, being shipped from a producer to our warehouse. Once the container has arrived, what has not been sold yet, can be added to the stock of our website.

## Our plugin has been integrated with the following plugins
<a href="https://woocommerce.com/products/product-bundles/">WC Product Bundles</a><br>
<a href="https://woocommerce.com/products/composite-products/">WC Composite Products</a><br>
<a href="https://wordpress.org/plugins/atum-stock-manager-for-woocommerce/">ATUM WooCommerce Inventory Management and Stock Tracking</a><br>
<a href="https://stockmanagementlabs.com/addons/atum-product-levels/">ATUM Product Levels</a><br>

## How Pre-Orders works
1. install & activate.
2. The WP admin sidebar will now show the menu item 'Pre-Orders'
3. Add a new Shipment here.
4. Now you can enter a date when your shipment will arrive, a comment and all the products which are being restocked
5. Once this is updated, all products in shipments can be purchased by customers even if the normal stock is 0

## Stock and quantity levels
A stock is a store. A quantity is a requested amount of stock.
There are multiple values we go by:
- stock: the amount of how many of a product are at the seller's warehouse. The stock can go into minus when it has a pre-order stock. 
The minus value will indicate how many of this product are pre-ordered. 
It can pass the fitting negative value of the pre-order stock, if it is set to backorder. 
The stock value will go up again, when the shipments are released. 
We handle stock this way because it is not certain if the stock shipped will be correct and to reflect how much are pre-ordered by customers.
- Total pre-order stock: All pre-order quantities in all shipments combined.
- Total available: All available quantities in all shipments combined.  
- Pre-order stock (po_stock): The available pre-order stock to purchase. Should match Total available. 
The calculation is when stock < 0 then total pre-order stock minues stock. Otherwise just total pre-order stock.

## About shipments
A shipment contains products which then can be pre-ordered.
They products in the shipment are in pre-order, when the order is published.

The shipment has multiple statuses it can go through:
- Draft: product pre-order quantity is not added to the product yet.
- Active: A published shipment. pre-order quantity is added to products and they are available for purchase.
- Received: arrival date has passed BUT pre-order quantity is still available for purchase - if automatic release has not been activated.
- Released: arrival date has passed AND pre-order quantity has been released to the warehouse stock. It is considered finished.

Product settings
- F.S. Makes it querieable for Future Stock pages.
- Pre-order Quantity: the original quantity.
- Product: a search field for the actual product
- Available quantity: the quantity available to be purchased
- Remove: remove the product from the shipment

## Aspects of a product in pre-order
The product stock can go into minus, once it has a pre-order quantity. 
If the product has "in backorder" activated, it can go into minus infinitely. If not, it'll only be purchasable by the pre-order quantity.

## Example scenario 1
Simple Product 1 has a stock of 0 and a future stock of 25.

## Example scenario 2
Simple Product 1 has a stock of 10 and a future stock of 50.
A Retail customer can purchase 10 times the product. A customer can purchase it 60 times.
When a customers adds more than 10 items to his or her cart, the cart will display information about the arrival dates of the stock.

## Example scenario 3
Simple Product 3 has a stock of 5 and a future stock of 30. <br> 
The future stock is shipped in three restock posts.<br> 
Restock post 1 arrives 11.11.21 and has 10,<br> 
restock post 2 arrives 12.12.21 and has 5 and <br>
restock post 3 arrives 01.01.22 and has 15 of the Simple Product 3. <br> 
A customer can now add the product max. 35 times to the cart.<br> 
If a customer now purchases 5 bikes, everything will be like it always is.<br> 
If a customer purchases 18 bikes, they will see: 5 in stock, 10x arrive on the 11.11.21 and 3x arrive on the 12.12.21.<br> 
If a customer purchases 32 bikes, they will see: 5 in stock, 10x arrive on the 11.11.21 and 5x arrive on the 12.12.21 and 2x arrive on the 01.01.22.<br> 