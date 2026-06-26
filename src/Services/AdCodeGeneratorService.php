<?php

namespace Inova\NovaAdmin\Services;

/**
 * Google Ad Manager (GPT) 广告代码生成器。
 * 从 novatool/app/Services/AdCodeService.php 移植，逐字保留输出格式。
 * 生成的代码只用 googletag、不含 GPT 库加载脚本（假设页面别处已加载）。
 */
class AdCodeGeneratorService
{
    /**
     * @param  array{adType?:string,interstitialAdUnitId:string,anchorAdUnitId?:string,anchorSizes?:string,anchorDivId?:string,anchorPosition?:string}  $params
     */
    public function generate(array $params): string
    {
        $interstitialAdUnitId = htmlspecialchars($params['interstitialAdUnitId'], ENT_QUOTES, 'UTF-8');

        // 仅插屏广告
        if (($params['adType'] ?? 'both') === 'interstitial') {
            return <<<HTML
<script>
  window.googletag = window.googletag || { cmd: [] };

  googletag.cmd.push(function() {
    let interstitialSlot = googletag.defineOutOfPageSlot(
      '{$interstitialAdUnitId}',
      googletag.enums.OutOfPageFormat.INTERSTITIAL
    );

    if (interstitialSlot) {
      interstitialSlot.addService(googletag.pubads()).setConfig({
        interstitial: { triggers: { navBar: true, unhideWindow: true } }
      });
    }

    googletag.pubads().enableSingleRequest();
    googletag.enableServices();

    if (interstitialSlot) { googletag.display(interstitialSlot); }
  });
</script>
HTML;
        }

        // 插屏 + 锚定广告（组合模式）
        $anchorAdUnitId = htmlspecialchars($params['anchorAdUnitId'], ENT_QUOTES, 'UTF-8');
        $anchorSizes = $params['anchorSizes']; // JSON 数组，直接插入 JS，不做 HTML 转义
        $anchorDivId = htmlspecialchars($params['anchorDivId'], ENT_QUOTES, 'UTF-8');
        $anchorPosition = htmlspecialchars($params['anchorPosition'], ENT_QUOTES, 'UTF-8');

        $positionStyle = $anchorPosition === 'top' ? 'top: 0;' : 'bottom: 0;';

        return <<<HTML
<script>
  window.googletag = window.googletag || { cmd: [] };

  googletag.cmd.push(function() {
    googletag.defineSlot('{$anchorAdUnitId}', {$anchorSizes}, '{$anchorDivId}')
      .addService(googletag.pubads());

    let interstitialSlot = googletag.defineOutOfPageSlot(
      '{$interstitialAdUnitId}',
      googletag.enums.OutOfPageFormat.INTERSTITIAL
    );

    if (interstitialSlot) {
      interstitialSlot.addService(googletag.pubads()).setConfig({
        interstitial: { triggers: { navBar: true, unhideWindow: true } }
      });
    }

    googletag.pubads().enableSingleRequest();
    googletag.enableServices();

    if (interstitialSlot) { googletag.display(interstitialSlot); }
  });
</script>

<div id='{$anchorDivId}'
     style="position: fixed; {$positionStyle} left: 0; right: 0;
            z-index: 9999; min-width: 300px; min-height: 50px;">
  <script>
    googletag.cmd.push(function() {
      googletag.display('{$anchorDivId}');
    });
  </script>
</div>
HTML;
    }
}
