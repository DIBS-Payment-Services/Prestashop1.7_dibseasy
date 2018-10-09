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

use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class DibsEasyCheckoutModuleFrontController extends ModuleFrontController
{
    /**
     * @var DibsEasy
     */
    public $module;

    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * @var array These variables are passed to JS
     */
    protected $jsVariables = [];

    /**
     * Check if customer can access checkout page.
     *
     * @retun bool
     */
    public function checkAccess()
    {
        // If guest checkout is enabled and customer is not logged in, then redirect to standard checkout
        $guestCheckoutEnabled = (bool) Configuration::get('PS_GUEST_CHECKOUT_ENABLED');
        if (!$guestCheckoutEnabled && !$this->context->customer->isLogged()) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // General checks
        if (!$this->module->active ||
            !$this->module->isConfigured()
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // If cart is not initialized or cart is empty redirect to default cart page
        if (!isset($this->context->cart) || $this->context->cart->nbProducts() <= 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = new Currency($this->context->cart->id_currency);
        $supportedCurrencies = $this->module->getParameter('supported_currencies');

        // If currency is not supported then redirect to default checkout
        if (!in_array($currency->iso_code, $supportedCurrencies)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        return true;
    }

    /**
     * Add custom JS & CSS to controller
     */
    public function setMedia()
    {
        parent::setMedia();

        $isTestingModeOn = (bool) Configuration::get('DIBS_TEST_MODE');
        switch ($isTestingModeOn) {
            case false:
                $checkoutJs = $this->module->getParameter('js_checkout_prod_url');
                $checkoutKey = Configuration::get('DIBS_PROD_CHECKOUT_KEY');
                break;
            default:
            case true:
                $checkoutJs = $this->module->getParameter('js_checkout_test_url');
                $checkoutKey = Configuration::get('DIBS_TEST_CHECKOUT_KEY');
                break;
        }

        $language = Configuration::get('DIBS_LANGUAGE');

        $changeDeliveryOptionUrl = $this->context->link->getModuleLink($this->module->name, 'checkout');
        $validationUrl = $this->context->link->getModuleLink($this->module->name, 'validation');

        $this->jsVariables['dibsCheckout']['checkoutKey'] = $checkoutKey;
        $this->jsVariables['dibsCheckout']['language'] = $language;
        $this->jsVariables['dibsCheckout']['validationUrl'] = $validationUrl;
        $this->jsVariables['dibsCheckout']['checkoutUrl'] = $changeDeliveryOptionUrl;

        $this->registerStylesheet('dibseasy-checkout-css', 'modules/dibseasy/views/css/checkout.css');
        $this->registerJavascript('dibseasy-remote-js', $checkoutJs, ['server' => 'remote']);
        $this->registerJavascript('dibseasy-checkout-js', 'modules/dibseasy/views/js/checkout.js');
    }

    /**
     * Process actions
     */
    public function postProcess()
    {
        CartRule::autoRemoveFromCart($this->context);
        CartRule::autoAddToCart($this->context);

        if (!$this->context->cart->id_address_delivery) {
            $this->context->cart->id_address_delivery = $this->getDeliveryAddressId();
            $this->context->cart->save();
        }

        if (!$this->context->cart->id_carrier) {
            $idCarrierDefault = (int) Configuration::get('PS_CARRIER_DEFAULT');
            $option = [$this->context->cart->id_address_delivery => $idCarrierDefault.','];

            $this->context->cart->setDeliveryOption($option);
            $this->context->cart->update();
        }

        $orderPayment = $this->getOrderPayment();

        $this->jsVariables['dibsCheckout']['paymentID'] = $orderPayment->id_payment;
    }

    /**
     * Initialize header
     */
    public function initHeader()
    {
        parent::initHeader();

        Media::addJsDef($this->jsVariables);
    }

    /**
     * Initialize checkout content
     */
    public function initContent()
    {
        $idLang = $this->context->language->id;

        $this->assignDeliveryOptionVars();

        $this->context->smarty->assign([
            'regularCheckoutUrl' => $this->context->link->getPageLink('order', true, $idLang, ['step' => 1]),
            'cart' => $this->context->cart,
        ]);

        parent::initContent();

        $this->setTemplate('module:dibseasy/views/templates/front/checkout.tpl');
    }

    /**
     * Get DIBS payment information for order.
     *
     * @return DibsOrderPayment
     */
    protected function getOrderPayment()
    {
        /** @var \Invertus\DibsEasy\Repository\OrderPaymentRepository $orderPaymentRepository */
        $orderPaymentRepository = $this->module->get('dibs.repository.order_payment');
        $orderPayment = $orderPaymentRepository->findOrderPaymentByCartId($this->context->cart->id);
        if ($orderPayment) {
            $orderPayment->delete();
        }

        if (Tools::isSubmit('paymentId')) {
            $paymentId = Tools::getValue('paymentId');

            /** @var \Invertus\DibsEasy\Action\PaymentGetAction $paymentGetAction */
            $paymentGetAction = $this->module->get('dibs.action.payment_get');
            $payment = $paymentGetAction->getPayment($paymentId);

            if (null === $payment) {
                Tools::redirect($this->context->link->getModuleLink($this->module->name, 'checkout'));
            }

            $paymentAmountInCents = $payment->getOrderDetail()->getAmount();
            $cartAmountInCents = (int) (string) ($this->context->cart->getOrderTotal() * 100);

            $paymentCurrency = $payment->getOrderDetail()->getCurrency();
            $cartCurrency = new Currency($this->context->cart->id_currency);

            if ($paymentAmountInCents != $cartAmountInCents ||
                $cartCurrency->iso_code != $paymentCurrency
            ) {
                // If payment details does not match cart details
                // Then skip and redirect to checkout without payment id
                // To create new payment with valid details
                Tools::redirect($this->context->link->getModuleLink($this->module->name, 'checkout'));
            }

            $orderPayment = new DibsOrderPayment();
            $orderPayment->id_payment = $paymentId;
            $orderPayment->id_cart = $this->context->cart->id;

            if (!$orderPayment->save()) {
                PrestaShopLogger::addLog(
                    'Failed to create DibsOrderPayment in PrestaShop',
                    3,
                    'FROM_QUERY_PARAM',
                    'DibsOrderPayment',
                    $paymentId,
                    true
                );
                $this->errors[] = $this->module->l('Failed to create payment in DIBS Easy. Please contact us for support.', 'checkout');
                $this->redirectWithNotifications('order');
            }

            return $orderPayment;
        }

        /** @var \Invertus\DibsEasy\Action\PaymentCreateAction $paymentCreateAction */
        $paymentCreateAction = $this->module->get('dibs.action.payment_create');
        $orderPayment = $paymentCreateAction->createPayment($this->context->cart);

        if (false === $orderPayment) {
            $this->errors[] = $this->module->l('Failed to create payment in DIBS Easy. Please contact us for support.', 'checkout');
            $this->redirectWithNotifications('order');
        }

        return $orderPayment;
    }

    /**
     * Variables related to delivery options
     */
    protected function assignDeliveryOptionVars()
    {
        $deliveryOptionsFinder = new DeliveryOptionsFinder(
            $this->context,
            $this->getTranslator(),
            $this->objectPresenter,
            new PriceFormatter()
        );

        $message = '';
        if ($result = Message::getMessageByCartId($this->context->cart->id)) {
            $message = $result['message'];
        }

        $this->context->smarty->assign([
            'delivery_options' => $deliveryOptionsFinder->getDeliveryOptions(),
            'delivery_option' => $deliveryOptionsFinder->getSelectedDeliveryOption(),
            'delivery_message' => $message,
            'id_address' => $this->context->cart->id_address_delivery,
        ]);
    }

    /**
     * Get delivery address by context language
     *
     * @return int
     */
    protected function getDeliveryAddressId()
    {
        $idAddress = null;

        switch ($this->context->currency->iso_code) {
            case 'DKK':
                $idAddress = Configuration::get('DIBS_DENMARK_ADDRESS_ID');
                break;
            case 'NOK':
                $idAddress = Configuration::get('DIBS_NORWAY_ADDRESS_ID');
                break;
            case 'SEK':
            default:
                $idAddress = Configuration::get('DIBS_SWEEDEN_ADDRESS_ID');
                break;
        }

        return (int) $idAddress;
    }
}
