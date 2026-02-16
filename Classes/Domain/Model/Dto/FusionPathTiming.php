<?php

declare(strict_types=1);

namespace Flowpack\Neos\Debug\Domain\Model\Dto;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
class FusionPathTiming implements \JsonSerializable
{
    protected int $count = 0;
    protected float $totalTime = 0.0;
    protected float $maxTime = 0.0;
    protected int $totalSqlQueries = 0;

    public function __construct(
        protected readonly string $fusionPath,
    ) {
    }

    public function addMeasurement(float $renderTime, int $sqlQueryCount): void
    {
        $this->count++;
        $this->totalTime += $renderTime;
        $this->totalSqlQueries += $sqlQueryCount;

        if ($renderTime > $this->maxTime) {
            $this->maxTime = $renderTime;
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'fusionPath' => $this->fusionPath,
            'count' => $this->count,
            'totalTime' => round($this->totalTime, 2),
            'maxTime' => round($this->maxTime, 2),
            'avgTime' => $this->count > 0 ? round($this->totalTime / $this->count, 2) : 0,
            'totalSqlQueries' => $this->totalSqlQueries,
        ];
    }
}
