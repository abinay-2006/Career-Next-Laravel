<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

echo "Default disk: " . Config::get('filesystems.default') . PHP_EOL;
echo "Storage URL: " . Storage::disk('public')->url('photos/profile_6_1792163859.jpg') . PHP_EOL;
echo "File exists: " . (Storage::disk('public')->exists('photos/profile_6_1792163859.jpg') ? 'Yes' : 'No') . PHP_EOL;
?>