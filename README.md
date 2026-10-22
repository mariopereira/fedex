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
* A field that is not in the schema, a value of the wrong type and a value that is not in the lists of the schema are rejected. In the schema the latitude and the longitude are strings (`searchByCoordinates()` converts numbers for you) and `excludeUnavailableLocations` is a string (`'true'` or `'false'`).
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
* A field that is not in the schema or a value of the wrong type is rejected.
* Every address needs its street lines (a list, none of them empty), a city and a postal code (a string or an integer). The state, if given, has at most two characters and the country, if given, two letters.
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

`validate()` takes the request of `newValidateRequest()`, which is the shipment without the account number and the label options. Note that FedEx's schema declares `totalWeight` of a validation as a whole number (an integer), and as a number with decimals when the shipment is created. `cancelByTrackingNumber()` cancels all the packages of a shipment:

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
* A field that is not in the schema, a value of the wrong type, a value that is not in the list of allowed values and a text longer than the schema allows are rejected.
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
$request['referencesInformation']['shipDateEndDate'] = '2019-02-13';

$result = $tracking->trackByReferences($request);
```

* The request is checked before it is sent, as in the other APIs: the fields, their types and the values of the enums (the carrier code and the associated type) are the ones of the schema, the required fields must be present, and a request by tracking number has from 1 to 30 numbers. Nothing is sent when the request is not valid. What the schema cannot express (for example that a reference needs the account number, or the destination country and postal code) is checked by FedEx.
* The values that the schema only describes (the type of a reference, the type and the format of a document, the type of a notification, the role, the event types) are not checked: they are sent as given.
* The response is checked against the schema too, and an invalid response throws a `FedexBasicTrackingException` with the response in `getData()`. A field that FedEx adds is accepted.
* **`sendNotification()` sends emails.** It is never retried, because repeating it would send them twice, and an invalid response says that the notification may have been sent.
* The schema has a few defects, handled like this: the references request requires `shipDateEndDate` but its example writes `shipDateEnd`, so both are accepted (and `shipDateEndDate` is still required); the address requires a field, `addressVerificationId`, that it never defines, so it is optional and of any type; and two enums of the responses are defective (`DelayDetail.subType` lists "EARTHQUAKE etc", and `ServiceCommitMessage.type` has a stray quote in `USPS_DELIVERING"`), so those two fields are checked as text.
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
* **A request is checked at its top level only.** The schema contradicts its own sample payloads in the nested objects: only 3 of its 19 samples pass the whole of it (for example it requires `consolidationKey.date` and `type`, and the samples send `consolidationType` and `shipDatestamp`). So the required fields, the names of the fields and their types are checked at the top level, and each nested object or list is sent as it comes: FedEx checks the rest and answers with its errors. Nothing is sent when the top level is not valid. The 19 samples of the schema all pass this check.
* **`FedexConsolidation::checkRequest()` and `checkResponse()` check the whole of the schema** and return the problems, each with the path of the field, without stopping anything (nothing is sent). The operation is one of `create`, `modify`, `createShipment`, `shipmentResults`, `confirm`, `confirmResults`, `retrieve`, `deleteShipments` and `delete`. A problem that they report is not necessarily one that FedEx refuses, given what the samples of the schema are.
* The response is checked at its top level, and an invalid response throws a `FedexConsolidationException` with the response in `getData()`. A field that FedEx adds is accepted.
* The schema has a few defects, handled like this in the whole check: `RequestedConsolidationShipment` requires a field `recipient` that is never defined (the field is `recipients`, and it is not required), so `recipient` is accepted with any value; and `Label.type` and `ShippingDocument.type` have an enum of one value, the whole list joined in a text, so they are checked as text.
* Never called against the live FedEx API: the schema is the only reference.

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
* Only the operations that do not change data in FedEx are retried: postal code validation, locations search, address validation, `FedexShip::validate()` and the async results of `FedexShip`. `create()`, `cancel()`, `createTag()` and `cancelTag()` are never retried, because repeating them could create or cancel a shipment twice.
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
