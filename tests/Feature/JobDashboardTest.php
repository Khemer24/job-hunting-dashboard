<?php

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\JobRefreshRun;
use App\Models\JobSourceCheck;
use App\Services\ResumeMatcher;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-11-24T09:00:00+00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rss_refresh_merges_duplicate_jobs_prefers_employer_link_and_preserves_original_date(): void
    {
        $feeds = $this->configuredFeeds();
        $feeds['https://feeds.test/onlinejobs'] = $this->rssFeed([$this->job([
            'source_job_id' => 'oj-1',
            'job_url' => 'https://onlinejobs.ph/job/1',
            'posted_at' => '2026-11-24T08:30:00+00:00',
        ])]);
        $feeds['https://feeds.test/jobstreet'] = $this->rssFeed([$this->job([
            'source_job_id' => 'js-1',
            'job_url' => 'https://jobstreet.com/job/1',
            'employer_apply_url' => 'https://acme.test/careers/1',
            'posted_at' => '2026-11-24T08:00:00+00:00',
        ])]);
        $this->fakeFeeds($feeds);

        $this->post(route('jobs.refresh'))->assertRedirect(route('jobs.index'));

        $this->assertDatabaseCount('job_listings', 2);
        $this->assertTrue(JobRefreshRun::firstOrFail()->successful);
        $this->assertSame('success', JobSourceCheck::where('source', 'linkedin')->value('status'));
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('match')
            ->assertSee('Not listed')
            ->assertSee('REST API development')
            ->assertSee('Nov 24, 2026 · 4:30 PM')
            ->assertSee('Nov 24, 2026 · 5:00 PM PHT')
            ->assertSee('https://acme.test/careers/1')
            ->assertSee('OnlineJobs.ph, JobStreet');

        $feeds['https://feeds.test/onlinejobs'] = $this->rssFeed([$this->job([
            'source_job_id' => 'oj-1',
            'job_url' => 'https://onlinejobs.ph/job/1',
            'posted_at' => '2026-11-24T08:59:00+00:00',
        ])]);
        $this->fakeFeeds($feeds);
        $this->post(route('jobs.refresh'));

        $stored = JobListing::where('source', 'onlinejobs_ph')->firstOrFail();
        $this->assertSame('2026-11-24 08:30:00', $stored->posted_at->format('Y-m-d H:i:s'));
    }

    public function test_manual_review_and_entry_match_resume_and_keep_original_posting_date(): void
    {
        $data = $this->manualJob();

        $this->post(route('jobs.store'), $data)->assertRedirect(route('jobs.index'));

        $this->assertDatabaseHas('job_listings', [
            'source' => 'linkedin',
            'entry_method' => 'manual',
            'posted_at' => '2026-11-24 08:30:00',
        ]);
        $this->assertSame('manual', JobSourceCheck::where('source', 'linkedin')->value('status'));
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('WooCommerce Developer')
            ->assertSee('Born Digital LTD')
            ->assertSee('https://employer.example/apply/42')
            ->assertSee('Manually checked')
            ->assertDontSee('name="company"')
            ->assertSee('Partial results: 1 of 4 sources checked');

        $this->assertDatabaseHas('job_listings', [
            'job_url' => 'https://linkedin.example/jobs/42',
            'company' => 'Not listed',
        ]);

        $data['posted_at'] = '2026-11-24T17:00';
        $this->post(route('jobs.store'), $data);
        $this->assertDatabaseCount('job_listings', 1);
        $this->assertSame(
            '2026-11-24 08:30:00',
            JobListing::firstOrFail()->posted_at->format('Y-m-d H:i:s'),
        );

        $secondListing = $data;
        $secondListing['job_url'] = 'https://linkedin.example/jobs/43';
        $secondListing['posted_at'] = '2026-11-24T16:00';
        $this->post(route('jobs.store'), $secondListing);

        $this->assertDatabaseCount('job_listings', 2);
        $this->get(route('jobs.index'))->assertSee('https://linkedin.example/jobs/43');
    }

    public function test_manual_entry_does_not_require_original_posting_or_application_url(): void
    {
        $data = $this->manualJob();
        unset($data['job_url']);
        $data['employer_apply_url'] = null;

        $this->post(route('jobs.store'), $data)->assertRedirect(route('jobs.index'));

        $this->assertDatabaseHas('job_listings', [
            'source' => 'linkedin',
            'job_url' => null,
            'employer_apply_url' => null,
        ]);
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('WooCommerce Developer')
            ->assertSee('Link unavailable');
    }

    public function test_atom_feeds_are_supported_and_philippines_eligibility_must_be_explicit(): void
    {
        $feeds = $this->configuredFeeds();
        $feeds['https://feeds.test/linkedin'] = $this->atomFeed([$this->job()]);
        $feeds['https://feeds.test/onlinejobs'] = $this->atomFeed([$this->job([
            'source_job_id' => 'not-ph-eligible',
            'title' => 'Laravel Developer - not open to PH applicants',
            'accepts_philippines' => false,
        ])]);
        $this->fakeFeeds($feeds);

        $this->post(route('jobs.refresh'));

        $this->assertSame('success', JobSourceCheck::where('source', 'linkedin')->value('status'));
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('Laravel API Developer')
            ->assertDontSee('Laravel Developer - not open to PH applicants');
    }

    public function test_search_completed_manually_across_all_sources_shows_empty_state(): void
    {
        foreach (array_keys(config('job_dashboard.sources')) as $source) {
            $this->post(route('sources.checked', $source))->assertRedirect(route('jobs.index'));
        }

        $this->assertTrue(JobRefreshRun::where('successful', true)->exists());
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('No matching jobs posted in the last 24 hours.')
            ->assertSee('Last successful refresh:')
            ->assertSee('5:00 PM PHT');
    }

    public function test_feed_failure_is_shown_separately_and_search_remains_incomplete(): void
    {
        $feeds = $this->configuredFeeds();
        Http::fake(function (Request $request) use ($feeds) {
            if ($request->url() === 'https://feeds.test/indeed') {
                return Http::response('temporarily unavailable', 503);
            }

            return Http::response($feeds[$request->url()] ?? $this->rssFeed([]));
        });

        $this->post(route('jobs.refresh'));
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Search in progress: 3 of 4 sources checked.')
            ->assertSee('Feed unavailable')
            ->assertSee('HTTP 503');
    }

    public function test_matcher_checks_skill_specific_and_overall_experience(): void
    {
        $matcher = app(ResumeMatcher::class);
        $woocommerceJob = new JobListing([
            'title' => 'WooCommerce Developer',
            'description' => 'Build WooCommerce payment integrations and Elementor widgets.',
            'qualifications' => '',
        ]);
        $laravelJob = new JobListing([
            'title' => 'Laravel Developer',
            'description' => 'Build Laravel APIs.',
            'qualifications' => 'Requires 2+ years of Laravel experience.',
        ]);
        $generalExperienceJob = new JobListing([
            'title' => 'Web Developer',
            'description' => 'Build PHP and WordPress sites.',
            'qualifications' => 'Requires 5+ years of professional experience.',
        ]);

        $match = $matcher->match($woocommerceJob);
        $this->assertTrue($match['is_match']);
        $this->assertStringContainsString('Born Digital LTD', implode(' ', $match['reasons']));
        $this->assertFalse($matcher->match($laravelJob)['is_match']);
        $this->assertFalse($matcher->match($generalExperienceJob)['is_match']);
    }

    private function configuredFeeds(): array
    {
        $sources = [
            'onlinejobs_ph' => ['label' => 'OnlineJobs.ph', 'feed_url' => 'https://feeds.test/onlinejobs', 'browse_url' => 'https://onlinejobs.ph'],
            'jobstreet' => ['label' => 'JobStreet', 'feed_url' => 'https://feeds.test/jobstreet', 'browse_url' => 'https://jobstreet.com'],
            'linkedin' => ['label' => 'LinkedIn', 'feed_url' => 'https://feeds.test/linkedin', 'browse_url' => 'https://linkedin.com'],
            'indeed' => ['label' => 'Indeed', 'feed_url' => 'https://feeds.test/indeed', 'browse_url' => 'https://indeed.com'],
        ];
        config(['job_dashboard.sources' => $sources]);

        return array_fill_keys(array_column($sources, 'feed_url'), $this->rssFeed([]));
    }

    private function fakeFeeds(array $feeds): void
    {
        Http::fake(function (Request $request) use (&$feeds) {
            return Http::response($feeds[$request->url()] ?? $this->rssFeed([]), 200, [
                'Content-Type' => 'application/rss+xml',
            ]);
        });
    }

    private function rssFeed(array $jobs): string
    {
        $items = array_map(function (array $job): string {
            $escape = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $description = '<description><![CDATA['.$job['description'].']]></description>';
            $applyUrl = $job['employer_apply_url'] === null
                ? ''
                : '<employer_apply_url>'.$escape($job['employer_apply_url']).'</employer_apply_url>';
            $pay = $job['pay'] === null ? '' : '<pay>'.$escape($job['pay']).'</pay>';

            return '<item>'
                .'<guid>'.$escape($job['source_job_id']).'</guid>'
                .'<title>'.$escape($job['title']).'</title>'
                .'<company>'.$escape($job['company']).'</company>'
                .'<link>'.$escape($job['job_url']).'</link>'
                .'<pubDate>'.CarbonImmutable::parse($job['posted_at'])->format('D, d M Y H:i:s O').'</pubDate>'
                .'<employment_type>'.$escape($job['employment_type']).'</employment_type>'
                .'<status>'.$escape($job['status']).'</status>'
                .'<remote>'.($job['remote'] ? 'true' : 'false').'</remote>'
                .'<accepts_philippines>'.($job['accepts_philippines'] ? 'true' : 'false').'</accepts_philippines>'
                .$applyUrl.$pay.$description
                .'<qualifications>'.$escape($job['qualifications']).'</qualifications>'
                .'</item>';
        }, $jobs);

        return '<rss version="2.0"><channel><title>Test jobs</title>'.implode('', $items).'</channel></rss>';
    }

    private function atomFeed(array $jobs): string
    {
        $items = array_map(function (array $job): string {
            $escape = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return '<entry>'
                .'<id>'.$escape($job['source_job_id']).'</id>'
                .'<title>'.$escape($job['title']).'</title>'
                .'<link rel="alternate" href="'.$escape($job['job_url']).'"/>'
                .'<company>'.$escape($job['company']).'</company>'
                .'<published>'.CarbonImmutable::parse($job['posted_at'])->utc()->toIso8601String().'</published>'
                .'<employment_type>'.$escape($job['employment_type']).'</employment_type>'
                .'<status>'.$escape($job['status']).'</status>'
                .'<remote>'.($job['remote'] ? 'true' : 'false').'</remote>'
                .'<accepts_philippines>'.($job['accepts_philippines'] ? 'true' : 'false').'</accepts_philippines>'
                .'<content type="text"><![CDATA['.$job['description'].']]></content>'
                .'<qualifications>'.$escape($job['qualifications']).'</qualifications>'
                .'</entry>';
        }, $jobs);

        return '<feed xmlns="http://www.w3.org/2005/Atom"><title>Test jobs</title>'.implode('', $items).'</feed>';
    }

    private function job(array $overrides = []): array
    {
        return array_replace([
            'source_job_id' => 'job-1',
            'title' => 'Laravel API Developer',
            'company' => 'Acme Labs',
            'job_url' => 'https://jobs.test/job/1',
            'employer_apply_url' => null,
            'posted_at' => '2026-11-24T08:30:00+00:00',
            'employment_type' => 'full-time',
            'pay' => null,
            'status' => 'open',
            'remote' => true,
            'accepts_philippines' => true,
            'description' => 'Build Laravel REST APIs using PHP and MySQL.',
            'qualifications' => 'Experience with REST APIs and PHP.',
        ], $overrides);
    }

    private function manualJob(): array
    {
        return [
            'source' => 'linkedin',
            'title' => 'WooCommerce Developer',
            'job_url' => 'https://linkedin.example/jobs/42',
            'employer_apply_url' => 'https://employer.example/apply/42',
            'posted_at' => '2026-11-24T16:30',
            'employment_type' => 'full_time',
            'pay' => null,
            'description' => 'Build WooCommerce stores and integrate payment gateways.',
            'qualifications' => 'Elementor experience preferred.',
            'is_open' => '1',
            'is_remote' => '1',
            'accepts_philippines' => '1',
        ];
    }
}
