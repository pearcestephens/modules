<?php
declare(strict_types=1);

/**
 * CIS Module Utilities
 * 
 * Central utilities for the CIS modules system
 * 
 * @author CIS Development Team
 * @version 1.0
 * @since October 12, 2025
 */

class ModuleUtilities
{
    /**
     * Get all available modules
     */
    public static function getAvailableModules(): array
    {
        $modules = [];
        $rootPath = dirname(__DIR__);
        $iterator = new DirectoryIterator($rootPath);
        
        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) continue;
            
            $name = $item->getFilename();
            if (in_array($name, ['.git', 'docs', 'backups', 'core', 'templates'])) continue;
            
            if (self::isModule($item->getPathname())) {
                $modules[] = $name;
            }
        }
        
        return $modules;
    }
    
    /**
     * Check if directory is a valid module
     */
    public static function isModule(string $path): bool
    {
        return file_exists($path . '/index.php') || 
               is_dir($path . '/api') || 
               is_dir($path . '/controllers');
    }
    
    /**
     * Get module metadata
     */
    public static function getModuleInfo(string $moduleName): array
    {
        $modulePath = dirname(__DIR__) . '/' . $moduleName;
        
        if (!self::isModule($modulePath)) {
            throw new InvalidArgumentException("Module '{$moduleName}' not found");
        }
        
        return [
            'name' => $moduleName,
            'path' => $modulePath,
            'has_api' => is_dir($modulePath . '/api'),
            'has_controllers' => is_dir($modulePath . '/controllers'),
            'has_assets' => is_dir($modulePath . '/assets'),
            'entry_point' => $modulePath . '/index.php',
            'bootstrap' => $modulePath . '/module_bootstrap.php',
        ];
    }
    
    /**
     * Load module autoloader if available
     */
    public static function loadModule(string $moduleName): bool
    {
        $moduleInfo = self::getModuleInfo($moduleName);
        
        // Load bootstrap if available
        if (file_exists($moduleInfo['bootstrap'])) {
            require_once $moduleInfo['bootstrap'];
            return true;
        }
        
        // Load autoloader if available
        $autoloader = $moduleInfo['path'] . '/_shared/lib/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
            return true;
        }
        
        return false;
    }
}
