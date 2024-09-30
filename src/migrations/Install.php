<?php

namespace zaengle\neverstale\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;

/**
 * Install migration.
 */
class Install extends Migration
{
    public static string $submissionTable = '{{%neverstale_submissions}}';
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->tableExists(self::$submissionTable)) {
            Db::dropAllForeignKeysToTable(self::$submissionTable);
        }
        $this->dropTableIfExists(self::$submissionTable);

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists(self::$submissionTable);
        $this->createTable(self::$submissionTable, [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer(),
            'siteId' => $this->integer(),
            'isSent' => $this->boolean()->defaultValue(false),
            'isProcessed' => $this->boolean()->defaultValue(false),
            'flagCount' => $this->integer()->defaultValue(0),
            'flagTypes' => $this->json()->defaultValue('[]'),
            'nextFlagDate' => $this->dateTime()->defaultValue(null),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, self::$submissionTable, 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, self::$submissionTable, 'entryId', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, self::$submissionTable, 'siteId', '{{%sites}}', 'id', 'CASCADE', null);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, self::$submissionTable, 'id', false);
        $this->createIndex(null, self::$submissionTable, 'entryId', false);
        $this->createIndex(null, self::$submissionTable, 'uid', false);
    }
}
