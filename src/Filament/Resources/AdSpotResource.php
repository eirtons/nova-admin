<?php

namespace Nbutl\NovaSiteCore\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Nbutl\NovaSiteCore\Filament\Resources\AdSpotResource\Pages;

class AdSpotResource extends Resource
{
    public static function getModel(): string
    {
        return config('nova-site-core.models.ad_spot', \Nbutl\NovaSiteCore\Models\AdSpot::class);
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = '广告管理';

    protected static ?string $modelLabel = '广告';

    public static function getNavigationGroup(): ?string
    {
        return config('nova-site-core.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('nova-site-core.navigation.sort');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('position')
                ->label('广告位')
                ->options(config('nova-site-core.ad_positions', []))
                ->required()
                ->helperText('同一广告位可创建多条，启用的会按创建顺序依次输出'),
            Textarea::make('head_code')
                ->label('Head 代码')
                ->rows(4)
                ->columnSpanFull()
                ->placeholder('<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-xxxxxxxxxxxxxxxx" crossorigin="anonymous"></script>')
                ->helperText('注入到页面 <head> 的代码（如 AdSense 全局脚本、验证标签），由 <x-nova-site-core::ad-head> 输出'),
            Textarea::make('body_code')
                ->label('Body 代码')
                ->rows(6)
                ->columnSpanFull()
                ->placeholder('<ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-xxxxxxxxxxxxxxxx" data-ad-slot="0000000000"></ins>')
                ->helperText('展示在页面广告位处的代码（如广告单元），由 <x-nova-site-core::ad> 输出'),
            Toggle::make('is_active')
                ->label('启用')
                ->default(true)
                ->helperText('关闭后前台立即停止输出该条广告（缓存自动失效）'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('position')
                    ->label('广告位')
                    ->badge()
                    ->formatStateUsing(fn ($state) => config("nova-site-core.ad_positions.$state", $state))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('启用')
                    ->afterStateUpdated(function (bool $state) {
                        Notification::make()
                            ->title($state ? '已启用' : '已停用')
                            ->success()
                            ->send();
                    }),
                TextColumn::make('updated_at')->label('更新时间')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('查看代码')
                    ->modalHeading(fn ($record): string => '广告代码 — ' . config("nova-site-core.ad_positions.{$record->position}", $record->position))
                    ->modalWidth('3xl')
                    ->infolist(fn (Schema $infolist): Schema => $infolist->schema([
                        Section::make('Head 代码')
                            ->description('注入到 <head> 标签的代码')
                            ->schema([
                                TextEntry::make('head_code')
                                    ->label('')
                                    ->fontFamily('mono')
                                    ->default('（未配置）')
                                    ->formatStateUsing(fn (?string $state): string => blank($state) ? '（未配置）' : $state)
                                    ->extraAttributes(['class' => 'whitespace-pre-wrap break-all text-xs']),
                            ])
                            ->collapsible(),
                        Section::make('Body 代码')
                            ->description('展示在页面主体中的广告代码')
                            ->schema([
                                TextEntry::make('body_code')
                                    ->label('')
                                    ->fontFamily('mono')
                                    ->default('（未配置）')
                                    ->formatStateUsing(fn (?string $state): string => blank($state) ? '（未配置）' : $state)
                                    ->extraAttributes(['class' => 'whitespace-pre-wrap break-all text-xs']),
                            ])
                            ->collapsible(),
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdSpots::route('/'),
            'create' => Pages\CreateAdSpot::route('/create'),
            'edit'   => Pages\EditAdSpot::route('/{record}/edit'),
        ];
    }
}
