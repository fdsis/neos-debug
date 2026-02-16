<?php

declare(strict_types=1);

namespace Flowpack\Neos\Debug\Aspect;

/**
 * This file is part of the Flowpack.Neos.Debug package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\Neos\Debug\Service\FusionPathTimingCollector;
use Flowpack\Neos\Debug\Service\RenderTimer;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Utility\ObjectAccess;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class RuntimeTracingAspect
{
    #[Flow\Inject]
    protected RenderTimer $renderTimer;

    #[Flow\Inject]
    protected FusionPathTimingCollector $fusionPathTimingCollector;

    #[Flow\Pointcut("setting(Flowpack.Neos.Debug.enabled)")]
    public function debuggingActive(): void
    {
    }

    #[Flow\Before("method(Neos\Fusion\Core\Cache\RuntimeContentCache->enter()) && Flowpack\Neos\Debug\Aspect\RuntimeTracingAspect->debuggingActive")]
    public function onEnter(JoinPointInterface $joinPoint): void
    {
        $fusionPath = $joinPoint->getMethodArgument('fusionPath');
        $configuration = $joinPoint->getMethodArgument('configuration');

        $fusionObjectType = $this->resolveFusionObjectType($joinPoint, $fusionPath);
        $this->fusionPathTimingCollector->start($fusionPath, $fusionObjectType);

        $cacheMode = $configuration['mode'] ?? null;

        if (!$cacheMode) {
            return;
        }

        $this->renderTimer->start($fusionPath);
    }

    protected function resolveFusionObjectType(JoinPointInterface $joinPoint, string $fusionPath): ?string
    {
        try {
            $runtimeContentCache = $joinPoint->getProxy();
            $runtime = ObjectAccess::getProperty($runtimeContentCache, 'runtime', true);
            $runtimeConfiguration = ObjectAccess::getProperty($runtime, 'runtimeConfiguration', true);
            $fusionConfiguration = $runtimeConfiguration->forPath($fusionPath);
            return $fusionConfiguration['__objectType'] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    #[Flow\After("method(Neos\Fusion\Core\Cache\RuntimeContentCache->leave()) && Flowpack\Neos\Debug\Aspect\RuntimeTracingAspect->debuggingActive")]
    public function onLeave(JoinPointInterface $joinPoint): void
    {
        $evaluateContext = $joinPoint->getMethodArgument('evaluateContext');
        $fusionPath = $evaluateContext['fusionPath'] ?? null;

        if ($fusionPath !== null) {
            $this->fusionPathTimingCollector->stop($fusionPath);
        }
    }
}
