<?php

namespace App\Filament\Resources;

use App\Models\Alert;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\AlertResource\Pages;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Semua Alert';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 3;

    // DISABLE SEMUA AKSI CREATE / EDIT / DELETE SECARA GLOBAL
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agent.app_name')
                    ->label('Aplikasi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('agent.hostname')
                    ->label('Server')
                    ->formatStateUsing(fn ($record) => $record->agent?->hostname . ' (' . $record->agent?->ip_address . ')')
                    ->color('gray'),

                BadgeColumn::make('type')
                    ->label('Tipe Alert')
                    ->colors([
                        'danger'  => 'yara_webshell_match',
                        'warning' => 'file_modified',
                        'primary' => 'file_created',
                    ])
                    ->icons([
                        'danger'  => 'heroicon-o-shield-exclamation',
                        'warning' => 'heroicon-o-pencil-square',
                        'primary' => 'heroicon-o-document-plus',
                    ]),

                TextColumn::make('file_path')
                    ->label('File')
                    ->limit(60)
                    ->tooltip(fn ($state): ?string => strlen($state ?? '') > 60 ? $state : null)
                    ->searchable(),

                TextColumn::make('matched_rules')
                    ->label('YARA Rules')
                    ->badge()
                    ->separator(', ')
                    ->color('danger')
                    ->wrap(),

                TextColumn::make('hash')
                    ->label('Hash')
                    ->limit(20)
                    ->tooltip(fn ($state) => $state ?? '-')
                    ->copyable()
                    ->copyMessage('Hash disalin!'),

                TextColumn::make('detected_at')
                    ->label('Waktu Deteksi')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filter Tipe')
                    ->options([
                        ''                     => 'Semua',
                        'yara_webshell_match' => 'Hanya Webshell Kritis',
                        'file_modified'       => 'File Diubah',
                        'file_created'        => 'File Baru',
                    ])
                    ->default('yara_webshell_match'), // default hanya webshell
            ])
            // KOSONGKAN SEMUA ACTIONS
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('detected_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlerts::route('/'),
        ];
    }
}