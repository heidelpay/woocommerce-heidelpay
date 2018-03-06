<?php
/**
* Require the composer autoloader file
*/
require_once __DIR__ . '/../../../autoload.php';

/**
* Load a new instance of the payment method
*/
$DirectDebit = new DirectDebitPaymentMethod();

/**
* Set up your authentification data for Heidepay api
*
* @link https://dev.heidelpay.de/testumgebung/#Authentifizierungsdaten
*/
$DirectDebit->getRequest()->authentification(
'31HA07BC8142C5A171745D00AD63D182',  // SecuritySender
'31ha07bc8142c5a171744e5aef11ffd3',  // UserLogin
'93167DE7',                          // UserPassword
'31HA07BC8142C5A171744F3D6D155865',  // TransactionChannel credit card without 3d secure
true                                 // Enable sandbox mode
);
/**
* Set up asynchronous request parameters
*/
$DirectDebit->getRequest()->async(
'EN', // Language code for the Frame
HEIDELPAY_PHP_PAYMENT_API_URL .
HEIDELPAY_PHP_PAYMENT_API_FOLDER .
'HeidelpayResponse.php'  // Response url from your application
);

/**
* Set up customer information required for risk checks
*/
$DirectDebit->getRequest()->customerAddress(
'Heidel',                  // Given name
'Berger-Payment',           // Family name
null,                     // Company Name
'12344',                   // Customer id of your application
'Vagerowstr. 18',          // Billing address street
'DE-BW',                   // Billing address state
'69115',                   // Billing address post code
'Heidelberg',              // Billing address city
'DE',                      // Billing address country code
'support@heidelpay.de'     // Customer mail address
);

/**
* Set up basket or transaction information
*/
$DirectDebit->getRequest()->basketData(
'2843294932', // Reference Id of your application
23.12,                         // Amount of this request
'EUR',                         // Currency code of this request
'39542395235ßfsokkspreipsr'    // A secret passphrase from your application
);

/**
* Set necessary parameters for Heidelpay payment Frame and send a registration request
*/
$DirectDebit->registration();
?>