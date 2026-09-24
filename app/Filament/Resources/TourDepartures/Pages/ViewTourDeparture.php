<?php

namespace App\Filament\Resources\TourDepartures\Pages;

use App\Filament\Pages\VehicleWizard;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\TourCommission;
use App\Models\TourLedgerEntry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;

class ViewTourDeparture extends ViewRecord
{
    protected static string $resource = TourDepartureResource::class;

    protected static ?string $navigationLabel = 'Özet';

    public function getTitle(): string
    {
        return $this->getRecord()->label;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->date_label;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addGroup')
                ->label('Yolcu ekle')
                ->icon('heroicon-o-user-plus')
                ->url(fn () => TourGroupResource::getUrl('create', ['departure' => $this->getRecord()->getKey()]))
                ->visible(fn () => (auth()->user()?->registersGroups() ?? false) && $this->getRecord()->status->acceptsGroups()),

            Action::make('wizard')
                ->label('Araç sihirbazı')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(fn () => VehicleWizard::getUrl(['tour' => $this->getRecord()->getKey()]))
                ->visible(fn () => auth()->user()?->can('allocate', $this->getRecord()) ?? false),

            $this->commissionAction(),
            $this->ledgerAction(),

            Action::make('manifest')
                ->label('Yolcu listesi')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('admin.manifest', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * Komisyon ekle: personel listesi, her birinin yanında tutar. En üstteki
     * "Tümüne uygula" alanı bütün kutuları aynı anda doldurur. Boş bırakılan
     * personelin komisyonu silinir.
     */
    private function commissionAction(): Action
    {
        $staff = fn () => User::query()->active()->ordered()->get();

        return Action::make('commission')
            ->label('Komisyon ekle')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn () => auth()->user()?->can('commission', $this->getRecord()) ?? false)
            ->modalHeading(fn () => $this->getRecord()->label.' · Komisyon')
            ->modalDescription('Bu turda görev alan personele ödenecek sabit tutarlar. Boş bırakılan personele komisyon yazılmaz.')
            ->modalSubmitActionLabel('Kaydet')
            ->modalWidth('lg')
            ->fillForm(fn () => [
                'amounts' => $this->getRecord()->commissions()->pluck('amount', 'user_id')
                    ->map(fn ($amount) => (float) $amount == (int) $amount ? (int) $amount : (float) $amount)
                    ->all(),
            ])
            ->schema(function () use ($staff) {
                $users = $staff();

                return [
                    TextInput::make('apply_all')
                        ->label('Tümüne uygula')
                        ->numeric()->minValue(0)->step('0.01')
                        ->suffix('₺')
                        ->placeholder('Örn. 500')
                        ->live(onBlur: true)
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set, $state) use ($users) {
                            foreach ($users as $user) {
                                $set('amounts.'.$user->getKey(), $state);
                            }
                        })
                        ->helperText('Buraya yazılan tutar aşağıdaki herkese yazılır; sonra tek tek değiştirebilirsiniz.'),
                    Section::make('Personel')
                        ->schema($users->map(fn (User $user) => TextInput::make('amounts.'.$user->getKey())
                            ->label($user->name)
                            ->numeric()->minValue(0)->step('0.01')
                            ->suffix('₺')
                            ->placeholder('—'))->all())
                        ->columns(2)
                        ->compact(),
                ];
            })
            ->action(function (array $data) {
                abort_unless(auth()->user()?->can('commission', $this->getRecord()), 403);

                $departure = $this->getRecord();
                $amounts = collect($data['amounts'] ?? [])->map(fn ($v) => (float) $v);

                DB::transaction(function () use ($departure, $amounts) {
                    foreach ($amounts as $userId => $amount) {
                        if ($amount > 0) {
                            TourCommission::query()->updateOrCreate(
                                ['tour_departure_id' => $departure->getKey(), 'user_id' => $userId],
                                ['amount' => $amount, 'created_by' => auth()->id()],
                            );
                        } else {
                            $departure->commissions()->where('user_id', $userId)->delete();
                        }
                    }
                });

                $count = $amounts->filter(fn ($a) => $a > 0)->count();

                Notification::make()
                    ->title($count > 0 ? "{$count} personele komisyon yazıldı" : 'Komisyonlar temizlendi')
                    ->body('Toplam: '.money_label($amounts->filter(fn ($a) => $a > 0)->sum()))
                    ->success()->send();
            });
    }

    /** Ekstra gelir / gider: yemek, giriş ücreti, sponsorluk gibi kalemler. */
    private function ledgerAction(): Action
    {
        return Action::make('ledger')
            ->label('Kasa hareketi')
            ->icon('heroicon-o-receipt-percent')
            ->color('gray')
            ->visible(fn () => auth()->user()?->can('ledger', $this->getRecord()) ?? false)
            ->modalHeading('Ekstra gelir / gider ekle')
            ->modalSubmitActionLabel('Ekle')
            ->modalWidth('md')
            ->schema([
                Select::make('type')
                    ->label('Tür')
                    ->options(TourLedgerEntry::TYPES)
                    ->default(TourLedgerEntry::TYPE_EXPENSE)
                    ->required()
                    ->native(false),
                TextInput::make('title')->label('Açıklama')->required()->maxLength(150)->placeholder('Öğle yemeği, müze girişi, sponsor desteği...'),
                TextInput::make('amount')->label('Tutar')->numeric()->minValue(0.01)->step('0.01')->suffix('₺')->required(),
                DatePicker::make('entry_date')->label('Tarih')->native(false)->displayFormat('d.m.Y')->default(now()),
            ])
            ->action(function (array $data) {
                abort_unless(auth()->user()?->can('ledger', $this->getRecord()), 403);

                $this->getRecord()->ledgerEntries()->create($data);

                Notification::make()->title('Kasa hareketi eklendi')->success()->send();
            });
    }
}
