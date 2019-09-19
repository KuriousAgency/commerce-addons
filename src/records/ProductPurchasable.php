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
use yii\db\ActiveQueryInterface;

/**
 * Discount product record.
 *
 * @property ActiveQueryInterface $discount
 * @property int $discountId
 * @property int $id
 * @property ActiveQueryInterface $purchasable
 * @property int $purchasableId
 * @property int $purchasableType
 * @author Kurious Agency
 * @since 1.0.0
 */
class ProductPurchasable extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%addons_product_purchasables}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['id' => 'discountId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Purchasable::class, ['id' => 'purchasableId']);
    }
}
