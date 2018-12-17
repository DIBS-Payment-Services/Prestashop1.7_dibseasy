<?php
/**
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace Invertus\DibsEasy\Action;

use Invertus\DibsEasy\Payment\PaymentUpdateCartItemsRequest;
use Invertus\DibsEasy\Service\PaymentService;

/**
 * Class PaymentUpdateCartItemsAction updates payment items.
 * It is used to update all cart items when shipping address has changed.
 */
class PaymentUpdateCartItemsAction extends AbstractAction
{
    /**
     * @var \Module
     */
    private $module;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @param \Module $module
     * @param PaymentService $paymentService
     */
    public function __construct(\Module $module, PaymentService $paymentService)
    {
        $this->module = $module;
        $this->paymentService = $paymentService;
    }

    /**
     * Collect payment items (products, discounts, shipping & etc) from cart and update DISB easy payment.
     *
     * @param string $paymentId
     * @param \Cart $cart
     *
     * @return bool
     */
    public function updatePaymentItems($paymentId, \Cart $cart)
    {
        $items = $this->getCartProductItems($cart);

        $additionalItems = $this->getCartAdditionalItems($cart);
        foreach ($additionalItems as $item) {
            $items[] = $item;
        }

        $updateCartItemsRequest = new PaymentUpdateCartItemsRequest(
            $items,
            $cart->getOrderTotal(),
            $paymentId
        );

        return $this->paymentService->updateItems($updateCartItemsRequest);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getModule()
    {
        return $this->module;
    }
}
