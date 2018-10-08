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

namespace Invertus\DibsEasy\ValueObject;

class Consumer
{
    const TYPE_B2C = 'B2C';
    const TYPE_B2B = 'B2B';

    private function __construct()
    {
    }

    /**
     * @return string
     */
    public static function b2cAndB2bWithDefaultB2cType()
    {
        return sprintf('%s_%s', Consumer::TYPE_B2C, Consumer::TYPE_B2B);
    }

    /**
     * @return string
     */
    public static function b2bAndB2cWithDefaultB2bType()
    {
        return sprintf('%s_%s', Consumer::TYPE_B2B, Consumer::TYPE_B2C);
    }
}
