<?php
$analyticsProLocked = isset($gateState) && empty($gateState['available']);

$analyticsMetric = static function ($value, array $attrs = []) use ($analyticsProLocked): void {
    $value = (string)$value;
    if ($analyticsProLocked) {
        AdminUI::proMetric($value, $attrs);
        return;
    }

    echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$analyticsLockedButton = static function (string $label, array $attrs = []) use ($analyticsProLocked): bool {
    if (!$analyticsProLocked) {
        return false;
    }

    AdminUI::proLockedButton($label, $attrs);
    return true;
};

$analyticsLockedInput = static function (string $value, array $attrs = []) use ($analyticsProLocked): bool {
    if (!$analyticsProLocked) {
        return false;
    }

    AdminUI::proLockedInput($value, $attrs);
    return true;
};
