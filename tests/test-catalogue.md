<!-- GENERATED: do not edit. Copied from documentation/openapi/public by `composer sync-catalogue`. -->
<!-- sha256: 717532c5428feb0a2b0db6b960fd6865fc0a05ba377a5deb1e5f494370129171 -->

# KetaPay Laravel bridge test catalogue

Every scenario below must be tested in `ketapay-laravel`. The rules are the same as the [server catalogue](SDK_TEST_CATALOGUE.md): tag tests with the ID in square brackets, one row is one behaviour, add rows with the next free ID, and never delete or renumber a row. The bridge copies this file with `composer sync-catalogue`, and its tests fail when a row has no tagged test.

API behaviour (requests, retries, errors, signatures) is the SDK's and is covered by `ketapay-php`. These scenarios cover only what the bridge adds: configuration, the container binding and facade, logging, and the webhook route.

These tests run the bridge and its SDK in a Laravel app (Testbench), configured only through `config/ketapay.php`, against the staging API with sandbox keys. Staging can't reach a route inside a CI runner, so webhooks are the one part not delivered for real: a test reads the real event from `GET /v1/events`, signs it and posts it to the app's webhook route.

## LE2E: the bridge against the staging API

| ID | Must hold |
| --- | --- |
| LE2E-001 | The client built from `config/ketapay.php` creates a real checkout session through the facade, and the client injected from the container reads it back |
| LE2E-002 | A wrong secret key in the config surfaces the API's authentication error through the facade |
| LE2E-003 | The SDK logs real requests to the Laravel log channel set in the config, and the secret key never appears in the log |
| LE2E-004 | The real `escrow.funded` event reaches listeners as an `Escrow` with the funded escrow's id; delivering it again is acknowledged as a duplicate and dispatches nothing |
| LE2E-005 | After the seller delivers and the buyer releases through the facade with their customer tokens, the real `milestone.released` event is dispatched for that escrow |
| LE2E-006 | A listener that fails on a real event answers 500 so KetaPay retries, and the retry is dispatched rather than treated as a duplicate |
