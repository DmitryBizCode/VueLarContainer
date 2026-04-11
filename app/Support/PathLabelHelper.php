<?php

namespace App\Support;

class PathLabelHelper
{
    /** Path prefix => human-readable label (user panel). */
    private const USER_LABELS = [
        'dashboard' => 'Dashboard',
        'profile' => 'Profile',
        'rentals-center' => 'Rentals center',
        'rentals/request' => 'Rental request',
        'rentals/' => 'Rentals',
        'finance-monitoring' => 'Finance',
        'contact' => 'Contact',
        'services' => 'Services',
    ];

    /** Path prefix => human-readable label (admin panel). Longer prefixes first. */
    private const ADMIN_LABELS = [
        'admin/activity-logs' => 'Activity logs',
        'admin/request-logs' => 'Request logs',
        'admin/containers' => 'Containers',
        'admin/rentals' => 'Rentals',
        'admin/finance' => 'Finance',
        'admin/users' => 'Users',
        'admin/approvals' => 'Approvals',
        'admin/ports' => 'Ports',
        'admin/routes' => 'Routes',
        'admin/vessels' => 'Vessels',
        'admin/owners' => 'Owners',
        'admin' => 'Admin dashboard',
    ];

    public static function pathToLabel(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '') {
            return 'Home';
        }

        foreach (array_merge(self::ADMIN_LABELS, self::USER_LABELS) as $prefix => $label) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $label;
            }
        }

        return self::humanizePath($path);
    }

    public static function isAdminPath(string $path): bool
    {
        $path = trim($path, '/');

        return $path === 'admin' || str_starts_with($path, 'admin/');
    }

    private static function humanizePath(string $path): string
    {
        $path = str_replace(['-', '_'], ' ', $path);
        $path = preg_replace('#/+#', ' · ', $path);

        return ucwords($path);
    }
}
