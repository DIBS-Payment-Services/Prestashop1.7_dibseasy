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

use Invertus\DibsEasy\Result\Payment;

class DibsEasyValidationModuleFrontController extends ModuleFrontController
{
    const FILENAME = 'validation';

    /**
     * @var DibsEasy
     */
    public $module;

    /**
     * Check if customer can access this page
     */
    public function checkAccess()
    {
        $cart = $this->context->cart;
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($cart) ||
            (int) $cart->id_customer != (int) $customer->id ||
            !$this->module->isConfigured() ||
            !$this->module->active ||
            $cart->orderExists()
        ) {
            $this->cancelCartPayment();

            Tools::redirect('index.php?controller=order&step=1');
        }

        $guestCheckoutEnabled = (bool) Configuration::get('PS_GUEST_CHECKOUT_ENABLED');
        if (!$guestCheckoutEnabled && !$this->context->customer->isLogged()) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        return true;
    }

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $idCart = $this->context->cart->id;
        $checkoutUrl = $this->context->link->getModuleLink($this->module->name, 'checkout');

        // Get payment which is associated with cart
        // It's simple mapping (id_cart - id_order - id_payment (dibs) - id_charge (dibs) - etc.)
        /** @var \Invertus\DibsEasy\Repository\OrderPaymentRepository $orderPaymentRepository */
        $orderPaymentRepository = $this->module->get('dibs.repository.order_payment');
        $orderPayment = $orderPaymentRepository->findOrderPaymentByCartId($idCart);
        if (!$orderPayment instanceof DibsOrderPayment) {
            $this->errors[] = $this->module->l('Unexpected error occured.', self::FILENAME);
            $this->redirectWithNotifications($checkoutUrl);
        }

        // Before creating order let's make some validations
        // First let's check if paid amount and currency is the same as it is in cart
        $payment = $this->validateCartPayment($orderPayment->id_payment);
        if (false == $payment) {
            $this->cancelCartPayment();
            $this->errors[] = $this->module->l('Payment validation has failed. Payment was canceled.', self::FILENAME);
            $this->redirectWithNotifications($checkoutUrl);
        }

        // Update payment mapping to be reserved
        $orderPayment->is_reserved = 1;
        $orderPayment->update();

        // Then check if payment country is valid
        if (!$this->validatePaymentCountry($payment)) {
            $this->cancelCartPayment();
            $this->errors[] = $this->module->l('Payment was canceled due to invalid country.', self::FILENAME);
            $this->redirectWithNotifications($checkoutUrl);
        }

        // If validations passed, let do some processing before creating order
        // First assign customer to cart if it does not exist
        if (!$this->processSaveCartCustomer($payment)) {
            $this->cancelCartPayment();
            $this->redirectWithNotifications($checkoutUrl);
        }

        // Take care of delivery address on guest checkout
        if (!$this->context->cart->id_address_delivery) {
            $defaultAddress = new Address($this->getDeliveryAddressId());
            $defaultAddress = clone $defaultAddress;
            $defaultAddress->id = null;
            $defaultAddress->id_customer = $this->context->customer->id;
            $defaultAddress->deleted = true;

            if ($defaultAddress->save()) {
                $this->context->cart->updateAddressId(
                    $this->context->cart->id_address_delivery,
                    $defaultAddress->id
                );

                $this->context->cart->autosetProductAddress();

                $option = [$defaultAddress->id => $this->context->cart->id_carrier.','];

                $this->context->cart->setDeliveryOption($option);
                $this->context->cart->update();

                $this->context->cart->getDeliveryOption(null, false, false);
            }
        }

        // After processing is done, let's create order
        try {
            $this->module->validateOrder(
                $idCart,
                (int) Configuration::get('DIBS_ACCEPTED_ORDER_STATE_ID'),
                $this->context->cart->getOrderTotal(),
                $this->module->displayName,
                null,
                [],
                $this->context->currency->id,
                false,
                $this->context->cart->secure_key
            );
        } catch (Exception $e) {
            // If we were unable to create order then cancel payment and redirect back to checkout
            /** @var \Invertus\DibsEasy\Action\PaymentCancelAction $paymentCancelAction */
            $paymentCancelAction = $this->module->get('dibs.action.payment_cancel');
            $paymentCancelAction->cancelCartPayment($this->context->cart);

            if (_PS_MODE_DEV_) {
                throw $e;
            }

            $this->errors[] = $this->module->l('Payment was canceled due to order creation failure.', self::FILENAME);
            $this->redirectWithNotifications($checkoutUrl);
        }

        $idOrder = Order::getIdByCartId($idCart);
        $order = new Order($idOrder);

        // Update payment mappings
        $orderPayment->is_reserved = 1;
        $orderPayment->id_order = $order->id;
        $orderPayment->save();

        // After processing is done, there's one more thing to do
        // Redirect to order confirmation page
        $orderConfirmationUrl = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            $this->context->language->id,
            [
                'id_cart' => $order->id_cart,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $order->getCustomer()->secure_key,
            ]
        );

        $this->redirectWithNotifications($orderConfirmationUrl);
    }

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

    /**
     * @param Payment $payment
     *
     * @return bool
     */
    protected function processSaveCartCustomer(Payment $payment)
    {
        $customer = new Customer($this->context->cart->id_customer);
        if (Validate::isLoadedObject($customer)) {
            return true;
        }

        $person = $payment->getConsumer()->getPrivatePerson();
        $company = $payment->getConsumer()->getCompany();

        $firstName = $person->getFirstName() ?: $company->getFirstName();
        $lastName = $person->getLastName() ?: $company->getLastName();
        $email = $person->getEmail() ?: $company->getEmail();

        $idCustomer = Customer::customerExists($email, true, false);
        if ($idCustomer) {
            $errorMessage = $this->module->l(
                'Payment was canceled, because customer with email %s was found, please sign in.',
                self::FILENAME
            );
            $this->errors[] = sprintf($errorMessage, $email);
            return false;
        }

        $newPassword = Tools::passwdGen();

        $customer = new Customer();
        $customer->firstname = $firstName;
        $customer->lastname = $lastName;
        $customer->email = $email;
        $customer->passwd = Tools::hash($newPassword);
        $customer->is_guest = 0;
        $customer->id_default_group = Configuration::get('PS_CUSTOMER_GROUP', null, $this->context->cart->id_shop);
        $customer->newsletter = 0;
        $customer->optin = 0;
        $customer->active = 1;
        $customer->id_gender = 9;

        if ($errors = $customer->validateController()) {
            $this->errors = array_merge($this->errors, $errors);
            return false;
        }
        
        $customer->save();

        $this->sendConfirmationEmail($customer, $newPassword);

        $this->context->updateCustomer($customer);

        $this->context->cart->id_customer = $customer->id;
        $this->context->cart->secure_key = $customer->secure_key;

        if (!$this->context->cart->save()) {
            $this->errors[] = $this->module->l(
                'Payment was canceled, because customer account could not be saved.',
                self::FILENAME
            );
            
            return false;
        }
        
        return true;
    }

    /**
     * Validate if cart payment has been reserved.
     *
     * @param string $paymentId
     *
     * @return bool|Payment
     */
    protected function validateCartPayment($paymentId)
    {
        /** @var \Invertus\DibsEasy\Action\PaymentGetAction $paymentGetAction */
        $paymentGetAction = $this->module->get('dibs.action.payment_get');
        $payment = $paymentGetAction->getPayment($paymentId);

        if (null == $payment) {
            return false;
        }

        $cartCurrency = new Currency($this->context->cart->id_currency);
        $cartAmount = (int) (string) ($this->context->cart->getOrderTotal() * 100);

        $summary = $payment->getSummary();
        $orderDetail = $payment->getOrderDetail();

        if ($summary->getReservedAmount() != $cartAmount ||
            $orderDetail->getCurrency() != $cartCurrency->iso_code
        ) {
            return false;
        }

        return $payment;
    }

    /**
     * Validate if payment country is valid
     *
     * @param Payment $payment
     *
     * @return bool
     */
    protected function validatePaymentCountry(Payment $payment)
    {
        $country = $payment->getConsumer()
            ->getShippingAddress()
            ->getCountry();

        $alpha2IsoCode = $this->getAlpha2FromAlpha3CountryIso($country);

        return null !== $alpha2IsoCode;
    }

    /**
     * Get coutnry ISO Alpha2 from Country ISO Alpha3
     *
     * @param string $alpha3Iso
     *
     * @return null|string
     */
    protected function getAlpha2FromAlpha3CountryIso($alpha3Iso)
    {
        /** @var \Invertus\DibsEasy\Service\CountryMapper $countryMapper */
        $countryMapper = $this->module->get('dibs.service.country_mapper');
        $mappings = $countryMapper->mappings();

        $alpha2Iso = null;

        if (isset($mappings[$alpha3Iso])) {
            $alpha2Iso = $mappings[$alpha3Iso];
        }

        return $alpha2Iso;
    }

    /**
     * Cancel any payment that has been reserved
     *
     * @return bool
     */
    protected function cancelCartPayment()
    {
        if (!Validate::isLoadedObject($this->context->cart)) {
            return true;
        }

        /** @var \Invertus\DibsEasy\Action\PaymentCancelAction $paymentCancelAction */
        $paymentCancelAction = $this->module->get('dibs.action.payment_cancel');

        return $paymentCancelAction->cancelCartPayment($this->context->cart);
    }

    /**
     * Send welcome email if new customer is created
     *
     * @param Customer $customer
     * @param string $password
     *
     * @return bool|int
     */
    private function sendConfirmationEmail(Customer $customer, $password)
    {
        if (!Configuration::get('PS_CUSTOMER_CREATION_EMAIL')) {
            return true;
        }

        return Mail::Send(
            $this->context->language->id,
            'account',
            Mail::l('Welcome!'),
            [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{email}' => $customer->email,
                '{passwd}' => $password
            ],
            $customer->email,
            $customer->firstname.' '.$customer->lastname
        );
    }
}
