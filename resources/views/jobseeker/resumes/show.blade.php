@extends('layouts.jobseeker')
@section('title', 'Resume Preview')
@section('content')
<style>
    .my-resume-preview {
        max-width: 1200px;
        margin: 0 auto;
        padding: 8px 14px 30px
    }

    .mr-preview-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px
    }

    .mr-preview-frame {
        width: 100%;
        height: calc(100vh - 205px);
        min-height: 760px;
        border: 1px solid #e7e2ed;
        border-radius: 14px;
        background: #eeeaf2;
        box-shadow: 0 8px 26px #24124a12
    }

    @media(max-width:700px) {
        .my-resume-preview {
            padding: 0 2px 20px
        }

        .mr-preview-toolbar {
            align-items: flex-start;
            flex-wrap: wrap
        }

        .mr-preview-frame {
            height: calc(100vh - 240px);
            min-height: 650px
        }
    }
</style>
<div class="my-resume-preview">
    <div class="mr-preview-toolbar">
        <div class="mr-preview-heading">
            <a href="{{ route('jobseeker.my-resumes.index') }}" class="mr-back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>My Resumes</span>
            </a>

            <h1 class="h4 mt-3 mb-1">{{ $resume->title }}</h1>

            <small class="text-muted">
                {{ $templates[$resume->template] ?? \Illuminate\Support\Str::headline($resume->template) }}
            </small>
        </div>

        <div class="mr-preview-actions">
            <a class="mr-preview-edit-btn"
                href="{{ route('jobseeker.resumes.index', ['resume'=>$resume->id]) }}">
                <i class="fas fa-pen"></i>
                Edit Resume
            </a>

            <a class="mr-preview-download-btn"
                href="{{ route('jobseeker.resumes.download', $resume) }}">
                <i class="fas fa-download"></i>
                Download PDF
            </a>
        </div>
    </div>
    <iframe class="mr-preview-frame" src="{{ route('jobseeker.my-resumes.preview', $resume) }}" title="{{ $resume->title }} resume preview" sandbox=""></iframe>
</div>
@endsection