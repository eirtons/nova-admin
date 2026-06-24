<?php

namespace Nova\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Nova\NovaAdmin\Services\LogFileService;

class SystemLogsPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $title = '系统日志';

    protected static ?string $navigationLabel = '系统日志';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected string $view = 'nova-admin::filament.pages.system-logs';

    /** 日志条目偏长，本页放开为全宽（其他页面保持默认宽度） */
    protected Width|string|null $maxContentWidth = Width::Full;

    public ?array $data = [];

    /** @var array<int, array{file: string, time: string, level: string, message: string, detail: string}> */
    public array $entries = [];

    public int $entriesLimit = 100;

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.system');
    }

    protected function logs(): LogFileService
    {
        return app(LogFileService::class);
    }

    public function mount(): void
    {
        $this->entriesLimit = max(1, (int) config('nova-admin.logs.search_limit', 100));

        // 默认只看最新的一个文件，避免打开页面就全量查询；下拉可手动切「全部文件」
        $latest = $this->logs()->files()->first();

        $this->form->fill([
            'file'    => $latest !== null ? md5($latest['path']) : null,
            'keyword' => '',
            'level'   => null,
        ]);
        $this->loadEntries();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('file')
                    ->label('日志文件')
                    ->options(fn (): array => $this->logs()->files()
                        ->mapWithKeys(fn (array $f): array => [md5($f['path']) => $f['name']])
                        ->all())
                    ->placeholder('全部文件'),
                TextInput::make('keyword')
                    ->label('关键字')
                    ->placeholder('匹配条目全文（含堆栈），如异常类名、请求路径'),
                Select::make('level')
                    ->label('级别')
                    ->options([
                        'ERROR'   => 'ERROR',
                        'WARNING' => 'WARNING',
                        'INFO'    => 'INFO',
                        'DEBUG'   => 'DEBUG',
                    ])
                    ->placeholder('全部'),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function loadEntries(): void
    {
        $this->entries = $this->logs()->entries(
            path: $this->selectedPath(),
            keyword: trim((string) ($this->data['keyword'] ?? '')),
            level: $this->data['level'] ?? null,
        );
    }

    /** 文件下拉用 md5 作 value，避免把服务器绝对路径暴露到前端 */
    protected function selectedPath(): ?string
    {
        $key = $this->data['file'] ?? null;
        if (blank($key)) {
            return null;
        }

        return $this->logs()->files()
            ->first(fn (array $f): bool => md5($f['path']) === $key)['path'] ?? null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->logs()->files()->keyBy(fn (array $f): string => md5($f['path'])))
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
                    ->action(function (array $record): void {
                        $this->data['file'] = md5($record['path']);
                        $this->loadEntries();
                    }),
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
                        $this->logs()->delete($record['path']);

                        Notification::make()->title('日志已删除')->success()->send();

                        if (($this->data['file'] ?? null) === md5($record['path'])) {
                            $this->data['file'] = null;
                        }
                        $this->loadEntries();
                        $this->resetTable();
                    }),
            ]);
    }
}
