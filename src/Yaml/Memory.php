<?php

namespace Vladitot\Architect\Yaml;

class Memory
{
    public static Project $project;

    /**
     * @var array|Module[] $modules
     */
    public static array $modules = [];

    public static string $currentModuleName = '';
}
