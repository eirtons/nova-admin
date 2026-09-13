# 更新日志

本文件记录 `inova/nova-admin` 每个版本的对外变更。格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循语义化版本。

新增条目写在「未发布」下，打 tag 时整段移到新版本标题下。

## [未发布]
### 变更
- `nova-admin.page_cache.ttl` 回退默认值 600 → 3600 秒，与各站 `config/page-cache.php` 对齐。
  仅影响没有 `config/page-cache.php` 的项目；有该文件的以宿主为准，不受影响。


## [1.6.0] - 2026-09-11
### 新增
- `Inova\NovaAdmin\Http\Middleware\CacheablePage`：给包注册的前台响应补 `Cache-Control`（`public` + `max-age` + `s-maxage` + `stale-while-revalidate`），边缘副本按自然日封顶，避免跨天把昨天的内容带到今天
- 配置 `nova-admin.page_cache.ttl` / `cdn_ttl`（默认 600 / 86400 秒）。宿主若有 `config/page-cache.php` 以宿主为准，这里只是老项目的回退默认值
- 配置 `nova-admin.static_pages.frontend.cacheable`（env `NOVA_STATIC_FRONTEND_CACHEABLE`，默认 `true`）

### 变更
- **静态页前台路由默认不再挂 `web` 中间件组**。原本每个静态页响应都带 `Set-Cookie`，Cloudflare 一律按 `DYNAMIC` 处理、每次回源，`/about`、`/privacy-policy` 这类几乎永不变的页面完全进不了边缘缓存。改挂 `CacheablePage` 后无 Cookie、可缓存。
- `ads.txt` / `robots.txt` / `sitemap.xml` 三个路由同样挂上 `CacheablePage`

### 升级注意
- 包内默认静态页模板不含表单，脱离会话无影响。**若项目用 `static_pages.frontend.view` 指向了自己的模板，且模板里有 `@csrf` 表单或引用 `$errors`**，升级后会 419 或 500 —— 置 `NOVA_STATIC_FRONTEND_CACHEABLE=false` 可退回 `web` 组。
- 宿主前台若也脱离了会话，建议在 `AppServiceProvider::boot()` 里 `View::share('errors', new ViewErrorBag)` 兜底，避免视图引用 `$errors` 时 500。

## [1.5.1] - 2026-09-08
### 新增
- CI：GitHub Actions 在 push（master 与 `v*` tag）和 PR 时跑 PHPUnit（PHP 8.2）
- 本更新日志

### 修复
- `AdSpot::deactivateAll()` 走批量更新不触发模型事件，改为手动 `AdService::flush()`，避免同一请求内继续读到已禁用的广告

## [1.5.0] - 2026-09-08
### 新增
- `php artisan nova-admin:doctor` 自检广告位与协议映射一致性
- `config/nova-admin.php` 中 `ad_positions` / `ads_protocol` 标注为只可追加不可删行（浅合并会让整块删除悄悄回落成包默认值）

## [1.4.1] - 2026-08-28
### 新增
- ads.txt 大内容支持：静态文件原子写（tmp + rename）+ 后台编辑适配

## [1.4.0] - 2026-08-28
### 新增
- `<x-ad-body>` 支持 `wrapper=false`，供锚定/插屏这类自定位浮层使用
- README 补齐 `<x-ad-head>` / `<x-ad-body>` 成对出现、`global_head` 排最后的约定

## [1.3.1] - 2026-08-28
### 修复
- 默认管理员被写成非管理员

## [1.3.0] - 2026-08-28
### 优化
- 广告与站点配置读取加请求内缓存
- 广告位包装改为可覆盖视图

## [1.2.0] - 2026-08-27
### 变更
- 站点设置上传限制改为可配置
- 联系邮箱加格式校验

## [1.1.0] - 2026-08-27
### 新增
- 接入 webdeploy 站点广告配置下发协议（`ads:import-site-ad-config`）

## [1.0.23] - 2026-08-13
### 变更
- 限定 PHP 版本为 `~8.2.0`

## [1.0.22] - 2026-08-13
### 移除
- 移除免密登录
### 优化
- 增强配置容错

## [1.0.21] - 2026-07-24
### 移除
- 移除插屏与锚定代码生成功能

## [1.0.20] - 2026-07-21
### 新增
- 内置信任反代，宿主无需再配 TrustProxies

## [1.0.19] - 2026-07-08
### 新增
- 静态页前台路由内置，单一数据源开箱即用

## [1.0.18] - 2026-06-26
### 优化
- 广告位后台：测试数据填充/清空、一位一条约束、保存后跳列表、主色可配置

## [1.0.17] - 2026-06-26
### 变更
- robots 移除 `route_only` 开关，统一走静态文件

## [1.0.16] - 2026-06-26
### 新增
- 插屏与锚定工具页：代码生成、语法高亮、粘贴识别（后于 1.0.21 移除）

## [1.0.15] - 2026-06-25
### 变更
- quick-login 所有环境开放，宿主零配置直达后台（后于 1.0.22 移除）

## [1.0.14] - 2026-06-25
### 修复
- install 自动接入 FilamentUser，避免生产后台 403

## [1.0.13] - 2026-06-24
### 变更
- 命名空间 `Nova` → `Inova`，包名改为 `inova/nova-admin`

## [1.0.12] - 2026-06-24
### 修复
- `InstallCommand` use 语句转义

## [1.0.11] - 2026-06-24
### 变更
- 命名空间 `Nbutl` → `Nova`

## [1.0.10] - 2026-06-24
### 移除
- 删除 ads.txt / robots.txt 表单说明文字

## [1.0.9] - 2026-06-23
### 变更
- robots.txt 强制走路由，避免静态文件冻结域名

## [1.0.8] - 2026-06-22

## [1.0.7] - 2026-06-22
### 变更
- 基座默认日志按天切割：single channel driver 切为 daily

## [1.0.6] - 2026-06-15
### 变更
- 修改上传

## [1.0.5] - 2026-06-15
### 优化
- 优化广告组件

## [1.0.4] - 2026-06-11
### 新增
- 一键创建并接入后台 Panel

## [1.0.3] - 2026-06-11
### 文档
- 更新安装文档使用最新稳定版本

## [1.0.2] - 2026-06-11
### 修复
- 账号登录错误提示与中文校验

## [1.0.1] - 2026-06-11
### 优化
- 简化安装流程并自动注册 Filament 插件

## [1.0.0] - 2026-06-11
- 首个开源版本：移除 .idea 跟踪，补充 MIT LICENSE
