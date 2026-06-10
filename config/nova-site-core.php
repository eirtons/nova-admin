<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 模型替换
    |--------------------------------------------------------------------------
    | 项目继承包模型扩展后，把这里指向自己的子类，包内部即自动使用项目模型。
    */
    'models' => [
        'ad_spot'     => \Nbutl\NovaSiteCore\Models\AdSpot::class,
        'site_config' => \Nbutl\NovaSiteCore\Models\SiteConfig::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 后台导航分组
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => '站点设置',
        'sort'  => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | 广告位枚举
    |--------------------------------------------------------------------------
    | 一个 position 可对应多条 AdSpot 记录，按 sort_order 排序输出。
    */
    'ad_positions' => [
        'global_head'  => '全局 Head',
        'home_banner1' => '首页 Banner 1',
        'home_banner2' => '首页 Banner 2',
        'page_detail'  => '详情页 Banner',
    ],

    /*
    |--------------------------------------------------------------------------
    | 广告缓存
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'store'      => env('NOVA_CACHE_STORE', null), // null = 项目默认 store
        'ttl'        => env('NOVA_CACHE_TTL', 3600),    // 秒；0 = 不缓存
        'key_prefix' => 'nova_site_core:ads:',
    ],

    /*
    |--------------------------------------------------------------------------
    | ads.txt
    |--------------------------------------------------------------------------
    */
    'ads_txt' => [
        'enabled'        => true,
        'storage'        => 'both',   // file | database | both
        'route_fallback' => true,
        'path'           => public_path('ads.txt'),
        'config_key'     => 'ads_txt_content',
        'empty_behavior' => 'keep_empty', // keep_empty | delete
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    */
    'robots_txt' => [
        'enabled'          => true,
        'storage'          => 'both',
        'route_fallback'   => true,
        'path'             => public_path('robots.txt'),
        'config_key'       => 'robots_txt_content',
        'empty_behavior'   => 'keep_empty',
        'sitemap_url'      => null,   // null = url('/sitemap.xml')
        'default_template' => null,   // null = 内置模板
    ],

    /*
    |--------------------------------------------------------------------------
    | 系统日志（后台查看 / 下载 / 删除）
    |--------------------------------------------------------------------------
    | paths 为空时默认 storage/logs，兼容单文件 laravel.log 与按天分割；
    | 生产可追加其它目录（如 supervisor 日志 /www/wwwlogs/supervisor）。
    */
    'logs' => [
        'enabled'      => true,
        'paths'        => [],      // 空 = [storage_path('logs')]
        'pattern'      => '*.log',
        'view_tail_kb' => 256,     // 「查看」弹窗读取的尾部大小
        'search_limit' => 200,     // 全文搜索返回的最大命中行数
    ],

    /*
    |--------------------------------------------------------------------------
    | sitemap.xml
    |--------------------------------------------------------------------------
    | robots.txt 默认模板指向 /sitemap.xml，由本包路由输出（项目自带 sitemap 时
    | 置 enabled=false）。urls 为静态条目；动态内容在项目 ServiceProvider::boot 注册：
    |   Sitemap::register(fn () => Article::published()->get()
    |       ->map(fn ($a) => ['loc' => route('articles.show', $a), 'lastmod' => $a->updated_at]));
    */
    'sitemap' => [
        'enabled'   => true,
        'cache_ttl' => env('NOVA_SITEMAP_CACHE_TTL', 1800), // 秒；0 = 不缓存
        'cache_key' => 'nova_site_core:sitemap',
        'urls'      => [
            ['loc' => '/', 'changefreq' => 'daily', 'priority' => '1.0'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 站点设置默认值
    |--------------------------------------------------------------------------
    | install 时写入 site_configs；站点设置页未保存过的字段也用它预填。
    */
    'site_defaults' => [
        'contact_email' => env('NOVA_CONTACT_EMAIL', 'logan.luo@adsnova.cn'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认管理员（AdminUserSeeder 读取）
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'default_name'     => env('NOVA_ADMIN_NAME', 'nova'),
        'default_email'    => env('NOVA_ADMIN_EMAIL', 'nova@example.com'),
        'default_password' => env('NOVA_ADMIN_PASSWORD', 'nova'),
        'login_field'      => env('NOVA_ADMIN_LOGIN_FIELD', 'name'), // name | username | email
    ],

    /*
    |--------------------------------------------------------------------------
    | 后台品牌 Logo 跳前台首页
    |--------------------------------------------------------------------------
    */
    'admin_brand' => [
        'logo_link_to_front' => true,
        'front_url'          => env('NOVA_FRONT_URL', '/'),
        'new_tab'            => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 快速登录（开发用）
    |--------------------------------------------------------------------------
    | 访问 path 即以数据库第一个用户身份登录并跳转后台，免输账号密码。
    | 仅 local 环境启用。
    */
    'quick_login' => [
        'path'     => '/quick-login',
        'redirect' => '/admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | 后台语言
    |--------------------------------------------------------------------------
    */
    'locale' => env('NOVA_ADMIN_LOCALE', 'zh_CN'),

];
