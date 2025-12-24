<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HostsOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Hosts';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.hosts-overview';

    protected static ?string $title = 'Hosts Overview';
}