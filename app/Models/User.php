<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Personel hesabı.
 *
 * Yetki, kişi başına işaretlenen bir listedir (permissions); süper yönetici her şeyi
 * yapar. Yetkisi olmayan bir hesap panele girer, yalnız rehberi olduğu turları ve
 * kendi kazancını görür.
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'is_super_admin', 'permissions',
        'title', 'phone', 'whatsapp', 'photo', 'bio',
        'is_active', 'show_on_site', 'sort_order',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_super_admin' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'show_on_site' => 'boolean',
        ];
    }

    /** Pasife alınan personel panele giremez. */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? media_url($this->photo) : null;
    }

    // ---------- Yetkiler ----------

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, (array) $this->permissions, true);
    }

    /** @param  array<int, Permission|string>  $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function managesTours(): bool
    {
        return $this->hasPermission(Permission::ToursManage);
    }

    public function managesVehicles(): bool
    {
        return $this->hasPermission(Permission::VehiclesManage);
    }

    /** Tura araç atar, grupları araçlara dağıtır, taşır. */
    public function managesOperations(): bool
    {
        return $this->hasPermission(Permission::AllocationManage);
    }

    /** Grup ve yolcu kaydı girebilir mi? */
    public function registersGroups(): bool
    {
        return $this->hasAnyPermission([Permission::GroupsCreate, Permission::GroupsManage]);
    }

    /** Herkesin girdiği grupları düzenleyip silebilir mi? */
    public function managesGroups(): bool
    {
        return $this->hasPermission(Permission::GroupsManage);
    }

    /** Yolcu verisini (ad, TC, telefon) görebilir mi? Rehber yalnız kendi turlarında görür (sorgu kapsamı). */
    public function seesPassengers(): bool
    {
        return $this->hasAnyPermission(Permission::passengerAccess());
    }

    public function managesRequests(): bool
    {
        return $this->hasPermission(Permission::RequestsManage);
    }

    /** Talepleri personele atayabilir mi? */
    public function assignsRequests(): bool
    {
        return $this->managesRequests();
    }

    public function managesCommissions(): bool
    {
        return $this->hasPermission(Permission::CommissionsManage);
    }

    public function viewsReports(): bool
    {
        return $this->hasPermission(Permission::ReportsView);
    }

    public function managesUsers(): bool
    {
        return $this->hasPermission(Permission::UsersManage);
    }

    public function managesSettings(): bool
    {
        return $this->hasPermission(Permission::SettingsManage);
    }

    /** Hiç yetkisi olmayan hesap: yalnız rehberi olduğu turları görür. */
    public function isGuideOnly(): bool
    {
        return ! $this->isSuperAdmin() && ! $this->seesPassengers();
    }

    /** Turun rehberi mi (turun kendisinde ya da araçlarından birinde)? */
    public function isGuideOf(TourDeparture $departure): bool
    {
        if ((int) $departure->guide_id === (int) $this->getKey()) {
            return true;
        }

        return $departure->vehicles()->where('guide_id', $this->getKey())->exists();
    }

    // ---------- İlişkiler ----------

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class, 'assigned_to');
    }

    public function guidedDepartures(): HasMany
    {
        return $this->hasMany(TourDeparture::class, 'guide_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(TourCommission::class);
    }

    // ---------- Kapsamlar ----------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Web sitesinde gösterilecek personel. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('show_on_site', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ---------- Görünüm yardımcıları ----------

    /** "Süper yönetici" / "3 yetki" / "Yetki yok" */
    public function getRoleLabelAttribute(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Süper yönetici';
        }

        $count = count((array) $this->permissions);

        return $count === 0 ? 'Yetki yok' : $count.' yetki';
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? media_url($this->photo) : null;
    }

    /** Baş harfler — fotoğraf yoksa avatar yerine. */
    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/u', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1), 'UTF-8'))
            ->implode('');
    }

    public function getWhatsappNumberAttribute(): ?string
    {
        $value = $this->whatsapp ?: $this->phone;

        return $value ? ltrim(phone_digits($value), '+') : null;
    }
}
