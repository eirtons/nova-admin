<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Panel
    |--------------------------------------------------------------------------
    | install 命令会自动将插件接入此 ID 的 Panel，无需手动修改 PanelProvider。
    */
    'panel' => [
        'id' => env('NOVA_ADMIN_PANEL_ID', 'admin'),
        // 主色：Filament 内置色板名（amber/blue/indigo/emerald/slate/rose…）。null = 用 Filament 默认
        'primary_color' => env('NOVA_ADMIN_PRIMARY_COLOR', 'indigo'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 后台导航分组
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'groups' => [
            'settings' => '基础设置',
            'content'  => '内容管理',
            'system'   => '系统',
        ],
        'sort' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | 广告位枚举
    |--------------------------------------------------------------------------
    | 一个 position 可对应多条 AdSpot 记录，按创建顺序输出。
    */
    'ad_positions' => [
        'global_head'    => '全局 Head',
        'home_banner1'   => '首页 Banner 1',
        'home_banner2'   => '首页 Banner 2',
        'detail_banner1' => '详情页 Banner 1',
        'detail_banner2' => '详情页 Banner 2',
    ],

    /*
    |--------------------------------------------------------------------------
    | ads.txt
    |--------------------------------------------------------------------------
    */
    'ads_txt' => [
        'enabled'        => true,
        'path'           => public_path('ads.txt'),
        'config_key'     => 'ads_txt_content',
        'empty_behavior' => 'delete', // keep_empty | delete
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    */
    'robots_txt' => [
        'enabled'          => true,
        'path'             => public_path('robots.txt'),
        'config_key'       => 'robots_txt_content',
        'empty_behavior'   => 'keep_empty',
        'sitemap_url'      => null,   // null = url('/sitemap.xml')
        'default_template' => null,   // null = 内置模板
        // 落 public/robots.txt 静态文件，Sitemap 行按 APP_URL 生成写入（单域名/每站独立部署）。
        // /robots.txt 路由仅作兜底：静态文件丢失或写失败时降级动态输出。
    ],

    /*
    |--------------------------------------------------------------------------
    | 后台布局
    |--------------------------------------------------------------------------
    | 侧边栏默认收窄至 16rem（Filament 默认 20rem 偏宽）。
    | max_content_width 默认 null = Filament 默认上限（表单页协调）；
    | 需要全局放宽时可设 \Filament\Support\Enums\Width 枚举或 CSS 值，
    | 系统日志等需要大空间的页面已自行覆盖为全宽。
    */
    'layout' => [
        'max_content_width' => null,
        'sidebar_width'     => '16rem',
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
        'view_tail_kb' => 256,     // 无筛选浏览时每个文件读取的尾部大小
        'search_limit' => 100,     // 浏览/检索展示的最大条目数
    ],

    /*
    |--------------------------------------------------------------------------
    | 静态页面（关于 / 隐私政策 / 服务条款等富文本落地页）
    |--------------------------------------------------------------------------
    | presets 为安装时预置的页面，结构 slug => [英文标签, 中文备注]：
    | 英文标签存为 title（前台展示用），中文备注仅后台标签页显示，便于辨认。
    | 后台标签页渲染为「About（关于我们）」。前台读取：static_page('about')->content。
    |
    | 安装时读取 defaults/static-pages/{slug}.html 模板、替换占位符
    | （{{site_name}} / {{site_description}} / {{contact_email}}）后写入 content，
    | 作为 AdSense 合规初稿（隐私政策含 Cookie / AdSense / GDPR 措辞）。
    | 模板查找顺序：宿主 resources/defaults/static-pages/ 优先，回退包内默认，
    | 故个别站点可放同名 .html 覆盖默认文案，无需改包。
    | site_description 为站点业务一句话描述，由各站点在此填写。
    */
    'static_pages' => [
        'enabled' => true,
        'site_description' => env('NOVA_SITE_DESCRIPTION', 'an online service'),
        'presets' => [
            'about'            => ['About', '关于我们'],
            'contact'          => ['Contact', '联系我们'],
            'privacy-policy'   => ['Privacy Policy', '隐私政策'],
            'terms-of-service' => ['Terms of Service', '服务条款'],
            'disclaimer'       => ['Disclaimer', '免责声明'],
            'faq'              => ['FAQ', '常见问题'],
            'dmca'             => ['DMCA', 'DMCA 版权'],
        ],
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
        'enabled'     => true,
        'cache_store' => env('NOVA_SITEMAP_CACHE_STORE'),
        'cache_ttl'   => env('NOVA_SITEMAP_CACHE_TTL', 1800), // 秒；0 = 不缓存
        'cache_key'   => 'nova_admin:sitemap',
        'urls'        => [
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
