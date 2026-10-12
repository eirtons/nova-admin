<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\SitePresets\SitePresetService;

class ExportSitePresetsCommand extends Command
{
    protected $signature = 'nova-admin:export-site-presets';

    protected $description = '按 nova-admin.site_presets 生成仓库根目录的 site-presets.json（供 webdeploy 拉取建站下拉清单）';

    public function handle(SitePresetService $presets): int
    {
        if ($presets->definitions() === []) {
            // 没登记却留着旧文件，webdeploy 会继续给出下拉，部署时传入的值项目又不认
            if (File::exists($presets->path())) {
                File::delete($presets->path());
                $this->warn('未登记建站预设，已删除 '.SitePresetService::FILE);
            } else {
                $this->info('未登记建站预设，无需生成。');
            }

            return self::SUCCESS;
        }

        File::put($presets->path(), $presets->encode());

        $this->info('已生成 '.SitePresetService::FILE.'：'.implode(', ', array_keys($presets->manifest())));

        return self::SUCCESS;
    }
}
