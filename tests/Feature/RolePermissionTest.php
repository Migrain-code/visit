<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\Passenger;
use App\Models\ReservationRequest;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $role->label(),
            'email' => $role->value.'@ornek.test',
            'password' => 'parola1234',
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function departure(array $attributes = []): TourDeparture
    {
        return TourDeparture::create(array_merge([
            'tour_id' => Tour::query()->firstOrFail()->getKey(),
            'starts_at' => now()->addDays(20)->setTime(6, 0),
        ], $attributes));
    }

    private function group(TourDeparture $departure, array $attributes = []): TourGroup
    {
        return TourGroup::create(array_merge([
            'tour_departure_id' => $departure->getKey(),
            'contact_name' => 'Deneme Grup',
            'contact_phone' => '05321112233',
        ], $attributes));
    }

    // ---------- Panel erişimi ----------

    public function test_inactive_account_cannot_enter_the_panel(): void
    {
        $user = $this->user(UserRole::Operasyon, ['is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_new_accounts_default_to_the_narrowest_role(): void
    {
        $user = User::create(['name' => 'Rolü Unutulan', 'email' => 'rolsuz@ornek.test', 'password' => 'parola1234'])->refresh();

        // Rolü atanmayı unutulan hesap yolcu verisini, içeriği ve ayarları göremez.
        $this->assertSame(UserRole::Rehber, $user->role);
    }

    public static function allRoles(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->label() => [$r]])->all();
    }

    #[DataProvider('allRoles')]
    public function test_every_active_role_can_open_the_panel(UserRole $role): void
    {
        $this->actingAs($this->user($role))->get('/admin')->assertOk();
    }

    // ---------- Sayfa erişimi ----------

    /**
     * Sayfa / rol erişim matrisi.
     *
     * Her satır TEK bir rolü dener. Aynı test içinde kullanıcı değiştirmek,
     * Filament'in oturum durumunu taşıdığı için yanıltıcı sonuç verir.
     *
     * @return array<string, array{string, UserRole, bool}>
     */
    public static function pageMatrix(): array
    {
        $pages = [
            // Ayarlar ve kullanıcılar: yalnız süper yönetici
            '/admin/site-settings' => ['super_admin'],
            '/admin/seo-ai-settings' => ['super_admin'],
            '/admin/system-commands' => ['super_admin'],
            '/admin/users' => ['super_admin'],
            '/admin/duplicate-cleaner' => ['super_admin'],
            // SEO: içerik editörü görür
            '/admin/seo-dashboard' => ['super_admin', 'icerik'],
            '/admin/seo-keywords' => ['super_admin', 'icerik'],
            '/admin/analytics' => ['super_admin', 'icerik', 'operasyon'],
            // Site içeriği: kayıt personeli ve rehber girmez
            '/admin/pages' => ['super_admin', 'icerik', 'operasyon'],
            '/admin/provinces' => ['super_admin', 'icerik', 'operasyon'],
            // Tur kataloğu: kayıt personeli de görür (müşteriye bilgi verir)
            '/admin/tours' => ['super_admin', 'icerik', 'operasyon', 'kayit'],
            '/admin/tour-categories' => ['super_admin', 'icerik', 'operasyon', 'kayit'],
            // YOLCU VERİSİ: içerik editörü GÖREMEZ
            '/admin/tur-kayitlari' => ['super_admin', 'operasyon', 'kayit', 'rehber'],
            '/admin/gruplar' => ['super_admin', 'operasyon', 'kayit', 'rehber'],
            '/admin/yolcular' => ['super_admin', 'operasyon', 'kayit', 'rehber'],
            '/admin/vehicles' => ['super_admin', 'operasyon', 'kayit'],
            '/admin/rezervasyon-talepleri' => ['super_admin', 'operasyon', 'kayit'],
            // Kayıt girme ekranı
            '/admin/gruplar/create' => ['super_admin', 'operasyon', 'kayit'],
            '/admin/tur-kayitlari/create' => ['super_admin', 'operasyon'],
        ];

        $rows = [];

        foreach ($pages as $url => $allowedRoles) {
            foreach (UserRole::cases() as $role) {
                $allowed = in_array($role->value, $allowedRoles, true);
                $rows[$url.' · '.$role->label()] = [$url, $role, $allowed];
            }
        }

        return $rows;
    }

    #[DataProvider('pageMatrix')]
    public function test_page_access_matches_the_role_matrix(string $url, UserRole $role, bool $allowed): void
    {
        $response = $this->actingAs($this->user($role))->get($url);

        $allowed
            ? $response->assertOk()
            : $response->assertForbidden();
    }

    // ---------- İçerik ve katalog yetkileri ----------

    public function test_content_and_catalog_permissions(): void
    {
        $tour = Tour::first();
        $page = Page::first();

        // Katalog: fiyat ve program operasyonel bilgidir; operasyon da yönetir.
        $this->assertTrue($this->user(UserRole::SuperAdmin)->can('update', $tour));
        $this->assertTrue($this->user(UserRole::Icerik)->can('update', $tour));
        $this->assertTrue($this->user(UserRole::Operasyon)->can('update', $tour));
        $this->assertFalse($this->user(UserRole::Kayit)->can('update', $tour));
        $this->assertTrue($this->user(UserRole::Kayit, ['email' => 'k2@ornek.test'])->can('view', $tour));
        $this->assertFalse($this->user(UserRole::Rehber)->can('view', $tour));

        // Site içeriği: yalnız süper yönetici ve içerik editörü değiştirir.
        $this->assertTrue(User::where('email', 'icerik@ornek.test')->first()->can('update', $page));
        $this->assertFalse(User::where('email', 'operasyon@ornek.test')->first()->can('update', $page));
    }

    public function test_only_operations_manage_vehicles_and_departures(): void
    {
        $departure = $this->departure();
        $vehicle = Vehicle::first();

        foreach ([UserRole::SuperAdmin, UserRole::Operasyon] as $role) {
            $user = $this->user($role);
            $this->assertTrue($user->can('update', $vehicle), $role->label());
            $this->assertTrue($user->can('create', TourDeparture::class), $role->label());
            $this->assertTrue($user->can('allocate', $departure), $role->label());
        }

        foreach ([UserRole::Kayit, UserRole::Icerik, UserRole::Rehber] as $role) {
            $user = $this->user($role);
            $this->assertFalse($user->can('update', $vehicle), $role->label());
            $this->assertFalse($user->can('create', TourDeparture::class), $role->label());
            $this->assertFalse($user->can('allocate', $departure), $role->label());
        }
    }

    public function test_departure_with_groups_cannot_be_deleted(): void
    {
        $operations = $this->user(UserRole::Operasyon);
        $empty = $this->departure();
        $busy = $this->departure(['starts_at' => now()->addDays(30)]);
        $this->group($busy);

        $this->assertTrue($operations->can('delete', $empty));
        $this->assertFalse($operations->can('delete', $busy), 'yolcu kaydı olan sefer silinmemeli');
    }

    // ---------- Yolcu verisi görünürlüğü ----------

    public function test_content_editor_never_sees_passenger_data(): void
    {
        $editor = $this->user(UserRole::Icerik);
        $group = $this->group($this->departure());

        $this->assertFalse($editor->can('viewAny', TourGroup::class));
        $this->assertFalse($editor->can('view', $group));
        $this->assertFalse($editor->can('viewAny', Passenger::class));
        $this->assertFalse($editor->can('viewAny', TourDeparture::class));
        $this->assertFalse($editor->can('viewAny', ReservationRequest::class));
    }

    public function test_guide_sees_only_their_own_departures_and_groups(): void
    {
        $guide = $this->user(UserRole::Rehber);
        $other = $this->user(UserRole::Rehber, ['email' => 'rehber2@ornek.test']);

        $mine = $this->departure(['guide_id' => $guide->id]);
        $theirs = $this->departure(['guide_id' => $other->id, 'starts_at' => now()->addDays(25)]);
        $nobody = $this->departure(['starts_at' => now()->addDays(40)]);

        $myGroup = $this->group($mine);
        $theirGroup = $this->group($theirs, ['contact_phone' => '05321112299']);

        $visible = TourDeparture::query()->visibleTo($guide)->pluck('id');
        $this->assertTrue($visible->contains($mine->id));
        $this->assertFalse($visible->contains($theirs->id), 'başka rehberin seferi görünmemeli');
        $this->assertFalse($visible->contains($nobody->id), 'rehbersiz sefer rehbere görünmemeli');

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
        $guide = $this->user(UserRole::Rehber);
        $other = $this->user(UserRole::Rehber, ['email' => 'rehber2@ornek.test']);

        $mine = $this->departure(['guide_id' => $guide->id]);
        $theirs = $this->departure(['guide_id' => $other->id, 'starts_at' => now()->addDays(25)]);
        $theirGroup = $this->group($theirs, ['contact_name' => 'Gizli Aile', 'contact_phone' => '05321112299']);
        $theirGroup->passengers()->create(['first_name' => 'Gizli', 'last_name' => 'Yolcu', 'tc_no' => '10000000146']);

        $this->actingAs($guide);

        $this->get('/admin/tur-kayitlari/'.$mine->id)->assertOk();
        $this->get('/admin/tur-kayitlari/'.$mine->id.'/arac-dagilimi')->assertOk();
        $this->get(route('admin.manifest', $mine))->assertOk();

        /*
         * Başkasının seferi AÇILAMAZ. Durum kodu 403 ya da 404 olabilir (kayıt sorgudan
         * elenir, varlığı da sızmaz). Asıl güvence, yolcu verisinin yanıtta bulunmamasıdır.
         */
        foreach ([
            '/admin/tur-kayitlari/'.$theirs->id,
            '/admin/tur-kayitlari/'.$theirs->id.'/arac-dagilimi',
            '/admin/gruplar/'.$theirGroup->id,
            route('admin.manifest', $theirs),
        ] as $url) {
            $response = $this->get($url);

            $this->assertContains($response->getStatusCode(), [403, 404], $url);
            $this->assertStringNotContainsString('10000000146', $response->getContent(), $url);
            $this->assertStringNotContainsString('Gizli Aile', $response->getContent(), $url);
        }

        // Rehber düzenleme ekranını da açamaz.
        $this->get('/admin/tur-kayitlari/'.$mine->id.'/edit')->assertForbidden();
    }

    public function test_manifest_requires_login_and_is_never_cached(): void
    {
        $departure = $this->departure();

        $this->get(route('admin.manifest', $departure))->assertRedirect('/admin/login');

        $response = $this->actingAs($this->user(UserRole::Operasyon))->get(route('admin.manifest', $departure));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('noindex, nofollow', false);

        // İçerik editörü yolcu listesini alamaz; pasife alınan hesap da (oturumu sürse bile).
        $this->actingAs($this->user(UserRole::Icerik))->get(route('admin.manifest', $departure))->assertForbidden();
        $this->actingAs($this->user(UserRole::Operasyon, ['email' => 'pasif@ornek.test', 'is_active' => false]))
            ->get(route('admin.manifest', $departure))->assertForbidden();
    }

    // ---------- Grup kaydı ----------

    public function test_registrar_can_delete_only_their_own_groups(): void
    {
        $registrar = $this->user(UserRole::Kayit);
        $colleague = $this->user(UserRole::Kayit, ['email' => 'kayit2@ornek.test']);
        $departure = $this->departure();

        $own = $this->group($departure, ['created_by' => $registrar->id]);
        $others = $this->group($departure, ['created_by' => $colleague->id, 'contact_phone' => '05321112299']);

        // Meslektaşı izinliyken müşterinin kaydını GÜNCELLEYEBİLİR ama SİLEMEZ.
        $this->assertTrue($registrar->can('update', $others));
        $this->assertTrue($registrar->can('delete', $own));
        $this->assertFalse($registrar->can('delete', $others));
        $this->assertTrue($this->user(UserRole::Operasyon)->can('delete', $others));
    }

    // ---------- Rezervasyon talepleri ----------

    public function test_only_operations_can_assign_requests(): void
    {
        $request = ReservationRequest::create(['name' => 'Müşteri', 'phone' => '05321112233', 'people_count' => 2, 'kvkk_accepted' => true]);

        $this->assertTrue($this->user(UserRole::SuperAdmin)->can('assign', $request));
        $this->assertTrue($this->user(UserRole::Operasyon)->can('assign', $request));
        $this->assertFalse($this->user(UserRole::Kayit)->can('assign', $request));
        $this->assertFalse($this->user(UserRole::Icerik)->can('view', $request));
    }

    public function test_assignment_records_who_when_and_moves_status_forward(): void
    {
        $operations = $this->user(UserRole::Operasyon);
        $registrar = $this->user(UserRole::Kayit);
        $request = ReservationRequest::create(['name' => 'Müşteri', 'phone' => '05321112233', 'people_count' => 2, 'kvkk_accepted' => true])->refresh();

        $this->assertSame(ReservationRequest::STATUS_NEW, $request->status);

        $request->assignTo($registrar, $operations, 'Akşam 6\'dan sonra aranacak.');
        $request->refresh();

        $this->assertSame($registrar->id, $request->assigned_to);
        $this->assertSame($operations->id, $request->assigned_by);
        $this->assertNotNull($request->assigned_at);
        // Atanan bir talep artık "yeni" değildir.
        $this->assertSame(ReservationRequest::STATUS_CONTACTED, $request->status);
    }

    public function test_reassignment_keeps_a_finished_status(): void
    {
        $request = ReservationRequest::create([
            'name' => 'Müşteri', 'phone' => '05321112233', 'people_count' => 2, 'kvkk_accepted' => true,
            'status' => ReservationRequest::STATUS_RESERVED,
        ]);

        $request->assignTo($this->user(UserRole::Kayit), $this->user(UserRole::Operasyon));

        $this->assertSame(ReservationRequest::STATUS_RESERVED, $request->refresh()->status, 'kayda dönüşmüş talep geri alınmamalı');
    }

    // ---------- Kullanıcı yönetimi ----------

    public function test_only_super_admin_manages_users(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $operations = $this->user(UserRole::Operasyon);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($operations->can('viewAny', User::class));

        // Herkes kendi profilini düzenleyebilir.
        $this->assertTrue($operations->can('update', $operations));
        $this->assertFalse($operations->can('update', $admin));
    }

    public function test_nobody_can_delete_themselves(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $other = $this->user(UserRole::Kayit);

        $this->assertFalse($admin->can('delete', $admin), 'kendini silmek panelde yönetici bırakmayabilir');
        $this->assertTrue($admin->can('delete', $other));
    }
}
