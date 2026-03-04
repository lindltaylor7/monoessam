<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Provider;

$testNames = [
    "Carlos Peña Otarola - Verduras",
    "MACRE PERU SRI - FRUTAS",
    "Marisol Torres Yauri - Frutas"
];

$output = "";
foreach ($testNames as $name) {
    $found = Provider::where('name', 'like', "%{$name}%")->first();
    $output .= "Testing '$name': " . ($found ? "Found: " . $found->name : "Not Found") . "\n";

    // Test base name after dash
    $parts = explode('-', $name);
    $baseName = trim($parts[0]);
    $foundBase = Provider::where('name', 'like', "%{$baseName}%")->first();
    $output .= "Testing Base '$baseName': " . ($foundBase ? "Found: " . $foundBase->name : "Not Found") . "\n";
}

$output .= "\nAll Providers:\n";
foreach (Provider::all() as $p) {
    $output .= "- " . $p->name . "\n";
}
file_put_contents('provider_test_results.txt', $output);
echo "Written to provider_test_results.txt\n";
