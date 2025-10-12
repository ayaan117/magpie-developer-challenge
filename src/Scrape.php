<?php

namespace App;

require 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

class Scrape
{
    private const DEBUG = false;
    private array $products = [];

    public function run(): void
    {
        echo "🔍 Starting scrape...\n";
        $page = 1;

        while (true) {
            $url = $page === 1
                ? 'https://www.magpiehq.com/developer-challenge/smartphones/'
                : "https://www.magpiehq.com/developer-challenge/smartphones/?page={$page}";

            echo "📄 Fetching: {$url}\n";
            $crawler = ScrapeHelper::fetchDocument($url, self::DEBUG);
            if (!$crawler) break;

            $found = $this->parseProducts($crawler);
            if (empty($found)) break;

            $this->products = array_merge($this->products, $found);

            $max = $this->extractMaxPagesText($crawler) ?? $this->extractMaxPagesLinks($crawler);
            if ($max !== null && $page >= $max) break;

            $page++;
        }

        $this->products = $this->dedupe($this->products);
        $normalized = array_map(fn($p) => (new Product($p))->toArray(), $this->products);

        file_put_contents('output.json', json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "✅ Wrote " . count($normalized) . " products to output.json\n";
    }

    private function parseProducts(Crawler $crawler): array
    {
        $results = [];

        // --- Card layout (.product) ---
        if ($crawler->filter('.product')->count() > 0) {
            $crawler->filter('.product')->each(function (Crawler $card) use (&$results) {
                $name = trim($this->safeText($card, '.product-name'));
                $capacityLabel = trim($this->safeText($card, '.product-capacity'));
                $title = trim("$name $capacityLabel");
                if ($title === '') return;

                $img = $this->safeAttr($card, 'img', 'src');
                $priceText = trim($this->safeText($card, '.my-8.block.text-center.text-lg'));
                $price = $this->parsePrice($priceText);

                // Get first colour if multiple
                $colour = null;
                $card->filter('[data-colour]')->each(function (Crawler $c) use (&$colour) {
                    if ($colour === null && $c->attr('data-colour')) {
                        $colour = strtolower(trim($c->attr('data-colour')));
                    }
                });

                // Availability + Shipping
                $availabilityText = null;
                $shippingText = null;
                $card->filter('.my-4.text-sm.block.text-center')->each(function (Crawler $line) use (&$availabilityText, &$shippingText) {
                    $txt = trim($line->text(''));
                    if ($txt === '') return;

                    if (stripos($txt, 'Availability:') === 0) {
                        $availabilityText = trim(substr($txt, strlen('Availability:')));
                        return;
                    }

                    if (preg_match('/\b(Delivery|Available on|Free Shipping|Free Delivery|Unavailable for delivery)\b/i', $txt)) {
                        if ($shippingText === null) $shippingText = $txt;
                    }
                });

                $results[] = $this->buildProduct($title, $price, $img, $availabilityText, $shippingText, $colour);
            });

            if (!empty($results)) return $results;
        }

        // --- Fallback: blob scraping if cards missing (future proof) ---
        $crawler->filter('h3')->each(function (Crawler $h3, $idx) use (&$results) {
            $title = trim($h3->text(''));
            if ($title === '') return;

            $node = $h3->getNode(0);
            if (!$node) return;

            [$blob, $imageUrl] = $this->collectBlobAndImage($node);
            if (self::DEBUG) $this->debugBlob($title, $idx, $blob);

            $price            = $this->parsePrice($blob);
            $availabilityText = $this->parseAvailability($blob);
            $shippingText     = $this->parseShippingText($blob);

            $results[] = $this->buildProduct($title, $price, $imageUrl, $availabilityText, $shippingText);
        });

        return $results;
    }

    private function buildProduct(
        string $title,
        ?float $price,
        ?string $imageUrl,
        ?string $availabilityText,
        ?string $shippingText,
        ?string $colour = null
    ): array {
        $capacityMB   = $this->extractCapacityMB($title);
        $colour       = $colour ?: $this->extractColour($title);
        $isAvailable  = $this->computeIsAvailable($availabilityText);
        $shippingDate = $this->extractShippingDate($shippingText);

        return [
            'title'            => $title,
            'price'            => $price,
            'imageUrl'         => $this->absUrl($imageUrl),
            'capacityMB'       => $capacityMB,
            'colour'           => $colour,
            'availabilityText' => $availabilityText,
            'isAvailable'      => $isAvailable,
            'shippingText'     => $shippingText,
            'shippingDate'     => $shippingDate,
        ];
    }

    // ---------- Price Fix ----------
    private function parsePrice(?string $text): ?float
    {
        if (!$text) return null;

        // 1) Prefer £ amounts
        if (preg_match_all('/£\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{2})?)/', $text, $m) && !empty($m[1])) {
            $cand = $m[1][0];
            $num = str_replace([',', ' '], '', $cand);
            return is_numeric($num) ? (float)$num : null;
        }

        // 2) Fallback to longest numeric pattern (ignore years)
        if (preg_match_all('/([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{2})?)/', $text, $m) && !empty($m[1])) {
            usort($m[1], fn($a, $b) => strlen(preg_replace('/\D/', '', $b)) <=> strlen(preg_replace('/\D/', '', $a)));
            foreach ($m[1] as $cand) {
                $num = str_replace([',', ' '], '', $cand);
                if (!((int)$num >= 1900 && (int)$num <= 2100) && is_numeric($num)) {
                    return (float)$num;
                }
            }
        }

        return null;
    }

    // ---------- Utility Helpers ----------

    private function extractMaxPagesText(Crawler $crawler): ?int
    {
        $txt = $crawler->filter('#products')->count() ? $crawler->filter('#products')->text('') : $crawler->text('');
        if (preg_match('/Page\s+\d+\s+of\s+(\d+)/i', $txt, $m)) return (int)$m[1];
        return null;
    }

    private function extractMaxPagesLinks(Crawler $crawler): ?int
    {
        if ($crawler->filter('#pages a')->count() === 0) return null;
        $max = 0;
        $crawler->filter('#pages a')->each(function (Crawler $a) use (&$max) {
            $n = (int)trim($a->text(''));
            if ($n > $max) $max = $n;
        });
        return $max ?: null;
    }

    private function parseAvailability(string $blob): ?string
    {
        if (preg_match('/Availability:\s*(.+)/i', $blob, $m)) return trim($m[1]);
        if (preg_match('/\b(In Stock[^\n]*)/i', $blob, $m)) return trim($m[1]);
        if (preg_match('/\b(Out of Stock[^\n]*)/i', $blob, $m)) return trim($m[1]);
        if (preg_match('/\b(Available[^\n]*)/i', $blob, $m)) return trim($m[1]);
        return null;
    }

    private function parseShippingText(string $blob): ?string
    {
        if (preg_match('/\b(Delivery(?:\s+(?:by|from))?[^\n]*|Available\s+on[^\n]*|Order\s+within[^\n]*)/i', $blob, $m))
            return trim($m[1]);
        if (preg_match('/\b(Free\s+(?:Delivery|Shipping)[^\n]*)/i', $blob, $m))
            return trim($m[1]);
        if (preg_match('/\bUnavailable for delivery\b/i', $blob, $m))
            return 'Unavailable for delivery';
        return null;
    }

    private function extractCapacityMB(string $title): ?int
    {
        if (preg_match('/(\d+)\s*GB\b/i', $title, $m)) return (int)$m[1] * 1024;
        if (preg_match('/(\d+)\s*MB\b/i', $title, $m)) return (int)$m[1];
        return null;
    }

    private function extractColour(string $title): ?string
    {
        $colours = ['black','white','red','blue','green','silver','gold','purple','pink','yellow','grey','gray','graphite','midnight','starlight'];
        foreach ($colours as $c) {
            if (preg_match('/\b' . preg_quote($c, '/') . '\b/i', $title)) return strtolower($c);
        }
        return null;
    }

    private function computeIsAvailable(?string $text): bool
    {
        if (!$text) return false;
        $t = strtolower($text);
        if (str_contains($t, 'out of stock')) return false;
        return str_contains($t, 'in stock') || (str_contains($t, 'available') && !str_contains($t, 'unavailable'));
    }

    private function extractShippingDate(?string $text): ?string
    {
        if (!$text) return null;
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $m)) return $m[1];
        if (preg_match('/(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})/', $text, $m)) {
            $ts = strtotime($m[1]);
            if ($ts !== false) return date('Y-m-d', $ts);
        }
        return null;
    }

    private function absUrl(?string $url): ?string
    {
        if (!$url) return null;
        if (preg_match('#^https?://#i', $url)) return $url;
        $base = 'https://www.magpiehq.com';
        $path = '/' . ltrim($url, '/');
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($segments); continue; }
            $segments[] = $seg;
        }
        return rtrim($base, '/') . '/' . implode('/', $segments);
    }

    private function safeText(Crawler $node, string $selector): string
    {
        return $node->filter($selector)->count()
            ? trim($node->filter($selector)->first()->text(''))
            : '';
    }

    private function safeAttr(Crawler $node, string $selector, string $attr): ?string
    {
        return $node->filter($selector)->count()
            ? $node->filter($selector)->first()->attr($attr)
            : null;
    }

    private function cleanText(string $s): string
    {
        $s = str_replace("\xC2\xA0", ' ', $s);
        return trim(preg_replace('/[ \t]+/u', ' ', $s));
    }

    private function dedupe(array $products): array
    {
        $seen = [];
        $unique = [];
        foreach ($products as $p) {
            $key = strtolower(($p['title'] ?? '') . '|' . ($p['capacityMB'] ?? '') . '|' . ($p['colour'] ?? ''));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $p;
            }
        }
        return $unique;
    }

    private function debugBlob(string $title, int $idx, string $blob): void
    {
        $dir = __DIR__ . '/../debug';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $safe = preg_replace('/[^a-z0-9]+/i', '_', $title) ?: 'product';
        file_put_contents(sprintf('%s/%03d_%s.txt', $dir, $idx + 1, $safe), $blob);
    }

    private function collectBlobAndImage(\DOMNode $h3Node): array
    {
        $blob = '';
        $imageUrl = null;
        $cursor = $h3Node->nextSibling;
        while ($cursor) {
            if ($cursor->nodeType === XML_ELEMENT_NODE && strtolower($cursor->nodeName) === 'h3') break;
            if ($cursor->nodeType === XML_TEXT_NODE) $blob .= "\n" . $this->cleanText($cursor->textContent ?? '');
            elseif ($cursor->nodeType === XML_ELEMENT_NODE) {
                /** @var \DOMElement $el */
                $el = $cursor;
                $blob .= "\n" . $this->cleanText($el->textContent ?? '');
                foreach ($el->getElementsByTagName('img') as $img) {
                    if (!$imageUrl && $img->hasAttribute('src')) $imageUrl = $img->getAttribute('src');
                }
            }
            $cursor = $cursor->nextSibling;
        }
        return [trim($blob), $imageUrl];
    }
}

// Run
$scrape = new Scrape();
$scrape->run();
