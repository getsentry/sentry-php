<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * The deduplicated frames, stacks and samples shared by all Sentry profile
 * formats.
 *
 * @internal
 */
final class ProfileData
{
    /**
     * @var ProfileFrame[]
     */
    private $frames;

    /**
     * @var array<int, array<int, int>>
     */
    private $stacks;

    /**
     * @var ProfileSample[]
     */
    private $samples;

    /**
     * @param ProfileFrame[]              $frames
     * @param array<int, array<int, int>> $stacks
     * @param ProfileSample[]             $samples
     */
    public function __construct(array $frames, array $stacks, array $samples)
    {
        $this->frames = $frames;
        $this->stacks = $stacks;
        $this->samples = $samples;
    }

    /**
     * @return ProfileFrame[]
     */
    public function getFrames(): array
    {
        return $this->frames;
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function getStacks(): array
    {
        return $this->stacks;
    }

    /**
     * @return ProfileSample[]
     */
    public function getSamples(): array
    {
        return $this->samples;
    }
}
