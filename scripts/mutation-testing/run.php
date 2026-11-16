<?php

declare(strict_types=1);

/**
 * Says which of the tests a change adds could be removed again without anything going unnoticed.
 *
 * A mutant is a copy of src/ with one small bug in it. A new test is reported when it fails for no
 * mutant at all, or when one other test fails for every mutant it fails for: a test that was there
 * before the change, or another new one. Infection writes the mutants, and the change to src/ itself,
 * undone one hunk at a time, adds a few more. This script runs the tests against them, because
 * Infection stops at the first failing test and cannot say which test noticed what.
 *
 * The result is a list of tests to read, not a list to delete: the mutants only cover the kinds of
 * bugs Infection knows. Nothing here fails a build. The summary goes to <out>/summary.md, and the
 * findings are printed as GitHub Actions warnings.
 *
 * Usage: php scripts/mutation-testing/run.php --base=<git ref> [--out=<dir>] [--infection=<path>]
 *            [--workers=<n>] [--budget=<seconds>]
 *
 * Needs Xdebug for the one coverage run and Infection, as a PHAR or on the PATH.
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;

ini_set('memory_limit', '-1');

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

$options = getopt('', ['base:', 'out:', 'infection:', 'workers:', 'budget:']);

if (!isset($options['base'])) {
    fwrite(\STDERR, "Usage: php scripts/mutation-testing/run.php --base=<git ref> [--out=<dir>] [--infection=<path>] [--workers=<n>] [--budget=<seconds>]\n");

    exit(2);
}

$started = microtime(true);
$out = $options['out'] ?? sys_get_temp_dir() . '/sentry-mutation-testing';
$workers = max(1, (int) ($options['workers'] ?? 4));
$infection = $options['infection'] ?? 'infection';

// Tests that are not judged when the time is up are reported as such, never as redundant.
define('DEADLINE', $started + (int) ($options['budget'] ?? 900));

sh(['rm', '-rf', $out], $root);
mkdir($out, 0777, true);
$out = (string) realpath($out);

/**
 * Runs a command and returns what it printed. A command that is still running when the time is up
 * is stopped.
 *
 * @param string[]              $command
 * @param array<string, string> $env
 */
function sh(array $command, string $cwd, array $env = [], bool $check = true): string
{
    $output = (string) tempnam(sys_get_temp_dir(), 'mt');
    $process = proc_open($command, [['file', '/dev/null', 'r'], ['file', $output, 'w'], ['file', $output, 'a']], $pipes, $cwd, $env + getenv());

    while (($status = proc_get_status($process))['running']) {
        if (microtime(true) > DEADLINE) {
            stop($process);
        }

        usleep(10000);
    }

    proc_close($process);
    $printed = (string) file_get_contents($output);
    unlink($output);

    if ($status['exitcode'] !== 0 && $check) {
        throw new RuntimeException(sprintf("Command failed: %s\n%s", implode(' ', $command), $printed));
    }

    return $printed;
}

/**
 * Kills a process and the processes it started: a PHPT test runs in a process of its own, which would
 * otherwise live on when PHPUnit is killed.
 *
 * @param resource|int $process
 */
function stop($process): void
{
    $pid = is_int($process) ? $process : proc_get_status($process)['pid'];

    foreach (array_filter(explode("\n", (string) shell_exec('pgrep -P ' . $pid))) as $child) {
        stop((int) $child);
    }

    posix_kill($pid, 9);
}

/**
 * Writes the summary and ends the run.
 *
 * @param string[] $warnings GitHub Actions workflow commands
 */
function finish(string $out, string $summary, array $warnings = []): void
{
    file_put_contents($out . '/summary.md', $summary . "\n");

    echo implode("\n", $warnings), "\n", $summary, "\n";

    exit(0);
}

/**
 * Writes phpunit.mt.xml next to phpunit.xml.dist: the same configuration with the listener that
 * selects and records tests, and with the "unit" suite cut down to the given test files.
 *
 * @param string[]|null $files null keeps the whole suite
 */
