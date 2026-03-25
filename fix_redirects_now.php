<?php
$dir = __DIR__ . '/logic';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Skip auth files as they manage their own logic
        if (strpos($file->getPathname(), 'login.php') !== false || strpos($file->getPathname(), 'logout.php') !== false) {
            continue;
        }
        
        $modified = false;
        $lines = explode("\n", $content);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            
            // Matches: header("Location: uri") or redirect('uri')
            // It uses regex to ensure it captures the URI
            if (preg_match('/^(.*)(header\s*\(\s*["\']Location:\s*)([^?"\']+)(["\']\s*\).*)$/i', $line, $m) || 
                preg_match('/^(.*)(redirect\s*\(\s*["\'])([^?"\']+)(["\']\s*\).*)$/i', $line, $m)) {
                
                // If it already has query parameters (e.g., success, error) mapped by ? or &
                if (strpos($m[3], 'success=') !== false || strpos($m[3], 'error=') !== false || strpos($line, 'success') !== false || strpos($line, 'error') !== false) {
                    continue;
                }
                
                // Very basic heuristic to determine if it's an error context
                $isError = false;
                for ($j = max(0, $i - 4); $j < $i; $j++) {
                    if (strpos(strtolower($lines[$j]), 'catch') !== false || 
                        strpos(strtolower($lines[$j]), 'else') !== false ||
                        strpos(strtolower($lines[$j]), 'error') !== false) {
                        $isError = true;
                    }
                }
                
                // Add the appropriate query parameter
                $param = $isError ? '?error=Operasi gagal' : '?success=Operasi berhasil';
                
                // Construct the new line carefully incorporating the query parameter
                $newLine = $m[1] . $m[2] . $m[3] . $param . $m[4];
                $lines[$i] = $newLine;
                $modified = true;
                echo "Fixed " . $file->getFilename() . " line " . ($i+1) . ": \n  OLD: $line\n  NEW: $newLine\n";
            }
        }
        
        if ($modified) {
            file_put_contents($file->getPathname(), implode("\n", $lines));
            $count++;
        }
    }
}
echo "Total files fixed: $count\n";
?>
