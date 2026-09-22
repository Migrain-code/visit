<?php

namespace App\Http\Controllers;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Support\SchemaOrg;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = GalleryCategory::query()->ordered()->whereHas('items', fn ($q) => $q->where('is_active', true))->get();
        $activeCategory = $request->query('kategori');

        if ($activeCategory && ! $categories->contains('slug', $activeCategory)) {
            $activeCategory = null;
        }

        return view('gallery.index', [
            'categories' => $categories,
            'items' => GalleryItem::query()->active()->with('category')->ordered()->get(),
            'activeCategory' => $activeCategory,
            'metaTitle' => 'Galeri | Turlarımızdan Kareler',
            'metaDescription' => 'Düzenlediğimiz turlardan fotoğraflar: gezdiğimiz yerler, misafirlerimiz ve yolculuk anıları.',
            'canonical' => route('gallery.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Galeri', 'url' => route('gallery.index')],
            ])],
        ]);
    }
}