function writeConfig(string $dir, ?array $files): void
{
    $document = new DOMDocument();
    $document->load($dir . '/phpunit.xml.dist');
    $phpunit = $document->documentElement;

    if ($files !== null) {
        $suite = $document->createElement('testsuite');
        $suite->setAttribute('name', 'unit');

        foreach ($files as $file) {
            $suite->appendChild($document->createElement('file', $dir . '/' . $file));
        }

        $suites = $document->createElement('testsuites');
        $suites->appendChild($suite);
        $phpunit->replaceChild($suites, $phpunit->getElementsByTagName('testsuites')->item(0));
    }

    $listener = $document->createElement('listener');
    $listener->setAttribute('class', 'MutationTestingListener');
    $listener->setAttribute('file', __DIR__ . '/MutationTestingListener.php');
    $phpunit->appendChild($document->createElement('listeners'))->appendChild($listener);

    $document->save($dir . '/phpunit.mt.xml');
}

/**
 * The command that runs the "unit" suite of phpunit.mt.xml.
 *
 * @param string[] $arguments
 *
 * @return string[]
 */
function phpunit(array $arguments): array
{
    return array_merge([\PHP_BINARY, '-d', 'memory_limit=-1', 'vendor/bin/phpunit', '-c', 'phpunit.mt.xml', '--testsuite', 'unit'], $arguments);
}

/**
 * Every test of the "unit" suite of the checkout in $dir.
 *
 * @return array<int, array<string, mixed>>
 */
function listTests(string $dir): array
{
    writeConfig($dir, null);
    $output = sh(phpunit(['--no-coverage']), $dir, ['MT_LIST' => $dir . '/mt-list.jsonl', 'XDEBUG_MODE' => 'off'], false);

    if (!is_file($dir . '/mt-list.jsonl')) {
        throw new RuntimeException("PHPUnit could not list the tests in {$dir}:\n" . $output);
    }

    return array_map(static function (string $line): array {
        return json_decode($line, true);
    }, file($dir . '/mt-list.jsonl', \FILE_IGNORE_NEW_LINES));
}

/**
 * The tests that are in $head and not in $base.
 *
 * A data set with a name is new when the name is. Data sets without a name are numbered, so a set
 * that is inserted renumbers the ones after it: as many sets count as new as the method gained, and
 * these are the ones whose data the base does not have, or else the last ones.
 *
 * @param array<int, array<string, mixed>> $base
 * @param array<int, array<string, mixed>> $head
 *
 * @return array<int, array<string, mixed>>
 */
function newTests(array $base, array $head): array
{
    $known = [];
    $hashes = [];
    $numbered = [];

    foreach ($base as $test) {
        $method = $test['method'] ?? $test['id'];
        $known[$method][is_string($test['dataSet'] ?? null) ? $test['dataSet'] : ''] = true;

        if (is_int($test['dataSet'] ?? null)) {
            $hashes[$method][$test['dataHash']] = ($hashes[$method][$test['dataHash']] ?? 0) + 1;
            $numbered[$method] = ($numbered[$method] ?? 0) + 1;
        }
    }

    $new = [];
    $candidates = [];

    foreach ($head as $index => $test) {
        $method = $test['method'] ?? $test['id'];

        if (!isset($known[$method])) {
            $new[$index] = $test;
        } elseif (is_string($test['dataSet'] ?? null)) {
            if (!isset($known[$method][$test['dataSet']])) {
                $new[$index] = $test;
            }
        } elseif (is_int($test['dataSet'] ?? null)) {
            $numbered[$method] = ($numbered[$method] ?? 0) - 1;

            if (($hashes[$method][$test['dataHash']] ?? 0) > 0) {
                --$hashes[$method][$test['dataHash']];
                $candidates[$method]['known'][] = $index;
            } else {
                $candidates[$method]['unknown'][] = $index;
            }
        }
    }

    foreach ($candidates as $method => $sets) {
        // Negative when the method has more numbered sets in $head than in $base.
        $gained = -$numbered[$method];
        $ordered = array_merge($sets['known'] ?? [], $sets['unknown'] ?? []);

        foreach ($gained > 0 ? array_slice($ordered, -$gained) : [] as $index) {
            $new[$index] = $head[$index];
        }
    }

    ksort($new);

    return array_values($new);
}

