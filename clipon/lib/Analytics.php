<?php
/**
 * Клас для легкої аналітики трафіку без БД.
 * Зберігає дані в JSON-PHP файлах за місяцями.
 */

// AnalyticsContainer is registered in bootstrap for collection and basic stats.
class Analytics {
    private static string $dataDir = C_DATA_PATH . '/analytics';
    private static ?AnalyticsContainer $container = null;
    /**
     * Позволяет явно установить контейнер зависимостей (рекомендуется для bootstrap).
     * Оставляем ленивое создание для совместимости, но предпочитаем инъекцию.
     */
    public static function setContainer(?AnalyticsContainer $container): void {
        self::$container = $container;
    }

    public static function hasContainer(): bool {
        return self::$container instanceof AnalyticsContainer;
    }
    
    /**
     * Основна функція для трекінгу перегляду
     */
    public static function track(): void {
        self::tracker()->track();
    }

    /**
     * Очистка файлів аналітики, що старші за встановлений ліміт
     */
    public static function cleanupOldData(): void {
        self::storage()->cleanupOldData();
    }

    /**
     * Повне видалення всіх даних аналітики
     */
    public static function clearAllData(): bool {
        return self::storage()->clearAllData();
    }

    /**
     * Трекінг кастомних івентів (скрол, кліки тощо)
     */
    public static function trackEvent(string $category, string $action, ?string $label = null): void {
        self::events()->trackEvent($category, $action, $label);
    }

    public static function trackBasicEvent(string $category, string $action, ?string $label = null): void {
        self::events()->trackBasicEvent($category, $action, $label);
    }

    private static function storage(): AnalyticsStorage {
        return self::container()->storage();
    }

    private static function report(): AnalyticsReport {
        return self::container()->report();
    }

    private static function events(): AnalyticsEvent {
        return self::container()->events();
    }

    private static function tracker(): AnalyticsTracker {
        return self::container()->tracker();
    }

    /**
     * Отримує базову статистику (хіти, уніки) для вільного використання
     */
    public static function getBasicStats(string $from, string $to): array {
        return self::report()->getBasicStats($from, $to);
    }

    /**
     * Повертає демо-дані для маркетингових цілей
     */
    public static function getDemoStats(): array {
        return self::view()->getDemoStats();
    }

    private static function view(): AnalyticsView {
        return self::container()->view();
    }

    private static function container(): AnalyticsContainer {
        if (self::$container === null) {
            throw new RuntimeException('Analytics container is not initialized. Call Analytics::setContainer(...) in bootstrap.');
        }

        return self::$container;
    }
}
