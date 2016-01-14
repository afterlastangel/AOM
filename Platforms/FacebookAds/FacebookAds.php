<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class FacebookAds extends Platform implements PlatformInterface
{
    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableNameStatic()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_FACEBOOK_ADS));
    }

    /**
     * Extracts advertisement platform specific data from the query params and validates it.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdParamsFromQueryParams($paramPrefix, array $queryParams)
    {
        // Validate required params
        $missingParams = array_diff(
            [$paramPrefix . '_campaign_id', $paramPrefix . '_adset_id', $paramPrefix . '_ad_id',],
            array_keys($queryParams)
        );
        if (count($missingParams)) {
            $this->getLogger()->warning(
                'Visit with platform ' . AOM::PLATFORM_FACEBOOK_ADS . ' without required param/s: '
                . implode(', ', $missingParams)
            );

            return null;
        }

        return [
            'platform' => AOM::PLATFORM_FACEBOOK_ADS,
            'campaignId' => $queryParams[$paramPrefix . '_campaign_id'],
            'adsetId' => $queryParams[$paramPrefix . '_adset_id'],
            'adId' => $queryParams[$paramPrefix . '_ad_id'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAdDataFromAdParams($idsite, array $adParams)
    {
        $data = $this->getAdData($idsite, date('Y-m-d'), $adParams);
        if(!$data[0]) {
            $data = [null, $this::getHistoricalAdData($idsite, $adParams['campaignId'], $adParams['adsetId'])];
        }
        return $data;
    }

    /**
     * Searches for matching ad data
     * @param $idsite
     * @param $date
     * @param $adParams
     * @return array|null
     * @throws \Exception
     */
    public static function getAdData($idsite, $date, $adParams)
    {
        $result = DB::fetchAll(
            'SELECT * FROM ' . FacebookAds::getDataTableNameStatic() . ' WHERE idsite = ? AND date = ? AND campaign_id = ? AND adset_id = ? AND ad_id = ?',
            [
                $idsite,
                $date,
                $adParams['campaignId'],
                $adParams['adsetId'],
                $adParams['adId']
            ]
        );

        if (count($result) > 1) {
            throw new \Exception('Found more than one match for exact match.');
        } elseif(count($result) == 0) {
            return null;
        }

        return [$result[0]['id'], $result[0]];
    }



    /**
     * Searches for historical AdData
     *
     * @param $idsite
     * @param $campaignId
     * @param $adsetId
     * @return array|null
     * @throws \Exception
     */
    public static function getHistoricalAdData($idsite, $campaignId, $adsetId)
    {
        $result = DB::fetchAll(
            'SELECT * FROM ' . FacebookAds::getDataTableNameStatic() . ' WHERE idsite = ? AND campaign_id = ? AND adset_id = ?',
            [
                $idsite,
                $campaignId,
                $adsetId
            ]
        );

        if (count($result) > 0) {
            // Keep generic date-independent information only
            return [
                'campaign_id' => $campaignId,
                'campaign_name' => $result[0]['campaign_name'],
                'adset_id' => $adsetId,
                'adset_name' => $result[0]['adset_name'],
            ];
        }

        return null;
    }
}
