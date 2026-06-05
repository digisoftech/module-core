<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Model\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json;

class InstalledModulesProvider
{
    private const CORE_MODULE = 'Digisoftech_Core';

    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ComponentRegistrar $componentRegistrar,
        private readonly Json $json
    ) {
    }

    /**
     * @return array<int, array{
     *     module_name: string,
     *     package_name: string,
     *     version: string,
     *     title: string
     * }>
     */
    public function getInstalledExtensionModules(): array
    {
        $modules = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            if (!str_starts_with($moduleName, 'Digisoftech_') || $moduleName === self::CORE_MODULE) {
                continue;
            }

            $modules[] = $this->buildModuleInfo($moduleName);
        }

        usort(
            $modules,
            static fn (array $left, array $right): int => strcmp($left['title'], $right['title'])
        );

        return $modules;
    }

    public function getInstalledExtensionCount(): int
    {
        return count($this->getInstalledExtensionModules());
    }

    /**
     * @return array{
     *     module_name: string,
     *     package_name: string,
     *     version: string,
     *     title: string
     * }
     */
    private function buildModuleInfo(string $moduleName): array
    {
        $composerData = $this->readComposerData($moduleName);

        return [
            'module_name' => $moduleName,
            'package_name' => (string) ($composerData['name'] ?? $this->guessPackageName($moduleName)),
            'version' => (string) ($composerData['version'] ?? '0.0.0'),
            'title' => (string) ($composerData['description'] ?? $this->humanizeModuleName($moduleName)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerData(string $moduleName): array
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $composerFile = $path . '/composer.json';

        if (!is_readable($composerFile)) {
            return [];
        }

        $contents = file_get_contents($composerFile);

        if ($contents === false) {
            return [];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = $this->json->unserialize($contents);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return $data;
    }

    private function guessPackageName(string $moduleName): string
    {
        $suffix = strtolower(str_replace('Digisoftech_', '', $moduleName));
        $suffix = preg_replace('/([a-z])([A-Z])/', '$1-$2', $suffix) ?? $suffix;
        $suffix = strtolower(str_replace('_', '-', $suffix));

        return 'digisoftech/module-' . $suffix;
    }

    private function humanizeModuleName(string $moduleName): string
    {
        $suffix = str_replace('Digisoftech_', '', $moduleName);
        $suffix = preg_replace('/([a-z])([A-Z])/', '$1 $2', $suffix) ?? $suffix;

        return 'Digisoftech ' . $suffix;
    }
}
