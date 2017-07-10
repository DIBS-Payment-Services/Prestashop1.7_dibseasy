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
     * @var Dibs
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
        $this->fields_options = array(
            'dibs_configuration' => array(
                'title' => $this->l('DIBS Easy Checkout configuration'),
                'fields' => array(
                    'DIBS_MERCHANT_ID' => array(
                        'title' => $this->l('Merchant ID'),
                        'type' => 'text',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ),
                    'DIBS_PROD_SECRET_KEY' => array(
                        'title' => $this->l('Live secret key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ),
                    'DIBS_TEST_SECRET_KEY' => array(
                        'title' => $this->l('Test secret key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ),
                    'DIBS_PROD_CHECKOUT_KEY' => array(
                        'title' => $this->l('Live checkout key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ),
                    'DIBS_TEST_CHECKOUT_KEY' => array(
                        'title' => $this->l('Test checkout key'),
                        'type' => 'text',
                        'validation' => 'isString',
                        'class' => 'fixed-width-xxl',
                        'size' => '30',
                    ),
                    'DIBS_LANGUAGE' => array(
                        'title' => $this->l('Checkout language'),
                        'type' => 'select',
                        'class' => 'fixed-width-xxl',
                        'list' => $this->getLangs(),
                        'identifier' => 'id',
                    ),
                    'DIBS_TEST_MODE' => array(
                        'title' => $this->l('Testing mode'),
                        'validation' => 'isBool',
                        'type' => 'bool',
                        'cast' => 'intval',
                        'class' => 'fixed-width-xxl',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        if (!$this->module->isPS16()) {
            $this->fields_options['dibs_configuration']['image'] = '../img/t/AdminPreferences.gif';
        }
    }

    /**
     * Get languages in which DIBS are available
     *
     * @return array
     */
    private function getLangs()
    {
        return array(
            array(
                'id' => 'en-GB',
                'name' => $this->l('English')
            ),
            array(
                'id' => 'sv-SE',
                'name' => $this->l('Swedish')
            ),
        );
    }
}
