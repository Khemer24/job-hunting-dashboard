<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use UnexpectedValueException;

class JobRssFeed
{
    public function fetch(string $url): array
    {
        $response = Http::accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
            ->timeout(20)
            ->get($url)
            ->throw();
        $contents = $response->body();

        if (strlen($contents) > 2_000_000) {
            throw new UnexpectedValueException('The RSS/Atom feed exceeds the 2 MB size limit.');
        }

        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $feed = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        if (! $feed instanceof SimpleXMLElement) {
            throw new UnexpectedValueException('The source did not return valid RSS or Atom XML.');
        }

        if (isset($feed->channel)) {
            $items = $feed->channel->item;
        } elseif ($feed->getName() === 'feed') {
            $namespaces = $feed->getNamespaces(true);
            $atomNamespace = array_search('http://www.w3.org/2005/Atom', $namespaces, true);
            $items = $atomNamespace === false ? $feed->entry : $feed->children($atomNamespace)->entry;
        } else {
            throw new UnexpectedValueException('The source did not return an RSS channel or Atom feed.');
        }

        $jobs = [];
        foreach ($items as $item) {
            $jobs[] = $this->normalize($item, $feed->getName() === 'feed');
        }

        return $jobs;
    }

    private function normalize(SimpleXMLElement $item, bool $atom): array
    {
        $title = $this->field($item, ['title']);
        $company = $this->field($item, ['company', 'employer']);
        $jobUrl = $this->link($item, $atom);
        $postedAt = $this->field($item, ['pubDate', 'published', 'date']);
        $identity = $this->field($item, ['guid', 'id']) ?? $jobUrl;

        foreach ([
            'title' => $title,
            'company' => $company,
            'job link' => $jobUrl,
            'original publication date' => $postedAt,
            'stable listing ID or link' => $identity,
        ] as $field => $value) {
            if ($value === null || trim($value) === '') {
                throw new UnexpectedValueException("An RSS/Atom job is missing its {$field}.");
            }
        }

        $employmentType = Str::of($this->field($item, ['employment_type', 'employmentType']) ?? '')
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();
        $status = mb_strtolower($this->field($item, ['status']) ?? 'open');

        if (! in_array($status, ['open', 'closed'], true)) {
            throw new UnexpectedValueException('An RSS/Atom job status must be open or closed.');
        }

        return [
            'source_listing_key' => hash('sha256', trim($identity)),
            'entry_method' => 'feed',
            'title' => trim($title),
            'company' => trim($company),
            'job_url' => $this->validatedUrl($jobUrl, 'job link'),
            'employer_apply_url' => $this->optionalUrl($this->field($item, ['employer_apply_url', 'apply_url'])),
            'posted_at' => $this->postedAt($postedAt),
            'employment_type' => $this->employmentType($employmentType),
            'pay' => $this->field($item, ['pay', 'salary']),
            'description' => $this->field($item, ['description', 'content']),
            'qualifications' => $this->field($item, ['qualifications', 'requirements']) ?? '',
            'is_open' => $status === 'open',
            'is_remote' => $this->boolean($this->field($item, ['remote', 'is_remote'])),
            'accepts_philippines' => $this->boolean($this->field($item, ['accepts_philippines', 'accepts_ph', 'ph_applicants'])),
        ];
    }

    private function field(SimpleXMLElement $item, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($item->{$name})) {
                $value = $this->plainText((string) $item->{$name});
                if ($value !== '') {
                    return $value;
                }
            }

            foreach ($item->getNamespaces(true) as $namespace) {
                $children = $item->children($namespace);
                if (isset($children->{$name})) {
                    $value = $this->plainText((string) $children->{$name});
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function link(SimpleXMLElement $item, bool $atom): ?string
    {
        if ($atom) {
            $namespaces = $item->getNamespaces(true);
            $atomNamespace = array_search('http://www.w3.org/2005/Atom', $namespaces, true);
            $links = $atomNamespace === false ? $item->link : $item->children($atomNamespace)->link;

            foreach ($links as $link) {
                $href = trim((string) $link['href']);
                $rel = trim((string) $link['rel']);
                if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                    return $href;
                }
            }
        }

        return $this->field($item, ['link']);
    }

    private function validatedUrl(mixed $value, string $field): string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new UnexpectedValueException("A feed job has an invalid {$field}.");
        }

        return $value;
    }

    private function optionalUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->validatedUrl($value, 'employer_apply_url');
    }

    private function postedAt(string $value): string
    {
        try {
            return CarbonImmutable::parse($value, 'UTC')->utc()->toDateTimeString();
        } catch (\Throwable $exception) {
            throw new UnexpectedValueException('An RSS/Atom job has an invalid original publication date.', previous: $exception);
        }
    }

    private function boolean(?string $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['true', '1', 'yes', 'y'], true);
    }

    private function employmentType(string $value): string
    {
        return match ($value) {
            'full_time', 'fulltime' => 'full_time',
            'part_time', 'parttime' => 'part_time',
            'contract' => 'contract',
            default => 'other',
        };
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    }
}
