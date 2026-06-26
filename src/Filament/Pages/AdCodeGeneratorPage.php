<?php

namespace Inova\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Inova\NovaAdmin\Services\AdCodeGeneratorService;

class AdCodeGeneratorPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = '插屏与锚定';

    protected static ?string $navigationLabel = '插屏与锚定';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected string $view = 'nova-admin::filament.pages.ad-code-generator';

    public ?array $data = [];

    public ?string $generated = null;

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.content');
    }

    public function mount(): void
    {
        $this->form->fill(['adType' => 'interstitial', 'anchorPosition' => 'bottom']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('adType')
                    ->label('广告类型')
                    ->options([
                        'both'         => '插屏 + 锚定',
                        'interstitial' => '仅插屏广告',
                    ])
                    ->default('interstitial')
                    ->required()
                    ->live(),
                TextInput::make('interstitialAdUnitId')
                    ->label('插屏广告标识 ID')
                    ->placeholder('/23043164651/xxx_interstitial')
                    ->required()
                    ->regex('/^[a-zA-Z0-9\/_.-]+$/')
                    ->maxLength(255),
                Fieldset::make('锚定广告配置')
                    ->visible(fn (Get $get): bool => $get('adType') === 'both')
                    ->columns(1)
                    ->schema([
                        TextInput::make('anchorAdUnitId')
                            ->label('锚定广告标识 ID')
                            ->placeholder('/23043164651/xxx_anchor')
                            ->required(fn (Get $get): bool => $get('adType') === 'both')
                            ->regex('/^[a-zA-Z0-9\/_.-]+$/')
                            ->maxLength(255),
                        TextInput::make('anchorSizes')
                            ->label('锚定广告尺寸（JSON 数组）')
                            ->placeholder('[[990,90],[300,100],"fluid"]')
                            ->helperText('示例：[[990,90],[300,100],"fluid"]')
                            ->required(fn (Get $get): bool => $get('adType') === 'both')
                            ->rule('json'),
                        TextInput::make('anchorDivId')
                            ->label('锚定容器 ID')
                            ->placeholder('div-gpt-ad-1749281211859-0')
                            ->required(fn (Get $get): bool => $get('adType') === 'both')
                            ->regex('/^[a-zA-Z0-9_-]+$/')
                            ->maxLength(255),
                        Radio::make('anchorPosition')
                            ->label('锚定位置')
                            ->options(['top' => '顶部 Top', 'bottom' => '底部 Bottom'])
                            ->default('bottom')
                            ->inline()
                            ->required(fn (Get $get): bool => $get('adType') === 'both'),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $this->generated = app(AdCodeGeneratorService::class)
            ->generate($this->form->getState());

        Notification::make()->title('已生成')->success()->send();
    }
}
