<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'nova-admin:doctor';

    protected $description = '自检 nova-admin 配置一致性';

    public function handle(): int
    {
        $positions = (array) config('nova-admin.ad_positions', []);
        $map = (array) config('nova-admin.ads_protocol.position_map', []);

        // 映射目标必须都是已启用的广告位，否则平台一勾到就整体导入失败，
        // 而站点若整块删了 ads_protocol，浅合并会让它悄悄回落到包默认值。
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

            $this->line('修法：把缺失的位补回 config/nova-admin.php 的 ad_positions（只追加，别删行）。');

            return self::FAILURE;
        }

        $this->info('广告位配置一致（'.count($map).' 个协议键全部命中）。');

        return self::SUCCESS;
    }
}
