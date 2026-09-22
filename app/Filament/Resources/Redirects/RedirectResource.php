<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Gözlem';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Yönlendirme';

    protected static ?string $pluralModelLabel = 'Yönlendirmeler';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('from_path')->label('Eski adres')->required()->maxLength(500)->prefix(url('/'))
                    ->helperText('Sorgu ve sondaki eğik çizgi otomatik temizlenir.'),
                TextInput::make('to_path')->label('Yeni adres')->required()->maxLength(500)
                    ->helperText('Site içi yol (/rize/ardesen) veya tam URL.'),
                Select::make('status_code')->label('Durum kodu')
                    ->options([301 => '301 — kalıcı', 302 => '302 — geçici', 410 => '410 — kaldırıldı'])
                    ->default(301)->required()->native(false),
                Select::make('source')->label('Kaynak')->options(Redirect::SOURCES)->default(Redirect::SOURCE_MANUAL)->native(false),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Textarea::make('note')->label('Not')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('Eski adres')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('to_path')->label('Yeni adres')->searchable()->wrap(),
                TextColumn::make('status_code')->label('Kod')->badge()
                    ->color(fn (int $state) => $state === 301 ? 'success' : ($state === 410 ? 'danger' : 'warning')),
                TextColumn::make('source')->label('Kaynak')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => Redirect::SOURCES[$state] ?? $state),
                TextColumn::make('hits')->label('Kullanım')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Son kullanım')->since()->placeholder('-'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([SelectFilter::make('source')->label('Kaynak')->options(Redirect::SOURCES)])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('hits', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
