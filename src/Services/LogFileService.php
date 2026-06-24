<?php

namespace Inova\NovaAdmin\Services;

use Illuminate\Support\Collection;

/**
 * 后台日志文件服务：扫描日志目录列文件、按条目检索、删除。
 * 兼容单文件（laravel.log）与按天分割（laravel-YYYY-MM-DD.log）——都按目录扫描。
 */
class LogFileService
{
    /** 单条目 detail（含堆栈）保留的最大字节数 */
    protected const DETAIL_MAX_BYTES = 8192;

    /**
     * @return Collection<int, array{name: string, path: string, size: int, mtime: int}>
     */
    public function files(): Collection
    {
        $dirs = (array) config('nova-admin.logs.paths', []);
        if ($dirs === []) {
            $dirs = [storage_path('logs')];
        }

        $pattern = (string) config('nova-admin.logs.pattern', '*.log');

        return collect($dirs)
            ->flatMap(fn (string $dir): array => glob(rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$pattern) ?: [])
            ->filter(fn (string $path): bool => is_file($path))
            ->map(fn (string $path): array => [
                'name'  => basename($path),
                'path'  => $path,
                'size'  => (int) filesize($path),
                'mtime' => (int) filemtime($path),
            ])
            ->sortByDesc('mtime')
            ->values();
    }

    /**
     * 统一入口：按文件 / 关键字 / 级别检索结构化条目，最新在前。
     * 无筛选条件时走快路径（只读各文件尾部）；有关键字或级别时全文流式扫描。
     *
     * @return array<int, array{file: string, time: string, level: string, message: string, detail: string}>
     */
    public function entries(?string $path = null, string $keyword = '', ?string $level = null, ?int $limit = null): array
    {
        $limit ??= max(1, (int) config('nova-admin.logs.search_limit', 100));
        $level = blank($level) ? null : strtoupper($level);

        $files = $this->files();
        if ($path !== null) {
            $files = $files->where('path', $path)->values();
            abort_if($files->isEmpty(), 404);
        }

        $results = [];

        foreach ($files as $file) {
            $entries = ($keyword === '' && $level === null)
                ? array_reverse(array_slice($this->parseEntries($this->readTail($file['path'])), -$limit))
                : array_reverse($this->scanFile($file['path'], $keyword, $level, $limit));

            foreach ($entries as $entry) {
                $entry['file'] = $file['name'];
                $results[] = $entry;

                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * 读取文件尾部（大文件只读最后 view_tail_kb KB，起点对齐到行首）。
     */
    protected function readTail(string $path): string
    {
        $bytes = max(1, (int) config('nova-admin.logs.view_tail_kb', 256)) * 1024;
        $size = (int) filesize($path);
        $truncated = $size > $bytes;

        $fp = fopen($path, 'rb');
        if ($truncated) {
            fseek($fp, -$bytes, SEEK_END);
        }
        $content = (string) stream_get_contents($fp);
        fclose($fp);

        if ($truncated && ($pos = strpos($content, "\n")) !== false) {
            $content = substr($content, $pos + 1);
        }

        return $content;
    }

    /**
     * 按 Laravel 日志格式（[datetime] env.LEVEL: message + 堆栈）切分条目。
     * 无法识别的内容（如 supervisor 日志）按整行降级为 DEBUG 级条目。
     *
     * @return array<int, array{time: string, level: string, message: string, detail: string}>
     */
    public function parseEntries(string $content): array
    {
        $chunks = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[T ][^\]]*\])/m', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $entries = [];

        foreach ($chunks as $chunk) {
            $eol = strpos($chunk, "\n");
            $firstLine = $eol === false ? $chunk : substr($chunk, 0, $eol);

            if (! preg_match('/^\[([^\]]+)\]\s+\w+\.(\w+):\s?(.*)$/', $firstLine, $m)) {
                foreach (preg_split('/\r\n|\r|\n/', trim($chunk)) ?: [] as $line) {
                    if (trim($line) !== '') {
                        $entries[] = $this->fallbackEntry($line);
                    }
                }

                continue;
            }

            $entries[] = [
                'time'    => $m[1],
                'level'   => strtoupper($m[2]),
                'message' => mb_strimwidth(trim($m[3]), 0, 300, '…'),
                'detail'  => $this->capDetail(trim($chunk)),
            ];
        }

        return $entries;
    }

    /**
     * 全文流式扫描单个文件：逐行组装条目，按关键字（匹配含堆栈的全文）与级别过滤，
     * 仅保留最后 $keep 条匹配（时间正序返回）。
     *
     * @return array<int, array{time: string, level: string, message: string, detail: string}>
     */
    protected function scanFile(string $path, string $keyword, ?string $level, int $keep): array
    {
        $fp = fopen($path, 'rb');
        $matches = [];
        $current = null;

        $finish = function () use (&$current, &$matches, $keyword, $level, $keep): void {
            if ($current === null) {
                return;
            }
            if (($level === null || $current['level'] === $level)
                && ($keyword === '' || stripos($current['detail'], $keyword) !== false)) {
                $current['detail'] = $this->capDetail($current['detail']);
                $matches[] = $current;
                if (count($matches) > $keep) {
                    array_shift($matches);
                }
            }
            $current = null;
        };

        while (($line = fgets($fp)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ][^\]]*)\]\s+\w+\.(\w+):\s?(.*)$/', $line, $m)) {
                $finish();
                $current = [
                    'time'    => $m[1],
                    'level'   => strtoupper($m[2]),
                    'message' => mb_strimwidth(trim($m[3]), 0, 300, '…'),
                    'detail'  => rtrim($line),
                ];
            } elseif ($current !== null) {
                if (strlen($current['detail']) < self::DETAIL_MAX_BYTES * 2) {
                    $current['detail'] .= "\n".rtrim($line);
                }
            } elseif (trim($line) !== '') {
                $current = $this->fallbackEntry($line);
                $finish();
            }
        }

        $finish();
        fclose($fp);

        return $matches;
    }

    protected function fallbackEntry(string $line): array
    {
        $line = trim($line);

        return [
            'time'    => '',
            'level'   => 'DEBUG',
            'message' => mb_strimwidth($line, 0, 300, '…'),
            'detail'  => $this->capDetail($line),
        ];
    }

    protected function capDetail(string $detail): string
    {
        if (strlen($detail) <= self::DETAIL_MAX_BYTES) {
            return $detail;
        }

        return substr($detail, 0, self::DETAIL_MAX_BYTES)."\n…（已截断，完整内容请下载日志文件）";
    }

    public function delete(string $path): void
    {
        $this->assertManaged($path);

        @unlink($path);
    }

    /**
     * 防御路径伪造：仅允许操作扫描列表内的文件。
     */
    protected function assertManaged(string $path): void
    {
        abort_unless($this->files()->contains('path', $path), 404);
    }
}
