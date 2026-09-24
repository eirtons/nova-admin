<?php

/*
|--------------------------------------------------------------------------
| nova-admin 站点差异配置
|--------------------------------------------------------------------------
| 包内 config/nova-admin.php 是出厂底座，这里只写本站与之不同的部分：
| 升级 inova/nova-admin 后，包新增的广告位、协议映射等配置自动继承。
|
| 合并规则：
| - 关联数组逐键合并：写一行就覆盖/追加一项；
| - 列表（如 sitemap.urls、favicon.accepted_types）整体替换；
| - 空数组等于没写；
| - 写 false 删除包内的键（包里是布尔值的键除外，那就是普通的关闭）。
|
| 完整配置项见 vendor/inova/nova-admin/config/nova-admin.php，
| 或 php artisan vendor:publish --tag=nova-admin-config 导出查看（导出后请改回差异写法）。
| 改完跑 php artisan nova-admin:doctor 自检。
*/

return [

    /*
    | 本站专属广告位：追加一行；去掉包内的位写 false，
    | 并同步处理 ads_protocol.position_map 里指向它的键。
    */
    'ad_positions' => [
        // 'custom_spot'  => '专属广告位',
        // 'interstitial' => false,
    ],

    'ads_protocol' => [
        'position_map' => [
            // 'custom_spot'  => 'custom_spot',
            // 'interstitial' => false,
        ],
    ],

    /*
    | 布局级（浮层 / 脚本类）广告位，由 <x-ad-layout-head/body /> 自动输出。
    */
    'ad_layout_positions' => [
        // 'custom_float' => true,
    ],

];
