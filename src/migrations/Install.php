<?php
/**
 * Addons plugin for Craft CMS 3.x
 *
 * addons Addons plugin for Craft CMS
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\addons\migrations;

use kuriousagency\commerce\addons\Addons;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * @author    Kurious Agency
 * @package   Addons
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            // $this->insertDefaultData();
        }

        return true;
    }

   /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%addons_discounts}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
		   
			$this->createTable('{{%addons_condition_purchasables}}', [
				'id' => $this->primaryKey(),
				'discountId' => $this->integer()->notNull(),
				'purchasableId' => $this->integer()->notNull(),
				'purchasableType' => $this->string()->notNull(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
	
			$this->createTable('{{%addons_condition_categories}}', [
				'id' => $this->primaryKey(),
				'discountId' => $this->integer()->notNull(),
				'categoryId' => $this->integer()->notNull(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
	
			$this->createTable('{{%addons_condition_usergroups}}', [
				'id' => $this->primaryKey(),
				'discountId' => $this->integer()->notNull(),
				'userGroupId' => $this->integer()->notNull(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);

			$this->createTable('{{%addons_product_purchasables}}', [
				'id' => $this->primaryKey(),
				'discountId' => $this->integer()->notNull(),
				'purchasableId' => $this->integer()->notNull(),
				'purchasableType' => $this->string()->notNull(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
	
			$this->createTable('{{%addons_product_categories}}', [
				'id' => $this->primaryKey(),
				'discountId' => $this->integer()->notNull(),
				'categoryId' => $this->integer()->notNull(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
	
			$this->createTable('{{%addons_discounts}}', [
				'id' => $this->primaryKey(),
				'name' => $this->string()->notNull(),
				'description' => $this->text(),
				'perUserLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
				'perEmailLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
				'totalUseLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),			
				'dateFrom' => $this->dateTime(),
				'dateTo' => $this->dateTime(),
				'purchaseTotal' => $this->integer()->notNull()->defaultValue(0),
				'purchaseQty' => $this->integer()->notNull()->defaultValue(0),
				'maxPurchaseQty' => $this->integer()->notNull()->defaultValue(0),
				'perItemDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
				'percentDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
				'percentageOffSubject' => $this->enum('percentageOffSubject', ['original', 'discounted'])->notNull(),
				'excludeOnSale' => $this->boolean(),
				'allGroups' => $this->boolean(),
				'allPurchasables' => $this->boolean(),
				'allCategories' => $this->boolean(),
				'enabled' => $this->boolean(),
				'sortOrder' => $this->integer(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
			
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
		$this->createIndex(null, '{{%addons_condition_purchasables}}', ['discountId', 'purchasableId'], true);
        $this->createIndex(null, '{{%addons_condition_purchasables}}', 'purchasableId', false);
        $this->createIndex(null, '{{%addons_condition_categories}}', ['discountId', 'categoryId'], true);
        $this->createIndex(null, '{{%addons_condition_categories}}', 'categoryId', false);
        $this->createIndex(null, '{{%addons_condition_usergroups}}', ['discountId', 'userGroupId'], true);
        $this->createIndex(null, '{{%addons_condition_usergroups}}', 'userGroupId', false);
        $this->createIndex(null, '{{%addons_discounts}}', 'dateFrom', false);
		$this->createIndex(null, '{{%addons_discounts}}', 'dateTo', false);
		$this->createIndex(null, '{{%addons_product_purchasables}}', ['discountId', 'purchasableId'], true);
        $this->createIndex(null, '{{%addons_product_purchasables}}', 'purchasableId', false);
        $this->createIndex(null, '{{%addons_product_categories}}', ['discountId', 'categoryId'], true);
		$this->createIndex(null, '{{%addons_product_categories}}', 'categoryId', false);		
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
		$this->addForeignKey(null, '{{%addons_condition_purchasables}}', ['discountId'], '{{%addons_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_condition_purchasables}}', ['purchasableId'], '{{%commerce_purchasables}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_condition_categories}}', ['discountId'], '{{%addons_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_condition_categories}}', ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_condition_usergroups}}', ['discountId'], '{{%addons_discounts}}', ['id'], 'CASCADE', 'CASCADE');
		$this->addForeignKey(null, '{{%addons_condition_usergroups}}', ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');		
		$this->addForeignKey(null, '{{%addons_product_purchasables}}', ['discountId'], '{{%addons_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_product_purchasables}}', ['purchasableId'], '{{%commerce_purchasables}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_product_categories}}', ['discountId'], '{{%addons_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%addons_product_categories}}', ['categoryId'], '{{%categories}}', ['id'], 'CASCADE', 'CASCADE');
    }


    /**
     * @return void
     */
    protected function removeTables()
    {
		$this->dropForeignKeys();
        $this->dropTables();
	}
	
	public function dropForeignKeys()
    {
		if ($this->_tableExists('{{%addons_condition_purchasables}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%addons_condition_purchasables}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%addons_condition_purchasables}}', $this);
        }
        if ($this->_tableExists('{{%addons_condition_categories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%addons_condition_categories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%addons_condition_categories}}', $this);
        }
        if ($this->_tableExists('{{%addons_condition_usergroups}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%addons_condition_usergroups}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%addons_condition_usergroups}}', $this);
		}
		if ($this->_tableExists('{{%addons_product_purchasables}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%addons_product_purchasables}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%addons_product_purchasables}}', $this);
        }
        if ($this->_tableExists('{{%addons_product_categories}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%addons_product_categories}}', $this);
            MigrationHelper::dropAllForeignKeysOnTable('{{%addons_product_categories}}', $this);
        }

	}

	public function dropTables()
    {
		$this->dropTableIfExists('{{%addons_discounts}}');
		$this->dropTableIfExists('{{%addons_condition_purchasables}}');
        $this->dropTableIfExists('{{%addons_condition_categories}}');
		$this->dropTableIfExists('{{%addons_condition_usergroups}}');
		$this->dropTableIfExists('{{%addons_product_purchasables}}');
        $this->dropTableIfExists('{{%addons_product_categories}}');
	}

	/**
     * Returns if the table exists.
     *
     * @param string $tableName
     * @param Migration|null $migration
     * @return bool If the table exists.
     */
    private function _tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }

}
