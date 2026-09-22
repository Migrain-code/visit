<?php

namespace App\Filament\Resources\Blogs\Tables;

use App\Models\Blog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class BlogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->size(48),
                TextColumn::make('title')->label('Başlık')->searchable()->sortable()->weight('semibold')->wrap()
                    ->description(fn (Blog $r) => '/blog/'.$r->slug),
                TextColumn::make('category.name')->label('Kategori')->badge()->color('gray')->placeholder('-'),
                TextColumn::make('primary_keyword')->label('Kelime')->placeholder('-')->toggleable(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (int $state, Blog $r) => $r->merged_into_id ? 'Birleştirildi' : (Blog::STATUSES[$state] ?? $state))
                    ->color(fn (int $state, Blog $r) => $r->merged_into_id ? 'gray' : ($state === Blog::STATUS_PUBLISHED ? 'success' : 'warning')),
                TextColumn::make('publish_at')->label('Yayın')->dateTime('d.m.Y H:i')->sortable()->placeholder('-'),
                TextColumn::make('source')->label('Kaynak')->badge()
                    ->formatStateUsing(fn (string $state) => Blog::SOURCES[$state] ?? $state)
                    ->color(fn (string $state) => $state === Blog::SOURCE_AI ? 'info' : 'gray')->toggleable(),
                TextColumn::make('views')->label('Görüntülenme')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(Blog::STATUSES),
                SelectFilter::make('blog_category_id')->label('Kategori')->relationship('category', 'name')->preload(),
                SelectFilter::make('source')->label('Kaynak')->options(Blog::SOURCES),
                TernaryFilter::make('merged_into_id')->label('Birleştirilmiş')->nullable()
                    ->trueLabel('Yalnız birleştirilenler')->falseLabel('Birleştirilmemişler'),
            ])
            ->recordActions([
                Action::make('preview')->label('Sitede gör')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Blog $r) => $r->url)->openUrlInNewTab()
                    ->visible(fn (Blog $r) => $r->is_published),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->label('Yayınla')->icon('heroicon-o-check')->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => Blog::STATUS_PUBLISHED]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unpublish')->label('Yayından kaldır')->icon('heroicon-o-eye-slash')->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['status' => Blog::STATUS_DRAFT]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('publish_at', 'desc');
    }
}
