<?php

declare(strict_types=1);

namespace Flowpack\Neos\Debug\Service;

/**
 * This file is part of the Flowpack.Neos.Debug package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */


class XdebugPathMappingGenerator
{

    protected function buildProxyPath(): string
    {
        $context = FLOW_APPLICATION_CONTEXT;

        $parts = str_replace('/', '/SubContext', (string)$context);

        return FLOW_PATH_DATA . 'Temporary/'.$parts.'/Cache/Code/Flow_Object_Classes/';
    }

    public function generate(): void
    {
        $proxyCacheDir = $this->buildProxyPath();
        if (!is_dir($proxyCacheDir)) {
            return;
        }

        $lines = [];
        foreach (new \FilesystemIterator($proxyCacheDir, \FilesystemIterator::SKIP_DOTS) as $proxyFile) {
            if ($proxyFile->getExtension() !== 'php') {
                continue;
            }
            $content = file($proxyFile->getPathname(), FILE_IGNORE_NEW_LINES);
            // Last 2 lines: "# PathAndFilename: /abs/path" and "#"
            $pathLine = $content[count($content) - 2] ?? '';
            if (!str_starts_with($pathLine, '# PathAndFilename: ')) {
                continue;
            }
            $absoluteSourcePath = substr($pathLine, strlen('# PathAndFilename: '));
            if (!str_starts_with($absoluteSourcePath, FLOW_PATH_ROOT)) {
                continue;
            }
            $relativePath = substr($absoluteSourcePath, strlen(FLOW_PATH_ROOT));
            $lines[] = $proxyFile->getFilename() . ' = ' . $relativePath;
        }

        $mapDir = FLOW_PATH_ROOT . '.xdebug/';
        if (!is_dir($mapDir)) {
            mkdir($mapDir, 0755, true);
        }

        $header = 'remote_prefix: ' . $proxyCacheDir . PHP_EOL;
        $header .= 'local_prefix: ' . FLOW_PATH_ROOT . PHP_EOL;
        file_put_contents($mapDir . 'flow.map', $header . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL);
    }
}