/**
 * What MutationTestingListener recorded about a run.
 *
 * @return array{started: bool, status: array<string, string>, time: array<string, float>, inFlight: string|null, broken: bool}
 */
function readLog(string $path): array
{
    $result = ['started' => false, 'status' => [], 'time' => [], 'inFlight' => null, 'broken' => false];

    foreach (is_file($path) ? file($path, \FILE_IGNORE_NEW_LINES) : [] as $line) {
        $fields = explode("\t", $line);

        if ($fields[0] === 'B') {
            $result['started'] = true;
        } elseif ($fields[0] === 'S') {
            $result['inFlight'] = $fields[1];
        } elseif ($fields[0] === 'R') {
            $result['status'][$fields[1]] = $fields[2];
            $result['time'][$fields[1]] = (float) $fields[3];
            $result['inFlight'] = null;
        } elseif ($fields[0] === 'X') {
            $result['broken'] = true;
        }
    }

    return $result;
}

/**
 * Runs PHPUnit jobs in the worker checkouts, as many at a time as there are workers.
 *
 * A job is ['mutant' => index of the mutant, 'ids' => ids of the tests to run against it]. $onResult
 * gets the job and what readLog() says about its run; it returns false to stop everything that is
 * still running or waiting, or more jobs to run.
 *
 * @param array<int, array<string, mixed>> $jobs
 * @param array<string, mixed>             $context
 */
function runJobs(array $jobs, array $context, callable $onResult): void
{
    $free = range(0, $context['workers'] - 1);
    $running = [];

    $end = static function (array $run) use ($context): array {
        if (proc_get_status($run['process'])['running']) {
            stop($run['process']);
        }

        proc_close($run['process']);

        // Take the mutant out of the worker's checkout again.
        $file = $context['mutants'][$run['job']['mutant']]['file'];
        copy($context['root'] . '/' . $file, $run['dir'] . '/' . $file);

        return readLog($run['dir'] . '/mt-log.txt');
    };

    while ($jobs !== [] || $running !== []) {
        while ($jobs !== [] && $free !== []) {
            $job = array_shift($jobs);
            $dir = $context['out'] . '/w' . array_shift($free);
            $mutant = $context['mutants'][$job['mutant']];
            $files = [];
            $time = 0.0;

            foreach ($job['ids'] as $id) {
                $files[$context['file'][$id]] = true;
                $time += $context['time'][$id];
            }

            writeConfig($dir, array_keys($files));
            file_put_contents($dir . '/mt-only.txt', implode("\n", $job['ids']));
            file_put_contents($dir . '/mt-log.txt', '');
            file_put_contents($dir . '/' . $mutant['file'], $mutant['code']);

            $running[] = [
                'job' => $job,
                'dir' => $dir,
                // The times were measured with coverage on, which is slower than these runs.
                'until' => microtime(true) + 10 + 3 * $time,
                'process' => proc_open(
                    phpunit(['--no-coverage']),
                    [['file', '/dev/null', 'r'], ['file', $dir . '/mt-output.txt', 'w'], ['file', $dir . '/mt-output.txt', 'a']],
                    $pipes,
                    $dir,
                    ['XDEBUG_MODE' => 'off', 'MT_ONLY' => $dir . '/mt-only.txt', 'MT_LOG' => $dir . '/mt-log.txt'] + getenv()
                ),
            ];
        }

        foreach ($running as $key => $run) {
            if (proc_get_status($run['process'])['running'] && microtime(true) < $run['until']) {
                continue;
            }

            $result = $end($run);
            $free[] = (int) substr($run['dir'], strlen($context['out'] . '/w'));
            unset($running[$key]);

            $more = $onResult($run['job'], $result);

            if ($more === false) {
                array_map($end, $running);

                return;
            }

            if (is_array($more)) {
                $jobs = array_merge($jobs, $more);
            }
        }

        if (microtime(true) > DEADLINE) {
            array_map($end, $running);

            return;
        }

        usleep(2000);
    }
}

