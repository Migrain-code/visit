<?php

namespace Tests\Feature;

use App\Enums\GroupStatus;
use App\Enums\UserRole;
use App\Filament\Resources\TourDepartures\Pages\CreateTourDeparture;
use App\Filament\Resources\TourDepartures\Pages\DepartureAllocation;
use App\Filament\Resources\TourGroups\Pages\CreateTourGroup;
use App\Filament\Resources\TourGroups\Pages\EditTourGroup;
use App\Models\Passenger;
use App\Models\ReservationRequest;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Allocation\DepartureAllocator;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Tur operasyonunun temel kuralları.
 *
 * EN ÖNEMLİSİ: bir grup asla iki araca bölünmez ve hiçbir araç kapasitesini aşmaz.
 */
class TourOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Biçimce geçerli, kurgusal T.C. kimlik numaraları. */
    private const TC = ['10000000146', '10000000214', '10000000382', '10000000450', '10000000528', '10000000696'];

    private int $tcSerial = 0;

    /**
     * Yolcu satırları testte 0, 1, 2... diye anahtarlanır. Aksi hâlde her satır rastgele
     * bir kimlik alır ve fillForm() listesi varsayılan boş satırın YANINA eklenir.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $undo = Repeater::fake();
        $this->beforeApplicationDestroyed($undo);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function user(UserRole $role): User
    {
        return User::create(['name' => $role->label(), 'email' => $role->value.'@ornek.test', 'password' => 'parola1234', 'role' => $role, 'is_active' => true]);
    }

    /** @param array<int, int> $seats sefere atanacak araçların koltuk sayıları */
    private function departure(array $seats = [], array $attributes = []): TourDeparture
    {
        $departure = TourDeparture::create(array_merge([
            'tour_id' => Tour::where('slug', 'batum-tiflis-turu')->firstOrFail()->getKey(),
            'starts_at' => now()->addDays(20)->setTime(20, 0),
        ], $attributes));

        foreach ($seats as $count) {
            $departure->vehicles()->create(['name' => $count.' koltuklu', 'seat_count' => $count]);
        }

        return $departure;
    }

    private function group(TourDeparture $departure, int $size, array $attributes = []): TourGroup
    {
        $group = TourGroup::create(array_merge([
            'tour_departure_id' => $departure->getKey(),
            'contact_name' => $size.' kişilik grup',
            'contact_phone' => '05321112233',
        ], $attributes));

        for ($i = 0; $i < $size; $i++) {
            $group->passengers()->create(['first_name' => 'Yolcu', 'last_name' => (string) ++$this->tcSerial, 'age' => 30, 'gender' => 'male']);
        }

        return $group->refresh();
    }

    /** Hiçbir araç taşmamalı; her grup TEK bir araçta olmalı. */
    private function assertNoVehicleOverflows(TourDeparture $departure): void
    {
        foreach ($departure->vehicles()->get() as $vehicle) {
            $this->assertLessThanOrEqual($vehicle->usable_seats, $vehicle->occupied_seats, $vehicle->name.' kapasitesini aştı.');
        }
    }

    // ---------- Sefer ----------

    public function test_departure_gets_a_code_and_an_end_date_from_the_tour(): void
    {
        $departure = $this->departure([], ['starts_at' => '2027-05-14 20:00:00']);

        $this->assertSame('BAT-140527', $departure->code);
        // Batum Tiflis turu 3 gün sürer: 14 Mayıs'ta çıkan tur 16 Mayıs'ta döner.
        $this->assertSame('2027-05-16', $departure->ends_on->toDateString());

        $second = $this->departure([], ['starts_at' => '2027-05-14 08:00:00']);
        $this->assertSame('BAT-140527-2', $second->code, 'aynı güne ikinci sefer ayrı kod almalı');
    }

    public function test_vehicle_details_are_copied_so_history_survives_fleet_changes(): void
    {
        $fleet = Vehicle::create(['name' => 'Sprinter', 'seat_count' => 19, 'plate' => '59 ABC 123', 'driver_name' => 'Ali Usta']);
        $departure = $this->departure();
        $assigned = $departure->vehicles()->create(['vehicle_id' => $fleet->id]);

        $this->assertSame([19, '59 ABC 123', 'Ali Usta'], [$assigned->seat_count, $assigned->plate, $assigned->driver_name]);

        $fleet->update(['seat_count' => 16]);
        $fleet->delete();

        $assigned->refresh();
        $this->assertSame(19, $assigned->seat_count, 'filodaki değişiklik geçmiş seferi bozmamalı');
        $this->assertNull($assigned->vehicle_id);
    }

    public function test_selected_fleet_vehicles_are_attached_when_a_departure_is_created(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $fleet = Vehicle::query()->whereIn('seat_count', [19, 50])->pluck('id')->all();

        Livewire::test(CreateTourDeparture::class)
            ->fillForm([
                'tour_id' => Tour::where('slug', 'uzungol-turu')->value('id'),
                'starts_at' => now()->addDays(45)->setTime(20, 0)->format('Y-m-d H:i:s'),
                'status' => 'open',
                'vehicle_ids' => $fleet,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $departure = TourDeparture::query()->latest('id')->firstOrFail();

        $this->assertSame([19, 50], $departure->vehicles()->orderBy('seat_count')->pluck('seat_count')->all());
        $this->assertSame(69, $departure->capacity);
    }

    public function test_seat_statistics_and_public_availability(): void
    {
        $departure = $this->departure([19, 24]);
        $departure->vehicles()->first()->update(['reserved_seats' => 1]); // rehber koltuğu

        $this->group($departure, 5);
        $this->group($departure, 4, ['status' => GroupStatus::Pending->value]);   // opsiyon da koltuk tutar
        $this->group($departure, 3, ['status' => GroupStatus::Cancelled->value]); // iptal tutmaz

        $fresh = TourDeparture::query()->withSeatStats()->findOrFail($departure->id);

        $this->assertSame(42, $fresh->capacity, '19 + 24 - 1 ayrılmış koltuk');
        $this->assertSame(9, $fresh->seats_taken);
        $this->assertSame(33, $fresh->seats_left);
        $this->assertSame(9, $fresh->unassigned_passengers);

        // Kontenjan girilirse satış sınırı odur.
        $departure->update(['quota' => 9]);
        $this->assertTrue(TourDeparture::query()->withSeatStats()->findOrFail($departure->id)->is_full);
    }

    // ---------- Dağıtım ----------

    public function test_groups_are_never_split_between_vehicles(): void
    {
        $departure = $this->departure([19, 19]);

        // 5+5+5 = 15. Kalan 4 koltuğa bir 4'lü sığar; ikinci 4'lü BÖLÜNMEDEN ikinci araca gider.
        foreach ([5, 5, 5, 4, 4] as $size) {
            $this->group($departure, $size);
        }

        $report = app(DepartureAllocator::class)->allocate($departure);

        $this->assertTrue($report->allPlaced());
        $this->assertSame(5, $report->placedGroups);
        $this->assertSame(23, $report->placedPassengers);
        $this->assertNoVehicleOverflows($departure);

        [$first, $second] = $departure->vehicles()->get()->all();
        $this->assertSame(19, $first->occupied_seats, 'ilk araç tam dolmalı');
        $this->assertSame(4, $second->occupied_seats);

        // Veri modelinde araç ataması GRUPTADIR: bir grubun yolcuları farklı araçta olamaz.
        $this->assertFalse(Schema::hasColumn('passengers', 'departure_vehicle_id'));
        $this->assertNotNull($departure->allocated_at?->toDateTimeString() ?? $departure->fresh()->allocated_at);
    }

    public function test_a_group_that_fits_nowhere_waits_instead_of_being_split(): void
    {
        $departure = $this->departure([4, 3]);
        $group = $this->group($departure, 5);

        $report = app(DepartureAllocator::class)->allocate($departure);

        $this->assertFalse($report->allPlaced());
        $this->assertNull($group->fresh()->departure_vehicle_id, '7 boş koltuk olsa da 5 kişilik grup bölünmez');
        $this->assertStringContainsString('BÖLÜNMEDEN', $report->headline());
        $this->assertStringContainsString('en büyük araçta 4', $report->reasons[$group->id]);
        // Filoda bu grubu alacak araç var: öneri sunulmalı.
        $this->assertStringContainsString('19 Koltuklu', (string) $report->suggestion);
    }

    public function test_cancelled_groups_take_no_seat_and_leave_their_vehicle(): void
    {
        $departure = $this->departure([10]);
        $staying = $this->group($departure, 6);
        $leaving = $this->group($departure, 4);

        app(DepartureAllocator::class)->allocate($departure);
        $this->assertNotNull($leaving->fresh()->departure_vehicle_id);

        $leaving->fresh()->update(['status' => GroupStatus::Cancelled->value]);

        $this->assertNull($leaving->fresh()->departure_vehicle_id, 'iptal edilen grup araçtaki yerini bırakmalı');
        $this->assertSame(6, $departure->vehicles()->first()->occupied_seats);

        // Boşalan yere yeni grup yerleşebilir.
        $newcomer = $this->group($departure, 4);
        app(DepartureAllocator::class)->allocate($departure, keepExisting: true);

        $this->assertNotNull($newcomer->fresh()->departure_vehicle_id);
        $this->assertNotNull($staying->fresh()->departure_vehicle_id);
    }

    public function test_filling_gaps_never_moves_groups_that_are_already_seated(): void
    {
        $departure = $this->departure([10, 10]);
        [$first, $second] = $departure->vehicles()->get()->all();

        // Kullanıcı bu grubu bilerek İKİNCİ araca koymuş (sabitlemeden).
        $seated = $this->group($departure, 3);
        $seated->forceFill(['departure_vehicle_id' => $second->id])->saveQuietly();

        $waiting = $this->group($departure, 8);

        app(DepartureAllocator::class)->allocate($departure, keepExisting: true);

        $this->assertSame($second->id, $seated->fresh()->departure_vehicle_id, 'yerleşmiş grup yerinde kalmalı');
        $this->assertSame($first->id, $waiting->fresh()->departure_vehicle_id);

        // "Baştan dağıt" ise sabitlenmemiş grubu taşıyabilir: 11 yolcu tek araca sığmaz ama
        // dağılım en dolu düzene göre yeniden kurulur ve yine kimse bölünmez.
        app(DepartureAllocator::class)->allocate($departure, keepExisting: false);
        $this->assertNoVehicleOverflows($departure);
    }

    public function test_pinned_groups_survive_a_full_reallocation(): void
    {
        $departure = $this->departure([10, 10]);
        [, $second] = $departure->vehicles()->get()->all();

        $pinned = $this->group($departure, 2);
        app(DepartureAllocator::class)->move($pinned, $second);

        foreach ([4, 4, 3] as $size) {
            $this->group($departure, $size);
        }

        app(DepartureAllocator::class)->allocate($departure, keepExisting: false);

        $pinned->refresh();
        $this->assertTrue($pinned->is_pinned);
        $this->assertSame($second->id, $pinned->departure_vehicle_id, 'elle sabitlenen grup yerinden oynamaz');
        $this->assertNoVehicleOverflows($departure);
        $this->assertSame(0, $departure->fresh()->unassigned_passengers);
    }

    public function test_manual_move_refuses_to_overfill_or_cross_departures(): void
    {
        $departure = $this->departure([6]);
        $vehicle = $departure->vehicles()->first();
        $this->group($departure, 4)->forceFill(['departure_vehicle_id' => $vehicle->id])->saveQuietly();
        $big = $this->group($departure, 3);

        try {
            app(DepartureAllocator::class)->move($big, $vehicle);
            $this->fail('Sığmayan grup taşınmamalıydı.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('2 boş koltuk', $e->getMessage());
            $this->assertStringContainsString('bölünmeden sığmıyor', $e->getMessage());
        }

        $this->assertNull($big->fresh()->departure_vehicle_id);

        $foreign = $this->departure([50], ['starts_at' => now()->addDays(60)])->vehicles()->first();

        $this->expectException(RuntimeException::class);
        app(DepartureAllocator::class)->move($big, $foreign);
    }

    public function test_a_group_that_outgrows_its_vehicle_is_released_not_split(): void
    {
        $departure = $this->departure([6, 10]);
        $small = $departure->vehicles()->orderBy('seat_count')->first();

        $family = $this->group($departure, 4);
        $couple = $this->group($departure, 2);
        app(DepartureAllocator::class)->move($family, $small);
        app(DepartureAllocator::class)->move($couple, $small);

        // Aileye bir kişi daha eklendi: 5 + 2 = 7 > 6.
        $family->passengers()->create(['first_name' => 'Yeni', 'last_name' => 'Yolcu']);

        $family->refresh();
        $this->assertSame(5, $family->passenger_count);
        $this->assertNull($family->departure_vehicle_id, 'büyüyen grup taşan araçta kalmamalı');
        $this->assertFalse($family->is_pinned);
        $this->assertSame($small->id, $couple->fresh()->departure_vehicle_id, 'diğer grup etkilenmemeli');

        // Yeniden dağıtınca bütün hâlinde büyük araca gider.
        app(DepartureAllocator::class)->allocate($departure, keepExisting: true);
        $this->assertSame(10, $family->fresh()->vehicle->seat_count);
    }

    public function test_shrinking_or_removing_a_vehicle_releases_its_groups(): void
    {
        $departure = $this->departure([10, 10]);
        [$first, $second] = $departure->vehicles()->get()->all();

        $a = $this->group($departure, 6);
        $b = $this->group($departure, 5);
        app(DepartureAllocator::class)->move($a, $first);
        app(DepartureAllocator::class)->move($b, $second);

        // Araç değişti: 10 yerine 5 koltuklu geldi. 6 kişilik grup sığmaz.
        $first->update(['seat_count' => 5]);
        $this->assertNull($a->fresh()->departure_vehicle_id);

        // Araç seferden çıkarıldı: grup SİLİNMEZ, bekleyenlere döner.
        $second->delete();
        $b->refresh();
        $this->assertNull($b->departure_vehicle_id);
        $this->assertFalse($b->is_pinned);
        $this->assertSame(5, $b->passenger_count);
    }

    public function test_moving_a_group_to_another_departure_clears_its_vehicle(): void
    {
        $from = $this->departure([10]);
        $to = $this->departure([10], ['starts_at' => now()->addDays(50)]);

        $group = $this->group($from, 3);
        app(DepartureAllocator::class)->move($group, $from->vehicles()->first());

        $group->fresh()->update(['tour_departure_id' => $to->id]);

        $this->assertNull($group->fresh()->departure_vehicle_id, 'eski seferin aracı yeni seferde geçersizdir');
    }

    public function test_reset_clears_every_assignment_but_keeps_the_records(): void
    {
        $departure = $this->departure([19]);
        $this->group($departure, 4);
        $this->group($departure, 5);
        app(DepartureAllocator::class)->allocate($departure);

        $cleared = app(DepartureAllocator::class)->reset($departure);

        $this->assertSame(2, $cleared);
        $this->assertSame(9, $departure->fresh()->unassigned_passengers);
        $this->assertSame(2, $departure->groups()->count());
        $this->assertSame(9, Passenger::query()->whereIn('tour_group_id', $departure->groups()->pluck('id'))->count());
    }

    public function test_scheduled_command_seats_waiting_groups_shortly_before_departure(): void
    {
        $soon = $this->departure([19], ['starts_at' => now()->addHours(30)]);
        $later = $this->departure([19], ['starts_at' => now()->addDays(30)]);
        $soonGroup = $this->group($soon, 4);
        $laterGroup = $this->group($later, 4);

        $this->artisan('tours:allocate-upcoming')->assertSuccessful();

        $this->assertNotNull($soonGroup->fresh()->departure_vehicle_id, 'kalkışa 48 saatten az kalan sefer yerleştirilmeli');
        $this->assertNull($laterGroup->fresh()->departure_vehicle_id, 'uzak tarihli sefere dokunulmamalı');

        // Ayarlardan kapatılabilir.
        Setting::set('auto_allocate_hours', '0');
        Setting::flush();
        $another = $this->group($soon, 3);

        $this->artisan('tours:allocate-upcoming')->assertSuccessful();
        $this->assertNull($another->fresh()->departure_vehicle_id);
    }

    /**
     * Hosting proc_open'ı kapatıyor; görev ayrı süreçte başlatılsaydı cron her saat
     * çalışsa da hiçbir grup yerleşmezdi. Zamanlayıcının kendisi üzerinden sınanır.
     */
    public function test_scheduler_seats_waiting_groups_on_the_hour(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(13, 0));

        $group = $this->group($this->departure([19], ['starts_at' => now()->addHours(30)]), 4);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNotNull($group->fresh()->departure_vehicle_id, 'saatlik dağıtım zamanlayıcının içinde çalışmadı');
    }

    // ---------- Grup ve yolcu kaydı ----------

    private function passenger(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'tc_no' => self::TC[0],
            'phone' => '05321112233', 'age' => 34, 'gender' => 'female', 'is_foreign' => false,
        ], $overrides);
    }

    public function test_staff_registers_a_group_with_its_passengers(): void
    {
        $registrar = $this->user(UserRole::Kayit);
        $this->actingAs($registrar);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);

        Livewire::test(CreateTourGroup::class)
            ->fillForm([
                'tour_departure_id' => $departure->id,
                'status' => 'confirmed',
                'name' => 'Sınama ailesi',
                'contact_name' => 'Ayşe Yılmaz',
                'contact_phone' => '0532 111 22 33',
                'pickup_point' => 'Ardeşen otel önü',
                'passengers' => [
                    $this->passenger(),
                    $this->passenger(['first_name' => 'Mehmet', 'tc_no' => self::TC[1], 'age' => 36, 'gender' => 'male']),
                    $this->passenger(['first_name' => 'Elif', 'tc_no' => self::TC[2], 'age' => 8, 'phone' => null]),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // (Örnek veride de bir "Yılmaz ailesi" var; ad bilerek farklı.)
        $group = TourGroup::query()->where('name', 'Sınama ailesi')->firstOrFail();

        $this->assertSame(3, $group->passenger_count, 'grup büyüklüğü yolcu satırlarından türemeli');
        $this->assertSame('G-'.str_pad((string) $group->id, 5, '0', STR_PAD_LEFT), $group->code);
        $this->assertSame($registrar->id, $group->created_by);
        $this->assertSame('Sınama ailesi (3 kişi)', $group->display_name);

        $first = $group->passengers()->first();
        $this->assertSame(['Ayşe', 'Yılmaz', self::TC[0], 34], [$first->first_name, $first->last_name, $first->tc_no, $first->age]);
        $this->assertSame('Kadın', $first->gender->label());
        $this->assertSame('100******46', $first->masked_identity, 'liste ekranlarında kimlik maskelenir');
    }

    public function test_identity_numbers_are_validated(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);
        $base = ['tour_departure_id' => $departure->id, 'status' => 'confirmed', 'contact_name' => 'Test', 'contact_phone' => '05321112233'];

        // Tek hanesi yanlış yazılmış numara: kontrol basamağı tutmaz.
        Livewire::test(CreateTourGroup::class)
            ->fillForm($base + ['passengers' => [$this->passenger(['tc_no' => '10000000147'])]])
            ->call('create')
            ->assertHasFormErrors();

        // Aynı kişi aynı forma iki kez yazılamaz.
        Livewire::test(CreateTourGroup::class)
            ->fillForm($base + ['passengers' => [$this->passenger(), $this->passenger(['first_name' => 'Kopya'])]])
            ->call('create')
            ->assertHasFormErrors();

        // Kesin kayıtta kimlik, yaş ve cinsiyet zorunludur.
        Livewire::test(CreateTourGroup::class)
            ->fillForm($base + ['passengers' => [$this->passenger(['tc_no' => null, 'age' => null, 'gender' => null])]])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, TourGroup::where('tour_departure_id', $departure->id)->count());
    }

    public function test_the_same_person_cannot_be_registered_twice_on_one_departure(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);
        $other = $this->departure([19], ['starts_at' => now()->addDays(60)]);

        $existing = $this->group($departure, 0, ['contact_name' => 'İlk Kayıt']);
        $existing->passengers()->create($this->passenger());

        $form = fn (int $departureId) => [
            'tour_departure_id' => $departureId, 'status' => 'confirmed',
            'contact_name' => 'İkinci Kayıt', 'contact_phone' => '05321112299',
            'passengers' => [$this->passenger()],
        ];

        Livewire::test(CreateTourGroup::class)->fillForm($form($departure->id))->call('create')->assertHasFormErrors();

        // Aynı kişi BAŞKA bir sefere elbette kaydolabilir.
        Livewire::test(CreateTourGroup::class)->fillForm($form($other->id))->call('create')->assertHasNoFormErrors();

        // İptal edilen kayıt engel olmaz.
        $existing->update(['status' => GroupStatus::Cancelled->value]);
        Livewire::test(CreateTourGroup::class)->fillForm($form($departure->id))->call('create')->assertHasNoFormErrors();
    }

    public function test_option_holds_seats_before_identity_details_are_known(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);

        Livewire::test(CreateTourGroup::class)
            ->fillForm([
                'tour_departure_id' => $departure->id, 'status' => 'pending',
                'contact_name' => 'Telefonla Arayan', 'contact_phone' => '05321112233',
                'passengers' => [
                    ['first_name' => 'Hasan', 'last_name' => 'Kaya'],
                    ['first_name' => 'Misafir', 'last_name' => '2'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, TourDeparture::query()->withSeatStats()->findOrFail($departure->id)->seats_taken, 'opsiyon da koltuk tutar');
    }

    public function test_foreign_passengers_use_a_passport_number(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);

        Livewire::test(CreateTourGroup::class)
            ->fillForm([
                'tour_departure_id' => $departure->id, 'status' => 'confirmed',
                'contact_name' => 'John Smith', 'contact_phone' => '05321112233',
                'passengers' => [['first_name' => 'John', 'last_name' => 'Smith', 'is_foreign' => true, 'passport_no' => 'P1234567', 'age' => 41, 'gender' => 'male']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $passenger = Passenger::where('passport_no', 'P1234567')->firstOrFail();
        $this->assertNull($passenger->tc_no);
        $this->assertSame('P1234567', $passenger->identity);
    }

    public function test_editing_a_group_keeps_passengers_and_reports_when_it_no_longer_fits(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([3]);
        $group = $this->group($departure, 0);
        foreach ([0, 1, 2] as $i) {
            $group->passengers()->create($this->passenger(['first_name' => 'Yolcu'.$i, 'tc_no' => self::TC[$i]]));
        }
        app(DepartureAllocator::class)->move($group->refresh(), $departure->vehicles()->first());

        // Değiştirmeden kaydetmek hiçbir şeyi bozmamalı.
        Livewire::test(EditTourGroup::class, ['record' => $group->id])->call('save')->assertHasNoFormErrors();

        $group->refresh();
        $this->assertSame(3, $group->passenger_count);
        $this->assertNotNull($group->departure_vehicle_id);
        $this->assertSame([self::TC[0], self::TC[1], self::TC[2]], $group->passengers()->pluck('tc_no')->all());
    }

    public function test_reservation_request_becomes_a_group(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);
        $request = ReservationRequest::create([
            'name' => 'Zeynep Demir', 'phone' => '05329998877', 'email' => 'zeynep@ornek.test',
            'tour_id' => $departure->tour_id, 'tour_departure_id' => $departure->id,
            'people_count' => 3, 'message' => 'Cam kenarı olursa seviniriz.', 'kvkk_accepted' => true,
        ]);

        $page = Livewire::withQueryParams(['request' => $request->id])->test(CreateTourGroup::class);

        // Form talepten ön doldurulur: iletişim bilgisi ve kişi sayısı kadar yolcu satırı.
        $page->assertFormSet([
            'tour_departure_id' => $departure->id,
            'contact_name' => 'Zeynep Demir',
            'contact_phone' => '05329998877',
        ]);
        $this->assertCount(3, $page->get('data.passengers'));

        // İlk satır başvuranın adıyla gelir.
        $this->assertSame(['Zeynep', 'Demir'], [$page->get('data.passengers.0.first_name'), $page->get('data.passengers.0.last_name')]);

        $page->fillForm([
            'passengers' => [
                $this->passenger(['first_name' => 'Zeynep', 'last_name' => 'Demir']),
                $this->passenger(['first_name' => 'Can', 'last_name' => 'Demir', 'tc_no' => self::TC[1], 'gender' => 'male']),
                $this->passenger(['first_name' => 'Ece', 'last_name' => 'Demir', 'tc_no' => self::TC[2], 'age' => 6]),
            ],
        ])->call('create')->assertHasNoFormErrors();

        $group = TourGroup::where('reservation_request_id', $request->id)->firstOrFail();

        $this->assertSame(3, $group->passenger_count);
        $this->assertSame(ReservationRequest::STATUS_RESERVED, $request->fresh()->status, 'talep "kayda dönüştü" olmalı');
        $this->assertSame($group->id, $request->fresh()->group->id);
    }

    // ---------- Dağılım panosu ----------

    public function test_allocation_board_actions(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19, 19]);
        [$first, $second] = $departure->vehicles()->get()->all();
        $groups = collect([5, 5, 5, 4, 4])->map(fn (int $size) => $this->group($departure, $size));

        $board = Livewire::test(DepartureAllocation::class, ['record' => $departure->id])
            ->assertSee('Yerleşmeyi bekleyen gruplar')
            ->assertSee('bölünmez');

        $board->callAction('fillGaps');
        $this->assertSame(0, $departure->fresh()->unassigned_passengers);
        $this->assertNoVehicleOverflows($departure);

        // Elle taşıma: 4'lü grubu ikinci araca al ve sabitle.
        $mover = $groups->last();
        $board->callAction('moveGroup', data: ['vehicle' => $second->id], arguments: ['group' => $mover->id]);

        $mover->refresh();
        $this->assertSame($second->id, $mover->departure_vehicle_id);
        $this->assertTrue($mover->is_pinned);

        // Sabitlemeyi kaldır.
        $board->call('togglePin', $mover->id);
        $this->assertFalse($mover->fresh()->is_pinned);

        // Dolu araç "Taşı" listesinde hiç sunulmaz: seçilmeye çalışılırsa form reddeder
        // ve grup olduğu yerde kalır.
        $first->update(['seat_count' => $first->occupied_seats]);
        Livewire::test(DepartureAllocation::class, ['record' => $departure->id])
            ->callAction('moveGroup', data: ['vehicle' => $first->id], arguments: ['group' => $mover->id])
            ->assertHasActionErrors(['vehicle']);
        $this->assertSame($second->id, $mover->fresh()->departure_vehicle_id);

        Livewire::test(DepartureAllocation::class, ['record' => $departure->id])->callAction('reset');
        $this->assertSame(23, $departure->fresh()->unassigned_passengers);
    }

    public function test_registrar_sees_the_board_but_cannot_change_it(): void
    {
        $this->actingAs($this->user(UserRole::Kayit));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $departure = $this->departure([19]);
        $group = $this->group($departure, 4);

        Livewire::test(DepartureAllocation::class, ['record' => $departure->id])
            ->assertOk()
            ->assertActionHidden('fillGaps')
            ->assertActionHidden('reallocate')
            ->assertActionHidden('reset')
            ->call('togglePin', $group->id)
            ->assertForbidden();
    }

    public function test_manifest_lists_passengers_vehicle_by_vehicle(): void
    {
        // 4 + 8 = 12 yolcu: tek araca sığmaz, iki araç da kullanılır.
        $departure = $this->departure([4, 10]);
        $family = $this->group($departure, 3, ['name' => 'Manifesto Ailesi', 'pickup_point' => 'Ardeşen otel önü']);
        $family->passengers()->create($this->passenger(['first_name' => 'Fatma', 'last_name' => 'Manifesto']));
        $this->group($departure, 8, ['name' => 'Kalabalık Grup']);

        app(DepartureAllocator::class)->allocate($departure);

        $this->actingAs($this->admin())->get(route('admin.manifest', $departure))
            ->assertOk()
            ->assertSeeInOrder(['1. Araç', 'Manifesto Ailesi', 'Fatma Manifesto', self::TC[0], '2. Araç', 'Kalabalık Grup'])
            ->assertSee('Ardeşen otel önü')
            ->assertSee('KVKK');
    }
}
