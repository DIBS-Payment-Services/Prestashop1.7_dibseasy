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

namespace Invertus\DibsEasy\Service;

use Context;
use Country;

/**
 * Provides data about default shipping country
 */
class DefaultShippingCountryProvider
{
    /**
     * @var CountryMapper
     */
    private $countryMapper;

    /**
     * @param CountryMapper $countryMapper
     */
    public function __construct(CountryMapper $countryMapper)
    {
        $this->countryMapper = $countryMapper;
    }

    /**
     * @return array
     */
    public function getAvailableCountries()
    {
        $context = Context::getContext();

        $countries = Country::getCountriesByIdShop(
            $context->shop->id,
            $context->language->id
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

        $supportedCountries = $this->countryMapper->mappings();
        $availableCountries = array_intersect($activeCountries, $supportedCountries);

        if (empty($availableCountries)) {
            return $activeCountries;
        }

        $formattedCountries = [];

        foreach ($availableCountries as $countryId => $isoCode) {
            $formattedCountries[] = [
                'id' => $countryId,
                'name' => Country::getNameById(
                    $context->language->id,
                    $countryId
                ),
            ];
        }

        return $formattedCountries;
    }
}
