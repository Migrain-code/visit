<?php

namespace App\Filament\Pages;

use App\Models\TourCommission;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Kazançlarım: personelin kendi komisyonları. Herkes yalnız kendininkini görür.
 */
class MyEarnings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Personel';

    protected static ?string $navigationLabel = 'Kazançlarım';

    protected static ?string $title = 'Kazançlarım';

    protected static ?string $slug = 'kazanclarim';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.my-earnings';

    public function getSubheading(): ?string
    {
        return 'Görev aldığınız turlarda size yazılan komisyonlar.';
    }

    private function query(): Builder
    {
        return TourCommission::query()
            ->where('user_id', auth()->id())
            ->with('departure');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->query())
            ->columns([
                TextColumn::make('departure.starts_at')->label('Tarih')->date('d.m.Y')->sortable(),
                TextColumn::make('departure.title')->label('Tur')->weight('semibold')->searchable(),
                TextColumn::make('amount')->label('Kazanç')
                    ->formatStateUsing(fn ($state) => money_label($state))
                    ->summarize(Sum::make()->label('Toplam')->formatStateUsing(fn ($state) => money_label($state))),
                TextColumn::make('note')->label('Not')->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label('Yazıldı')->dateTime('d.m.Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50])
            ->emptyStateHeading('Henüz komisyon yazılmadı')
            ->emptyStateDescription('Bir turda görev alıp komisyon yazıldığında burada görünür.');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $all = $this->query()->get();
        $month = $all->filter(fn (TourCommission $c) => $c->departure?->starts_at?->isSameMonth(now()));

        return [
            'cards' => [
                ['Toplam kazanç', money_label($all->sum('amount')), $all->count().' tur', 'success'],
                ['Bu ay', money_label($month->sum('amount')), $month->count().' tur', 'info'],
                ['Son tur', ($last = $all->sortByDesc('id')->first()) ? money_label($last->amount) : '—', $last?->departure?->label ?? 'henüz yok', 'gray'],
            ],
        ];
    }
}
