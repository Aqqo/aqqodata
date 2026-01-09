<?php

namespace Aqqo\OData\Utils;

class ClassUtils
{
    /**
     * @var array<string, string>
     */
    private static array $classes = [];

    /**
     * @var array<string, string>
     */
    private static array $shortNames = [];

    /**
     * Get the fully qualified class name (FQCN) for use as array keys.
     * This prevents conflicts when multiple models have the same short name but different namespaces.
     *
     * @param object|string $class Model instance or class name
     * @return string Fully qualified class name
     */
    public static function getClassName(object|string $class): string
    {
        $className = is_object($class) ? get_class($class) : $class;
        return $className;
    }

    /**
     * Get the short name (for backward compatibility and display purposes).
     *
     * @param object $class
     * @return string
     */
    public static function getShortName(object $class): string
    {
        $className = get_class($class);
        if (!isset(self::$shortNames[$className])) {
            self::$shortNames[$className] = strtolower((new \ReflectionClass($class))->getShortName());
        }
        return self::$shortNames[$className];
    }

    /**
     * Normalize a class name for lookup - tries full class name first, then short name.
     * This provides backward compatibility while supporting namespaced models.
     *
     * @param array<string, array<string, string>> $array The array to search in
     * @param string $className The class name (can be full FQCN or short name)
     * @param \ReflectionClass|null $reflectionClass Optional reflection class for resolving FQCN
     * @return string|null The normalized key to use, or null if not found
     */
    public static function normalizeClassNameForLookup(array $array, string $className, ?\ReflectionClass $reflectionClass = null): ?string
    {
        // If it's already a full class name and exists, use it
        if (isset($array[$className])) {
            return $className;
        }

        // Try to resolve to full class name if we have a reflection class
        if ($reflectionClass !== null) {
            try {
                // Check if it's a short name that matches the reflection class
                $shortName = strtolower($reflectionClass->getShortName());
                if (strtolower($className) === $shortName) {
                    $fullName = $reflectionClass->getName();
                    if (isset($array[$fullName])) {
                        return $fullName;
                    }
                }
            } catch (\Exception $e) {
                // Ignore reflection errors
            }
        }

        // Try lowercase version (for backward compatibility with short names)
        $lowerClassName = strtolower($className);
        if (isset($array[$lowerClassName])) {
            return $lowerClassName;
        }

        // Try to find by short name match (for backward compatibility)
        foreach ($array as $key => $value) {
            $keyShortName = strtolower((new \ReflectionClass($key))->getShortName());
            if ($lowerClassName === $keyShortName) {
                return $key;
            }
        }

        return null;
    }
}