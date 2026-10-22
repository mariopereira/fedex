<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the request and the response of the Locations Search API operation that FedexLocationsSearch implements, taken
 * from schemas/locations.json and written for SchemaValidator
 */
final class FedexLocationsSearchSchemas
{
    /**
     * Name of the definition of the request
     * @var string
     */
    const REQUEST = 'Full_Schema_Find_Location';

    /**
     * Name of the definition of the response
     * @var string
     */
    const RESPONSE = 'LoccResponseVO';

    /**
     * Get the definitions used to validate the request and the response, taken from schemas/locations.json.
     * Full_Schema_Find_Location describes the request and LoccResponseVO the response. The names of the other
     * schemas, and of their fields, are the ones of the file (including its misspellings)
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['Full_Schema_Find_Location'] = [
            'locationsSummaryRequestControlParameters?' => '@LocationsSummaryRequestControlParameters',
            'constraints?' => '@SearchLocationConstraints',
            'locationSearchCriterion?' => ['@type' => 'string', '@enum' => ['ADDRESS', 'GEOGRAPHIC_COORDINATES', 'PHONE_NUMBER']],
            'location' => '@Location',
            'phoneNumber?' => 'string',
            'multipleMatchesAction?' => ['@type' => 'string', '@enum' => ['RETURN_ALL', 'RETURN_ERROR', 'RETURN_FIRST']],
            'sort?' => '@Sort',
            'trackingInfo?' => '@TrackingInfo',
            'sameState?' => 'bool',
            'sameCountry?' => 'bool',
            'redirectToHoldType?' => ['@type' => 'string', '@enum' => ['FEDEX_EXPRESS', 'FEDEX_GROUND', 'FEDEX_GROUND_HOME_DELIVERY']],
            'locationAttrTypes?' => [['@type' => 'string', '@enum' => ['ACCEPTS_CASH', 'FEDEX_RETURNS_TECHNOLOGY', 'ALREADY_OPEN', 'COPY_AND_PRINT_SERVICES', 'DANGEROUS_GOODS_SERVICES', 'DIRECT_MAIL_SERVICES', 'LOCATION_IS_IN_AIRPORT', 'NOTARY_SERVICES', 'OPEN_TWENTY_FOUR_HOURS', 'PACK_AND_SHIP', 'PACKAGING_SUPPLIES', 'PASSPORT_PHOTO_SERVICES', 'RETURNS_SERVICES', 'SIGNS_AND_BANNERS_SERVICE', 'SONY_PICTURE_STATION', 'DROP_BOX', 'DOMESTIC_SHIPPING_SERVICES', 'INTERNATIONAL_SHIPPING_SERVICES', 'CLEARANCE_SERVICES']]],
            'locationCapabilities?' => ['@LocationCapabilities'],
            'packageAttributes?' => ['@PackageAttribute'],
            'locationTypes?' => [['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_ONSITE', 'FEDEX_EXPRESS_STATION', 'FEDEX_SHIPSITE', 'FEDEX_SHIP_AND_GET']]],
            'includeHoliday?' => 'bool',
            'dropoffTime?' => 'string',
            'dropOffServiceType?' => 'string',
            'carrierCodes?' => [['@type' => 'string', '@enum' => ['FDXE', 'FDXG']]],
            'getCall?' => 'bool',
            'packagingType?' => 'string',
            'totalDeclaredValue?' => '@Amounts',
            'totalCustomsValue?' => '@Amounts',
            'paymentType?' => ['@type' => 'string', '@enum' => ['SENDER', 'THIRD_PARTY', 'RECIPIENT', 'COLLECT']]
        ];

        $schemas['LocationsSummaryRequestControlParameters'] = [
            'distance?' => '@Distance',
            'maxResults?' => 'int'
        ];

        $schemas['Distance'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KM', 'MI']],
            'value?' => 'number'
        ];

        $schemas['SearchLocationConstraints'] = [
            'locationContentOptions?' => [['@type' => 'string', '@enum' => ['HOLIDAYS', 'LOCATION_DROPOFF_TIMES', 'TIMEZONE_OFFSET']]],
            'dropOffTimeNeeded?' => ['@type' => 'string', '@pattern' => '/^([01]\\d|2[0-3]):[0-5]\\d:[0-5]\\d$/D'],
            'excludeUnavailableLocations?' => 'string'
        ];

        $schemas['Location'] = [
            'address' => '@Address',
            'longLat?' => '@GeoCode'
        ];

        $schemas['Address'] = [
            'streetLines?' => ['string'],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool',
            'addressVerificationId?' => 'string',
            'addressClassification?' => ['@type' => 'string', '@enum' => ['MIXED', 'UNKNOWN', 'BUSINESS', 'RESIDENTIAL']],
            'addressClassificationConfidence?' => ['@type' => 'string', '@enum' => ['POTENTIAL', 'PERMANENT']]
        ];

        $schemas['GeoCode'] = [
            'latitude?' => 'string',
            'longitude?' => 'string'
        ];

        $schemas['Sort'] = [
            'criteria?' => ['@type' => 'string', '@enum' => ['DISTANCE', 'LATEST_EXPRESS_DROPOFF_TIME', 'LATEST_GROUND_DROPOFF_TIME', 'LOCATION_TYPE']],
            'order?' => ['@type' => 'string', '@enum' => ['ASCENDING', 'DESCENDING']]
        ];

        $schemas['TrackingInfo'] = [
            'uniqueTrackingId?' => 'string',
            'trackingNumber?' => 'string',
            'shipDate?' => 'string'
        ];

        $schemas['LocationCapabilities'] = [
            'serviceType?' => 'string',
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG']],
            'transferOfPossessionType?' => ['@type' => 'string', '@enum' => ['DROPOFF', 'HOLD_AT_LOCATION', 'REDIRECT_TO_HOLD_AT_LOCATION']],
            'serviceCategory?' => ['@type' => 'string', '@enum' => ['EXPRESS_FREIGHT', 'EXPRESS_PARCEL', 'GROUND_HOME_DELIVERY']],
            'daysOfWeek?' => ['string']
        ];

        $schemas['PackageAttribute'] = [
            'weight?' => '@Weight',
            'dimensions?' => '@Dimensions',
            'serviceOptions?' => ['string']
        ];

        $schemas['Weight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'number'
        ];

        $schemas['Dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['Amounts'] = [
            'amount?' => 'number',
            'currency?' => 'string'
        ];

        $schemas['LoccResponseVO'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO'
        ];

        $schemas['BaseProcessOutputVO'] = '@LocationSummaryOutputVO';

        $schemas['LocationSummaryOutputVO'] = [
            'totalResults?' => 'int',
            'resultsReturned?' => 'int',
            'matchedAddress?' => '@Address',
            'matchedAddressGeoCoord?' => '@GeoPositionalCoordinates',
            'locationDetailList?' => ['@LocationDetail'],
            'alerts?' => ['@Alert'],
            'nearestLocation?' => '@LocationDetail',
            'latestLocation?' => '@LocationDetail',
            'ambiguousAddress?' => ['@LocationDetail'],
            'ambiguousLocationsReturned?' => 'bool',
            'nearestLocationReturned?' => 'bool',
            'latestLocationReturned?' => 'bool',
            'lockerAvailabilityCode?' => 'int',
            'lockerAvailabilityMessage?' => 'string',
            'location?' => '@Location',
            'phoneNumber?' => 'string',
            'multipleMatchesAction?' => ['@type' => 'string', '@enum' => ['RETURN_ALL', 'RETURN_ERROR', 'RETURN_FIRST']],
            'sort?' => '@Sort',
            'trackingInfo?' => '@TrackingInfo',
            'sameState?' => 'bool',
            'sameCountry?' => 'bool',
            'redirectToHoldType?' => ['@type' => 'string', '@enum' => ['FEDEX_EXPRESS', 'FEDEX_GROUND', 'FEDEX_GROUND_HOME_DELIVERY']],
            'locationAttrTypes?' => [['@type' => 'string', '@enum' => ['ACCEPTS_CASH', 'ALREADY_OPEN', 'COPY_AND_PRINT_SERVICES', 'DANGEROUS_GOODS_SERVICES', 'DIRECT_MAIL_SERVICES', 'LOCATION_IS_IN_AIRPORT', 'NOTARY_SERVICES', 'OBSERVES_DAY_LIGHT_SAVING_TIMES', 'OPEN_TWENTY_FOUR_HOURS', 'PACK_AND_SHIP', 'PACKAGING_SUPPLIES', 'PASSPORT_PHOTO_SERVICES', 'RETURNS_SERVICES', 'SIGNS_AND_BANNERS_SERVICE', 'SONY_PICTURE_STATION', 'DROP_BOX', 'DOMESTIC_SHIPPING_SERVICES', 'INTERNATIONAL_SHIPPING_SERVICES', 'CLEARANCE_SERVICES']]],
            'locationCapabilities?' => ['@LocationCapabilities'],
            'packageMaximumLimits?' => ['@PackageAttribute'],
            'locationTypes?' => [['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_ONSITE', 'FEDEX_EXPRESS_STATION', 'FEDEX_SHIPSITE', 'FEDEX_FREIGHT_SERVICE_CENTER', 'FEDEX_HOME_DELIVERY_STATION', 'FEDEX_SMART_POST_HUB']]],
            'includeHoliday?' => 'bool',
            'dropoffTime?' => 'string',
            'dropOffServiceType?' => 'string',
            'searchBy?' => 'string',
            'contentOptions?' => 'string',
            'carrierCodes?' => [['@type' => 'string', '@enum' => ['FDXE', 'FDXG']]],
            'getCall?' => 'bool'
        ];

        $schemas['GeoPositionalCoordinates'] = [
            'latitude?' => 'number',
            'longitude?' => 'number'
        ];

        $schemas['LocationDetail'] = [
            'distance?' => '@Distance',
            'contactAndAddress?' => '@ContactAndAddress',
            'locationId?' => 'string',
            'storeHours?' => ['@LocationHours'],
            'carrierDetailList?' => ['@FASTCarrierDetails'],
            'geoPositionalCoordinates?' => '@GeoPositionalCoordinates',
            'locationType?' => ['@type' => 'string', '@enum' => ['FEDEX_AUTHORIZED_SHIP_CENTER', 'FEDEX_OFFICE', 'FEDEX_SELF_SERVICE_LOCATION', 'FEDEX_EXPRESS_STATION', 'FEDEX_STAFFED', 'FEDEX_GROUND_TERMINAL', 'FEDEX_HOME_DELIVERY_STATION', 'FEDEX_ONSITE', 'FEDEX_SHIPSITE', 'FEDEX_FACILITY', 'FEDEX_FREIGHT_SERVICE_CENTER', 'FEDEX_SMART_POST_HUB']],
            'locationAttributeTypes?' => [['@type' => 'string', '@enum' => ['ACCEPTS_CASH', 'ALREADY_OPEN', 'COPY_AND_PRINT_SERVICES', 'DANGEROUS_GOODS_SERVICES', 'DIRECT_MAIL_SERVICES', 'EXPRESS_FREIGHT_DROPOFFS', 'EXPRESS_PARCEL_DROPOFFS', 'FEDEX_FREIGHT_DROPOFFS', 'GROUND_DROPOFFS', 'GROUND_HOME_DELIVERY_DROPOFFS', 'LOCATION_IS_IN_AIRPORT', 'NOTARY_SERVICES', 'OBSERVES_DAY_LIGHT_SAVING_TIMES', 'OPEN_TWENTY_FOUR_HOURS', 'PACK_AND_SHIP', 'PACKAGING_SUPPLIES', 'PASSPORT_PHOTO_SERVICES', 'RETURNS_SERVICES', 'SATURDAY_DROPOFFS', 'SIGNS_AND_BANNERS_SERVICE', 'WEEKDAY_EXPRESS_HOLD_AT_LOCATION', 'WEEKDAY_GROUND_HOLD_AT_LOCATION', 'SONY_PICTURE_STATION', 'EXPANDED_OFFICE_SUPPLIES_AND_TECHNOLOGY_PRODUCTS', 'VIDEO_CONFERENCING', 'SATURDAY_EXPRESS_HOLD_AT_LOCATION', 'KIOSK_LOCATION', 'STAFFED_LOCATION', 'SAME_DAY_DROPOFFS', 'SAME_DAY_CITY_DROPOFFS', 'DROP_BOX', 'DOMESTIC_SHIPPING_SERVICES', 'INTERNATIONAL_SHIPPING_SERVICES']]],
            'lockerAvailability?' => 'bool',
            'packageMaximumLimits?' => '@PackageAttribute',
            'specialInstructions?' => 'string',
            'rthservice?' => 'string',
            'locationCapabilities?' => '@LocationCapabilities'
        ];

        $schemas['ContactAndAddress'] = [
            'address?' => '@Address',
            'contact?' => '@Contact',
            'addressAncillaryDetail?' => '@AddressAncillaryDetail'
        ];

        $schemas['Contact'] = [
            'personName?' => 'string',
            'emailAddress?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string',
            'faxNumber?' => 'string',
            'companyName?' => 'string',
            'stateTaxId?' => 'string',
            'fedralTaxId?' => 'string'
        ];

        $schemas['AddressAncillaryDetail'] = [
            'locationInCity' => 'string',
            'suite?' => 'string',
            'adderssVerificationOverrideReason?' => 'string',
            'displayName' => 'string',
            'locationInProperty?' => 'string',
            'addtionalDescriptions?' => 'string',
            'department?' => 'string',
            'roomFloor?' => 'string',
            'crossStreet?' => 'string',
            'building?' => 'string',
            'apartment?' => 'string',
            'room?' => 'string'
        ];

        $schemas['LocationHours'] = [
            'dayofweek?' => ['@type' => 'string', '@enum' => ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY']],
            'operationalHoursType?' => ['@type' => 'string', '@enum' => ['CLOSED_ALL_DAY', 'OPEN_ALL_DAY', 'OPEN_BY_HOURS']],
            'operationalHours?' => ['@TimeRange'],
            'exceptionalHoursType?' => ['@type' => 'string', '@enum' => ['CLOSED_ALL_DAY', 'OPEN_ALL_DAY', 'OPEN_BY_HOURS']],
            'exceptionalHours?' => ['@TimeRange']
        ];

        $schemas['TimeRange'] = [
            'begins?' => 'string',
            'ends?' => 'string'
        ];

        $schemas['FASTCarrierDetails'] = [
            'serviceType?' => 'string',
            'serviceCategory?' => ['@type' => 'string', '@enum' => ['EXPRESS', 'GROUND', 'EXPRESS_FREIGHT', 'FREIGHT', 'SMARTPOST', 'EXPRESS_PARCEL', 'NULL']]
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'message?' => 'string'
        ];

        return $schemas;
    }
}
