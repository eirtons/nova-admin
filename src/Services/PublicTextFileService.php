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
        // 后台表单里 {url} 已被解析成站点 URL 展示；存库前收回成 {url}，避免域名焊死。
        $content = $this->normalizePlaceholders(trim((string) $content));

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

        if ($content === '') {
            if ($conf['empty_behavior'] === 'delete') {
                File::delete($path);
            } else {
                $this->atomicPut($path, '');
            }

            return;
        }

        $this->atomicPut($path, $this->resolvePlaceholders($content).PHP_EOL);
    }

    /**
     * ads.txt 动辄几千行，直接覆盖写到一半失败会留下被爬虫抓走的截断清单。
     * 同目录临时文件写全 + rename 原子替换：要么是旧内容，要么是完整新内容。
     */
    protected function atomicPut(string $path, string $content): void
    {
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        try {
            $written = File::put($tmp, $content);

            if ($written === false || $written !== strlen($content)) {
                throw new \RuntimeException("写入 {$path} 不完整");
            }

            if (! @rename($tmp, $path)) {
                throw new \RuntimeException("替换 {$path} 失败");
            }

            @chmod($path, 0644);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /** {url} → 站点 URL（config('app.url')，与请求域名无关，便于换域名/CLI 一致）。 */
    public function resolvePlaceholders(string $content): string
    {
        return str_replace('{url}', $this->siteUrl(), $content);
    }

    /** 反向：把内容里等于站点 URL 的前缀收回成 {url}，确保 DB 永远存占位符不焊死域名。 */
    public function normalizePlaceholders(string $content): string
    {
        return str_replace($this->siteUrl(), '{url}', $content);
    }

    protected function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function defaultTemplate(string $type): string
    {
        $conf = $this->conf($type);

        if ($type === 'robots_txt') {
            if (! empty($conf['default_template'])) {
                return $conf['default_template'];
            }

            $sitemap = $conf['sitemap_url'] ?: '{url}/sitemap.xml';

            return "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /login\n\nSitemap: ".$sitemap."\n";
        }

        return '';
    }

    protected function conf(string $type): array
    {
        return config("nova-admin.$type");
    }
}
