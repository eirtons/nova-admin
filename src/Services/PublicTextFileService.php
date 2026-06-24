<?php

namespace Inova\NovaAdmin\Services;

use Illuminate\Support\Facades\File;

/**
 * ads.txt / robots.txt 共用的存储服务：同时写入文件与数据库，
 * 写文件失败不中断（降级到路由兜底），数据库与文件保持同步。
 */
class PublicTextFileService
{
    public function __construct(
        protected SiteConfigService $config,
    ) {}

    public function read(string $type): string
    {
        $conf = $this->conf($type);

        $path = $conf['path'];
        if (File::exists($path)) {
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
     * @return array{file_written: bool, message: ?string}
     */
    public function save(string $type, ?string $content): array
    {
        $conf = $this->conf($type);
        $content = trim((string) $content);

        $this->config->set($conf['config_key'], $content, 'text', $type);

        $fileWritten = true;
        $message = null;

        try {
            $this->writeFile($conf, $content);
        } catch (\Throwable) {
            $fileWritten = false;
            $message = '文件写入失败，请检查 public 目录写权限（已启用路由动态输出兜底）';
        }

        return ['file_written' => $fileWritten, 'message' => $message];
    }

    protected function writeFile(array $conf, string $content): void
    {
        $path = $conf['path'];

        // route_only：内容依赖运行时 {url}，不落静态文件（防 Nginx 绕过 PHP 返回冻结域名）。
        // 顺手清掉历史遗留的静态文件，确保请求始终落到动态路由。
        if (! empty($conf['route_only'])) {
            File::delete($path);

            return;
        }

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

        return '';
    }

    protected function conf(string $type): array
    {
        return config("nova-admin.$type");
    }
}
