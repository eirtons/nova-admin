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
     * 内容位由各页面模板放置，包无法代劳：扫描模板里的 <x-ad-head> / <x-ad-body>，
     * 只有一半的位广告永远不展示，直接判失败；完全没放的位可能是预留位，默认只警告。
     */
    protected function checkTemplates(): bool
    {
        $found = ['head' => [], 'body' => []];

        foreach ($this->bladeFiles() as $file) {
            preg_match_all('/<x-ad-(head|body)\b[^>]*?\sposition\s*=\s*["\']([^"\']+)["\']/', File::get($file), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $side, $position]) {
                $found[$side][$position] = true;
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
            $this->error("内容位 {$line}：head 与 body 必须成对，否则广告永远不展示");
        }

        if ($missing !== []) {
            $message = '以下内容位在模板中没有渲染点，后台填了代码也不会展示：'.implode(', ', $missing);
            $this->option('strict') ? $this->error($message) : $this->warn($message);
        }

        if ($halfPaired === [] && $missing === []) {
            $this->info('模板广告渲染点完整。');
        }

        return $halfPaired === [] && ($missing === [] || ! $this->option('strict'));
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
