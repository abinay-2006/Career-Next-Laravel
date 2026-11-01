@extends('layouts.admin')

@section('title', 'Manage News')

@section('content')
<div class="main-content cn-news-page">

    {{-- Page Header --}}
    <div class="cn-news-header">
        <div class="cn-news-header-content">
            <div class="cn-news-icon">
                <i class="bi bi-megaphone-fill"></i>
            </div>

            <div>
                <h1>Manage News</h1>
                <p>Publish and manage news updates displayed on your public news pages.</p>
            </div>
        </div>

        <div class="cn-news-header-badge">
            <i class="bi bi-newspaper"></i>
            News Management
        </div>
    </div>

    {{-- Information Notice --}}
    <div class="cn-news-notice">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Publication information</strong>
            <p>News items are visible on public news pages because the current database schema does not include a publication status.</p>
        </div>
    </div>

    {{-- Add News Form --}}
    <div class="card cn-news-form-card">
        <div class="cn-news-card-heading">
            <div class="cn-news-section-icon">
                <i class="bi bi-plus-lg"></i>
            </div>

            <div>
                <h2>Add News</h2>
                <p>Create a new announcement for your website visitors.</p>
            </div>
        </div>

        <div class="cn-news-card-body">
            <form method="POST"
                action="{{ route('admin.news.store') }}"
                data-ajax
                data-ajax-refresh-target=".cn-admin-content">

                @csrf

                <div class="mb-4">
                    <label class="form-label cn-news-label" for="News">
                        News Content <span class="text-danger">*</span>
                    </label>

                    <textarea
                        class="form-control cn-news-input"
                        id="News"
                        name="News"
                        maxlength="200"
                        rows="3"
                        placeholder="Enter your news announcement..."
                        required>{{ old('News') }}</textarea>

                    <div class="cn-news-field-help">
                        <i class="bi bi-pencil-square"></i>
                        Maximum 200 characters.
                    </div>

                    @error('News')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row align-items-end g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label cn-news-label" for="NewsDate">
                            Publication Date <span class="text-danger">*</span>
                        </label>

                        <input
                            class="form-control cn-news-input"
                            type="date"
                            id="NewsDate"
                            name="NewsDate"
                            value="{{ old('NewsDate', now()->toDateString()) }}"
                            required>

                        @error('NewsDate')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-7">
                        <div class="cn-news-form-actions">
                            <button type="reset" class="btn cn-news-reset-btn">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Reset
                            </button>

                            <button type="submit" class="btn cn-news-submit-btn">
                                <i class="bi bi-plus-circle"></i>
                                Add News
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- News List --}}
    <div class="card cn-news-list-card">

        <div class="cn-news-list-heading">
            <div class="cn-news-list-title">
                <div class="cn-news-section-icon">
                    <i class="bi bi-list-ul"></i>
                </div>

                <div >
                    <h2 style="color: aliceblue;">News Updates</h2>
                    <p style="color: aliceblue">Review and manage existing announcements.</p>
                </div>
            </div>

            <div class="cn-news-count">
                <i class="bi bi-collection"></i>

                @php
                $newsCount = isset($news) && is_object($news) && method_exists($news, 'total')
                ? $news->total()
                : 0;
                @endphp

                <span>{{ $newsCount }} {{ $newsCount === 1 ? 'Item' : 'Items' }}</span>
            </div>
        </div>

        <div class="table-responsive cn-news-table-wrapper">
            <table class="table cn-news-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">DATE</th>
                        <th scope="col">NEWS CONTENT</th>
                        <th scope="col" class="text-md-center">ACTIONS</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse(($news ?? collect()) as $item)
                    <tr>
                        <td>
                            <div class="cn-news-date">
                                <span class="cn-news-date-icon">
                                    <i class="bi bi-calendar3"></i>
                                </span>

                                <span>{{ \Illuminate\Support\Carbon::parse($item->NewsDate)->format('d M Y') }}</span>
                            </div>
                        </td>

                        <td>
                            <div class="cn-news-content">
                                <span class="cn-news-content-icon">
                                    <i class="bi bi-megaphone"></i>
                                </span>

                                <span>{{ $item->News }}</span>
                            </div>
                        </td>

                        <td>
                            <div class="cn-news-row-actions">
                                <a
                                    class="btn cn-news-edit-btn"
                                    href="{{ route('admin.news.show', $item) }}">
                                    <i class="bi bi-pencil-square"></i>
                                    <span>Edit</span>
                                </a>

                                <form
                                    class="d-inline"
                                    method="POST"
                                    data-ajax
                                    data-ajax-refresh-target=".cn-admin-content"
                                    action="{{ route('admin.news.destroy', $item) }}"
                                    onsubmit="return confirm('Delete this news item?')">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn cn-news-delete-btn">
                                        <i class="bi bi-trash3"></i>
                                        <span>Delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3">
                            <div class="cn-news-empty">
                                <div class="cn-news-empty-icon">
                                    <i class="bi bi-newspaper"></i>
                                </div>
                                <h3>No news updates yet</h3>
                                <p>Your news announcements will appear here after you add them.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (isset($news) && is_object($news) && method_exists($news, 'hasPages') && $news->hasPages())
        <div class="cn-news-pagination">
            {{ $news->links() }}
        </div>
        @endif
    </div>
</div>

@endsection