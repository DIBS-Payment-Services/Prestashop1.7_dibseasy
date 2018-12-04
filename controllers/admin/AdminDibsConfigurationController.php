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

class AdminDibsConfigurationController extends ModuleAdminController
{
    /****
     * @var DibsEasy
     */
    public $module;

    public $bootstrap = true;

    public function init()
    {
        $this->initOptions();

        $isFriendlyUrlOn = (bool) Configuration::get('PS_REWRITING_SETTINGS');
        if (!$isFriendlyUrlOn) {
            $this->warnings[] = $this->l('Friendly URLs must be enabled in order for module to work.');
        }

        parent::init();
    }

    /**
     * Initialize options
     */
    protected function initOptions()
    {
        $availableCountries = $this->getAvailableCountries();

        if (empty($availableCountries)) {
            $this->warnings[] =
                $this->l('DIBS Easy does not support any active country of your shop, thus module will not work');
        }

        $this->fields_options = [
            'dibs_configuration' => [
                'title' => $this->l('DIBS Easy Checkout configuration'),
                'fields' => [
                    'DIBS_MERCHANT_ID' => [
                        'title' => $this->l('Merchant ID'),
                        'type' => 'text',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ],
                    'DIBS_PROD_SECRET_KEY' => [
                        'title' => $this->l('Live secret key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ],
                    'DIBS_TEST_SECRET_KEY' => [
                        'title' => $this->l('Test secret key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ],
                    'DIBS_PROD_CHECKOUT_KEY' => [
                        'title' => $this->l('Live checkout key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ],
                    'DIBS_TEST_CHECKOUT_KEY' => [
                        'title' => $this->l('Test checkout key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ],
                    'DIBS_LANGUAGE' => [
                        'title' => $this->l('Checkout language'),
                        'type' => 'select',
                        'class' => 'fixed-width-xxl',
                        'list' => $this->getLangs(),
                        'identifier' => 'id',
                    ],
                    'DIBS_CONSUMER_TYPE' => [
                        'title' => $this->l('Allowed customer types'),
                        'type' => 'select',
                        'class' => 'fixed-width-xxl',
                        'list' => $this->getConsumerTypes(),
                        'identifier' => 'id',
                    ],
                    'DIBS_TAC_URL' => array(
                        'title' => $this->l('Terms & Conditions URL'),
                        'desc' => $this->l('URL is required'),
                        'validation' => 'isUrl',
                        'type' => 'text',
                        'class' => 'fixed-width-xxl',
                        'required' => true,
                    ),
                    'DIBS_TEST_MODE' => [
                        'title' => $this->l('Testing mode'),
                        'validation' => 'isBool',
                        'type' => 'bool',
                        'cast' => 'intval',
                        'class' => 'fixed-width-xxl',
                    ],
                    'DIBS_DEFAULT_SHIPPING_COUNTRY' => [
                        'title' => $this->l('Default shipping country'),
                        'type' => 'select',
                        'class' => 'fixed-width-xxl',
                        'list' => $this->getAvailableCountries(),
                        'identifier' => 'id',
                        'desc' => $this->l('Shipping cost is calculated for selected country by default'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Get languages in which DIBS are available
     *
     * @return array
     */
    private function getLangs()
    {
        return [
            [
                'id' => 'en-GB',
                'name' => $this->l('English'),
            ],
            [
                'id' => 'sv-SE',
                'name' => $this->l('Swedish'),
            ],
            [
                'id' => 'nb-NO',
                'name' => $this->l('Norwegian Bokmål'),
            ],
            [
                'id' => 'da-DK',
                'name' => $this->l('Danish'),
            ],
        ];
    }

    /**
     * Get available consumer types
     */
    private function getConsumerTypes()
    {
        $b2c = \Invertus\DibsEasy\ValueObject\Consumer::TYPE_B2C;
        $b2b = \Invertus\DibsEasy\ValueObject\Consumer::TYPE_B2B;

        return [
            [
                'id' => $b2c,
                'name' => $this->l('B2C only'),
            ],
            [
                'id' => $b2b,
                'name' => $this->l('B2B only'),
            ],
            [
                'id' => \Invertus\DibsEasy\ValueObject\Consumer::b2cAndB2bWithDefaultB2cType(),
                'name' => $this->l('B2C & B2B (defaults to B2C)'),
            ],
            [
                'id' => \Invertus\DibsEasy\ValueObject\Consumer::b2bAndB2cWithDefaultB2bType(),
                'name' => $this->l('B2B & B2C (defaults to B2B)'),
            ],
        ];
    }

    /**
     * Get countries that are supported by DIBS Easy and activated in PrestaShop
     *
     * @return array
     */
    private function getAvailableCountries()
    {
        $countries = Country::getCountriesByIdShop(
            $this->context->shop->id,
            $this->context->language->id
        );
        $activeCountries = [];

        foreach ($countries as $country) {
            if ($country['active']) {
                $activeCountries[$country['id_country']] = $country['iso_code'];
            }
        }

        if (empty($activeCountries)) {
            return $activeCountries;
        }

        $supportedCountries = $this->module->get('dibs.service.country_mapper')->mappings();
        $availableCountries = array_intersect($activeCountries, $supportedCountries);

        if (empty($availableCountries)) {
            return $activeCountries;
        }

        $formattedCountries = [];

        foreach ($availableCountries as $countryId => $isoCode) {
            $formattedCountries[] = [
                'id' => $countryId,
                'name' => Country::getNameById(
                    $this->context->language->id,
                    $countryId
                ),
            ];
        }

        return $formattedCountries;
    }
}
