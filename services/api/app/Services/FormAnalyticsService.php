<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class FormAnalyticsService extends AnalyticsReportService
{
    public function report(Project $project, int $days = 30, ?string $device = null): array
    {
        $sessionIds = $this->sessions($project, $days, $device)->pluck('id');
        $events = DB::table('analytics_events')
            ->whereIn('session_id', $sessionIds)
            ->whereIn('event_name', ['form_focus', 'form_submit'])
            ->orderBy('occurred_at')
            ->get();
        $forms = [];

        foreach ($events as $event) {
            $properties = $this->properties($event->properties);
            $formId = trim((string) ($properties['formId'] ?? $properties['id'] ?? ''));
            $page = $this->pageLabel($event->url);
            $key = $page.'#'.($formId ?: 'form');
            $forms[$key] ??= [
                'key' => $key,
                'name' => $formId ?: ($page.' form'),
                'url' => $event->url ?: '',
                'started' => [],
                'submitted' => [],
                'fields' => [],
            ];

            if ($event->event_name === 'form_focus') {
                $forms[$key]['started'][(string) $event->session_id] = true;
                $field = trim((string) ($properties['name'] ?? ''));
                if ($field && $field !== 'masked') {
                    $forms[$key]['fields'][$field] = ($forms[$key]['fields'][$field] ?? 0) + 1;
                }
            } else {
                $forms[$key]['submitted'][(string) $event->session_id] = true;
            }
        }

        $rows = collect($forms)->map(function ($form) {
            $started = count($form['started']);
            $submitted = count($form['submitted']);
            $abandonments = count(array_diff_key($form['started'], $form['submitted']));
            arsort($form['fields']);

            return [
                'key' => $form['key'],
                'name' => $form['name'],
                'url' => $form['url'],
                'starts' => $started,
                'submissions' => $submitted,
                'abandonments' => $abandonments,
                'completionRate' => $started ? round(min($started, $submitted) * 100 / $started, 1) : 0,
                'topField' => array_key_first($form['fields']),
            ];
        })->sortByDesc('starts')->values();

        $starts = $rows->sum('starts');
        $submissions = $rows->sum('submissions');
        $abandonments = $rows->sum('abandonments');

        return [
            'totalForms' => $rows->count(),
            'starts' => $starts,
            'submissions' => $submissions,
            'abandonments' => $abandonments,
            'completionRate' => $starts ? round(min($starts, $submissions) * 100 / $starts, 1) : 0,
            'forms' => $rows->all(),
        ];
    }
}
