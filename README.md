# Barcodes for WooCommerce®

Barcodes for WooCommerce® is a plugin designed to simplify order and product management by automatically generating unique QR codes for orders and products. 

These QR codes are integrated into the WooCommerce experience, including emails, admin pages, and customer-facing order details.

This plugin is purposefully focused on QR codes for version **0.0.1**, and I'm seeking feedback from the community to determine which barcode formats would provide the most value in future releases. 

I'm aiming to prioritize features that are truly useful rather than adding every possible format or feature just because _you know who_ does it.

## Features

- **Order QR Codes**: Automatically generate QR codes for all new orders.
- **Product QR Codes**: Generate unique QR codes for products without existing codes.
- **Email Integration**: Include QR codes in customer order confirmation emails.
- **Admin Integration**: Display QR codes in the admin order details page.
- **Shortcode Support**: Display QR codes anywhere using the `[order_barcode]` shortcode.
- **AJAX Functionality**: Lookup and generate QR codes dynamically.
- **Settings Page**: Customize QR code settings, including color.

### Planned Features

I want to hear from **you**! Let me know which barcode formats (e.g., Code128, EAN, UPC) you see most often and would like included in future versions.

My goal is to keep the plugin lean and focus on the most practical use cases.

## Installation

1. Download the plugin zip file from the [releases page](https://github.com/robertdevore/barcodes-for-woocommerce/releases).
2. Upload the plugin to your WordPress site:
    - Navigate to **Plugins** > **Add New**.
    - Click **Upload Plugin** and choose the zip file.
    - Click **Install Now** and then **Activate**.
3. Configure the plugin settings:
    - Go to **Barcodes** in the WordPress admin menu.
    - Customize settings as needed.

## Usage

### Automatic QR Code Generation

- **Orders**: QR codes are generated automatically for all new orders.
- **Products**: QR codes can be generated for products manually or in bulk via the **Advanced Settings** tab.

### Display QR Codes

- **Order Details**: QR codes are displayed on the thank you page, in the admin dashboard, and in customer emails.
- **Shortcodes**: Use `[order_barcode order_id="123"]` to display an order's QR code anywhere on your site.

### Email Integration

The plugin appends the order QR code to the customer order confirmation email for easy scanning and retrieval of order details.

## Contributing

I welcome contributions! Here's how you can help:

1. Fork the repository.
2. Create a new feature branch: `git checkout -b feature/my-feature`.
3. Commit your changes: `git commit -m 'Add my feature'`.
4. Push to the branch: `git push origin feature/my-feature`.
5. Open a pull request.

## License

This plugin is licensed under the GPL-2.0+ license. See the [LICENSE](http://www.gnu.org/licenses/gpl-2.0.txt) file for more details.