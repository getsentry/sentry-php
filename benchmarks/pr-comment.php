<?php

declare(strict_types=1);

/*
 * Turns Tally's Markdown report into the body of the pull request comment: the headline and
 * a table with one row per benchmark, with the names from #[Name] instead of the case IDs.
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

/**
 * The name from #[Name] of a case like `Class::method` or `Class::method@data set`.
 */
function displayName(string $cell): string
{
    if (preg_match('/^`([^`@]+)::(\w+)(@.*)?`$/', $cell, $match) !== 1 || !method_exists($match[1], $match[2])) {
        return $cell;
    }

    $attributes = (new \ReflectionMethod($match[1], $match[2]))->getAttributes(Name::class);
    if ($attributes === []) {
        return $cell;
    }

    return $attributes[0]->newInstance()->name . ($match[3] ?? '');
}

// The table of all benchmarks is the one inside <details>, the last of the report. It has a
// row per metric, with the case only in the first one:
// | Benchmark | Metric | Expected | Measured | Change | Allowed | Status |
$benchmarks = [];
$inTable = false;
foreach ($lines as $line) {
    if (str_starts_with($line, '<details>')) {
        $inTable = true;
    }

    if (!$inTable || !str_starts_with($line, '|')) {
        continue;
    }

    [$benchmark, $metric, $expected, $measured, $change, $allowed] = array_map('trim', explode('|', trim($line, '|')));
    if ($benchmark === 'Benchmark' || str_starts_with($benchmark, '-')) {
        // the header and the separator below it
        continue;
    }

    if ($benchmark !== '') {
        $benchmarks[] = ['name' => displayName($benchmark), 'metrics' => []];
    }

    $benchmarks[array_key_last($benchmarks)]['metrics'][$metric] = [
        $change === '-' ? $measured : \sprintf('%s (%s)', $measured, $change),
        $allowed === '-' ? $expected : \sprintf('%s %s', $expected, $allowed),
    ];
}

echo $lines[0], "\n\n";
echo "| Benchmark | Instructions | Instructions expected | Cycles | Cycles expected | Memory | Memory expected |\n";
echo "|---|--:|--:|--:|--:|--:|--:|\n";

foreach ($benchmarks as $benchmark) {
    $cells = [$benchmark['name']];
    foreach (['instructions', 'cycles', 'memory'] as $metric) {
        // a benchmark that could not be measured has no metrics
        array_push($cells, ...($benchmark['metrics'][$metric] ?? ['-', '-']));
    }

    echo '| ', implode(' | ', $cells), " |\n";
}
