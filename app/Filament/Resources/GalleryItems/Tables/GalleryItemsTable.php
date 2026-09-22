<?php

namespace App\Filament\Resources\GalleryItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GalleryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('Görsel')->disk('public')->square()->size(64),
                TextColumn::make('title')->label('Başlık')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('category.name')->label('Kategori')->badge()->color('gray')->placeholder('-'),
                TextColumn::make('district.name')->label('İlçe')->placeholder('-')->toggleable(),
                ToggleColumn::make('show_on_home')->label('Ana sayfa'),
                ToggleColumn::make('is_active')->label('Yayında'),
            ])
            ->filters([
                SelectFilter::make('gallery_category_id')->label('Kategori')->relationship('category', 'name')->preload(),
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
}
