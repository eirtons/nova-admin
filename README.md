# nova-admin

多站点复用的通用后台底座（**Laravel 12 + Filament 5**）：广告管理、插屏与锚定代码生成、站点设置、静态页面、ads.txt、robots.txt、后台中文、账号密码登录、默认管理员、后台 Logo 跳前台。

---
## 一、新建项目从零接入

### 1. 创建 Laravel 项目并安装本包

```bash
composer create-project laravel/laravel:^12.0 mysite
cd mysite

composer require inova/nova-admin
```

已依赖 Filament 5，无需单独安装。

> 默认 SQLite；用 MySQL 等先配 `.env`。
> 生产 `APP_URL` 须为完整 URL（如 `https://example.com`）——robots.txt 的 `Sitemap` 行按它生成。

### 2. 一键安装

```bash
php artisan nova-admin:install
```

一条命令搞定全部：接入 `admin` Panel、跑迁移、建默认管理员、初始化 robots/站点设置、
填充示例广告、发布静态资源、建 storage 软链。无需手动碰 `AdminPanelProvider.php`。

附带处理：公开文件（`robots.txt`/`ads.txt`/`vendor/livewire`）加入 `.gitignore`，
`App\Models\User` 自动接入 `FilamentUser`（防生产 403）。自定义才需发布 `config/nova-admin.php`。

### 3. 启动验证

```bash
php artisan serve
```

访问 `http://127.0.0.1:8000/admin` → 用 **nova / nova** 登录。

> 上线前立即在后台改默认密码。

---

## 二、接入后你立即拥有

| 能力 | 入口 |
|------|------|
| 广告管理（一位多条、按序输出、代码框语法高亮） | 后台「广告管理」 |
| 插屏与锚定（生成 Google GPT 插屏/锚定代码，支持粘贴自动识别） | 后台「插屏与锚定」 |
| 站点设置（基础/SEO/媒体/品牌） | 后台「站点设置」 |
| 静态页面（关于/隐私/条款等富文本落地页，可增删改） | 后台「静态页面」 |
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
{{-- 放在 <head> 内，输出该广告位的 head_code --}}
<x-ad-head position="global_head" />

{{-- 放在页面展示位置，输出 body_code；无生效广告时不产生 DOM --}}
<x-ad-body position="home_banner1" />
```

```php
site_config('site_name');        // 读站点配置

// Facade
use Inova\NovaAdmin\Facades\SiteConfig;
SiteConfig::get('site_name', 'default');
SiteConfig::set('site_name', 'My Site');         // string
SiteConfig::set('ads_enabled', true, 'boolean'); // 按 type 存取
```

### 静态页面

后台「静态页面」管理关于、隐私政策、服务条款等富文本落地页。安装时按
`nova-admin.static_pages.presets` 预置一批页面，后台可继续增删改。

前台按 slug 读取（仅返回**已启用**页面，未找到或已停用返回 `null`）：

```blade
@php($page = static_page('privacy-policy'))

@if ($page)
    <h1>{{ $page->title }}</h1>
    <div class="prose">{!! $page->content !!}</div>
@endif
```

对应路由可在项目自行定义，例如：

```php
// routes/web.php
Route::get('/page/{slug}', function (string $slug) {
    abort_unless($page = static_page($slug), 404);

    return view('pages.show', compact('page'));
})->name('static-page');
```

> `content` 为富文本 HTML，输出用 `{!! !!}`（内容由后台管理员录入，可信）。

### 插屏与锚定

后台「插屏与锚定」按表单参数生成 Google GPT 插屏/锚定广告代码（支持粘贴现有代码自动识别填充）。
生成后复制到「广告管理 → 全局 Head」的 Head 代码即可。仅生成代码，不含 GPT 库加载脚本。

### Sitemap

包自带 `GET /sitemap.xml`（robots.txt 默认模板已指向它）。静态条目在 config
`nova-admin.sitemap.urls` 配置；动态内容在项目 `AppServiceProvider::boot` 注册：

```php
use Inova\NovaAdmin\Facades\Sitemap;

Sitemap::register(fn () => Article::published()->get()->map(fn ($a) => [
    'loc'      => route('articles.show', $a),
    'lastmod'  => $a->updated_at,          // 可选，DateTime 或字符串
    'priority' => '0.7',                   // 可选；changefreq 同理
]));
```

输出带缓存（`sitemap.cache_ttl`，默认 1800 秒），内容更新后可执行
`php artisan nova-admin:clear-sitemap-cache` 立即刷新；项目自带 sitemap 时置
`sitemap.enabled = false` 关闭包路由。

---

## 四、命令

```bash
php artisan nova-admin:install                  # 接入 Panel、建表并初始化
php artisan nova-admin:create-admin [--force]   # 创建/重置默认管理员
php artisan ad:seed [--off]                     # 填充测试广告（先清空）/ 禁用广告
php artisan nova-admin:clear-sitemap-cache       # 清 sitemap 缓存
```

---

## 五、配置

发布后编辑 `config/nova-admin.php`，常用项：

```php
'panel'        => ['id' => 'admin'],
'ad_positions' => [ /* 自定义广告位枚举 */ ],
'navigation'   => [
    'groups' => ['settings' => '基础设置', 'content' => '内容管理', 'system' => '系统'],
    'sort' => 90,
],
'admin'        => ['default_name' => 'nova', 'login_field' => 'name'],
'admin_brand'  => ['logo_link_to_front' => true, 'front_url' => '/', 'new_tab' => true],
'ads_txt'      => ['enabled' => true, 'empty_behavior' => 'delete'],
'robots_txt'   => ['enabled' => true, 'sitemap_url' => null],
'static_pages' => [
    'enabled' => true,
    'presets' => [ /* slug => [英文, 中文]，安装时预置；置 enabled=false 关闭整个功能 */ ],
],
```

---

## 六、新项目如何扩展后台功能

- **加纯业务功能**（如 Game / Destination）：项目正常写 Filament Resource/Page，与本包并列注册，互不干扰。
- **给包的表加字段**：项目写补充 ALTER 迁移加列，在项目自己的 Resource/Service 中使用扩展后的模型。
- **简单业务配置**：直接走 `site_configs` 键值（`SiteConfig::set`），无需建表。
- **定制包页面视图**：发布 `vendor:publish --tag=nova-admin-views` 后修改 Blade。

---

## 生产部署 / 升级

首次安装用 `composer install`，升级用 `composer update inova/nova-admin`，其余相同：

```bash
php artisan nova-admin:install --force
php artisan optimize:clear && php artisan optimize
```

`nova-admin:install` 会自动处理 `FilamentUser` 接入（避免后台 403）、发布
Filament / Livewire 静态资源、`storage:link` 与公开文件忽略。

生产服务器需确保 `storage/`、`public/vendor/livewire` 归属 web 用户。若启用了
`opcache.validate_timestamps=0`，发布后 reload php-fpm。
