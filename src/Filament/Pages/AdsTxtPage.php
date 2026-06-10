<?php

namespace Nbutl\NovaSiteCore\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Nbutl\NovaSiteCore\Services\PublicTextFileService;

class AdsTxtPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = 'Ads.txt';

    protected static ?string $navigationLabel = 'Ads.txt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'nova-site-core::filament.pages.text-file';

    public ?array $data = [];

    protected string $configType = 'ads_txt';

    protected string $fieldLabel = 'Ads.txt 内容';

    public static function getNavigationGroup(): ?string
    {
        return config('nova-site-core.navigation.group');
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
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make($this->fieldLabel)
                    ->schema([
                        Textarea::make('content')
                            ->label($this->fieldLabel)
                            ->rows(12),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $result = app(PublicTextFileService::class)
            ->save($this->configType, $this->form->getState()['content'] ?? '');

        if ($result['file_written']) {
            Notification::make()->title('已保存')->success()->send();
        } else {
            Notification::make()->title('已保存（' . $result['message'] . '）')->warning()->send();
        }
    }
}
