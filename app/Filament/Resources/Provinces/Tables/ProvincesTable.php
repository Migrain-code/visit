<?php

namespace App\Filament\Resources\Provinces\Tables;

use App\Models\Province;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProvincesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('İl')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Province $record) => '/'.$record->slug),
                TextColumn::make('districts_count')->label('İlçe sayısı')->counts('districts')->badge(),
                ToggleColumn::make('is_active')->label('Yayında'),
                TextColumn::make('sort_order')->label('Sıra')->sortable(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Sitede gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Province $record) => $record->url)
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
            ->defaultSort('sort_order');
    }
}
