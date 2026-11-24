<?php

namespace App\Services;

use App\Models\JobListing;
use Carbon\CarbonImmutable;

class ResumeMatcher
{
    public function match(JobListing $job): array
    {
        $text = mb_strtolower(implode("\n", [
            $job->title,
            $job->description,
            $job->qualifications,
        ]));
        $matched = [];

        foreach (config('job_dashboard.skills') as $key => $skill) {
            if ($this->containsAny($text, $skill['aliases'])) {
                $matched[$key] = $skill;
            }
        }

        if ($matched === []) {
            return ['is_match' => false, 'reasons' => []];
        }

        $generalRequiredYears = $this->generalRequiredYears($text);
        if ($generalRequiredYears !== null && $this->yearsOfExperience('web_development') < $generalRequiredYears) {
            return ['is_match' => false, 'reasons' => []];
        }

        foreach ($matched as $key => $skill) {
            $requiredYears = $this->requiredYearsForSkill($text, $skill['aliases']);

            if ($requiredYears !== null && $this->yearsOfExperience($key) < $requiredYears) {
                return ['is_match' => false, 'reasons' => []];
            }
        }

        $reasons = [];
        foreach ($matched as $key => $skill) {
            $evidence = $this->historyFor($key);
            if ($evidence !== []) {
                $reasons[] = sprintf(
                    'This role calls for %s; your résumé shows related work at %s.',
                    $skill['label'],
                    implode(' and ', $evidence),
                );
            }
        }

        return ['is_match' => $reasons !== [], 'reasons' => array_slice($reasons, 0, 4)];
    }

    private function containsAny(string $text, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            $pattern = '/(?<![\pL\pN])'.preg_quote(mb_strtolower($alias), '/').'(?![\pL\pN])/u';

            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function requiredYearsForSkill(string $text, array $aliases): ?float
    {
        $highest = null;
        foreach ($aliases as $alias) {
            $skill = preg_quote(mb_strtolower($alias), '/');
            $years = '(\\d+(?:\\.\\d+)?)\\s*\\+?\\s*(?:years?|yrs?)';
            $patterns = [
                '/'.$years.'[^.;\\n]{0,100}\\b'.$skill.'\\b/ui',
                '/\\b'.$skill.'\\b[^.;\\n]{0,100}'.$years.'/ui',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $text, $matches) === false) {
                    continue;
                }

                foreach ($matches[1] as $value) {
                    $highest = max($highest ?? 0, (float) $value);
                }
            }
        }

        return $highest;
    }

    private function generalRequiredYears(string $text): ?float
    {
        $highest = null;
        $patterns = [
            '/(\\d+(?:\\.\\d+)?)\\s*\\+?\\s*(?:years?|yrs?)\\b[^.;\\n]{0,80}\\bexperience\\b/ui',
            '/\\bexperience\\b[^.;\\n]{0,40}(\\d+(?:\\.\\d+)?)\\s*\\+?\\s*(?:years?|yrs?)\\b/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $value) {
                $highest = max($highest ?? 0, (float) $value);
            }
        }

        return $highest;
    }

    private function yearsOfExperience(string $skill): float
    {
        $today = CarbonImmutable::now('UTC');
        $ranges = [];

        foreach (config('job_dashboard.resume_history') as $entry) {
            if (! in_array($skill, $entry['skills'], true)) {
                continue;
            }

            $start = CarbonImmutable::parse($entry['start'], 'UTC');
            $end = isset($entry['end'])
                ? CarbonImmutable::parse($entry['end'], 'UTC')
                : $today;

            if ($end->greaterThan($today)) {
                $end = $today;
            }

            $ranges[] = [$start, $end];
        }

        usort($ranges, fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());
        $merged = [];

        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start->lessThanOrEqualTo($merged[$last][1])) {
                if ($end->greaterThan($merged[$last][1])) {
                    $merged[$last][1] = $end;
                }

                continue;
            }

            $merged[] = [$start, $end];
        }

        $days = array_sum(array_map(
            fn (array $range): int => max(0, (int) $range[0]->diffInDays($range[1])),
            $merged,
        ));

        return $days / 365.2425;
    }

    private function historyFor(string $skill): array
    {
        $history = [];
        foreach (config('job_dashboard.resume_history') as $entry) {
            if (! in_array($skill, $entry['skills'], true)) {
                continue;
            }

            $start = CarbonImmutable::parse($entry['start'])->format('F Y');
            $end = isset($entry['end'])
                ? CarbonImmutable::parse($entry['end'])->format('F Y')
                : 'present';
            $evidence = $entry['role'].' at '.$entry['employer'].' ('.$start.'–'.$end.')';

            if (! in_array($evidence, $history, true)) {
                $history[] = $evidence;
            }
        }

        return $history;
    }
}
