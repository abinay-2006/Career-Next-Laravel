<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @include('jobseeker.resumes.partials.template-styles')
        html,body{background:#fff}
        .resume-preview-canvas{padding:0;min-height:0}
        .resume-page{width:auto;min-height:263mm;margin:0;padding:0;box-shadow:none}
        .resume-document{min-height:263mm}
    </style>
</head>
<body>
    <div class="resume-preview-canvas">
        <main class="resume-page">
            @include('jobseeker.resumes.partials.document', ['content'=>$resume->content,'template'=>$resume->template,'photoData'=>$photoData])
        </main>
    </div>
</body>
</html>
