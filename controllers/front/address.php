<?php
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

/**
 * Class DibsEasyAddressModuleFrontController
 *
 * @property DibsEasy $module
 */
class DibsEasyAddressModuleFrontController extends ModuleFrontController
{
    /**
     * Check if customer can access address controller.
     *
     * @retun bool
     */
    public function checkAccess()
    {
        if (!$this->isXmlHttpRequest()) {
            $this->json([
                'error' => true,
            ]);
        }

        // If guest checkout is enabled and customer is not logged in, then redirect to standard checkout
        $guestCheckoutEnabled = (bool) Configuration::get('PS_GUEST_CHECKOUT_ENABLED');
        if (!$guestCheckoutEnabled && !$this->context->customer->isLogged()) {
            $this->json([
                'error' => true,
            ]);
        }

        // General checks
        if (!$this->module->active ||
            !$this->module->isConfigured()
        ) {
            $this->json([
                'error' => true,
            ]);
        }

        // If cart is not initialized or cart is empty redirect to default cart page
        if (!isset($this->context->cart) || $this->context->cart->nbProducts() <= 0) {
            $this->json([
                'error' => true,
            ]);
        }

        $currency = new Currency($this->context->cart->id_currency);
        $supportedCurrencies = $this->module->getParameter('supported_currencies');

        // If currency is not supported then redirect to default checkout
        if (!in_array($currency->iso_code, $supportedCurrencies)) {
            $this->json([
                'error' => true,
            ]);
        }

        return true;
    }

    public function postProcess()
    {
        $postCode = Tools::getValue('post_code');
        $countryAlpha3Code = Tools::getValue('country_code');

        if (!$postCode || !$countryAlpha3Code) {
            $this->json([
                'error' => true,
            ]);
        }

        /** @var \Invertus\DibsEasy\Service\CountryMapper $countryMapper */
        $countryMapper = $this->module->get('dibs.service.country_mapper');
        $countryAlpha2Code = $countryMapper->getIso2Code($countryAlpha3Code);

        $deliveryAddress = new Address($this->context->cart->id_address_delivery);
        $deliveryCountry = new Country($deliveryAddress->id_country);

        // if delivery data is the same
        // then do nothing
        if ($deliveryAddress->postcode == $postCode &&
            $deliveryCountry->iso_code == $countryAlpha2Code
        ) {
            $this->json([
                'success' => true,
                'need_reload' => false,
            ]);
        }

        if ($deliveryAddress->postcode != $postCode) {
            $deliveryAddress->postcode = $postCode;
        }

        if ($deliveryCountry->iso_code != $countryAlpha2Code) {
            $countryId = Country::getByIso($countryAlpha2Code);

            $deliveryAddress->id_country = $countryId;
        }

        $deliveryAddress->save();

        $this->json([
            'success' => true,
            'need_reload' => true,
        ]);
    }

    public function json(array $data)
    {
        die(json_encode($data));
    }
}
