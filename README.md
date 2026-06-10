# nova-site-core

多站点复用的通用后台底座（**Laravel 12 + Filament 5**）：广告管理、站点设置、ads.txt、robots.txt、后台中文、账号密码登录、默认管理员、后台 Logo 跳前台。

> 接入后，新项目只需写自己的前台页面和业务后台，通用底座由本包统一维护、可一键 `composer update` 升级。

---

## 一、新建项目从零接入（已实测，可直接照抄）

### 1. 起一个干净 Laravel + Filament 项目

```bash
composer create-project laravel/laravel:^12.0 mysite
cd mysite

# 装 Filament 5 并创建后台 panel（生成 app/Providers/Filament/AdminPanelProvider.php）
composer require filament/filament:"^5.0" -W
php artisan filament:install --panels
```

> 数据库用 SQLite 最省事：`.env` 里 `DB_CONNECTION=sqlite`，并 `touch database/database.sqlite`（Laravel 12 默认已是 SQLite）。

### 2. 引用本包

**方式 A —— 本地 path 引用（本地多项目共用，推荐开发期）**

在 `mysite/composer.json` 顶层加 `repositories`，再 require：

```jsonc
{
  "repositories": [
    {
      "type": "path",
      "url": "../nova-site-core-pkg",
      "options": { "symlink": true }
    }
  ]
}
```

```bash
composer require "nbutl/nova-site-core:*"
```

> ⚠️ 实测注意：path 包必须带稳定版本号才能被默认 `minimum-stability: stable` 的项目引用。
> 本包 `composer.json` 已写 `"version": "1.0.0"`，无需额外处理。
> 路径 `url` 按你的实际目录调整（示例假设 `mysite` 与 `nova-site-core-pkg` 同级）。

**方式 B —— 私有 Git 仓库引用**

```jsonc
{
  "repositories": [
    { "type": "vcs", "url": "git@your-git-host:nbutl/nova-site-core.git" }
  ]
}
```

```bash
composer require "nbutl/nova-site-core:^1.0"
```

### 3. 发布配置与迁移，建表

```bash
php artisan vendor:publish --tag=nova-site-core-config
php artisan vendor:publish --tag=nova-site-core-migrations
php artisan migrate
```

> 会创建 `site_configs`、`ad_spots` 两张表，并生成 `config/nova-site-core.php`。

### 4. 在 AdminPanelProvider 一行接入插件

编辑 `app/Providers/Filament/AdminPanelProvider.php`：

```php
use Nbutl\NovaSiteCore\NovaSiteCorePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        // ...Filament 默认配置...
        ->plugin(NovaSiteCorePlugin::make());   // ← 加这一行
}
```

插件会自动注册：广告 Resource、站点设置 / ads.txt / robots.txt 页面、自定义账号密码登录页、
后台中文中间件、Logo 跳前台。

### 5. 生成默认管理员与示例广告位

```bash
# 一条龙：生成管理员 + 初始化广告位（含交互确认）
php artisan nova-site-core:install

# 或分开执行
php artisan nova-site-core:create-admin     # 默认管理员 nova / nova
php artisan ad:seed                          # 填充示例广告位
```

### 6. 启动验证

```bash
php artisan serve
```

访问 `http://127.0.0.1:8000/admin` → 用 **nova / nova** 登录，即可看到：
广告管理、站点设置、Ads.txt、Robots.txt 四个后台入口。

> 上线前请立即在后台修改默认管理员密码。

---

## 二、接入后你立即拥有

| 能力 | 入口 |
|------|------|
| 广告管理（一位多条、排序、启用） | 后台「广告管理」 |
| 站点设置（基础/SEO/媒体/品牌） | 后台「站点设置」 |
| ads.txt 编辑 | 后台「Ads.txt」+ `GET /ads.txt` |
| robots.txt 编辑（含默认模板） | 后台「Robots.txt」+ `GET /robots.txt` |
| sitemap.xml（静态条目 + 项目注册动态来源，带缓存） | `GET /sitemap.xml` |
| 账号密码登录 | `/admin/login`（账号字段可配） |
| 后台中文 | 自动 |
| 默认管理员 | `nova / nova` |
| Logo 点击跳前台 | 后台左上角品牌 |

