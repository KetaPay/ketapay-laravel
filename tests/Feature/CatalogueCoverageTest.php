<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\Feature;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Every scenario in the Laravel test catalogue (tests/test-catalogue.md, synced from the documentation repo)
 * must have at least one test tagged with its ID, and no test may carry an ID the catalogue doesn't have.
 * Adding a scenario to the catalogue fails this test until it is covered here.
 */
final class CatalogueCoverageTest extends TestCase
{
    // IDs look like LE2E-004: letters or digits, a dash and three digits.
    private const ID = '[A-Z][A-Z0-9]{1,4}-\d{3}';

    private const FILE = __DIR__ . '/../test-catalogue.md';

    /** @return array<string, string> ID => "must hold" */
    private static function catalogue(): array
    {
        $ids = [];
        foreach (file(self::FILE, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\| (' . self::ID . ') \| (.+) \|$/', $line, $m)) {
                $ids[$m[1]] = $m[2];
            }
        }

        return $ids;
    }

    /** @return array<string, list<string>> ID => files tagging it */
    private static function tagged(): array
    {
        $tagged = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/..', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/\[(' . self::ID . ')\]/', (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $id) {
                $tagged[$id][] = $file->getFilename();
            }
        }

        return $tagged;
    }

    #[TestDox('reads the catalogue, unedited since it was synced')]
    public function testReadsCatalogue(): void
    {
        self::assertArrayHasKey('LE2E-001', self::catalogue());
        [$generated, $hash, , $body] = explode("\n", (string) file_get_contents(self::FILE), 4);
        self::assertStringContainsString('GENERATED', $generated);
        self::assertSame('<!-- sha256: ' . hash('sha256', $body) . ' -->', $hash, 'Edit the catalogue in the documentation repo, then run composer sync-catalogue');
    }

    #[TestDox('has a tagged test for every scenario in the catalogue')]
    public function testEveryScenarioTagged(): void
    {
        $live = array_filter(self::catalogue(), static fn(string $what): bool => !str_starts_with($what, 'retired'));
        $missing = array_diff_key($live, self::tagged());
        $list = implode('; ', array_map(static fn(string $id, string $what): string => "$id ($what)", array_keys($missing), $missing));
        self::assertSame([], array_keys($missing), "Write tests tagged with: $list");
    }

    #[TestDox('tags no test with an ID the catalogue does not have')]
    public function testNoUnknownTags(): void
    {
        $unknown = array_keys(array_diff_key(self::tagged(), self::catalogue()));
        self::assertSame([], $unknown, 'Not in the catalogue: ' . implode(', ', $unknown));
    }
}
