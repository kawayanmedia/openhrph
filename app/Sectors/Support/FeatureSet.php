<?php

namespace App\Sectors\Support;

/**
 * Named boolean capabilities declared by a sector profile.
 */
final class FeatureSet
{
    /**
     * @param  array<string, bool>  $features
     */
    public function __construct(private readonly array $features) {}

    public function enabled(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function disabled(string $feature): bool
    {
        return ! $this->enabled($feature);
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return $this->features;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->features);
    }

    /**
     * @return array<int, string>
     */
    public function enabledNames(): array
    {
        return array_keys(array_filter($this->features));
    }
}
