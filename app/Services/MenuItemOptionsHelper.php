<?php

namespace App\Services;

class MenuItemOptionsHelper
{
    /**
     * @param  array<string, mixed>|null  $selected
     */
    public static function formatSelectedOptions(?array $selected): ?string
    {
        if (empty($selected)) {
            return null;
        }

        $parts = [];

        foreach (['size' => 'Size', 'flavor' => 'Flavor'] as $key => $label) {
            $choice = $selected[$key] ?? null;
            if (!is_array($choice) || empty($choice['label'])) {
                continue;
            }
            $segment = "{$label}: {$choice['label']}";
            $price = (float) ($choice['price'] ?? 0);
            if ($price > 0) {
                $segment .= ' (+₱' . number_format($price, 2) . ')';
            }
            $parts[] = $segment;
        }

        $addons = $selected['addons'] ?? null;
        if (is_array($addons) && !empty($addons)) {
            $addonParts = [];
            foreach ($addons as $addon) {
                if (!is_array($addon) || empty($addon['label'])) {
                    continue;
                }
                $qty = max(1, (int) ($addon['quantity'] ?? 1));
                $price = (float) ($addon['price'] ?? 0);
                $segment = $addon['label'];
                if ($qty > 1) {
                    $segment = "{$qty}x {$segment}";
                }
                if ($price > 0) {
                    $segment .= ' (+₱' . number_format($price * $qty, 2) . ')';
                }
                $addonParts[] = $segment;
            }
            if (!empty($addonParts)) {
                $parts[] = 'Add-ons: ' . implode(', ', $addonParts);
            }
        }

        return empty($parts) ? null : implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $selected
     */
    public static function calculateUnitPrice(float $basePrice, ?array $selected): float
    {
        $total = $basePrice;

        foreach (['size', 'flavor'] as $key) {
            $choice = $selected[$key] ?? null;
            if (is_array($choice)) {
                $total += (float) ($choice['price'] ?? 0);
            }
        }

        $addons = $selected['addons'] ?? null;
        if (is_array($addons)) {
            foreach ($addons as $addon) {
                if (!is_array($addon)) {
                    continue;
                }
                $qty = max(1, (int) ($addon['quantity'] ?? 1));
                $total += (float) ($addon['price'] ?? 0) * $qty;
            }
        }

        return round($total, 2);
    }
}
