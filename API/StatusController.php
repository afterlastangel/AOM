<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\API;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;

class StatusController
{
    public static function getStatus()
    {
        $status = [
            'stats' => [],
            'platforms' => [],
        ];

        foreach (['Hour', 'Day', 'Week'] as $period) {

            $visits = intval(Db::fetchOne(
                'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                     WHERE log_visit.visit_first_action_time >= ?',
                [
                    date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                ]));

            $orders = intval(Db::fetchOne(
                'SELECT COUNT(*) FROM ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                     WHERE log_conversion.server_time >= ?',
                [
                    date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                ]));

            $status['stats']['last' . $period] = [
                'visits' => $visits,
                'orders' => $orders,
                'conversionRate' => ($visits > 0 ? $orders / $visits : 0),
            ];

            foreach (AOM::getPlatforms() as $platformName) {

                $platform = AOM::getPlatformInstance($platformName);
                $tableName = AOM::getPlatformDataTableNameByPlatformName($platformName);

                $status['platforms'][$platformName] = [
                    'daysSinceLastImportWithResults' =>
                        (Db::fetchOne('SELECT COUNT(*) FROM ' . $tableName) > 0)
                            ? intval(Db::fetchOne('SELECT DATEDIFF(CURDATE(), MAX(date)) FROM ' . $tableName))
                            : null,
                ];

                foreach (['Hour', 'Day'] as $period) {

                    $visitsWithPlatform = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $visitsWithAdParams = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"
                            AND aom_ad_params IS NOT NULL AND aom_ad_params != "null"',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $visitsWithAdData = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"
                            AND aom_ad_data IS NOT NULL AND aom_ad_data != "null"',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $visitsWithPlatformRowId = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"
                            AND aom_platform_row_id IS NOT NULL',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $status['platforms'][$platformName]['last' . $period] = [
                        'visitsWithPlatform' => $visitsWithPlatform,
                        'visitsWithAdParams' => $visitsWithAdParams,
                        'visitsWithAdData' => $visitsWithAdData,
                        'visitsWithPlatformRowId' => $visitsWithPlatformRowId,
                    ];

                }
            }
        }

        return $status;
    }
}