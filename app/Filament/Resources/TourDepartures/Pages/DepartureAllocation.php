<?php

namespace App\Filament\Resources\TourDepartures\Pages;

use App\Enums\GroupStatus;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\DepartureVehicle;
use App\Models\TourGroup;
use App\Services\Allocation\DepartureAllocator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * Araç dağılım panosu: grupları sefere atanmış araçlara yerleştirir.
 *
 * GRUP BÖLÜNMEZ. Otomatik dağıtım da elle taşıma da bir grubu yalnız BÜTÜN hâlinde
 * tek bir araca koyar; sığmıyorsa açıkta bırakır ve nedenini söyler.
 */
class DepartureAllocation extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TourDepartureResource::class;

    protected static ?string $navigationLabel = 'Araç Dağılımı';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $title = 'Araç Dağılımı';

    protected string $view = 'filament.resources.tour-departures.allocation';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(TourDepartureResource::canView($this->getRecord()), 403);
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->label.' · '.$this->getRecord()->code;
    }

    private function canAllocate(): bool
    {
        return auth()->user()?->can('allocate', $this->getRecord()) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fillGaps')
                ->label('Bekleyenleri yerleştir')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->tooltip('Yerleşmiş gruplara dokunmaz; yalnız araçsız grupları boş koltuklara yerleştirir.')
                ->visible(fn () => $this->canAllocate())
                ->action(fn () => $this->runAllocation(keepExisting: true)),

            Action::make('reallocate')
                ->label('Baştan dağıt')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->canAllocate())
                ->requiresConfirmation()
                ->modalHeading('Tüm gruplar baştan dağıtılsın mı?')
                ->modalDescription('Elle sabitlediğiniz (kilitli) gruplar yerinde kalır. Diğer tüm gruplar araçlara yeniden, en az araç ve en dolu koltuk düzeniyle yerleştirilir. Yolculara araç bilgisi verdiyseniz değişebilir.')
                ->modalSubmitActionLabel('Baştan dağıt')
                ->action(fn () => $this->runAllocation(keepExisting: false)),

            Action::make('reset')
                ->label('Dağıtımı sıfırla')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn () => $this->canAllocate())
                ->requiresConfirmation()
                ->modalHeading('Tüm araç atamaları kaldırılsın mı?')
                ->modalDescription('Sabitlenenler dahil bütün gruplar araçsız kalır. Grup ve yolcu kayıtları silinmez.')
                ->action(function () {
                    $count = app(DepartureAllocator::class)->reset($this->getRecord());

                    Notification::make()->title($count.' grubun araç ataması kaldırıldı.')->success()->send();
                }),

            Action::make('manifest')
                ->label('Yolcu listesi')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('admin.manifest', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }

    private function runAllocation(bool $keepExisting): void
    {
        abort_unless($this->canAllocate(), 403);

        $report = app(DepartureAllocator::class)->allocate($this->getRecord(), $keepExisting);

        $notification = Notification::make()
            ->title($report->headline())
            ->body(implode("\n", $report->lines()));

        $report->allPlaced() && $report->overloaded === []
            ? $notification->success()
            : $notification->warning()->persistent();

        $notification->send();
    }

    /** Grubu elle bir araca taşır. Panodaki "Taşı" düğmesi bu eylemi grup kimliğiyle açar. */
    public function moveGroupAction(): Action
    {
        return Action::make('moveGroup')
            ->label('Taşı')
            ->modalHeading(fn (array $arguments) => ($this->findGroup($arguments['group'] ?? null)?->display_name ?? 'Grup').' — araç seç')
            ->modalDescription('Grup seçtiğiniz araca BÜTÜN hâlinde taşınır ve oraya sabitlenir; otomatik dağıtım sabitlenen gruba dokunmaz.')
            ->modalSubmitActionLabel('Taşı ve sabitle')
            ->schema(fn (array $arguments) => [
                Select::make('vehicle')
                    ->label('Araç')
                    ->options($this->vehicleOptionsFor($this->findGroup($arguments['group'] ?? null)))
                    ->placeholder('Araçtan çıkar (yerleşmemiş)')
                    ->native(false)
                    ->helperText('Yeterli boş koltuğu olmayan araçlar listede görünmez: grup bölünmez.'),
            ])
            ->action(function (array $data, array $arguments) {
                abort_unless($this->canAllocate(), 403);

                $group = $this->findGroup($arguments['group'] ?? null);

                if (! $group) {
                    return;
                }

                $vehicle = filled($data['vehicle'] ?? null)
                    ? $this->getRecord()->vehicles()->whereKey($data['vehicle'])->first()
                    : null;

                try {
                    app(DepartureAllocator::class)->move($group, $vehicle);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Grup taşınamadı')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($vehicle ? $group->display_name.' → '.$vehicle->name : $group->display_name.' araçtan çıkarıldı')
                    ->success()->send();
            });
    }

    /** Sabitlemeyi açar/kapatır: sabit grup "Baştan dağıt"ta yerinden oynamaz. */
    public function togglePin(int $groupId): void
    {
        abort_unless($this->canAllocate(), 403);

        $group = $this->findGroup($groupId);

        if (! $group || ! $group->departure_vehicle_id) {
            return;
        }

        $group->forceFill(['is_pinned' => ! $group->is_pinned])->saveQuietly();
    }

    private function findGroup(int|string|null $id): ?TourGroup
    {
        return $id ? $this->getRecord()->groups()->whereKey($id)->first() : null;
    }

    /** @return array<int, string> yalnız grubu BÜTÜN hâlinde alabilecek araçlar */
    private function vehicleOptionsFor(?TourGroup $group): array
    {
        if (! $group) {
            return [];
        }

        return $this->getRecord()->vehicles()->with('groups')->get()
            ->mapWithKeys(function (DepartureVehicle $vehicle) use ($group) {
                $occupied = $vehicle->groups
                    ->where('status', '!=', GroupStatus::Cancelled)
                    ->where('id', '!=', $group->getKey())
                    ->sum('passenger_count');
                $free = $vehicle->usable_seats - $occupied;

                return $free >= $group->passenger_count
                    ? [$vehicle->getKey() => $vehicle->label.' — '.$free.' boş koltuk']
                    : [];
            })
            ->all();
    }

    protected function getViewData(): array
    {
        $departure = $this->getRecord();
        $vehicles = $departure->vehicles()->with(['groups' => fn ($q) => $q->seatHolding()->with('passengers')])->get();
        $waiting = $departure->seatHoldingGroups()->whereNull('departure_vehicle_id')->with('passengers')->get();

        return [
            'departure' => $departure,
            'vehicles' => $vehicles,
            'waiting' => $waiting,
            'capacity' => (int) $vehicles->sum(fn (DepartureVehicle $v) => $v->usable_seats),
            'registered' => (int) $departure->seatHoldingGroups()->sum('passenger_count'),
            'canAllocate' => $this->canAllocate(),
            'groupUrl' => fn (TourGroup $group) => (auth()->user()?->can('update', $group) ?? false)
                ? TourGroupResource::getUrl('edit', ['record' => $group])
                : TourGroupResource::getUrl('view', ['record' => $group]),
        ];
    }
}
