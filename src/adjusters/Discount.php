<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */
namespace kuriousagency\commerce\addons\adjusters;

use kuriousagency\commerce\addons\Addons;

use Craft;

use kuriousagency\commerce\addons\models\Discount as DiscountModel;
use kuriousagency\commerce\addons\records\Discount as DiscountRecord;
use kuriousagency\commerce\currencyprices\CurrencyPrices;

use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\DiscountAdjustmentsEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin as Commerce;
use DateTime;

/**
 * Discount Adjuster
 *
 * @author Kurious Agency
 * @since 1.0.0
 */
class Discount extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    /**
     * The discount adjustment type.
     */
    const ADJUSTMENT_TYPE = 'discount';

    // Properties
    // =========================================================================

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var
     */
    private $_discount;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
		$this->_order = $order;

        $adjustments = [];
        $availableDiscounts = [];
		$discounts = Addons::$plugin->service->getAllDiscounts();

        foreach ($discounts as $discount) {
            if (Addons::$plugin->service->matchOrder($order, $discount)) {
                $availableDiscounts[] = $discount;
            }
		}

		// Craft::dd($availableDiscounts);

        foreach ($availableDiscounts as $discount) {
            $newAdjustments = $this->_getAdjustments($discount);
            if ($newAdjustments) {
                array_push($adjustments, ...$newAdjustments);
            }
        }

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param DiscountModel $discount
     * @return OrderAdjustment
     */
    private function _createOrderAdjustment(DiscountModel $discount): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $discount->name;
        $adjustment->setOrder($this->_order);
        $adjustment->description = $discount->description;
        $adjustment->sourceSnapshot = $discount->toArray();

        return $adjustment;
    }

    /**
     * @param DiscountModel $discount
     * @return OrderAdjustment[]|false
     */
    private function _getAdjustments(DiscountModel $discount)
    {
        $adjustments = [];

        $this->_discount = $discount;

        $now = new DateTime();
        $from = $this->_discount->dateFrom;
        $to = $this->_discount->dateTo;
        if (($from && $from > $now) || ($to && $to < $now)) {
            return false;
        }

        //checking items that match the conditions
        $matchingQty = 0;
        $matchingTotal = 0;
        $matchingLineIds = [];
        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            if (Addons::$plugin->service->matchLineItem($item, $this->_discount)) {
                if (!$this->_discount->allGroups) {
                    $customer = $this->_order->getCustomer();
                    $user = $customer ? $customer->getUser() : null;
                    $userGroups = Commerce::getInstance()->getCustomers()->getUserGroupIdsForUser($user);
                    if ($user && array_intersect($userGroups, $this->_discount->getUserGroupIds())) {
                        $matchingLineIds[] = $lineItemHashId;
                        $matchingQty += $item->qty;
                        $matchingTotal += $item->getSubtotal();
                    }
                } else {
                    $matchingLineIds[] = $lineItemHashId;
                    $matchingQty += $item->qty;
                    $matchingTotal += $item->getSubtotal();
                }
            }
		}

		if(!$matchingLineIds) {
			return false;
		}

        if (!$matchingQty) {
            return false;
        }

        // Have they entered a max qty?
        if ($this->_discount->maxPurchaseQty > 0 && $matchingQty > $this->_discount->maxPurchaseQty) {
            return false;
        }

        // Reject if they have not added enough matching items
        if ($matchingQty < $this->_discount->purchaseQty) {
            return false;
        }

        // Reject if the matching items values is not enough
        if ($matchingTotal < $this->_discount->purchaseTotal) {
            return false;
		}

		$price = null;

		if (Craft::$app->plugins->isPluginEnabled('commerce-currency-prices')) {
			$price = CurrencyPrices::$plugin->addons->getPricesByAddonIdAndCurrency($this->_discount->id, $this->_order->paymentCurrency);
			if ($price) $price = (object) $price;
		}

		// apply discounts to matched products
        foreach ($this->_order->getLineItems() as $item) {

			if (Addons::$plugin->service->matchProductLineItem($item, $this->_discount)) {

				// $lineItemHashId = spl_object_hash($item);
				// if ($matchingLineIds && in_array($lineItemHashId, $matchingLineIds, false)) {
					$adjustment = $this->_createOrderAdjustment($this->_discount);
					$adjustment->setLineItem($item);

					// $amountPerItem = Currency::round($this->_discount->perItemDiscount * $item->qty);
					$amountPerItem = Currency::round(($price ? $price->perItemDiscount : $this->_discount->perItemDiscount) * $item->qty);

					//Default is percentage off already discounted price
					$existingLineItemDiscount = $item->getAdjustmentsTotalByType('discount');
					$existingLineItemPrice = ($item->getSubtotal() + $existingLineItemDiscount);
					$amountPercentage = Currency::round($this->_discount->percentDiscount * $existingLineItemPrice);

					if ($this->_discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
						$amountPercentage = Currency::round($this->_discount->percentDiscount * $item->getSubtotal());
					}

					$adjustment->amount = $amountPerItem + $amountPercentage;

					if ($adjustment->amount != 0) {
						$adjustments[] = $adjustment;
					}
				// }
			}
			
		}

		// exit("exit");
		
        // only display adjustment if an amount was calculated
        if (!count($adjustments)) {
            return false;
        }

        return $adjustments;
    }
}
