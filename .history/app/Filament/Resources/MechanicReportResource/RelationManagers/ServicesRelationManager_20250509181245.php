<?php

namespace App\Filament\Resources\MechanicReportResource\RelationManagers;

use App\Models\Service;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'mechanic';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Riwayat Servis';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->mechanic()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service_type')
            ->columns([
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Jenis Servis')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Nama Pelanggan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('license_plate')
                    ->label('Nomor Plat')
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nomor Nota')
                    ->searchable(),

                Tables\Columns\TextColumn::make('labor_cost')
                    ->label('Biaya Jasa')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'in_progress' => 'Dalam Pengerjaan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Servis')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'in_progress' => 'Dalam Pengerjaan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->placeholder('Semua Status')
                    ->multiple(),
            ])
            ->headerActions([
                // No header actions needed
            ])
            ->actions([
                // View action to see service details
                Tables\Actions\ViewAction::make()
                    ->url(fn(Service $record): string => route('filament.admin.resources.services.edit', ['record' => $record])),
            ])
            ->bulkActions([
                // No bulk actions needed
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $mechanicId = $this->getOwnerRecord()->mechanic_id;
                $weekStart = $this->getOwnerRecord()->week_start;
                $weekEnd = $this->getOwnerRecord()->week_end;

                return $query->join('mechanic_service', function ($join) use ($mechanicId, $weekStart, $weekEnd) {
                    $join->on('mechanic_service.service_id', '=', 'services.id')
                        ->where('mechanic_service.mechanic_id', '=', $mechanicId)
                        ->where('mechanic_service.week_start', '=', $weekStart)
                        ->where('mechanic_service.week_end', '=', $weekEnd);
                })
                    ->select('services.*', 'mechanic_service.invoice_number', 'mechanic_service.labor_cost');
            });
    }
}
