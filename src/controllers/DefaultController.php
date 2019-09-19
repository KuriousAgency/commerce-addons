<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons\controllers;

use kuriousagency\commerce\addons\Addons;
use kuriousagency\commerce\addons\models\Discount;


use Craft;
use craft\web\Controller;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\Plugin as Commerce;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\i18n\Locale;
use function explode;
use function get_class;
use yii\web\HttpException;
use yii\web\Response;


/**
 * @author    Kurious Agency
 * @package   Addons
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        // $this->requirePermission('commerce-managePromotions');
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $addons = Addons::$plugin->service->getAllDiscounts();
        return $this->renderTemplate('commerce-addons/index', compact('addons'));
    }

    /**
     * @param int|null $id
     * @param Discount|null $discount
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Discount $discount = null): Response
    {
        $variables = compact('id', 'discount');

        if (!$variables['discount']) {
            if ($variables['id']) {
                $variables['discount'] = Addons::$plugin->service->getDiscountById($variables['id']);

                if (!$variables['discount']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['discount'] = new Discount();
            }
        }

		$this->_populateVariables($variables);
		
		// Craft::dd($variables->getProductPurchasables);

        return $this->renderTemplate('commerce-addons/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $discount = new Discount();
        $request = Craft::$app->getRequest();

        $discount->id = $request->getBodyParam('id');
        $discount->name = $request->getBodyParam('name');
        $discount->description = $request->getBodyParam('description');
        $discount->enabled = (bool)$request->getBodyParam('enabled');
        $discount->purchaseTotal = $request->getBodyParam('purchaseTotal');
        $discount->purchaseQty = $request->getBodyParam('purchaseQty');
        $discount->maxPurchaseQty = $request->getBodyParam('maxPurchaseQty');
        $discount->perItemDiscount = $request->getBodyParam('perItemDiscount');
        $discount->percentDiscount = $request->getBodyParam('percentDiscount');
        $discount->percentageOffSubject = $request->getBodyParam('percentageOffSubject');
        $discount->excludeOnSale = (bool)$request->getBodyParam('excludeOnSale');
        $discount->perItemDiscount = (float)$request->getBodyParam('perItemDiscount') * -1;

        $date = $request->getBodyParam('dateFrom');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateFrom = $dateTime;
        }

        $date = $request->getBodyParam('dateTo');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateTo = $dateTime;
        }

        // Format into a %
        $percentDiscountAmount = $request->getBodyParam('percentDiscount');
        $localeData = Craft::$app->getLocale();
        $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);
        if (strpos($percentDiscountAmount, $percentSign) || (float)$percentDiscountAmount >= 1) {
            $discount->percentDiscount = (float)$percentDiscountAmount / -100;
        } else {
            $discount->percentDiscount = (float)$percentDiscountAmount * -1;
        }

        $purchasables = [];
        $purchasableGroups = $request->getBodyParam('purchasables') ?: [];
        foreach ($purchasableGroups as $group) {
            if (is_array($group)) {
                array_push($purchasables, ...$group);
            }
        }
        $purchasables = array_unique($purchasables);
        $discount->setPurchasableIds($purchasables);

        $categories = $request->getBodyParam('categories', []);
        if (!$categories) {
            $categories = [];
        }
        $discount->setCategoryIds($categories);

        $groups = $request->getBodyParam('groups', []);
        if (!$groups) {
            $groups = [];
        }
		$discount->setUserGroupIds($groups);
		
		// product purchasables
		$productPurchasables = [];
        $productPurchasableGroups = $request->getBodyParam('productPurchasables') ?: [];
        foreach ($productPurchasableGroups as $group) {
            if (is_array($group)) {
                array_push($productPurchasables, ...$group);
            }
        }
        $productPurchasables = array_unique($productPurchasables);
		$discount->setProductPurchasableIds($productPurchasables);
		
		// product categories
		$productCategories = $request->getBodyParam('productCategories', []);
        if (!$productCategories) {
            $productCategories = [];
		}
		
		$discount->setProductCategoryIds($productCategories);

        // Save it
        if (Addons::$plugin->service->saveDiscount($discount)
        ) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-addons', 'Addon saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce-addons', 'Couldn’t save addon.'));
        }

        // Send the model back to the template
        $variables = [
            'discount' => $discount
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
    }

    /**
     *
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Addons::$plugin->service->reorderDiscounts($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce-addons', 'Couldn’t reorder discounts.')]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Addons::$plugin->service->deleteDiscountById($id);

        return $this->asJson(['success' => true]);
    }

    // /**
    //  * @throws HttpException
    //  */
    // public function actionClearCouponUsageHistory()
    // {
    //     $this->requirePostRequest();
    //     $this->requireAcceptsJson();

    //     $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

    //     Addons::$plugin->service->clearCouponUsageHistoryById($id);

    //     return $this->asJson(['success' => true]);
    // }

    // Private Methods
    // =========================================================================

    /**
     * @param array $variables
     */
    private function _populateVariables(&$variables)
    {

		if ($variables['discount']->id) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Craft::t('commerce-addons', 'Create a Discount');
        }

        //getting user groups map      
        $groups = Craft::$app->getUserGroups()->getAllGroups();
        $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
       
        $variables['categoryElementType'] = Category::class;
        $variables['categories'] = null;
        $categories = $categoryIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('categoryIds')) {
            $categoryIds = explode('|', Craft::$app->getRequest()->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['discount']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $categories[] = Craft::$app->getElements()->getElementById($id);
        }

		$variables['categories'] = $categories;
		
        $variables['purchasables'] = null;

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {

            $purchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId;
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
        } else {
            $purchasableIds = $variables['discount']->getPurchasableIds();
        }

        $purchasables = [];
        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = get_class($purchasable);
                $purchasables[$class] = $purchasables[$class] ?? [];
                $purchasables[$class][] = $purchasable;
            }
        }
		$variables['purchasables'] = $purchasables;


		// product categories
		$variables['productCategories'] = null;
        $productCategories = $productCategoryIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('productCategoryIds')) {
            $productCategoryIds = explode('|', Craft::$app->getRequest()->getParam('productCategoryIds'));
        } else {
            $productCategoryIds = $variables['discount']->getProductCategoryIds();
        }

        foreach ($productCategoryIds as $categoryId) {
            $id = (int)$categoryId;
            $productCategories[] = Craft::$app->getElements()->getElementById($id);
        }

		$variables['productCategories'] = $productCategories;

		// product purchasables
		$variables['productPurchasables'] = null;

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('productPurchasableIds')) {
            $productPurchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('productPurchasableIds'));
            $productPurchasableIds = [];
            foreach ($productPurchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    $productPurchasableIds[] = $purchasable->defaultVariantId;
                } else {
                    $productPurchasableIds[] = $purchasableId;
                }
            }
        } else {
            $productPurchasableIds = $variables['discount']->getProductPurchasableIds();
		}

		// Craft::dump($productPurchasableIds);

        $productPurchasables = [];
        foreach ($productPurchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = get_class($purchasable);
                $productPurchasables[$class] = $productPurchasables[$class] ?? [];
                $productPurchasables[$class][] = $purchasable;
            }
        }
		$variables['productPurchasables'] = $productPurchasables;

		// purchasableTypes
        $variables['purchasableTypes'] = [];
        $purchasableTypes = Commerce::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
            $variables['purchasableTypes'][] = [
                'name' => $purchasableType::displayName(),
                'elementType' => $purchasableType
            ];
		}
	}
	

	public function actionTest() {

		$purchasable = Commerce::getInstance()->getPurchasables()->getPurchasableById(2543);
		Addons::$plugin->service->getAddonsByPurchasableId($purchasable);

		exit();

	}

}
