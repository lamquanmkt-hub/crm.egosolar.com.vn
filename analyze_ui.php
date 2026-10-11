<?php
$dir = 'd:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn/resources/views';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$layouts = [];
$inlineStyles = [];
$inlineScripts = [];
$frameworkMix = [];

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        $relPath = str_replace($dir . DIRECTORY_SEPARATOR, '', $path);
        
        // Find layout
        if (preg_match('/@extends\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
            $layout = $matches[1];
            $layouts[$layout][] = $relPath;
        }

        // Inline style/script
        if (preg_match('/<style\b[^>]*>/i', $content)) {
            $inlineStyles[] = $relPath;
        }
        if (preg_match('/<script\b[^>]*>/i', $content)) {
            $inlineScripts[] = $relPath;
        }

        // Bootstrap vs Tailwind heuristic
        $hasBootstrap = preg_match('/\b(row|col-md-|btn-primary|card-header|mb-3)\b/', $content);
        $hasTailwind = preg_match('/\b(flex-col|text-gray-|bg-blue-500|p-4|rounded-lg)\b/', $content);

        if ($hasBootstrap && $hasTailwind) {
            $frameworkMix[] = $relPath;
        }
    }
}

echo "=== LAYOUTS ===\n";
foreach ($layouts as $l => $views) {
    echo "- $l: " . count($views) . " views\n";
}
echo "\n=== INLINE STYLES (" . count($inlineStyles) . ") ===\n";
echo implode("\n", array_slice($inlineStyles, 0, 10)) . "\n";

echo "\n=== FRAMEWORK MIX (" . count($frameworkMix) . ") ===\n";
echo implode("\n", array_slice($frameworkMix, 0, 10)) . "\n";

