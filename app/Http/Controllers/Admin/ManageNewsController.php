<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

class ManageNewsController extends Controller
{
    public function index()
    {
        $news = News::query()->orderByDesc('NewsDate')->orderByDesc('NewsId')->paginate(20);
        return view('admin.news.index', compact('news'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['News' => ['required', 'string', 'max:200'], 'NewsDate' => ['required', 'date']]);
        News::create($data);
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'News article created.', 'refresh' => true]);
        return back()->with('success', 'News article created.');
    }

    public function show(News $news)
    {
        return view('admin.news.show', compact('news'));
    }

    public function update(Request $request, News $news)
    {
        $news->update($request->validate(['News' => ['required', 'string', 'max:200'], 'NewsDate' => ['required', 'date']]));
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'News article updated.', 'refresh' => true]);
        return redirect()->route('admin.news.show', $news)->with('success', 'News article updated.');
    }

    public function destroy(Request $request, News $news)
    {
        $news->delete();
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'News article deleted.', 'refresh' => true]);
        return redirect()->route('admin.news.index')->with('success', 'News article deleted.');
    }
}
