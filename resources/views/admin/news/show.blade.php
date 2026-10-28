@extends('layouts.admin')
@section('title', 'Edit News')
@section('content')
<div class="main-content"><h1>Edit News</h1><form method="POST" action="{{ route('admin.news.update', $news) }}" data-ajax data-ajax-refresh-target=".cn-admin-content">@csrf @method('PUT')<label class="form-label" for="News">News</label><textarea class="form-control" id="News" name="News" maxlength="200" required>{{ old('News', $news->News) }}</textarea><label class="form-label mt-3" for="NewsDate">Date</label><input class="form-control" type="date" id="NewsDate" name="NewsDate" value="{{ old('NewsDate', $news->NewsDate) }}" required><button class="btn btn-primary mt-3">Save changes</button> <a class="btn btn-secondary mt-3" href="{{ route('admin.news.index') }}">Back</a></form></div>
@endsection
