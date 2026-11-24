<?php

namespace App\Services;

use App\Models\JobListing;
use App\Models\JobRefreshRun;
use App\Models\JobSourceCheck;
use App\Services\JobRssFeed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

class JobDashboardRefresher
{
    public function __construct(private readonly JobRssFeed $feed)
    {
    }

    public function refresh(): JobRefreshRun
    {
        $startedAt = now('UTC');
        $run = JobRefreshRun::create(['started_at' => $startedAt]);
        $configuredFeeds = 0;
        $sources = config('job_dashboard.sources');

        foreach ($sources as $key => $source) {
            $url = trim((string) ($source['feed_url'] ?? ''));

            if ($url === '') {
                $check = JobSourceCheck::firstOrNew(['source' => $key]);
                $checkIsManuallyChecked = $check->status === 'manual'
                    && $check->last_checked_at?->greaterThanOrEqualTo(now('UTC')->subHours(24));

                if (! $checkIsManuallyChecked) {
                    $check->status = 'not_configured';
                    $check->message = 'No official RSS/Atom feed is configured. Browse the source or add a manually verified job.';
                    $check->records_found = 0;
                    $check->save();
                }

                continue;
            }

            $configuredFeeds++;
            $checkedAt = now('UTC');

            try {
                $jobs = $this->feed->fetch($url);
            } catch (ConnectionException|RequestException|UnexpectedValueException|InvalidArgumentException $exception) {
                Log::warning('Job source refresh failed.', [
                    'source' => $key,
                    'exception' => $exception,
                ]);

                JobSourceCheck::updateOrCreate(
                    ['source' => $key],
                    [
                        'status' => 'error',
                        'message' => $this->failureMessage($exception),
                        'last_checked_at' => $checkedAt,
                        'records_found' => 0,
                    ],
                );

                continue;
            }

            $this->storeSnapshot($key, $jobs);
            JobSourceCheck::updateOrCreate(
                ['source' => $key],
                [
                    'status' => 'success',
                    'message' => 'Source checked successfully.',
                    'last_checked_at' => $checkedAt,
                    'last_successful_at' => $checkedAt,
                    'records_found' => count($jobs),
                ],
            );
        }

        $successful = $configuredFeeds > 0 && $this->allSourcesCheckedRecently();
        $run->forceFill([
            'finished_at' => now('UTC'),
            'successful' => $successful,
        ])->save();

        return $run;
    }

    public function markManuallyChecked(string $source): void
    {
        JobSourceCheck::updateOrCreate(
            ['source' => $source],
            [
                'status' => 'manual',
                'message' => 'You marked this source as searched manually.',
                'last_checked_at' => now('UTC'),
                'records_found' => JobListing::query()
                    ->where('source', $source)
                    ->where('entry_method', 'manual')
                    ->where('is_open', true)
                    ->count(),
            ],
        );

        $this->recordCompletedSearchIfReady();
    }

    public function recordManuallyAddedListing(string $source): void
    {
        JobSourceCheck::updateOrCreate(
            ['source' => $source],
            [
                'status' => 'manual',
                'message' => 'A job from this source was verified and added manually.',
                'last_checked_at' => now('UTC'),
                'records_found' => JobListing::query()
                    ->where('source', $source)
                    ->where('entry_method', 'manual')
                    ->where('is_open', true)
                    ->count(),
            ],
        );

        $this->recordCompletedSearchIfReady();
    }

    private function storeSnapshot(string $source, array $jobs): void
    {
        JobListing::query()->where('source', $source)->update(['is_open' => false]);

        foreach ($jobs as $job) {
            $listing = JobListing::firstOrNew([
                'source' => $source,
                'source_listing_key' => $job['source_listing_key'],
            ]);

            $originalPostedAt = $listing->exists ? $listing->posted_at : $job['posted_at'];
            $listing->fill($job);
            $listing->posted_at = $originalPostedAt;
            $listing->save();
        }
    }

    private function recordCompletedSearchIfReady(): void
    {
        if (! $this->allSourcesCheckedRecently()) {
            return;
        }

        $now = now('UTC');
        JobRefreshRun::create([
            'started_at' => $now,
            'finished_at' => $now,
            'successful' => true,
        ]);
    }

    private function allSourcesCheckedRecently(): bool
    {
        $sourceCount = count(config('job_dashboard.sources'));
        $checkedCount = JobSourceCheck::query()
            ->whereIn('source', array_keys(config('job_dashboard.sources')))
            ->whereIn('status', ['success', 'manual'])
            ->where('last_checked_at', '>=', now('UTC')->subHours(24))
            ->count();

        return $checkedCount === $sourceCount;
    }

    private function failureMessage(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            return 'The source returned HTTP '.$exception->response->status().'.';
        }

        if ($exception instanceof \UnexpectedValueException) {
            return $exception->getMessage();
        }

        return 'The source could not be reached. Check the feed URL and server logs.';
    }
}
