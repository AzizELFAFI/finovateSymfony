<?php

$directory = new RecursiveDirectoryIterator(__DIR__.'/templates');
$iterator = new RecursiveIteratorIterator($directory);
$regex = new RegexIterator($iterator, '/^.+\.html\.twig$/i', RecursiveRegexIterator::GET_MATCH);

$addedCount = 0;

foreach ($regex as $file) {
    if (strpos($file[0], 'backoffice') === false && strpos($file[0], 'forum') === false) {
        continue; 
    }
    
    $content = file_get_contents($file[0]);
    
    // Look for the line containing backoffice_ads
    $lines = explode("\n", $content);
    $newLines = [];
    $modified = false;
    
    foreach ($lines as $line) {
        $newLines[] = $line;
        if (strpos($line, "path('backoffice_ads')") !== false && strpos($content, 'admin_reclamations_index') === false) {
            // Found the Annonces line, append our new line after it
            $newLines[] = '      <li><a class="nav-link" href="{{ path(\'admin_reclamations_index\') }}"><i class="ti ti-headset"></i><span class="nav-text">Réclamations</span></a></li>';
            $modified = true;
        }
    }
    
    if ($modified) {
        file_put_contents($file[0], implode("\n", $newLines));
        echo "Updated: " . $file[0] . "\n";
        $addedCount++;
    }
}

echo "Total files updated: $addedCount\n";
