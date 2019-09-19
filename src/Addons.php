<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * Commerce Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons;

use kuriousagency\commerce\addons\services\AddonsService;
use kuriousagency\commerce\addons\variables\AddonsVariable;
use kuriousagency\commerce\addons\adjusters\Discount;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\services\OrderAdjustments;

use yii\base\Event;

/**
 * Class Addons
 *
 * @author    Kurious Agency
 * @package   Addons
 * @since     1.0.0
 *
 * @property  AddonsService $addonsService
 */
class Addons extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Addons
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
		self::$plugin = $this;
		
		$this->setComponents([
            'service' => AddonsService::class
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'addons/default';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['commerce-addons'] = 'commerce-addons/default/index';
                $event->rules['commerce-addons/new'] = 'commerce-addons/default/edit';
                $event->rules['commerce-addons/<id:\d+>'] = 'commerce-addons/default/edit';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('addons', AddonsVariable::class);
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
		);
		
		Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $event) {
			$event->types[] = Discount::class;
		});

        Craft::info(
            Craft::t(
                'commerce-addons',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
