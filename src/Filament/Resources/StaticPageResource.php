<?php

namespace Nbutl\NovaAdmin\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Nbutl\NovaAdmin\Filament\Resources\StaticPageResource\Pages;
use Nbutl\NovaAdmin\Models\StaticPage;

class StaticPageResource extends Resource
{
    protected static ?string $model = StaticPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = '静态页面';

    protected static ?string $modelLabel = '静态页面';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('页面标题')
                ->required()
                ->maxLength(200),
            TextInput::make('slug')
                ->label('页面标识')
                ->required()
                ->maxLength(200)
                ->rule('alpha_dash')
                ->unique(ignoreRecord: true)
                ->helperText('页面 URL 标识，仅含字母、数字、连字符，如 privacy-policy')
                // 标识决定前台链接，编辑已有页面时禁改，避免链接失效
                ->disabledOn('edit'),
            // 附件磁盘在 StaticPage 模型的 setUpRichContent() 配置（Filament 5 富文本附件机制）
            RichEditor::make('content')
                ->label('页面内容')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('title')->label('页面标题')->searchable()->sortable(),
                TextColumn::make('slug')->label('页面标识')->badge()->searchable(),
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
            'index'  => Pages\ListStaticPages::route('/'),
            'create' => Pages\CreateStaticPage::route('/create'),
            'edit'   => Pages\EditStaticPage::route('/{record}/edit'),
        ];
    }
}
