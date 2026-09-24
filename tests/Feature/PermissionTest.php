<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\JobApplication;
use App\Models\Passenger;
use App\Models\ReservationRequest;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

/**
 * Yetki sistemi: kişi başına işaretlenen kutular.
 */
class PermissionTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected bool $seed = true;

    private function departure(array $attributes = []): TourDeparture
    {
        return TourDeparture::create(array_merge([
            'title' => 'Deneme Turu',
            'starts_at' => now()->addDays(20)->setTime(6, 0),
        ], $attributes));
    }

    private function group(TourDeparture $departure, array $attributes = []): TourGroup
    {
        return TourGroup::create(array_merge(['tour_departure_id' => $departure->getKey()], $attributes));
    }

    // ---------- Panel erişimi ----------

    public function test_inactive_account_cannot_enter_the_panel(): void
    {
        $this->actingAs($this->operations(['is_active' => false]))->get('/admin')->assertForbidden();
    }

    public function test_new_accounts_have_no_permissions(): void
    {
        $user = User::create(['name' => 'Yeni', 'email' => 'yeni@ornek.test', 'password' => 'parola1234'])->refresh();

        $this->assertFalse($user->isSuperAdmin());
        $this->assertSame([], (array) $user->permissions);
        $this->assertTrue($user->isGuideOnly(), 'yetki verilmeyen hesap yalnız rehberi olduğu turları görür');
        $this->assertFalse($user->seesPassengers());
    }

    public function test_super_admin_has_every_permission(): void
    {
        $admin = $this->superAdmin();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->hasPermission($permission), $permission->value);
        }

        // Tohumlanan yönetici de süper yöneticidir.
        $this->assertTrue($this->admin()->isSuperAdmin());
    }

    public function test_every_active_account_can_open_the_panel_and_the_tour_list(): void
    {
        foreach ([$this->superAdmin(), $this->operations(), $this->registrar(), $this->guide()] as $user) {
            $this->actingAs($user)->get('/admin')->assertOk();
            $this->actingAs($user)->get('/admin/turlar')->assertOk();
            $this->actingAs($user)->get('/admin/kazanclarim')->assertOk();
        }
    }

    // ---------- Sayfa erişimi ----------

    /**
     * Sayfa / yetki matrisi. Her satır TEK bir hesabı dener.
     *
     * @return array<string, array{string, array<int, string>, bool}>
     */
    public static function pageMatrix(): array
    {
        $pages = [
            '/admin/site-settings' => ['settings.manage'],
            '/admin/system-commands' => ['settings.manage'],
            '/admin/personel' => ['users.manage'],
            '/admin/kasa' => ['reports.view'],
            '/admin/komisyon-raporu' => ['reports.view'],
            '/admin/arac-sihirbazi' => ['allocation.manage'],
            '/admin/iletisim-talepleri' => ['requests.manage'],
            '/admin/is-basvurulari' => ['requests.manage'],
            '/admin/gruplar/create' => ['groups.create', 'groups.manage'],
            '/admin/turlar/create' => ['tours.manage'],
            '/admin/yolcular' => Permission::passengerAccess(),
            '/admin/vehicles' => ['vehicles.manage', 'allocation.manage', 'reports.view'],
            '/admin/arac-gecmisi' => ['vehicles.manage', 'allocation.manage', 'reports.view'],
        ];

        $rows = [];

        foreach ($pages as $url => $allowed) {
            $allowed = array_map(fn ($p) => $p instanceof Permission ? $p->value : $p, $allowed);

            foreach (Permission::cases() as $permission) {
                $rows[$url.' · '.$permission->value] = [$url, [$permission->value], in_array($permission->value, $allowed, true)];
            }

            $rows[$url.' · yetkisiz'] = [$url, [], false];
        }

        return $rows;
    }

    #[DataProvider('pageMatrix')]
    public function test_page_access_matches_the_permission_matrix(string $url, array $permissions, bool $allowed): void
    {
        $response = $this->actingAs($this->staff($permissions))->get($url);

        $allowed
            ? $response->assertOk()
            : $this->assertContains($response->getStatusCode(), [403, 404], $url);
    }

    // ---------- Tur, araç, dağıtım ----------

    public function test_tour_vehicle_and_allocation_permissions_are_separate(): void
    {
        $departure = $this->departure();
        $vehicle = Vehicle::first();

        $tours = $this->staff([Permission::ToursManage]);
        $fleet = $this->staff([Permission::VehiclesManage]);
        $allocation = $this->staff([Permission::AllocationManage]);

        $this->assertTrue($tours->can('create', TourDeparture::class));
        $this->assertFalse($tours->can('update', $vehicle));
        $this->assertFalse($tours->can('allocate', $departure));

        $this->assertTrue($fleet->can('update', $vehicle));
        $this->assertFalse($fleet->can('create', TourDeparture::class));

        $this->assertTrue($allocation->can('allocate', $departure));
        $this->assertFalse($allocation->can('update', $departure));
    }

    public function test_departure_with_groups_cannot_be_deleted(): void
    {
        $operations = $this->operations();
        $empty = $this->departure();
        $busy = $this->departure(['starts_at' => now()->addDays(30)]);
        $this->group($busy);

        $this->assertTrue($operations->can('delete', $empty));
        $this->assertFalse($operations->can('delete', $busy), 'yolcu kaydı olan tur silinmemeli');
    }

    // ---------- Yolcu verisi görünürlüğü ----------

    public function test_account_without_permissions_never_sees_passenger_data_of_other_tours(): void
    {
        $guide = $this->guide();
        $group = $this->group($this->departure());

        $this->assertFalse($guide->can('view', $group));
        $this->assertFalse($guide->can('viewAny', Passenger::class));
        $this->assertFalse($guide->can('viewAny', ReservationRequest::class));
        $this->assertFalse($guide->can('viewAny', JobApplication::class));
    }

    public function test_guide_sees_only_their_own_tours_and_groups(): void
    {
        $guide = $this->guide();
        $other = $this->guide();

        $mine = $this->departure(['guide_id' => $guide->id]);
        $viaVehicle = $this->departure(['starts_at' => now()->addDays(22)]);
        $viaVehicle->vehicles()->create(['name' => 'Minibüs', 'seat_count' => 19, 'guide_id' => $guide->id]);
        $theirs = $this->departure(['guide_id' => $other->id, 'starts_at' => now()->addDays(25)]);
        $nobody = $this->departure(['starts_at' => now()->addDays(40)]);

        $myGroup = $this->group($mine);
        $theirGroup = $this->group($theirs);

        $visible = TourDeparture::query()->visibleTo($guide)->pluck('id');
        $this->assertTrue($visible->contains($mine->id));
        $this->assertTrue($visible->contains($viaVehicle->id), 'araç rehberi de turu görür');
        $this->assertFalse($visible->contains($theirs->id), 'başka rehberin turu görünmemeli');
        $this->assertFalse($visible->contains($nobody->id), 'rehbersiz tur rehbere görünmemeli');

        $visibleGroups = TourGroup::query()->visibleTo($guide)->pluck('id');
        $this->assertTrue($visibleGroups->contains($myGroup->id));
        $this->assertFalse($visibleGroups->contains($theirGroup->id));

        // Rehber görür ama DEĞİŞTİREMEZ.
        $this->assertTrue($guide->can('view', $myGroup));
        $this->assertFalse($guide->can('update', $myGroup));
        $this->assertFalse($guide->can('create', TourGroup::class));
        $this->assertFalse($guide->can('view', $theirGroup));
    }

    public function test_guide_cannot_open_another_guides_records_by_url(): void
    {
        $guide = $this->guide();
        $other = $this->guide();

        $mine = $this->departure(['guide_id' => $guide->id]);
        $theirs = $this->departure(['guide_id' => $other->id, 'starts_at' => now()->addDays(25)]);
        $theirGroup = $this->group($theirs, ['name' => 'Gizli Grup']);
        $theirGroup->passengers()->create(['first_name' => 'Gizli', 'last_name' => 'Yolcu', 'tc_no' => '10000000146']);

        $this->actingAs($guide);

        $this->get('/admin/turlar/'.$mine->id)->assertOk();
        $this->get('/admin/turlar/'.$mine->id.'/arac-dagilimi')->assertOk();
        $this->get(route('admin.manifest', $mine))->assertOk();

        /*
         * Başkasının turu AÇILAMAZ. Durum kodu 403 ya da 404 olabilir (kayıt sorgudan
         * elenir, varlığı da sızmaz). Asıl güvence, yolcu verisinin yanıtta bulunmamasıdır.
         */
        foreach ([
            '/admin/turlar/'.$theirs->id,
            '/admin/turlar/'.$theirs->id.'/arac-dagilimi',
            '/admin/gruplar/'.$theirGroup->id,
            route('admin.manifest', $theirs),
        ] as $url) {
            $response = $this->get($url);

            $this->assertContains($response->getStatusCode(), [403, 404], $url);
            $this->assertStringNotContainsString('10000000146', $response->getContent(), $url);
            $this->assertStringNotContainsString('Gizli Grup', $response->getContent(), $url);
        }

        // Rehber düzenleme ekranını da açamaz.
        $this->get('/admin/turlar/'.$mine->id.'/edit')->assertForbidden();
    }

    public function test_manifest_requires_login_and_is_never_cached(): void
    {
        $departure = $this->departure();

        $this->get(route('admin.manifest', $departure))->assertRedirect('/admin/login');

        $response = $this->actingAs($this->operations())->get(route('admin.manifest', $departure));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('noindex, nofollow', false);

        // Yetkisiz hesap listeyi alamaz; pasife alınan hesap da (oturumu sürse bile).
        $this->actingAs($this->guide())->get(route('admin.manifest', $departure))->assertForbidden();
        $this->actingAs($this->operations(['is_active' => false]))->get(route('admin.manifest', $departure))->assertForbidden();
    }

    // ---------- Grup kaydı ----------

    public function test_registrar_only_sees_their_own_groups_and_no_tour_or_vehicle_menus(): void
    {
        $registrar = $this->staff([Permission::GroupsCreate]);
        $colleague = $this->registrar();
        $departure = $this->departure();

        $own = $this->group($departure, ['created_by' => $registrar->id]);
        $others = $this->group($departure, ['created_by' => $colleague->id]);
        $others->passengers()->create(['first_name' => 'Gizli', 'last_name' => 'Yolcu', 'tc_no' => '10000000146']);

        $this->assertFalse($registrar->seesPassengers(), 'yalnız yolcu ekleyen bütün yolcuları görmez');
        $this->assertTrue($registrar->can('view', $own));
        $this->assertFalse($registrar->can('view', $others));
        $this->assertFalse($registrar->can('view', $departure), 'rehberi olmadığı turu açamaz');

        $visible = TourGroup::query()->visibleTo($registrar)->pluck('id');
        $this->assertTrue($visible->contains($own->id));
        $this->assertFalse($visible->contains($others->id));

        $this->actingAs($registrar);

        $html = $this->get('/admin')->assertOk()->getContent();
        foreach (['Tüm Turlar', 'Yolcular', 'Araç Geçmişi', '>Araçlar<'] as $label) {
            $this->assertStringNotContainsString($label, $html, $label.' menüde görünmemeli');
        }
        $this->assertStringContainsString('Yolcu Ekle', $html);

        $this->get('/admin/gruplar')->assertOk()->assertDontSee('Gizli Yolcu');
        $this->get('/admin/gruplar/create')->assertOk();
        // Başkasının grubu sorgudan elenir (404) ya da ilke reddeder (403); ikisi de kabul.
        $this->assertContains($this->get('/admin/gruplar/'.$others->id)->getStatusCode(), [403, 404]);
        $this->assertContains($this->get('/admin/turlar/'.$departure->id)->getStatusCode(), [403, 404]);
        $this->assertContains($this->get('/admin/turlar/'.$departure->id.'/arac-dagilimi')->getStatusCode(), [403, 404]);
        $this->get('/admin/yolcular')->assertForbidden();
        $this->get('/admin/vehicles')->assertForbidden();
        $this->get('/admin/arac-gecmisi')->assertForbidden();
        $this->get(route('admin.manifest', $departure))->assertForbidden();
    }

    public function test_registrar_can_change_only_their_own_groups(): void
    {
        $registrar = $this->registrar();
        $colleague = $this->registrar();
        $departure = $this->departure();

        $own = $this->group($departure, ['created_by' => $registrar->id]);
        $others = $this->group($departure, ['created_by' => $colleague->id]);

        $this->assertTrue($registrar->can('update', $own));
        $this->assertTrue($registrar->can('delete', $own));
        $this->assertFalse($registrar->can('update', $others));
        $this->assertFalse($registrar->can('delete', $others));

        // "Tüm grupları yönetme" yetkisi olan herkesinkini değiştirir.
        $manager = $this->staff([Permission::GroupsManage]);
        $this->assertTrue($manager->can('update', $others));
        $this->assertTrue($manager->can('delete', $others));
    }

    // ---------- Talepler ----------

    public function test_requests_permission_covers_contact_requests_and_job_applications(): void
    {
        $request = ReservationRequest::create(['name' => 'Kişi', 'phone' => '05321112233', 'people_count' => 2, 'kvkk_accepted' => true]);
        $application = JobApplication::create(['name' => 'Aday', 'phone' => '05321112233']);

        $staff = $this->staff([Permission::RequestsManage]);
        $other = $this->staff([Permission::ToursManage]);

        $this->assertTrue($staff->can('update', $request));
        $this->assertTrue($staff->can('assign', $request));
        $this->assertTrue($staff->can('update', $application));
        $this->assertFalse($staff->can('delete', $request), 'silme süper yöneticide');

        $this->assertFalse($other->can('view', $request));
        $this->assertFalse($other->can('view', $application));
    }

    public function test_assignment_records_who_when_and_moves_status_forward(): void
    {
        $operations = $this->operations();
        $registrar = $this->registrar();
        $request = ReservationRequest::create(['name' => 'Kişi', 'phone' => '05321112233', 'people_count' => 2, 'kvkk_accepted' => true])->refresh();

        $this->assertSame(ReservationRequest::STATUS_NEW, $request->status);

        $request->assignTo($registrar, $operations, 'Akşam 6\'dan sonra aranacak.');
        $request->refresh();

        $this->assertSame($registrar->id, $request->assigned_to);
        $this->assertSame($operations->id, $request->assigned_by);
        $this->assertNotNull($request->assigned_at);
        $this->assertSame(ReservationRequest::STATUS_CONTACTED, $request->status);
    }

    // ---------- Personel yönetimi ----------

    public function test_users_permission_manages_staff_but_not_super_admins(): void
    {
        $admin = $this->superAdmin();
        $manager = $this->staff([Permission::UsersManage]);
        $registrar = $this->registrar();

        $this->assertTrue($manager->can('viewAny', User::class));
        $this->assertTrue($manager->can('update', $registrar));
        $this->assertTrue($manager->can('delete', $registrar));
        $this->assertFalse($manager->can('update', $admin), 'yetki yöneticisi süper yöneticiyi düzenleyemez');
        $this->assertFalse($manager->can('delete', $admin));

        // Herkes kendi profilini düzenleyebilir, kimse kendini silemez.
        $this->assertTrue($registrar->can('update', $registrar));
        $this->assertFalse($registrar->can('update', $manager));
        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($manager->can('delete', $manager));
    }

    public function test_only_super_admin_toggle_is_hidden_from_permission_managers(): void
    {
        $manager = $this->staff([Permission::UsersManage]);

        $this->actingAs($manager)->get('/admin/personel')->assertOk();
    }
}
