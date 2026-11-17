<?php

declare(strict_types=1);

/*
 * Turns Tally's Markdown report into the body of the pull request comment: the headline and
 * the table of all benchmarks, with the names from #[Name] instead of the case IDs.
 *
 * Usage: php benchmarks/pr-comment.php <report.md>
 */

namespace Sentry\Benchmarks;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Name.php';

foreach (glob(__DIR__ . '/*Bench.php') ?: [] as $file) {
    require_once $file;
}

$lines = file($argv[1], \FILE_IGNORE_NEW_LINES);
if ($lines === false || $lines === []) {
    fwrite(\STDERR, \sprintf("Cannot read the report from %s.\n", $argv[1]));

    exit(1);
}

echo $lines[0], "\n\n";

// The table of all benchmarks is the one inside <details>, the last of the report.
$inTable = false;
foreach ($lines as $line) {
    if (str_starts_with($line, '<details>')) {
        $inTable = true;
    }

    if (!$inTable || !str_starts_with($line, '|')) {
        continue;
    }

    // The status is the last column.
    $line = preg_replace('/[^|]*\|$/', '', $line);

    echo preg_replace_callback(
        '/^\| `([^`@]+)::(\w+)(@[^`]*)?` \|/',
        static function (array $match): string {
            $attributes = (new \ReflectionMethod($match[1], $match[2]))->getAttributes(Name::class);
            if ($attributes === []) {
                return $match[0];
            }

            return \sprintf('| %s%s |', $attributes[0]->newInstance()->name, $match[3] ?? '');
        },
        $line
    ), "\n";
}
