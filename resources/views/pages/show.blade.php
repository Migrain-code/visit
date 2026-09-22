@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $page->title,
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => $page->title, 'url' => $page->url]],
    ])

    <section class="section">
        <div class="container" style="max-width:860px">
            <div class="content-prose">{!! $page->content !!}</div>
        </div>
    </section>
@endsection
