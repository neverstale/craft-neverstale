<?php

namespace neverstale\craft\services;

use yii\base\Component;
use neverstale\craft\Plugin;

/**
 * Template service
 */
class Template extends Component
{
    public function getTranslationsForFlagsWidget(): array
    {
        return [
            'DATE' => Plugin::t('Date'),
            'CONTENT_STATUS' => Plugin::t('Content Status'),
            'NO_FLAGS_FOUND' => Plugin::t('No flags found.'),
            'IGNORE' => Plugin::t('Ignore'),
            'RESCHEDULE' => Plugin::t('Reschedule'),
            'LAST_ANALYZED' => Plugin::t('Last Analyzed'),
            'CONTENT_EXPIRED' => Plugin::t('Content Expired'),
            'RELOAD_PAGE' => Plugin::t('Reload Page'),
            'VIEW_IN_NEVERSTALE' => Plugin::t('View in Neverstale'),
            'VIEW_LOCAL_DETAILS' => Plugin::t('Show logs'),
            'EXPIRED_AT' => Plugin::t('Expired at'),
            'REASON' => Plugin::t('Reason'),
            'SNIPPET' => Plugin::t('Snippet'),
            'CONTENT' => Plugin::t('Content'),
            'FLAG' => Plugin::t('Flag'),
            'FLAGS' => Plugin::t('Flags'),
            'IS_STALE_NOTICE' => Plugin::t("This content is currently pending processing by Neverstale, and as such, some values may be out of date."),
        ];
    }
}
