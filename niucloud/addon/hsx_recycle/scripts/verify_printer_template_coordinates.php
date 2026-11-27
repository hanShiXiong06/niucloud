<?php
declare(strict_types=1);

// Exercise the real editor JSON -> printer instruction conversion; no DB, API or printer.
namespace core\base { class BaseAdminService { public function __construct() {} } }
namespace core\exception { class CommonException extends \RuntimeException {} }

namespace {
    use addon\hsx_recycle\app\service\admin\printer\template\TemplateConverterService;
    use addon\hsx_recycle\app\service\admin\printer\template\TemplateRenderService;

    if (PHP_SAPI !== 'cli') exit(1);
    require __DIR__ . '/../app/service/admin/printer/template/TemplateConverterService.php';
    require __DIR__ . '/../app/service/admin/printer/template/TemplateLayoutService.php';
    require __DIR__ . '/../app/service/admin/printer/template/TemplateRenderService.php';

    function same($expected, $actual, string $case): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($case . ': expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true));
        }
    }

    $converter = new TemplateConverterService();
    $editorJson = '{"copies":2,"width":80,"height":50,"elements":[{"type":"text","content":"{{model}}","x":10,"y":6.75}]}';
    same('<PAGE n="2"><SIZE>80,50</SIZE><TEXT x="10" y="7" w="1" h="1" r="0">{{model}}</TEXT></PAGE>',
        $converter->jsonToXml(json_decode($editorJson, true, 512, JSON_THROW_ON_ERROR)), 'reported decimal Y in text');

    // Positions are printer dots already. No page-size, pixel or DPI scaling at export.
    $elements = [];
    foreach (['variable', 'qrcode', 'barcode', 'image', 'line', 'rectangle'] as $type) {
        $elements[] = ['type' => $type, 'x' => '12.49', 'y' => 23.5, 'content' => '{{imei}}'];
    }
    $elements[] = ['type' => 'rectangle', 'x' => 5.75, 'y' => 6.25, 'xe' => '89.75', 'ye' => 45.125];
    $xml = $converter->jsonToXml(['pages' => [
        ['width' => 40, 'height' => 30, 'elements' => $elements],
        ['width' => 58, 'height' => 40, 'copies' => 3, 'elements' => [['type' => 'text', 'content' => '整数', 'x' => 20, 'y' => 34]]],
    ]]);
    // Keep the literal wire format under test, including variables and per-page copies.
    $expected = '<PAGE><SIZE>40,30</SIZE>'
        . '<TEXT x="12" y="24" w="1" h="1" r="0">{{imei}}</TEXT>'
        . '<QRC x="12" y="24" s="2" e="L">{{imei}}</QRC>'
        . '<BC128 x="12" y="24" h="60" s="1" n="1" w="1">{{imei}}</BC128>'
        . '<IMG x="12" y="24" w="50"></IMG><L x="12" y="24" w="4" h="2"></L>'
        . '<SEQ x="12" y="24" xe="92" ye="64" s="4"></SEQ>'
        . '<SEQ x="6" y="6" xe="90" ye="45" s="4"></SEQ></PAGE>'
        . '<PAGE n="3"><SIZE>58,40</SIZE><TEXT x="20" y="34" w="1" h="1" r="0">整数</TEXT></PAGE>';
    same($expected, $xml, 'all element types, rectangle endpoints and multipage copies');
    same('<PAGE><SIZE>58,40</SIZE><TEXT x="0" y="0" w="1" h="1" r="0"></TEXT></PAGE>',
        $converter->jsonToXml(['elements' => [['type' => 'text']]]), 'default origin unchanged');

    $renderer = new TemplateRenderService();
    $layout = ['width' => 40, 'height' => 30, 'elements' => [
        ['type' => 'text', 'x' => 12.49, 'y' => 6.75, 'content' => '{{model}}'],
        ['type' => 'rectangle', 'x' => 5.75, 'y' => 6.25, 'xe' => '89.75', 'ye' => 45.125],
    ]];
    $original = $layout;
    $html = $renderer->renderToHtml($layout, ['model' => '演示型号'], 1.5);
    same(true, str_contains($html, 'left: 9px; top: 5.25px;'), 'preview text uses same rounded dots as printer before scaling');
    same(true, str_contains($html, '演示型号'), 'variable replacement preserved');
    same(true, str_contains($html, 'left: 4.5px; top: 4.5px; width: 63px; height: 29.25px;'), 'preview rectangle rounds endpoints before scaling');
    $converter->jsonToXml($layout);
    same($original, $layout, 'preview & export do not mutate the editable coordinates');

    foreach (['x', 'y', 'xe', 'ye'] as $key) {
        $invalid = ['elements' => [['type' => 'rectangle', $key => 'not-a-number']]];
        foreach ([[$converter, 'jsonToXml'], [$renderer, 'renderToHtml']] as $action) {
            try { $action($invalid); }
            catch (\core\exception\CommonException $e) {
                same(true, $e->getMessage() !== '', 'invalid coordinate has a readable error');
                continue;
            }
            throw new \RuntimeException("Invalid {$key} should not silently print at zero in {$action[1]}");
        }
    }
    echo "PASS: XML and HTML use the same dot positions, single/multi-page, valid and invalid coordinates. No printer contacted.\n";
}
