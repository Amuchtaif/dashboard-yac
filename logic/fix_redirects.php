<?php
$dir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $modified = false;
        
        // Match header() or redirect() without ?, success, error
        $lines = explode("\n", $content);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            
            if (preg_match('/(header\s*\(\s*["\']Location:\s*)([^?"\']+)(["\']\s*\))/i', $line, $m) || 
                preg_match('/(redirect\s*\(\s*["\'])([^?"\']+)(["\']\s*\))/i', $line, $m)) {
                
                // Assume success if line contains 'store', 'update', 'delete', or 'index'.
                // If the block is an else block or catch block, it is an error.
                // Simple heuristic: if previous lines contain 'catch' or 'else', it's error.
                $isError = false;
                for ($j = max(0, $i-3); $j < $i; $j++) {
                    if (strpos($lines[$j], 'catch') !== false || strpos($lines[$j], 'else') !== false) {
                        $isError = true;
                    }
                }
                
                // If it's logout/login, skip
                if (strpos($file->getPathname(), 'login.php') !== false || strpos($file->getPathname(), 'logout.php') !== false) {
                    continue;
                }
                
                $param = $isError ? '?error=Operasi gagal' : '?success=Operasi berhasil';
                $newLine = str_replace($m[2], $m[2] . $param, $line);
                $lines[$i] = $newLine;
                $modified = true;
                echo "Modified " . $file->getFilename() . ": " . trim($newLine) . "\n";
            }
        }
        
        if ($modified) {
            file_put_contents($file->getPathname(), implode("\n", $lines));
            $count++;
        }
    }
}
echo "Total files fixed: $count \n";
