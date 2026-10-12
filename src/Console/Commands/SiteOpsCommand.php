<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Inova\NovaAdmin\SitePresets\SitePresetService;
use Inova\NovaAdmin\SitePresets\SwitchableSitePreset;
use RuntimeException;
use Throwable;

/**
 * webdeploy「站点配置」下发协议：单站修改 robots.txt / ads.txt、切换建站预设（领域 / 主题等）。
 *
 * 负载 {"meta":{"protocol":1},"robots_txt":?string,"ads_txt":?string,"presets":?{key:slug}}，
 * 各部件独立成败，未下发的部件不出现在回包里；只发 meta 即只读。
 * 回包总带 presets_current（各预设当前值），webdeploy 据此校正记录：
 *   __SITE_OPS_RESULT_BEGIN__{...}__SITE_OPS_RESULT_END__
 */
class SiteOpsCommand extends Command
{
    public const PROTOCOL = 1;

    protected $signature = 'nova-admin:site-ops {file : webdeploy 下发的 JSON 文件路径}';

    protected $description = '执行 webdeploy 下发的站点配置（robots.txt / ads.txt / 建站预设切换）';

    public function handle(PublicTextFileService $files, SitePresetService $presets): int
    {
        try {
            $payload = $this->readPayload((string) $this->argument('file'));
        } catch (Throwable $e) {
            return $this->emit(['error' => $e->getMessage()]);
        }

        $result = [];

        foreach (['robots_txt', 'ads_txt'] as $type) {
            if (is_string($payload[$type] ?? null)) {
                $result[$type] = $this->saveText($files, $type, $payload[$type]);
            }
        }

        if (is_array($payload['presets'] ?? null)) {
            $result['presets'] = [];
            foreach ($payload['presets'] as $key => $slug) {
                $result['presets'][$key] = $this->applyPreset($presets, (string) $key, (string) $slug);
            }
        }

        try {
            $result['presets_current'] = $this->currentPresets($presets);
        } catch (Throwable) {
            // 读当前值出错不影响已执行部件的结果；webdeploy 缺这项时不校正记录
        }

        return $this->emit($result);
    }

    private function readPayload(string $path): array
    {
        $payload = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($payload)) {
            throw new RuntimeException('下发文件不存在或不是合法 JSON');
        }

        if ((int) ($payload['meta']['protocol'] ?? 0) !== self::PROTOCOL) {
            throw new RuntimeException('不支持的协议版本：'.($payload['meta']['protocol'] ?? 'null'));
        }

        return $payload;
    }

    /** @return array{status:string,reason?:string} */
    private function saveText(PublicTextFileService $files, string $type, string $content): array
    {
        try {
            // save() 写文件失败只降级不抛异常；协议要求写不进去如实回 failed
            $saved = $files->save($type, $content);

            return $saved['file_written']
                ? ['status' => 'success']
                : ['status' => 'failed', 'reason' => $saved['message'] ?? '写入失败'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** @return array{status:string,reason?:string} */
    private function applyPreset(SitePresetService $presets, string $key, string $slug): array
    {
        try {
            $provider = $presets->providerFor($key);

            if (! $provider instanceof SwitchableSitePreset) {
                return ['status' => 'not_applicable', 'reason' => "本站不支持远程切换「{$key}」"];
            }

            if (! array_key_exists($slug, $provider->options())) {
                return ['status' => 'failed', 'reason' => "[{$slug}] 不在可选清单中"];
            }

            $provider->apply($slug);

            return ['status' => 'success'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** @return array<string, ?string> 不支持读取的预设不出现 */
    private function currentPresets(SitePresetService $presets): array
    {
        $current = [];

        foreach (array_keys($presets->definitions()) as $key) {
            $provider = $presets->providerFor($key);
            if ($provider instanceof SwitchableSitePreset) {
                $current[$key] = $provider->current();
            }
        }

        return $current;
    }

    /** 结果 marker 是 webdeploy 唯一认可的边界，任何路径都必须输出且只输出一次 */
    private function emit(array $result): int
    {
        $this->output->write('__SITE_OPS_RESULT_BEGIN__'.json_encode($result, JSON_UNESCAPED_UNICODE).'__SITE_OPS_RESULT_END__', false);

        $parts = array_merge(
            array_intersect_key($result, ['robots_txt' => 1, 'ads_txt' => 1]),
            $result['presets'] ?? [],
        );
        $failed = isset($result['error']) || collect($parts)->contains(fn (array $p) => $p['status'] === 'failed');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
