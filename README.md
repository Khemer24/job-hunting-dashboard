# Philippines Remote Job Match

A single-page Laravel, Blade, and Tailwind dashboard that lists verified open full-time and part-time remote jobs posted in the last 24 hours, explicitly open to applicants in the Philippines, and matched to the résumé work history in `config/job_dashboard.php`.

**No job-search APIs are used.** The app can read a source's official RSS/Atom feed when one is available. For sources without a suitable feed, browse the source site, mark it searched, and add jobs you have verified yourself. It does not automate or scrape job-board pages.

The matching is explainable and experience-aware: it checks technologies and duties against the résumé's dated roles, and rejects explicit technology-specific or general years requirements when the dated experience does not meet them. Remote eligibility and acceptance of Philippine applicants must be explicitly confirmed by a feed or by you in the manual-entry form.

## Requirements and local setup

- PHP 8.3 or later with SQLite or MySQL enabled
- Composer
- Node.js and npm

From the project directory:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. Timestamps are stored in UTC and displayed in Philippine time (`Asia/Manila`).

## Manual search workflow

1. Use **Browse jobs** on the OnlineJobs.ph, JobStreet, LinkedIn, or Indeed card.
2. Verify the original listing, posting date, open status, remote arrangement, Philippines eligibility, and employment type.
3. Use **Mark searched** on that source when you finish checking it.
4. Add eligible listings in **Add a manually verified job**. The form requires confirmation that the role is open, fully remote, and accepts applicants in the Philippines.

The employer name and original posting URL are optional for manual entries. If no employer is listed, the dashboard displays “Not listed”; if neither an original nor direct application URL is available, the title is not linked and the Apply column says “Link unavailable.” Re-entering a listing with its URL preserves the original posting date.

The dashboard considers a search complete after all four sources have been checked in the last 24 hours, either manually or through a configured feed.

## Optional official RSS/Atom feeds

No feed URLs are supplied by default. Some job boards may not offer a public feed suitable for job search; leave those settings blank and use the manual workflow. Do not use unofficial endpoints or feeds that violate a provider's terms.

To configure a feed, add its official RSS/Atom URL to `.env`:

| Source | Environment variable |
| --- | --- |
| OnlineJobs.ph | `ONLINEJOBS_PH_RSS_URL` |
| JobStreet | `JOBSTREET_RSS_URL` |
| LinkedIn | `LINKEDIN_RSS_URL` |
| Indeed | `INDEED_RSS_URL` |

No API keys or bearer tokens are used. The feed must be a complete snapshot of currently open listings and provide the original job link, title, company, stable ID or stable link, and original publication date. Job entries can include RSS/Atom fields (or namespaced fields) for `employment_type`, `remote`, `accepts_philippines`, `status`, `pay`, `employer_apply_url`, `description`, and `qualifications`. Listings without explicit remote and Philippines eligibility metadata are excluded from feed matches; verify them and add them manually instead.

Example RSS item:

```xml
<item>
  <guid>provider-listing-123</guid>
  <title>Laravel Backend Developer</title>
  <company>Example Company</company>
  <link>https://provider.example/jobs/123</link>
  <pubDate>Tue, 24 Nov 2026 09:15:00 +0800</pubDate>
  <employment_type>full-time</employment_type>
  <remote>true</remote>
  <accepts_philippines>true</accepts_philippines>
  <status>open</status>
  <description>Build backend services and REST APIs.</description>
  <qualifications>Laravel experience; MySQL</qualifications>
</item>
```

If a provider offers a feed that lacks those fields, the app cannot verify eligibility from it; use manual review. After changing `.env`, clear cached configuration if needed:

```powershell
php artisan config:clear
```

Feed refresh failures appear separately from other sources and do not erase prior records. Feed refreshes preserve each existing listing's original date. Duplicate listings are merged by normalized company and title, preferring an employer application link when one is supplied; titles link to the original posting when its URL is available.

## Résumé matching profile

The included profile was transcribed from the uploaded résumé:

- WordPress Developer Intern at WSAP, February–May 2022
- WordPress Developer at Born Digital LTD, June 2022–February 2025
- Backend Developer at Born Digital LTD, February 2025–present
- Includes PHP, MySQL/SQL, WordPress themes/plugins, WooCommerce and payment integrations, Elementor widgets/components, third-party APIs, Laravel, REST APIs, backend development, and web development.

Update the dated entries and skill aliases in `config/job_dashboard.php` whenever the résumé changes. Do not enter experience that is not supported by the work history. Laravel experience is measured from February 2025.

## Private access

The dashboard is intended for local or otherwise private access. Before deploying to the public internet, add authentication and rate limiting to protect the personalized résumé and manual-entry/refresh endpoints.

## Tests

```powershell
php artisan test
```
