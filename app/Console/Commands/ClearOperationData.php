<?php

namespace App\Console\Commands;

use App\Models\DepartureVehicle;
use App\Models\Passenger;
use App\Models\TourCommission;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\TourLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test verilerini temizler: turlara bağlanan HER kayıt silinir, turların kendisi kalır.
 *
 * Silinen: yolcular, gruplar, tura atanan araçlar (ücret, şoför, rehber), komisyonlar,
 * kasa hareketleri; turların son dağıtım zamanı sıfırlanır.
 * Kalan: turlar, personel ve yetkileri, araç filosu, site ayarları, iletişim talepleri,
 * iş başvuruları.
 *
 * Geri alınamaz. Panelden onay kutusuyla, terminalden --force ile çalışır.
 */
class ClearOperationData extends Command
{
    protected $signature = 'data:clear-operations {--force : Onay sormadan çalıştır}';

    protected $description = 'Test verilerini temizler: yolcular, gruplar, tura atanan araçlar, komisyonlar ve kasa hareketleri silinir; turlar ve personel kalır';

    public function handle(): int
    {
        $counts = [
            'yolcu' => Passenger::query()->count(),
            'grup' => TourGroup::query()->count(),
            'tura atanan araç' => DepartureVehicle::query()->count(),
            'komisyon' => TourCommission::query()->count(),
            'kasa hareketi' => TourLedgerEntry::query()->count(),
        ];

        if (! $this->option('force') && ! $this->confirm('Bu kayıtlar KALICI olarak silinecek: '.$this->summary($counts).'. Devam edilsin mi?')) {
            $this->warn('İptal edildi.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            // Sıra önemli: gruplar araçlara, yolcular gruplara bağlı.
            Passenger::query()->delete();
            TourGroup::query()->delete();
            DepartureVehicle::query()->delete();
            TourCommission::query()->delete();
            TourLedgerEntry::query()->delete();
            TourDeparture::query()->update(['allocated_at' => null]);
        });

        $this->info('Temizlendi: '.$this->summary($counts).'.');
        $this->line('Kalanlar: '.TourDeparture::query()->count().' tur, personel, araç filosu, site ayarları, iletişim talepleri ve iş başvuruları.');

        return self::SUCCESS;
    }

    /** @param  array<string, int>  $counts */
    private function summary(array $counts): string
    {
        return collect($counts)->map(fn (int $n, string $label) => $n.' '.$label)->implode(', ');
    }
}
