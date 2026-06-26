<?php

namespace Inova\NovaAdmin\Filament\Resources;

use BackedEnum;
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
use Inova\NovaAdmin\Filament\Resources\AdSpotResource\Pages;
use Inova\NovaAdmin\Models\AdSpot;

class AdSpotResource extends Resource
{
    protected static ?string $model = AdSpot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = '广告管理';

    protected static ?string $modelLabel = '广告';

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.content');
    }

    public static function getNavigationSort(): ?int
    {
        return config('nova-admin.navigation.sort');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('position')
                ->label('广告位')
                ->options(config('nova-admin.ad_positions', []))
                ->required()
                ->unique(ignoreRecord: true, table: 'ad_spots')
                ->validationMessages(['unique' => '该广告位已存在，每个位置只能配置一条'])
                // 非原生下拉：必填校验走 Filament 红色错误提示，浏览器原生气泡不明显
                ->native(false)
                ->helperText('每个广告位只能配置一条'),
            Textarea::make('head_code')
                ->label('Head 代码')
                ->view('nova-admin::filament.components.code-editor-dark')
                ->columnSpanFull()
                ->helperText('注入到页面 <head> 的代码，如 AdSense 全局脚本、验证标签'),
            Textarea::make('body_code')
                ->label('Body 代码')
                ->view('nova-admin::filament.components.code-editor-dark')
                ->columnSpanFull()
                ->helperText('展示在页面广告位处的广告单元代码'),
            Toggle::make('is_active')
                ->label('启用')
                ->default(true)
                ->helperText('关闭后前台立即停止输出该条广告'),
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
                    ->formatStateUsing(fn ($state) => config("nova-admin.ad_positions.$state", $state))
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
                    ->modalHeading(fn ($record): string => '广告代码 — ' . config("nova-admin.ad_positions.{$record->position}", $record->position))
                    ->modalWidth('3xl')
                    ->infolist(fn (Schema $infolist): Schema => $infolist->schema([
                        Section::make('Head 代码')
                            ->description('注入到 <head> 标签的代码')
                            ->schema([
                                TextEntry::make('head_code')
                                    ->label('')
                                    ->view('nova-admin::filament.components.code-block'),
                            ])
                            ->collapsible(),
                        Section::make('Body 代码')
                            ->description('展示在页面主体中的广告代码')
                            ->schema([
                                TextEntry::make('body_code')
                                    ->label('')
                                    ->view('nova-admin::filament.components.code-block'),
                            ])
                            ->collapsible(),
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
