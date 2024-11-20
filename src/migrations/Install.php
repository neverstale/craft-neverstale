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
            'neverstaleId' => $this->string(),
            'uid' => $this->uid(),
            'analysisStatus' => $this->string()->defaultValue(AnalysisStatus::Unsent->value),
            'flagCount' => $this->integer()->defaultValue(0),
            'flagTypes' => $this->json(),
            'nextFlagDate' => $this->dateTime()->defaultValue(null),
            'jobIds' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%neverstale_transactions}}', [
            'id' => $this->primaryKey(),
            'submissionId' => $this->integer(),
            'status' => $this->string(),
            'message' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
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

        $this->addForeignKey(
            null,
            '{{%neverstale_transactions}}',
            'submissionId',
            '{{%neverstale_submissions}}',
            'id',
            'CASCADE',
            null);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%neverstale_submissions}}', 'id');
        $this->createIndex(null, '{{%neverstale_submissions}}', 'entryId');
        $this->createIndex(null, '{{%neverstale_submissions}}', 'siteId');
        $this->createIndex(null, '{{%neverstale_submissions}}', 'neverstaleId');
        $this->createIndex(null, '{{%neverstale_submissions}}', 'uid');
        $this->createIndex(null, '{{%neverstale_transactions}}', 'submissionId');
    }
}
