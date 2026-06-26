<?php

namespace Inova\NovaAdmin\Filament\Resources\AdSpotResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Inova\NovaAdmin\Filament\Resources\AdSpotResource;
use Inova\NovaAdmin\Models\AdSpot;

class ListAdSpots extends ListRecords
{
    protected static string $resource = AdSpotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('seed')
                    ->label('填充测试广告')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('将清空现有广告位并按配置重新填充测试代码（全部启用）。')
                    ->action(function () {
                        $count = AdSpot::seedTestSpots();
                        Notification::make()->title("已填充并启用 {$count} 条测试广告")->success()->send();
                    }),
                Action::make('clear')
                    ->label('清空广告位')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('删除全部广告位记录，不可恢复。')
                    ->action(function () {
                        $count = AdSpot::query()->delete();
                        Notification::make()->title("已删除 {$count} 条广告位")->success()->send();
                    }),
            ])
                ->label('测试工具')
                ->icon('heroicon-o-wrench-screwdriver')
                ->button()
                ->color('gray'),
            CreateAction::make()->label('新增广告'),
        ];
    }
}
