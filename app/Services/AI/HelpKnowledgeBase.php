<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Kho kiến thức "Hướng dẫn sử dụng" cho trợ lý AI.
 *
 * Nguồn: các file Markdown trong resources/help (mỗi file = một module).
 * Đầu file có khối front matter:
 *
 *   ---
 *   title: Đơn hàng
 *   url: /orders
 *   routes: orders.*, order-returns.*
 *   keywords: đơn hàng, duyệt đơn, xuất kho
 *   ---
 *
 * Nội dung được tách theo tiêu đề "## ". Khi người dùng hỏi, chọn các mục liên quan nhất
 * theo từ khoá câu hỏi + trang người dùng đang mở, rồi đưa cho AI làm nguồn duy nhất để trả lời.
 */
final class HelpKnowledgeBase
{
    /** Giới hạn ký tự tài liệu gửi kèm mỗi câu hỏi (kiểm soát chi phí token). */
    private const CONTEXT_CHAR_BUDGET = 9000;

    private const MAX_SECTIONS = 6;

    /** Từ phổ biến không mang nghĩa phân loại, bỏ qua khi chấm điểm. */
    private const STOP_WORDS = [
        'toi', 'minh', 'ban', 'la', 'va', 'cua', 'cho', 'thi', 'the', 'nao', 'lam', 'sao', 'o', 'dau', 'co', 'khong',
        'duoc', 'can', 'muon', 'nhu', 'nay', 'do', 'cac', 'nhung', 'mot', 'voi', 'trong', 'tren', 'de', 'khi', 'se',
        'da', 'dang', 'bi', 'gi', 'ai', 'ra', 'vao', 'len', 'xuong', 'hay', 'nhe', 'a', 'oi', 'giup', 'huong', 'dan',
        'cach', 'su', 'dung', 'chuc', 'nang', 'trang', 'he', 'thong', 'crm',
    ];

    public function __construct(private readonly ?string $directory = null) {}

    /**
     * Chọn các mục tài liệu liên quan.
     *
     * @return array<int, array{doc:string, title:string, heading:string, url:?string, content:string}>
     */
    public function search(string $question, ?string $routeName = null): array
    {
        $queryTokens = $this->tokens($question);
        $sections = $this->sections();
        if ($sections === []) {
            return [];
        }

        $scored = [];
        foreach ($sections as $index => $section) {
            $score = 0.0;

            foreach ($queryTokens as $token) {
                if (isset($section['heading_tokens'][$token])) {
                    $score += 3.0;
                }
                if (isset($section['keyword_tokens'][$token])) {
                    $score += 2.5;
                }
                if (isset($section['body_tokens'][$token])) {
                    $score += 1.0;
                }
            }

            // Cụm 2 từ liên tiếp (vd "phieu doi", "cong no") khớp → tăng độ chính xác.
            foreach ($this->bigrams($queryTokens) as $bigram) {
                if (str_contains($section['search_text'], $bigram)) {
                    $score += 2.0;
                }
            }

            if ($routeName && $this->routeMatches($section['routes'], $routeName)) {
                $score += $section['is_overview'] ? 6.0 : 3.0;
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'index' => $index];
            }
        }

        usort($scored, static fn (array $a, array $b): int => [$b['score'], $a['index']] <=> [$a['score'], $b['index']]);

        $picked = [];
        $budget = self::CONTEXT_CHAR_BUDGET;
        foreach ($scored as $row) {
            $section = $sections[$row['index']];
            $length = mb_strlen($section['content']);
            if ($length > $budget) {
                continue;
            }
            $budget -= $length;
            $picked[] = [
                'doc' => $section['doc'],
                'title' => $section['title'],
                'heading' => $section['heading'],
                'url' => $section['url'],
                'content' => $section['content'],
            ];
            if (count($picked) >= self::MAX_SECTIONS) {
                break;
            }
        }

        return $picked;
    }

    /**
     * Danh sách module có tài liệu (để AI gợi ý khi không tìm thấy nội dung phù hợp).
     *
     * @return array<int, array{title:string, url:?string}>
     */
    public function catalog(): array
    {
        return collect($this->sections())
            ->unique('doc')
            ->map(fn (array $section): array => ['title' => $section['title'], 'url' => $section['url']])
            ->values()
            ->all();
    }

    /**
     * Toàn bộ mục tài liệu đã tách, cache theo thời điểm sửa file.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.md') ?: [];
        sort($files);
        if ($files === []) {
            return [];
        }

        $signature = sha1(implode('|', array_map(static fn (string $f): string => basename($f).':'.filemtime($f), $files)));

        return Cache::remember('ego-help-kb:'.$signature, now()->addDay(), function () use ($files): array {
            $sections = [];
            foreach ($files as $file) {
                array_push($sections, ...$this->parseFile($file));
            }

            return $sections;
        });
    }

    private function directory(): string
    {
        return $this->directory ?? resource_path('help');
    }

    /** @return array<int, array<string, mixed>> */
    private function parseFile(string $file): array
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        $meta = [];

        if (preg_match('/^---\n(.*?)\n---\n/s', $raw, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = array_map('trim', explode(':', $line, 2));
                    $meta[strtolower($key)] = $value;
                }
            }
            $raw = substr($raw, strlen($m[0]));
        }

        $doc = pathinfo($file, PATHINFO_FILENAME);
        $title = $meta['title'] ?? Str::headline($doc);
        $url = isset($meta['url']) && str_starts_with($meta['url'], '/') ? $meta['url'] : null;
        $routes = array_values(array_filter(array_map('trim', explode(',', $meta['routes'] ?? ''))));
        $keywordTokens = array_fill_keys($this->tokens($meta['keywords'] ?? ''), true);

        // Tách theo "## "; phần trước tiêu đề đầu tiên là phần tổng quan của module.
        $parts = preg_split('/^(?=## )/m', trim($raw)) ?: [];
        $sections = [];
        foreach ($parts as $i => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $heading = preg_match('/^## (.+)$/m', $part, $h) ? trim($h[1]) : 'Tổng quan';
            $content = "# {$title} — {$heading}\n".($url ? "Trang: {$url}\n" : '')."\n".$part;
            $searchText = ' '.implode(' ', $this->tokens($title.' '.$heading.' '.$part)).' ';

            $sections[] = [
                'doc' => $doc,
                'title' => $title,
                'heading' => $heading,
                'url' => $url,
                'routes' => $routes,
                'is_overview' => $i === 0 && ! str_starts_with($part, '## '),
                'content' => $content,
                'search_text' => $searchText,
                'heading_tokens' => array_fill_keys($this->tokens($title.' '.$heading), true),
                'keyword_tokens' => $keywordTokens,
                'body_tokens' => array_fill_keys($this->tokens($part), true),
            ];
        }

        return $sections;
    }

    /** @param array<int, string> $patterns */
    private function routeMatches(array $patterns, string $routeName): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Chuẩn hoá tiếng Việt về không dấu, chữ thường, tách từ; bỏ từ dừng và từ 1 ký tự.
     *
     * @return array<int, string>
     */
    public function tokens(string $text): array
    {
        $ascii = Str::ascii(mb_strtolower($text, 'UTF-8'));
        $words = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            static fn (string $w): bool => strlen($w) > 1 && ! in_array($w, self::STOP_WORDS, true)
        ));
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function bigrams(array $tokens): array
    {
        $out = [];
        for ($i = 0, $n = count($tokens) - 1; $i < $n; $i++) {
            $out[] = ' '.$tokens[$i].' '.$tokens[$i + 1].' ';
        }

        return array_values(array_unique($out));
    }
}
