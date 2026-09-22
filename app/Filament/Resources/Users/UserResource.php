<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\FormHelpers;
use App\Models\ReservationRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Personel';

    protected static ?string $pluralModelLabel = 'Personel';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Hesap')
                ->description('Panele giriş bilgileri ve yetki seviyesi.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->label('Ad Soyad')->required()->maxLength(100),
                        TextInput::make('email')->label('E-posta')->email()->required()
                            ->unique(ignoreRecord: true)->maxLength(150),
                        Select::make('role')
                            ->label('Rol')
                            ->options(UserRole::options())
                            ->default(UserRole::Kayit->value)
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(fn (Get $get) => UserRole::tryFrom((string) $get('role'))?->description()),
                        TextInput::make('password')
                            ->label('Parola')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Düzenlerken boş bırakılırsa parola değişmez.'),
                    ]),
                    Toggle::make('is_active')->label('Hesap aktif')->default(true)
                        ->helperText('Kapatılırsa panele giriş yapamaz. Kayıtları ve geçmiş atamaları korunur.'),
                ])->columnSpanFull(),

            Section::make('İletişim ve site görünürlüğü')
                ->description('Bu bilgiler web sitesinde gösterilebilir; müşteriler doğrudan bu numaralara yönlendirilir.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->label('Ünvan')->maxLength(100)
                            ->placeholder('Tur Rehberi / Rezervasyon Sorumlusu'),
                        TextInput::make('phone')->label('Telefon')->tel()->maxLength(30)
                            ->placeholder('0532 000 00 00'),
                        TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(30)
                            ->helperText('Boş bırakılırsa telefon numarası kullanılır.'),
                        TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                    ]),
                    FormHelpers::imageUpload('photo', 'staff', 'Fotoğraf')->avatar()->columnSpanFull(),
                    Textarea::make('bio')->label('Kısa tanıtım')->rows(2)->maxLength(300)->columnSpanFull()
                        ->placeholder('Hangi turlarda görev alıyor, hangi dilleri konuşuyor...'),
                    Toggle::make('show_on_site')->label('Web sitesinde göster')
                        ->helperText('Açıldığında iletişim sayfasındaki ekip listesinde yer alır. Telefon girilmesi gerekir.'),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular()->size(40)
                    ->defaultImageUrl(fn (User $r) => 'https://ui-avatars.com/api/?name='.urlencode($r->name).'&background=0F2B47&color=fff'),
                TextColumn::make('name')->label('Ad Soyad')->searchable()->sortable()->weight('semibold')
                    ->description(fn (User $r) => $r->title),
                TextColumn::make('role')->label('Rol')->badge()
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->color(fn (UserRole $state) => $state->color()),
                TextColumn::make('email')->label('E-posta')->searchable()->toggleable(),
                TextColumn::make('phone')->label('Telefon')->placeholder('-')->copyable(),
                TextColumn::make('open_jobs')->label('Açık iş')
                    ->tooltip('Rehber: yaklaşan sefer sayısı · Kayıt/operasyon: açık rezervasyon talebi')
                    ->getStateUsing(fn (User $r) => match (true) {
                        $r->isGuide() => $r->guidedDepartures()->upcoming()->count(),
                        $r->registersGroups() => $r->assignedRequests()
                            ->whereIn('status', [ReservationRequest::STATUS_NEW, ReservationRequest::STATUS_CONTACTED])->count(),
                        default => null,
                    })
                    ->badge()
                    ->placeholder('-')
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state === 0 => 'success',
                        $state >= 5 => 'danger',
                        default => 'warning',
                    }),
                ToggleColumn::make('show_on_site')->label('Sitede'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(UserRole::options()),
                TernaryFilter::make('show_on_site')->label('Sitede gösteriliyor'),
                TernaryFilter::make('is_active')->label('Hesap durumu'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('role');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