/**
 * The tests of a job that noticed its mutant, and the tests that did not get their turn because the
 * process died or hung in the test before them.
 *
 * @param array<string, mixed> $job
 * @param array<string, mixed> $result
 *
 * @return array{0: string[], 1: string[]}
 */
function noticedIn(array $job, array $result): array
{
    if (!$result['started']) {
        // The mutant breaks PHPUnit before the first test.
        return [$job['ids'], []];
    }

    $failed = array_keys(array_intersect($result['status'], ['F', 'E']));
    $missing = array_values(array_diff($job['ids'], array_keys($result['status'])));

    if ($result['inFlight'] !== null) {
        return [array_merge($failed, [$result['inFlight']]), array_values(array_diff($missing, [$result['inFlight']]))];
    }

    // When a data provider threw, PHPUnit could not build the tests that are missing.
    return [$result['broken'] ? array_merge($failed, $missing) : $failed, []];
}

/**
 * The first of $candidates that notices every one of $noticed, the mutants a new test notices.
 *
 * Every mutant is run against the candidates that are left, and a candidate that passes is out. The
 * one that is left at the end has to notice every mutant a second time, so a test that fails now and
 * then by itself does not make a new test look redundant.
 *
 * @param int[]                $noticed
 * @param string[]             $candidates
 * @param array<string, mixed> $context
 */
function subsumer(array $noticed, array $candidates, array $context): ?string
{
    foreach ([1, 2] as $round) {
        $jobs = [];

        foreach ($noticed as $mutant) {
            $jobs[] = ['mutant' => $mutant, 'ids' => $candidates];
        }

        runJobs($jobs, $context, static function (array $job, array $result) use (&$candidates): ?bool {
            $candidates = array_values(array_intersect($candidates, noticedIn($job, $result)[0]));

            return $candidates === [] ? false : null;
        });

        if ($candidates === [] || microtime(true) > DEADLINE) {
            return null;
        }

        $candidates = [$candidates[0]];
    }

    return $candidates[0];
}

/**
 * "FooTest::testBar" for "Sentry\Tests\FooTest::testBar", as Markdown code.
 */
function name(string $id): string
{
    return '`' . str_replace('`', "'", preg_replace('/^Sentry\\\\Tests\\\\/', '', $id)) . '`';
}

$summary = "## New tests under mutation testing\n\n";

// --- 1. Which tests are new? -------------------------------------------------------------------

if (trim(sh(['git', 'diff', '--name-only', '--diff-filter=AMR', $options['base'], '--', 'tests'], $root)) === '') {
    finish($out, $summary . 'This change adds no test.');
}

// Every run happens in a copy of the checkout: w0, w1, ... for the workers, and one of the base.
$entries = array_diff(scandir($root), ['.', '..', '.git', explode('/', substr($out, strlen($root) + 1))[0]]);
$copy = \PHP_OS_FAMILY === 'Darwin' ? ['cp', '-cR'] : ['cp', '-a'];

mkdir($out . '/w0');
sh(array_merge($copy, $entries, [$out . '/w0']), $root);
// Not "git archive": it leaves out the tests, which .gitattributes keeps out of the releases.
sh(['git', 'read-tree', $options['base']], $root, ['GIT_INDEX_FILE' => $out . '/base.index']);
sh(['git', 'checkout-index', '--all', '--prefix=' . $out . '/base/'], $root, ['GIT_INDEX_FILE' => $out . '/base.index']);
sh(array_merge($copy, ['vendor', $out . '/base']), $root);

$tests = listTests($out . '/w0');
$new = newTests(listTests($out . '/base'), $tests);

if ($new === []) {
    finish($out, $summary . 'This change adds no test.');
}

for ($worker = 1; $worker < $workers; ++$worker) {
    mkdir($out . '/w' . $worker);
    sh(array_merge($copy, $entries, [$out . '/w' . $worker]), $root);
}

