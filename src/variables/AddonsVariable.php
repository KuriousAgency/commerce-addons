<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons\variables;

use kuriousagency\commerce\addons\Addons;

use Craft;

/**
 * @author    Kurious Agency
 * @package   Addons
 * @since     1.0.0
 */
class AddonsVariable
{
    // Public Methods
    // =========================================================================

    /**
     * @param null $optional
     * @return string
     */
    public function getAddOns($purchasable,$currency)
    {
		$elements = Addons::$plugin->service->getAddonsByPurchasable($purchasable,$currency);

		return $elements;
		
    }
}
