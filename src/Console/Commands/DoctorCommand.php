<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\Services\AdService;

class DoctorCommand extends Command
{
    protected $signature = 'nova-admin:doctor {--strict : 内容位在模板里完全没有渲染点也判为失败（CI 用）}';

    protected $description = '自检 nova-admin 配置一致性与模板广告渲染点';

    public function handle(): int
    {
        $failed = ! $this->checkProtocolMap();
        $failed = ! $this->checkTemplates() || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function checkProtocolMap(): bool
    {
        $positions = (array) config('nova-admin.ad_positions', []);
        $map = (array) config('nova-admin.ads_protocol.position_map', []);

        // 映射目标必须都是已启用的广告位，否则平台一勾到就整体导入失败
        $broken = [];
        foreach ($map as $key => $target) {
            if (! array_key_exists($target, $positions)) {
                $broken[] = "协议键 {$key} 映射的广告位 {$target} 未在 ad_positions 中启用";
            }
        }

        if ($broken !== []) {
            foreach ($broken as $line) {
                $this->error($line);
            }

            $this->line("修法：把缺失的位补回 ad_positions；确实不用的位在 position_map 里也写成 false。");

            return false;
        }

        $this->info('广告位配置一致（'.count($map).' 个协议键全部命中）。');

        return true;
    }

    /**
     * 内容位由各页面模板放置，包无法代劳：扫描模板里的 <x-ad-head> / <x-ad-body>。
     * 以下直接判失败：只放了一半的位（广告永远不展示）、动态 :position（静态检查失效）、
     * 引用了未启用的位（AdService 不会输出）。完全没放的位可能是预留位，默认只警告。
     */
    protected function checkTemplates(): bool
    {
        $found = ['head' => [], 'body' => []];
        $errors = [];
        $enabled = (array) config('nova-admin.ad_positions', []);

        foreach ($this->bladeFiles() as $file) {
            $source = File::get($file);
            $name = ltrim(str_replace(base_path(), '', $file), DIRECTORY_SEPARATOR);

            if (preg_match('/<x-ad-(?:head|body)\b[^>]*\s:position\s*=/', $source)) {
                $errors[] = "{$name} 用了动态 :position，广告位必须写成字面量 position=\"...\"";
            }

            preg_match_all('/<x-ad-(head|body)\b[^>]*?\sposition\s*=\s*["\']([^"\']+)["\']/', $source, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $side, $position]) {
                $found[$side][$position] = true;

                if (! array_key_exists($position, $enabled)) {
                    $errors[] = "{$name} 引用的广告位 {$position} 未在 ad_positions 中启用";
                }
            }
        }

        $halfPaired = [];
        $missing = [];
        foreach (AdService::contentPositions() as $position) {
            $head = isset($found['head'][$position]);
            $body = isset($found['body'][$position]);

            if ($head xor $body) {
                $halfPaired[] = $position.'（缺 '.($head ? '<x-ad-body>' : '<x-ad-head>').'）';
            } elseif (! $head && ! $body) {
                $missing[] = $position;
            }
        }

        foreach ($halfPaired as $line) {
            $errors[] = "内容位 {$line}：head 与 body 必须成对，否则广告永远不展示";
        }

        foreach (array_unique($errors) as $line) {
            $this->error($line);
        }

        if ($missing !== []) {
            $message = '以下内容位在模板中没有渲染点，后台填了代码也不会展示：'.implode(', ', $missing);
            $this->option('strict') ? $this->error($message) : $this->warn($message);
        }

        if ($errors === [] && $missing === []) {
            $this->info('模板广告渲染点完整。');
        }

        return $errors === [] && ($missing === [] || ! $this->option('strict'));
    }

    /** @return list<string> */
    protected function bladeFiles(): array
    {
        $files = [];

        foreach ((array) config('view.paths', [resource_path('views')]) as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
