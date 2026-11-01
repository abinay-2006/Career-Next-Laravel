@extends('layouts.admin')

@section('title', 'Edit News')

@section('content')
<div class="main-content cn-news-page cn-news-edit-page">

    <div class="cn-news-header">
        <div class="cn-news-header-content">
            <div class="cn-news-icon">
                <i class="bi bi-pencil-square" aria-hidden="true"></i>
            </div>
            <div>
                <h1>Edit News</h1>
                <p>Update the news content and publication date</p>
            </div>
        </div>
        <a href="{{ route('admin.news.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to News
        </a>
    </div>

    <div class="card cn-news-form-card">
        <div class="cn-news-card-heading">
            <div class="cn-news-section-icon">
                <i class="bi bi-newspaper" aria-hidden="true"></i>
            </div>
            <div>
                <h2>Edit News Details</h2>
                <p>Make the necessary changes below.</p>
            </div>
        </div>

        <div class="cn-news-card-body">
            <form
                method="POST"
                action="{{ route('admin.news.update', $news) }}"
                data-ajax
                data-ajax-refresh-target=".cn-admin-content">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="cn-news-label" for="News">
                        <i class="bi bi-card-text" aria-hidden="true"></i>
                        News Content
                    </label>

                    <textarea
                        class="form-control cn-news-input"
                        id="News"
                        name="News"
                        maxlength="200"
                        rows="5"
                        required>{{ old('News', $news->News) }}</textarea>

                    <small class="cn-news-field-help">
                        Maximum 200 characters.
                    </small>
                </div>

                <div class="mb-4">
                    <label class="cn-news-label" for="NewsDate">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        News Date
                    </label>

                    <input
                        class="form-control cn-news-input"
                        type="date"
                        id="NewsDate"
                        name="NewsDate"
                        value="{{ old('NewsDate', $news->NewsDate) }}"
                        required>
                </div>

                <div class="cn-news-form-actions">
                    <a
                        href="{{ route('admin.news.index') }}"
                        class="btn cn-news-reset-btn">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn cn-news-submit-btn"
                        data-loading>
                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection