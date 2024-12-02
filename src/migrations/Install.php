<?php

namespace zaengle\neverstale\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use zaengle\neverstale\enums\AnalysisStatus;

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
        if ($this->db->tableExists('{{%neverstale_transactions}}')) {
            Db::dropAllForeignKeysToTable('{{%neverstale_transactions}}');
        }

        if ($this->db->tableExists('{{%neverstale_content}}')) {
            Db::dropAllForeignKeysToTable('{{%neverstale_content}}');
        }
        $this->dropTableIfExists('{{%neverstale_transactions}}');
        $this->dropTableIfExists('{{%neverstale_content}}');

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%neverstale_content}}');
        $this->createTable('{{%neverstale_content}}', [
            'id' => $this->primaryKey(),
            'uid' => $this->uid(),
            'entryId' => $this->integer(),
            'entryUid' => $this->uid(),
            'siteId' => $this->integer(),
            'neverstaleId' => $this->string(),
            'analysisStatus' => $this->string()->defaultValue(AnalysisStatus::UNSENT->value),
            'flagCount' => $this->integer()->defaultValue(0),
            'dateAnalyzed' => $this->dateTime()->defaultValue(null),
            'dateExpired' => $this->dateTime()->defaultValue(null),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%neverstale_transactions}}', [
            'id' => $this->primaryKey(),
            'contentId' => $this->integer(),
            'status' => $this->string(),
            'message' => $this->text(),
            'event' => $this->text(),
            'debugTransaction' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(
            null,
            '{{%neverstale_content}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
        );
        $this->addForeignKey(
            null,
            '{{%neverstale_content}}',
            'entryId',
            '{{%elements}}',
            'id',
            'CASCADE',
        );
        $this->addForeignKey(
            null,
            '{{%neverstale_content}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
        );

        $this->addForeignKey(
            null,
            '{{%neverstale_transactions}}',
            'contentId',
            '{{%neverstale_content}}',
            'id',
            'CASCADE',
        );
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%neverstale_content}}', 'id');
        $this->createIndex(null, '{{%neverstale_content}}', 'entryId');
        $this->createIndex(null, '{{%neverstale_content}}', 'entryUid');
        $this->createIndex(null, '{{%neverstale_content}}', 'siteId');
        $this->createIndex(null, '{{%neverstale_content}}', 'neverstaleId');
        $this->createIndex(null, '{{%neverstale_content}}', 'uid');
        $this->createIndex(null, '{{%neverstale_transactions}}', 'contentId');
    }
}
