<?php

namespace Inova\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\BaseFileUpload;
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
use Inova\NovaAdmin\Services\SiteConfigService;

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
    ];

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.settings');
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
                    ->helperText($this->uploadHelperText(
                        'favicon',
                        '支持 '.$this->acceptedExtensions('favicon').'，为空则使用浏览器默认图标',
                    ))
                    ->acceptedFileTypes((array) config('nova-admin.site_settings.favicon.accepted_types'))
                    ->maxSize(config('nova-admin.site_settings.favicon.max_size') ?: null),
                $key === 'logo_path'        => $this->fileUpload($key, $label)
                    ->helperText($this->uploadHelperText('logo', '上传后在导航栏展示，未上传则不显示'))
                    ->image()
                    ->maxSize(config('nova-admin.site_settings.logo.max_size') ?: null),
                $key === 'contact_email'    => TextInput::make($key)
                    ->label($label)
                    ->email()
                    ->maxLength(254),
                $key === 'meta_description' => Textarea::make($key)->label($label)->rows(3),
                default                     => TextInput::make($key)->label($label),
            };
        }

        return $components;
    }

    /** 上传限制来自 config，helperText 里把大小上限一并说明，避免用户传完才被拒。 */
    protected function uploadHelperText(string $which, string $base): string
    {
        $maxSize = (int) config("nova-admin.site_settings.$which.max_size");

        return $maxSize > 0
            ? $base.'，最大 '.$this->humanSize($maxSize)
            : $base;
    }

    protected function humanSize(int $kb): string
    {
        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : $kb.' KB';
    }

    /** MIME → 扩展名，用于 helperText 里列出可接受的类型。未收录的按子类型兜底。 */
    protected function acceptedExtensions(string $which): string
    {
        $known = [
            'image/x-icon'    => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'image/png'       => 'png',
            'image/jpeg'      => 'jpg',
            'image/svg+xml'   => 'svg',
            'image/webp'      => 'webp',
        ];

        $types = (array) config("nova-admin.site_settings.$which.accepted_types");

        return implode(' / ', array_map(
            fn (string $type) => '.'.($known[$type] ?? (explode('/', $type)[1] ?? $type)),
            $types,
        ));
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
