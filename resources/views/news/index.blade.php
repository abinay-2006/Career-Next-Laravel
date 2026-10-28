@extends(Auth::guard('employer')->check() ? 'layouts.employer' : (Auth::guard('jobseeker')->check() ? 'layouts.jobseeker' : 'layouts.news'))

@section('title', 'Career News')

@section('content')
<div class="main-content cn-news-page">
    <header class="cn-news-hero">
        <div class="cn-news-hero-copy">
            <span class="cn-news-kicker"><i class="fas fa-sparkles" aria-hidden="true"></i> CAREER NEXT JOURNAL</span>
            <h1>Career news and updates</h1>
            <p>Ideas, announcements, and useful updates for your next career move.</p>
        </div>
        <form class="cn-news-search" method="GET" action="{{ route('news.index') }}" role="search" data-ajax-html>
            <label class="visually-hidden" for="newsSearch">Search Career News</label>
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input id="newsSearch" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search news">
            @if($search !== '')<a class="cn-news-clear" href="{{ route('news.index') }}" aria-label="Clear search"><i class="fas fa-xmark" aria-hidden="true"></i></a>@endif
            <button type="submit">Search</button>
        </form>
    </header>

    @if($featuredNews)
        <section class="cn-news-featured" aria-label="Latest update">
            <div class="cn-news-featured-mark"><i class="fas fa-bolt" aria-hidden="true"></i><span>Latest update</span></div>
            <time datetime="{{ \Illuminate\Support\Carbon::parse($featuredNews->NewsDate)->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($featuredNews->NewsDate)->format('F j, Y') }}</time>
            <h2>{{ \Illuminate\Support\Str::limit($featuredNews->News, 90) }}</h2>
            <p>{{ \Illuminate\Support\Str::limit($featuredNews->News, 180) }}</p>
            <a href="{{ route('news.show', $featuredNews) }}" class="cn-news-read-link">Read the update <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </section>
    @endif

    <div class="cn-news-list-heading">
        <div><h2>{{ $search !== '' ? 'Search results' : ($featuredNews ? 'More from Career Next' : 'Latest updates') }}</h2><p>{{ $news->total() }} {{ \Illuminate\Support\Str::plural('update', $news->total()) }}{{ $search !== '' ? ' for “' . $search . '”' : '' }}</p></div>
    </div>

    @php($newsItems = $featuredNews ? $news->getCollection()->skip(1) : $news->getCollection())
    @if($newsItems->isNotEmpty())
        <div class="cn-news-grid">
            @foreach($newsItems as $item)
                <article class="cn-news-card">
                    <div class="cn-news-card-top"><span class="cn-news-card-icon"><i class="fas fa-newspaper" aria-hidden="true"></i></span><time datetime="{{ \Illuminate\Support\Carbon::parse($item->NewsDate)->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($item->NewsDate)->format('M j, Y') }}</time></div>
                    <h3>{{ \Illuminate\Support\Str::limit($item->News, 88) }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($item->News, 150) }}</p>
                    <a href="{{ route('news.show', $item) }}" class="cn-news-read-link">Read more <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            @endforeach
        </div>
    @elseif(!$featuredNews)
        <div class="cn-news-empty"><span><i class="fas fa-newspaper" aria-hidden="true"></i></span><h2>{{ $search !== '' ? 'No matching updates' : 'No news has been published yet' }}</h2><p>{{ $search !== '' ? 'Try a different search term.' : 'Check back soon for the latest Career Next updates.' }}</p>@if($search !== '')<a href="{{ route('news.index') }}">View all news</a>@endif</div>
    @endif

    @if($news->hasPages())<nav class="cn-news-pagination" aria-label="Career News pages">{{ $news->links() }}</nav>@endif
</div>
@endsection
