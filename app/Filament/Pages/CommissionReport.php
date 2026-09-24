<?php

namespace App\Filament\Pages;

use App\Models\TourCommission;
use App\Models\TourDeparture;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Komisyon Raporu: toplam ne ödendi, hangi tura ve hangi personele ne kadar.
 */
class CommissionReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Komisyon Raporu';

    protected static ?string $title = 'Komisyon Raporu';

    protected static ?string $slug = 'komisyon-raporu';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.commission-report';

    public static function canAccess(): bool
    {
        return auth()->user()?->viewsReports() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Tura ve personele göre ödenen komisyonlar. Filtreler aşağıdaki toplamları da günceller.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => TourCommission::query()->with(['departure', 'user']))
            ->columns([
                TextColumn::make('departure.starts_at')->label('Tarih')->date('d.m.Y')->sortable(),
                TextColumn::make('departure.title')->label('Tur')->weight('semibold')->searchable(),
                TextColumn::make('user.name')->label('Personel')->searchable(),
                TextColumn::make('amount')->label('Tutar')
                    ->formatStateUsing(fn ($state) => money_label($state))
                    ->summarize(Sum::make()->label('Toplam')->formatStateUsing(fn ($state) => money_label($state))),
                TextColumn::make('note')->label('Not')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('dates')
                    ->label('Tur tarihi')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereHas('departure', fn (Builder $d) => $d->whereDate('starts_at', '>=', $date)))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereHas('departure', fn (Builder $d) => $d->whereDate('starts_at', '<=', $date)))),
                SelectFilter::make('tour_departure_id')
                    ->label('Tur')
                    ->options(fn () => TourDeparture::query()->orderByDesc('starts_at')->limit(200)->get()
                        ->mapWithKeys(fn (TourDeparture $d) => [$d->id => $d->label]))
                    ->searchable(),
                SelectFilter::make('user_id')
                    ->label('Personel')
                    ->options(fn () => User::query()->ordered()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Komisyon kaydı yok');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = $this->getFilteredTableQuery()->with(['user', 'departure'])->get();

        $byStaff = $rows->groupBy('user_id')
            ->map(fn ($items) => ['name' => $items->first()->user?->name ?? '-', 'total' => (float) $items->sum('amount'), 'count' => $items->count()])
            ->sortByDesc('total')
            ->values();

        $byTour = $rows->groupBy('tour_departure_id')
            ->map(fn ($items) => ['label' => $items->first()->departure?->label ?? '-', 'total' => (float) $items->sum('amount'), 'count' => $items->count()])
            ->sortByDesc('total')
            ->values();

        return [
            'total' => (float) $rows->sum('amount'),
            'tours' => $rows->pluck('tour_departure_id')->unique()->count(),
            'staff' => $rows->pluck('user_id')->unique()->count(),
            'byStaff' => $byStaff,
            'byTour' => $byTour,
        ];
    }
}
