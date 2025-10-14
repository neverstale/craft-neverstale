<?php

namespace neverstale\neverstale\services;

use craft\helpers\App;
use craft\helpers\Db;
use DateTime;
use neverstale\neverstale\elements\Content;
use neverstale\neverstale\models\TransactionLogItem;
use neverstale\neverstale\Plugin;
use neverstale\neverstale\records\TransactionLog as TransactionLogRecord;
use yii\base\Component;
use yii\db\Exception;

/**
 * Neverstale Transaction Log Service
 *
 * Handles logging and management of API transaction history.
 * Provides detailed audit trails for all Neverstale API interactions
 * including ingestion, webhooks, and error tracking.
 *
 * @author  Zaengle
 * @package neverstale/neverstale
 * @since   2.0.0
 * @see     https://github.com/neverstale/craft-neverstale
 */
class TransactionLog extends Component
{
    /**
     * Log a transaction to the database
     *
     * Creates a persistent record of API transactions for audit,
     * debugging, and status tracking purposes.
     *
     * @param  Content             $content  Content element the transaction relates to
     * @param  TransactionLogItem  $logItem  Transaction details to log
     * @return bool Success status
     * @throws Exception
     */
    public function logTo(Content $content, TransactionLogItem $logItem): bool
    {
        try {
            $record = new TransactionLogRecord();
            $record->contentId = $content->id;
            $record->status = $logItem->getAnalysisStatus()->value;
            $record->message = $logItem->message;
            $record->event = $logItem->event;

            // Store debug information in development mode or when explicitly enabled
            if (App::devMode() || Plugin::getInstance()->getSettings()->debugLogging) {
                $record->debugTransaction = $logItem->content;
            }

            $saved = $record->save();

            if (! $saved) {
                Plugin::error("Failed to save transaction log: ".print_r($record->getErrors(), true));
            }

            return $saved;
        } catch (\Exception $e) {
            Plugin::error("Exception saving transaction log: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Delete all transaction logs for a content item
     *
     * Removes all transaction history when content is deleted
     * or when explicitly requested for cleanup.
     *
     * @param  Content  $content  Content element
     * @return bool Success status
     */
    public function deleteFor(Content $content): bool
    {
        try {
            TransactionLogRecord::deleteAll(['contentId' => $content->id]);
            Plugin::info("Deleted transaction logs for content #{$content->id}");

            return true;
        } catch (\Exception $e) {
            Plugin::error("Failed to delete transaction logs for content #{$content->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get transaction logs for a content item
     *
     * Retrieves transaction history for display in admin interfaces
     * or for debugging purposes.
     *
     * @param  Content  $content  Content element
     * @param  int      $limit    Maximum number of logs to return
     * @param  string   $order    Order direction ('ASC' or 'DESC')
     * @return TransactionLogRecord[] Array of transaction log records
     */
    public function getLogsFor(Content $content, int $limit = 50, string $order = 'DESC'): array
    {
        return TransactionLogRecord::find()
            ->where(['contentId' => $content->id])
            ->orderBy(['dateCreated' => SORT_DESC === strtoupper($order) ? SORT_DESC : SORT_ASC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get recent transaction logs across all content
     *
     * Provides a system-wide view of recent API activity
     * for monitoring and troubleshooting.
     *
     * @param  int    $limit    Maximum number of logs to return
     * @param  array  $filters  Additional filtering criteria
     * @return TransactionLogRecord[] Array of transaction log records
     */
    public function getRecentLogs(int $limit = 100, array $filters = []): array
    {
        $query = TransactionLogRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit);

        // Apply filters
        if (isset($filters['status'])) {
            $query->andWhere(['status' => $filters['status']]);
        }

        if (isset($filters['event'])) {
            $query->andWhere(['event' => $filters['event']]);
        }

        if (isset($filters['dateRange'])) {
            $query->andWhere(['>=', 'dateCreated', $filters['dateRange']['start']])
                ->andWhere(['<=', 'dateCreated', $filters['dateRange']['end']]);
        }

        return $query->all();
    }

    /**
     * Get transaction statistics
     *
     * Provides statistical overview of API transaction activity
     * for dashboard displays and monitoring.
     *
     * @param  int  $days  Number of days to include in statistics
     * @return array Statistics array
     */
    public function getStatistics(int $days = 30): array
    {
        $startDate = new DateTime("-{$days} days");

        $totalTransactions = TransactionLogRecord::find()
            ->where(['>=', 'dateCreated', Db::prepareDateForDb($startDate)])
            ->count();

        $successfulTransactions = TransactionLogRecord::find()
            ->where(['>=', 'dateCreated', Db::prepareDateForDb($startDate)])
            ->andWhere(['like', 'status', 'analyzed'])
            ->count();

        $errorTransactions = TransactionLogRecord::find()
            ->where(['>=', 'dateCreated', Db::prepareDateForDb($startDate)])
            ->andWhere(['or',
                ['like', 'status', 'error'],
                ['like', 'status', 'failed'],
            ])
            ->count();

        // Get event breakdown
        $eventCounts = TransactionLogRecord::find()
            ->select(['event', 'COUNT(*) as count'])
            ->where(['>=', 'dateCreated', Db::prepareDateForDb($startDate)])
            ->groupBy('event')
            ->asArray()
            ->all();

        return [
            'period' => $days,
            'total' => $totalTransactions,
            'successful' => $successfulTransactions,
            'errors' => $errorTransactions,
            'successRate' => $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 2) : 0,
            'eventCounts' => $eventCounts,
        ];
    }

    /**
     * Clean up old transaction logs
     *
     * Removes transaction logs older than specified retention period
     * to prevent database bloat while maintaining recent history.
     *
     * @param  int  $retentionDays  Number of days to retain logs
     * @return int Number of records deleted
     */
    public function cleanup(int $retentionDays = 90): int
    {
        $cutoffDate = new DateTime("-{$retentionDays} days");

        try {
            $deletedCount = TransactionLogRecord::deleteAll([
                '<', 'dateCreated', Db::prepareDateForDb($cutoffDate),
            ]);

            Plugin::info("Cleaned up {$deletedCount} transaction log records older than {$retentionDays} days");

            return $deletedCount;
        } catch (\Exception $e) {
            Plugin::error("Failed to clean up transaction logs: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Get error logs for troubleshooting
     *
     * Retrieves recent error transactions for debugging and
     * system health monitoring.
     *
     * @param  int  $limit  Maximum number of error logs to return
     * @param  int  $hours  Hours back to search for errors
     * @return TransactionLogRecord[] Array of error log records
     */
    public function getErrorLogs(int $limit = 50, int $hours = 24): array
    {
        $startDate = new DateTime("-{$hours} hours");

        return TransactionLogRecord::find()
            ->where(['>=', 'dateCreated', Db::prepareDateForDb($startDate)])
            ->andWhere(['or',
                ['like', 'status', 'error'],
                ['like', 'status', 'failed'],
                ['like', 'event', 'error'],
            ])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Export transaction logs
     *
     * Exports transaction log data for external analysis,
     * compliance, or backup purposes.
     *
     * @param  array   $criteria  Export criteria (date range, content IDs, etc.)
     * @param  string  $format    Export format ('json', 'csv')
     * @return array|string Exported data
     */
    public function export(array $criteria = [], string $format = 'json'): array|string
    {
        $query = TransactionLogRecord::find();

        // Apply criteria filters
        if (isset($criteria['contentIds'])) {
            $query->andWhere(['contentId' => $criteria['contentIds']]);
        }

        if (isset($criteria['dateRange'])) {
            $query->andWhere(['>=', 'dateCreated', $criteria['dateRange']['start']])
                ->andWhere(['<=', 'dateCreated', $criteria['dateRange']['end']]);
        }

        if (isset($criteria['events'])) {
            $query->andWhere(['event' => $criteria['events']]);
        }

        $records = $query->orderBy(['dateCreated' => SORT_ASC])->all();

        $data = array_map(function (TransactionLogRecord $record) {
            return [
                'id' => $record->id,
                'contentId' => $record->contentId,
                'status' => $record->status,
                'event' => $record->event,
                'message' => $record->message,
                'dateCreated' => $record->dateCreated->format('Y-m-d H:i:s'),
                'debugTransaction' => $record->debugTransaction,
            ];
        }, $records);

        if ($format === 'csv') {
            return $this->exportToCsv($data);
        }

        return $data;
    }

    /**
     * Convert data array to CSV format
     *
     * @param  array  $data  Data to convert
     * @return string CSV formatted data
     */
    protected function exportToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Add headers
        fputcsv($output, array_keys($data[0]));

        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
