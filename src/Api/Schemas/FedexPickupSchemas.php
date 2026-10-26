<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Api\Schemas;

/**
 * Definitions of the requests and responses of the Pickup Request API operations that FedexPickup implements, generated from
 * schemas/pickup.json and written for SchemaValidator
 */
final class FedexPickupSchemas
{
    /**
     * Name of the definition of the Create Pickup request
     * @var string
     */
    const CREATE_REQUEST = 'Full_Schema_Create_Pickup';

    /**
     * Name of the definition of the Create Pickup response
     * @var string
     */
    const CREATE_RESPONSE = 'PudcResponseVO_CreatePickup';

    /**
     * Name of the definition of the Check Pickup Availability request
     * @var string
     */
    const AVAILABILITY_REQUEST = 'Full_Schema_Pickup_Availability';

    /**
     * Name of the definition of the Check Pickup Availability response
     * @var string
     */
    const AVAILABILITY_RESPONSE = 'PudcResponseVO_PickupAvailability';

    /**
     * Name of the definition of the Cancel Pickup request
     * @var string
     */
    const CANCEL_REQUEST = 'Full_Schema_Cancel_Pickup';

    /**
     * Name of the definition of the Cancel Pickup response
     * @var string
     */
    const CANCEL_RESPONSE = 'PudcResponseVO_CancelPickup';

