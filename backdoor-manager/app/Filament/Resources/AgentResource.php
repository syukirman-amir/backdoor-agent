<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Filament\Resources\AgentResource\RelationManagers\AlertsRelationManager;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'Agents';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 1;

    // Disable create/edit/delete globally
    protected static bool $shouldRegisterNavigation = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Aplikasi')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('app_name')->disabled()->dehydrated(false),
                            Forms\Components\TextInput::make('app_id')->disabled()->dehydrated(false),
                            Forms\Components\TextInput::make('hostname')->disabled()->dehydrated(false),
                            Forms\Components\TextInput::make('ip_address')->disabled()->dehydrated(false),
                        ]),
                    ]),

                Forms\Components\Section::make('Status Agent')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Placeholder::make('status')
                                ->label('Status')
                                ->content(fn ($record) => match ($record?->status) {
                                    'approved' => '✅ Approved',
                                    'pending'  => '⏳ Pending Approval',
                                    'revoked'  => '🚫 Revoked',
                                    default    => '-',
                                }),

                            Forms\Components\Placeholder::make('registered_at')
                                ->label('Terdaftar')
                                ->content(fn ($record) => $record?->registered_at?->format('d M Y H:i') ?? '-'),

                            Forms\Components\Placeholder::make('approved_at')
                                ->label('Disetujui')
                                ->content(fn ($record) => $record?->approved_at?->format('d M Y H:i') ?? '-'),

                            Forms\Components\Placeholder::make('last_seen_at')
                                ->label('Online Terakhir')
                                ->content(fn ($record) => $record?->last_seen_at?->diffForHumans() ?? 'Belum pernah'),

                            Forms\Components\Placeholder::make('key_rotated_at')
                                ->label('Key Diganti')
                                ->content(fn ($record) => $record?->key_rotated_at?->format('d M Y H:i') ?? '-'),
                        ]),
                    ]),

                Forms\Components\Section::make('Technology Stack')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Placeholder::make('tech_stack.os')->label('OS')
                                ->content(fn ($record) => $record->tech_stack['os'] ?? '-'),

                            Forms\Components\Placeholder::make('tech_stack.web_server')->label('Web Server')
                                ->content(fn ($record) => $record->tech_stack['web_server'] ?? '-'),

                            Forms\Components\Placeholder::make('tech_stack.database')->label('Database')
                                ->content(fn ($record) => $record->tech_stack['database'] ?? '-'),

                            Forms\Components\TagsInput::make('tech_stack.language')->label('Languages')->disabled()
                                ->default(fn ($record) => $record->tech_stack['language'] ?? []),

                            Forms\Components\TagsInput::make('tech_stack.framework')->label('Frameworks')->disabled()
                                ->default(fn ($record) => $record->tech_stack['framework'] ?? []),

                            Forms\Components\Placeholder::make('tech_stack.container')->label('Environment')
                                ->content(fn ($record) => $record->tech_stack['container'] ?? '-'),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('app_name')
                        ->weight('bold')
                        ->size('lg')
                        ->searchable(),

                    Tables\Columns\TextColumn::make('hostname')
                        ->formatStateUsing(fn ($record) => $record->hostname . ' (' . $record->ip_address . ')')
                        ->color('gray'),

                    Tables\Columns\TextColumn::make('tech_stack.language')->badge()->separator(', '),
                    Tables\Columns\TextColumn::make('tech_stack.framework')->badge()->separator(', '),

                    Tables\Columns\BadgeColumn::make('status')
                        ->colors([
                            'success' => 'approved',
                            'info' => 'pending',
                            'danger'  => 'revoked',
                        ]),

                    Tables\Columns\TextColumn::make('last_seen_at')
                        ->since()
                        ->color(fn ($state) => $state && now()->diffInMinutes($state) > 30 ? 'danger' : 'gray'),
                ]),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([10, 25, 50, 'all'])
            ->defaultSort('app_name');
    }

    public static function getRelations(): array
    {
        return [
            AlertsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'view'  => Pages\ViewAgent::route('/{record}'),
        ];
    }
}