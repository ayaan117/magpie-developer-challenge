<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeHelper
{
    /**
     * Build a hardened HTTP client.
     * - Uses local certs/cacert.pem if available; otherwise system CA.
     * - Realistic headers so the site serves full markup.
     * - Follows redirects, keeps cookies, doesn't throw on 4xx/5xx.
     */
    private static function client(): Client
    {
        $localCa = __DIR__ . '/../certs/cacert.pem';
        $verify  = is_file($localCa) ? $localCa : true; // fallback to system CA

        return new Client([
            'base_uri'        => 'https://www.magpiehq.com',
            'timeout'         => 20,
            'connect_timeout' => 10,
            'allow_redirects' => true,
            'cookies'         => true,
            'http_errors'     => false,     // don't throw; we still want the body
            'decode_content'  => true,      // gzip/br
            'verify'          => $verify,
            'headers'         => [
                'User-Agent'      => 'magpie-challenge-scraper/1.0 (+https://github.com/<your-handle>)',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en;q=0.9',
                'Cache-Control'   => 'no-cache',
                'Referer'         => 'https://www.magpiehq.com/developer-challenge/smartphones/',
            ],
        ]);
    }

    /**
     * Fetch a URL and return a DomCrawler (or null on failure).
     * If $debug = true, writes raw HTML to /debug for inspection.
     */
    public static function fetchDocument(string $url, bool $debug = false): ?Crawler
    {
        $client = self::client();

        try {
            $resp   = $client->get($url);
            $status = $resp->getStatusCode();
            $html   = (string) $resp->getBody();

            // Normalise to UTF-8 so regex/text ops behave consistently
            if (!mb_detect_encoding($html, 'UTF-8', true)) {
                $html = mb_convert_encoding($html, 'UTF-8');
            }

            if ($debug) {
                self::writeDebug($url, $status, $html);
            }

            if ($html !== '') {
                libxml_use_internal_errors(true); // lenient HTML parsing
                return new Crawler($html, $url);
            }

            fwrite(STDERR, "⚠️ Empty response for {$url} (HTTP {$status})\n");
        } catch (RequestException $e) {
            fwrite(STDERR, "❌ Request failed for {$url}: {$e->getMessage()}\n");
        }

        return null;
    }

    private static function writeDebug(string $url, int $status, string $html): void
    {
        $dir = __DIR__ . '/../debug';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $safe = preg_replace('/[^a-z0-9]+/i', '_', $url);
        $path = "{$dir}/net_{$safe}.html";
        file_put_contents($path, "<!-- HTTP {$status} {$url} -->\n" . $html);
    }
}
