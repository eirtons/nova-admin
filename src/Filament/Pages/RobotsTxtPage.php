<?php

namespace Nbutl\NovaSiteCore\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class RobotsTxtPage extends AdsTxtPage
{
    protected static ?string $title = 'Robots.txt';

    protected static ?string $navigationLabel = 'Robots.txt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected string $configType = 'robots_txt';

    protected string $fieldLabel = 'Robots.txt 内容';
}
