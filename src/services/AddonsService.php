<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons\services;

use kuriousagency\commerce\addons\Addons;

use Craft;

use craft\commerce\elements\Order;
use kuriousagency\commerce\addons\models\Discount;
use kuriousagency\commerce\addons\records\Discount as DiscountRecord;
use kuriousagency\commerce\addons\records\ConditionCategory as ConditionCategoryRecord;
use kuriousagency\commerce\addons\records\ConditionPurchasable as ConditionPurchasableRecord;
use kuriousagency\commerce\addons\records\ConditionUserGroup as ConditionUserGroupRecord;
use kuriousagency\commerce\addons\records\ProductCategory as ProductCategoryRecord;
use kuriousagency\commerce\addons\records\ProductPurchasable as ProductPurchasableRecord;

use craft\db\Query;
use craft\elements\Category;
use craft\commerce\models\LineItem;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use DateTime;
use yii\base\Component;
use yii\base\Exception;
use yii\db\Expression;
use function in_array;

/**
 * @author    Kurious Agency
 * @package   Addons
 * @since     1.0.0
 */
class AddonsService extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var Discount[]
     */
    private $_allDiscounts;

    // Public Methods
    // =========================================================================

    /**
     * Get a discount by its ID.
     *
     * @param int $id
     * @return Discount|null
     */
    public function getDiscountById($id)
    {
        foreach ($this->getAllDiscounts() as $discount) {
            if ($discount->id == $id) {
                return $discount;
            }
        }

        return null;
    }

    /**
     * Get all discounts.
     *
     * @return Discount[]
     */
    public function getAllDiscounts(): array
    {
        if (null === $this->_allDiscounts) {
            $discounts = $this->_createDiscountQuery()
                ->addSelect([
                    'ap.purchasableId',
                    'apt.categoryId',
					'aug.userGroupId',
					'app.purchasableId as productPurchasableId',
					'appt.categoryId as productCategoryId'
                ])
                ->leftJoin('{{%addons_condition_purchasables}} ap', '[[ap.discountId]]=[[discounts.id]]')
                ->leftJoin('{{%addons_condition_categories}} apt', '[[apt.discountId]]=[[discounts.id]]')
				->leftJoin('{{%addons_condition_usergroups}} aug', '[[aug.discountId]]=[[discounts.id]]')
				->leftJoin('{{%addons_product_purchasables}} app', '[[app.discountId]]=[[discounts.id]]')
				->leftJoin('{{%addons_product_categories}} appt', '[[appt.discountId]]=[[discounts.id]]')
				->orderBy('app.id')
                ->all();

            $allDiscountsById = [];
            $purchasables = [];
            $categories = [];
			$userGroups = [];
			$productPurchasables = [];
        	$productCategories = [];

            foreach ($discounts as $discount) {
                $id = $discount['id'];
                if ($discount['purchasableId']) {
                    $purchasables[$id][] = $discount['purchasableId'];
                }

                if ($discount['categoryId']) {
                    $categories[$id][] = $discount['categoryId'];
                }

                if ($discount['userGroupId']) {
                    $userGroups[$id][] = $discount['userGroupId'];
				}
				
				if ($discount['productPurchasableId']) {
					$productPurchasables[$id][] = $discount['productPurchasableId'];
				}
	
				if ($discount['productCategoryId']) {
					$productCategories[$id][] = $discount['productCategoryId'];
				}

                unset($discount['purchasableId'], $discount['userGroupId'], $discount['categoryId'], $discount['productPurchasableId'], $discount['productCategoryId']);

                if (!isset($allDiscountsById[$id])) {
                    $allDiscountsById[$id] = new Discount($discount);
                }
            }

            foreach ($allDiscountsById as $id => $discount) {
                $discount->setPurchasableIds($purchasables[$id] ?? []);
                $discount->setCategoryIds($categories[$id] ?? []);
				$discount->setUserGroupIds($userGroups[$id] ?? []);
				$discount->setProductPurchasableIds($productPurchasables[$id] ?? []);
        		$discount->setProductCategoryIds($productCategories[$id] ?? []);
            }

            $this->_allDiscounts = $allDiscountsById;
        }

        return $this->_allDiscounts;
    }

    /**
     * Populates a discount's relations.
     *
     * @param Discount $discount
     */
    public function populateDiscountRelations(Discount $discount)
    {
        $rows = (new Query())->select(
            'ap.purchasableId,
            apt.categoryId,
			aug.userGroupId,
			app.purchasableId as productPurchasableId,
            appt.categoryId as productCategoryId')
            ->from('{{%addons_discounts}} discounts')
            ->leftJoin('{{%addons_condition_purchasables}} ap', '[[ap.discountId]]=[[discounts.id]]')
            ->leftJoin('{{%addons_condition_categories}} apt', '[[apt.discountId]]=[[discounts.id]]')
			->leftJoin('{{%addons_condition_usergroups}} aug', '[[aug.discountId]]=[[discounts.id]]')
			->leftJoin('{{%addons_product_purchasables}} app', '[[app.discountId]]=[[discounts.id]]')
            ->leftJoin('{{%addons_product_categories}} appt', '[[appt.discountId]]=[[discounts.id]]')
			->where(['discounts.id' => $discount->id])
			->orderBy('app.id')
            ->all();

        $purchasableIds = [];
        $categoryIds = [];
		$userGroupIds = [];
		$productPurchasableIds = [];
        $productCategoryIds = [];

        foreach ($rows as $row) {
            if ($row['purchasableId']) {
                $purchasableIds[] = $row['purchasableId'];
            }

            if ($row['categoryId']) {
                $categoryIds[] = $row['categoryId'];
            }

            if ($row['userGroupId']) {
                $userGroupIds[] = $row['userGroupId'];
			}

			if ($row['productPurchasableId']) {
                $productPurchasableIds[] = $row['productPurchasableId'];
            }

            if ($row['productCategoryId']) {
                $productCategoryIds[] = $row['productCategoryId'];
            }
        }

        $discount->setPurchasableIds($purchasableIds);
        $discount->setCategoryIds($categoryIds);
		$discount->setUserGroupIds($userGroupIds);
		$discount->setProductPurchasableIds($productPurchasableIds);
        $discount->setProductCategoryIds($productCategoryIds);
    }

    /**
     * Match a line item against a discount.
     *
     * @param LineItem $lineItem
     * @param Discount $discount
     * @return bool
     */
    public function matchLineItem(LineItem $lineItem, Discount $discount): bool
    {
        if (!$this->matchOrder($lineItem->order, $discount)) {
            return false;
        }

        if ($lineItem->onSale && $discount->excludeOnSale) {
            return false;
        }

        // can't match something not promotable
        if (!$lineItem->purchasable->getIsPromotable()) {
            return false;
        }

        if ($discount->getPurchasableIds() && !$discount->allPurchasables) {
            $purchasableId = $lineItem->purchasableId;
            if (!in_array($purchasableId, $discount->getPurchasableIds(), true)) {
                return false;
            }
        }

        if ($discount->getCategoryIds() && !$discount->allCategories && $lineItem->getPurchasable()) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable) {
                return false;
            }

            $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
            $relatedCategories = Category::find()->relatedTo($relatedTo)->ids();
            $purchasableIsRelateToOneOrMoreCategories = (bool)array_intersect($relatedCategories, $discount->getCategoryIds());
            if (!$purchasableIsRelateToOneOrMoreCategories) {
                return false;
            }
		}
		
		if(!$discount->getCategoryIds() && !$discount->getPurchasableIds()) {
			return false;
		}

		return true;
	}
	
	/**
     * Match a line item against a discount.
     *
     * @param LineItem $lineItem
     * @param Discount $discount
     * @return bool
     */
    public function matchProductLineItem(LineItem $lineItem, Discount $discount): bool
    {
        if (!$this->matchOrder($lineItem->order, $discount)) {
            return false;
        }

        if ($lineItem->onSale && $discount->excludeOnSale) {
            return false;
        }

        // can't match something not promotable
        if (!$lineItem->purchasable->getIsPromotable()) {
            return false;
		}

        if ($discount->getProductPurchasableIds()) {
           $purchasableId = $lineItem->purchasableId;
            if (in_array($purchasableId, $discount->getProductPurchasableIds(),true)) {
                return true;
            }
		}

        if ($discount->getProductCategoryIds() && $lineItem->getPurchasable()) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable) {
                return false;
            }

            $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
            $relatedCategories = Category::find()->relatedTo($relatedTo)->ids();
            $purchasableIsRelateToOneOrMoreCategories = (bool)array_intersect($relatedCategories, $discount->getProductCategoryIds());
            if ($purchasableIsRelateToOneOrMoreCategories) {
                return true;
            }
		}

		return false;
    }

    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     */
    public function matchOrder(Order $order, Discount $discount): bool
    {
        // If the discount is no longer enabled don't use
        if (!$discount->enabled) {
            return false;
		}

        return true;
	}
	
	public function getAddonsByPurchasable(PurchasableInterface $purchasable, $currency)
	{
		
		$addonCategories = [];
		$addonProducts = [];
		$addOnPurchasableTypes = [];
		$addonElements = [];
		$purchaseableElements = [];
		$addOnPurchasables = [];
		$discounts = [];
		
		// get product and purchasable ids
		$relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
		$relatedCategories = Category::find()->relatedTo($relatedTo)->ids();

		// get addon purchasables and addon categories related to purchasable categories
		$productCategories = $this->_createDiscountQuery()
			->select([
				'discounts.id',
				'discounts.percentDiscount',
				'discounts.perItemDiscount',
				'apc.categoryId',
				'app.purchasableId',
				'app.purchasableType'
			])	
			->leftjoin('{{%addons_condition_categories}} acc', '[[acc.discountId]]=[[discounts.id]]')	
			->leftjoin('{{%addons_product_categories}} apc', '[[apc.discountId]]=[[discounts.id]]')
			->leftjoin('{{%addons_product_purchasables}} app', '[[app.discountId]]=[[discounts.id]]')
			->where(['in', 'acc.categoryId', $relatedCategories])
			->orderBy('app.id')
			->all();


		// Craft::dd($productCategories);

		foreach($productCategories as $category) {

			$discountValue = $this->getFormattedDiscountValue($purchasable['perItemDiscount'],$purchasable['percentDiscount'],$currency);

			$addonCategories[] = $category['categoryId'];
			$addonProducts[$category['purchasableType']][] = $category['purchasableId'];

			// $discounts[$category['purchasableId']] = $discountValue;
			// $discounts[$category['categoryId']] = $discountValue;
			
			$discounts[$category['purchasableId']] = [
				'id'=> $category['id'],
				'value'=> $discountValue
			];

			$discounts[$category['categoryId']] = [
				'id'=> $category['id'],
				'value'=> $discountValue
			];
		}

		// get addon purchasables and addon categories related to purchasableId
		$productPurchasables = $this->_createDiscountQuery()
			->select([
				'discounts.id',
				'discounts.percentDiscount',
				'discounts.perItemDiscount',
				'apc.categoryId',
				'app.purchasableId',
				'app.purchasableType'
			])	
			->leftjoin('{{%addons_condition_purchasables}} ap', '[[ap.discountId]]=[[discounts.id]]')	
			->leftjoin('{{%addons_product_categories}} apc', '[[apc.discountId]]=[[discounts.id]]')
			->leftjoin('{{%addons_product_purchasables}} app', '[[app.discountId]]=[[discounts.id]]')
			->where(['ap.purchasableId' => $purchasable->id])
			->orderBy('app.id')
			->all();

		foreach($productPurchasables as $purchasable) {

			$discountValue = $this->getFormattedDiscountValue($purchasable['perItemDiscount'],$purchasable['percentDiscount'],$currency);

			$addonCategories[] = $purchasable['categoryId'];
			$addonProducts[$purchasable['purchasableType']][] = $purchasable['purchasableId'];

			// $discounts[$purchasable['purchasableId']] = $discountValue;
			// $discounts[$purchasable['categoryId']] = $discountValue;

			$discounts[$purchasable['purchasableId']] = [
				'id'=> $purchasable['id'],
				'value'=> $discountValue
			];

			$discounts[$purchasable['categoryId']] = [
				'id'=> $purchasable['id'],
				'value'=> $discountValue
			];
		}

		// get purchasable elements
		foreach($addonProducts as $type => $products) {
			$addOnPurchasables[$type] = array_unique($products);
		}
		
		foreach($addOnPurchasables as $type=>$addOnPurchasable) {
			$purchaseableElements = array_merge($purchaseableElements,$type::find()->id($addOnPurchasable)->fixedOrder(true)->all());
		}

		$addOnCategories = Category::find()->id($addonCategories)->all();

		$allElements = array_merge($purchaseableElements,$addOnCategories);

		// Craft::dd($discounts);

		foreach($allElements as $element) {
			$type = explode("\\",get_class($element));
			$type = strtolower(end($type));

			$addonElements[] = ['type'=>$type,'element' => $element,'discount'=>$discounts[$element->id]];
		}

		return $addonElements;
	}

	public function getFormattedDiscountValue($perItemDiscount,$percentDiscount,$currency)
	{
		
		$perItemDiscount = $perItemDiscount * -1;

		if($perItemDiscount > 0) {
			// return Craft::$app->getFormatter()->asCurrency($perItemDiscount, $currency, [], [], true);
			return $perItemDiscount;
		}
		
		if ($percentDiscount !== 0) {
            $string = (string)$percentDiscount;
            $number = rtrim($string, '0');
            $diff = strlen($string) - strlen($number);
            return Craft::$app->formatter->asPercent(-$percentDiscount, 2 - $diff);
		}
	}

    /**
     * Save a discount.
     *
     * @param Discount $model the discount being saved
     * @param bool $runValidation should we validate this discount before saving.
     * @return bool
     * @throws \Exception
     */
    public function saveDiscount(Discount $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = DiscountRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce-addons', 'No discount exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new DiscountRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Discount not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->description = $model->description;
        $record->dateFrom = $model->dateFrom;
        $record->dateTo = $model->dateTo;
        $record->enabled = $model->enabled;
        $record->purchaseTotal = $model->purchaseTotal;
        $record->purchaseQty = $model->purchaseQty;
        $record->maxPurchaseQty = $model->maxPurchaseQty;
        $record->perItemDiscount = $model->perItemDiscount;
        $record->percentDiscount = $model->percentDiscount;
        $record->percentageOffSubject = $model->percentageOffSubject;
        $record->excludeOnSale = $model->excludeOnSale;
        $record->sortOrder = $record->sortOrder ?: 999;
        $record->allGroups = $model->allGroups = empty($model->getUserGroupIds());
        $record->allCategories = $model->allCategories = empty($model->getCategoryIds());
        $record->allPurchasables = $model->allPurchasables = empty($model->getPurchasableIds());

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            ConditionUserGroupRecord::deleteAll(['discountId' => $model->id]);
            ConditionPurchasableRecord::deleteAll(['discountId' => $model->id]);
			ConditionCategoryRecord::deleteAll(['discountId' => $model->id]);
			ProductPurchasableRecord::deleteAll(['discountId' => $model->id]);
            ProductCategoryRecord::deleteAll(['discountId' => $model->id]);

            foreach ($model->getUserGroupIds() as $groupId) {
                $relation = new ConditionUserGroupRecord;
                $relation->userGroupId = $groupId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getCategoryIds() as $categoryId) {
                $relation = new ConditionCategoryRecord();
                $relation->categoryId = $categoryId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new ConditionPurchasableRecord();
                $element = Craft::$app->getElements()->getElementById($purchasableId);
                $relation->purchasableType = get_class($element);
                $relation->purchasableId = $purchasableId;
                $relation->discountId = $model->id;
                $relation->save(false);
			}
			
			foreach ($model->getProductCategoryIds() as $categoryId) {
                $relation = new ProductCategoryRecord();
                $relation->categoryId = $categoryId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getProductPurchasableIds() as $purchasableId) {
                $relation = new ProductPurchasableRecord();
                $element = Craft::$app->getElements()->getElementById($purchasableId);
                $relation->purchasableType = get_class($element);
                $relation->purchasableId = $purchasableId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            $transaction->commit();

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a discount by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteDiscountById($id): bool
    {
        $record = DiscountRecord::findOne($id);

        if ($record) {
            return $record->delete();
        }

        return false;
    }

    // /**
    //  * Clears a coupon's usage history.
    //  *
    //  * @param int $id the coupon's ID
    //  */
    // public function clearCouponUsageHistoryById(int $id)
    // {
    //     $db = Craft::$app->getDb();

    //     $db->createCommand()
    //         ->delete('{{%commerce_customer_discountuses}}', ['discountId' => $id])
    //         ->execute();

    //     $db->createCommand()
    //         ->delete('{{%commerce_email_discountuses}}', ['discountId' => $id])
    //         ->execute();

    //     $db->createCommand()
    //         ->update('{{%commerce_discounts}}', ['totalUses' => 0], ['id' => $id])
    //         ->execute();
    // }

    /**
     * Reorder discounts by an array of ids.
     *
     * @param array $ids
     * @return bool
     */
    public function reorderDiscounts(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%addons_discounts}}', ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        return true;
    }

    /**
     * Updates discount uses counters.
     *
     * @param Order $order
     */
    // public function orderCompleteHandler($order)
    // {
    //     if (!$order->couponCode) {
    //         return;
    //     }

    //     /** @var DiscountRecord $discount */
    //     $discount = DiscountRecord::find()->where(['code' => $order->couponCode])->one();
    //     if (!$discount || !$discount->id) {
    //         return;
    //     }

    //     if ($discount->totalUseLimit) {
    //         // Increment total uses.
    //         Craft::$app->getDb()->createCommand()
    //             ->update('{{%commerce_discounts}}', [
    //                 'totalUses' => new Expression('[[totalUses]] + 1')
    //             ], [
    //                 'code' => $order->couponCode
    //             ])
    //             ->execute();
    //     }

    //     if ($discount->perUserLimit && $order->customerId) {
    //         $customerDiscountUseRecord = CustomerDiscountUseRecord::find()->where(['customerId' => $order->customerId, 'discountId' => $discount->id])->one();

    //         if (!$customerDiscountUseRecord) {
    //             $customerDiscountUseRecord = new CustomerDiscountUseRecord();
    //             $customerDiscountUseRecord->customerId = $order->customerId;
    //             $customerDiscountUseRecord->discountId = $discount->id;
    //             $customerDiscountUseRecord->uses = 1;
    //             $customerDiscountUseRecord->save();
    //         } else {
    //             Craft::$app->getDb()->createCommand()
    //                 ->update('{{%commerce_customer_discountuses}}', [
    //                     'uses' => new Expression('[[uses]] + 1')
    //                 ], [
    //                     'customerId' => $order->customerId,
    //                     'discountId' => $discount->id
    //                 ])
    //                 ->execute();
    //         }
    //     }

    //     if ($discount->perEmailLimit && $order->customerId) {
    //         $customerDiscountUseRecord = EmailDiscountUseRecord::find()->where(['email' => $order->getEmail(), 'discountId' => $discount->id])->one();

    //         if (!$customerDiscountUseRecord) {
    //             $customerDiscountUseRecord = new EmailDiscountUseRecord();
    //             $customerDiscountUseRecord->email = $order->getEmail();
    //             $customerDiscountUseRecord->discountId = $discount->id;
    //             $customerDiscountUseRecord->uses = 1;
    //             $customerDiscountUseRecord->save();
    //         } else {
    //             Craft::$app->getDb()->createCommand()
    //                 ->update('{{%commerce_email_discountuses}}', [
    //                     'uses' => new Expression('[[uses]] + 1')
    //                 ], [
    //                     'email' => $order->getEmail(),
    //                     'discountId' => $discount->id
    //                 ])
    //                 ->execute();
    //         }
    //     }
    // }

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving discounts
     *
     * @return Query
     */
    private function _createDiscountQuery(): Query
    {
        return (new Query())
            ->select([
                'discounts.id',
                'discounts.name',
                'discounts.description',
                'discounts.dateFrom',
                'discounts.dateTo',
                'discounts.purchaseTotal',
                'discounts.purchaseQty',
                'discounts.maxPurchaseQty',
                'discounts.perItemDiscount',
                'discounts.percentDiscount',
                'discounts.percentageOffSubject',
                'discounts.excludeOnSale',
                'discounts.allGroups',
                'discounts.allPurchasables',
                'discounts.allCategories',
                'discounts.enabled',
                'discounts.sortOrder',
                'discounts.dateCreated',
                'discounts.dateUpdated',
            ])
            ->from(['discounts' => '{{%addons_discounts}}'])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