// --- 2. One run of the whole suite: is it green, and which test runs which line? ----------------

fwrite(\STDERR, sprintf("%d new tests. Running the suite with coverage.\n", count($new)));

mkdir($out . '/coverage');
writeConfig($out . '/w0', null);
sh(
    phpunit(['--coverage-php', $out . '/coverage.php', '--coverage-xml', $out . '/coverage/coverage-xml', '--log-junit', $out . '/coverage/junit.xml']),
    $out . '/w0',
    ['XDEBUG_MODE' => 'coverage', 'MT_LOG' => $out . '/baseline.txt'],
    false
);

$baseline = readLog($out . '/baseline.txt');

if (!is_file($out . '/coverage.php')) {
    finish($out, $summary . sprintf(
        'The test suite did not finish with coverage on PHP %s, so nothing was measured.%s',
        \PHP_VERSION,
        $baseline['inFlight'] === null ? '' : ' It stopped in ' . name($baseline['inFlight']) . '.'
    ));
}

// A test that fails without any mutant can tell nothing about one. It is left out.
$failing = array_keys(array_intersect($baseline['status'], ['F', 'E']));

if ($failing !== []) {
    $summary .= sprintf("%d tests fail here without any mutant and were left out, the first one is %s.\n\n", count($failing), name($failing[0]));
}

$context = [
    'root' => $root,
    'out' => $out,
    'workers' => $workers,
    'time' => $baseline['time'],
    'file' => array_column($tests, 'file', 'id'),
    'mutants' => [],
];

$isNew = [];
$unmeasured = [];

foreach ($new as $test) {
    if (($baseline['status'][$test['id']] ?? null) === 'P') {
        $isNew[$test['id']] = true;
    } else {
        $unmeasured[$test['id']] = ['F' => 'it fails', 'E' => 'it fails', 'O' => 'it is skipped, incomplete or risky here'][$baseline['status'][$test['id']] ?? ''] ?? 'it did not run';
    }
}

/** @var CodeCoverage $coverage */
$coverage = require $out . '/coverage.php';

// $newCover and $oldCover: source file => line => ids of the new and of the other tests that run it.
$newCover = [];
$oldCover = [];

foreach ($coverage->getData()->lineCoverage() as $file => $lines) {
    $file = substr($file, strlen($out . '/w0/'));

    foreach ($lines as $line => $ids) {
        foreach ($ids ?? [] as $id) {
            $id = strpos($id, $out . '/w0/') === 0 ? substr($id, strlen($out . '/w0/')) : str_replace(["\r\n", "\n", "\r", "\t"], ['\\n', '\\n', '\\n', ' '], $id);

            if (isset($isNew[$id])) {
                $newCover[$file][$line][] = $id;
            } elseif (($baseline['status'][$id] ?? null) === 'P') {
                $oldCover[$file][$line][] = $id;
            }
        }
    }
}

unset($coverage);

// --- 3. The mutants in the lines the new tests run ----------------------------------------------

$mutants = [];
$mutantsOf = array_fill_keys(array_keys($isNew), []);

