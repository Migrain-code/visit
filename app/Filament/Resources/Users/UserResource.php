<?php

namespace App\Filament\Resources\Users;

use App\Enums\Permission;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\FormHelpers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Personel ve yetkileri. Yetki, kişi başına işaretlenen kutulardır.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Personel';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Personel';

    protected static ?string $pluralModelLabel = 'Personel';

    protected static ?string $slug = 'personel';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Hesap')
                ->description('Panele giriş bilgileri.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->label('Ad Soyad')->required()->maxLength(100),
                        TextInput::make('email')->label('E-posta')->email()->required()
                            ->unique(ignoreRecord: true)->maxLength(150),
                        TextInput::make('password')
                            ->label('Parola')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Düzenlerken boş bırakılırsa parola değişmez.'),
                        TextInput::make('phone')->label('Telefon')->tel()->maxLength(30)->placeholder('0532 000 00 00'),
                    ]),
                    Toggle::make('is_active')->label('Hesap aktif')->default(true)
                        ->helperText('Kapatılırsa panele giriş yapamaz. Kayıtları, komisyonları ve geçmiş atamaları korunur.'),
                ])->columnSpanFull(),

            Section::make('Yetkiler')
                ->description('Hiç yetki verilmeyen personel yalnız rehberi olduğu turları ve kendi kazancını görür.')
                ->schema([
                    Toggle::make('is_super_admin')
                        ->label('Süper yönetici (her şeyi yapar)')
                        ->live()
                        ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
                        ->helperText('Yalnız süper yönetici bu kutuyu değiştirebilir.'),
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options(Permission::options())
                        ->descriptions(Permission::descriptions())
                        ->columns(['default' => 1, 'md' => 2])
                        ->bulkToggleable()
                        ->disabled(fn (Get $get) => (bool) $get('is_super_admin'))
                        ->dehydrated()
                        ->helperText(fn (Get $get) => $get('is_super_admin') ? 'Süper yöneticide bütün yetkiler açıktır.' : null),
                ])->columnSpanFull(),

            Section::make('Profil')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->label('Görev / ünvan')->maxLength(100)
                            ->placeholder('Rehber, Organizasyon...'),
                        TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                    ]),
                    FormHelpers::imageUpload('photo', 'staff', 'Fotoğraf')->avatar()->columnSpanFull(),
                    Textarea::make('bio')->label('Kısa not')->rows(2)->maxLength(300)->columnSpanFull(),
                ])->collapsible()->collapsed()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular()->size(40)
                    ->defaultImageUrl(fn (User $r) => 'https://ui-avatars.com/api/?name='.urlencode($r->name).'&background=0d2544&color=fff'),
                TextColumn::make('name')->label('Ad Soyad')->searchable()->sortable()->weight('semibold')
                    ->description(fn (User $r) => $r->title),
                TextColumn::make('role_label')->label('Yetki')->badge()
                    ->getStateUsing(fn (User $r) => $r->role_label)
                    ->color(fn (User $r) => match (true) {
                        $r->isSuperAdmin() => 'danger',
                        count((array) $r->permissions) === 0 => 'gray',
                        default => 'info',
                    })
                    ->tooltip(fn (User $r) => collect((array) $r->permissions)
                        ->map(fn ($p) => Permission::tryFrom($p)?->label())->filter()->implode(', ') ?: null),
                TextColumn::make('email')->label('E-posta')->searchable()->toggleable(),
                TextColumn::make('phone')->label('Telefon')->placeholder('-')->copyable(),
                TextColumn::make('commission_total')->label('Toplam kazanç')
                    ->getStateUsing(fn (User $r) => (float) $r->commissions()->sum('amount'))
                    ->formatStateUsing(fn ($state) => money_label($state))
                    ->visible(fn () => auth()->user()?->viewsReports() ?? false)
                    ->toggleable(),
                ToggleColumn::make('is_active')->label('Aktif')
                    ->disabled(fn (User $r) => $r->is(auth()->user())),
            ])
            ->filters([
                SelectFilter::make('permission')
                    ->label('Yetki')
                    ->options(Permission::options())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $value) => $q
                        ->where(fn (Builder $w) => $w->where('is_super_admin', true)->orWhereJsonContains('permissions', $value)))),
                TernaryFilter::make('is_active')->label('Hesap durumu'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
