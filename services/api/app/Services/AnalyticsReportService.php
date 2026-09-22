<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\HasMany;

abstract class AnalyticsReportService
{
    protected function sessions(Project $project, int $days, ?string $device = null): HasMany
    {
        return $project->sessions()
            ->where('started_at', '>=', now()->subDays(min(365, max(1, $days))))
            ->when($device && $device !== 'all', fn ($query) => $query->where('device_type', $device));
    }

    protected function properties(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function pageLabel(?string $url): string
    {
        if (! $url) {
            return 'Unknown page';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return $path === '/' ? 'Homepage' : $path;
    }
}
