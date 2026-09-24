<?php

namespace App\Filament\Resources\TourDepartures\Tables;

use App\Enums\DepartureStatus;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\TourDeparture;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tek ekrana sığan sade liste: tur, tarih, kişi, araç, boş koltuk, durum.
 */
class TourDeparturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->size(44)
                    ->defaultImageUrl(asset('images/placeholder.svg'))
                    ->extraImgAttributes(['loading' => 'lazy']),
                TextColumn::make('title')->label('Tur')->searchable()->sortable()->weight('semibold')
                    ->description(fn (TourDeparture $record) => $record->starts_at?->translatedFormat('j F Y l · H:i')),
                TextColumn::make('starts_at')->label('Tarih')->date('d.m.Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seats_taken')->label('Kişi')
                    ->getStateUsing(fn (TourDeparture $record) => $record->seats_taken)
                    ->badge()
                    ->color(fn (TourDeparture $record) => match (true) {
                        $record->sale_limit !== null && $record->seats_taken > $record->sale_limit => 'danger',
                        $record->is_full => 'warning',
                        default => 'info',
                    })
                    ->tooltip(fn (TourDeparture $record) => $record->sale_limit !== null && $record->seats_taken > $record->sale_limit
                        ? 'Kayıtlı yolcu kapasiteyi aşıyor: araç ekleyin.'
                        : null),
                TextColumn::make('vehicles_count')->label('Araç')->counts('vehicles')
                    ->formatStateUsing(fn (int $state, TourDeparture $record) => $state === 0 ? 'Yok' : $state.' · '.$record->capacity.' koltuk')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'danger' : 'gray'),
                TextColumn::make('empty_seats')->label('Boş koltuk')
                    ->getStateUsing(fn (TourDeparture $record) => $record->empty_seats)
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state === 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('price')->label('Fiyat')->money('TRY', locale: 'tr')->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (DepartureStatus $state) => $state->label())
                    ->color(fn (DepartureStatus $state) => $state->color()),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label('Yalnız yaklaşan turlar')
                    ->query(fn (Builder $query) => $query->upcoming())
                    ->default(),
                Filter::make('dates')
                    ->label('Tarih aralığı')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('starts_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('starts_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Başlangıç: '.\Illuminate\Support\Carbon::parse($data['from'])->format('d.m.Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Bitiş: '.\Illuminate\Support\Carbon::parse($data['until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('status')->label('Durum')->options(DepartureStatus::options()),
            ])
            ->filtersFormColumns(1)
            ->recordActions([
                Action::make('allocation')
                    ->label('Dağılım')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->url(fn (TourDeparture $record) => TourDepartureResource::getUrl('allocation', ['record' => $record])),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->recordUrl(fn (TourDeparture $record) => TourDepartureResource::getUrl('view', ['record' => $record]))
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering) => $action
                ->label($isReordering ? 'Sıralamayı bitir' : 'Site sırasını değiştir')
                ->icon('heroicon-o-arrows-up-down'))
            ->defaultSort('starts_at')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
