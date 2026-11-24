<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fa">
    <title>Remote jobs for you</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('jobs.index') }}" class="flex items-center gap-3" aria-label="Job Match home">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold tracking-wide text-white">JM</span>
                    <span>
                        <span class="block text-sm font-semibold tracking-tight text-slate-900">Job Match</span>
                        <span class="block text-xs text-slate-500">A shortlist built around your experience</span>
                    </span>
                </a>
                <span class="hidden items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800 sm:inline-flex">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Based in the Philippines
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-[1440px] px-4 pb-14 pt-8 sm:px-6 sm:pt-10 lg:px-8">
            <section class="grid gap-8 rounded-3xl bg-slate-900 px-6 py-7 text-white shadow-sm sm:px-9 sm:py-9 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="max-w-3xl">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-indigo-300">Your next opportunity</p>
                    <h1 class="max-w-2xl text-3xl font-semibold tracking-tight sm:text-4xl">Remote work that fits your experience.</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        Verified remote full-time and part-time roles, open to applicants in the Philippines, posted in the last 24 hours.
                    </p>
                </div>
                <div class="flex flex-col items-start gap-3 lg:items-end">
                    <p class="text-sm text-slate-300">
                        Last successful refresh:
                        <span class="font-medium text-white">
                            @if ($lastSuccessfulRefresh)
                                {{ \Carbon\CarbonImmutable::parse($lastSuccessfulRefresh)->setTimezone($timezone)->format('M j, Y · g:i A') }} PHT
                            @else
                                Not yet — all four sources must be configured and checked.
                            @endif
                        </span>
                    </p>
                    <form method="POST" action="{{ route('jobs.refresh') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-indigo-50 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                                <path d="M16.5 8A6.75 6.75 0 0 0 4.7 5.2L3.5 6.5M3.5 6.5V3.8M3.5 6.5h2.7M3.5 12a6.75 6.75 0 0 0 11.8 2.8l1.2-1.3m0 0v2.7m0-2.7h-2.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Refresh jobs
                        </button>
                    </form>
                </div>
            </section>

            @if (session('status'))
                <div role="status" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if (session('warning'))
                <div role="status" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
            @endif

            <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Job source status">
                @foreach ($sources as $source)
                    @php
                        $statusStyle = match ($source['status']) {
                            'success' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'label' => 'Feed checked'],
                            'manual' => ['dot' => 'bg-indigo-500', 'text' => 'text-indigo-700', 'label' => 'Manually checked'],
                            'error' => ['dot' => 'bg-rose-500', 'text' => 'text-rose-700', 'label' => 'Feed unavailable'],
                            'stale' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'label' => 'Needs review'],
                            default => ['dot' => 'bg-slate-400', 'text' => 'text-slate-500', 'label' => 'Manual search'],
                        };
                    @endphp
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-slate-800">{{ $source['label'] }}</h2>
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $statusStyle['text'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $statusStyle['dot'] }}"></span>
                                {{ $statusStyle['label'] }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ $source['message'] }}</p>
                        @if ($source['last_checked_at'])
                            <p class="mt-2 text-[11px] text-slate-400">Last checked {{ $source['last_checked_at']->setTimezone($timezone)->format('M j, g:i A') }} PHT</p>
                        @endif
                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <a href="{{ $source['browse_url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-indigo-700 hover:underline">Browse jobs ↗</a>
                            <form method="POST" action="{{ route('sources.checked', $source['key']) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50">Mark searched</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </section>

            @if ($jobs->isNotEmpty() && ! $hasCompletedSearch && $checkedSourceCount > 0)
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Partial results: {{ $checkedSourceCount }} of {{ $totalSourceCount }} sources checked in the last 24 hours. Review the remaining source cards for more matches.
                </p>
            @endif

            <details class="mt-7 rounded-2xl border border-slate-200 bg-white shadow-sm" @if ($errors->any()) open @endif>
                <summary class="cursor-pointer list-none px-5 py-4 sm:px-6">
                    <span class="font-semibold text-slate-900">Add a manually verified job</span>
                    <span class="ml-2 text-sm text-slate-500">Enter a match after reviewing the original posting.</span>
                </summary>
                <form method="POST" action="{{ route('jobs.store') }}" class="border-t border-slate-200 px-5 py-5 sm:px-6">
                    @csrf
                    @if ($errors->any())
                        <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            Check the highlighted fields and confirm the posting details.
                        </div>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="text-sm font-medium text-slate-700">
                            Source
                            <select name="source" required class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                <option value="">Choose a source</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source['key'] }}" @selected(old('source') === $source['key'])>{{ $source['label'] }}</option>
                                @endforeach
                            </select>
                            @error('source')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700">
                            Job title
                            <input name="title" value="{{ old('title') }}" required maxlength="255" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            @error('title')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2">
                            Original posting URL <span class="font-normal text-slate-400">optional</span>
                            <input name="job_url" type="url" value="{{ old('job_url') }}" placeholder="Leave blank if unavailable" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            @error('job_url')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700">
                            Direct employer application URL <span class="font-normal text-slate-400">optional</span>
                            <input name="employer_apply_url" type="url" value="{{ old('employer_apply_url') }}" placeholder="https://…" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            @error('employer_apply_url')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700">
                            Original date posted <span class="font-normal text-slate-400">PHT</span>
                            <input name="posted_at" type="datetime-local" value="{{ old('posted_at') }}" required class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            @error('posted_at')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700">
                            Employment type
                            <select name="employment_type" required class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                <option value="">Choose type</option>
                                <option value="full_time" @selected(old('employment_type') === 'full_time')>Full-time</option>
                                <option value="part_time" @selected(old('employment_type') === 'part_time')>Part-time</option>
                            </select>
                            @error('employment_type')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700">
                            Pay <span class="font-normal text-slate-400">optional</span>
                            <input name="pay" value="{{ old('pay') }}" maxlength="255" placeholder="Leave blank if undisclosed" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            @error('pay')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2 lg:col-span-3">
                            Duties and job description
                            <textarea name="description" rows="3" required maxlength="30000" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">{{ old('description') }}</textarea>
                            @error('description')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2 lg:col-span-3">
                            Required qualifications
                            <textarea name="qualifications" rows="2" maxlength="30000" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">{{ old('qualifications') }}</textarea>
                            @error('qualifications')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <fieldset class="mt-5">
                        <legend class="text-sm font-semibold text-slate-800">Confirm these details on the original listing</legend>
                        <div class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-3">
                            <label class="flex items-start gap-2"><input type="checkbox" name="is_open" value="1" required @checked(old('is_open')) class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">The job is still open.</label>
                            <label class="flex items-start gap-2"><input type="checkbox" name="is_remote" value="1" required @checked(old('is_remote')) class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">The role is fully remote.</label>
                            <label class="flex items-start gap-2"><input type="checkbox" name="accepts_philippines" value="1" required @checked(old('accepts_philippines')) class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">Applicants in the Philippines are accepted.</label>
                        </div>
                        @foreach (['is_open', 'is_remote', 'accepts_philippines'] as $field)
                            @error($field)<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
                        @endforeach
                    </fieldset>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">The original date is kept if you add this same listing again.</p>
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">Save verified job</button>
                    </div>
                </form>
            </details>

            <section class="mt-10" aria-labelledby="matches-heading">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600">Your shortlist</p>
                        <h2 id="matches-heading" class="mt-1 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Jobs matched to your résumé</h2>
                        <p class="mt-1 text-sm text-slate-500">Sorted newest first · All dates and refresh times use Philippines time.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm">
                        <span class="font-semibold text-slate-900">{{ $jobs->count() }}</span>
                        {{ \Illuminate\Support\Str::plural('match', $jobs->count()) }}
                    </div>
                </div>

                @if ($jobs->isNotEmpty())
                    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1050px] border-collapse text-left">
                                <thead class="bg-slate-50">
                                    <tr class="border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                        <th scope="col" class="px-5 py-4">Date posted</th>
                                        <th scope="col" class="px-5 py-4">Company / role</th>
                                        <th scope="col" class="px-5 py-4">Type</th>
                                        <th scope="col" class="px-5 py-4">Pay</th>
                                        <th scope="col" class="px-5 py-4">Why it matches</th>
                                        <th scope="col" class="px-5 py-4">Apply</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($jobs as $job)
                                        <tr class="align-top transition hover:bg-slate-50/70">
                                            <td class="whitespace-nowrap px-5 py-5 text-sm text-slate-600">{{ $job->posted_at->setTimezone($timezone)->format('M j, Y · g:i A') }}</td>
                                            <td class="max-w-64 px-5 py-5">
                                                <p class="text-sm font-medium text-slate-500">{{ $job->company }}</p>
                                                @if (filled($job->job_url))
                                                    <a href="{{ $job->job_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-sm font-semibold leading-5 text-indigo-700 decoration-indigo-300 underline-offset-4 hover:underline">{{ $job->title }}</a>
                                                @else
                                                    <p class="mt-1 text-sm font-semibold leading-5 text-slate-800">{{ $job->title }}</p>
                                                @endif
                                                <p class="mt-2 text-xs text-slate-400">{{ $job->sources->join(', ') }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-5">
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $job->employment_type === 'full_time' ? 'Full-time' : 'Part-time' }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-5 text-sm text-slate-700">{{ $job->pay ?: 'Not listed' }}</td>
                                            <td class="min-w-72 max-w-md px-5 py-5">
                                                <ul class="space-y-2 text-sm leading-5 text-slate-600">
                                                    @foreach ($job->match_reasons as $reason)
                                                        <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-500"></span><span>{{ $reason }}</span></li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-5">
                                                @if (filled($job->apply_url))
                                                    <a href="{{ $job->apply_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">
                                                        Apply
                                                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none"><path d="M4.5 11.5 11.25 4.75M5 4.75h6.25V11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </a>
                                                @else
                                                    <span class="text-xs text-slate-400">Link unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-3 lg:hidden">
                        @foreach ($jobs as $job)
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-medium text-slate-500">{{ $job->company }}</p>
                                        @if (filled($job->job_url))
                                            <a href="{{ $job->job_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block text-base font-semibold leading-5 text-indigo-700 hover:underline">{{ $job->title }}</a>
                                        @else
                                            <p class="mt-1 text-base font-semibold leading-5 text-slate-800">{{ $job->title }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-700">{{ $job->employment_type === 'full_time' ? 'Full-time' : 'Part-time' }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span>{{ $job->posted_at->setTimezone($timezone)->format('M j, Y · g:i A') }} PHT</span>
                                    <span>{{ $job->pay ?: 'Not listed' }}</span>
                                </div>
                                <ul class="mt-3 space-y-1.5 text-xs leading-5 text-slate-600">
                                    @foreach ($job->match_reasons as $reason)
                                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-500"></span><span>{{ $reason }}</span></li>
                                    @endforeach
                                </ul>
                                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                                    <span class="max-w-[65%] truncate text-[11px] text-slate-400">{{ $job->sources->join(', ') }}</span>
                                    @if (filled($job->apply_url))
                                        <a href="{{ $job->apply_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                                            Apply
                                            <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none"><path d="M4.5 11.5 11.25 4.75M5 4.75h6.25V11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">Link unavailable</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @elseif ($hasCompletedSearch)
                    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.7"/><path d="m16 16 4.5 4.5M8 10.8h5.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </div>
                        <p class="mt-4 text-base font-semibold text-slate-900">No matching jobs posted in the last 24 hours.</p>
                        <p class="mt-1 text-sm text-slate-500">Try refreshing later. Source availability is shown above.</p>
                    </div>
                @elseif ($checkedSourceCount > 0)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-10 text-center">
                        <p class="text-base font-semibold text-amber-950">Search in progress: {{ $checkedSourceCount }} of {{ $totalSourceCount }} sources checked.</p>
                        <p class="mt-1 text-sm text-amber-900">Browse the remaining sources above, mark each one searched, and add verified matches here.</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                        <p class="text-base font-semibold text-slate-900">Browse the job sites and add verified matches.</p>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Use each source link above, then mark it searched and add matching jobs. If a source provides an official RSS or Atom feed, you can configure it for automatic refresh. This dashboard does not use APIs or scrape job pages.</p>
                    </div>
                @endif
            </section>

            <footer class="mt-10 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500">
                Listings link to their original postings when a URL is available. Confirm that a role is still open and review its requirements before applying.
                Searches use official RSS/Atom feeds when configured or your manual review. No job-board APIs are called and no pages are scraped.
            </footer>
        </main>
    </div>
</body>
</html>
