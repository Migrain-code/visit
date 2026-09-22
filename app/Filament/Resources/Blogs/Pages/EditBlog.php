<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use App\Models\Blog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label('Sitede gör')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Blog $record) => $record->url)->openUrlInNewTab()
                ->visible(fn (Blog $record) => $record->is_published),
            DeleteAction::make(),
        ];
    }
}
