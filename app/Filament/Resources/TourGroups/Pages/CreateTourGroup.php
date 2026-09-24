<?php

namespace App\Filament\Resources\TourGroups\Pages;

use App\Enums\GroupStatus;
use App\Filament\Resources\TourGroups\Pages\Concerns\ReportsSeatSituation;
use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\ReservationRequest;
use App\Models\TourDeparture;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Yolcu Ekle: personelin ana ekranı. Turu seçer, yolcuları girer, kaydeder;
 * kayıt bir grup olur ("Grup 1", "Grup 2"...).
 */
class CreateTourGroup extends CreateRecord
{
    use ReportsSeatSituation;

    protected static string $resource = TourGroupResource::class;

    protected static ?string $title = 'Yolcu Ekle';

    /** Gruba dönüştürülen iletişim talebi (adres çubuğundan gelir). Tarayıcıdan değiştirilemez. */
    #[Locked]
    public ?int $requestId = null;

    public function getSubheading(): ?string
    {
        return 'Turu seçin, yolcuları alt alta girin. Kaydedince hepsi bir grup olur ve aynı araçta yolculuk eder.';
    }

    /**
     * Form, turdan ("Yolcu ekle") ya da iletişim talebinden ("Gruba dönüştür")
     * açıldıysa ön doldurulur.
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        /*
         * Forma değer vererek doldurmak alan varsayılanlarını (durum, ilk yolcu satırı)
         * DEVRE DIŞI bırakır; bu yüzden ön doldurma yapılırken onlar da elle verilir.
         * Ön doldurma yoksa form kendi varsayılanlarıyla açılır.
         */
        $prefill = $this->prefill();

        $prefill === []
            ? $this->form->fill()
            : $this->form->fill($prefill + [
                'status' => GroupStatus::Confirmed->value,
                'paid_amount' => 0,
                'passengers' => [[]],
            ]);

        $this->callHook('afterFill');
    }

    /** @return array<string, mixed> */
    private function prefill(): array
    {
        $data = [];

        if ($departure = TourDeparture::query()->visibleTo(auth()->user())->find(request()->query('departure'))) {
            $data['tour_departure_id'] = $departure->getKey();
        }

        $request = ReservationRequest::find(request()->query('request'));

        if ($request && (auth()->user()?->can('update', $request) ?? false)) {
            $this->requestId = $request->getKey();

            $data['tour_departure_id'] ??= $request->tour_departure_id;
            $data['notes'] = $request->message;

            // Talepteki kişi sayısı kadar boş yolcu satırı; ilki başvuranın adıyla.
            $first = Str::beforeLast($request->name, ' ');
            $last = Str::contains($request->name, ' ') ? Str::afterLast($request->name, ' ') : '';

            $data['passengers'] = collect(range(1, max(1, min(60, (int) $request->people_count))))
                ->map(fn (int $i) => $i === 1
                    ? ['first_name' => $first, 'last_name' => $last, 'phone' => $request->phone]
                    : [])
                ->all();
        }

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->requestId) {
            $data['reservation_request_id'] = $this->requestId;
        }

        // İletişim kişisi ilk yolcudur; yolcu satırları kayıttan sonra yazıldığı için
        // ham form durumundan alınır (Passenger kaydedilince yeniden eşitlenir).
        $first = collect($this->data['passengers'] ?? [])->first(fn ($row) => filled($row['first_name'] ?? null));

        $data['contact_name'] = $first ? trim(($first['first_name'] ?? '').' '.($first['last_name'] ?? '')) : ($data['name'] ?? null);
        $data['contact_phone'] = $first['phone'] ?? null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->requestId) {
            ReservationRequest::query()->whereKey($this->requestId)->update(['status' => ReservationRequest::STATUS_RESERVED]);
        }

        $this->reportSeatSituation($this->record);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->record->name.' kaydedildi';
    }

    protected function getRedirectUrl(): string
    {
        return TourGroupResource::getUrl('create', ['departure' => $this->record->tour_departure_id]);
    }
}
