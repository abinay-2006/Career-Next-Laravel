<?php
// Trace ProfilePhoto::path() logic for the affected filename

$testValues = [
    'photos/profile_5_36ccefc3-32a0-4a3f-86a4-545faac2964c.png',
    'profile_5_36ccefc3-32a0-4a3f-86a4-545faac2964c.png',
    'upload/photos/profile_5_36ccefc3-32a0-4a3f-86a4-545faac2964c.png',
    'uploads/photos/profile_5_36ccefc3-32a0-4a3f-86a4-545faac2964c.png',
];

foreach ($testValues as $input) {
    echo "=== Input: '$input' ===" . PHP_EOL;

    $value = trim((string) $input);
    if ($value === '') { echo "  -> null (empty)" . PHP_EOL; continue; }

    $value = ltrim(str_replace('\\', '/', $value), '/');
    $value = preg_replace('#^(?:public/|storage/)#', '', $value);
    echo "  After prefix strip: '$value'" . PHP_EOL;

    if (preg_match('#^(upload|uploads)/photos/([^/]+)$#', $value)) {
        $result = str_contains($value, '..') ? null : $value;
        echo "  -> Legacy upload match: " . ($result ?? 'null') . PHP_EOL;
        continue;
    }

    if (str_starts_with($value, 'registration/photos/')) {
        echo "  -> Registration match" . PHP_EOL;
        continue;
    }

    $value = preg_replace('#^photos/#', '', $value);
    echo "  After photos/ strip: '$value'" . PHP_EOL;

    if ($value === '' || str_contains($value, '..') || str_contains($value, '/')) {
        echo "  -> null (rejected)" . PHP_EOL;
    } else {
        echo "  -> RESULT: 'photos/$value'" . PHP_EOL;
    }
}
