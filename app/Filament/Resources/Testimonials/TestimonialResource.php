<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Testimonial;
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
use Filament\Tables\Table;
use UnitEnum;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Müşteri Yorumu';

    protected static ?string $pluralModelLabel = 'Müşteri Yorumları';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->label('Müşteri adı')->required()->maxLength(100)->helperText('Örn: Ayşe K.'),
                TextInput::make('location')->label('İlçe / şehir')->maxLength(100)->helperText('Örn: Ardeşen'),
                Select::make('tour_id')->label('Katıldığı tur (isteğe bağlı)')->relationship('tour', 'title')->preload()->searchable(),
                Select::make('rating')->label('Yıldız')->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1'])->default(5)->required(),
                Textarea::make('comment')->label('Yorum')->required()->rows(4)->maxLength(1000)->columnSpanFull()
                    ->helperText('Yalnızca gerçek müşteri yorumları ekleyin.'),
                Toggle::make('is_active')->label('Yayında')->default(true),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Müşteri')->searchable()->weight('semibold'),
                TextColumn::make('location')->label('Bölge')->placeholder('-'),
                TextColumn::make('rating')->label('Puan')->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning'),
                TextColumn::make('comment')->label('Yorum')->limit(70)->wrap(),
                TextColumn::make('tour.title')->label('Tur')->placeholder('-')->toggleable(),
                ToggleColumn::make('is_active')->label('Yayında'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTestimonials::route('/'),
        ];
    }
}
