<?php

namespace App\Filament\Resources\AgentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class AlertsRelationManager extends RelationManager
{
    protected static string $relationship = 'alerts';

    protected static ?string $title = 'Log Deteksi Backdoor';

    protected static ?string $icon = 'heroicon-o-exclamation-triangle';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_path')
            ->columns([
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
                    ->searchable()
                    ->sortable(),

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
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe Alert')
                    ->options([
                        ''                     => 'Semua Alert',
                        'yara_webshell_match' => 'Hanya Webshell Kritis (YARA)',
                        'file_modified'       => 'File Diubah',
                        'file_created'        => 'File Baru',
                    ])
                    ->default('yara_webshell_match') // DEFAULT: HANYA WEBSHELL
                    ->placeholder('Semua Alert'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('detected_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100, 'all']);
    }
}