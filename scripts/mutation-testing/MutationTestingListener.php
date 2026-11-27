<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ErrorTestCase;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Framework\WarningTestCase;
use PHPUnit\Runner\Filter\Factory;
use PHPUnit\Runner\PhptTestCase;

require_once __DIR__ . '/SelectedTestsFilter.php';

/**
 * The part of scripts/mutation-testing/run.php that lives inside a PHPUnit run. It does nothing unless
 * one of these environment variables is set:
 *
 *   MT_LIST    Write every test of the suite to this file, one JSON object per line, and exit without
 *              running a test.
 *   MT_ONLY    Run only the tests whose ids are listed in this file, one per line.
 *   MT_LOG     Append the outcome of every test to this file. Every line is flushed at once, so when
 *              the process dies in a test the log still says which one was running:
 *                B                      the run started
 *                S <id>                 a test started
 *                R <id> <status> <s>    a test ended: P passed, F failed, E error, O anything else
 *                X                      PHPUnit could not build a test, e.g. a data provider threw
 *                Z                      the run ended
 *
 * A test id is "Class::method with data set #0", the id code coverage uses as well, or the path of a
 * PHPT file relative to the working directory.
 *
 * PHPUnit 9 deprecates TestListener in favour of its hooks, but a hook only gets the name of a test.
 * Listing the tests and selecting some of them needs the suite, which only a listener is given.
 */
final class MutationTestingListener implements TestListener
{
    /**
     * @var resource|null
     */
    private $log;

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @var string
     */
    private $status = 'P';

    public static function id(Test $test): string
    {
        if ($test instanceof PhptTestCase) {
            $root = getcwd() . '/';
            $name = $test->getName();

            return strpos($name, $root) === 0 ? substr($name, strlen($root)) : $name;
        }

        if ($test instanceof TestCase) {
            return str_replace(["\r\n", "\n", "\r", "\t"], ['\\n', '\\n', '\\n', ' '], get_class($test) . '::' . $test->getName());
        }

        return get_class($test);
    }

    public function startTestSuite(TestSuite $suite): void
    {
        if ($this->depth++ > 0) {
            return;
        }

        $list = getenv('MT_LIST');

        if (is_string($list) && $list !== '') {
            $this->writeList($suite, $list);

            exit(0);
        }

        $only = getenv('MT_ONLY');

        if (is_string($only) && $only !== '') {
            $factory = new Factory();
            $factory->addFilter(new ReflectionClass(SelectedTestsFilter::class), $only);
            $suite->injectFilter($factory);
        }

        $log = getenv('MT_LOG');

        if (is_string($log) && $log !== '') {
            $this->log = fopen($log, 'a');
            $this->write('B');
        }
    }

    public function endTestSuite(TestSuite $suite): void
    {
        if (--$this->depth === 0) {
            $this->write('Z');
        }
    }

    public function startTest(Test $test): void
    {
        $this->status = 'P';

        if (!$test instanceof ErrorTestCase && !$test instanceof WarningTestCase) {
            $this->write("S\t" . self::id($test));
        }
    }

    public function endTest(Test $test, float $time): void
    {
        if ($test instanceof ErrorTestCase) {
            $this->write('X');
        } elseif (!$test instanceof WarningTestCase) {
            $this->write(sprintf("R\t%s\t%s\t%.4F", self::id($test), $this->status, $time));
        }
    }

    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->status = 'E';
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        // A warning does not fail a run, so it does not count as noticing a mutant.
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        if ($this->status !== 'E') {
            $this->status = 'F';
        }
    }

    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $this->other();
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
        $this->other();
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->other();
    }

    private function other(): void
    {
        if ($this->status === 'P') {
            $this->status = 'O';
        }
    }

    private function write(string $line): void
    {
        if ($this->log !== null) {
            fwrite($this->log, $line . "\n");
            fflush($this->log);
        }
    }

    private function writeList(TestSuite $suite, string $path): void
    {
        $root = getcwd() . '/';
        $lines = '';

        foreach (new RecursiveIteratorIterator($suite->getIterator()) as $test) {
            if ($test instanceof PhptTestCase) {
                $lines .= json_encode(['id' => self::id($test), 'file' => self::id($test), 'line' => 1]) . "\n";

                continue;
            }

            if (!$test instanceof TestCase || $test instanceof ErrorTestCase || $test instanceof WarningTestCase) {
                continue;
            }

            $class = new ReflectionClass($test);
            $method = $class->getMethod($test->getName(false));
            $entry = [
                'id' => self::id($test),
                'method' => $class->getName() . '::' . $method->getName(),
                'file' => substr((string) $class->getFileName(), strlen($root)),
                // An inherited test is reported on the class that inherits it.
                'line' => $method->getFileName() === $class->getFileName() ? $method->getStartLine() : $class->getStartLine(),
            ];

            if ($test->usesDataProvider()) {
                $entry['dataSet'] = $test->dataName();
                // Tells an inserted data set from the ones that only moved down by one. The paths
                // differ between the two checkouts that are compared.
                $entry['dataHash'] = md5(str_replace($root, '', print_r($test->getProvidedData(), true)));
            }

            $lines .= json_encode($entry, \JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        }

        file_put_contents($path, $lines);
    }
}
