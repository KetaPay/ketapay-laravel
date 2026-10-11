# Contributing

This package is a thin Laravel layer over [ketapay-php](https://github.com/KetaPay/ketapay-php). API behaviour (requests, retries, errors, webhook signatures) belongs in ketapay-php and follows the KetaPay SDK standards; this package only wires it into Laravel. If a change would make sense outside Laravel, make it in ketapay-php.

## Layout

```
config/ketapay.php                          published config: keys, client options, webhook route
src/
├── KetaPayServiceProvider.php              binds the client (singleton), registers the webhook route
├── Config.php                              reads config/ketapay.php with clear errors (internal)
├── Facades/KetaPay.php                     KetaPay::escrows() forwards to the client's properties
├── Http/Middleware/VerifyWebhookSignature  verifies the raw body, puts the Event on the request
├── Http/Controllers/WebhookController      dedupes by event id, dispatches events, answers 200
├── Events/WebhookReceived.php              dispatched as the class and as ketapay.<type>
└── Testing/InteractsWithKetaPayWebhooks    ketapayEvent() and postKetaPayWebhook() for app tests
tests/Feature/                              Orchestra Testbench: client, webhooks, route options
```

## Supported versions

The Laravel versions that still get security fixes: 12 and 13, on PHP 8.2 or later (13 needs 8.3). CI runs each on its oldest and newest PHP. Composer refuses to install releases with open security advisories, which is every release of an end-of-life Laravel, so a Laravel version is dropped when its security support ends (a breaking change, released as such). Use only APIs present in every supported version; when a newer Laravel adds something useful, guard it.

The PHP library itself supports PHP 8.1: apps on older Laravel use it directly.

No composer.lock is committed: CI pins each Laravel version and resolves the rest. CI also runs every night against ketapay-php's latest code, so an SDK change that breaks the bridge is caught before it ships; a red nightly run is fixed in ketapay-php or here before the next SDK release.

## Working locally

ketapay-php comes from Packagist (`^0.1@beta`). To try the bridge against a local change in the SDK, add a path repository for `../ketapay-php` to composer.json on your machine only; the publish workflow refuses to release while one is there.

```sh
composer install
composer test           # Testbench feature tests
composer lint           # Larastan, level max
composer format         # PER-CS 2.0
```

To test against another Laravel: `composer require --no-update "illuminate/support:^11.0" "orchestra/testbench:^9.0"` (and the other `illuminate/*` packages), then `composer update`. Don't commit that.

A pre-push hook runs formatting, Larastan and the tests; `composer install` turns it on.

## Pull requests and releases

PR titles are conventional commits (`feat:`, `fix:` ...). Release Please keeps a release PR with the next version (`version.txt`) and changelog; merging it tags the release. Publishing is manual and owner-only: Actions > **Publish to Packagist** runs the full CI on the tag, then asks Packagist to read it. The Packagist package must not have GitHub's auto-update hook. ketapay/ketapay-php must be on Packagist first.

Switches (repo variables): `RELEASES_ENABLED`, `PACKAGIST_PUBLISH_ENABLED`. Secrets come from the Infisical project `ketapay-sdks`, shared by every KetaPay SDK.

## Secrets and security

No secret ever goes in this repo. GitHub secret scanning with push protection blocks known token formats, and the **Secrets** CI check runs gitleaks over the whole history with KetaPay's own patterns (`.gitleaks.toml`). Vulnerabilities are reported privately (see SECURITY.md). The publish job runs in the GitHub environment `publish`, so it waits for dudox's approval.
