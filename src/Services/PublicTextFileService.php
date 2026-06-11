<?php

namespace Nbutl\NovaAdmin\Services;

use Illuminate\Support\Facades\File;

/**
 * ads.txt / robots.txt 共用的存储服务：支持 file | database | both，
 * 写文件失败不中断（降级到路由兜底），数据库与文件保持同步。
 */
class PublicTextFileService
{
    public function __construct(
        protected SiteConfigService $config,
    ) {}

    /**
     * 读取内容：优先文件，其次数据库，最后默认模板。
     */
    public function read(string $type): string
    {
        $conf = $this->conf($type);

        $path = $conf['path'];
        if (in_array($conf['storage'], ['file', 'both'], true) && File::exists($path)) {
            $content = File::get($path);
            if (trim($content) !== '') {
                return $content;
            }
        }

        $fromDb = $this->config->get($conf['config_key']);
        if (is_string($fromDb) && trim($fromDb) !== '') {
            return $this->resolvePlaceholders($fromDb);
        }

        return $this->resolvePlaceholders($this->defaultTemplate($type));
    }

    /**
     * 读取“后台编辑框初始值”：只取已保存内容（不含默认模板），便于区分空与默认。
     */
    public function readRaw(string $type): string
    {
        $conf = $this->conf($type);

        $fromDb = $this->config->get($conf['config_key']);
        if (is_string($fromDb)) {
            return $fromDb;
        }

        $path = $conf['path'];
        if (File::exists($path)) {
            return File::get($path);
        }

        return '';
    }

    /**
     * 保存：DB 为先（database/both），再尝试写文件（file/both）。
     *
     * @return array{file_written: bool, message: ?string}
     */
    public function save(string $type, ?string $content): array
    {
        $conf = $this->conf($type);
        $content = trim((string) $content);

        if (in_array($conf['storage'], ['database', 'both'], true)) {
            $this->config->set($conf['config_key'], $content, 'text', $type);
        }

        $fileWritten = true;
        $message = null;

        if (in_array($conf['storage'], ['file', 'both'], true)) {
            try {
                $this->writeFile($conf, $content);
            } catch (\Throwable $e) {
                $fileWritten = false;
                $message = '文件写入失败，请检查 public 目录写权限'
                    .($conf['route_fallback'] ? '（已启用路由动态输出兜底）' : '');
            }
        }

        return ['file_written' => $fileWritten, 'message' => $message];
    }

    protected function writeFile(array $conf, string $content): void
    {
        $path = $conf['path'];

        if ($content === '') {
            if ($conf['empty_behavior'] === 'delete') {
                File::delete($path);
            } else {
                File::put($path, '');
            }

            return;
        }

        File::put($path, $this->resolvePlaceholders($content).PHP_EOL);
    }

    /**
     * 解析内容占位符：{url} = 当前请求域名（CLI 下为 APP_URL）。
     * 数据库存占位符、文件与输出存解析结果，域名变化无需改内容。
     */
    public function resolvePlaceholders(string $content): string
    {
        return str_replace('{url}', rtrim(url('/'), '/'), $content);
    }

    public function defaultTemplate(string $type): string
    {
        $conf = $this->conf($type);

        if ($type === 'robots_txt') {
            if (! empty($conf['default_template'])) {
                return $conf['default_template'];
            }

            $sitemap = $conf['sitemap_url'] ?: '{url}/sitemap.xml';

            return "User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: ".$sitemap."\n";
        }

        return ''; // ads.txt 无默认模板
    }

    protected function conf(string $type): array
    {
        return config("nova-admin.$type");
    }
}
