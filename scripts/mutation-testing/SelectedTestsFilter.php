<?php

declare(strict_types=1);

use PHPUnit\Framework\ErrorTestCase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Runner\PhptTestCase;

/**
 * Lets through only the tests listed in a file, one test id per line. See MutationTestingListener.
 */
final class SelectedTestsFilter extends RecursiveFilterIterator
{
    /**
     * @var array<string, true>
     */
    private $selected;

    /**
     * @param array<string, true>|string $selected the path of the list, or the list itself for the
     *                                             iterators PHP creates for the children
     */
    public function __construct(RecursiveIterator $iterator, $selected, ?TestSuite $suite = null)
    {
        parent::__construct($iterator);

        if (is_string($selected)) {
            $selected = array_fill_keys(file($selected, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [], true);
        }

        $this->selected = $selected;
    }

    public function accept(): bool
    {
        $test = $this->getInnerIterator()->current();

        // Suites always pass, and so does the placeholder PHPUnit creates when it cannot build a test.
        if ($test instanceof TestSuite || $test instanceof ErrorTestCase) {
            return true;
        }

        if (!$test instanceof TestCase && !$test instanceof PhptTestCase) {
            return false;
        }

        return isset($this->selected[MutationTestingListener::id($test)]);
    }

    public function getChildren(): self
    {
        return new self($this->getInnerIterator()->getChildren(), $this->selected);
    }
}
