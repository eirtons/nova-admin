<?php

namespace Nbutl\NovaSiteCore\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Nbutl\NovaSiteCore\Services\LogFileService;

class SystemLogsPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $title = '系统日志';

    protected static ?string $navigationLabel = '系统日志';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected string $view = 'nova-site-core::filament.pages.system-logs';

    public static function getNavigationGroup(): ?string
    {
        return config('nova-site-core.navigation.group');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => app(LogFileService::class)->files()->keyBy(fn (array $f): string => md5($f['path'])))
            ->columns([
                TextColumn::make('name')
                    ->label('文件')
                    ->state(fn (array $record): string => $record['name']),
                TextColumn::make('size')
                    ->label('大小')
                    ->state(fn (array $record): string => Number::fileSize($record['size'])),
                TextColumn::make('mtime')
                    ->label('最后更新')
                    ->state(fn (array $record): string => date('Y-m-d H:i:s', $record['mtime'])),
            ])
            ->emptyStateHeading('暂无日志文件')
            ->recordActions([
                Action::make('view')
                    ->label('查看')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (array $record): string => $record['name'])
                    ->modalWidth('5xl')
                    ->modalContent(fn (array $record) => view(
                        'nova-site-core::filament.pages.log-tail',
                        app(LogFileService::class)->tail($record['path'])
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭'),
                Action::make('download')
                    ->label('下载')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function (array $record) {
                        return response()->streamDownload(function () use ($record): void {
                            $fp = fopen($record['path'], 'rb');
                            fpassthru($fp);
                            fclose($fp);
                        }, $record['name']);
                    }),
                Action::make('delete')
                    ->label('删除')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('删除日志文件')
                    ->modalDescription(fn (array $record): string => "确认删除 {$record['name']}（".Number::fileSize($record['size'])."）？此操作不可恢复。")
                    ->modalSubmitActionLabel('确认删除')
                    ->action(function (array $record): void {
                        app(LogFileService::class)->delete($record['path']);

                        Notification::make()->title('日志已删除')->success()->send();

                        $this->resetTable();
                    }),
            ]);
    }
}
