<?php

namespace App\Filament\Resources\Districts\Tables;

use App\Models\District;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DistrictsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('İlçe')->searchable()->sortable()->weight('semibold')
                    ->description(fn (District $record) => '/'.$record->province->slug.'/'.$record->slug),
                TextColumn::make('province.name')->label('İl')->sortable()->badge()->color('gray'),
                TextColumn::make('pickup_points')->label('Biniş noktası')
                    ->getStateUsing(fn (District $record) => count($record->pickup_points ?? []))
                    ->badge(),
                ToggleColumn::make('is_active')->label('Yayında'),
                TextColumn::make('sort_order')->label('Sıra')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('province_id')->label('İl')->relationship('province', 'name')->preload(),
                TernaryFilter::make('is_active')->label('Yayın durumu'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Sitede gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (District $record) => $record->url)
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('province_id');
    }
}
