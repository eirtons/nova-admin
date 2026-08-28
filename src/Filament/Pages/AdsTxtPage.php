<?php

namespace Inova\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Inova\NovaAdmin\Services\PublicTextFileService;

class AdsTxtPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = 'Ads.txt';

    protected static ?string $navigationLabel = 'Ads.txt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'nova-admin::filament.pages.form-save';

    public ?array $data = [];

    protected string $configType = 'ads_txt';

    protected string $fieldLabel = 'Ads.txt 内容';

    protected string $placeholder = 'google.com, pub-xxxxxxxxxxxxxxxx, DIRECT, f08c47fec0942fa0';

    /** ads.txt 常见几千行，编辑框给足高度；robots.txt 只有十几行，子类调小。 */
    protected int $rows = 28;

    /** 当前已保存内容的规模提示，保存后刷新。 */
    public string $sizeHint = '';

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.settings');
    }

    public function mount(): void
    {
        $svc = app(PublicTextFileService::class);
        $content = $svc->readRaw($this->configType);

        // 从未保存过时预填默认模板（robots.txt 含按域名生成的 Sitemap；ads.txt 模板为空）
        if (trim($content) === '') {
            $content = $svc->defaultTemplate($this->configType);
        }

        // {url} 占位符按当前请求域名解析后展示
        $this->form->fill(['content' => $svc->resolvePlaceholders($content)]);

        $this->sizeHint = $this->sizeHint($content);
    }

    protected function sizeHint(string $content): string
    {
        $content = trim($content);
        $lines = $content === '' ? 0 : substr_count($content, "\n") + 1;
        $kb = round(strlen($content) / 1024, 1);

        return "当前 {$lines} 行 / {$kb} KB；保存后同时写入数据库与静态文件，写文件失败会降级为路由动态输出。";
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')
                    ->label($this->fieldLabel)
                    ->rows($this->rows)
                    ->helperText(fn () => $this->sizeHint)
                    ->extraInputAttributes(['style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, monospace;'])
                    ->placeholder($this->placeholder),
            ])
            ->statePath('data');
    }


    public function save(): void
    {
        $content = (string) ($this->form->getState()['content'] ?? '');

        $result = app(PublicTextFileService::class)->save($this->configType, $content);

        $this->sizeHint = $this->sizeHint($content);

        if ($result['file_written']) {
            Notification::make()->title('已保存')->success()->send();
        } else {
            Notification::make()->title('已保存（' . $result['message'] . '）')->warning()->send();
        }
    }
}