    /**
     * Get the definitions of all the requests and responses. The names of the definitions, and of their fields, are the ones of
     * schemas/pickup.json, with these differences:
     *  - what the file writes as an allOf of a definition and an overlay that adds required keys (PickupAddress and
     *    PickupLocationParty) is a definition with the fields of the base and the required keys of both. The address and the
     *    contact of PickupLocationParty are tightened by the overlay, and are definitions of their own, with the suffix _overlay
     *  - the minimum length of the street lines of an address is not checked: FedEx decides
     *  - the postal code of PickupAddress and the state or province code of PickupLocationParty_address_overlay are optional,
     *    although the file requires them: the minimum fields of an address vary among countries, and the examples of FedEx leave
     *    them out for some countries. FedEx decides
     *  - PickupScheduleOption.defaultLatestTimeOptions has no type in the file: it accepts any value
     *  - Full_Schema_Pickup_Availability.shipmentAttributes is an object in the file, and two of its sample payloads send a list
     *    with one object: it accepts an object, or a list (the content of the list is not checked, FedEx decides)
     * @return array
     */
    public static function definitions() : array
    {
        $schemas = [];

        $schemas['AccountNumber'] = [
            'value?' => 'string'
        ];

        $schemas['Address'] = [
            'streetLines?' => [['@type' => 'string', '@max' => 35]],
            'urbanizationCode?' => 'string',
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool',
            'addressClassification?' => ['@type' => 'string', '@enum' => ['MIXED', 'UNKNOWN', 'BUSINESS', 'RESIDENTIAL']]
        ];

        $schemas['Address_1'] = [
            'streetLines?' => [['@type' => 'string', '@max' => 35]],
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode?' => 'string',
            'residential?' => 'bool',
            'addressClassification?' => ['@type' => 'string', '@enum' => ['MIXED', 'UNKNOWN', 'BUSINESS', 'RESIDENTIAL']]
        ];

        $schemas['Alert'] = [
            'code?' => 'string',
            'alertType?' => ['@type' => 'string', '@enum' => ['NOTE', 'WARNING']],
            'message?' => 'string'
        ];

        $schemas['BaseProcessOutputVO_CancelPickup'] = '@CancelPickupOutputVO';

        $schemas['BaseProcessOutputVO_CreatePickup'] = '@CreatePickupOutputVO';

        $schemas['BaseProcessOutputVO_PickupAvailability'] = '@PickupAvailabilityOutputVO';

        $schemas['CancelPickupOutputVO'] = [
            'pickupConfirmationCode?' => 'string',
            'cancelConfirmationMessage?' => 'string',
            'alerts?' => ['@Alert']
        ];

        $schemas['Contact_CreatePickup'] = [
            'companyName?' => 'string',
            'personName?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string'
        ];

        $schemas['CreatePickupOutputVO'] = [
            'pickupConfirmationCode?' => 'string',
            'location?' => 'string',
            'alerts?' => ['@Alert']
        ];

        $schemas['Dimensions'] = [
            'length?' => 'int',
            'width?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']],
            'height?' => 'int'
        ];

        $schemas['Dimensions_2'] = [
            'length?' => 'int',
            'width?' => 'int',
            'height?' => 'int',
            'units?' => ['@type' => 'string', '@enum' => ['CM', 'IN']]
        ];

        $schemas['Duration'] = [
            'hours?' => 'int',
            'minutes?' => 'int'
        ];

        $schemas['EmailDetail'] = [
            'address?' => 'string',
            'locale?' => 'string'
        ];

        $schemas['ExpressFreightPickupDetail'] = [
            'truckType?' => ['@type' => 'string', '@enum' => ['DROP_TRAILER_AGREEMENT', 'LIFTGATE', 'TRACTOR_TRAILER_ACCESS']],
            'service?' => 'string',
            'trailerLength?' => ['@type' => 'string', '@enum' => ['TRAILER_28_FT', 'TRAILER_48_FT', 'TRAILER_53_FT']],
            'bookingNumber?' => 'string',
            'dimensions?' => '@Dimensions'
        ];

        $schemas['Full_Schema_Cancel_Pickup'] = [
            'associatedAccountNumber' => '@AccountNumber',
            'pickupConfirmationCode' => 'string',
            'remarks?' => 'string',
            'carrierCode?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG']],
            'scheduledDate' => 'string',
            'location?' => 'string',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_Create_Pickup'] = [
            'associatedAccountNumber' => '@AccountNumber',
            'originDetail' => '@OriginDetail',
            'associatedAccountNumberType?' => 'string',
            'totalWeight?' => '@Weight',
            'packageCount?' => 'int',
            'carrierCode' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG']],
            'requestedExpressPickupDetail?' => '@RequestedExpressPickupDetail',
            'accountAddressOfRecord?' => '@Address_1',
            'remarks?' => 'string',
            'countryRelationships?' => ['@type' => 'string', '@enum' => ['DOMESTIC', 'INTERNATIONAL']],
            'pickupType?' => 'string',
            'trackingNumber?' => 'string',
            'commodityDescription?' => 'string',
            'expressFreightDetail?' => '@ExpressFreightPickupDetail',
            'oversizePackageCount?' => 'int',
            'pickupNotificationDetail?' => '@PickupNotificationdetail',
            'pickupChargesPayment?' => '@PickupChargesPayment',
            'version?' => '@Version'
        ];

        $schemas['Full_Schema_Pickup_Availability'] = [
            'pickupAddress' => '@PickupAddress',
            'dispatchDate?' => 'string',
            'packageReadyTime?' => 'string',
            'customerCloseTime?' => 'string',
            'pickupType?' => ['@type' => 'string', '@enum' => ['ON_CALL', 'TAG']],
            'pickupRequestType' => [['@type' => 'string', '@enum' => ['SAME_DAY', 'FUTURE_DAY']]],
            'shipmentAttributes?' => '@ShipmentAttributes|array',
            'numberOfBusinessDays?' => 'int',
            'packageDetails?' => ['@RequestedPickupPackageDetail'],
            'associatedAccountNumber?' => 'string',
            'associatedAccountNumberType?' => ['@type' => 'string', '@enum' => ['FEDEX_EXPRESS', 'FEDEX_GROUND']],
            'carriers' => [['@type' => 'string', '@enum' => ['FDXE', 'FDXG']]],
            'countryRelationship' => ['@type' => 'string', '@enum' => ['DOMESTIC', 'INTERNATIONAL']],
            'version?' => '@Version'
        ];

        $schemas['OriginDetail'] = '@PickupOriginDetail';

        $schemas['PackageSpecialServices'] = [
            'specialServiceTypes?' => ['string']
        ];

        $schemas['Party_createPickup'] = [
            'contact?' => '@Contact_CreatePickup',
            'address?' => '@Address',
            'accountNumber?' => '@AccountNumber',
            'deliveryInstructions?' => 'string'
        ];

        $schemas['Payor'] = [
            'responsibleParty?' => '@Payor_responsibleParty'
        ];

        $schemas['PayorType'] = ['@type' => 'string', '@enum' => ['RECIPIENT', 'SENDER', 'THIRD_PARTY']];

        $schemas['PickupAddress'] = [
            'streetLines?' => [['@type' => 'string', '@max' => 35]],
            'urbanizationCode?' => 'string',
            'city?' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode?' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool',
            'addressClassification?' => ['@type' => 'string', '@enum' => ['MIXED', 'UNKNOWN', 'BUSINESS', 'RESIDENTIAL']]
        ];

        $schemas['PickupAvailabilityOutputVO'] = [
            'requestTimestamp?' => 'string',
            'options?' => ['@PickupScheduleOption'],
            'alerts?' => ['@Alert']
        ];

        $schemas['PickupChargesPayment'] = [
            'paymentType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'CASH', 'COLLECT', 'CHECK', 'CREDIT_CARD', 'RECIPIENT', 'SENDER', 'THIRD_PARTY']],
            'payor?' => '@Payor',
            'payorType?' => '@PayorType'
        ];

        $schemas['PickupLocationParty'] = [
            'contact' => '@PickupLocationParty_contact_overlay',
            'address' => '@PickupLocationParty_address_overlay',
            'accountNumber?' => '@AccountNumber',
            'deliveryInstructions?' => 'string'
        ];

        $schemas['PickupLocationParty_address'] = [
            'streetLines' => ['string'],
            'city' => 'string',
            'stateOrProvinceCode' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string'
        ];

        $schemas['PickupLocationParty_contact'] = [
            'companyName?' => 'string',
            'personName?' => 'string',
            'phoneNumber?' => 'string'
        ];

        $schemas['PickupNotificationDetail'] = [
            'emailDetails?' => ['@EmailDetail'],
            'format?' => ['@type' => 'string', '@enum' => ['HTML', 'TEXT']],
            'userMessage?' => 'string'
        ];

        $schemas['PickupNotificationdetail'] = '@PickupNotificationDetail';

        $schemas['PickupOriginDetail'] = [
            'pickupAddressType?' => ['@type' => 'string', '@enum' => ['ACCOUNT', 'SHIPPER', 'OTHER']],
            'pickupLocation' => '@PickupLocationParty',
            'readyDateTimestamp' => 'string',
            'customerCloseTime' => 'string',
            'pickupDateType?' => ['@type' => 'string', '@enum' => ['SAME_DAY', 'FUTURE_DAY']],
            'packageLocation?' => ['@type' => 'string', '@enum' => ['FRONT', 'NONE', 'REAR', 'SIDE']],
            'buildingPart?' => ['@type' => 'string', '@enum' => ['APARTMENT', 'BUILDING', 'DEPARTMENT', 'FLOOR', 'ROOM', 'SUITE']],
            'buildingPartDescription?' => 'string',
            'earlyPickup?' => 'bool',
            'suppliesRequested?' => 'string',
            'geographicalPostalCode?' => 'string'
        ];

        $schemas['PickupScheduleOption'] = [
            'carrier?' => ['@type' => 'string', '@enum' => ['FDXE', 'FDXG', 'FXSP', 'FXFR']],
            'available?' => 'bool',
            'pickupDate?' => 'string',
            'cutOffTime?' => 'string',
            'accessTime?' => '@Duration',
            'residentialAvailable?' => 'bool',
            'countryRelationship?' => 'string',
            'scheduleDay?' => ['@type' => 'string', '@enum' => ['SAME_DAY', 'FUTURE_DAY']],
            'defaultReadyTime?' => 'string',
            'defaultLatestTimeOptions?' => 'mixed',
            'earlyCutOffTime?' => 'string',
            'earlyAccessTime?' => '@Duration',
            'earlyPickupLocationId?' => 'string',
            'readyTimeOptions?' => ['string'],
            'latestTimeOptions?' => ['string']
        ];

        $schemas['PickupShipmentAttributes'] = [
            'serviceType?' => 'string',
            'weight?' => '@Weight',
            'packagingType?' => 'string',
            'dimensions?' => '@Dimensions_2'
        ];

        $schemas['PudcResponseVO_CancelPickup'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_CancelPickup'
        ];

        $schemas['PudcResponseVO_CreatePickup'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_CreatePickup'
        ];

        $schemas['PudcResponseVO_PickupAvailability'] = [
            'transactionId?' => 'string',
            'customerTransactionId?' => 'string',
            'output?' => '@BaseProcessOutputVO_PickupAvailability'
        ];

        $schemas['RequestedExpressPickupDetail'] = [
            'serviceType?' => '@ServiceType'
        ];

        $schemas['RequestedPickupPackageDetail'] = [
            'packageSpecialServices?' => '@PackageSpecialServices'
        ];

        $schemas['ServiceType'] = 'string';

        $schemas['ShipmentAttributes'] = '@PickupShipmentAttributes';

        $schemas['Version'] = [
            'major?' => 'int',
            'minor?' => 'int',
            'patch?' => 'int'
        ];

        $schemas['Weight'] = [
            'units?' => ['@type' => 'string', '@enum' => ['KG', 'LB']],
            'value?' => 'number'
        ];

        $schemas['Payor_responsibleParty'] = [
            'accountNumber?' => '@Payor_responsibleParty_accountNumber'
        ];

        $schemas['PickupLocationParty_address_overlay'] = [
            'streetLines' => [['@type' => 'string', '@max' => 35]],
            'urbanizationCode?' => 'string',
            'city' => 'string',
            'stateOrProvinceCode?' => 'string',
            'postalCode' => 'string',
            'countryCode' => 'string',
            'residential?' => 'bool',
            'addressClassification?' => ['@type' => 'string', '@enum' => ['MIXED', 'UNKNOWN', 'BUSINESS', 'RESIDENTIAL']]
        ];

        $schemas['PickupLocationParty_contact_overlay'] = [
            'companyName?' => 'string',
            'personName?' => 'string',
            'phoneNumber?' => 'string',
            'phoneExtension?' => 'string'
        ];

        $schemas['Payor_responsibleParty_accountNumber'] = [
            'value?' => 'string',
            'key?' => 'string'
        ];

        return $schemas;
    }
}
