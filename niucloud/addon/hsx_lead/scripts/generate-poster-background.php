<?php
declare(strict_types=1);
/** Reproducible plugin artwork, not a copied framework/competitor asset. */
if (PHP_SAPI !== 'cli')exit(1);
if (!extension_loaded('imagick'))throw new RuntimeException('Imagick required');
$canvas = new Imagick();
$canvas->newImage(750, 1100, new ImagickPixel('#EDF5FF'), 'png');
$hero = new Imagick();
$hero->newPseudoImage(750, 390, 'gradient:#1F5CCB-#3A81E8');
$canvas->compositeImage($hero, Imagick::COMPOSITE_OVER, 0, 0);
$draw = new ImagickDraw();
$draw->setFillColor('rgba(255,255,255,0.045)');
$draw->circle(724, 65, 1034, 65);
$draw->circle(740, 53, 976, 53);
$draw->circle(749, 48, 926, 48);
$draw->setFillColor('#FFFFFF');
$draw->roundRectangle(28, 354, 722, 1040, 22, 22);
$draw->setStrokeColor('#E9F0FA');
$draw->setStrokeWidth(1);
$draw->line(60, 550, 690, 550);
$canvas->drawImage($draw);
$canvas->stripImage();
$canvas->setImageFormat('png');
$path = dirname(__DIR__).'/resource/poster/share-background.png';
if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true))throw new RuntimeException('Cannot create plugin artwork directory');
$canvas->writeImage($path);
echo json_encode(['path' => $path, 'width' => 750, 'height' => 1100, 'bytes' => filesize($path)]).PHP_EOL;
