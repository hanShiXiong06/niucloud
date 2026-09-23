<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device_export;

use addon\hsx_recycle\app\listener\export\RecycleDeviceExportDataListener;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** 回收插件的带条形码导出；复用现有导出列和框架条码库，不修改公共导出器。 */
class DeviceBarcodeWorkbook
{
    public static function assertSupported(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagepng')) {
            throw new \RuntimeException('条形码导出需要 PHP GD 扩展，请联系管理员开启后重试');
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Excel 导出需要 PHP ZIP 扩展，请联系管理员开启后重试');
        }
    }

    /**
     * 图片作为工作簿附件嵌入，不依赖公网图片地址、条码字体或客户电脑联网。
     * 临时 PNG 在写入 xlsx 后清理。缺失/异常串号保留原文，不伪造可扫描的 IMEI。
     */
    public function save(array $rows, string $path): void
    {
        self::assertSupported();
        $columns = [];
        foreach ((new RecycleDeviceExportDataListener())->handle()['recycle_device']['column'] as $key => $column) {
            $columns[$key] = $column;
            if ($key === 'imei') $columns['imei_barcode'] = ['name' => 'IMEI 条形码'];
        }

        $tempDir = sys_get_temp_dir() . '/hsx_recycle_barcode_' . bin2hex(random_bytes(12));
        if (!mkdir($tempDir, 0700)) throw new \RuntimeException('无法创建条形码临时目录，请检查服务器磁盘和权限');
        $images = [];
        $book = new Spreadsheet();
        try {
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('回收设备');
            $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
            $sheet->freezePane('A2');
            foreach (array_keys($columns) as $index => $key) {
                $letter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit($letter . '1', $columns[$key]['name'], DataType::TYPE_STRING);
                $sheet->getColumnDimension($letter)->setWidth($this->columnWidth($key));
                $columns[$key]['letter'] = $letter;
            }
            $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
            $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
            $sheet->getRowDimension(1)->setRowHeight(26);

            foreach ($rows as $index => $item) {
                $row = $index + 2;
                $imei = trim((string)($item['imei'] ?? ''));
                $sheet->getRowDimension($row)->setRowHeight(76);
                foreach ($columns as $key => $column) {
                    $cell = $column['letter'] . $row;
                    if ($key === 'imei_barcode') {
                        if (!preg_match('/^[0-9]{15}$/D', $imei)) {
                            $sheet->setCellValueExplicit($cell, $imei === '' ? '未填写 IMEI' : '非15位数字 IMEI，未生成条形码', DataType::TYPE_STRING);
                            continue;
                        }
                        // 同一文件中重复 IMEI 复用图片，但每一行有独立的锚点。
                        $imageKey = 'imei_' . $imei;
                        if (!isset($images[$imageKey])) {
                            $images[$imageKey] = $tempDir . '/' . hash('sha256', $imei) . '.png';
                            $this->writeBarcode($imei, $images[$imageKey]);
                        }
                        $drawing = new Drawing();
                        $drawing->setName('IMEI ' . $imei);
                        $drawing->setDescription($imei);
                        $drawing->setPath($images[$imageKey]);
                        $drawing->setCoordinates($cell);
                        $drawing->setOffsetX(6)->setOffsetY(5);
                        $drawing->setEditAs(Drawing::EDIT_AS_ONECELL);
                        $drawing->setWorksheet($sheet);
                        continue;
                    }
                    $value = (string)($item[$key] ?? '');
                    if (in_array($key, ['imei', 'imei2', 'sn', 'code'], true)) $value = trim($value);
                    // 明确写为文本：保留前导零、完整串号，并防止用户内容被当作公式执行。
                    $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            }
            $range = 'A1:' . $lastColumn . max(1, count($rows) + 1);
            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);
        } finally {
            $book->disconnectWorksheets();
            foreach ($images as $image) if (is_file($image)) unlink($image);
            if (is_dir($tempDir)) rmdir($tempDir);
        }
    }

    private function columnWidth(string $key): int
    {
        if ($key === 'imei_barcode') return 47;
        if ($key === 'check_result') return 60;
        if ($key === 'model') return 30;
        if (in_array($key, ['order_no', 'consignment_no'], true)) return 29;
        if (in_array($key, ['imei', 'imei2', 'sn', 'code', 'create_at'], true)) return 23;
        return 17;
    }

    private function writeBarcode(string $imei, string $path): void
    {
        // 使用框架已有的 Code 128 实现，不引入新的 Composer 依赖。
        require_once root_path() . 'core/util/barcode/class/BCGDrawing.php';
        require_once root_path() . 'core/util/barcode/class/BCGcode128.barcode.php';
        $code = new \BCGcode128();
        $code->setScale(2);
        $code->setThickness(30);
        $code->setLabel('');
        $code->parse($imei);
        $drawing = new \BCGDrawing(null, new \BCGColor(255, 255, 255));
        $drawing->setBarcode($code);
        $drawing->draw();
        $source = $drawing->get_im();
        $width = imagesx($source);
        $height = imagesy($source);
        // 两侧各留 10 个窄条宽的静区，不压缩条码；下方以普通字体显示串号。
        $image = imagecreatetruecolor($width + 40, $height + 30);
        if ($image === false) throw new \RuntimeException('条形码图片生成失败');
        try {
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            imagecopy($image, $source, 20, 5, 0, 0, $width, $height);
            imagestring($image, 3, (int)(($width + 40 - imagefontwidth(3) * strlen($imei)) / 2), $height + 10, $imei, imagecolorallocate($image, 0, 0, 0));
            if (!imagepng($image, $path)) throw new \RuntimeException('条形码图片写入失败，请检查服务器磁盘和权限');
        } finally {
            imagedestroy($image);
        }
    }
}
