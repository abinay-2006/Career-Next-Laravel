@extends(Auth::guard('employer')->check() ? 'layouts.employer' : (Auth::guard('jobseeker')->check() ? 'layouts.jobseeker' : 'layouts.news'))

@section('title', 'Career News')

@section('content')
<div class="main-content cn-news-page cn-news-detail-page">
    <a href="{{ route('news.index') }}" class="cn-news-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Career News</a>
    <article class="cn-news-article">
        <div class="cn-news-article-meta"><span class="cn-news-article-label"><i class="fas fa-newspaper" aria-hidden="true"></i> Career Next update</span><time datetime="{{ \Illuminate\Support\Carbon::parse($news->NewsDate)->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($news->NewsDate)->format('F j, Y') }}</time></div>
        <h1>{{ $title }}</h1>
        <div class="cn-news-article-body">{{ $news->News }}</div>
    </article>

    @if($relatedNews->isNotEmpty())
        <section class="cn-news-related" aria-labelledby="relatedNewsHeading">
            <div class="cn-news-list-heading"><div><h2 id="relatedNewsHeading">More updates</h2><p>Keep up with the latest from Career Next.</p></div></div>
            <div class="cn-news-grid">
                @foreach($relatedNews as $item)
                    <article class="cn-news-card">
                        <div class="cn-news-card-top"><span class="cn-news-card-icon"><i class="fas fa-newspaper" aria-hidden="true"></i></span><time datetime="{{ \Illuminate\Support\Carbon::parse($item->NewsDate)->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($item->NewsDate)->format('M j, Y') }}</time></div>
                        <h3>{{ \Illuminate\Support\Str::limit($item->News, 88) }}</h3>
                        <p>{{ \Illuminate\Support\Str::limit($item->News, 150) }}</p>
                        <a href="{{ route('news.show', $item) }}" class="cn-news-read-link">Read more <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
