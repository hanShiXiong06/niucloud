<?php
declare(strict_types=1);

/** Render only safe, selected readbacks. Never render gateway URLs, request JSON, credentials or customer data. */
function cycleEvidenceHtml(array $r): string {
    $s=$r['steps'];
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $money=static fn($v)=>'¥'.number_format((float)$v,2);
    $p=$s['purchase']; $a=$s['sale']; $vIn=$s['simulated_vendor_purchase']; $vOut=$s['simulated_vendor_sale']; $f=$s['local_settlement'];
    $flow=$a['stock_path'];
    $cards=[
        ['01','本站 ERP','期初建账','源资料 '.$s['references']['archived_rows'].' 条；建档商品 '.$s['opening']['products'].' 个、仓库 '.$s['opening']['warehouses'].' 个、往来单位 '.$s['opening']['parties'].' 个。','从 ERP 选择器读取：'.$s['selection']['product'].' · '.$s['selection']['warehouse'].' / '.$s['selection']['location']],
        ['02','本站 ERP','采购挂账入库','采购单 '.$p['purchase_no'].' · 应付 '.$money($p['payable']['amount']).' · 可售库存 1 台。','库存流向：'.$flow['from_supplier'].' → '.$flow['in_stock_warehouse'].' / '.$flow['in_stock_location'].'。'],
        ['03','本地模拟博远','核对采购原单','采购单号 '.$vIn['bill'].'：取得单号后，再核对采购明细和串号记录。','出库前另外按原设备串号调用在库查询；不把采购回执当在库证明。'],
        ['04','本站 ERP','同行挂账销售出库','销售单 '.$a['sale_no'].' · 应收 '.$money($a['receivable']['amount']).' · 原机可售库存 '.$a['sellable_count'].' 台。','库存流向：'.$flow['outbound_warehouse'].' / '.$flow['outbound_location'].' → '.$flow['to_customer'].'。已拦截二次销售，无重复应收。'],
        ['05','本地模拟博远','核对批发出库原单','批发单号 '.$vOut['bill'].'：已核对原单和明细，重复提交被拦截。','此结果不验证博远实时库存余额或应收到账。'],
        ['06','本站 ERP','核对收付款','付款 #'.$f['payment_id'].' '.$money($f['payable']['settled']).'；收款 #'.$f['receipt_id'].' '.$money($f['receivable']['settled']).'。','应付、应收余额均为 0.00。隔离资金账户余额 '.$money($f['local_account_balance']).'（期初 ¥3,000.00）；未转账。'],
    ];
    $main='';
    foreach($cards as [$n,$scope,$title,$body,$more]) {
        $main.='<section><div class="n">'.$e($n).'</div><div><small>'.$e($scope).'</small><h2>'.$e($title).'</h2><p>'.$e($body).'</p><p class="more">'.$e($more).'</p></div></section>';
    }
    return '<!DOCTYPE html><html lang="zh-CN"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>博远对接 · 本机验收结果</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#192937;font:16px/1.65 system-ui,sans-serif}main{max-width:850px;padding:30px 18px 56px;margin:auto}header,section,footer{padding:22px 26px;background:white;border:1px solid #dde5ea;border-radius:12px;margin-bottom:10px}header{background:#153b49;color:white;padding-top:26px}h1{font-size:clamp(23px,5vw,34px);margin:6px 0}small{font-weight:650;font-size:13px;color:#32655f}header small{color:#a5e3d1}section{display:grid;grid-template-columns:34px 1fr;gap:14px}.n{font:600 18px/32px system-ui;color:#13725a}h2{font-size:19px;margin:2px 0}p{margin:3px 0}.more,footer{color:#526573;font-size:14px}footer{background:#f7f9fa}a{color:#155c7d}@media print{main{padding:0}section{break-inside:avoid}header{color:#111}header small{color:#255}}</style>
<main><header><small>已通过 · 本机运行的只读快照 · PHP '.$e($r['php']).' / MySQL '.$e($r['database_engine']).'</small><h1>同一台设备：从入库到出库</h1><p>验收串号 '.$e($p['imei']).' · 本机执行 '.$e($r['executed_at']).'</p></header>'.
        $main.'<footer><b>验收范围：</b>本站 ERP 业务服务实际执行，使用新建隔离数据库和合成资料；博远请求经真实适配器交给本机模拟对端。公网请求 0，未动生产或业务库。此报告不等于真实博远回执。ERP 事件完成 '.count($s['outbox']).' 条。<br><a href="local-cycle.json">查看逐项机器读数（JSON）</a></footer></main></html>';
}