if ($newCover !== []) {
    // Infection looks every test up in junit.xml by its class, which a PHPT test does not have.
    $junit = new DOMDocument();
    $junit->load($out . '/coverage/junit.xml');

    foreach ($junit->getElementsByTagName('testcase') as $case) {
        if (!$case->hasAttribute('class')) {
            $case->setAttribute('class', $case->getAttribute('name'));
            $case->setAttribute('file', $case->getAttribute('name'));
        }
    }

    $junit->save($out . '/coverage/junit.xml');

    file_put_contents($out . '/w0/infection.json5', json_encode([
        'source' => ['directories' => ['src']],
        'tmpDir' => $out . '/infection',
        'logs' => ['json' => $out . '/infection.json'],
        // Every caller of a method notices that it is no longer public, which tells no test from another.
        'mutators' => ['@default' => true, 'PublicVisibility' => false, 'ProtectedVisibility' => false],
    ]));

    sh(
        array_merge(is_file($infection) ? [\PHP_BINARY, '-d', 'memory_limit=-1', $infection] : [$infection], [
            'run',
            '--configuration=infection.json5',
            '--coverage=' . $out . '/coverage',
            '--filter=' . implode(',', array_keys($newCover)),
            '--skip-initial-tests',
            '--dry-run',
            '--log-verbosity=all',
            '--threads=' . $workers,
            '--no-progress',
            '--no-interaction',
        ]),
        $out . '/w0',
        ['XDEBUG_MODE' => 'off']
    );

    // $changes: the mutants, with the lines of the file each of them changes.
    $changes = [];

    foreach (json_decode((string) file_get_contents($out . '/infection.json'), true) as $status => $logged) {
        foreach ($status === 'stats' ? [] : $logged as $entry) {
            $file = substr($entry['mutator']['originalFilePath'], strlen($out . '/w0/'));
            $code = $entry['mutator']['mutatedSourceCode'];
            $before = file($root . '/' . $file, \FILE_IGNORE_NEW_LINES);
            $after = explode("\n", $code);

            // The changed lines are what is left between the lines both versions share at the top
            // and at the bottom.
            $top = 0;
            $bottom = 0;
            $shortest = min(count($before), count($after));

            while ($top < $shortest && $before[$top] === $after[$top]) {
                ++$top;
            }

            while ($bottom < $shortest - $top && $before[count($before) - 1 - $bottom] === $after[count($after) - 1 - $bottom]) {
                ++$bottom;
            }

            $changes[] = ['file' => $file, 'line' => $entry['mutator']['originalStartLine'], 'mutator' => $entry['mutator']['mutatorName'], 'code' => $code, 'from' => $top + 1, 'to' => count($before) - $bottom];
        }
    }

    // The change itself, undone one hunk at a time, as further mutants. Infection's mutants rarely
    // look like the bug a fix removes, so the test written for a fix would look redundant when it
    // is the only one that notices the fix being taken out again.
    $file = null;

    foreach (explode("\n", sh(['git', 'diff', '--unified=0', '--no-renames', '--diff-filter=M', $options['base'], '--', 'src'], $root)) as $line) {
        if (strpos($line, '+++ b/') === 0) {
            $file = substr($line, 6);
            $before = file($out . '/base/' . $file, \FILE_IGNORE_NEW_LINES);
            $after = file($root . '/' . $file, \FILE_IGNORE_NEW_LINES);
        } elseif ($file !== null && preg_match('/^@@ -(\d+)(,\d+)? \+(\d+)(,\d+)? @@/', $line, $hunk) === 1) {
            $removed = ($hunk[2] ?? '') === '' ? 1 : (int) substr($hunk[2], 1);
            $added = ($hunk[4] ?? '') === '' ? 1 : (int) substr($hunk[4], 1);
            // For a side without lines git names the line before the change.
            $old = array_slice($before, (int) $hunk[1] - ($removed === 0 ? 0 : 1), $removed);
            $start = (int) $hunk[3] - ($added === 0 ? 0 : 1);
            $code = implode("\n", array_merge(array_slice($after, 0, $start), $old, array_slice($after, $start + $added))) . "\n";

            $changes[] = ['file' => $file, 'line' => (int) $hunk[3], 'mutator' => 'the change of this pull request undone', 'code' => $code, 'from' => (int) $hunk[3], 'to' => (int) $hunk[3] + max(1, $added) - 1];
        }
    }

    $seen = [];

    foreach ($changes as $mutant) {
        $file = $mutant['file'];
        $mutant += ['new' => [], 'old' => []];

        for ($line = $mutant['from']; $line <= max($mutant['from'], $mutant['to']); ++$line) {
            $mutant['new'] += array_flip($newCover[$file][$line] ?? []);
            $mutant['old'] += array_flip($oldCover[$file][$line] ?? []);
        }

        // Two mutators can write the same code.
        if ($mutant['new'] === [] || isset($seen[$file . md5($mutant['code'])])) {
            continue;
        }

        $seen[$file . md5($mutant['code'])] = true;
        $mutant['new'] = array_keys($mutant['new']);
        $mutant['old'] = array_keys($mutant['old']);
        $mutants[] = $mutant;

        foreach ($mutant['new'] as $id) {
            $mutantsOf[$id][] = count($mutants) - 1;
        }
    }
}

