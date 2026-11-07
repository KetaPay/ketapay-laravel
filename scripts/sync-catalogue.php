<?php

// Copies the Laravel test catalogue from the documentation repo (its source of truth) into tests/, with a
// GENERATED header and a hash of its contents, so a hand edit fails the coverage test.
// Usage: composer sync-catalogue [-- path/to/documentation/openapi/public]

declare(strict_types=1);

$dir = $argv[1] ?? __DIR__ . '/../../documentation/openapi/public';
$from = "$dir/SDK_LARAVEL_TEST_CATALOGUE.md";
$to = __DIR__ . '/../tests/test-catalogue.md';
if (!is_file($from)) {
    fwrite(STDERR, "Not found: $from\n");
    exit(1);
}
$body = (string) file_get_contents($from);
file_put_contents($to, "<!-- GENERATED: do not edit. Copied from documentation/openapi/public by `composer sync-catalogue`. -->\n"
    . '<!-- sha256: ' . hash('sha256', $body) . " -->\n\n" . $body);
echo "Copied $from -> tests/test-catalogue.md\nNow run: composer test\n";
