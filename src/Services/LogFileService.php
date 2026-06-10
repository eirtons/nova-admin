<?php

namespace Nbutl\NovaSiteCore\Services;

use Illuminate\Support\Collection;

/**
 * 后台日志文件服务：扫描日志目录列文件、读尾部、删除。
 * 兼容单文件（laravel.log）与按天分割（laravel-YYYY-MM-DD.log）——都按目录扫描。
 */
class LogFileService
{
    /**
     * @return Collection<int, array{name: string, path: string, size: int, mtime: int}>
     */
    public function files(): Collection
    {
        $dirs = (array) config('nova-site-core.logs.paths', []);
        if ($dirs === []) {
            $dirs = [storage_path('logs')];
        }

        $pattern = (string) config('nova-site-core.logs.pattern', '*.log');

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
     * 读取文件尾部（大文件只读最后 view_tail_kb KB，起点对齐到行首）。
     *
     * @return array{content: string, truncated: bool, tail_kb: int}
     */
    public function tail(string $path): array
    {
        $this->assertManaged($path);

        $kb = max(1, (int) config('nova-site-core.logs.view_tail_kb', 256));
        $bytes = $kb * 1024;
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

        return ['content' => $content, 'truncated' => $truncated, 'tail_kb' => $kb];
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
