<?php

namespace neverstale\neverstale\migrations;

use craft\db\Migration;

/**
 * Migration to convert "stale" status to "analyzed-flagged"
 *
 * This migration updates existing content records that have the deprecated
 * "stale" analysis status to use "analyzed-flagged" instead.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.1.0
 */
class m250114_000000_convert_stale_to_flagged extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Update all content records with 'stale' status to 'analyzed-flagged'
        $this->update(
            '{{%neverstale_content}}',
            ['analysisStatus' => 'analyzed-flagged'],
            ['analysisStatus' => 'stale']
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250114_000000_convert_stale_to_flagged cannot be reverted.\n";
        return false;
    }
}
