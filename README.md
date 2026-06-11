# nova-admin

多站点复用的通用后台底座（**Laravel 12 + Filament 5**）：广告管理、站点设置、ads.txt、robots.txt、后台中文、账号密码登录、默认管理员、后台 Logo 跳前台。

> 接入后，新项目只需写自己的前台页面和业务后台，通用底座由本包统一维护。

---

## 一、新建项目从零接入（已实测，可直接照抄）

### 1. 创建 Laravel 项目并安装本包

```bash
composer create-project laravel/laravel:^12.0 mysite
cd mysite

composer require nbutl/nova-admin
```

不指定版本号时，Composer 会安装 Packagist 上兼容当前项目的最新稳定版本。
`nova-admin` 已依赖 Filament 5，不需要再单独执行
`composer require filament/filament`。

> Laravel 12 新项目默认使用 SQLite；改用 MySQL 等数据库时，先正确配置 `.env`。

### 2. 一键安装

```bash
php artisan nova-admin:install
```

该命令会自动创建并接入默认的 `admin` Panel、发布配置、执行包内及项目待运行迁移、
创建默认管理员、填充示例广告、初始化 robots.txt 和站点设置，并创建 storage 软链。
无需单独安装 Filament Panel，也无需手动修改 `AdminPanelProvider.php`。

安装命令还会将后台生成的 `public/robots.txt`、`public/ads.txt` 加入项目
`.gitignore`。项目已初始化 Git 时，会同时取消对 Laravel 默认
`public/robots.txt` 的跟踪。

### 3. 启动验证

```bash
php artisan serve
```

访问 `http://127.0.0.1:8000/admin` → 用 **nova / nova** 登录，即可看到：
广告管理、站点设置、Ads.txt、Robots.txt、系统日志五个后台入口。

> 上线前请立即在后台修改默认管理员密码。

---

## 二、接入后你立即拥有

| 能力 | 入口 |
|------|------|
| 广告管理（一位多条、按创建顺序输出、启用） | 后台「广告管理」 |
| 站点设置（基础/SEO/媒体/品牌） | 后台「站点设置」 |
| ads.txt 编辑 | 后台「Ads.txt」+ `GET /ads.txt` |
| robots.txt 编辑（含默认模板） | 后台「Robots.txt」+ `GET /robots.txt` |
| sitemap.xml（静态条目 + 项目注册动态来源，带缓存） | `GET /sitemap.xml` |
| 系统日志（查看尾部 / 下载 / 删除，兼容单文件与按天分割） | 后台「系统日志」 |
| 账号密码登录 | `/admin/login`（账号字段可配） |
| 后台中文 | 自动 |
| 默认管理员 | `nova / nova` |
| Logo 点击跳前台 | 后台左上角品牌 |
---

## 三、前台使用

```blade
{{-- 广告组件 --}}
<x-nova-admin::ad position="home_banner1" />
<x-nova-admin::ad-head position="global_head" />
```

```php
// helper
site_ad('home_banner1');         // 输出 body 广告
site_ad_head('global_head');     // 输出 head 广告
site_config('site_name');        // 读站点配置

// Facade
use Nbutl\NovaAdmin\Facades\SiteConfig;
SiteConfig::get('site_name', 'default');
SiteConfig::set('site_name', 'My Site');         // string
SiteConfig::set('ads_enabled', true, 'boolean'); // 按 type 存取
```

### Sitemap

包自带 `GET /sitemap.xml`（robots.txt 默认模板已指向它）。静态条目在 config
`nova-admin.sitemap.urls` 配置；动态内容在项目 `AppServiceProvider::boot` 注册：

```php
use Nbutl\NovaAdmin\Facades\Sitemap;

Sitemap::register(fn () => Article::published()->get()->map(fn ($a) => [
    'loc'      => route('articles.show', $a),
    'lastmod'  => $a->updated_at,          // 可选，DateTime 或字符串
    'priority' => '0.7',                   // 可选；changefreq 同理
]));
```

输出带缓存（`sitemap.cache_ttl`，默认 1800 秒），内容更新后可执行
`php artisan nova-admin:clear-cache` 立即刷新；项目自带 sitemap 时置
`sitemap.enabled = false` 关闭包路由。

---

## 四、命令

```bash
php artisan nova-admin:install                  # 接入 Panel、建表并初始化
php artisan nova-admin:create-admin [--force]   # 创建/重置默认管理员
php artisan ad:seed [--off]                     # 填充测试广告（先清空）/ 禁用广告
php artisan nova-admin:clear-cache              # 清广告与 sitemap 缓存
```

---

## 五、配置

发布后编辑 `config/nova-admin.php`，常用项：

```php
'panel'        => ['id' => 'admin'],
'ad_positions' => [ /* 自定义广告位枚举 */ ],
'navigation'   => ['group' => '站点设置', 'sort' => 90],
'admin'        => ['default_name' => 'nova', 'login_field' => 'name'],
'admin_brand'  => ['logo_link_to_front' => true, 'front_url' => '/', 'new_tab' => true],
'ads_txt'      => ['storage' => 'both', 'route_fallback' => true],
'robots_txt'   => ['storage' => 'both', 'sitemap_url' => null],
'models'       => [ /* 指向项目子类以替换包模型 */ ],
```

---

## 六、新项目如何扩展后台功能

- **加纯业务功能**（如 Game / Destination）：项目正常写 Filament Resource/Page，与本包并列注册，互不干扰。
- **给包的表加字段**：项目写补充 ALTER 迁移加列 + 继承包模型，再用 `config('nova-admin.models.*')` 指向子类。
- **简单业务配置**：直接走 `site_configs` 键值（`SiteConfig::set`），无需建表。
- **定制包页面视图**：发布 `vendor:publish --tag=nova-admin-views` 后修改 Blade。

---

## 生产部署注意（必读，否则后台登录会挂）

本包的后台（含登录页）基于 Filament / Livewire。某些面板生成的 Nginx 配置会用静态
`location` 拦截所有 `.js` 请求，导致 Livewire 的 `/livewire/livewire.js` 路由 404 ——
表现为**后台登录页点击无反应、所有 Livewire 交互失效**。

如果服务器静态规则会拦截 `/livewire/livewire.js`，部署时把前端资源发布为物理文件：

```bash
php artisan filament:assets             # Filament 静态资源 → public/
php artisan livewire:publish --assets   # Livewire JS → public/vendor/livewire/
```

并确保 `public/vendor/livewire` 归属 web 用户（如 `chown -R www:www public/vendor/livewire`）。

站点设置的 Favicon / Logo 上传存储在 `storage/app/public/site/`，需要 storage 软链
（`nova-admin:install` 已自动创建；手动执行 `php artisan storage:link`），
并保证 `storage/` 归属 web 用户、软链 `public/storage` 随站点一起部署。
若生产启用了 `opcache.validate_timestamps=0`，更新后还需 reload php-fpm 重置 OPcache。

---

## 升级

```bash
composer update nbutl/nova-admin
php artisan migrate
```
