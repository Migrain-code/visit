<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Pages\CashReport;
use App\Filament\Pages\CommissionReport;
use App\Filament\Pages\MyEarnings;
use App\Filament\Resources\TourDepartures\Pages\ViewTourDeparture;
use App\Models\TourCommission;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\TourLedgerEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

/**
 * Komisyon ve kasa: personel kazancı, tur başına gelir-gider, raporlar.
 */
class FinanceTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected bool $seed = true;

    private function tour(array $attributes = []): TourDeparture
    {
        return TourDeparture::create(array_merge([
            'title' => 'Batum',
            'starts_at' => now()->addDays(10)->setTime(7, 0),
            'price' => 1000,
        ], $attributes));
    }

    private function passengers(TourDeparture $tour, int $count): TourGroup
    {
        $group = TourGroup::create(['tour_departure_id' => $tour->getKey()]);

        for ($i = 0; $i < $count; $i++) {
            $group->passengers()->create(['first_name' => 'Yolcu', 'last_name' => (string) $i]);
        }

        return $group->refresh();
    }

    // ---------- Komisyon ----------

    public function test_commission_action_writes_amounts_per_staff_and_apply_all_fills_everyone(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tour = $this->tour();
        $ali = $this->staff([], ['name' => 'Ali Rehber']);
        $veli = $this->staff([], ['name' => 'Veli Şoför']);
        $ayse = $this->staff([], ['name' => 'Ayşe Organizasyon']);

        // Tümüne 500, Ali'ye 750, Ayşe boş (komisyon yok).
        Livewire::test(ViewTourDeparture::class, ['record' => $tour->id])
            ->callAction('commission', data: [
                'amounts' => [$ali->id => 750, $veli->id => 500, $ayse->id => null],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame([$ali->id => 750.0, $veli->id => 500.0], $tour->commissions()->pluck('amount', 'user_id')->map(fn ($a) => (float) $a)->all());
        $this->assertSame(1250.0, $tour->fresh()->commission_total);

        // Tekrar açıp Veli'yi boşaltmak komisyonunu siler; Ali'ninki güncellenir.
        Livewire::test(ViewTourDeparture::class, ['record' => $tour->id])
            ->callAction('commission', data: ['amounts' => [$ali->id => 800, $veli->id => '', $ayse->id => 300]])
            ->assertHasNoActionErrors();

        $this->assertSame([$ali->id => 800.0, $ayse->id => 300.0], $tour->commissions()->orderBy('user_id')->pluck('amount', 'user_id')->map(fn ($a) => (float) $a)->all());
    }

    public function test_only_the_commission_permission_can_write_commissions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $tour = $this->tour();
        $staff = $this->staff([]);

        $this->actingAs($this->operations());
        Livewire::test(ViewTourDeparture::class, ['record' => $tour->id])->assertActionHidden('commission');

        $this->actingAs($this->staff([Permission::CommissionsManage, Permission::ToursManage]));
        Livewire::test(ViewTourDeparture::class, ['record' => $tour->id])
            ->assertActionVisible('commission')
            ->callAction('commission', data: ['amounts' => [$staff->id => 400]]);

        $this->assertSame(400.0, (float) $tour->commissions()->where('user_id', $staff->id)->value('amount'));
    }

    public function test_staff_sees_only_their_own_earnings(): void
    {
        $me = $this->guide(['name' => 'Ben']);
        $other = $this->guide(['name' => 'Başkası']);

        $a = $this->tour(['title' => 'Batum', 'starts_at' => now()->subDays(3)]);
        $b = $this->tour(['title' => 'Uzungöl', 'starts_at' => now()->addDays(4)]);

        TourCommission::create(['tour_departure_id' => $a->id, 'user_id' => $me->id, 'amount' => 500]);
        TourCommission::create(['tour_departure_id' => $b->id, 'user_id' => $me->id, 'amount' => 350]);
        TourCommission::create(['tour_departure_id' => $a->id, 'user_id' => $other->id, 'amount' => 999]);

        $this->actingAs($me);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = $this->get('/admin/kazanclarim')->assertOk()->getContent();

        $this->assertStringContainsString('850 ₺', $html);
        $this->assertStringContainsString('Batum', $html);
        $this->assertStringContainsString('Uzungöl', $html);
        $this->assertStringNotContainsString('999', $html, 'başkasının komisyonu görünmemeli');

        Livewire::test(MyEarnings::class)->assertCanSeeTableRecords($me->commissions)->assertCanNotSeeTableRecords($other->commissions);

        // Panoda da kendi kazancı görünür.
        $this->get('/admin')->assertOk()->assertSee('Kazancım')->assertSee('850 ₺');
    }

    public function test_commission_report_sums_by_tour_and_staff_with_filters(): void
    {
        $ali = $this->guide(['name' => 'Ali Rehber']);
        $veli = $this->guide(['name' => 'Veli Şoför']);
        $batum = $this->tour(['title' => 'Batum', 'starts_at' => now()->subDays(20)]);
        $uzungol = $this->tour(['title' => 'Uzungöl', 'starts_at' => now()->subDays(2)]);

        TourCommission::create(['tour_departure_id' => $batum->id, 'user_id' => $ali->id, 'amount' => 500]);
        TourCommission::create(['tour_departure_id' => $batum->id, 'user_id' => $veli->id, 'amount' => 700]);
        TourCommission::create(['tour_departure_id' => $uzungol->id, 'user_id' => $ali->id, 'amount' => 400]);

        $this->actingAs($this->staff([Permission::ReportsView]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = $this->get('/admin/komisyon-raporu')->assertOk()->getContent();
        $this->assertStringContainsString('1.600 ₺', $html);
        $this->assertStringContainsString('Ali Rehber', $html);

        Livewire::test(CommissionReport::class)
            ->filterTable('user_id', $ali->id)
            ->assertCanSeeTableRecords(TourCommission::where('user_id', $ali->id)->get())
            ->assertCanNotSeeTableRecords(TourCommission::where('user_id', $veli->id)->get())
            ->assertSee('900 ₺');

        Livewire::test(CommissionReport::class)
            ->filterTable('dates', ['from' => now()->subDays(5)->toDateString(), 'until' => null])
            ->assertCanSeeTableRecords(TourCommission::where('tour_departure_id', $uzungol->id)->get())
            ->assertCanNotSeeTableRecords(TourCommission::where('tour_departure_id', $batum->id)->get());
    }

    // ---------- Kasa ----------

    public function test_tour_finance_adds_up_revenue_vehicles_commissions_and_extras(): void
    {
        $tour = $this->tour(['price' => 1000]);
        $this->passengers($tour, 20);
        $cancelled = $this->passengers($tour, 5);
        $cancelled->update(['status' => 'cancelled']);

        $tour->vehicles()->create(['name' => 'Otobüs', 'seat_count' => 30, 'cost' => 9000]);
        $tour->vehicles()->create(['name' => 'Minibüs', 'seat_count' => 19, 'cost' => 4000]);
        TourCommission::create(['tour_departure_id' => $tour->id, 'user_id' => $this->guide()->id, 'amount' => 1500]);
        TourLedgerEntry::create(['tour_departure_id' => $tour->id, 'type' => 'income', 'title' => 'Sponsor', 'amount' => 2000]);
        TourLedgerEntry::create(['tour_departure_id' => $tour->id, 'type' => 'expense', 'title' => 'Öğle yemeği', 'amount' => 3000]);

        $fresh = TourDeparture::query()->withFinanceStats()->findOrFail($tour->id);

        $this->assertSame(20000.0, $fresh->passenger_revenue, 'iptal edilen grup gelire girmez');
        $this->assertSame(2000.0, $fresh->extra_income);
        $this->assertSame(22000.0, $fresh->total_income);
        $this->assertSame(13000.0, $fresh->vehicle_cost);
        $this->assertSame(1500.0, $fresh->commission_total);
        $this->assertSame(3000.0, $fresh->extra_expense);
        $this->assertSame(17500.0, $fresh->total_expense);
        $this->assertSame(4500.0, $fresh->net);

        // Alt sorgusuz erişim de aynı sonucu verir.
        $this->assertSame(4500.0, $tour->fresh()->net);
    }

    public function test_ledger_action_adds_extra_income_and_expense(): void
    {
        $this->actingAs($this->staff([Permission::ReportsView, Permission::ToursManage]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tour = $this->tour();

        Livewire::test(ViewTourDeparture::class, ['record' => $tour->id])
            ->callAction('ledger', data: ['type' => 'expense', 'title' => 'Müze girişi', 'amount' => 1250.5, 'entry_date' => now()->toDateString()])
            ->assertHasNoActionErrors()
            ->callAction('ledger', data: ['type' => 'income', 'title' => 'Sponsor', 'amount' => 500])
            ->assertHasNoActionErrors();

        $this->assertSame(1250.5, $tour->fresh()->extra_expense);
        $this->assertSame(500.0, $tour->fresh()->extra_income);
        $this->assertNotNull(TourLedgerEntry::where('title', 'Sponsor')->value('entry_date'), 'tarih boşsa bugün yazılır');

        // Rapor yetkisi olmayan kasa hareketi göremez / ekleyemez.
        $this->actingAs($this->operations());
        Livewire::test(ViewTourDeparture::class, ['record' => $tour->id])->assertActionHidden('ledger');
    }

    public function test_cash_report_lists_tours_with_totals_and_filters(): void
    {
        $profitable = $this->tour(['title' => 'Kârlı Tur', 'price' => 1000, 'starts_at' => now()->subDays(10)]);
        $this->passengers($profitable, 30);
        $profitable->vehicles()->create(['name' => 'Otobüs', 'seat_count' => 46, 'cost' => 10000]);

        $loss = $this->tour(['title' => 'Zararlı Tur', 'price' => 500, 'starts_at' => now()->subDays(3)]);
        $this->passengers($loss, 4);
        $loss->vehicles()->create(['name' => 'Minibüs', 'seat_count' => 19, 'cost' => 5000]);
        $staff = $this->guide();
        TourCommission::create(['tour_departure_id' => $loss->id, 'user_id' => $staff->id, 'amount' => 300]);

        $this->actingAs($this->staff([Permission::ReportsView]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = $this->get('/admin/kasa')->assertOk()->getContent();
        $this->assertStringContainsString('Kârlı Tur', $html);
        $this->assertStringContainsString('20.000 ₺', $html);   // 30 × 1000 − 10000
        $this->assertStringContainsString('-3.300 ₺', $html);   // 4 × 500 − 5000 − 300

        Livewire::test(CashReport::class)
            ->assertCanSeeTableRecords([$profitable, $loss])
            ->filterTable('staff', $staff->id)
            ->assertCanSeeTableRecords([$loss])
            ->assertCanNotSeeTableRecords([$profitable]);

        Livewire::test(CashReport::class)
            ->filterTable('net', ['min' => 0, 'max' => null])
            ->assertCanSeeTableRecords([$profitable])
            ->assertCanNotSeeTableRecords([$loss]);

        Livewire::test(CashReport::class)
            ->filterTable('dates', ['from' => now()->subDays(5)->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$loss])
            ->assertCanNotSeeTableRecords([$profitable]);

        Livewire::test(CashReport::class)
            ->sortTable('net', 'desc')
            ->assertCanSeeTableRecords([$profitable, $loss], inOrder: true);
    }

    public function test_reports_need_the_reports_permission(): void
    {
        $this->actingAs($this->operations());

        $this->get('/admin/kasa')->assertForbidden();
        $this->get('/admin/komisyon-raporu')->assertForbidden();

        // Tur özetindeki kasa kutusu da gizlidir.
        $tour = $this->tour();
        $this->get('/admin/turlar/'.$tour->id)->assertOk()->assertDontSee('Kasaya kalan');

        $this->actingAs($this->staff([Permission::ReportsView, Permission::ToursManage]));
        $this->get('/admin/turlar/'.$tour->id)->assertOk()->assertSee('Kasaya kalan');
    }
}
