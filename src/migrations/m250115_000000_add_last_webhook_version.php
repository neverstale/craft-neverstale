<?php

namespace neverstale\neverstale\migrations;

use Craft;
use craft\db\Migration;

/**
 * m250115_000000_add_last_webhook_version migration.
 */
class m250115_000000_add_last_webhook_version extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%neverstale_content}}',
            'lastWebhookVersion',
            $this->bigInteger()->unsigned()->defaultValue(0)->notNull()->after('dateExpired')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%neverstale_content}}', 'lastWebhookVersion');

        return true;
    }
}
