<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Tour;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'posts' => Blog::query()->published()->with('category')->orderByDesc('publish_at')->orderByDesc('id')->paginate(9),
            'categories' => BlogCategory::query()->active()->ordered()->withCount('publishedPosts')->get(),
            'activeCategory' => null,
            'metaTitle' => 'Blog | Gezi Rehberi ve Tur Önerileri',
            'metaDescription' => 'Gezilecek yerler, tur öncesi hazırlık, rota önerileri ve yolculuk ipuçları: tura çıkmadan önce okumanız gerekenler.',
            'canonical' => route('blog.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Blog', 'url' => route('blog.index')],
            ])],
        ]);
    }

    public function category(BlogCategory $category): View
    {
        abort_unless($category->is_active, 404);

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Blog', 'url' => route('blog.index')],
            ['name' => $category->name, 'url' => $category->url],
        ];

        return view('blog.index', [
            'posts' => $category->posts()->published()->with('category')->orderByDesc('publish_at')->paginate(9),
            'categories' => BlogCategory::query()->active()->ordered()->withCount('publishedPosts')->get(),
            'activeCategory' => $category,
            'metaTitle' => $category->meta_title ?: $category->name.' | Blog',
            'metaDescription' => $category->meta_description ?: $category->description,
            'canonical' => $category->url,
            'jsonLd' => [SchemaOrg::breadcrumbs($breadcrumbs)],
        ]);
    }

    public function show(string $slug): View
    {
        $post = Blog::query()->where('slug', $slug)->with('category')->firstOrFail();

        // Yayında değilse: birleştirilmişse 301, değilse 404.
        if (! $post->is_published) {
            abort(404);
        }

        $post->forceFill(['views' => $post->views + 1])->saveQuietly();

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Blog', 'url' => route('blog.index')],
        ];

        if ($post->category) {
            $breadcrumbs[] = ['name' => $post->category->name, 'url' => $post->category->url];
        }

        $breadcrumbs[] = ['name' => $post->title, 'url' => $post->url];

        $jsonLd = [
            SchemaOrg::breadcrumbs($breadcrumbs),
            SchemaOrg::article($post),
        ];

        if ($faq = SchemaOrg::faq($post->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('blog.show', [
            'post' => $post,
            'related' => Blog::query()->published()
                ->when($post->blog_category_id, fn ($q) => $q->where('blog_category_id', $post->blog_category_id))
                ->whereKeyNot($post->getKey())
                ->orderByDesc('publish_at')->limit(3)->get(),
            'tours' => Tour::query()->active()->where('is_featured', true)->ordered()->limit(6)->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $post->meta_title ?: $post->title,
            'metaDescription' => $post->meta_description ?: $post->summary,
            'canonical' => $post->url,
            'ogImage' => $post->image_url,
            'jsonLd' => $jsonLd,
        ]);
    }
}
