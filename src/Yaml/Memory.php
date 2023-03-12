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

    public static array $fileMTimes = [];

    public static function shouldReRender($moduleName) {
        $filename = base_path()."/Packages/".ucfirst($moduleName)."/".ucfirst($moduleName)."Module.yaml";

        if (!isset(self::$fileMTimes[$moduleName])) {
            self::$fileMTimes[$moduleName] = filemtime($filename);
            return true;
        } elseif (filemtime($filename) > self::$fileMTimes[$moduleName]) {
            self::$fileMTimes[$moduleName] = filemtime($filename);
            return true;
        }

        return false;
    }
}
