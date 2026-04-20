<?php
require_once 'config/database.php';

function titleCase($string) {
    $string = strtolower($string);
    // Capitalize after space, hyphen, and dot
    $string = preg_replace_callback('/(^|[\s.-])([a-z])/', function($matches) {
        return $matches[1] . strtoupper($matches[2]);
    }, $string);
    return $string;
}

function standardizeName($fullName) {
    $commonTitles = [
        'S.Pd', 'S.Pd.I', 'S.Kom', 'S.T', 'S.H', 'S.E', 'S.Si', 'S.Sos',
        'M.Pd', 'M.Kom', 'M.T', 'M.H', 'M.E',
        'A.Md', 'A.Md. Farm', 'A.Md. Kom', 'A.Md. Ak', 'A.Md.Kep',
        'Lc', 'Dipl.', 'H.', 'Hj.', 'Dr.', 'Drs.', 'BA', 'M.A', 'S.Ag'
    ];

    $name = trim($fullName);

    // Case 1: Already has a comma
    if (strpos($name, ',') !== false) {
        $parts = explode(',', $name, 2);
        $namePart = trim($parts[0], " \t\n\r\0\x0B.");
        $titlePart = trim($parts[1]);
        
        $namePart = titleCase($namePart);
        
        return $namePart . ', ' . $titlePart;
    }

    // Case 2: No comma, but might have a title at the end
    foreach ($commonTitles as $title) {
        // Look for title at the end (case-insensitive)
        if (preg_match('/ ('.preg_quote($title, '/').')$/i', $name, $matches)) {
            $namePart = trim(substr($name, 0, -strlen($matches[0])));
            $namePart = titleCase($namePart);
            return $namePart . ', ' . $title;
        }
    }
    
    // Case 3: Title with dot suffix without space? e.g. Febrianti. A.Md
    foreach ($commonTitles as $title) {
        if (preg_match('/(?:\.|\s)+('.preg_quote($title, '/').')$/i', $name, $matches)) {
            $namePart = trim(preg_replace('/\.?\s*'.preg_quote($title, '/').'$/i', '', $name));
            $namePart = titleCase($namePart);
            return $namePart . ', ' . $title;
        }
    }

    // Case 4: No recognizable title, just capitalize
    return titleCase($name);
}

$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name FROM employees");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Standardizing Employee Names:\n";
echo str_repeat("-", 60) . "\n";
foreach ($employees as $emp) {
    $newName = standardizeName($emp['full_name']);
    if ($newName !== $emp['full_name']) {
        echo "ID " . $emp['id'] . ": " . $emp['full_name'] . " -> " . $newName . "\n";
        // To actually update, uncomment below:
        $upd = $conn->prepare("UPDATE employees SET full_name = ? WHERE id = ?");
        $upd->execute([$newName, $emp['id']]);
    }
}
echo str_repeat("-", 60) . "\n";
echo "Done.\n";