unset($newCover, $oldCover);

$context['mutants'] = $mutants;

// --- 4. Which new test notices which mutant? ---------------------------------------------------

fwrite(\STDERR, sprintf("%d mutants in the lines the new tests run. Running the new tests against them.\n", count($mutants)));

// $noticedBy: mutant => ids of the new tests that fail for it. $noticed: the other way round.
$noticedBy = [];
$noticed = array_fill_keys(array_keys($isNew), []);
$jobs = [];

foreach ($mutants as $index => $mutant) {
    $jobs[] = ['mutant' => $index, 'ids' => $mutant['new']];
}

runJobs($jobs, $context, static function (array $job, array $result) use (&$noticedBy, &$noticed): ?array {
    [$failed, $again] = noticedIn($job, $result);

    foreach (array_intersect($failed, $job['ids']) as $id) {
        $noticedBy[$job['mutant']][$id] = true;
        $noticed[$id][] = $job['mutant'];
    }

    return $again === [] ? null : [['mutant' => $job['mutant'], 'ids' => $again]];
});

$complete = microtime(true) < DEADLINE;

// --- 5. Does one other test notice everything a new test notices? ------------------------------

fwrite(\STDERR, "Looking for tests that notice the same mutants.\n");

// The last test is judged first, so of two tests that do the same the first one stays. A test that
// is found redundant no longer stands in for the tests judged after it.
$verdicts = [];
$gone = [];

foreach (array_reverse(array_keys($isNew)) as $id) {
    if ($mutantsOf[$id] === []) {
        $unmeasured[$id] = 'it runs no line of `src/` that Infection can mutate';

        continue;
    }

    if (!$complete || microtime(true) > DEADLINE) {
        $unmeasured[$id] = 'the time ran out';

        continue;
    }

    $file = $context['file'][$id];
    $by = null;
    // The other tests that may notice all of its mutants: only those that notice, or at least run,
    // every one of them.
    $newTests = null;
    $oldTests = null;

    foreach ($noticed[$id] as $mutant) {
        $others = array_diff_key($noticedBy[$mutant], [$id => true], $gone);
        $newTests = $newTests === null ? $others : array_intersect_key($newTests, $others);
        $oldTests = $oldTests === null ? array_flip($mutants[$mutant]['old']) : array_intersect_key($oldTests, array_flip($mutants[$mutant]['old']));
    }

    if ($newTests !== null && $newTests !== []) {
        // Which new test notices which mutant is known from step 4.
        $by = array_key_first($newTests);

        foreach (array_keys($newTests) as $other) {
            if ($context['file'][$other] === $file) {
                $by = $other;

                break;
            }
        }
    } elseif ($oldTests !== null) {
        // The tests that were there before, one test file at a time: the file of the new test, then
        // the files next to it, then the others. Not more than 20 files, to bound the time.
        $files = [];

        foreach (array_keys($oldTests) as $other) {
            $files[$context['file'][$other]][] = $other;
        }

        uksort($files, static function (string $a, string $b) use ($file): int {
            return [$a !== $file, dirname($a) !== dirname($file), $a] <=> [$b !== $file, dirname($b) !== dirname($file), $b];
        });

        // The mutants the fewest tests run first: they are the ones a candidate most likely misses.
        $order = $noticed[$id];
        usort($order, static function (int $a, int $b) use ($mutants): int {
            return count($mutants[$a]['old']) <=> count($mutants[$b]['old']);
        });

        foreach (array_slice($files, 0, 20) as $candidates) {
            $by = subsumer($order, $candidates, $context);

            if ($by !== null || microtime(true) > DEADLINE) {
                break;
            }
        }
    }

    if (microtime(true) > DEADLINE) {
        $unmeasured[$id] = 'the time ran out';

        continue;
    }

    if ($noticed[$id] === [] || $by !== null) {
        $gone[$id] = true;
    }

    $verdicts[$id] = ['mutants' => count($mutantsOf[$id]), 'noticed' => count($noticed[$id]), 'by' => $by];
}

