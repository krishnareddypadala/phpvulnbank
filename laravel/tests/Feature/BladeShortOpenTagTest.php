<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards against a deployment bug that local testing cannot catch.
 *
 * A literal `<?xml` (or any bare `<?`) inside a Blade template is parsed as a
 * PHP SHORT OPEN TAG when the view is compiled. On a host with
 * short_open_tag=Off it is harmless; on one with it On -- as the Debian
 * php:8.3-apache image used by docker-compose has -- the compiled view is a
 * fatal syntax error and the page returns 500.
 *
 * That asymmetry is exactly how this reached a deployed VM while every local
 * test passed. Scanning the source is environment-independent, so this test
 * fails on any machine rather than only on the unlucky ones.
 *
 * Not a lesson -- a build correctness check.
 */
class BladeShortOpenTagTest extends TestCase
{
    public function test_no_blade_template_contains_a_short_open_tag(): void
    {
        $offenders = [];

        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($dir as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            // Any `<?` that is not `<?php` and not `<?=` is a short open tag.
            if (preg_match_all('/<\?(?!php\b|=)/', $contents, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$match, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.$line;
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'Blade template(s) contain a bare `<?`, which compiles to a PHP short open tag',
            'and is a fatal syntax error where short_open_tag=On (the Docker image).',
            'Split it, e.g.  \'<\' + \'?xml ... ?\' + \'>\'  in JS.',
            'Offenders: '.implode(', ', $offenders),
        ]));
    }
}