> robots.txt / ads.txt 默认 `both` 模式：保存时写 `public/` 静态文件（web server 直出最快），
> 同时存数据库；文件不存在或只读时由路由兜底动态输出。
> 注意 Laravel 自带的 `public/robots.txt` 会优先于路由——`nova-site-core:install` 安装时会自动
> 用默认模板（`User-agent: * / Allow: / / Disallow: /admin` + `Sitemap`）覆盖它；
> 后台编辑框未保存过时也会预填该模板。
> 内容支持 `{url}` 占位符（= 当前请求域名）：数据库存占位符，路由输出与后台编辑框按当前域名
> 动态解析，写静态文件时按写入时刻的域名解析（install 时为 `APP_URL`）。

---

## 三、前台使用

```blade
{{-- 广告组件 --}}
<x-nova-site-core::ad position="home_banner1" />
<x-nova-site-core::ad-head position="global_head" />
```

```php
// helper
site_ad('home_banner1');         // 输出 body 广告
site_ad_head('global_head');     // 输出 head 广告
site_config('site_name');        // 读站点配置

// Facade
use Nbutl\NovaSiteCore\Facades\SiteConfig;
SiteConfig::get('site_name', 'default');
SiteConfig::set('site_name', 'My Site');         // string
SiteConfig::set('ads_enabled', true, 'boolean'); // 按 type 存取
```

### Sitemap

包自带 `GET /sitemap.xml`（robots.txt 默认模板已指向它）。静态条目在 config
`nova-site-core.sitemap.urls` 配置；动态内容在项目 `AppServiceProvider::boot` 注册：

```php
use Nbutl\NovaSiteCore\Facades\Sitemap;

Sitemap::register(fn () => Article::published()->get()->map(fn ($a) => [
    'loc'      => route('articles.show', $a),
    'lastmod'  => $a->updated_at,          // 可选，DateTime 或字符串
    'priority' => '0.7',                   // 可选；changefreq 同理
]));
```

输出带缓存（`sitemap.cache_ttl`，默认 1800 秒），内容更新后可执行
`php artisan nova-site-core:clear-cache` 立即刷新；项目自带 sitemap 时置
`sitemap.enabled = false` 关闭包路由。

---

## 四、命令

```bash
php artisan nova-site-core:install                  # 安装：管理员 + 初始化数据
php artisan nova-site-core:create-admin [--force]   # 创建/重置默认管理员
php artisan ad:seed [--off]                          # 填充（先清空）/ 禁用广告
php artisan nova-site-core:clear-cache              # 清广告缓存
```

---

## 五、配置

发布后编辑 `config/nova-site-core.php`，常用项：

```php
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
- **给包的表加字段**：项目写补充 ALTER 迁移加列 + 继承包模型，再用 `config('nova-site-core.models.*')` 指向子类。
- **简单业务配置**：直接走 `site_configs` 键值（`SiteConfig::set`），无需建表。
- **定制包页面**：① 改 `config`（零代码）→ ② `vendor:publish --tag=nova-site-core-views` 改 blade → ③ 继承包的 Resource/Page 类覆盖方法。

> 完整扩展指南见设计文档《公共功能抽离设计方案.md》第十四节。

---

## 生产部署注意（必读，否则后台登录会挂）

本包的后台（含登录页）基于 Filament / Livewire。宝塔等面板的 Nginx 默认配置会用静态
`location` 拦截所有 `.js` 请求，导致 Livewire 的 `/livewire/livewire.js` 路由 404 ——
表现为**后台登录页点击无反应、所有 Livewire 交互失效**。

部署（含每次更新）时必须把前端资源发布为物理文件：

```bash
php artisan filament:assets             # Filament 静态资源 → public/
php artisan livewire:publish --assets   # Livewire JS → public/vendor/livewire/
```

并确保 `public/vendor/livewire` 归属 web 用户（如 `chown -R www:www public/vendor/livewire`）。

站点设置的 Favicon / Logo 上传存储在 `storage/app/public/site/`，需要 storage 软链
（`nova-site-core:install` 已自动创建；手动执行 `php artisan storage:link`），
并保证 `storage/` 归属 web 用户、软链 `public/storage` 随站点一起部署。
若生产启用了 `opcache.validate_timestamps=0`，更新后还需 reload php-fpm 重置 OPcache。

> 完整可参考 webdeploy 的 `deploy-scripts/worldcup-news/create.sh` / `update.sh`。

---

## 升级

```bash
composer update nbutl/nova-site-core
php artisan vendor:publish --tag=nova-site-core-migrations  # 若有新迁移
php artisan migrate
```
