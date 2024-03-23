

TYPO3 extension transactor_paypal
=================================

What is does
------------

This extension is based on the Transactor API extension and brings the PayPal Payments Standard to
any other TYPO3 extension.


Example Setup
------------


    lib.transactor_paypal {
        extName = transactor_paypal
        extTitle = PayPal REST API
        extInfo = PayPal enables any business or consumer with an email address to securely, conveniently, and cost-effectively send and receive payments online.
        extImage = EXT:transactor_paypal/Resources/Public/Images/paypal_euro.gif
        gatewaymode = form
        paymentMethod = webpayment_standard
        currency = EUR
        templateFile = EXT:transactor/Resources/Private/Templates/PaymentHtmlTemplate.html

        returnPID = 15
        cancelPID = 12
    }


External documentation
-----------------------

*   `PayPal  Payments Standard https://developer.paypal.com/api/nvp-soap/paypal-payments-standard/integration-guide/formbasics/`__
*   `PayPal Buttons Integration https://developer.paypal.com/demo/checkout/#/pattern/server`__
*   `Checkout Standard payments https://developer.paypal.com/docs/checkout/standard/`__
    *    Payment buttons to pay with PayPal, debit and credit cards, Pay Later options, Venmo, and alternative payment methods.



