<?php

namespace Inova\NovaAdmin\SitePresets;

use InvalidArgumentException;

/**
 * 建站预设：部署系统（webdeploy）发起建站时可预选的领域 / 主题等。
 *
 * 宿主在 nova-admin.site_presets 登记 Provider，本服务据此：
 * - 生成仓库根目录的 site-presets.json，webdeploy 定时拉取后渲染成下拉；
 * - 读取部署时写入 .env 的初始值（initial），首次 seed 时据此代替随机。
 *
 * 清单以项目代码为唯一来源，json 只是导出物，doctor 校验两者一致。
 */
class SitePresetService
{
    public const FILE = 'site-presets.json';

    /** @return array<string, array{label: string, provider: class-string<SitePresetProvider>, initial?: ?string}> */
    public function definitions(): array
    {
        $definitions = array_filter((array) config('nova-admin.site_presets', []));

        foreach ($definitions as $key => $definition) {
            // 键会变成部署系统的环境变量名 WEBDEPLOY_PRESET_<KEY>，只允许小写字母与下划线
            if (! preg_match('/^[a-z_]+$/', (string) $key)) {
                throw new InvalidArgumentException("建站预设键 [{$key}] 只能包含小写字母与下划线");
            }

            if (! is_array($definition) || empty($definition['label']) || empty($definition['provider'])) {
                throw new InvalidArgumentException("建站预设 [{$key}] 必须配置 label 与 provider");
            }
        }

        return $definitions;
    }

    /**
     * site-presets.json 的内容：每个键给出显示名、全部选项与 NEW 选项。
     *
     * @return array<string, array{label: string, options: array<string, string>, new: list<string>}>
     */
    public function manifest(): array
    {
        $manifest = [];

        foreach ($this->definitions() as $key => $definition) {
            $provider = $this->provider($key, $definition);
            $options = array_map('strval', $provider->options());

            $manifest[$key] = [
                'label' => (string) $definition['label'],
                'options' => $options,
                // NEW 只认清单内的项，避免批次常量里残留已下线的 slug
                'new' => array_values(array_intersect(array_map('strval', $provider->newOptions()), array_keys($options))),
            ];
        }

        return $manifest;
    }

    public function encode(): string
    {
        return json_encode($this->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }

    public function path(): string
    {
        return base_path(self::FILE);
    }

    /**
     * 部署时选定的值；没选（随机）返回 null。
     * 选了却不在清单里直接抛异常让部署失败：悄悄随机会得到一个与域名不符、且很难察觉的站。
     */
    public function initial(string $key): ?string
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException("建站预设 [{$key}] 未在 nova-admin.site_presets 中登记");
        }

        $value = trim((string) ($definitions[$key]['initial'] ?? ''));

        if ($value === '') {
            return null;
        }

        $options = $this->provider($key, $definitions[$key])->options();

        if (! array_key_exists($value, $options)) {
            throw new InvalidArgumentException(
                "部署指定的{$definitions[$key]['label']} [{$value}] 不在可选清单中，可选值：".implode(', ', array_keys($options))
            );
        }

        return $value;
    }

    /** 已登记预设的 Provider 实例 */
    public function providerFor(string $key): SitePresetProvider
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException("建站预设 [{$key}] 未在 nova-admin.site_presets 中登记");
        }

        return $this->provider($key, $definitions[$key]);
    }

    private function provider(string $key, array $definition): SitePresetProvider
    {
        $provider = app($definition['provider']);

        if (! $provider instanceof SitePresetProvider) {
            throw new InvalidArgumentException("建站预设 [{$key}] 的 provider 必须实现 ".SitePresetProvider::class);
        }

        return $provider;
    }
}
