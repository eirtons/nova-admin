{{-- 广告位包装。项目可 publish 到 resources/views/vendor/nova-admin/components/ad-slot.blade.php 覆盖。
     保留 .ad：块级子元素的居中样式（AdBody 输出）与主题用 :has(.ad) 预留高度都靠它。 --}}
<div class="ad" style="width: 100% !important; text-align: center !important; margin: 10px auto !important; overflow: hidden !important;">{!! $html !!}</div>
