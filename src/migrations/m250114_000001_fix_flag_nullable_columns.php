<?php

namespace neverstale\neverstale\migrations;

use craft\db\Migration;

/**
 * Migration to make lastAnalyzedAt and expiredAt nullable in neverstale_flags table
 *
 * These columns should be nullable since flags may not have expiration dates
 * or may not have been analyzed yet.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.1.0
 */
class m250114_000001_fix_flag_nullable_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Make lastAnalyzedAt nullable
        $this->alterColumn(
            '{{%neverstale_flags}}',
            'lastAnalyzedAt',
            $this->dateTime()->null()->comment('When this flag was last analyzed')
        );

        // Make expiredAt nullable
        $this->alterColumn(
            '{{%neverstale_flags}}',
            'expiredAt',
            $this->dateTime()->null()->comment('When this flag expires')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250114_000001_fix_flag_nullable_columns cannot be safely reverted.\n";
        return false;
    }
}
