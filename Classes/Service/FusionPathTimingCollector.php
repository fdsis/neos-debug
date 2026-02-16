<?php

declare(strict_types=1);

namespace Flowpack\Neos\Debug\Service;

use Doctrine\ORM\EntityManagerInterface;
use Flowpack\Neos\Debug\Domain\Model\Dto\FusionPathTiming;
use Flowpack\Neos\Debug\Logging\DebugStack;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope("singleton")]
class FusionPathTimingCollector
{
    #[Flow\Inject]
    protected EntityManagerInterface $entityManager;

    /**
     * @var array<int, array{fusionPath: string, startTime: float, startSqlCount: int}>
     */
    protected array $stack = [];

    /**
     * @var array<string, FusionPathTiming>
     */
    protected array $timings = [];

    /**
     * @var array<int, array{name: string, startTime: float, duration: float, depth: int, sqlQueries: int, fusionObjectType: string|null}>
     */
    protected array $traceEvents = [];

    public function start(string $fusionPath, ?string $fusionObjectType = null): void
    {
        $this->stack[] = [
            'fusionPath' => $fusionPath,
            'fusionObjectType' => $fusionObjectType,
            'startTime' => microtime(true) * 1000,
            'startSqlCount' => $this->getCurrentSqlQueryCount(),
        ];
    }

    public function stop(string $fusionPath): void
    {
        if (empty($this->stack)) {
            return;
        }

        $entry = array_pop($this->stack);

        if ($entry['fusionPath'] !== $fusionPath) {
            return;
        }

        $renderTime = (microtime(true) * 1000) - $entry['startTime'];
        $sqlQueryCount = $this->getCurrentSqlQueryCount() - $entry['startSqlCount'];

        if (!isset($this->timings[$fusionPath])) {
            $this->timings[$fusionPath] = new FusionPathTiming($fusionPath);
        }

        $this->timings[$fusionPath]->addMeasurement($renderTime, $sqlQueryCount);

        $this->traceEvents[] = [
            'name' => $fusionPath,
            'fusionObjectType' => $entry['fusionObjectType'],
            'startTime' => $entry['startTime'],
            'duration' => $renderTime,
            'depth' => count($this->stack),
            'sqlQueries' => $sqlQueryCount,
        ];
    }

    /**
     * @return FusionPathTiming[]
     */
    public function getTimings(): array
    {
        return array_values($this->timings);
    }

    /**
     * @return array<int, array{name: string, startTime: float, duration: float, depth: int, sqlQueries: int, fusionObjectType: string|null}>
     */
    public function getTraceEvents(): array
    {
        return $this->traceEvents;
    }

    protected function getCurrentSqlQueryCount(): int
    {
        $sqlLoggingStack = $this->entityManager->getConfiguration()->getSQLLogger();
        return $sqlLoggingStack instanceof DebugStack ? $sqlLoggingStack->queryCount : 0;
    }
}
