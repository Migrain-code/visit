<?php

namespace App\Enums;

/**
 * Personel yetkileri.
 *
 * Sabit roller yerine kişi başına işaretlenen yetkiler kullanılır: bir personele
 * "yolcu ekleyebilsin ama araç atayamasın" demek tek kutuyla mümkündür.
 * Süper yönetici (users.is_super_admin) her şeyi yapar ve bu listeye bakılmaz.
 *
 * Yetkilerin nerede uygulandığı: App\Policies ve sayfaların canAccess() yöntemleri.
 */
enum Permission: string
{
    case ToursManage = 'tours.manage';
    case VehiclesManage = 'vehicles.manage';
    case AllocationManage = 'allocation.manage';
    case GroupsCreate = 'groups.create';
    case GroupsManage = 'groups.manage';
    case RequestsManage = 'requests.manage';
    case CommissionsManage = 'commissions.manage';
    case ReportsView = 'reports.view';
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::ToursManage => 'Tur ekleme ve düzenleme',
            self::VehiclesManage => 'Araç filosunu yönetme',
            self::AllocationManage => 'Araç atama ve grupları yerleştirme',
            self::GroupsCreate => 'Yolcu (grup) ekleme',
            self::GroupsManage => 'Tüm grupları düzenleme ve silme',
            self::RequestsManage => 'İletişim talepleri ve iş başvuruları',
            self::CommissionsManage => 'Komisyon tanımlama',
            self::ReportsView => 'Kasa ve komisyon raporları',
            self::UsersManage => 'Personel ve yetki yönetimi',
            self::SettingsManage => 'Site ayarları ve sistem komutları',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ToursManage => 'Tüm Turlar\'da yeni tur açar, tarih/fiyat/görsel değiştirir, sıralar ve siler.',
            self::VehiclesManage => 'Araçlar sayfasında filoya araç ekler, plaka ve koltuk bilgisini değiştirir.',
            self::AllocationManage => 'Araç Liste Sihirbazı ve Araç Dağılımı: tura araç atar, grupları araçlara yerleştirir, taşır.',
            self::GroupsCreate => 'Yolcu Ekle ekranını kullanır; yalnız kendi girdiği grupları düzenler ve siler.',
            self::GroupsManage => 'Herkesin girdiği grupları düzenler, siler; yolcu listesini yazdırır.',
            self::RequestsManage => 'Siteden gelen iletişim taleplerini ve iş başvurularını görür, işler, personele atar.',
            self::CommissionsManage => 'Tur sayfasında "Komisyon ekle" ile personele komisyon yazar.',
            self::ReportsView => 'Kasa ve Komisyon Raporu sayfalarını görür (tüm personelin kazancı dahil).',
            self::UsersManage => 'Personel ekler, yetkilerini değiştirir, hesabı kapatır.',
            self::SettingsManage => 'Site Ayarları ve Sistem Komutları sayfalarına girer.',
        };
    }

    /** Yolcu verisini (ad, TC, telefon) görmeye yeten yetkiler. */
    public static function passengerAccess(): array
    {
        return [self::ToursManage, self::AllocationManage, self::GroupsCreate, self::GroupsManage, self::ReportsView];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->description()])->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
