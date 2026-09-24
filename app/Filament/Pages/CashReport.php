<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\TourDeparture;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Kasa: tur başına gelir, gider ve kasaya kalan.
 *
 *   Gelir  = kayıtlı yolcu × kişi başı fiyat + ekstra gelirler
 *   Gider  = araç ücretleri + komisyonlar + ekstra giderler
 *   Kalan  = Gelir − Gider
 *
 * Bütün toplamlar alt sorguyla tek seferde gelir (TourDeparture::withFinanceStats).
 */
class CashReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Kasa';

    protected static ?string $title = 'Kasa';

    protected static ?string $slug = 'kasa';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.cash-report';

    public static function canAccess(): bool
    {
        return auth()->user()?->viewsReports() ?? false;
    }

    /**
     * "Kasaya kalan" SQL ifadesi: filtre ve sıralama için, alt sorgu takma adlarına
     * bağlı kalmadan (SQLite ORDER BY/WHERE içinde takma ad çözmez).
     */
    private const NET_SQL = '(coalesce((select coalesce(sum(passenger_count), 0) from tour_groups where tour_groups.tour_departure_id = tour_departures.id and status != \'cancelled\'), 0) * coalesce(price, 0)'
        .' + coalesce((select coalesce(sum(amount), 0) from tour_ledger_entries where tour_ledger_entries.tour_departure_id = tour_departures.id and type = \'income\'), 0)'
        .' - coalesce((select coalesce(sum(cost), 0) from departure_vehicles where departure_vehicles.tour_departure_id = tour_departures.id), 0)'
        .' - coalesce((select coalesce(sum(amount), 0) from tour_commissions where tour_commissions.tour_departure_id = tour_departures.id), 0)'
        .' - coalesce((select coalesce(sum(amount), 0) from tour_ledger_entries where tour_ledger_entries.tour_departure_id = tour_departures.id and type = \'expense\'), 0))';

    public function getSubheading(): ?string
    {
        return 'Gelir = yolcu × fiyat + ekstra gelir · Gider = araç + komisyon + ekstra gider. Ekstra hareketler tur sayfasındaki "Kasa hareketi" ile eklenir.';
    }

    public function table(Table $table): Table
    {
        $money = fn ($state) => money_label($state);

        return $table
            ->query(fn () => TourDeparture::query()->withFinanceStats())
            ->columns([
                TextColumn::make('starts_at')->label('Tarih')->date('d.m.Y')->sortable(),
                TextColumn::make('title')->label('Tur')->weight('semibold')->searchable()
                    ->url(fn (TourDeparture $record) => TourDepartureResource::getUrl('view', ['record' => $record])),
                TextColumn::make('seats_taken_sum')->label('Kişi')->badge()->color('info')->sortable(),
                TextColumn::make('passenger_revenue')->label('Yolcu geliri')
                    ->getStateUsing(fn (TourDeparture $record) => $record->passenger_revenue)
                    ->formatStateUsing($money),
                TextColumn::make('vehicle_cost_sum')->label('Araç')->formatStateUsing($money)->sortable(),
                TextColumn::make('commission_sum')->label('Komisyon')->formatStateUsing($money)->sortable(),
                TextColumn::make('extra_income_sum')->label('Ek gelir')->formatStateUsing($money)->toggleable(),
                TextColumn::make('extra_expense_sum')->label('Ek gider')->formatStateUsing($money)->toggleable(),
                TextColumn::make('net')->label('Kasaya kalan')
                    ->getStateUsing(fn (TourDeparture $record) => $record->net)
                    ->formatStateUsing($money)
                    ->badge()
                    ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(self::NET_SQL.' '.($direction === 'desc' ? 'desc' : 'asc'))),
            ])
            ->filters([
                Filter::make('dates')
                    ->label('Tarih aralığı')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('starts_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('starts_at', '<=', $date))),
                SelectFilter::make('id')
                    ->label('Tur')
                    ->options(fn () => TourDeparture::query()->orderByDesc('starts_at')->limit(200)->get()
                        ->mapWithKeys(fn (TourDeparture $d) => [$d->id => $d->label]))
                    ->searchable(),
                SelectFilter::make('staff')
                    ->label('Personel (komisyon alan)')
                    ->options(fn () => User::query()->ordered()->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q
                        ->whereHas('commissions', fn (Builder $c) => $c->where('user_id', $id)))),
                Filter::make('net')
                    ->label('Kasaya kalan')
                    ->schema([
                        TextInput::make('min')->label('En az (₺)')->numeric(),
                        TextInput::make('max')->label('En çok (₺)')->numeric(),
                    ])
                    ->columns(2)
                    // "? + 0": bağlanan değer metin olarak gider; SQLite sayı ile metni karşılaştırmaz, toplama sayıya çevirir.
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $q) => $q->whereRaw(self::NET_SQL.' >= (? + 0)', [(float) $data['min']]))
                        ->when(filled($data['max'] ?? null), fn (Builder $q) => $q->whereRaw(self::NET_SQL.' <= (? + 0)', [(float) $data['max']]))),
                Filter::make('past')
                    ->label('Yalnız yapılmış turlar')
                    ->query(fn (Builder $query) => $query->where('starts_at', '<', now())),
            ])
            ->filtersFormColumns(1)
            ->recordActions([
                Action::make('open')->label('Aç')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (TourDeparture $record) => TourDepartureResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('starts_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Filtreye uyan tur yok');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = $this->getFilteredTableQuery()->get();

        $income = (float) $rows->sum(fn (TourDeparture $d) => $d->total_income);
        $expense = (float) $rows->sum(fn (TourDeparture $d) => $d->total_expense);

        return [
            'cards' => [
                ['Toplam gelir', money_label($income), $rows->sum('seats_taken_sum').' yolcu', 'success'],
                ['Toplam gider', money_label($expense), 'araç '.money_label($rows->sum('vehicle_cost_sum')).' · komisyon '.money_label($rows->sum('commission_sum')), 'danger'],
                ['Kasaya kalan', money_label($income - $expense), $rows->count().' tur', $income - $expense >= 0 ? 'success' : 'danger'],
            ],
        ];
    }
}
