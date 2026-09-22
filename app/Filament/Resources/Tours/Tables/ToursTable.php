<?php

namespace App\Filament\Resources\Tours\Tables;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\Tour;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ToursTable
{
    public static function configure(Table $table): Table
    {
        $canManage = fn (): bool => auth()->user()?->managesCatalog() ?? false;

        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->size(48),
                TextColumn::make('title')->label('Tur')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Tour $record) => '/'.$record->slug),
                TextColumn::make('category.name')->label('Kategori')->badge()->color('gray')->placeholder('-')->toggleable(),
                TextColumn::make('duration_label')->label('Süre'),
                TextColumn::make('price')->label('Fiyat')->sortable()
                    ->formatStateUsing(fn (Tour $record) => $record->price_label)
                    ->placeholder('-'),
                TextColumn::make('departures_count')->label('Sefer')->counts('departures')->sortable(),
                ToggleColumn::make('is_featured')->label('Ana sayfa')->disabled(fn () => ! $canManage()),
                ToggleColumn::make('is_active')->label('Yayında')->disabled(fn () => ! $canManage()),
            ])
            ->filters([
                SelectFilter::make('tour_category_id')->label('Kategori')->relationship('category', 'name')->preload(),
                TernaryFilter::make('is_active')->label('Yayın durumu'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Sitede gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Tour $record) => $record->url)
                    ->openUrlInNewTab(),
                Action::make('departures')
                    ->label('Seferler')
                    ->icon('heroicon-o-calendar-days')
                    ->url(fn (Tour $record) => TourDepartureResource::getUrl('index', ['tableFilters' => ['tour_id' => ['value' => $record->getKey()]]]))
                    ->visible(fn () => auth()->user()?->registersGroups() ?? false),
                EditAction::make(),
                // Seferi olan tur silinemez (yolcu kayıtları ona bağlıdır); yayından kaldırılabilir.
                DeleteAction::make()->hidden(fn (Tour $record) => $record->departures_count > 0),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(fn ($records) => $records->reject(fn (Tour $t) => $t->departures()->exists())->each->delete()),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
