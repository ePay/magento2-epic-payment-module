# ePay Payment Solutions - Gateway for Magento 2

**Version:** 4.0.33
**License:** MIT

Integrates the ePay payment gateway with Magento 2.

## Description

Important: This module must only be installed by merchants using ePay's EPIC payment gateway. It is not compatible with the ePay Classic gateway.

With ePay Payment Solutions for Magento 2, merchants can offer the ePay payment window in checkout and manage ePay payments directly from Magento administration.

## Features

- Receive payments securely through the ePay payment window.
- Capture, refund, and void payments from the Magento order view.
- Create payment links for eligible unpaid orders from Magento administration.
- Offer Anyday, Apple Pay, Google Pay, Klarna, MobilePay, Swish, and ViaBill as separate checkout payment methods.
- Configure payment-method availability by country and currency.
- Configure checkout card logos without changing the payment methods accepted by the merchant.
- Optionally send customer, invoice, and order-line data to the payment window.
- Support instant capture when the goods are supplied immediately.
- Configure whether ePay surcharge fees are added as an order line or to shipping & handling.
- Restore the customer's cart after a cancelled or failed payment when possible.
- Support optional age verification based on product and category configuration.

## Requirements

- Magento 2.
- An ePay account with an API key and Point of Sale ID.
- A publicly reachable HTTPS URL for the payment callback.

## Installation

Install the module using Composer:

```bash
composer require epay/magento2-epic-payment-module
php bin/magento module:enable Epay_Magento2EpicPaymentModule
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Then configure the payment method in **Stores > Configuration > Sales > Payment Methods > ePay Payment Solutions**.

## Configuration

Configure the following values in Magento administration:

- API Key
- Point of Sale ID
- Webhook Authorization token
- Instant capture, invoice data, cancelled-payment order status, and age-verification settings as required

Enable and configure separate payment methods only when they are enabled for the merchant's Point of Sale.

## External Services

This module connects to ePay's payment services to process online payments securely.

When a customer starts a payment, the module creates and manages payment sessions, receives payment-status callbacks, and performs capture, refund, or void operations. These connections are required for the module to process payments.

### Data sent to ePay

- Order reference, amount, and currency.
- Customer IP address and optional customer or invoice data when enabled.
- Order lines when invoice data is enabled.
- Transaction and payment-session identifiers.
- Age-verification data only when the merchant enables the feature.

During capture, refund, and void operations, the module sends the ePay transaction reference and applicable amount. No card data is stored or processed by Magento or this module; payment details are handled by ePay's payment window and infrastructure.

### Purpose and provider

The data is necessary to authorize and complete payments, issue refunds, void authorizations, display payment status, and perform optional age verification.

All payment transactions are handled by ePay Payment Solutions.

- Service website: <https://epay.eu>
- Terms of use: <https://epay.eu/terms>
- Privacy policy: <https://epay.eu/privacy>
- Service API endpoint: <https://payments.epay.eu>

## Changelog

### 4.0.33

- Added configurable surcharge-fee handling, including order-line and shipping modes.

### 4.0.32

- Synchronize release tags to the GitHub mirror.

### 4.0.31

- Updated the module documentation.

### 4.0.30

- Maintenance release.

### 4.0.28

- Added payment links for eligible unpaid orders in Magento administration.

### 4.0.26

- Added configurable checkout card logos and separate payment-method options.

### 4.0.24

- Added Hyvä Checkout compatibility support.
