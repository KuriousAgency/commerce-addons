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
use craft\elements\Category;
use yii\db\ActiveQueryInterface;

/**
 * Discount Product type record.
 *
 * @property ActiveQueryInterface $category
 * @property int $categoryId
 * @property ActiveQueryInterface $discount
 * @property int $discountId
 * @property int $id
 * @author Kurious Agency
 * @since 1.0.0
 */
class ConditionCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%addons_condition_categories}}';
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
    public function getCategory(): ActiveQueryInterface
    {
        return $this->hasOne(Category::class, ['id' => 'categoryId']);
    }
}
