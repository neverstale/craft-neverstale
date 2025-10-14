<?php

namespace neverstale\neverstale\migrations;

use craft\db\Migration;
use craft\helpers\Db;
use neverstale\neverstale\enums\AnalysisStatus;
use yii\base\Exception;

/**
 * Neverstale Install Migration
 *
 * This migration creates the necessary database tables for the Neverstale plugin:
 * - neverstale_content: Tracks content analysis status and metadata
 * - neverstale_transactions: Logs transaction history for debugging and auditing
 * - neverstale_flags: Stores individual flag details for content
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   1.0.0
 * @see     https://github.com/zaengle/craft-neverstale
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     * @throws Exception
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * Creates the plugin tables
     *
     * @return void
     * @throws Exception
     */
    public function createTables(): void
    {
        // Archive the existing table if it exists (for upgrades)
        $this->archiveTableIfExists('{{%neverstale_content}}');

        // Create neverstale_content table
        $this->createTable('{{%neverstale_content}}', [
            'id' => $this->primaryKey(),
            'uid' => $this->uid(),
            'entryId' => $this->integer()->notNull()->comment('Foreign key to entries table'),
            'siteId' => $this->integer()->notNull()->comment('Foreign key to sites table'),
            'neverstaleId' => $this->string(255)->null()->comment('Neverstale API identifier'),
            'analysisStatus' => $this->string(50)->notNull()->defaultValue(AnalysisStatus::UNSENT->value)->comment('Current analysis status'),
            'flagCount' => $this->integer()->null()->comment('Number of flags detected (null until analyzed)'),
            'dateAnalyzed' => $this->dateTime()->null()->comment('Last analysis completion date'),
            'dateExpired' => $this->dateTime()->null()->comment('Content expiration date'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);

        // Create neverstale_transactions table
        $this->createTable('{{%neverstale_transactions}}', [
            'id' => $this->primaryKey(),
            'contentId' => $this->integer()->notNull()->comment('Foreign key to neverstale_content table'),
            'status' => $this->string(100)->null()->comment('Transaction status'),
            'message' => $this->text()->null()->comment('Transaction message or error details'),
            'event' => $this->text()->null()->comment('Event type or description'),
            'debugTransaction' => $this->json()->null()->comment('Debug data for development mode'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);

        // Create neverstale_flags table
        $this->createTable('{{%neverstale_flags}}', [
            'id' => $this->primaryKey(),
            'contentId' => $this->integer()->notNull()->comment('Reference to neverstale_content.id'),
            'flagId' => $this->string(255)->notNull()->comment('Neverstale API flag ID'),
            'flag' => $this->string(255)->notNull()->comment('Flag type/category'),
            'reason' => $this->text()->null()->comment('Reason for the flag'),
            'snippet' => $this->text()->null()->comment('Content snippet that triggered the flag'),
            'lastAnalyzedAt' => $this->dateTime()->null()->comment('When this flag was last analyzed'),
            'expiredAt' => $this->dateTime()->null()->comment('When this flag expires'),
            'ignoredAt' => $this->dateTime()->null()->comment('When this flag was ignored (null if active)'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Creates database indexes for optimal performance
     *
     * @return void
     */
    public function createIndexes(): void
    {
        // neverstale_content indexes
        $this->createIndex(null, '{{%neverstale_content}}', 'id');
        $this->createIndex(null, '{{%neverstale_content}}', 'entryId');
        $this->createIndex(null, '{{%neverstale_content}}', 'siteId');
        $this->createIndex(null, '{{%neverstale_content}}', 'neverstaleId');
        $this->createIndex(null, '{{%neverstale_content}}', 'uid');
        $this->createIndex(null, '{{%neverstale_content}}', 'analysisStatus');
        $this->createIndex(null, '{{%neverstale_content}}', 'flagCount');
        $this->createIndex(null, '{{%neverstale_content}}', 'dateAnalyzed');
        $this->createIndex(null, '{{%neverstale_content}}', 'dateExpired');

        // Composite indexes for common queries
        $this->createIndex(null, '{{%neverstale_content}}', ['entryId', 'siteId']);
        $this->createIndex(null, '{{%neverstale_content}}', ['analysisStatus', 'dateAnalyzed']);

        // neverstale_transactions indexes
        $this->createIndex(null, '{{%neverstale_transactions}}', 'contentId');
        $this->createIndex(null, '{{%neverstale_transactions}}', 'status');
        $this->createIndex(null, '{{%neverstale_transactions}}', 'dateCreated');

        // neverstale_flags indexes
        $this->createIndex(null, '{{%neverstale_flags}}', 'contentId');
        $this->createIndex(null, '{{%neverstale_flags}}', 'flagId', true); // Unique flag ID
        $this->createIndex(null, '{{%neverstale_flags}}', 'flag');
        $this->createIndex(null, '{{%neverstale_flags}}', 'ignoredAt');
        $this->createIndex(null, '{{%neverstale_flags}}', 'expiredAt');
    }

    /**
     * Adds foreign key constraints
     *
     * @return void
     */
    public function addForeignKeys(): void
    {
        // neverstale_content foreign keys
        $this->addForeignKey(
            null,
            '{{%neverstale_content}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            '{{%neverstale_content}}',
            'entryId',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            '{{%neverstale_content}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            null
        );

        // neverstale_transactions foreign keys
        $this->addForeignKey(
            null,
            '{{%neverstale_transactions}}',
            'contentId',
            '{{%neverstale_content}}',
            'id',
            'CASCADE',
            null
        );

        // neverstale_flags foreign keys
        $this->addForeignKey(
            null,
            '{{%neverstale_flags}}',
            'contentId',
            '{{%neverstale_content}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%neverstale_flags}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE'
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop foreign keys first
        if ($this->db->tableExists('{{%neverstale_flags}}')) {
            Db::dropAllForeignKeysToTable('{{%neverstale_flags}}', $this->db);
        }

        if ($this->db->tableExists('{{%neverstale_transactions}}')) {
            Db::dropAllForeignKeysToTable('{{%neverstale_transactions}}', $this->db);
        }

        if ($this->db->tableExists('{{%neverstale_content}}')) {
            Db::dropAllForeignKeysToTable('{{%neverstale_content}}', $this->db);
        }

        // Drop tables
        $this->dropTableIfExists('{{%neverstale_flags}}');
        $this->dropTableIfExists('{{%neverstale_transactions}}');
        $this->dropTableIfExists('{{%neverstale_content}}');

        return true;
    }
}
