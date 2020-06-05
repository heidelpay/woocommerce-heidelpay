# Release Notes - heidelpay Payment Gateway for WooCommerce

## [1.7.0][1.7.0]

### Added:
- Use context to generate a dedicated heidelpay logfile
- WooCommerce Subscriptions
    - Enable / Disable payment for subscription orders
    - Support for  configuration
        - Change amount of subscription
        - Change date of subscription
    - Direct debit: 
        - Add creditorId payment information
        - Payment info text for subscription renewal orders.

###Fixed:
- Push notifications were not processed

## [1.6.0][1.6.0]

### Added:
- Unique ID and Short ID now show up in Order Meta

### Change:
- Use different way to get the host url to ensure better compatibility
- Ensure that total price is only submitted with 2 decimal digits

### Fixed:
- Set the correct customer ip for payment request

## [1.5.0][1.5.0]

### Added:
- Payment method: Invoice
### Fixed: 
- Date input for secured invoice not working on Safari browser.

## [1.4.0][1.4.0]

### Added:
#### Features:
- Supports WooCommerce Subscriptions
- Add Prepayment Payment Method

## [1.3.0][1.3.0]

### Fixed:
- Credit Card iFrame not working in Safari Browsers

### Added:
#### Features:
- Add GiroPay Payment Method

## [1.2.0][1.2.0]

### Fixed:
- Missing payment information for secured invoice and direct debit on success page and notification mail.
- Invoice instruction was shown in other mails where other payment methods were used.
- Exception that can occur in combination with other plugins.

### Added
#### Features:
- Support for push notifications
- A checkbox to decide whether payment information should be added to the notification mail or not.

## [1.1.1][1.1.1]

### Fixed:
- a bug that caused the payment requests to fail in some shops due to an invalid url.

## [1.1.0][1.1.0]

### Added

#### New payment methods:
 - iDeal
 - PayPal
 - Direct Debit

#### Features:
- Credit Card and Debit Card: Added css file to customize the appearance of the iFrame.
- Credit Card, Debit Card and Paypal: Added setting to chose between bookingmodes debit (default) and authorize.
- Secured Invoice: added settings for minimum and maximum Amount to accept for payment.
- Secured Invoice: added settings to enable payment for Germany and Austria.
- Secured Invoice: added validation on checkout.

### Changed
- payment methods will be deactivated by default.

## [1.0.1][1.0.1]

### Fixed
- A bug which caused that the sandbox mode was activated regardless of the state of the switch.

[1.0.1]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.0.0..1.0.1
[1.1.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.0.1..1.1.0
[1.1.1]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.1.0..1.1.1
[1.2.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.1.1..1.2.0
[1.3.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.2.0..1.3.0
[1.4.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.3.0..1.4.0
[1.5.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.4.0..1.5.0
[1.6.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.5.0..1.6.0
[1.7.0]: https://github.com/heidelpay/woocommerce-heidelpay/compare/1.6.0..1.7.0