<?php
/**
 * Clipon CMS Hook System
 * Allows modules to interact with core logic.
 */
class Hooks {
    private static $actions = [];
    private static $filters = [];

    /**
     * Add action hook
     */
    public static function addAction($name, $callback, $priority = 10) {
        if (!isset(self::$actions[$name])) self::$actions[$name] = [];
        self::$actions[$name][] = ['callback' => $callback, 'priority' => $priority];
        usort(self::$actions[$name], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Execute action
     */
    public static function doAction($name, ...$args) {
        if (isset(self::$actions[$name])) {
            foreach (self::$actions[$name] as $hook) {
                call_user_func_array($hook['callback'], $args);
            }
        }
    }

    /**
     * Add filter hook
     */
    public static function addFilter($name, $callback, $priority = 10) {
        if (!isset(self::$filters[$name])) self::$filters[$name] = [];
        self::$filters[$name][] = ['callback' => $callback, 'priority' => $priority];
        usort(self::$filters[$name], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Apply filters to a value
     */
    public static function applyFilters($name, $value, ...$args) {
        if (isset(self::$filters[$name])) {
            foreach (self::$filters[$name] as $hook) {
                $value = call_user_func_array($hook['callback'], array_merge([$value], $args));
            }
        }
        return $value;
    }
}
