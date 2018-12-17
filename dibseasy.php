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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class DibsEasy
 */
class DibsEasy extends PaymentModule
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * DibsEasy constructor.
     */
    public function __construct()
    {
        $this->name = 'dibseasy';
        $this->author = 'Invertus';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->controllers = ['validation', 'checkout'];
        $this->compatibility = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
        $this->module_key = '7aa447652d62fa94766ded6234e74266';

        parent::__construct();

        $this->autoload();
        $this->compile();

        $this->displayName = $this->l('DIBS Easy Checkout');
        $this->description = $this->l('Accept payments via DIBS Easy Checkout.');
    }

    /**
     * Redirect to configuration page
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDibsConfiguration'));
    }

    /**
     * @return bool
     */
    public function install()
    {
        /** @var \Invertus\DibsEasy\Install\Installer $installer */
        $installer = $this->get('dibs.installer');

        return parent::install() && $installer->install();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        /** @var \Invertus\DibsEasy\Install\Installer $installer */
        $installer = $this->get('dibs.installer');

        return $installer->uninstall() && parent::uninstall();
    }

    /**
     * Let PS automatically handle tabs install/uninstall
     *
     * @return array
     */
    public function getTabs()
    {
        /** @var \Invertus\DibsEasy\Install\Installer $installer */
        $installer = $this->get('dibs.installer');

        return $installer->getTabs();
    }

    /**
     * Get service from container
     *
     * @param string $id
     *
     * @return object
     */
    public function get($id)
    {
        return $this->container->get($id);
    }

    /**
     * Get parameter from service container
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Add global CSS and JS to front controller
     */
    public function hookActionFrontControllerSetMedia()
    {
        $globalJsVariables = [
            'dibsGlobal' => [
                'checkoutUrl' => $this->context->link->getModuleLink($this->name, 'checkout'),
            ],
        ];

        Media::addJsDef($globalJsVariables);

        $this->context->controller->addJS(
            $this->getPathUri().'views/js/global.js'
        );
    }

    /**
     * Add custom JS & CSS to admin controllers
     */
    public function hookActionAdminControllerSetMedia()
    {
        $controller = Tools::getValue('controller');

        if ('AdminOrders' == $controller) {
            $this->context->controller->addJS($this->getPathUri().'views/js/admin-orders.js');
        }
    }

    /**
     * Get module payment options
     *
     * @return array|PaymentOption[]
     */
    public function hookPaymentOptions()
    {
        if (!$this->isConfigured() || !$this->active || !$this->checkCurrency($this->context->cart)) {
            return [];
        }

        $paymentOption = new PaymentOption();
        $paymentOption->setCallToActionText($this->l('Pay by DIBS Easy Checkout'));
        $paymentOption->setAction($this->context->link->getModuleLink($this->name, 'checkout'));

        return [$paymentOption];
    }

    /**
     * Display payment return content
     *
     * @param array $params
     *
     * @return string
     */
    public function hookPaymentReturn(array $params)
    {
        if (!$this->active) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];
        $idOrder = $order->id;
        $idLang = $this->context->language->id;
        $currentOrderState = $order->getCurrentOrderState();
        $orderDetailsUrl = $this->context->link->getPageLink('order-detail', 1, $idLang, ['id_order' => $idOrder]);

        $this->context->smarty->assign([
            'currentOrderState' => $currentOrderState->name[$this->context->language->id],
            'orderDetailsUrl' => $orderDetailsUrl,
        ]);

        return $this->context->smarty->fetch($this->getLocalPath().'views/templates/hook/payment_return.tpl');
    }

    /**
     * Display payment actions
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminOrder(array $params)
    {
        $idOrder = $params['id_order'];
        $order = new Order($idOrder);

        if ($this->name != $order->module) {
            return '';
        }

        /** @var \Invertus\DibsEasy\Repository\OrderPaymentRepository $orderPaymentRepository */
        $orderPaymentRepository = $this->get('dibs.repository.order_payment');
        $orderPayment = $orderPaymentRepository->findOrderPaymentByOrderId($idOrder);
        if (!$orderPayment ||
            (!$orderPayment->canBeCanceled() &&
            !$orderPayment->canBeCharged() &&
            !$orderPayment->canBeRefunded())
        ) {
            return '';
        }

        $adminOrderUrl = $this->context->link->getAdminLink('AdminOrders');

        $this->context->smarty->assign([
            'dibsPaymentCanBeCanceled' => $orderPayment->canBeCanceled(),
            'dibsPaymentCanBeCharged' => $orderPayment->canBeCharged(),
            'dibsPaymentCanBeRefunded' => $orderPayment->canBeRefunded(),
            'dibsCancelUrl' => $adminOrderUrl.'&action=cancelPayment&id_order='.(int)$idOrder,
            'dibsChargeUrl' => $adminOrderUrl.'&action=chargePayment&id_order='.(int)$idOrder,
            'dibsRefundUrl' => $adminOrderUrl.'&action=refundPayment&id_order='.(int)$idOrder,
        ]);

        return $this->context->smarty->fetch($this->getLocalPath().'views/templates/hook/displayAdminOrder.tpl');
    }

    /**
     * Handle partial refund on order slip creation
     *
     * @param array $params
     */
    public function hookActionOrderSlipAdd(array $params)
    {
        /** @var Order $order */
        $order = $params['order'];

        $shippingCostRefund = Tools::getValue('partialRefundShippingCost');

        /** @var \Invertus\DibsEasy\Action\PaymentRefundAction $refundAction */
        $refundAction = $this->get('dibs.action.payment_refund');

        $success = $refundAction->partialRefundPayment($order, $params['productList'], $shippingCostRefund);
        if (!$success) {
            $this->context->controller->errors[] =
                $this->l('Partial refund was successfully created, but failed to partially refund in DIBS Easy');
        }
    }

    /**
     * Get additional template varibles
     *
     * @param array $params
     */
    public function hookActionGetExtraMailTemplateVars(array &$params)
    {
        $template = $params['template'];
        if ('order_conf' != $template) {
            return;
        }

        /** @var Cart $cart */
        $cart = $params['cart'];
        $idOrder = Order::getIdByCartId($cart->id);
        $order = new Order($idOrder);

        if ($this->name != $order->module || !Validate::isLoadedObject($order)) {
            $params['extra_template_vars']['{dibs_html_block}'] = '';
            $params['extra_template_vars']['{dibs_txt_block}'] = '';
            return;
        }

        /** @var \Invertus\DibsEasy\Adapter\ConfigurationAdapter $configuration */
        $configuration = $this->get('dibs.adapter.configuration');
        /** @var \Invertus\DibsEasy\Repository\OrderPaymentRepository $orderPaymentRepository */
        $orderPaymentRepository = $this->get('dibs.repository.order_payment');
        $orderPayment = $orderPaymentRepository->findOrderPaymentByCartId($cart->id);

        /** @var \Invertus\DibsEasy\Action\PaymentGetAction $getPaymentAction */
        $getPaymentAction = $this->get('dibs.action.payment_get');
        $payment = $getPaymentAction->getPayment($orderPayment->id_payment);

        $idLang = $this->context->language->id;
        $carrier = new Carrier($order->id_carrier);
        $orderState = $order->getCurrentOrderState();

        $tplVars = [
            'dibs_payment_id' => $orderPayment->id_payment,
            'dibs_delay' => $carrier->delay[$idLang],
            'dibs_contact_email' => $configuration->get('PS_SHOP_EMAIL'),
            'dibs_order_state' => $orderState->name[$idLang],
            'dibs_payment_type' => '',
            'dibs_masked_pan' => '',
        ];

        if ($payment instanceof \Invertus\DibsEasy\Result\Payment) {
            $paymentDetail = $payment->getPaymentDetail();
            $tplVars['dibs_payment_type'] = $paymentDetail->getPaymentType();
            $tplVars['dibs_masked_pan'] = $paymentDetail->getCardDetails()->getMaskedPan();
        }

        $this->context->smarty->assign($tplVars);

        $params['extra_template_vars']['{dibs_html_block}'] = $this->context->smarty->fetch(
            $this->getLocalPath().'views/templates/hook/actionGetExtraMailTemplateVars.tpl'
        );
        $params['extra_template_vars']['{dibs_txt_block}'] = $this->context->smarty->fetch(
            $this->getLocalPath().'views/templates/hook/actionGetExtraMailTemplateVars.txt'
        );
    }

    /**
     * Associate new address from payment with order/cart
     * @todo: should be moved to separate class
     *
     * @param array $params
     *
     * @return bool
     */
    public function hookActionObjectOrderAddAfter(array $params)
    {
        /** @var Order $order */
        $order = $params['object'];

        if ($order->module != $this->name) {
            return true;
        }

        /** @var \Invertus\DibsEasy\Repository\OrderPaymentRepository $orderPaymentRepository */
        $orderPaymentRepository = $this->get('dibs.repository.order_payment');
        $orderPayment = $orderPaymentRepository->findOrderPaymentByCartId($this->context->cart->id);

        /** @var \Invertus\DibsEasy\Action\PaymentGetAction $paymentGetAction */
        $paymentGetAction = $this->get('dibs.action.payment_get');
        $payment = $paymentGetAction->getPayment($orderPayment->id_payment);

        /** @var \Invertus\DibsEasy\Util\AddressChecksum $addressChecksumUtil */
        $addressChecksumUtil = $this->get('dibs.util.address_checksum');

        $shippingAddress = $payment->getConsumer()->getShippingAddress();
        $person = $payment->getConsumer()->getPrivatePerson();
        $company = $payment->getConsumer()->getCompany();

        $firstName = $person->getFirstName() ?: $company->getFirstName();
        $lastName = $person->getLastName() ?: $company->getLastName();
        if ($person->getPhoneNumber()->getPrefix()) {
            $phone = $person->getPhoneNumber()->getPrefix().$person->getPhoneNumber()->getNumber();
        } else {
            $phone = $company->getPhoneNumber()->getPrefix().$company->getPhoneNumber()->getNumber();
        }

        /** @var \Invertus\DibsEasy\Service\CountryMapper $countryMapper */
        $countryMapper = $this->get('dibs.service.country_mapper');
        $countryIso = $countryMapper->getIso2Code($shippingAddress->getCountry());

        $saveAddress = true;
        $deliveryAddress = new Address($this->context->cart->id_address_delivery);
        $deliveryAddress->alias = $this->l('DIBS EASY Address');
        $deliveryAddress->address1 = $shippingAddress->getAddressLine1();
        $deliveryAddress->address2 = $shippingAddress->getAddressLine2();
        $deliveryAddress->postcode = $shippingAddress->getPostalCode();
        $deliveryAddress->city = $shippingAddress->getCity();
        $deliveryAddress->id_country = Country::getByIso($countryIso);
        $deliveryAddress->firstname = $firstName;
        $deliveryAddress->lastname = $lastName;
        $deliveryAddress->phone = $phone;
        $deliveryAddress->id_customer = $this->context->cart->id_customer;

        $deliveryAddressChecksum = $addressChecksumUtil->generateChecksum($deliveryAddress);

        // If same address already exists then use it, otherwise create new one
        $customerAddresses = new PrestaShopCollection('Address', $this->context->language->id);
        $customerAddresses->where('id_customer', '=', $this->context->cart->id_customer);
        $customerAddresses->where('deleted', '=', 0);

        /** @var Address $address */
        foreach ($customerAddresses as $address) {
            $addressChecksum = $addressChecksumUtil->generateChecksum($address);

            if ($addressChecksum == $deliveryAddressChecksum) {
                $deliveryAddress = $address;
                $saveAddress = false;
                break;
            }
        }

        if ($saveAddress || !Validate::isLoadedObject($deliveryAddress)) {
            if (!$deliveryAddress->save()) {
                return false;
            }
        }

        $order->id_address_delivery = $deliveryAddress->id;
        $order->id_address_invoice = $deliveryAddress->id;

        $this->context->cart->id_address_delivery = 0;
        $this->context->cart->id_address_invoice = 0;

        return $this->context->cart->save() && $order->save();
    }

    /**
     * Check if module supports cart currency
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function checkCurrency(Cart $cart)
    {
        $currency = new Currency($cart->id_currency);
        $supportedCurrencies = $this->getParameter('supported_currencies');

        return in_array($currency->iso_code, $supportedCurrencies);
    }

    /**
     * Check if module is configured based on mode
     *
     * @return bool
     */
    public function isConfigured()
    {
        /** @var \Invertus\DibsEasy\Adapter\ConfigurationAdapter $configuration */
        $configuration = $this->get('dibs.adapter.configuration');
        $testingMode = (bool) $configuration->get('DIBS_TEST_MODE');
        $merchantId = $configuration->get('DIBS_MERCHANT_ID');

        switch ($testingMode) {
            case true:
                $secretKey = $configuration->get('DIBS_TEST_SECRET_KEY');
                $checkoutKey = $configuration->get('DIBS_TEST_CHECKOUT_KEY');
                break;
            case false:
                $secretKey = $configuration->get('DIBS_PROD_SECRET_KEY');
                $checkoutKey = $configuration->get('DIBS_PROD_CHECKOUT_KEY');
                break;
        }

        return !empty($merchantId) && !empty($secretKey) && !empty($checkoutKey);
    }

    /**
     * Build module service container
     */
    private function compile()
    {
        $this->container = new ContainerBuilder();

        $locator = new FileLocator($this->getLocalPath().'etc/config');
        $loader  = new YamlFileLoader($this->container, $locator);
        $loader->load('config.yml');

        $this->container->compile();
    }

    /**
     * Require autoloader
     */
    private function autoload()
    {
        require_once $this->getLocalPath().'vendor/autoload.php';
    }
}
