<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons\models;

use kuriousagency\commerce\addons\Addons;

use Craft;
use craft\base\Model;
// use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Discount model
 *
 * @property string|false $cpEditUrl
 * @property-read string $percentDiscountAsPercent
 * @property array $categoryIds
 * @property array $purchasableIds
 * @property array $userGroupIds
 * @author Kurious Agency
 * @since 1.0.0
 */
class Discount extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name of the discount
     */
    public $name;

    /**
     * @var string The description of this discount
     */
    public $description;

    /**
     * @var DateTime|null Date the discount is valid from
     */
    public $dateFrom;

    /**
     * @var DateTime|null Date the discount is valid to
     */
    public $dateTo;

    /**
     * @var float Total minimum spend on matching items
     */
    public $purchaseTotal = 0;

    /**
     * @var int Total minimum qty of matching items
     */
    public $purchaseQty = 0;

    /**
     * @var int Total maximum spend on matching items
     */
    public $maxPurchaseQty = 0;

    /**
     * @var float Amount of discount per item
     */
    public $perItemDiscount;

    /**
     * @var float Percentage of amount discount per item
     */
    public $percentDiscount;

    /**
     * @var string Whether the discount is off the original price, or the already discount price.
     */
    public $percentageOffSubject;

    /**
     * @var bool Exclude on sale purchasables
     */
    public $excludeOnSale;

    /**
     * @var bool Match all user groups.
     */
    public $allGroups;

    /**
     * @var bool Match all products
     */
    public $allPurchasables;

    /**
     * @var bool Match all product types
     */
    public $allCategories;

    /**
     * @var bool Discount enabled?
     */
    public $enabled = true;

    /**
     * @var int sortOrder
     */
    public $sortOrder;

    /**
     * @var DateTime|null
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     */
    public $dateUpdated;

    /**
     * @var int[] Product Ids
     */
    private $_purchasableIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_categoryIds;

    /**
     * @var int[] Group IDs
     */
	private $_userGroupIds;
	
	 /**
     * @var int[] Product Ids
     */
    private $_productPurchasableIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_productCategoryIds;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateFrom';
        $attributes[] = 'dateTo';

        return $attributes;
    }

    /**
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce-addons/discounts/' . $this->id);
    }

    /**
     * @return array
     */
    public function getCategoryIds(): array
    {
        if (null === $this->_categoryIds) {
            $this->_loadRelations();
        }

        return $this->_categoryIds;
    }

    /**
     * @return array
     */
    public function getPurchasableIds(): array
    {
        if (null === $this->_purchasableIds) {
            $this->_loadRelations();
        }

        return $this->_purchasableIds;
    }

    /**
     * @return array
     */
    public function getUserGroupIds(): array
    {
        if (null === $this->_userGroupIds) {
            $this->_loadRelations();
        }

        return $this->_userGroupIds;
	}

	 /**
     * @return array
     */
    public function getProductCategoryIds(): array
    {
        if (null === $this->_productCategoryIds) {
            $this->_loadRelations();
        }

        return $this->_productCategoryIds;
    }

    /**
     * @return array
     */
    public function getProductPurchasableIds(): array
    {		
		if (null === $this->_productPurchasableIds) {
            $this->_loadRelations();
        }

        return $this->_productPurchasableIds;
    }

    /**
     * Sets the related condition product type ids
     *
     * @param array $categoryIds
     */
    public function setCategoryIds(array $categoryIds)
    {
        $this->_categoryIds = array_unique($categoryIds);
    }

    /**
     * Sets the related condition product ids
     *
     * @param array $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds)
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * Sets the related user group ids
     *
     * @param array $userGroupIds
     */
    public function setUserGroupIds(array $userGroupIds)
    {
        $this->_userGroupIds = array_unique($userGroupIds);
	}
	
	/**
     * Sets the related product type ids
     *
     * @param array $categoryIds
     */
    public function setProductCategoryIds(array $categoryIds)
    {
        $this->_productCategoryIds = array_unique($categoryIds);
    }

    /**
     * Sets the related product ids
     *
     * @param array $purchasableIds
     */
    public function setProductPurchasableIds(array $purchasableIds)
    {		
		$this->_productPurchasableIds = array_unique($purchasableIds);
    }

    /**
     * @return string
     */
    public function getPercentDiscountAsPercent(): string
    {
        if ($this->percentDiscount !== 0) {
            $string = (string)$this->percentDiscount;
            $number = rtrim($string, '0');
            $diff = strlen($string) - strlen($number);
            return Craft::$app->formatter->asPercent(-$this->percentDiscount, 2 - $diff);
        }

        return Craft::$app->formatter->asPercent(0);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {		
		return [
            [['name'], 'required'],
            [
                [
                    'purchaseTotal',
                    'purchaseQty',
                    'maxPurchaseQty',
                    'perItemDiscount',
                    'percentDiscount'
                ], 'number', 'skipOnEmpty' => false
			],
			[
                ['purchasableIds'], 'required', 'when' => function($model) {
                	return !$model->categoryIds;
            	}
            ],
            [
                ['categoryIds'], 'required', 'when' => function($model) {
                	return !$model->purchasableIds;
            	}
			],
			[
                ['productPurchasableIds'], 'required', 'when' => function($model) {
                	return !$model->productCategoryIds;
            	}
            ],
            [
                ['productCategoryIds'], 'required', 'when' => function($model) {
                	return !$model->productPurchasableIds;
            	}
            ],
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Loads the sale relations
     */
    private function _loadRelations()
    {
        Addons::$plugin->service->populateDiscountRelations($this);
    }
}
