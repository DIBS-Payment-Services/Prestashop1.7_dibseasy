/**
 * 2016 - 2017 Invertus, UAB
 *
 * NOTICE OF LICENSE
 *
 * This file is proprietary and can not be copied and/or distributed
 * without the express permission of INVERTUS, UAB
 *
 * @author    INVERTUS, UAB www.invertus.eu <support@invertus.eu>
 * @copyright Copyright (c) permanent, INVERTUS, UAB
 * @license   Addons PrestaShop license limitation
 *
 * International Registered Trademark & Property of INVERTUS, UAB
 */

$(document).ready(function () {
    initCheckout();
    initDeliveryOptionChangeEvent();

    var originalDeliveryMessage = $('#delivery_message').val();

    prestashop.on('updateCart', function() {
        window.location.reload();
    });

    /**
     * Initialize delivery option change handler
     */
    function initDeliveryOptionChangeEvent()
    {
        var $deliveryOptionForm = $('#js-delivery');

        // If for some reason form could not be found
        if (0 == $deliveryOptionForm.length) {
            return;
        }

        // Make ajax call on delivery option change & reload page
        $deliveryOptionForm.find('input').on('change', function() {
            $.post($deliveryOptionForm.data('url-update'), $deliveryOptionForm.serialize(), function () {
                window.location.reload();
            });
        });

        // Make ajax call on delivery message change & reload page
        $deliveryOptionForm.find('textarea').on('blur', function() {
            var newDeliveryMessage = $(this).val();
            if (originalDeliveryMessage == newDeliveryMessage) {
                return;
            }

            $.post($deliveryOptionForm.data('url-update'), $deliveryOptionForm.serialize(), function () {
                window.location.reload();
            });
        });
    }

    /**
     * Initialize DIBS checkout iframe
     */
    function initCheckout()
    {
        var checkoutOptions = {
            checkoutKey: dibsCheckout.checkoutKey,
            paymentId : dibsCheckout.paymentID,
            containerId : "dibs-complete-checkout",
            language: dibsCheckout.language
        };

        var checkout = new Dibs.Checkout(checkoutOptions);

        checkout.on('payment-completed', function () {
            window.location = dibsCheckout.validationUrl;
        });

        checkout.on('address-changed', function (address) {
            checkout.freezeCheckout();

            $.post(dibsCheckout.addressUrl, {
                post_code: address.postalCode,
                country_code: address.countryCode
            }, function (response) {
                var res = JSON.parse(response);

                if (res.success && res.need_reload) {
                  window.location = dibsCheckout.refreshUrl;

                  return;
                }

                checkout.thawCheckout();
            });
        });
    }
});
