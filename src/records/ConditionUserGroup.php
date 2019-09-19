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
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Discount user record.
 *
 * @property ActiveQueryInterface $discount
 * @property int $discountId
 * @property int $id
 * @property ActiveQueryInterface $productType
 * @property int $userGroupId
 * @author Kurious Agency
 * @since 1.0.0
 */
class ConditionUserGroup extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%addons_condition_usergroups}}';
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
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(UserGroup::class, ['id' => 'userGroupId']);
    }
}
