<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Inova\NovaAdmin\Exceptions\AdsProtocolException;
use Inova\NovaAdmin\Models\AdSpot;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Throwable;

/**
 * webdeploy 的站点广告配置下发协议实现（协议见 webdeploy: docs/GAM-AD-UNIT-CSV-WORKFLOW.md 5.1）。
 *
 * 一次下发两样东西，各自独立成败：
 *   - slots   → GAM 广告位代码，按协议键映射到 ad_spots.position 覆盖式写入
 *   - ads_txt → ads.txt 内容，走 PublicTextFileService（与后台「Ads.txt」页同一条路径）
 *
 * webdeploy 不感知本包的落点，只认结果 marker：
 *   __SITE_AD_CONFIG_RESULT_BEGIN__{"slots":{...},"ads_txt":{...}}__SITE_AD_CONFIG_RESULT_END__
 * 未下发的部件不出现在回包里；请求了却写不进去的部件必须返回 failed，不得静默略过。
 */
class ImportSiteAdConfigCommand extends Command
{
    protected $signature = 'ads:import-site-ad-config {file : 标准站点广告配置 JSON 文件路径}';

    protected $description = '导入 webdeploy 下发的站点广告配置（GAM 广告位代码 + ads.txt）';

    public function handle(PublicTextFileService $files): int
    {
        try {
            $payload = $this->readPayload((string) $this->argument('file'));
        } catch (Throwable $e) {
            // 负载本身就读不了/协议不对：两部件都无从下手，各回一条失败
            return $this->emit([
                'slots' => ['status' => 'failed', 'reason' => $e->getMessage()],
                'ads_txt' => ['status' => 'failed', 'reason' => $e->getMessage()],
            ]);
        }

        $result = [];

        if (is_array($payload['slots'] ?? null)) {
            $result['slots'] = $this->importSlots($payload['slots']);
        }

        if (is_string($payload['ads_txt'] ?? null)) {
            $result['ads_txt'] = $this->importAdsTxt($files, $payload['ads_txt']);
        }

        return $this->emit($result);
    }

    private function readPayload(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new AdsProtocolException('下发文件不存在或不可读');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            throw new AdsProtocolException('下发文件不是合法 JSON');
        }

        $protocol = $payload['meta']['protocol'] ?? null;
        if ((int) $protocol !== (int) config('nova-admin.ads_protocol.version')) {
            throw new AdsProtocolException("不支持的协议版本：{$protocol}");
        }

        return $payload;
    }

    /** @return array{status:string,written_positions?:array<int,string>,reason?:string} */
    private function importSlots(array $slots): array
    {
        try {
            $map = (array) config('nova-admin.ads_protocol.position_map', []);
            $globalHeadKey = (string) config('nova-admin.ads_protocol.global_head_key');

            if ($slots === []) {
                throw new AdsProtocolException('slots 为空');
            }

            if (! array_key_exists($globalHeadKey, $slots)) {
                throw new AdsProtocolException("slots 缺少 {$globalHeadKey}");
            }

            $unknown = array_diff(array_keys($slots), array_keys($map));
            if ($unknown !== []) {
                throw new AdsProtocolException('存在未知协议键：'.implode(', ', $unknown));
            }

            // 映射目标必须是本站启用的广告位，否则写进去也永远输出不了（AdService 按 ad_positions 过滤）
            $positions = (array) config('nova-admin.ad_positions', []);
            foreach (array_keys($slots) as $key) {
                if (! array_key_exists($map[$key], $positions)) {
                    throw new AdsProtocolException("协议键 {$key} 映射的广告位 {$map[$key]} 未在 ad_positions 中启用");
                }
            }

            // 任一广告位写不进去即整体失败，绝不静默忽略某个广告位
            $written = DB::transaction(function () use ($slots, $map) {
                $written = [];

                foreach ($slots as $key => $slot) {
                    if (! is_array($slot) || ! isset($slot['name']) || ! array_key_exists('head_code', $slot)) {
                        throw new AdsProtocolException("广告位 {$key} 结构非法");
                    }

                    // 协议里的 name 仅作人类可读标识，本包广告位名称取自 config('nova-admin.ad_positions')
                    AdSpot::updateOrCreate(
                        ['position' => $map[$key]],
                        [
                            'head_code' => $slot['head_code'],
                            'body_code' => $slot['body_code'] ?? null,
                            'is_active' => true,
                        ],
                    );

                    $written[] = $key;
                }

                return $written;
            });

            return ['status' => 'success', 'written_positions' => $written];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** @return array{status:string,reason?:string} */
    private function importAdsTxt(PublicTextFileService $files, string $content): array
    {
        try {
            // save() 写文件失败只做降级不抛异常；下发协议要求写不进去必须如实回 failed
            $saved = $files->save('ads_txt', $content);

            if (! $saved['file_written']) {
                throw new AdsProtocolException($saved['message'] ?? '写入 ads.txt 失败');
            }

            return ['status' => 'success'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** 结果 marker 是 webdeploy 唯一认可的边界，任何路径都必须输出且只输出一次。 */
    private function emit(array $result): int
    {
        $this->output->write(
            '__SITE_AD_CONFIG_RESULT_BEGIN__'.json_encode($result, JSON_UNESCAPED_UNICODE).'__SITE_AD_CONFIG_RESULT_END__',
            false,
        );

        foreach ($result as $part) {
            if (($part['status'] ?? '') !== 'success') {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
