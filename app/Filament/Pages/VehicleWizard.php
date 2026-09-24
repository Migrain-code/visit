<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\DepartureVehicle;
use App\Models\TourDeparture;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Allocation\DepartureAllocator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Araç Liste Sihirbazı: turu seç → araçları seç → her araca plaka, şoför, ücret,
 * rehber ve not gir → kaydet → "Grupları yerleştir".
 *
 * Sayfa, tura atanmış araçları yerinde düzenler: listeden çıkarılan araç turdan
 * silinir (içindeki gruplar bekleyenlere döner), yenisi eklenir, kalanı güncellenir.
 */
class VehicleWizard extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?string $navigationLabel = 'Araç Liste Sihirbazı';

    protected static ?string $title = 'Araç Liste Sihirbazı';

    protected static ?string $slug = 'arac-sihirbazi';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.vehicle-wizard';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->managesOperations() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Turu seçin, araçları ekleyin, her araca plaka / şoför / ücret / rehber yazın. Kaydedince "Grupları yerleştir" ile yolcular araçlara dağıtılır.';
    }

    public function mount(): void
    {
        $tour = request()->integer('tour');

        $this->form->fill([
            'tour_departure_id' => $tour ?: null,
            'vehicles' => $tour ? $this->vehicleRows($tour) : [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('1. Tur')
                    ->schema([
                        Select::make('tour_departure_id')
                            ->label('Tur')
                            ->options(fn () => TourDeparture::query()->withSeatStats()
                                ->where('starts_at', '>=', now()->subDay())
                                ->whereNotIn('status', ['cancelled', 'completed'])
                                ->orderBy('starts_at')->get()
                                ->mapWithKeys(fn (TourDeparture $d) => [$d->id => $d->starts_at->format('d.m.Y').' · '.$d->title.' · '.$d->seats_taken.' yolcu']))
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, $state) => $set('vehicles', $state ? $this->vehicleRows((int) $state) : [])),
                    ])
                    ->columnSpanFull(),

                Section::make('2. Araçlar')
                    ->description('Filodan seçince ad, koltuk, plaka ve şoför dolar; bu tura özel değiştirebilirsiniz. Aynı araç tipinden birden çok ekleyebilirsiniz.')
                    ->schema([
                        Repeater::make('vehicles')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('id'),
                                Select::make('vehicle_id')
                                    ->label('Filodan seç')
                                    ->options(fn () => Vehicle::query()->active()->ordered()->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label]))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if (! $vehicle = Vehicle::find($state)) {
                                            return;
                                        }

                                        $set('name', $vehicle->name);
                                        $set('seat_count', $vehicle->seat_count);
                                        $set('plate', $vehicle->plate);
                                        $set('driver_name', $vehicle->driver_name);
                                        $set('driver_phone', $vehicle->driver_phone);
                                    })
                                    ->columnSpan(2),
                                TextInput::make('name')->label('Araç adı')->required()->maxLength(100),
                                TextInput::make('seat_count')->label('Yolcu koltuğu')->required()->numeric()->minValue(1)->maxValue(99)->inputMode('numeric'),
                                TextInput::make('plate')->label('Plaka')->maxLength(20)->placeholder('53 ABC 123'),
                                TextInput::make('driver_name')->label('Şoför(ler)')->maxLength(150)->placeholder('Ali Usta, Veli Usta'),
                                TextInput::make('driver_phone')->label('Şoför telefonu')->tel()->maxLength(30),
                                TextInput::make('cost')->label('Araç ücreti')->numeric()->minValue(0)->step('0.01')->suffix('₺')->inputMode('decimal'),
                                Select::make('guide_id')->label('Rehber')
                                    ->options(fn () => User::query()->active()->ordered()->pluck('name', 'id'))
                                    ->searchable()->native(false),
                                TextInput::make('reserved_seats')->label('Görevliye ayrılan koltuk')->numeric()->minValue(0)->default(0)->inputMode('numeric'),
                                Textarea::make('notes')->label('Not')->rows(1)->columnSpan(2),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->itemLabel(fn (array $state): ?string => trim(($state['name'] ?? '').' '.($state['plate'] ? '· '.$state['plate'] : '')) ?: 'Yeni araç')
                            ->addActionLabel('+ Araç ekle')
                            ->addActionAlignment('center')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->maxItems(30)
                            ->disabled(fn (Get $get) => blank($get('tour_departure_id')))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /** @return array<int, array<string, mixed>> */
    private function vehicleRows(int $tourId): array
    {
        return DepartureVehicle::query()
            ->where('tour_departure_id', $tourId)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (DepartureVehicle $v) => [
                'id' => $v->getKey(),
                'vehicle_id' => $v->vehicle_id,
                'name' => $v->name,
                'seat_count' => $v->seat_count,
                'reserved_seats' => $v->reserved_seats,
                'plate' => $v->plate,
                'driver_name' => $v->driver_name,
                'driver_phone' => $v->driver_phone,
                'cost' => $v->cost !== null ? (float) $v->cost : null,
                'guide_id' => $v->guide_id,
                'notes' => $v->notes,
            ])
            ->values()
            ->all();
    }

    private function selectedTour(): ?TourDeparture
    {
        $id = $this->data['tour_departure_id'] ?? null;

        return $id ? TourDeparture::query()->withSeatStats()->find($id) : null;
    }

    /** Araç listesini tura yazar: güncelle, ekle, listeden çıkanı sil. */
    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $tour = TourDeparture::query()->findOrFail($state['tour_departure_id']);
        $rows = collect($state['vehicles'] ?? []);

        DB::transaction(function () use ($tour, $rows) {
            $keep = [];

            foreach ($rows->values() as $index => $row) {
                $attributes = [
                    'vehicle_id' => $row['vehicle_id'] ?: null,
                    'name' => $row['name'],
                    'seat_count' => (int) $row['seat_count'],
                    'reserved_seats' => (int) ($row['reserved_seats'] ?? 0),
                    'plate' => $row['plate'] ?: null,
                    'driver_name' => $row['driver_name'] ?: null,
                    'driver_phone' => $row['driver_phone'] ?: null,
                    'cost' => filled($row['cost'] ?? null) ? (float) $row['cost'] : null,
                    'guide_id' => $row['guide_id'] ?: null,
                    'notes' => $row['notes'] ?: null,
                    'sort_order' => $index + 1,
                ];

                $existing = ! empty($row['id']) ? $tour->vehicles()->whereKey($row['id'])->first() : null;

                if ($existing) {
                    $existing->update($attributes);
                    $keep[] = $existing->getKey();
                } else {
                    $keep[] = $tour->vehicles()->create($attributes)->getKey();
                }
            }

            // Listeden çıkarılan araçlar turdan silinir; içindeki gruplar bekleyenlere döner (model olayı).
            $tour->vehicles()->whereKeyNot($keep)->get()->each->delete();
        });

        $this->form->fill([
            'tour_departure_id' => $tour->getKey(),
            'vehicles' => $this->vehicleRows($tour->getKey()),
        ]);

        Notification::make()
            ->title($rows->count().' araç kaydedildi')
            ->body('Toplam '.(int) $rows->sum(fn ($r) => (int) $r['seat_count'] - (int) ($r['reserved_seats'] ?? 0)).' yolcu koltuğu. Şimdi "Grupları yerleştir" ile dağıtabilirsiniz.')
            ->success()->send();
    }

    /** Kaydedilmiş araçlara grupları yerleştirir (yerleşmiş gruplara dokunmaz). */
    public function allocateAction(): Action
    {
        return Action::make('allocate')
            ->label('Grupları yerleştir')
            ->icon('heroicon-o-play')
            ->color('success')
            ->size('lg')
            ->visible(fn () => $this->selectedTour() !== null)
            ->requiresConfirmation()
            ->modalHeading('Gruplar araçlara yerleştirilsin mi?')
            ->modalDescription('Önce araç listesini kaydettiğinizden emin olun. Araçsız bekleyen gruplar boş koltuklara, bölünmeden yerleştirilir; yerleşmiş gruplara dokunulmaz.')
            ->modalSubmitActionLabel('Yerleştir')
            ->action(function () {
                abort_unless(static::canAccess(), 403);

                $tour = $this->selectedTour();

                if (! $tour) {
                    return;
                }

                if ($tour->vehicles()->doesntExist()) {
                    Notification::make()->title('Önce araç ekleyip kaydedin')->warning()->send();

                    return;
                }

                $report = app(DepartureAllocator::class)->allocate($tour, keepExisting: true);

                $notification = Notification::make()
                    ->title($report->headline())
                    ->body(implode("\n", $report->lines()))
                    ->actions([
                        Action::make('board')->label('Araç dağılımını aç')
                            ->url(TourDepartureResource::getUrl('allocation', ['record' => $tour])),
                    ]);

                $report->allPlaced() && $report->overloaded === []
                    ? $notification->success()
                    : $notification->warning()->persistent();

                $notification->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('board')
                ->label('Araç dağılımı')
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->visible(fn () => $this->selectedTour() !== null)
                ->url(fn () => TourDepartureResource::getUrl('allocation', ['record' => $this->data['tour_departure_id']])),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $tour = $this->selectedTour();

        if (! $tour) {
            return ['tour' => null, 'summary' => [], 'vehicles' => collect()];
        }

        $vehicles = $tour->vehicles()->with(['groups' => fn ($q) => $q->seatHolding(), 'guide'])->get();
        $capacity = (int) $vehicles->sum(fn (DepartureVehicle $v) => $v->usable_seats);
        $registered = $tour->seats_taken;
        $waiting = $tour->unassigned_passengers;

        return [
            'tour' => $tour,
            'vehicles' => $vehicles,
            'summary' => [
                ['Araç', $vehicles->count(), $capacity.' yolcu koltuğu', $vehicles->isEmpty() ? 'danger' : 'gray'],
                ['Kayıtlı yolcu', $registered, $tour->seatHoldingGroups()->count().' grup', $capacity > 0 && $registered > $capacity ? 'danger' : 'gray'],
                ['Boş koltuk', max(0, $capacity - $registered), $capacity > 0 ? '%'.round($registered / $capacity * 100).' doluluk' : 'araç yok', $capacity - $registered < 0 ? 'danger' : 'success'],
                ['Yerleşmeyi bekleyen', $waiting, $waiting > 0 ? 'yolcu araç bekliyor' : 'herkes yerleşti', $waiting > 0 ? 'warning' : 'success'],
            ],
        ];
    }
}
