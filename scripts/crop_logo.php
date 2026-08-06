<?php
$sourcePath = 'C:/Users/Kahlil Gibran/.gemini/antigravity-ide/brain/308c8190-e61d-426f-8dc0-24c598b0a6b7/media__1785747743098.png';
$targetDir = __DIR__ . '/../uploads/kop_logos';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}
$targetPath = $targetDir . '/logo_yac.png';

list($width, $height) = getimagesize($sourcePath);
echo "Source image size: {$width}x{$height}\n";

$srcImg = imagecreatefrompng($sourcePath);

// Crop left logo (approx x: 20 to x: 220, y: 10 to y: height - 20)
$cropX = intval($width * 0.02);
$cropY = intval($height * 0.05);
$cropWidth = intval($width * 0.22);
$cropHeight = intval($height * 0.85);

$cropped = imagecrop($srcImg, ['x' => $cropX, 'y' => $cropY, 'width' => $cropWidth, 'height' => $cropHeight]);
if ($cropped !== false) {
    imagepng($cropped, $targetPath);
    imagedestroy($cropped);
    echo "Successfully cropped logo and saved to: " . $targetPath . "\n";
} else {
    echo "Failed to crop image\n";
}
imagedestroy($srcImg);
?>
