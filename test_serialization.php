<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$q = App\Models\Question::first();
$q->translations = [['id' => 1, 'text_it' => 'test']];
$array = $q->toArray();
if (isset($array['translations'])) {
    echo "Translations found in array\n";
    print_r($array['translations']);
} else {
    echo "Translations NOT found in array\n";
    print_r($array);
}
