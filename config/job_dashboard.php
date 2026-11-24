<?php

return [
    'timezone' => 'Asia/Manila',
    'sources' => [
        'onlinejobs_ph' => [
            'label' => 'OnlineJobs.ph',
            'feed_url' => env('ONLINEJOBS_PH_RSS_URL'),
            'browse_url' => 'https://www.onlinejobs.ph/',
        ],
        'jobstreet' => [
            'label' => 'JobStreet',
            'feed_url' => env('JOBSTREET_RSS_URL'),
            'browse_url' => 'https://ph.jobstreet.com/',
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'feed_url' => env('LINKEDIN_RSS_URL'),
            'browse_url' => 'https://www.linkedin.com/jobs/search/?keywords=Laravel%20WordPress%20PHP&location=Philippines',
        ],
        'indeed' => [
            'label' => 'Indeed',
            'feed_url' => env('INDEED_RSS_URL'),
            'browse_url' => 'https://ph.indeed.com/',
        ],
    ],
    'resume_history' => [
        [
            'role' => 'WordPress Developer Intern',
            'employer' => 'WSAP',
            'start' => '2022-02-01',
            'end' => '2022-05-31',
            'skills' => ['wordpress', 'php', 'mysql', 'web_development'],
        ],
        [
            'role' => 'WordPress Developer',
            'employer' => 'Born Digital LTD',
            'start' => '2022-06-01',
            'end' => '2025-02-28',
            'skills' => [
                'wordpress',
                'wordpress_themes',
                'php',
                'woocommerce',
                'elementor',
                'payment_integrations',
                'third_party_apis',
                'web_development',
            ],
        ],
        [
            'role' => 'Backend Developer',
            'employer' => 'Born Digital LTD',
            'start' => '2025-02-01',
            'end' => null,
            'skills' => [
                'laravel',
                'php',
                'mysql',
                'rest_apis',
                'third_party_apis',
                'backend',
                'web_development',
            ],
        ],
    ],
    'skills' => [
        'laravel' => [
            'label' => 'Laravel',
            'aliases' => ['laravel'],
        ],
        'php' => [
            'label' => 'PHP',
            'aliases' => ['php'],
        ],
        'mysql' => [
            'label' => 'MySQL and SQL',
            'aliases' => ['mysql', 'sql'],
        ],
        'wordpress' => [
            'label' => 'WordPress',
            'aliases' => ['wordpress', 'wordpress developer'],
        ],
        'wordpress_themes' => [
            'label' => 'WordPress theme and plugin customization',
            'aliases' => ['wordpress theme', 'wordpress themes', 'wordpress plugin', 'wordpress plugins', 'custom theme', 'custom themes', 'custom plugin', 'custom plugins'],
        ],
        'woocommerce' => [
            'label' => 'WooCommerce',
            'aliases' => ['woocommerce'],
        ],
        'elementor' => [
            'label' => 'Elementor components',
            'aliases' => ['elementor widget', 'elementor widgets', 'elementor component', 'elementor components', 'elementor'],
        ],
        'payment_integrations' => [
            'label' => 'WooCommerce payment integrations',
            'aliases' => ['payment integration', 'payment integrations', 'payment gateway', 'payment gateways', 'woocommerce payment', 'woocommerce payments'],
        ],
        'rest_apis' => [
            'label' => 'REST API development',
            'aliases' => ['rest api', 'rest apis', 'restful api', 'restful apis'],
        ],
        'third_party_apis' => [
            'label' => 'third-party API integrations',
            'aliases' => ['third-party api', 'third-party apis', 'third party api', 'third party apis', 'api integration', 'api integrations', 'external api', 'external apis'],
        ],
        'backend' => [
            'label' => 'backend development',
            'aliases' => ['backend', 'back-end', 'server-side'],
        ],
        'web_development' => [
            'label' => 'web development',
            'aliases' => ['web development', 'web developer', 'web developers'],
        ],
    ],
];
