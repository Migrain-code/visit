<?php

namespace App\Filament\Resources\SeoKeywords;

use App\Filament\Resources\SeoKeywords\Pages\ManageSeoKeywords;
use App\Models\SeoKeyword;
use BackedEnum;
use Filament\Actions\BulkAction;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class SeoKeywordResource extends Resource
{
    protected static ?string $model = SeoKeyword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Anahtar Kelime';

    protected static ?string $pluralModelLabel = 'Anahtar Kelimeler';

    protected static ?string $recordTitleAttribute = 'keyword';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kelime')->schema([
                Grid::make(2)->schema([
                    TextInput::make('keyword')->label('Anahtar kelime')->required()->maxLength(255)->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule),
                    TextInput::make('search_volume')->label('Aylık arama hacmi')->numeric()->minValue(0),
                    Select::make('keyword_type')->label('Kelime tipi')->options(SeoKeyword::TYPES)->default('BLOG_PRIMARY')->required()->native(false),
                    Select::make('search_intent')->label('Arama niyeti')->options(SeoKeyword::INTENTS)->native(false),
                    Select::make('priority')->label('Öncelik')->options([1 => '1 — en yüksek', 2 => '2', 3 => '3 — normal', 4 => '4', 5 => '5 — en düşük'])->default(3)->native(false),
                    Toggle::make('status')->label('Aktif')->default(true),
                ]),
            ])->columnSpanFull(),

            Section::make('Sahiplik')
                ->description('BİR KELİMENİN TEK SAHİBİ OLUR. Hedef sayfa seçilirse blog sahipliği otomatik düşer.')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('target_id')->label('Sahip: hedef sayfa')->relationship('target', 'name')->searchable()->preload()
                            ->helperText('Ticari kelimeler hedef sayfaya atanır.'),
                        Select::make('owner_blog_id')->label('Sahip: blog yazısı')->relationship('ownerBlog', 'title')->searchable()->preload()
                            ->disabled(fn (Get $get) => filled($get('target_id')))
                            ->helperText('Bilgi amaçlı kelimeler blog yazısına atanır.'),
                    ]),
                    Textarea::make('note')->label('Not')->rows(2)->columnSpanFull(),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')->label('Kelime')->searchable()->sortable()->weight('semibold')->wrap(),
                TextColumn::make('assignment_status')->label('Atama')->badge()
                    ->formatStateUsing(fn (string $state) => SeoKeyword::ASSIGNMENT_STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => SeoKeyword::ASSIGNMENT_COLORS[$state] ?? 'gray'),
                TextColumn::make('owner_label')->label('Sahibi')->placeholder('-')->wrap(),
                TextColumn::make('keyword_type')->label('Tip')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => SeoKeyword::TYPES[$state] ?? $state)->toggleable(),
                TextColumn::make('search_volume')->label('Hacim')->numeric()->sortable()->placeholder('-'),
                TextColumn::make('priority')->label('Öncelik')->sortable()->toggleable(),
                TextColumn::make('latest_position')->label('Sıra')
                    ->getStateUsing(fn (SeoKeyword $r) => $r->rankHistory()->latest('data_date')->value('position_avg'))
                    ->formatStateUsing(fn ($state) => $state ? round((float) $state, 1) : '-')
                    ->badge()->color(fn ($state) => $state && $state <= 10 ? 'success' : ($state && $state <= 30 ? 'warning' : 'gray')),
                TextColumn::make('target_match')->label('Doğru sayfa?')
                    ->getStateUsing(function (SeoKeyword $r) {
                        $match = $r->rankHistory()->latest('data_date')->value('target_match');

                        return $match === null ? '-' : ($match ? 'evet' : 'HAYIR');
                    })
                    ->badge()->color(fn (string $state) => $state === 'HAYIR' ? 'danger' : ($state === 'evet' ? 'success' : 'gray'))
                    ->toggleable(),
                ToggleColumn::make('status')->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('assignment_status')->label('Atama durumu')->options(SeoKeyword::ASSIGNMENT_STATUSES),
                SelectFilter::make('keyword_type')->label('Tip')->options(SeoKeyword::TYPES),
                Filter::make('unassigned')->label('Yalnız sahipsizler')
                    ->query(fn ($query) => $query->whereNull('target_id')->whereNull('owner_blog_id')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('release')->label('Sahipliği kaldır')->icon('heroicon-o-lock-open')
                        ->action(fn (Collection $records) => $records->each(fn (SeoKeyword $k) => $k->update(['target_id' => null, 'owner_blog_id' => null])))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeoKeywords::route('/')];
    }
}
