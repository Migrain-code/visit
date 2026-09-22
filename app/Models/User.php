<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role',
        'title', 'phone', 'whatsapp', 'photo', 'bio',
        'is_active', 'show_on_site', 'sort_order',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
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

    // ---------- Roller ----------

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOperations(): bool
    {
        return $this->role === UserRole::Operasyon;
    }

    public function isRegistrar(): bool
    {
        return $this->role === UserRole::Kayit;
    }

    public function isEditor(): bool
    {
        return $this->role === UserRole::Icerik;
    }

    public function isGuide(): bool
    {
        return $this->role === UserRole::Rehber;
    }

    /** Site içeriğini (blog, galeri, bölge, sayfa) yönetebilir mi? */
    public function managesContent(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::Icerik], true);
    }

    /** Tur kataloğunu (turlar, kategoriler, fiyatlar) yönetebilir mi? */
    public function managesCatalog(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::Icerik, UserRole::Operasyon], true);
    }

    /** Sefer açabilir, araç atayabilir, grupları araçlara dağıtabilir mi? */
    public function managesOperations(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::Operasyon], true);
    }

    /** Grup ve yolcu kaydı girebilir mi? */
    public function registersGroups(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::Operasyon, UserRole::Kayit], true);
    }

    /**
     * Yolcu verisini (ad, TC, telefon) görebilir mi? İçerik editörü GÖREMEZ.
     * Rehber görür ama yalnız kendi seferlerinde; o sınır sorgu kapsamındadır.
     */
    public function seesPassengers(): bool
    {
        return $this->role !== null && $this->role !== UserRole::Icerik;
    }

    /** Rezervasyon taleplerini personele atayabilir mi? */
    public function assignsRequests(): bool
    {
        return $this->managesOperations();
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

    // ---------- Kapsamlar ----------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGuides(Builder $query): Builder
    {
        return $query->where('role', UserRole::Rehber);
    }

    /** Rezervasyon talebi atanabilecek personel. */
    public function scopeRegistrars(Builder $query): Builder
    {
        return $query->whereIn('role', [UserRole::SuperAdmin, UserRole::Operasyon, UserRole::Kayit]);
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

    public function getRoleLabelAttribute(): string
    {
        return $this->role?->label() ?? '-';
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
