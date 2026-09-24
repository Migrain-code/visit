<?php

namespace Tests\Feature;

use App\Filament\Pages\VehicleWizard;
use App\Filament\Resources\VehicleHistory\VehicleHistoryResource;
use App\Models\DepartureVehicle;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

/**
 * Araç Liste Sihirbazı ve Araç Geçmişi.
 */
class VehicleWizardTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $undo = Repeater::fake();
        $this->beforeApplicationDestroyed($undo);
    }

    private function tour(array $attributes = []): TourDeparture
    {
        return TourDeparture::create(array_merge([
            'title' => 'Batum',
            'starts_at' => now()->addDays(10)->setTime(7, 0),
            'price' => 1250,
        ], $attributes));
    }

    private function group(TourDeparture $tour, int $size): TourGroup
    {
        $group = TourGroup::create(['tour_departure_id' => $tour->getKey()]);

        for ($i = 0; $i < $size; $i++) {
            $group->passengers()->create(['first_name' => 'Yolcu', 'last_name' => (string) $i]);
        }

        return $group->refresh();
    }

    public function test_wizard_saves_vehicles_with_details_and_seats_the_groups(): void
    {
        $this->actingAs($this->operations());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tour = $this->tour();
        $guide = $this->guide(['name' => 'Rehber Ali']);
        $fleet = Vehicle::where('seat_count', 19)->firstOrFail();

        foreach ([9, 8, 5] as $size) {
            $this->group($tour, $size);
        }

        $wizard = Livewire::test(VehicleWizard::class)
            ->fillForm([
                'tour_departure_id' => $tour->id,
                'vehicles' => [
                    ['vehicle_id' => $fleet->id, 'name' => '19 Koltuklu Minibüs', 'seat_count' => 19, 'plate' => '53 AB 123', 'driver_name' => 'Ali Usta, Veli Usta', 'driver_phone' => '05320000000', 'cost' => 4500, 'guide_id' => $guide->id, 'notes' => 'Sabah 6:45 hazır', 'reserved_seats' => 0],
                    ['vehicle_id' => null, 'name' => 'Kiralık Minibüs', 'seat_count' => 12, 'plate' => '53 CD 456', 'driver_name' => null, 'driver_phone' => null, 'cost' => 6000, 'guide_id' => null, 'notes' => null, 'reserved_seats' => 1],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $vehicles = $tour->vehicles()->get();
        $this->assertCount(2, $vehicles);
        $this->assertSame(['19 Koltuklu Minibüs', 'Kiralık Minibüs'], $vehicles->pluck('name')->all());
        $this->assertSame('53 AB 123', $vehicles[0]->plate);
        $this->assertSame('Ali Usta, Veli Usta', $vehicles[0]->driver_name);
        $this->assertSame($guide->id, $vehicles[0]->guide_id);
        $this->assertSame('4500.00', (string) $vehicles[0]->cost);
        $this->assertSame(11, $vehicles[1]->usable_seats, 'görevliye ayrılan koltuk düşülür');
        $this->assertSame(10500.0, $tour->fresh()->vehicle_cost);

        // Grupları yerleştir: 22 yolcu tek araca sığmaz; 9+8 = 17 ilk araca, 5'lik ikinciye; kimse bölünmez.
        $wizard->callAction('allocate');

        $this->assertSame(0, $tour->fresh()->unassigned_passengers);
        $this->assertSame(17, $vehicles[0]->fresh()->occupied_seats);
        $this->assertSame(5, $vehicles[1]->fresh()->occupied_seats);

        // Özet kartlarında boş koltuk görünür.
        $wizard->assertSee('Boş koltuk')->assertSee('Rehber: Rehber Ali');

        // Araç rehberi artık bu turu görür.
        $this->assertTrue(TourDeparture::query()->visibleTo($guide)->whereKey($tour->id)->exists());
    }

    public function test_wizard_edits_in_place_and_removing_a_vehicle_releases_its_groups(): void
    {
        $this->actingAs($this->operations());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tour = $this->tour();
        $keep = $tour->vehicles()->create(['name' => 'Kalan', 'seat_count' => 19]);
        $drop = $tour->vehicles()->create(['name' => 'Giden', 'seat_count' => 19]);
        $group = $this->group($tour, 4);
        $group->forceFill(['departure_vehicle_id' => $drop->id])->saveQuietly();

        // Sayfa ?tour= ile açılınca mevcut araçlar forma gelir.
        $wizard = Livewire::withQueryParams(['tour' => $tour->id])->test(VehicleWizard::class);
        $this->assertCount(2, $wizard->get('data.vehicles'));
        $this->assertSame('Kalan', $wizard->get('data.vehicles.0.name'));

        $wizard->fillForm([
            'tour_departure_id' => $tour->id,
            'vehicles' => [
                ['id' => $keep->id, 'vehicle_id' => null, 'name' => 'Kalan (güncel)', 'seat_count' => 24, 'plate' => '53 X 1', 'cost' => 3000, 'reserved_seats' => 0],
            ],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame(['Kalan (güncel)'], $tour->vehicles()->pluck('name')->all());
        $this->assertSame(24, $keep->fresh()->seat_count);
        $this->assertNull(DepartureVehicle::find($drop->id), 'listeden çıkarılan araç turdan silinir');
        $this->assertNull($group->fresh()->departure_vehicle_id, 'içindeki grup silinmez, bekleyenlere döner');
        $this->assertSame(4, $group->fresh()->passenger_count);
    }

    public function test_wizard_requires_the_allocation_permission(): void
    {
        $this->actingAs($this->registrar())->get('/admin/arac-sihirbazi')->assertForbidden();
        $this->actingAs($this->staff(['allocation.manage']))->get('/admin/arac-sihirbazi')->assertOk();
    }

    public function test_vehicle_history_shows_which_vehicle_went_to_which_tour(): void
    {
        $batum = $this->tour(['title' => 'Batum', 'starts_at' => now()->subDays(20)]);
        $uzungol = $this->tour(['title' => 'Uzungöl', 'starts_at' => now()->subDays(6)]);
        $fleet = Vehicle::where('seat_count', 19)->firstOrFail();

        $a = $batum->vehicles()->create(['vehicle_id' => $fleet->id, 'plate' => '53 AB 123', 'cost' => 4000]);
        $b = $uzungol->vehicles()->create(['vehicle_id' => $fleet->id, 'plate' => '53 AB 123', 'cost' => 4200]);
        $c = $uzungol->vehicles()->create(['name' => 'Kiralık', 'seat_count' => 46, 'plate' => '61 ZZ 999']);
        $this->group($uzungol, 7)->forceFill(['departure_vehicle_id' => $b->id])->saveQuietly();

        $this->actingAs($this->operations());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/arac-gecmisi')->assertOk()->assertSee('Batum')->assertSee('Uzungöl')->assertSee('53 AB 123')->assertSee('7 / 19');

        $page = VehicleHistoryResource::getPages()['index']->getPage();

        Livewire::test($page)
            ->filterTable('vehicle_id', $fleet->id)
            ->assertCanSeeTableRecords([$a, $b])
            ->assertCanNotSeeTableRecords([$c]);

        Livewire::test($page)
            ->filterTable('plate', ['plate' => 'ZZ'])
            ->assertCanSeeTableRecords([$c])
            ->assertCanNotSeeTableRecords([$a, $b]);

        Livewire::test($page)
            ->filterTable('dates', ['from' => now()->subDays(10)->toDateString(), 'until' => null])
            ->assertCanSeeTableRecords([$b, $c])
            ->assertCanNotSeeTableRecords([$a]);
    }
}