// --- 6. The report ------------------------------------------------------------------------------

$lines = array_column($tests, 'line', 'id');
$groups = ['Notice no mutant' => [], 'Notice only what one other test notices too' => []];

foreach (array_reverse($verdicts) as $id => $verdict) {
    // A stand-in that is redundant itself is replaced by the test that stands in for it.
    while ($verdict['by'] !== null && isset($gone[$verdict['by']])) {
        $verdict['by'] = $verdicts[$verdict['by']]['by'];
    }

    if ($verdict['noticed'] === 0) {
        $groups['Notice no mutant'][$id] = $verdict['mutants'] === 1
            ? 'the one mutant in the lines it runs does not make it fail'
            : sprintf('none of the %d mutants in the lines it runs makes it fail', $verdict['mutants']);
    } elseif ($verdict['by'] !== null) {
        $groups['Notice only what one other test notices too'][$id] = sprintf(
            '%s also noticed by %s%s',
            $verdict['noticed'] === 1 ? 'the one mutant it notices is' : sprintf('all %d mutants it notices are', $verdict['noticed']),
            name($verdict['by']),
            in_array($context['file'][$verdict['by']], [$context['file'][$id], $verdict['by']], true) ? '' : ' in `' . $context['file'][$verdict['by']] . '`'
        );
    }
}

$redundant = count(array_merge(...array_values($groups)));
$warnings = [];
$summary .= sprintf(
    "%d of the %d new tests notice nothing that another test does not notice too. %d mutants in the lines the new tests run, PHP %s, %d s.\n",
    $redundant,
    count($new),
    count($mutants),
    \PHP_VERSION,
    microtime(true) - $started
);

foreach (array_filter($groups) as $title => $group) {
    $summary .= sprintf("\n### %s (%d)\n\n", $title, count($group));

    // The whole list is in report.json. A comment with hundreds of lines is read by nobody.
    foreach (array_slice($group, 0, 30, true) as $id => $detail) {
        $summary .= sprintf("- %s: %s\n", name($id), $detail);
        $warnings[] = sprintf(
            '::warning file=%s,line=%d,title=This test notices nothing another test does not notice too::%s',
            $context['file'][$id],
            $lines[$id],
            str_replace(['%', "\n"], ['%25', '%0A'], preg_replace('/^Sentry\\\\Tests\\\\/', '', $id) . ': ' . str_replace('`', '', $detail))
        );
    }

    if (count($group) > 30) {
        $summary .= sprintf("- and %d more, see `report.json` in the artifact of this run\n", count($group) - 30);
    }
}

if ($unmeasured !== []) {
    $summary .= sprintf("\n<details>\n<summary>%d new tests were not measured</summary>\n\n", count($unmeasured));

    foreach (array_reverse($unmeasured) as $id => $reason) {
        $summary .= sprintf("- %s: %s\n", name($id), $reason);
    }

    $summary .= "\n</details>\n";
}

if ($redundant > 0) {
    $summary .= "\nA mutant is a copy of `src/` with one small bug in it. These tests are candidates to read next to the test that is named, "
        . 'not tests to delete unread: the mutants only cover the kinds of bugs Infection knows. A test for an empty, null or boundary '
        . "value, for a regex, or for something that must not happen can look redundant here although it is the only one that guards that case.\n";
}

file_put_contents($out . '/report.json', json_encode(['reported' => $groups, 'verdicts' => $verdicts, 'unmeasured' => $unmeasured, 'mutants' => array_map(static function (array $mutant): array {
    return array_diff_key($mutant, ['code' => true, 'new' => true, 'old' => true]);
}, $mutants)], \JSON_PRETTY_PRINT | \JSON_INVALID_UTF8_SUBSTITUTE));

finish($out, $summary, $warnings);
