<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @include('jobseeker.resumes.partials.template-styles')
    </style>
</head>

<body>
    <div class="resume-preview-canvas">
        <main class="resume-page">@include('jobseeker.resumes.partials.document', ['content'=>$content,'template'=>$template,'photoData'=>$photoData])</main>
    </div>
</body>

</html>