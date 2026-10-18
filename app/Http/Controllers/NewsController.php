<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['q'] ?? '');
        $news = News::query()
            ->when($search !== '', fn ($query) => $query->where('News', 'like', '%' . $search . '%'))
            ->orderByDesc('NewsDate')->orderByDesc('NewsId')
            ->paginate(9)->withQueryString();
        $featuredNews = $search === '' && $news->currentPage() === 1 ? $news->getCollection()->first() : null;
        return view('news.index', compact('news', 'featuredNews', 'search'));
    }

    public function show(News $news)
    {
        $relatedNews = News::query()->where('NewsId', '!=', $news->NewsId)
            ->orderByDesc('NewsDate')->orderByDesc('NewsId')->limit(3)->get();
        $title = Str::limit(Str::before($news->News, '. '), 72, '');
        if ($title === '') $title = Str::limit($news->News, 72);
        return view('news.show', compact('news', 'relatedNews', 'title'));
    }
}
