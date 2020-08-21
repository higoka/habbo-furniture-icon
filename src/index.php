<?php

ini_set('display_errors', true);
ini_set('display_startup_errors', true);

error_reporting(E_ALL);

if (! is_dir('resource/icon')) {
    mkdir('resource/icon', 0777, true);
}

foreach (glob('resource/*.swf') as $file) {
    $itemName = substr($file, 9, -4);
    createIcon($itemName);
}

function createIcon(string $itemName): void {
    shell_exec("java -jar ffdec/ffdec.jar -export binaryData,image tmp resource/{$itemName}.swf");

    $binVisualization = glob('tmp/binaryData/*_visualization.bin');
    $binAssets        = glob('tmp/binaryData/*_assets.bin');

    if (empty($binVisualization) || empty($binAssets)) {
        return;
    }

    $visualization = simplexml_load_file($binVisualization[0])->xpath('//visualization[@size=1]')[0];
    $assets        = simplexml_load_file($binAssets[0]);

    if (empty($visualization) || empty($assets)) {
        echo "error parsing xml for \"{$itemName}\"\n";
        continue;
    }

    $layerCount = (int) $visualization->attributes()->layerCount;

    $colorMap = [
        0 => [],
    ];

    foreach ($visualization->xpath('//visualization[@size=1]//colors/color') as $color) {
        $colorId = (int) $color->attributes()->id;

        foreach ($color->colorLayer as $colorLayer) {
            $id    = (int) $colorLayer->attributes()->id;
            $color = (string) $colorLayer->attributes()->color;

            $colorMap[$colorId][$id] = $color;
        }
    }

    foreach ($colorMap as $colorId => $colorLayer) {
        $dst = imagecreatetruecolor(100, 100);

        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, true);
        imagesavealpha($dst, true);

        for ($i = 0; $i < $layerCount; $i++) {
            $char = chr(97 + $i);
            $path = glob("tmp/images/*_icon_{$char}.png")[0];

            if (empty($path)) {
                echo "cannot find icon for \"{$itemName}\"\n";
                continue;
            }

            $assetName = substr(preg_replace("~tmp/images/\d+_{$itemName}_~i", '', $path), 0, -4);
            $asset = $assets->xpath("//asset[@name='{$assetName}']")[0];

            if (empty($asset)) {
                echo "error parsing asset for \"{$itemName}\"\n";
                continue;
            }

            $src = imagecreatefrompng($path);

            $w = imagesx($src);
            $h = imagesy($src);
            $x = (100 / 2) - $asset->attributes()->x;
            $y = (100 / 2) - $asset->attributes()->y;

            if (isset($colorLayer[$i])) {
                $src = colorize($src, $w, $h, $colorLayer[$i]);
            }

            imagecopy($dst, $src, $x, $y, 0, 0, $w, $h);
            imagedestroy($src);
        }

        $iconName = ($colorId === 0) ? $itemName : "{$itemName}_{$colorId}";
        $dst = imagecropauto($dst, IMG_CROP_DEFAULT);

        imagesavealpha($dst, true);
        imagepng($dst, "resource/icon/{$iconName}_icon.png");
        imagedestroy($dst);
    }

    array_map('unlink', glob('tmp/*/*'));
    echo "icon created: {$itemName}\n";
}

function colorize($src, int $w, int $h, string $color) {
    $color = array_map('hexdec', str_split($color, 2));

    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $h; $y++) {
            $srcColor = imagecolorsforindex($src, imagecolorat($src, $x, $y));

            $r = $color[0] * $srcColor['red'] / 255;
            $g = $color[1] * $srcColor['green'] / 255;
            $b = $color[2] * $srcColor['blue'] / 255;

            imagesetpixel($src, $x, $y, imagecolorallocatealpha($src, $r, $g, $b, $srcColor['alpha']));
        }
    }

    return $src;
}

exit('done');
