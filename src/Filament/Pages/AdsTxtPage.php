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
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')
                    ->label($this->fieldLabel)
                    ->rows(14)
                    ->placeholder($this->placeholder),
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
