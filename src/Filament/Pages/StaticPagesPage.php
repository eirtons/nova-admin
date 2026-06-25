<?php

namespace Inova\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Inova\NovaAdmin\Models\StaticPage;

class StaticPagesPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = '静态页面';

    protected static ?string $navigationLabel = '静态页面';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'nova-admin::filament.pages.static-pages';

    // 给编辑器足够宽度但不贴边铺满（7xl≈80rem）；不够宽改 Full，太宽降到 6xl/5xl
    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return config('nova-admin.navigation.groups.content');
    }

    /** @return array<string, array{0: string, 1: string}> slug => [英文标签, 中文备注] */
    protected function presets(): array
    {
        return config('nova-admin.static_pages.presets', []);
    }

    public function mount(): void
    {
        $pages = StaticPage::all()->keyBy('slug');

        $this->form->fill(
            collect($this->presets())
                ->mapWithKeys(fn (array $p, string $slug) => [$slug => $pages[$slug]?->content ?? ''])
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        $tabs = collect($this->presets())
            // 标签只显示英文（紧凑不溢出），中文名以说明文字置于编辑器上方
            ->map(fn (array $p, string $slug) => Tabs\Tab::make($p[0])->schema([
                Text::make($p[1])->color('gray')->size(TextSize::Small),
                // statePath 为页面 data 数组，不挂在 StaticPage 模型上，须显式指定
                // public 磁盘，与模型 setUpRichContent() 一致，保证前台 /storage 取得到附件
                RichEditor::make($slug)
                    ->hiddenLabel()
                    ->fileAttachmentsDisk('public')
                    ->extraInputAttributes(['style' => 'min-height: 24rem'])
                    ->columnSpanFull(),
            ]))
            ->values()
            ->all();

        return $schema
            ->components([Tabs::make('static_pages')->tabs($tabs)])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('保存')->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($this->presets() as $slug => $p) {
            StaticPage::updateOrCreate(
                ['slug' => $slug],
                ['content' => $data[$slug] ?? '', 'title' => $p[0]],
            );
        }

        Notification::make()->title('静态页面已保存')->success()->send();
    }
}
