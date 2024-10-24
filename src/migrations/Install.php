<?php

namespace zaengle\neverstale\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;

/**
 * Neverstale Install Migration
 *
 * @author Zaengle
 * @package zaengle/craft-neverstale
 * @since 1.0.0
 * @see https://github.com/zaengle/craft-neverstale
 */
class Install extends Migration
{
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
        if ($this->db->tableExists('{{%neverstale_submissions}}')) {
            Db::dropAllForeignKeysToTable('{{%neverstale_submissions}}');
        }
        $this->dropTableIfExists('{{%neverstale_submissions}}');

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%neverstale_submissions}}');
        $this->createTable('{{%neverstale_submissions}}', [
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
        $this->addForeignKey(
            null,
            '{{%neverstale_submissions}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
        null);
        $this->addForeignKey(
            null,
            '{{%neverstale_submissions}}',
            'entryId',
            '{{%elements}}',
            'id',
            'CASCADE',
        null);
        $this->addForeignKey(
            null,
            '{{%neverstale_submissions}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
        null);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%neverstale_submissions}}', 'uid', false);
    }
}
