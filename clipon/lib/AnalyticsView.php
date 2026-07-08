<?php

class AnalyticsView {
    private AnalyticsReport $report;

    public function __construct(AnalyticsReport $report) {
        $this->report = $report;
    }

    public function getDemoStats(): array {
        $days = 30;
        $daily = [];
        $total_hits = 0;
        $total_uniques = 0;

        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $h = rand(150, 300);
            $u = rand(80, 140);
            $daily[$date] = ['hits' => $h, 'uniques' => $u, 'conversions' => rand(2, 8)];
            $total_hits += $h;
            $total_uniques += $u;
        }

        return [
            'total_hits' => $total_hits,
            'total_uniques' => $total_uniques,
            'daily' => $daily,
            'pages' => ['/' => 450, '/blog' => 320, '/pricing' => 180, '/contact' => 95],
            'referrers' => ['google' => 400, 'direct' => 300, 'facebook' => 150, 'twitter' => 80],
            'devices' => ['desktop' => 600, 'mobile' => 350, 'tablet' => 50],
            'conversions' => ['total' => rand(50, 100)],
            'is_demo' => true
        ];
    }

    /**
     * Demo funnel definitions for the Funnels tab when no real config exists (unlicensed UI).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getMockFunnelsList(): array {
        return [
            [
                'id' => 'mock-funnel-purchase',
                'name' => 'Purchase funnel',
                'steps' => ['/', '/pricing', '/thank-you'],
                'ordered' => true,
            ],
            [
                'id' => 'mock-funnel-signup',
                'name' => 'Signup funnel',
                'steps' => ['/blog', '/signup', '/welcome'],
                'ordered' => true,
            ],
            [
                'id' => 'mock-funnel-explore',
                'name' => 'Content exploration',
                'steps' => ['/', '/blog', '/about', '/contact'],
                'ordered' => false,
            ],
        ];
    }

    public function getMockData(string $from, string $to): array {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $startTs = strtotime($from);
        $endTs = strtotime($to);
        if ($startTs === false || $endTs === false) {
            $endTs = strtotime(date('Y-m-d'));
            $startTs = strtotime('-30 days', $endTs);
        }

        $daily = [];
        $totalHits = 0;
        $totalUniques = 0;

        for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
            $date = date('Y-m-d', $ts);
            $hits = $this->seededInt('mock:hits:' . $date, 140, 320);
            $uniques = min($hits, $this->seededInt('mock:uniques:' . $date, 75, 190));
            $conv = $this->seededInt('mock:conv:' . $date, 2, 11);

            $daily[$date] = [
                'hits' => $hits,
                'uniques' => $uniques,
                'conversions' => $conv
            ];

            $totalHits += $hits;
            $totalUniques += $uniques;
        }

        $entryPages = [
            '/' => $this->seededInt('mock:entry:home', 180, 420),
            '/blog' => $this->seededInt('mock:entry:blog', 90, 240),
            '/pricing' => $this->seededInt('mock:entry:pricing', 60, 160),
            '/contact' => $this->seededInt('mock:entry:contact', 25, 90),
        ];

        $bounceCount = (int)round(array_sum($entryPages) * ($this->seededInt('mock:bounce_pct', 28, 48) / 100));

        $mockPaths = [
            ['/', '/pricing', '/thank-you'],
            ['/', '/blog', '/pricing'],
            ['/blog', '/signup', '/welcome'],
            ['/', '/about', '/contact'],
        ];

        $funnelCompleted = [];
        foreach ($mockPaths as $i => $path) {
            $key = implode(' > ', $path);
            $funnelCompleted[$key] = $this->seededInt('mock:funnel:path:' . $i, 8, 42);
        }

        $funnelRecent = [];
        for ($i = 0; $i < 12; $i++) {
            $path = $mockPaths[$i % count($mockPaths)];
            $funnelRecent[] = [
                'path' => $path,
                'type' => $i % 2 === 0 ? 'lead' : 'purchase',
                'ts' => $endTs - ($i * 7200),
            ];
        }

        $conversionRecent = [];
        $convTypes = ['lead', 'signup', 'purchase'];
        $convUris = ['/pricing', '/contact', '/thank-you', '/signup'];
        $convRefs = ['google', 'direct', 'facebook', 'instagram'];
        for ($i = 0; $i < 15; $i++) {
            $conversionRecent[] = [
                'uri' => $convUris[$i % count($convUris)],
                'type' => $convTypes[$i % count($convTypes)],
                'ts' => $endTs - ($i * 5400),
                'utm' => [
                    'utm_source' => ['google', 'facebook', 'newsletter'][$i % 3],
                    'utm_medium' => ['cpc', 'organic', 'email'][$i % 3],
                    'utm_campaign' => ['spring_sale', 'brand', 'retarget'][$i % 3],
                ],
                'ref' => $convRefs[$i % count($convRefs)],
            ];
        }

        $attributionRecent = [];
        for ($i = 0; $i < 12; $i++) {
            $attributionRecent[] = [
                'uri' => $convUris[$i % count($convUris)],
                'type' => $convTypes[$i % count($convTypes)],
                'first' => ['google', 'facebook', 'newsletter'][$i % 3],
                'last' => ['direct', 'google', 'instagram'][$i % 3],
                'ts' => $endTs - ($i * 6300),
            ];
        }

        return [
            'total_hits' => $totalHits,
            'total_uniques' => $totalUniques,
            'bounce_count' => $bounceCount,
            'pages' => [
                '/' => $this->seededInt('mock:page:home', 280, 520),
                '/blog' => $this->seededInt('mock:page:blog', 170, 380),
                '/pricing' => $this->seededInt('mock:page:pricing', 120, 260),
                '/contact' => $this->seededInt('mock:page:contact', 60, 160),
                '/about' => $this->seededInt('mock:page:about', 50, 130)
            ],
            'top_pages' => [
                '/' => $this->seededInt('mock:top:home', 220, 450),
                '/blog' => $this->seededInt('mock:top:blog', 160, 320),
                '/pricing' => $this->seededInt('mock:top:pricing', 110, 240),
                '/contact' => $this->seededInt('mock:top:contact', 45, 120),
                '/about' => $this->seededInt('mock:top:about', 35, 95)
            ],
            'referrers' => [
                'google' => $this->seededInt('mock:ref:google', 260, 520),
                'direct' => $this->seededInt('mock:ref:direct', 180, 380),
                'facebook' => $this->seededInt('mock:ref:facebook', 90, 210),
                'instagram' => $this->seededInt('mock:ref:instagram', 70, 180)
            ],
            'devices' => [
                'desktop' => $this->seededInt('mock:dev:desktop', 380, 700),
                'mobile' => $this->seededInt('mock:dev:mobile', 210, 520),
                'tablet' => $this->seededInt('mock:dev:tablet', 20, 90)
            ],
            'languages' => [
                'uk' => $this->seededInt('mock:lang:uk', 220, 480),
                'en' => $this->seededInt('mock:lang:en', 140, 320),
                'pl' => $this->seededInt('mock:lang:pl', 30, 90),
            ],
            'countries' => [
                'UA' => $this->seededInt('mock:geo:ua', 200, 450),
                'US' => $this->seededInt('mock:geo:us', 80, 200),
                'PL' => $this->seededInt('mock:geo:pl', 40, 120),
                'DE' => $this->seededInt('mock:geo:de', 35, 100),
            ],
            'entry_pages' => $entryPages,
            'exit_pages' => [
                '/thank-you' => $this->seededInt('mock:exit:thanks', 45, 120),
                '/contact' => $this->seededInt('mock:exit:contact', 35, 95),
                '/pricing' => $this->seededInt('mock:exit:pricing', 30, 85),
                '/blog' => $this->seededInt('mock:exit:blog', 25, 70),
            ],
            'time_on_page' => [
                '/' => ['t' => $this->seededInt('mock:time:home:t', 800, 2400), 'c' => $this->seededInt('mock:time:home:c', 40, 120)],
                '/blog' => ['t' => $this->seededInt('mock:time:blog:t', 600, 1800), 'c' => $this->seededInt('mock:time:blog:c', 30, 90)],
                '/pricing' => ['t' => $this->seededInt('mock:time:pricing:t', 500, 1500), 'c' => $this->seededInt('mock:time:pricing:c', 25, 75)],
                '/contact' => ['t' => $this->seededInt('mock:time:contact:t', 200, 800), 'c' => $this->seededInt('mock:time:contact:c', 15, 45)],
            ],
            'utm' => [
                'utm_source' => [
                    'google' => $this->seededInt('mock:utm:src:google', 120, 280),
                    'facebook' => $this->seededInt('mock:utm:src:facebook', 60, 160),
                    'newsletter' => $this->seededInt('mock:utm:src:newsletter', 25, 80),
                ],
                'utm_medium' => [
                    'cpc' => $this->seededInt('mock:utm:med:cpc', 90, 220),
                    'organic' => $this->seededInt('mock:utm:med:organic', 110, 260),
                    'email' => $this->seededInt('mock:utm:med:email', 20, 70),
                ],
                'utm_campaign' => [
                    'spring_sale' => $this->seededInt('mock:utm:cmp:spring', 50, 140),
                    'brand' => $this->seededInt('mock:utm:cmp:brand', 40, 110),
                    'retarget' => $this->seededInt('mock:utm:cmp:retarget', 25, 85),
                ],
            ],
            'events' => [
                'scroll' => [
                    '/' => [
                        '25%' => $this->seededInt('mock:scroll:home:25', 35, 80),
                        '50%' => $this->seededInt('mock:scroll:home:50', 28, 65),
                        '75%' => $this->seededInt('mock:scroll:home:75', 18, 45),
                        '100%' => $this->seededInt('mock:scroll:home:100', 10, 30),
                    ],
                    '/pricing' => [
                        '25%' => $this->seededInt('mock:scroll:pricing:25', 20, 55),
                        '50%' => $this->seededInt('mock:scroll:pricing:50', 16, 42),
                        '75%' => $this->seededInt('mock:scroll:pricing:75', 12, 35),
                        '100%' => $this->seededInt('mock:scroll:pricing:100', 8, 25),
                    ],
                    '/blog' => [
                        '25%' => $this->seededInt('mock:scroll:blog:25', 22, 58),
                        '50%' => $this->seededInt('mock:scroll:blog:50', 18, 48),
                        '75%' => $this->seededInt('mock:scroll:blog:75', 14, 38),
                        '100%' => $this->seededInt('mock:scroll:blog:100', 9, 28),
                    ],
                ],
            ],
            'daily' => $daily,
            'conversions' => [
                'total' => $this->seededInt('mock:conversions:total', 45, 130),
                'pages' => [
                    '/pricing' => $this->seededInt('mock:cp:pricing', 20, 55),
                    '/contact' => $this->seededInt('mock:cp:contact', 15, 40),
                    '/signup' => $this->seededInt('mock:cp:signup', 8, 26)
                ],
                'types' => [
                    'lead' => $this->seededInt('mock:ct:lead', 20, 65),
                    'signup' => $this->seededInt('mock:ct:signup', 12, 42),
                    'purchase' => $this->seededInt('mock:ct:purchase', 6, 20)
                ],
                'recent' => $conversionRecent,
            ],
            'funnels' => [
                'completed' => $funnelCompleted,
                'recent' => $funnelRecent,
            ],
            'attribution' => [
                'first_touch' => [
                    'google' => $this->seededInt('mock:attr:first:google', 20, 60),
                    'facebook' => $this->seededInt('mock:attr:first:facebook', 8, 32),
                    'newsletter' => $this->seededInt('mock:attr:first:newsletter', 5, 22),
                ],
                'last_touch' => [
                    'direct' => $this->seededInt('mock:attr:last:direct', 12, 44),
                    'google' => $this->seededInt('mock:attr:last:google', 10, 36),
                    'instagram' => $this->seededInt('mock:attr:last:instagram', 6, 24),
                ],
                'recent' => $attributionRecent,
            ],
            'is_mock' => true
        ];
    }

    private function seededInt(string $seed, int $min, int $max): int {
        if ($max <= $min) {
            return $min;
        }

        $hash = hexdec(substr(sha1($seed), 0, 8));
        return $min + ($hash % ($max - $min + 1));
    }
}
