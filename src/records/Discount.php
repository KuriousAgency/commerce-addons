<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons\records;

use craft\db\ActiveRecord;
use craft\records\Category;
use craft\records\UserGroup;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Discount record.
 *
 * @property bool $allCategories
 * @property bool $allGroups
 * @property bool $allPurchasables
 * @property float $baseDiscount
 * @property string $code
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property string $description
 * @property ActiveQueryInterface $discountUserGroups
 * @property bool $enabled
 * @property bool $excludeOnSale
 * @property bool $hasFreeShippingForMatchingItems
 * @property bool $hasFreeShippingForOrder
 * @property UserGroup[] $groups
 * @property int $id
 * @property int $maxPurchaseQty
 * @property string $name
 * @property string $percentageOffSubject
 * @property float $percentDiscount
 * @property int $perEmailLimit
 * @property float $perItemDiscount
 * @property int $perUserLimit
 * @property int $purchaseQty
 * @property int $purchaseTotal
 * @property int $sortOrder
 * @property int $totalUseLimit
 * @property int $totalUses
 * @author Kurious Agency
 * @since 1.0.0
 */
class Discount extends ActiveRecord
{
    // Constants
    // =========================================================================

    const TYPE_ORIGINAL_SALEPRICE = 'original';
    const TYPE_DISCOUNTED_SALEPRICE = 'discounted';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%addons_discounts}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscountUserGroups(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountUserGroup::class, ['discountId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscountPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountPurchasable::class, ['discountId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscountCategories(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountCategory::class, ['discountId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGroups(): ActiveQueryInterface
    {
        return $this->hasMany(UserGroup::class, ['id' => 'discountId'])->via('discountUserGroups');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(Purchasable::class, ['id' => 'discountId'])->via('discountPurchasables');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCategories(): ActiveQueryInterface
    {
        return $this->hasMany(Category::class, ['id' => 'discountId'])->via('discountCategories');
    }
}
