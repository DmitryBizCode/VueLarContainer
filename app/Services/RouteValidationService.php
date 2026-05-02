<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Route;

final class RouteValidationService
{
    /**
     * Route is drawable if:
     * - ports have coordinates (handled by controller queries), and
     * - sea_path has at least one waypoint pair.
     */
    public function isDrawable(Route $route): bool
    {
        if (! is_array($route->sea_path) || $route->sea_path === []) {
            return false;
        }

        // Validate at least one coordinate pair exists
        foreach ($route->sea_path as $row) {
            if (is_array($row) && (array_is_list($row) ? count($row) >= 2 : (isset($row['lat'], $row['lng'])))) {
                return true;
            }
        }

        return false;
    }
}

