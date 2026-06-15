<?php

namespace Nbutl\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Nbutl\NovaAdmin\Services\SiteConfigService;

class SiteSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = '站点设置';

    protected static ?string $navigationLabel = '站点设置';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'nova-admin::filament.pages.form-save';

    public ?array $data = [];

    /** 字段 => [label, group] */
    protected array $fields = [
        'site_name'           => ['站点名称', 'basic'],
        'subtitle'            => ['副标题', 'basic'],
        'copyright'           => ['版权', 'basic'],
        'contact_email'       => ['联系邮箱', 'basic'],
        'meta_title_template' => ['标题模板', 'seo'],
        'meta_description'    => ['Meta 描述', 'seo'],
        'meta_keywords'       => ['Meta 关键词', 'seo'],
        'favicon_path'        => ['Favicon', 'media'],
        'logo_path'           => ['Logo', 'media'],
        'brand_color'         => ['品牌色', 'brand'],
    ];

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.group');
    }

    public function mount(): void
    {
        $config = app(SiteConfigService::class);

        $this->form->fill(
            collect($this->fields)
                ->mapWithKeys(function ($meta, $key) use ($config) {
                    $value = $config->get($key, config("nova-admin.site_defaults.$key"));

                    // FileUpload 等组件用 null 表示"未设置"，空字符串会被当作已有文件路径
                    return [$key => blank($value) ? null : $value];
                })
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基础信息')->schema($this->fieldsFor('basic'))->columns(2),
                Section::make('SEO 配置')->schema($this->fieldsFor('seo'))->columns(1),
                Section::make('媒体资源')->schema($this->fieldsFor('media'))->columns(2),
                Section::make('品牌')->schema($this->fieldsFor('brand')),
            ])
            ->statePath('data');
    }

    protected function fieldsFor(string $group): array
    {
        $components = [];

        foreach ($this->fields as $key => [$label, $g]) {
            if ($g !== $group) {
                continue;
            }

            $components[] = match (true) {
                $key === 'favicon_path'     => $this->fileUpload($key, $label)
                    ->helperText('支持 .ico / .png / .svg，为空则使用浏览器默认图标')
                    ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml']),
                $key === 'logo_path'        => $this->fileUpload($key, $label)
                    ->helperText('上传后在导航栏展示，未上传则不显示')
                    ->image(),
                $key === 'brand_color'      => ColorPicker::make($key)->label($label),
                $key === 'meta_description' => Textarea::make($key)->label($label)->rows(3),
                default                     => TextInput::make($key)->label($label),
            };
        }

        return $components;
    }

    protected function fileUpload(string $key, string $label): FileUpload
    {
        return FileUpload::make($key)
            ->label($label)
            ->disk('public')
            ->directory('site')
            ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                $info = $component->getUploadedFile($file, $storedFileNames);

                if ($info) {
                    $info['url'] = asset('storage/'.$file);
                }

                return $info;
            })
            ->nullable();
    }

    public function save(): void
    {
        $config = app(SiteConfigService::class);
        $state = $this->form->getState();

        foreach ($this->fields as $key => [$label, $group]) {
            $config->set($key, $state[$key] ?? null, 'string', $group);
        }

        Notification::make()->title('站点设置已保存')->success()->send();
    }
}
