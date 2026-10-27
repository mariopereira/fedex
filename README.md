# fedex

A PHP interface to the FedEx APIs.

> **Status: in development.** The API of the package may still change.

| FedEx API | Class | State |
| --- | --- | --- |
| OAuth authorization (`/oauth/token`) | `FedexAuth` | Implemented |
| Postal Code Validation (`/country/v1/postal/validate`) | `FedexPostalCodeValidation` | Implemented. Checked against the FedEx schema in `schemas/`, not yet against the live API |
| Locations Search (`/location/v1/locations`) | `FedexLocationsSearch` | Implemented. Checked against the FedEx schema in `schemas/`, not yet against the live API |
| Address Validation (`/address/v1/addresses/resolve`) | `FedexAddressValidation` | Implemented. Checked against the FedEx schema in `schemas/`, not yet against the live API |
| Ship: Create Shipment (`/ship/v1/shipments`), Validate Shipment (`/ship/v1/shipments/packages/validate`), Cancel Shipment (`/ship/v1/shipments/cancel`), Create Tag (`/ship/v1/shipments/tag`), Cancel Tag (`/ship/v1/shipments/tag/cancel/{shipmentid}`) and Retrieve Async Ship (`/ship/v1/shipments/results`) | `FedexShip` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Basic Integrated Visibility (tracking): Track by Tracking Number (`/track/v1/trackingnumbers`), Track Multiple Piece Shipment (`/track/v1/associatedshipments`), Track by References (`/track/v1/referencenumbers`), Track by Transportation Control Number (`/track/v1/tcn`), Track Document (`/track/v1/trackingdocuments`) and Send Notification (`/track/v1/notifications`) | `FedexBasicTracking` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Consolidation: Create (`/ship/v1/consolidations`), Modify (`PUT /ship/v1/consolidations`), Create Consolidation Shipment (`/ship/v1/consolidations/shipments`), Shipment Results (`/ship/v1/consolidations/results`), Confirm (`/ship/v1/consolidations/confirmations`), Confirm Results (`/ship/v1/consolidations/confirmationresults`), Retrieve (`/ship/v1/consolidations/retrieve`), Delete Shipments (`PUT /ship/v1/consolidations/shipments/delete`) and Delete (`PUT /ship/v1/consolidations/delete`) | `FedexConsolidation` | Implemented. Checked against the FedEx schema in `schemas/` (the top level of each request; the schema contradicts its own samples below that), never called against the live API |
| Estimated Duty and Tax: Duties Taxes Estimates (`/dutytax/v1/estimate`) | `FedexDutyAndTax` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Service Availability: Retrieve Services and Transit Times (`/availability/v1/transittimes`), Retrieve Services and Packaging Options (`/availability/v1/packageandserviceoptions`) and Retrieve Special Service Options (`/availability/v1/specialserviceoptions`) | `FedexServiceAvailability` | Implemented. Checked against the FedEx schema in `schemas/` (a number may also be given as a text that is a number, as the samples of the schema do), never called against the live API |
| Rates and Transit Times: Retrieve Rates and Transit Times (`/rate/v1/rates/quotes`) | `FedexRateTransitTimes` | Implemented. Checked against the FedEx schema in `schemas/` (a number may also be a text that is a number and a postal code an integer, and keys that the schema does not define are accepted, because the samples of the schema do all that), never called against the live API |
| Pickup: Create Pickup (`/pickup/v1/pickups`), Check Pickup Availability (`/pickup/v1/pickups/availabilities`) and Cancel Pickup (`PUT /pickup/v1/pickups/cancel`) | `FedexPickup` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Global Trade: Retrieve Regulatory Details (`/globaltrade/v1/shipments/regulatorydetails/retrieve`) | `FedexGlobalTrade` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Ship End of Day Close: Ground End Of Day Close (`PUT /ship/v1/endofday/`) and Reprint End of the day (`/ship/v1/endofday/`) | `FedexEndOfDayClose` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Harmonized System (HS) Code Classification: Search Harmonized Code Data (`/commodity/v2/hscodes/search`) | `FedexHarmonizedCode` | Implemented. Checked against the FedEx schema in `schemas/` (either the destination or the origin country of a commodity is enough, as the documentation says), never called against the live API |
| Regulatory: Regulatory Profile (`/regulatory/v1/profiles`) | `FedexRegulatory` | Implemented. Checked against the FedEx schema in `schemas/`, never called against the live API |
| Open Ship: Create Open Shipment (`/ship/v1/openshipments/create`), Modify Open Shipment (`PUT /ship/v1/openshipments/`), Confirm Open Shipment (`/ship/v1/openshipments/`), Modify Open Shipment Packages (`PUT /ship/v1/openshipments/packages`), Add Open Shipment Packages (`/ship/v1/openshipments/packages`), Delete Open Shipment Packages (`PUT /ship/v1/openshipments/packages/delete`), Retrieve Open Shipment Packages (`/ship/v1/openshipments/packages/retrieve`), Delete Open Shipment (`PUT /ship/v1/openshipments/delete`), Retrieve Open Shipment (`/ship/v1/openshipments/retrieve`) and Get Open Shipment Results (`/ship/v1/openshipments/results`) | `FedexOpenShip` | Implemented. Checked against the FedEx schema in `schemas/` (the top level of each request: FedEx's collection contradicts the schema below that), never called against the live API |
| Trade Documents Upload: Upload Document (`/documents/v1/etds/upload`), Upload Multiple Documents (`/documents/v1/etds/multiupload`), Upload Images (`/documents/v1/lhsimages/upload`), Upload Encoded Image (`/documents/v1/lhsimages/encodedupload`) and Upload Multiple Encoded Documents (`/documents/v1/etds/encodedmultiupload`), on the hosts of this API | `FedexTradeDocuments` | Implemented. Checked against the FedEx schema in `schemas/` (the upload of an image sends the description and the rules as two parts, as the schema describes them), never called against the live API |

## Requirements

* PHP 7.4 or later. The code uses PHP 7.4 syntax only and is also checked to run without deprecation notices on newer versions (up to PHP 8.5).
* The `curl` and `json` extensions. cURL is used by the default HTTP transport (see [HTTP transport](#http-transport)). The `mbstring` extension is used when available.

## Installation

```bash
composer require mariopereira/fedex
```

## Usage

```php
use Mariopereira\Fedex\Api\FedexPostalCodeValidation;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Exception\FedexException;
use Mariopereira\Fedex\Misc\FedexUtils;

// true = FedEx sandbox, false = production
$auth = new FedexAuth(true, FedexUtils::GRANT_TYPE_CLIENT_CREDENTIALS, 'YOUR_CLIENT_ID', 'YOUR_CLIENT_SECRET');

$postal = new FedexPostalCodeValidation($auth);

try {
    $shipDate = date('Y-m-d', strtotime('+1 day'));
    $result = $postal->validate('FDXG', 'US', 'TN', '38017', $shipDate);

    echo $result->output->cleanedPostalCode;
} catch (FedexException $ex) {
    echo $ex->getMessage(), ' ', $ex->getErrorsAsString();
}
```

The access token is requested automatically, kept in the `FedexAuth` object until it expires, and renewed once if FedEx rejects it.

### Postal code validation

The carrier code must be one of `FedexPostalCodeValidation::CARRIER_CODES` (`FDXE`, `FDXG`, `FXSP`, `FDXC` and `FXCC`). It is accepted in upper or lower case. Any other value is rejected before a request is sent.

The city is optional, except when the country is Australia (`AU`):

```php
$result = $postal->validate('FDXE', 'AU', 'NSW', '2000', $shipDate, null, null, 'Sydney');
```

The optional arguments after the ship date are the routing code, the state/postal code mismatch check, and the city.

The request carries the API version, `1.1.1` unless you say otherwise: the schema marks `version` as optional and gives `1`, `1` and `1` only as examples, so this is not a default that FedEx documents. Pass the numbers to the constructor or to `setAPIversion()` to send another version, or `null` to leave a number out. When all three are `null` the request has no `version` at all:

```php
$postal->setAPIversion(null, null, null);
```

### Locations search

Finds FedEx locations by address, by geographic coordinates or by phone number:

```php
use Mariopereira\Fedex\Api\FedexLocationsSearch;

$locations = new FedexLocationsSearch($auth);

// by address: the address of the request, then any other field of the request
$result = $locations->searchByAddress(
    ['postalCode' => '38017', 'countryCode' => 'US'],
    ['locationsSummaryRequestControlParameters' => ['maxResults' => 5], 'locationTypes' => ['FEDEX_OFFICE']]
);

foreach ($result->output->locationDetailList ?? [] as $location) {
    echo $location->contactAndAddress->address->city ?? '', PHP_EOL;
}

// by geographic coordinates, or by (landline) phone number
$result = $locations->searchByCoordinates(35.1495, -90.049, 'US');
$result = $locations->searchByPhoneNumber('9015551234', 'US');
```

For any other search, `FedexLocationsSearch::newRequest()` returns an empty request with every field of the API, named as in `schemas/locations.json`. Fill in what you need and pass it to `search()`:

```php
$request = FedexLocationsSearch::newRequest();
$request['location']['address']['postalCode'] = '38017';
$request['location']['address']['countryCode'] = 'US';
$request['sort']['criteria'] = 'DISTANCE';
$request['locationTypes'][] = 'FEDEX_OFFICE';

$result = $locations->search($request);
```

The request is checked before it is sent, and a problem is reported with a `FedexLocationsSearchException`:

* Fields set to `null`, and empty lists, are left out.
* A value of the wrong type and a value that is not in the lists of the schema are rejected, and a field that is not in the schema is sent (see [Request validation](#request-validation)). In the schema the latitude and the longitude are strings (`searchByCoordinates()` converts numbers for you) and `excludeUnavailableLocations` is a string (`'true'` or `'false'`).
* The country code is required. So is a city or a postal code when searching by address (the default), the latitude and the longitude when searching by geographic coordinates, and the phone number when searching by phone number.
* The `searchBy...()` methods set the search criterion and the location themselves, so those two fields cannot be given as options.

### Address validation

Validates and resolves addresses, up to 100 in a request:

```php
use Mariopereira\Fedex\Api\FedexAddressValidation;

$validation = new FedexAddressValidation($auth);

// one address
$result = $validation->validate([
    'streetLines' => ['7372 PARKRIDGE BLVD', 'APT 286'],
    'city' => 'IRVING',
    'stateOrProvinceCode' => 'TX',
    'postalCode' => '75063-8659',
    'countryCode' => 'US'
]);

foreach ($result->output->resolvedAddresses ?? [] as $address) {
    echo $address->city ?? '', ' ', $address->classification ?? '', PHP_EOL;
}

// several addresses at once, and any other field of the request as an option
$result = $validation->validateArray(
    [
        ['streetLines' => ['24 Grosvenor Square'], 'city' => 'London', 'postalCode' => 'W1A 2LQ', 'countryCode' => 'GB'],
        ['streetLines' => ['Via Vittorio Veneto, 121'], 'city' => 'Roma', 'postalCode' => '00187', 'countryCode' => 'IT']
    ],
    ['validateAddressControlParameters' => ['includeResolutionTokens' => true]]
);
```

To send a `clientReferenceId` with an address, build the whole request. `FedexAddressValidation::newRequest()` and `newAddress()` return empty structures with every field of the API, named as in `schemas/address-validation.json`:

```php
$request = FedexAddressValidation::newRequest();

$item = FedexAddressValidation::newAddress();
$item['address']['streetLines'][] = '7372 PARKRIDGE BLVD';
$item['address']['city'] = 'IRVING';
$item['address']['postalCode'] = '75063-8659';
$item['clientReferenceId'] = 'order-123';
$request['addressesToValidate'][] = $item;

$result = $validation->resolve($request);
```

The request is checked before it is sent, and a problem is reported with a `FedexAddressValidationException`:

* Fields set to `null`, and empty lists, are left out.
* A value of the wrong type is rejected, and a field that is not in the schema is sent (see [Request validation](#request-validation)).
* Every address needs its street lines (a list). The city, the postal code (a text or an integer), the state and the country are optional, because the minimum fields vary among countries: FedEx checks them (and an empty street line, which FedEx decides about). The state, if given, has at most two characters and the country, if given, two letters.
* `inEffectAsOfTimestamp`, if given, must be a real date written as `YYYY-MM-DD`.
* `validate()` and `validateArray()` set `addressesToValidate` themselves, so it cannot be given as an option.

### Ship

Creates, validates and cancels shipments, creates and cancels tags, and retrieves the results of asynchronous shipments, with `FedexShip`.

**`create()` buys a label, `createTag()` orders a pickup, and `cancel()` and `cancelTag()` void a shipment or a pickup: all four change real data in FedEx.** The package never repeats them, except when FedEx answers that the access token is not valid (nothing was processed then). If an exception is thrown after the request was sent (a timeout, a network failure, or a response that is not valid) the operation may have been done: check it in FedEx before trying again. `validate()` and `getAsyncResults()` change nothing.

`FedexShip::newCreateRequest()`, `newValidateRequest()`, `newCancelRequest()`, `newCreateTagRequest()`, `newCancelTagRequest()` and `newResultsRequest()` return the structure of a request with the fields that the API requires, named as in `schemas/ship.json`. Any other field of the schema can be added:

```php
use Mariopereira\Fedex\Api\FedexShip;

$ship = new FedexShip($auth);

$request = FedexShip::newCreateRequest();
$request['accountNumber']['value'] = '000000000';
$request['labelResponseOptions'] = 'URL_ONLY';
$request['requestedShipment']['shipper']['address'] = ['streetLines' => ['10 FedEx Parkway'], 'city' => 'Beverly Hills', 'countryCode' => 'US'];
$request['requestedShipment']['shipper']['contact']['phoneNumber'] = '1234567890';
$request['requestedShipment']['recipients'][] = [
    'address' => ['streetLines' => ['1 Main St'], 'city' => 'Memphis', 'countryCode' => 'US'],
    'contact' => ['phoneNumber' => '9012223333']
];
$request['requestedShipment']['pickupType'] = 'USE_SCHEDULED_PICKUP';
$request['requestedShipment']['serviceType'] = 'FEDEX_GROUND';
$request['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
$request['requestedShipment']['totalWeight'] = 1.5;
$request['requestedShipment']['shippingChargesPayment']['paymentType'] = 'SENDER';
$request['requestedShipment']['labelSpecification'] = ['labelStockType' => 'PAPER_4X6', 'imageType' => 'PDF'];
$request['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => 1.5]];

$result = $ship->create($request);
```

`validate()` takes the request of `newValidateRequest()`, which is the shipment without the account number and the label options. Note that FedEx's schema declares `totalWeight` of a validation as a whole number (an integer), and as a number with decimals when the shipment is created: a number with decimals is accepted in both (see [Request validation](#request-validation)), and `totalWeight` is optional, as FedEx's examples leave it out. `cancelByTrackingNumber()` cancels all the packages of a shipment:

```php
$result = $ship->cancelByTrackingNumber('000000000', '794953555571');
```

`cancel()` takes the request of `newCancelRequest()` instead, for the other fields of the schema (`emailShipment`, `senderCountryCode`, `deletionControl`, `version`).

A tag is a return label that FedEx delivers to your customer, with a pickup (FedEx Express and FedEx Ground). `createTag()` takes the request of `newCreateTagRequest()`, which has the fields of a shipment plus the pickup times (`pickupDetail`). `cancelTag()` cancels a tag and its pickup, as long as the courier has not collected the package; the id of the shipment goes in the path of the call, and the schema does not say what it is, so it is sent as given:

```php
use Mariopereira\Fedex\Api\FedexShip;

$ship = new FedexShip($auth);

$request = FedexShip::newCreateTagRequest();
$request['accountNumber']['value'] = '000000000';
$request['requestedShipment'] = [
    'shipper' => [
        'address' => ['streetLines' => ['1 Main St'], 'city' => 'Memphis', 'countryCode' => 'US'],
        'contact' => ['phoneNumber' => '9012223333']
    ],
    'recipients' => [[
        'address' => ['streetLines' => ['10 FedEx Parkway'], 'city' => 'Beverly Hills', 'countryCode' => 'US'],
        'contact' => ['phoneNumber' => '1234567890']
    ]],
    'pickupType' => 'USE_SCHEDULED_PICKUP',
    'serviceType' => 'FEDEX_GROUND',
    'packagingType' => 'YOUR_PACKAGING',
    'totalWeight' => 1.5,
    'shippingChargesPayment' => ['paymentType' => 'SENDER'],
    'labelSpecification' => ['labelStockType' => 'PAPER_4X6', 'imageType' => 'PDF'],
    'requestedPackageLineItems' => [['weight' => ['units' => 'LB', 'value' => 1.5]]],
    'pickupDetail' => ['readyPickupDateTime' => '2020-07-03T09:00:00Z', 'latestPickupDateTime' => '2020-07-03T17:00:00Z']
];

$result = $ship->createTag($request);

$cancel = FedexShip::newCancelTagRequest();
$cancel['accountNumber']['value'] = '000000000';
$cancel['serviceType'] = 'FEDEX_GROUND';
$cancel['completedTagDetail'] = ['confirmationNumber' => '275', 'location' => 'NQAA', 'dispatchDate' => '2019-08-03'];

$result = $ship->cancelTag('794953555571', $cancel);
```

When a shipment is created with asynchronous processing, FedEx answers with a job id, and the results are retrieved later with it. This does not create anything, so it can be repeated until the results are ready:

```php
$result = $ship->getAsyncResultsByJobId('000000000', '89sxxxxx233ae24ff31xxxxx');
```

The request is checked before it is sent, and a problem is reported with a `FedexShipException`:

* Fields set to `null`, and empty lists, are left out.
* A value of the wrong type, a value that is not in the list of allowed values and a text longer than the schema allows are rejected, and a field that is not in the schema is sent (see [Request validation](#request-validation)). `totalWeight` and `commercialInvoice`, which the schema requires, are optional, because the examples of FedEx leave them out.
* The required fields of the schema must be present. What the schema cannot express (for example the extra fields that some services or customs declarations need) is checked by FedEx, which answers with an error.
* The schema lists three fields of the shipment of a tag as required without ever defining them: `shipTimestamp`, `shippingLabelType` and `specialServicesRequested`. They are accepted with any value, and are not checked, so that they can be sent if FedEx asks for them.
* The id given to `cancelTag()` cannot be empty, `.` or `..`.
* The response is checked against the schema as well. A field that FedEx adds to the response is accepted, and an invalid response is reported with the response in the exception (`getData()`).

#### Labels

FedEx returns each label already in the format asked for in `labelSpecification.imageType` (`PDF`, `PNG`, `ZPLII` or `EPL2`), as text in the field `encodedLabel`. Nothing is converted: `FedexLabels` finds the documents of a response and decodes them to the bytes of that format. Ask for `'labelResponseOptions' => 'LABEL'` to get the label in the response (with `URL_ONLY`, FedEx returns a `url` instead).

```php
use Mariopereira\Fedex\Misc\FedexLabels;

$request['labelResponseOptions'] = 'LABEL';
$result = $ship->create($request);

foreach (FedexLabels::documents($result) as $document) {
    if ($document['hasContent']) {
        FedexLabels::save($document, '/labels/' . $document['trackingNumber'] . '.pdf');
    }
}
```

* `documents()` lists the documents of a create or async results response: `output.transactionShipments[].shipmentDocuments[]` (scope `shipment`, for example an invoice) and `output.transactionShipments[].pieceResponses[].packageDocuments[]` (scope `package`, the labels). Each one has `scope`, `transactionIndex`, `pieceIndex`, `trackingNumber`, `contentType`, `docType`, `copiesToPrint`, `contentKey`, `url`, `encodedLabel` and `hasContent`. What the response does not have is skipped. The response of `createTag()` has no documents in this path.
* `decode()` returns the bytes of a document, from an element of the response or of `documents()`. `save()` writes them to a file and returns the number of bytes. `save()` refuses to replace a file unless its third argument is `true`, and writes nothing when the document cannot be decoded.
* A document without `encodedLabel`, or with content that is not valid Base64, throws a `FedexShipException`. The schema does not say how `encodedLabel` is encoded: it is treated as standard Base64, and this has not been tried against FedEx.
* The format check is optional. `decode()` and `save()` take `$verifyFormat` (and a `$format`, when it is not the `docType` of the document): the bytes must then look like PDF (`%PDF-` in the first 1024 bytes), PNG (the PNG signature) or ZPL (`^XA` in the content), or a `FedexShipException` is thrown. `FedexLabels::isFormat()` does the same check on any bytes. EPL2 cannot be checked and is refused when the check is asked for.

### Basic tracking

`FedexBasicTracking` tracks shipments (Basic Integrated Visibility API, `schemas/track.json`). Tracking changes nothing in FedEx, so a request that failed with a timeout or a busy server can be retried (see [Retries](#retries)).

```php
use Mariopereira\Fedex\Api\FedexBasicTracking;

$tracking = new FedexBasicTracking($auth);

$result = $tracking->trackByNumber('128667043726');

// several numbers, up to 30 in a request; false asks for the latest status only, without the scans
$result = $tracking->trackByNumbers(['128667043726', '149331877648230'], false);

foreach ($result->output->completeTrackResults as $complete) {
    foreach ($complete->trackResults as $track) {
        echo $track->trackingNumberInfo->trackingNumber, ': ', $track->latestStatusDetail->description ?? '', "\n";
    }
}
```

The other operations take the request of the schema. Each one has a method that makes the empty request (the required fields, with nested objects already in place), which is filled in and sent:

| Operation | Request | Method |
| --- | --- | --- |
| Track by tracking number | `newTrackingNumbersRequest()` | `trackByTrackingNumbers()` |
| Track the other packages of a multiple piece shipment | `newAssociatedShipmentsRequest()` | `trackAssociatedShipments()` |
| Track by reference | `newReferencesRequest()` | `trackByReferences()` |
| Track by Transportation Control Number | `newTcnRequest()` | `trackByTcn()` |
| Track documents (signature proof of delivery, bill of lading, ...) | `newDocumentsRequest()` | `getTrackingDocuments()` |
| Send tracking notifications | `newNotificationRequest()` | `sendNotification()` |

```php
$request = FedexBasicTracking::newReferencesRequest();
$request['referencesInformation']['value'] = '56754674567546754';
$request['referencesInformation']['accountNumber'] = '123456789';
$request['referencesInformation']['shipDateBegin'] = '2019-02-13';
$request['referencesInformation']['shipDateEnd'] = '2019-02-13';

$result = $tracking->trackByReferences($request);
```

* The request is checked before it is sent, as in the other APIs: the fields, their types and the values of the enums (the carrier code and the associated type) are the ones of the schema, the required fields must be present, and a request by tracking number has from 1 to 30 numbers (see [Request validation](#request-validation) for how loose the types are, and for the keys that the schema does not define, which are sent). Nothing is sent when the request is not valid. What the schema cannot express (for example that a reference needs the account number, or the destination country and postal code) is checked by FedEx.
* The values that the schema only describes (the type of a reference, the type and the format of a document, the type of a notification, the role, the event types) are not checked: they are sent as given.
* The response is checked against the schema too, and an invalid response throws a `FedexBasicTrackingException` with the response in `getData()`. A field that FedEx adds is accepted.
* **`sendNotification()` sends emails.** It is never retried, because repeating it would send them twice, and an invalid response says that the notification may have been sent.
* The schema has a few defects, handled like this: the references request requires `shipDateEndDate` but its example, the other requests and the documentation write `shipDateEnd`, so `shipDateEnd` is the required one and `shipDateEndDate` is accepted as an optional field; the address requires a field, `addressVerificationId`, that it never defines, so it is optional and of any type; and two enums of the responses are defective (`DelayDetail.subType` lists "EARTHQUAKE etc", and `ServiceCommitMessage.type` has a stray quote in `USPS_DELIVERING"`), so those two fields are checked as text.
* Never called against the live FedEx API: the schema is the only reference.

### Consolidation

`FedexConsolidation` implements the Consolidation API (`schemas/consolidation.json`): creating, modifying, confirming, retrieving and deleting consolidations, and creating and deleting their shipments.

```php
use Mariopereira\Fedex\Api\FedexConsolidation;

$consolidation = new FedexConsolidation($auth);

$key = ['consolidationType' => 'INTERNATIONAL_ECONOMY_DISTRIBUTION', 'index' => '12345', 'shipDatestamp' => '2022-03-21'];

$result = $consolidation->retrieveByKey('123456789', $key);

$request = FedexConsolidation::newCreateRequest();
$request['accountNumber']['value'] = '123456789';
$request['requestedConsolidation'] = ['shipper' => ['address' => ['countryCode' => 'US']]]; // see the schema and its samples

$problems = FedexConsolidation::checkRequest('create', $request); // what does not match the schema, nothing is sent
$result = $consolidation->createConsolidation($request);
```

| Operation | Request | Method |
| --- | --- | --- |
| Create Consolidation | `newCreateRequest()` | `createConsolidation()` |
| Modify Consolidation | `newModifyRequest()` | `modifyConsolidation()` |
| Create Consolidation Shipment | `newCreateShipmentRequest()` | `createConsolidationShipment()` |
| Consolidation Shipment Results | `newShipmentResultsRequest()` | `getShipmentResults()` and `getShipmentResultsByJobId()` |
| Confirm Consolidations | `newConfirmRequest()` | `confirmConsolidation()` |
| Confirm Consolidation Results | `newConfirmResultsRequest()` | `getConfirmationResults()` and `getConfirmationResultsByJobId()` |
| Retrieve Consolidation | `newRetrieveRequest()` | `retrieveConsolidation()` and `retrieveByKey()` |
| Delete Consolidation Shipments | `newDeleteShipmentsRequest()` | `deleteConsolidationShipments()` |
| Delete Consolidation | `newDeleteRequest()` | `deleteConsolidation()` and `deleteByKey()` |

* **Creating, modifying, confirming and deleting change real data in FedEx, and creating a shipment of a consolidation buys its labels.** The package never repeats those requests, except when FedEx answers that the access token is not valid (nothing was processed then). If an exception is thrown after the request was sent (a timeout, a network failure, or a response that is not valid), the operation may have been done: check it in FedEx before trying again. Modify is treated like the others, although the same modification repeated would probably do no harm. Only the results of a shipment, the results of a confirmation and the retrieval change nothing, so only those can be retried (see [Retries](#retries)).
* **A request is checked at its top level only.** The schema contradicts its own sample payloads in the nested objects: only 3 of its 19 samples pass the whole of it (for example it requires `consolidationKey.date` and `type`, and the samples send `consolidationType` and `shipDatestamp`). So the required fields and the types of the fields are checked at the top level (a key that the schema does not define is sent, see [Request validation](#request-validation)), and each nested object or list is sent as it comes: FedEx checks the rest and answers with its errors. Nothing is sent when the top level is not valid. The 19 samples of the schema all pass this check.
* **`FedexConsolidation::checkRequest()` and `checkResponse()` check the whole of the schema** and return the problems, each with the path of the field, without stopping anything (nothing is sent). The operation is one of `create`, `modify`, `createShipment`, `shipmentResults`, `confirm`, `confirmResults`, `retrieve`, `deleteShipments` and `delete`. A problem that they report is not necessarily one that FedEx refuses, given what the samples of the schema are.
* The response is checked at its top level, and an invalid response throws a `FedexConsolidationException` with the response in `getData()`. A field that FedEx adds is accepted.
* The schema has a few defects, handled like this in the whole check: `RequestedConsolidationShipment` requires a field `recipient` that is never defined (the field is `recipients`, and it is not required), so `recipient` is accepted with any value; and `Label.type` and `ShippingDocument.type` have an enum of one value, the whole list joined in a text, so they are checked as text.
* Never called against the live FedEx API: the schema is the only reference.

### Estimated duty and tax

`FedexDutyAndTax` estimates the duties, the taxes and the fees of a shipment (Estimated Duty and Tax API, `schemas/dutiestaxes.json`). An estimate changes nothing in FedEx, so a request that failed with a timeout or a busy server can be retried (see [Retries](#retries)).

```php
use Mariopereira\Fedex\Api\FedexDutyAndTax;

$duties = new FedexDutyAndTax($auth);

$shoes = FedexDutyAndTax::newCommodity();
$shoes['description'] = 'Athletic Shoes';
$shoes['quantity'] = 1;
$shoes['unitPrice'] = ['amount' => 10.39, 'currencyCode' => 'USD'];

// from the countries and the commodities (the shipper and the recipient have a country code of two letters)
$result = $duties->estimateForCommodities('IN', 'US', [$shoes], ['shipmentPurpose' => 'GIFT']);

// or with the whole request, for example to give a postal code or a state
$request = FedexDutyAndTax::newRequest();
$request['shipper']['countryCode'] = 'IN';
$request['recipient'] = ['countryCode' => 'CA', 'stateOrProvinceCode' => 'AB'];
$request['commodities'][] = $shoes;

$result = $duties->estimate($request);

foreach ($result->output->edtDetails as $details) {
    echo $details->summary->totalLandedCost, ' ', $details->summary->currency, "\n";
}
```

* The request is checked before it is sent, as in the other APIs: the fields, their types, the lengths (the name of a commodity up to 256 characters, its description up to 512, the country of manufacture 2) and the values of `shipmentPurpose` (`DOCUMENTS`, `FOR_RESALE`, `NOT_FOR_RESALE`, `GIFT` or `RETURN`) are the ones of the schema, and the required fields must be present: the country of the shipper and of the recipient, and for each commodity its description, its quantity and its unit price with the amount and the currency code. Nothing is sent when the request is not valid. What the schema cannot express (the state or province code, which FedEx requires for Canada and Brazil) is checked by FedEx.
* The country codes of the shipper and the recipient must be two letters: the schema has no length, but its description says "the two-letter code used to identify a country".
* The schema asks for the header `x-content-type: application/json`, which is sent besides `Content-Type`.
* The response is checked against the schema too, and an invalid response throws a `FedexDutyAndTaxException` with the response in `getData()`. A field that FedEx adds is accepted.
* The examples of the schema (three requests and a response) are all accepted by these checks.
* Never called against the live FedEx API: the schema is the only reference.

### Service availability

`FedexServiceAvailability` asks FedEx what is available for a shipment: the services and their transit times, the services and the packaging options, and the special service options (Service Availability API, `schemas/service-availability.json`). The three operations change nothing in FedEx, so a request that failed with a timeout or a busy server can be retried (see [Retries](#retries)).

```php
use Mariopereira\Fedex\Api\FedexServiceAvailability;

$availability = new FedexServiceAvailability($auth);

// between two addresses, with one package (a weight of 10.5 pounds here)
$from = ['postalCode' => '38117', 'countryCode' => 'US'];
$to = ['postalCode' => '75201', 'countryCode' => 'US'];

$result = $availability->getTransitTimesBetween($from, $to, 10.5);

foreach ($result->output->transitTimes[0]->transitTimeDetails as $detail) {
    echo $detail->serviceType, ' ', $detail->serviceName, "\n";
}

// the same for the packaging options and for the special service options
$options = $availability->getPackageAndServiceOptionsBetween(['countryCode' => 'US'], ['countryCode' => 'CA'], ['FDXE']);
$special = $availability->getSpecialServiceOptionsBetween(['countryCode' => 'US'], ['countryCode' => 'CA'], 5, 'KG');

// or with the whole request, to use any field of the schema
$request = FedexServiceAvailability::newTransitTimesRequest();
$request['requestedShipment']['shipper']['address'] = $from;
$request['requestedShipment']['recipients'][] = ['address' => $to];
$request['requestedShipment']['packagingType'] = 'YOUR_PACKAGING';
$request['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => 10.5]];

$result = $availability->getTransitTimes($request);
```

* The request is checked before it is sent, as in the other APIs: the fields, their types and their allowed values are the ones of the schema, and the required fields must be present. Nothing is sent when the request is not valid.
* `newTransitTimesRequest()`, `newPackageAndServiceOptionsRequest()` and `newSpecialServiceOptionsRequest()` give the required fields. Note that the recipient is a list (`recipients`) in the transit times and in the packaging options, and one object (`recipient`) in the special service options. The schema requires nothing at the top of the transit times request, but every sample has `requestedShipment`, so the template has it.
* A number can be given as a number or as a text that is a number (`'10.5'`), because the samples of the schema send the weight and the customs value as text although the schema says number. This also applies to the weight and the money of a response.
* The methods `...Between()` take the addresses as arrays, with the fields of the address in the schema. `requestedShipment` and `carrierCodes` cannot be given in their `$options`: they are set by the method.
* The response is checked against the schema too, and an invalid response throws a `FedexServiceAvailabilityException` with the response in `getData()`. A field that FedEx adds is accepted.
* The samples of the schema (eight requests) are all accepted by these checks. The schema has no sample of a response.
* Never called against the live FedEx API: the schema is the only reference.

### Rates and transit times

`FedexRateTransitTimes` asks FedEx for the rates, and the transit times, of the services that are available for a shipment (Rates and Transit Times API, `schemas/rate.json`). A quote changes nothing in FedEx, so a request that failed with a timeout or a busy server can be retried (see [Retries](#retries)).

```php
use Mariopereira\Fedex\Api\FedexRateTransitTimes;

$rates = new FedexRateTransitTimes($auth);

// between two addresses, with one package (a weight of 10.5 pounds here)
$from = ['postalCode' => '38117', 'countryCode' => 'US'];
$to = ['postalCode' => '75201', 'countryCode' => 'US'];

$result = $rates->getRatesBetween('123456789', $from, $to, 10.5);

foreach ($result->output->rateReplyDetails as $reply) {
    echo $reply->serviceType, ' ', $reply->serviceName, "\n";
}

// or with the whole request, to use any field of the schema
$request = FedexRateTransitTimes::newRatesRequest();
$request['accountNumber']['value'] = '123456789';
$request['requestedShipment']['shipper']['address'] = $from;
$request['requestedShipment']['recipient']['address'] = $to;
$request['requestedShipment']['pickupType'] = 'DROPOFF_AT_FEDEX_LOCATION';
$request['requestedShipment']['requestedPackageLineItems'][] = ['weight' => ['units' => 'LB', 'value' => 10.5]];

$result = $rates->getRates($request);
```

* The request is checked before it is sent, as in the other APIs: the types and the allowed values of the fields are the ones of the schema, and the required fields must be present: in the shipment the shipper and the recipient (each with the postal code and the country code of its address), the pickup type and the packages (each with the weight and its unit). The account number and the customs commodities, which the schema requires, are optional, because the samples of FedEx leave them out. Nothing is sent when the request is not valid.
* The samples of the schema contradict the schema, and the checks follow the samples: a number can be given as a number or as a text that is a number (`'10.5'`), as the samples do for the weight; a postal code can be a text or an integer (`75063`); and a field that the schema does not define is accepted and sent, so FedEx decides about it. This also applies to the weight and the money of a response.
* All twenty two samples of the schema are accepted (a `null` is left out of the request, as for the other APIs): the one for Freight sends `groupPackageCount` as a text where the schema says integer, and the one for Intra Europe has no account number.
* `getRatesBetween()` takes the addresses as arrays, with the fields of the address in the schema. `accountNumber` and `requestedShipment` cannot be given in its `$options`: they are set by the method.
* The response is checked against the schema too, and an invalid response throws a `FedexRateTransitTimesException` with the response in `getData()`. A field that FedEx adds is accepted. The schema has no sample of a response.
* Never called against the live FedEx API: the schema is the only reference.

### Pickup

`FedexPickup` checks when FedEx can pick up a package, schedules a FedEx Express or Ground pickup, and cancels it (Pickup Request API, `schemas/pickup.json`).

```php
use Mariopereira\Fedex\Api\FedexPickup;

$pickup = new FedexPickup($auth);

// when can FedEx come? (this only asks, and can be repeated)
$result = $pickup->checkAvailabilityAt(['postalCode' => '38117', 'countryCode' => 'US'], ['FDXE', 'FDXG']);

// schedule a pickup
$request = FedexPickup::newCreateRequest();
$request['associatedAccountNumber']['value'] = '123456789';
$request['originDetail']['pickupLocation']['contact'] = ['personName' => 'John Taylor', 'phoneNumber' => '9013577890'];
$request['originDetail']['pickupLocation']['address'] = [
    'streetLines' => ['10 FedEx Parkway'], 'city' => 'Beverly Hills', 'stateOrProvinceCode' => 'CA', 'postalCode' => '90210', 'countryCode' => 'US',
];
$request['originDetail']['readyDateTimestamp'] = '2026-11-03T09:00:00Z';
$request['originDetail']['customerCloseTime'] = '17:00:00';
$request['carrierCode'] = 'FDXE';

$result = $pickup->createPickup($request);
$code = $result->output->pickupConfirmationCode;

// cancel it with the confirmation code
$pickup->cancelByConfirmation('123456789', $code, '2026-11-03');
```

* **`createPickup()` asks FedEx to send a driver, and `cancelPickup()` cancels one.** They are never retried (see [Retries](#retries)), except when FedEx answers 401 and the request is sent again with a fresh token, because repeating them could schedule or cancel twice. If one of them fails after it was sent (a timeout, a network failure, or a response that is not valid), the exception says so: the pickup may or may not have been scheduled, so check it before trying again. Checking the availability changes nothing, so it is retried like the other read-only operations.
* The request is checked before it is sent, as in the other APIs (see [Request validation](#request-validation)): the types and the allowed values of the fields are the ones of the schema, and the required fields must be present. Nothing is sent when the request is not valid. The required fields are the account number, the carrier (`FDXE` or `FDXG`) and the origin detail for a pickup; the pickup address, the carriers, the country relationship and the request type for the availability; and the confirmation code, the date and the account number to cancel.
* `shipmentAttributes` of the availability is an object in the schema, but two of its samples send a list with one object: both are accepted (the content of the list is not checked).
* The minimum length of the street lines is not checked, and the state of the pickup location and the postal code of the address of the availability are optional, although the schema requires them, because the minimum fields of an address vary among countries (the examples of FedEx leave them out for some countries): FedEx decides.
* The response is checked against the schema too, and an invalid response throws a `FedexPickupException` with the response in `getData()`. A field that FedEx adds is accepted. The schema has no sample of a response.
* The eight samples of the schema are all accepted by these checks.
* Never called against the live FedEx API: the schema is the only reference.

### Global trade

`FedexGlobalTrade` retrieves the regulatory documents that a shipment needs, and the advisories that apply to it, before it is shipped (Global Trade API, `schemas/global-trade.json`). It only asks FedEx and changes nothing, so a request that failed with a timeout or a busy server can be retried (see [Retries](#retries)).

```php
use Mariopereira\Fedex\Api\FedexGlobalTrade;

$globalTrade = new FedexGlobalTrade($auth);

// between two countries, with a carrier and any other field of the request
$result = $globalTrade->retrieveRegulatoryDetailsFor(['countryCode' => 'IN'], ['countryCode' => 'CA'], 'FDXE', [
    'customsClearanceDetail' => ['commodities' => [['harmonizedCode' => '580122']]],
]);

foreach ($result->output->userMessages as $message) {
    echo $message->text, "\n";
}

// or with the whole request
$request = FedexGlobalTrade::newRequest();
$request['originAddress']['countryCode'] = 'IN';
$request['destinationAddress']['countryCode'] = 'CA';
$request['carrierCode'] = 'FDXE';

$result = $globalTrade->retrieveRegulatoryDetails($request);
```

* The request is checked before it is sent, as in the other APIs (see [Request validation](#request-validation)): the types and the allowed values of the fields are the ones of the schema, and the required fields must be present: the country code of the origin and of the destination, and the carrier. Nothing is sent when the request is not valid.
* The value of a weight (of a commodity, and the total weight) is optional, although the schema requires it: most requests of FedEx's collection leave it out (they send `values`, a typo, which is sent as it is). The units of a weight are still required.
* The sample of the schema leaves the amount of the customs value blank (`''`), which the loose checks accept.
* `retrieveRegulatoryDetailsFor()` refuses `originAddress`, `destinationAddress` and `carrierCode` in its `$options`: they are set by the method.
* The response is checked against the schema too, and an invalid response throws a `FedexGlobalTradeException` with the response in `getData()`. A field that FedEx adds is accepted. The schema has no sample of a response.
* Never called against the live FedEx API: the schema is the only reference.

### End of day close

`FedexEndOfDayClose` closes the FedEx Ground shipments of the day, which gives the Ground Manifest to print and hand to the FedEx driver before the shipments are tendered, and reprints the reports of a close (Ship End of Day Close API, `schemas/close.json`).

```php
use Mariopereira\Fedex\Api\FedexEndOfDayClose;

$endOfDay = new FedexEndOfDayClose($auth);

// close the ground shipments of the day (this closes them in FedEx)
$result = $endOfDay->closeGroundFor('123456789', '2026-11-03', [
    'closeDocumentSpecification' => ['closeDocumentTypes' => ['MANIFEST']],
]);

foreach ($result->output->closeDocuments as $document) {
    echo $document->type, "\n";
}

// reprint the reports of the closes of a day (up to three days back)
$result = $endOfDay->reprintReportsFor('123456789', '2026-11-03');

// or with the whole request, for example to reprint by tracking number
$request = FedexEndOfDayClose::newReprintRequest();
$request['accountNumber']['value'] = '123456789';
$request['reprintOption'] = 'BY_TRACKING_NUMBER';
$request['trackingNumber'] = '794953555571';

$result = $endOfDay->reprintReports($request);
```

* **`closeGround()` closes the shipments in FedEx.** It is never retried (see [Retries](#retries)), except when FedEx answers 401 and the request is sent again with a fresh token. If it fails after it was sent (a timeout, a network failure, or a response that is not valid), the exception says so: the close may have been done, so check it before trying again. The reprint changes nothing, so it is retried like the other read-only operations.
* The close is a `PUT` and the reprint a `POST`, both to `/ship/v1/endofday/` (with the trailing slash of the schema).
* The request is checked before it is sent, as in the other APIs (see [Request validation](#request-validation)). The schema requires no field in either request, so only the types and the allowed values are checked (`closeReqType`, `groundServiceCategory`, `reprintOption`, the document types and formats); FedEx checks the rest. The templates and the `...For()` methods fill in the key inputs that the documentation lists: the request type (`GCDR` to close, `RGCDR` to reprint), the ground service category (`GROUND`), the account number and the close date.
* `closeGroundFor()` and `reprintReportsFor()` refuse, in their `$options`, the fields that they set themselves.
* The samples of the schema send the account number as an integer, which the loose checks accept. 14 of the 15 requests of FedEx's collection are accepted; the other one is a negative test (an empty `reprintOption`).
* The response is checked against the schema too, and an invalid response throws a `FedexEndOfDayCloseException` with the response in `getData()`. A field that FedEx adds is accepted. The schema has no sample of a response.
* Never called against the live FedEx API: the schema is the only reference.

### Harmonized code

`FedexHarmonizedCode` suggests the Harmonized System (HS) codes of commodities, from their description and their destination or origin country (HS Code Classification API, `schemas/harmonized-code.json`). The search is powered by artificial intelligence, and FedEx says that its results are only suggestions: validate them before using them in a shipment. It changes nothing, so a request that failed with a timeout or a busy server can be retried (see [Retries](#retries)).

```php
use Mariopereira\Fedex\Api\FedexHarmonizedCode;

$hsCodes = new FedexHarmonizedCode($auth);

// one commodity, with its destination countries and its origin country (either one is enough)
$result = $hsCodes->searchFor('Footwear with outer soles of rubber or plastics. Sports footwear', ['US', 'CA'], 'IN', [
    'material' => 'leather',
]);

foreach ($result->output->hsClassifications as $classification) {
    foreach ($classification->subheadingOptions as $option) {
        echo $classification->countryCode, ' ', $option->code, ' ', $option->confidenceScore, "\n";
    }
}

// or several commodities in one request
$request = FedexHarmonizedCode::newRequest();
$shoes = FedexHarmonizedCode::newClassification();
$shoes['commodityDescription'] = 'custom shoes';
$shoes['destinationCountryCodes'][] = 'GB';
$request['hsClassify'][] = $shoes;

$result = $hsCodes->searchHarmonizedCodes($request);
```

* The request is checked before it is sent, as in the other APIs (see [Request validation](#request-validation)): the types, the lengths (the name 256, the description 512, the country codes 2, the product id 100) and the required fields of the schema. Nothing is sent when the request is not valid.
* The schema requires both the destination countries and the origin country of a commodity, but the documentation says that either one is required, and the minimum examples of the schema send only one: so either one is enough, and a commodity without either is refused. At least one commodity is required (the schema does not say so, but the documentation requires the description and a country of a commodity).
* The schema asks for the header `x-content-type: application/json`, which is sent besides `Content-Type`.
* The response is checked against the schema too, and an invalid response throws a `FedexHarmonizedCodeException` with the response in `getData()`. A field that FedEx adds is accepted. The four request examples and the three response examples of the schema are all accepted by these checks.
* Never called against the live FedEx API: the schema is the only reference.

### Regulatory

`FedexRegulatory` stores the regulatory compliance data of products, so that shipments can reference it in `commodities[].regulatoryDetails[]`: CPSC certificates of compliance for imports into the United States, and the product identifiers of EU de minimis consignments (Regulatory API, `schemas/regulatory.json`).

```php
use Mariopereira\Fedex\Api\FedexRegulatory;

$regulatory = new FedexRegulatory($auth);

$profile = FedexRegulatory::newProfile();
$profile['regulationCode'] = 'EU_DE_MINIMIS';
$profile['productId'] = 'TSHIRT-L-001';
$profile['productIdType'] = 'SKU';
$profile['countryOfImport'] = 'FR';
$profile['details'][] = ['standardManufacturerProductId' => 'GTIN 00000006', 'merchantProductId' => '12345'];

// one profile (this stores it in FedEx)
$result = $regulatory->createProfile($profile);
echo $result->output->message, "\n";

// or up to 25 profiles in one request
$tShirtM = array_replace($profile, ['productId' => 'TSHIRT-M-001']);
$result = $regulatory->createProfiles([$profile, $tShirtM]);
```

* **This stores data in FedEx.** It is never retried (see [Retries](#retries)), except when FedEx answers 401 and the request is sent again with a fresh token. If it fails after it was sent (a timeout, a network failure, or a response that is not valid), the exception says so: the profiles may have been stored, so check it before trying again.
* The request is a JSON list of profiles. It is checked before it is sent, as in the other APIs (see [Request validation](#request-validation)): from 1 to 25 profiles, each one with its regulation (`CPSC` or `EU_DE_MINIMIS`), its product id and its type, its country of import and at least one detail, and the types, the lengths and the allowed values of the schema. What each regulation needs in its details is checked by FedEx. Nothing is sent when the request is not valid.
* Three integer fields have a length in the schema (`gtinUpcCode` 14, `phoneExtension` 5, `lotNumberQualifier` 1): it is not checked, FedEx checks it. A GTIN with leading zeros can be given as text (`'00012345678905'`). The contact's `phoneNumber` has no length limit, because an example of the schema sends an international number longer than the 15 characters of the schema, and `lotNumberQualifier` also takes the texts `'1'` and `'2'`.
* The response is checked against the schema too, and an invalid response throws a `FedexRegulatoryException` with the response in `getData()`. A field that FedEx adds is accepted. The six request examples of the schema are all accepted.
* Never called against the live FedEx API: the schema is the only reference.

### Trade documents upload

`FedexTradeDocuments` uploads the customs documents of an electronic trade documents (ETD) shipment, before or after it is shipped, and the letterhead and signature images used in the documents that FedEx generates (Trade Documents Upload API, `schemas/upload-documents.json`).

```php
use Mariopereira\Fedex\Api\FedexTradeDocuments;

$documents = new FedexTradeDocuments($auth);

// one document (this stores it in FedEx)
$document = FedexTradeDocuments::newDocument();
$document['workflowName'] = 'ETDPreshipment';
$document['name'] = 'invoice.pdf';
$document['contentType'] = 'application/pdf';
$document['meta']['shipDocumentType'] = 'COMMERCIAL_INVOICE';
$document['meta']['originCountryCode'] = 'US';
$document['meta']['destinationCountryCode'] = 'IN';

$result = $documents->uploadDocument($document, 'invoice.pdf', file_get_contents('invoice.pdf'), 'application/pdf');
echo $result->output->meta->docId, "\n";

// up to five documents of a shipment at once: the information, then each file as [file name, content, media type]
$result = $documents->uploadDocuments([
    'workflowName' => 'ETDPreshipment',
    'carrierCode' => 'FDXE',
    'originCountryCode' => 'US',
    'destinationCountryCode' => 'CA',
    'metaData' => [
        ['fileName' => 'invoice.pdf', 'contentType' => 'application/pdf', 'shipDocumentType' => 'COMMERCIAL_INVOICE'],
    ],
], [
    ['invoice.pdf', file_get_contents('invoice.pdf'), 'application/pdf'],
]);

// a letterhead or a signature
$image = FedexTradeDocuments::newImageDocument();
$image['referenceId'] = '1234';
$image['name'] = 'signature.png';
$image['contentType'] = 'image/png';
$image['meta']['imageType'] = 'SIGNATURE';
$image['meta']['imageIndex'] = 'IMAGE_1';

$result = $documents->uploadImage($image, ['workflowName' => 'LetterheadSignature'], 'signature.png', file_get_contents('signature.png'), 'image/png');
```

* **Every upload stores data in FedEx.** None is retried (see [Retries](#retries)), except when FedEx answers 401 and the request is sent again with a fresh token. If one fails after it was sent (a timeout, a network failure, or a response that is not valid), the exception says so: the files may have been stored, so check it before trying again.
* This API has its own hosts, `https://documentapitest.prod.fedex.com/sandbox/` in the sandbox and `https://documentapi.prod.fedex.com/` in production (from the documentation of the API); the token is the same as for the other APIs.
* `uploadDocument()`, `uploadDocuments()` and `uploadImage()` send a `multipart/form-data` request: the description as JSON parts, then the files as they are. `uploadEncodedImage()` and `uploadEncodedDocuments()` send a JSON request with the files encoded in Base64, named as in the schema.
* The upload of an image sends three parts, `document`, `rules` and `attachment`, as the schema describes them. The example of the documentation sends the description and the rules in one `document` part instead: it has not been confirmed which of the two FedEx expects.
* The JSON parts and the JSON requests are checked before they are sent, as in the other APIs (see [Request validation](#request-validation)). From one to five files can be uploaded at once, an empty file is refused, and a file name or a media type with a quote or a line break is refused by `FedexClient`. Nothing is sent when something is not valid.
* The examples of the multipart requests of the schema: the ones of the upload of a document are accepted; the ones of the upload of several documents are not valid JSON (a closing brace is missing, and one of them has a value without a key), and are accepted once fixed. The examples of the two JSON requests are accepted. The minimum sample payloads of the upload of several documents give the metadata of one file in separate entries, and are refused, as the schema says.
* The response is checked against the schema too, and an invalid response throws a `FedexTradeDocumentsException` with the response in `getData()`. A field that FedEx adds is accepted.
* Never called against the live FedEx API: the schema and the documentation are the only references.

### Open ship

`FedexOpenShip` implements the Open Ship API (`schemas/open-ship.json`): a shipment that stays open while its packages are added, modified or deleted over several requests, and is confirmed when it is complete, which generates its labels.

```php
use Mariopereira\Fedex\Api\FedexOpenShip;

$openShip = new FedexOpenShip($auth);

// create the open shipment (this changes data in FedEx)
$request = FedexOpenShip::newCreateRequest();
$request['accountNumber']['value'] = '123456789';
$request['index'] = 'Test1234'; // the id of the open shipment in the next requests
$request['requestedShipment'] = [/* shipper, recipients, service, packaging, payment, ...: see the schema */];
$openShip->createOpenShipment($request);

// add packages
$request = FedexOpenShip::newAddPackagesRequest();
$request['accountNumber']['value'] = '123456789';
$request['index'] = 'Test1234';
$request['requestedPackageLineItems'] = [['weight' => ['units' => 'LB', 'value' => 20]]];
$openShip->addPackages($request);

// confirm it: FedEx generates the labels
$request = FedexOpenShip::newConfirmRequest();
$request['accountNumber']['value'] = '123456789';
$request['index'] = 'Test1234';
$request['labelResponseOptions'] = 'URL_ONLY';
$request['labelSpecification'] = ['labelStockType' => 'PAPER_85X11_TOP_HALF_LABEL', 'imageType' => 'PDF'];
$result = $openShip->confirmOpenShipment($request);

// a shipment of many packages is confirmed asynchronously: its results come with the job id
if (isset($result->output->jobId)) {
    $result = $openShip->getResultsByJobId('123456789', $result->output->jobId, 'CONFIRM');
}

$problems = FedexOpenShip::checkRequest('confirm', $request); // what does not match the whole schema, nothing is sent
```

| Operation | Request | Method |
| --- | --- | --- |
| Create Open Shipment | `newCreateRequest()` | `createOpenShipment()` |
| Modify Open Shipment | `newModifyRequest()` | `modifyOpenShipment()` |
| Confirm Open Shipment | `newConfirmRequest()` | `confirmOpenShipment()` |
| Modify Open Shipment Packages | `newModifyPackageRequest()` | `modifyPackage()` |
| Add Open Shipment Packages | `newAddPackagesRequest()` | `addPackages()` |
| Delete Open Shipment Packages | `newDeletePackagesRequest()` | `deletePackages()` |
| Retrieve Open Shipment Packages | `newRetrievePackageRequest()` | `retrievePackage()` |
| Delete Open Shipment | `newDeleteRequest()` | `deleteOpenShipment()` and `deleteByIndex()` |
| Retrieve Open Shipment | `newRetrieveRequest()` | `retrieveOpenShipment()` and `retrieveByIndex()` |
| Get Open Shipment Results | `newResultsRequest()` | `getResults()` and `getResultsByJobId()` |

* **Creating, modifying, confirming and deleting an open shipment, and adding, modifying and deleting its packages, change real data in FedEx, and confirming generates the labels.** The package never repeats those requests, except when FedEx answers that the access token is not valid (nothing was processed then). If an exception is thrown after the request was sent (a timeout, a network failure, or a response that is not valid), the operation may have been done: check it in FedEx before trying again. Only the retrieval of an open shipment, the retrieval of a package and the results change nothing, so only those can be retried (see [Retries](#retries)).
* **A request is checked at its top level only**, as in [Consolidation](#consolidation). FedEx's own collection (`schemas/collections/open-ship.json`) contradicts the schema in the nested objects: checked against the whole of the schema, most of its creations and modifications would be refused (they leave out `requestedShipment.labelSpecification` or `customsClearanceDetail.commercialInvoice`, which the schema requires). So the required fields, the types and the allowed values of the top level are checked, and each nested object or list is sent as it comes: FedEx checks the rest and answers with its errors. Nothing is sent when the top level is not valid. 325 of the 327 requests of the collection that can be sent pass this check; the other two leave out `index`, which the schema requires (one of them is a negative test). The two sample payloads of the schema pass it too.
* **`FedexOpenShip::checkRequest()` and `checkResponse()` check the whole of the schema** and return the problems, each with the path of the field, without stopping anything (nothing is sent). The operation is one of `create`, `modify`, `confirm`, `modifyPackage`, `addPackages`, `deletePackages`, `retrievePackage`, `delete`, `retrieve` and `results`. A problem that they report is not necessarily one that FedEx refuses.
* The modification and the confirmation are sent to `/ship/v1/openshipments/`, with the trailing slash of the schema and of the documentation (the collection leaves it out).
* A confirmation of about 40 packages or more is processed asynchronously: the response gives a `jobId`, and `getResults()` or `getResultsByJobId()` (with `CONFIRM`, or `CREATE` and `MODIFY` for those operations) give the labels or the errors when they are ready.
* The response is checked at its top level, and an invalid response throws a `FedexOpenShipException` with the response in `getData()`. A field that FedEx adds is accepted.
* The schema has two defects, handled like this in the whole check: the retrieval of an open shipment requires a `trackingId` that it never defines, so it is accepted with any value and is not required; and the values of `alcoholRecipientType` are `LICENSEE` and `CONSUMER`, as in the Ship API (the schema has `CONSUMERstomer`).
* Never called against the live FedEx API: the schema, the documentation and the collection are the only references.

### Request validation

Every API checks its request before sending it, against the schema of FedEx, and sends nothing when the request is not valid. FedEx's own examples contradict its schemas in many places, so the checks of the requests are looser than the schemas, in the same way for every API:

* **Types are loose.** A text can be given a number or a boolean (`'phoneNumber' => 9013577890`, `'postalCode' => 75063`, `'excludeUnavailableLocations' => true`), a whole number can be a number with decimals or a text that is a number (`'width' => '10.5'`), a number can be a text that is a number (`'value' => '68.25'`), a whole number or a number can be left blank (`''`), and a boolean can be the text `'true'` or `'false'` in any case (`'TRUE'`), or `0` or `1`. The value is sent as it was given, never converted. Everything else is still refused: a text that is not a number (`'abc'`, `'1,5'`, `' 5'`), another text or number for a boolean (`'yes'`, `2`), a list where a value is expected, a value that is not in the list of allowed values, and a text that does not match the pattern or the length of the schema.
* **Keys that the schema does not define are sent.** FedEx decides about them (a misspelled option of a helper method is not caught either, FedEx answers with an error).
* **A few required fields are optional**, because the examples of FedEx leave them out: for a shipment `totalWeight`, `commercialInvoice`, `commodities`, `labelSpecification`, the `locationId` of a hold at location, the `phoneNumber` of a contact (except the one of the delivery on invoice) and the `allowedSpecialService` of a return email; `includeDetailedScans` of tracking by number; the `city` and the `postalCode` of an address to validate; `codDetail` and the `weight` of a package of the service availability; and `commodities` and `accountNumber` of a rates request. The other required fields of the schema must be present.
* The **responses** are checked as before: a field that FedEx adds is accepted, and a value of the wrong type is not. `FedexConsolidation::checkRequest()` also stays strict: it exists to report what does not match the schema.

When FedEx refuses a request that was accepted here, the exception is a `FedexClientException`, with the errors that FedEx gave.

### Parent/child credentials

```php
$auth = new FedexAuth(true, FedexUtils::GRANT_TYPE_PC_CREDENTIALS, 'CLIENT_ID', 'CLIENT_SECRET', 'CHILD_KEY', 'CHILD_SECRET');

// send the child key as child_Key (the default is child_key)
$auth->setChildKeyField(FedexAuth::CHILD_KEY_FIELD_SCHEMA);
```

### Timeouts

The API classes and `FedexClient` take a timeout and a connect timeout, in seconds. The defaults are 30 and 10, which are also the maximum: larger values are lowered to them.

`FedexAuth` has its own, for the token request:

```php
$auth->setTimeouts(20, 5);
```

### Retries

Nothing is retried by default, apart from a rejected token (401), which is refreshed once. To retry the transient failures, set a retry policy on the `FedexClient` and pass that client to the API class:

```php
$client = new FedexClient($auth, 'en_US', 30, 10);
$client->setRetryPolicy(3, 500, 5000, 30000); // total attempts, base delay, longest delay and longest Retry-After, in milliseconds

$locations = new FedexLocationsSearch($auth, 'en_US', 30, 10, $client);
```

`setRetryPolicy()` without arguments uses those same values (3 attempts, 500 ms, 5000 ms, 30000 ms). The attempts go from 1 (no retry) to 10.

* A request is retried when FedEx answers 429, 503 or 504, or when it could not be completed (network failure, timeout, ...). Any other failure is thrown at once.
* The wait before the attempt *n* is a random number of milliseconds between 0 and `min(longest delay, base delay * 2^(n-1))`. When the attempts are over, the last failure is thrown as usual.
* Only the operations that do not change data in FedEx are retried: postal code validation, locations search, address validation, the availability of a pickup (`FedexPickup::checkPickupAvailability()`), the regulatory details of `FedexGlobalTrade`, the reprint of `FedexEndOfDayClose`, the search of `FedexHarmonizedCode`, `FedexShip::validate()`, the async results of `FedexShip`, and the retrievals and the results of `FedexOpenShip`. `create()`, `cancel()`, `createTag()` and `cancelTag()` of `FedexShip`, `createPickup()` and `cancelPickup()` of `FedexPickup`, `closeGround()` of `FedexEndOfDayClose`, `createProfiles()` and `createProfile()` of `FedexRegulatory`, every upload of `FedexTradeDocuments`, and the seven operations of `FedexOpenShip` that change data, are never retried, because repeating them could create or cancel a shipment, a tag or a pickup twice, close the shipments again, store the profiles or the documents again, or change an open shipment twice.
* When the failure comes with a valid `Retry-After` header (a number of seconds, or an HTTP date), the wait is the longer of the header and the backoff, so that a retry is never sent before FedEx asked. If the header asks for more than the fourth argument of `setRetryPolicy()` (30 seconds by default), the retries stop and the failure is thrown, instead of holding the request for that long. With `0` the header is ignored and only the backoff is used. A header that is not valid is ignored. This is the behaviour of the HTTP standard (RFC 9110): the FedEx schemas that came with the package do not mention the header, so it has not been confirmed that FedEx sends it.
* The failure keeps the header, so that your code can use it too: `FedexClientException::getRetryAfter()` gives the seconds that FedEx asked to wait, or `null`.
* To retry your own calls to `FedexClient::request()`, pass `true` as its last argument, and only for requests that are safe to repeat.

The request for the access token has a retry of its own, set on `FedexAuth` with the same arguments and the same rules (the client's policy does not apply to it, and it does not retry the failures of the token request):

```php
$auth->setRetryPolicy(3, 500, 5000, 30000);
```

* It retries a token request that fails with 429, 503 or 504, or that could not be completed. It never retries a request that is refused with 401 (wrong credentials: asking again could lock the account) or any other failure, and it is not used when a valid token comes from the token cache.
* A failure of the token request inside an API request is retried by `FedexAuth` only, so the attempts of the two are not multiplied. Each attempt can take as long as the timeouts, so allow for them in `max_execution_time`.
* `FedexAuthException::getRetryAfter()` gives the `Retry-After` of the failure, like the exception of the client.

### Token cache

By default the access token lives only in the `FedexAuth` object, so every PHP request asks FedEx for a new one (it is valid for about an hour). To share it between requests, give the authorization a cache:

```php
use Mariopereira\Fedex\Auth\FileTokenCache;

$auth->setTokenCache(new FileTokenCache('/var/lib/myapp/fedex-tokens')); // the folder is created if it does not exist
```

* A token that is still valid is read from the cache instead of being requested, and a token that FedEx gives is stored in it. When FedEx rejects a cached token (401), it is removed and replaced by a new one.
* There is one entry for each environment (sandbox or production) and set of credentials. The key is a SHA-256 digest of them, and the client secret is never stored.
* **The files hold the access token, which FedEx accepts until it expires.** Keep the folder outside the web root and readable only by the user that runs PHP. `FileTokenCache` creates the folder with mode 0700 and the files with 0600 where the system has file modes; on Windows, protect the folder with its permissions.
* A problem of the cache never stops the authorization: an entry that cannot be read or is not valid is ignored, a token that cannot be stored is used anyway, and the token is requested from FedEx. The latest problem is available in `$auth->getLastCacheError()` (it never contains the token), so a folder that cannot be written does not go unnoticed.
* To keep the token somewhere else (APCu, Redis, a database), implement `FedexTokenCache`, which has three methods, `get($key)`, `set($key, $data, $ttl)` and `delete($key)`, and pass it to `setTokenCache()`. `set()` receives an array of text, numbers and `null` that `get()` has to return as it is.
* Two processes may request a token at the same time, because nothing is locked. Both tokens are valid, and the last one stored is kept.
* `$auth->setTokenCache(null)` stops the use of the cache.

## HTTP transport

Requests are sent with cURL by default (`CurlTransport`). To use another HTTP library, to log the requests, or to test your code without the network, implement `FedexTransport` and pass it to `FedexAuth` and `FedexClient`. Then pass that client to the API class.

```php
use Mariopereira\Fedex\Api\FedexPostalCodeValidation;
use Mariopereira\Fedex\Auth\FedexAuth;
use Mariopereira\Fedex\Http\FedexClient;
use Mariopereira\Fedex\Http\FedexTransport;
use Mariopereira\Fedex\Http\FedexTransportResponse;
use Mariopereira\Fedex\Misc\FedexUtils;

// A transport that answers with fixed data, so that nothing is sent over the network
class FakeTransport implements FedexTransport
{
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        int $connectTimeout
    ) : FedexTransportResponse {
        if (false !== strpos($url, '/oauth/token')) {
            return new FedexTransportResponse(200, '{"access_token":"TEST","token_type":"bearer","expires_in":3600}');
        }

        return new FedexTransportResponse(200, '{"transactionId":"T1","output":{"cleanedPostalCode":"38017"}}');
    }
}

$transport = new FakeTransport();
$auth = new FedexAuth(true, FedexUtils::GRANT_TYPE_CLIENT_CREDENTIALS, 'CLIENT_ID', 'CLIENT_SECRET', null, null, $transport);
$client = new FedexClient($auth, 'en_US', 30, 10, $transport);
$postal = new FedexPostalCodeValidation($auth, 'en_US', 30, 10, 1, 1, 1, $client);

$result = $postal->validate('FDXG', 'US', 'TN', '38017', '2030-01-15');

echo $result->output->cleanedPostalCode;
```

`send()` receives the HTTP method, the full url, the header lines (`'Name: value'`, including the `Authorization` header, so do not log them as they are), the body or `null`, and the timeouts in seconds. It returns a `FedexTransportResponse` with the status and the body, and optionally the response headers as a fourth argument (`new FedexTransportResponse(503, $body, null, ['Retry-After' => '30'])`, the names in any case, read with `getHeader()` and `getHeaders()`). A transport that does not give the headers works as before, and only what needs one (the `Retry-After` of a retry) is not available. `CurlTransport` gives them.

* An HTTP error status (401, 500, ...) is not a failure of the transport: return it with its body, and the package turns it into the exceptions described below.
* A request that could not be completed (network failure, timeout, ...) is returned as `new FedexTransportResponse(0, '', 'the error text')`. The transport must not throw.
* When a client is passed to an API class, the locale and timeouts given to the API class are not used: set them on the client.

## Errors

Every exception thrown by the package extends `FedexException`: `FedexAuthException`, `FedexClientException`, `FedexPostalCodeValidationException`, `FedexLocationsSearchException` and `FedexAddressValidationException`.

They provide `getStatusCode()`, `getTransactionId()`, `getCustomerTransactionId()`, `getErrors()`, `getErrorsAsString()` and `getData()`. The access token is removed from the data kept in the exceptions.

`getCode()` returns one of these constants:

| Constant | Value | Meaning |
| --- | --- | --- |
| `FedexException::EC_MISSING_PARAMETER` | 1 | A required parameter is missing |
| `FedexException::EC_BAD_PARAMETER` | 2 | A parameter has an invalid value |
| `FedexException::EC_TRANSPORT_ERROR` | 100 | The request failed: a network failure (the message starts with `Transport error:` and the status is 0) or an HTTP error status |
| `FedexException::EC_BAD_RESULT_RETURNED` | 101 | The response received is not valid |
| `FedexException::EC_UNABLE_TO_AUTHENTICATE` | 200 | Authentication failed: no access token could be obtained, or HTTP status 401 |

## Development

* `schemas/` holds the FedEx OpenAPI specifications that the code is checked against.
* Install with `composer install` and run the tests with `composer test`. They use PHPUnit 9.6, the last version that runs on PHP 7.4, so run them with the oldest PHP that the package supports. No test uses the network except the loopback tests, which talk to a throwaway server on `127.0.0.1` only, and no test calls FedEx.
* The API documentation is generated with PhpDocumentor (PHP 8 or newer): `vendor/bin/phpdoc -d src -t docs`.
* [CONTRIBUTING.md](CONTRIBUTING.md) has the rest: the conventions, the test groups (`slow` and `loopback`), the fast run, how to rebuild the documentation, and what a release contains.

## License

GPL-3.0-or-later. See the `LICENSE` file.
