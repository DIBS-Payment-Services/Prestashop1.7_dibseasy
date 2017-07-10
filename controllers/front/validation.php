<?php
use Invertus\Dibs\Result\Payment;

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

class DibsValidationModuleFrontController extends ModuleFrontController
{
    const FILENAME = 'validation';

    /**
     * @var Dibs
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
        // Get payment which is associated with cart
        // It's simple mapping (id_cart - id_order - id_payment (dibs) - id_charge (dibs) - etc.)
        /** @var \Invertus\Dibs\Repository\OrderPaymentRepository $orderPaymentRepository */
        $orderPaymentRepository = $this->module->get('dibs.repository.order_payment');
        $orderPayment = $orderPaymentRepository->findOrderPaymentByCartId($this->context->cart->id);
        if (!$orderPayment instanceof DibsOrderPayment) {
            $this->errors[] = $this->module->l('Unexpected error occured.', self::FILENAME);
            $this->redirectWithNotifications($this->context->link->getModuleLink($this->module->name, 'checkout'));
        }

        // Before creating order let's make some validations
        // First let's check if paid amount and currency is the same as it is in cart
        $payment = $this->validateCartPayment($orderPayment->id_payment);
        if (false == $payment) {
            $this->errors[] = $this->module->l('Payment validation has failed.', self::FILENAME);
            $this->redirectWithNotifications($this->context->link->getModuleLink($this->module->name, 'checkout'));
        }

        // Update payment mapping to be reserved
        $orderPayment->is_reserved = 1;
        $orderPayment->update();

        // Then check if payment country is valid
        if (!$this->validatePaymentCountry($payment)) {
            $this->cancelCartPayment();
            $this->errors[] = $this->module->l('Payment was canceled due to invalid country.', self::FILENAME);
            $this->redirectWithNotifications($this->context->link->getModuleLink($this->module->name, 'checkout'));
        }

        // If validations passed, let do some processing before creating order
        // First assign customer to cart if it does not exist
        if (!$this->processSaveCartCustomer($payment)) {
            $this->cancelCartPayment();
            $errorMessage = $this->module->l(
                'Payment was canceled, because customer with email %s was found, please sign in.',
                self::FILENAME
            );

            $this->errors[] = sprintf($errorMessage, $payment->getConsumer()->getPrivatePerson()->getEmail());
            $this->redirectWithNotifications($this->context->link->getModuleLink($this->module->name, 'checkout'));
        }

        // After processing is done, let's create order
        try {
            $this->module->validateOrder(
                $this->context->cart->id,
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
            /** @var \Invertus\Dibs\Action\PaymentCancelAction $paymentCancelAction */
            $paymentCancelAction = $this->module->get('dibs.action.payment_cancel');
            $paymentCancelAction->cancelCartPayment($this->context->cart);

            $this->errors[] = $this->module->l('Payment was canceled due to order creation failure.', self::FILENAME);
            $this->redirectWithNotifications($this->context->link->getModuleLink($this->module->name, 'checkout'));
        }

        $idOrder = Order::getIdByCartId($this->context->cart->id);
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

        $idCustomer = Customer::customerExists($person->getEmail(), true, false);
        if ($idCustomer) {
            return false;
        }

        $newPassword = Tools::passwdGen();

        $customer = new Customer();
        $customer->firstname = $person->getFirstName();
        $customer->lastname = $person->getLastName();
        $customer->email = $person->getEmail();
        $customer->passwd = Tools::hash($newPassword);
        $customer->is_guest = 0;
        $customer->id_default_group = Configuration::get('PS_CUSTOMER_GROUP', null, $this->context->cart->id_shop);
        $customer->newsletter = 0;
        $customer->optin = 0;
        $customer->active = 1;
        $customer->id_gender = 9;
        $customer->save();

        $this->sendConfirmationEmail($customer, $newPassword);

        $this->context->cart->id_customer = $customer->id;
        $this->context->cart->secure_key = $customer->secure_key;

        return $this->context->cart->save();
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
        /** @var \Invertus\Dibs\Action\PaymentGetAction $paymentGetAction */
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
        $alpha2Iso = null;

        switch ($alpha3Iso) {
            case 'SWE':
                $alpha2Iso = 'SE';
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

        /** @var \Invertus\Dibs\Action\PaymentCancelAction $paymentCancelAction */
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
