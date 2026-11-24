<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\JobRefreshRun;
use App\Models\JobSourceCheck;
use App\Services\JobDashboardRefresher;
use App\Services\ResumeMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobDashboardController extends Controller
{
    public function index(ResumeMatcher $matcher): View
    {
        $now = now('UTC');
        $cutoff = $now->copy()->subHours(24);
        $verifiedFeedSources = JobSourceCheck::query()
            ->where('status', 'success')
            ->where('last_checked_at', '>=', $cutoff)
            ->pluck('source');
        $verifiedManualSources = JobSourceCheck::query()
            ->where('status', 'manual')
            ->where('last_checked_at', '>=', $cutoff)
            ->pluck('source');
        $jobs = JobListing::query()
            ->where('is_open', true)
            ->where('is_remote', true)
            ->where('accepts_philippines', true)
            ->where(function ($query) use ($verifiedFeedSources, $verifiedManualSources): void {
                $query->where(function ($query) use ($verifiedFeedSources): void {
                    $query->where('entry_method', 'feed')
                        ->whereIn('source', $verifiedFeedSources);
                })->orWhere(function ($query) use ($verifiedManualSources): void {
                    $query->where('entry_method', 'manual')
                        ->whereIn('source', $verifiedManualSources);
                });
            })
            ->whereIn('employment_type', ['full_time', 'part_time'])
            ->whereBetween('posted_at', [$cutoff, $now])
            ->orderByDesc('posted_at')
            ->get()
            ->map(function (JobListing $job) use ($matcher): ?JobListing {
                $match = $matcher->match($job);
                if (! $match['is_match']) {
                    return null;
                }

                $job->setAttribute('match_reasons', $match['reasons']);

                return $job;
            })
            ->filter()
            ->groupBy(fn (JobListing $job): string => $this->duplicateKey($job))
            ->map(fn (Collection $listings): JobListing => $this->mergeListings($listings))
            ->sortByDesc(fn (JobListing $job) => $job->posted_at)
            ->values();

        $checks = JobSourceCheck::query()->get()->keyBy('source');
        $checkedSourceCount = $checks
            ->filter(fn (JobSourceCheck $check): bool => in_array($check->status, ['success', 'manual'], true)
                && $check->last_checked_at?->greaterThanOrEqualTo($cutoff))
            ->count();
        $sources = collect(config('job_dashboard.sources'))
            ->map(function (array $source, string $key) use ($checks, $cutoff): array {
                $check = $checks->get($key);
                $status = $check?->status ?? 'not_configured';
                $message = $check?->message ?? 'No official RSS/Atom feed is configured. Browse the site or add a manually verified job.';

                if (in_array($status, ['success', 'manual'], true)
                    && (! $check->last_checked_at || $check->last_checked_at->lessThan($cutoff))) {
                    $status = 'stale';
                    $message = 'This source has not been checked in the last 24 hours.';
                }

                return [
                    'key' => $key,
                    'label' => $source['label'],
                    'browse_url' => $source['browse_url'],
                    'status' => $status,
                    'message' => $message,
                    'last_checked_at' => $check?->last_checked_at,
                ];
            })
            ->values();

        $lastSuccessfulRefresh = JobRefreshRun::query()
            ->where('successful', true)
            ->latest('finished_at')
            ->value('finished_at');

        return view('jobs.index', [
            'jobs' => $jobs,
            'sources' => $sources,
            'lastSuccessfulRefresh' => $lastSuccessfulRefresh,
            'hasCompletedSearch' => $checkedSourceCount === count(config('job_dashboard.sources')),
            'checkedSourceCount' => $checkedSourceCount,
            'totalSourceCount' => count(config('job_dashboard.sources')),
            'timezone' => config('job_dashboard.timezone'),
        ]);
    }

    public function refresh(JobDashboardRefresher $refresher): RedirectResponse
    {
        $run = $refresher->refresh();

        return redirect()
            ->route('jobs.index')
            ->with(
                $run->successful ? 'status' : 'warning',
                $run->successful
                    ? 'All four sources were checked using configured feeds or manual reviews.'
                    : 'Refresh finished. Review each source status; add official RSS feeds or complete the manual checks.',
            );
    }

    public function store(Request $request, JobDashboardRefresher $refresher): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', Rule::in(array_keys(config('job_dashboard.sources')))],
            'title' => ['required', 'string', 'max:255'],
            'job_url' => ['nullable', 'url:http,https', 'max:2048'],
            'employer_apply_url' => ['nullable', 'url:http,https', 'max:2048'],
            'posted_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time'])],
            'pay' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:30000'],
            'qualifications' => ['nullable', 'string', 'max:30000'],
            'is_open' => ['required', 'accepted'],
            'is_remote' => ['required', 'accepted'],
            'accepts_philippines' => ['required', 'accepted'],
        ]);

        $source = $validated['source'];
        $jobUrl = filled($validated['job_url'] ?? null) ? trim($validated['job_url']) : null;
        $employerApplyUrl = filled($validated['employer_apply_url'] ?? null)
            ? trim($validated['employer_apply_url'])
            : null;
        $originalPostedAt = CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $validated['posted_at'],
            config('job_dashboard.timezone'),
        )->utc();
        $identity = $jobUrl
            ?? $employerApplyUrl
            ?? Str::slug($validated['title']).'|'.$originalPostedAt->format('Y-m-d H:i:s');
        $listingKey = hash('sha256', mb_strtolower($identity));
        $listing = JobListing::firstOrNew([
            'source' => $source,
            'source_listing_key' => $listingKey,
        ]);
        $originalPostedAt = $listing->exists
            ? $listing->posted_at
            : $originalPostedAt->toDateTimeString();

        $listing->fill([
            'source' => $source,
            'source_listing_key' => $listingKey,
            'entry_method' => 'manual',
            'title' => trim($validated['title']),
            'company' => 'Not listed',
            'job_url' => $jobUrl,
            'employer_apply_url' => $employerApplyUrl,
            'employment_type' => $validated['employment_type'],
            'pay' => $validated['pay'] ?? null,
            'description' => trim($validated['description']),
            'qualifications' => trim($validated['qualifications'] ?? ''),
            'is_open' => true,
            'is_remote' => true,
            'accepts_philippines' => true,
        ]);
        $listing->posted_at = $originalPostedAt;
        $listing->save();
        $refresher->recordManuallyAddedListing($source);

        return redirect()->route('jobs.index')->with('status', 'The job was saved after your manual verification.');
    }

    public function markChecked(string $source, JobDashboardRefresher $refresher): RedirectResponse
    {
        $refresher->markManuallyChecked($source);

        return redirect()->route('jobs.index')->with('status', 'The source was marked as searched manually.');
    }

    private function duplicateKey(JobListing $job): string
    {
        if ($job->company === 'Not listed') {
            $identity = $job->job_url
                ?? $job->employer_apply_url
                ?? $job->source.'|'.$job->source_listing_key;

            return 'unlisted|'.Str::lower(trim($identity));
        }

        return Str::slug($job->company).'|'.Str::slug($job->title);
    }

    private function mergeListings(Collection $listings): JobListing
    {
        $ordered = $listings->sortByDesc(fn (JobListing $job) => $job->posted_at)->values();
        $primary = $ordered->first();
        $directApplication = $ordered->first(fn (JobListing $job): bool => filled($job->employer_apply_url));
        $primary->setAttribute('apply_url', $directApplication?->employer_apply_url ?? $primary->job_url);
        $primary->setAttribute('sources', $ordered
            ->map(fn (JobListing $job): string => config("job_dashboard.sources.{$job->source}.label", $job->source))
            ->unique()
            ->values());
        $primary->setAttribute('match_reasons', $ordered
            ->flatMap(fn (JobListing $job): array => $job->getAttribute('match_reasons') ?? [])
            ->unique()
            ->take(4)
            ->values());

        return $primary;
    }
}
