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

namespace Invertus\DibsEasy\Payment;

class PaymentUpdateCartItemsRequest
{
    /**
     * @var PaymentItem[]
     */
    private $paymentItems;

    /**
     * @var int
     */
    private $amountTotal;

    /**
     * @var string
     */
    private $paymentId;

    /**
     * @param PaymentItem[] $paymentItems
     * @param float $amountTotal
     * @param string $paymentId
     */
    public function __construct(array $paymentItems, $amountTotal, $paymentId)
    {
        $this->paymentItems = $paymentItems;
        $this->amountTotal = (int) (string) ((float)$amountTotal * 100);
        $this->paymentId = $paymentId;
    }

    /**
     * @return PaymentItem[]
     */
    public function getPaymentItems()
    {
        return $this->paymentItems;
    }

    /**
     * @return int
     */
    public function getAmountTotal()
    {
        return $this->amountTotal;
    }

    /**
     * @return string
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'amount' => $this->amountTotal,
            'items' => array_map(function (PaymentItem $item) {
                return $item->toArray();
            }, $this->paymentItems),
            'shipping' => [
                'costSpecified' => true,
            ],
        ];
    }
}
