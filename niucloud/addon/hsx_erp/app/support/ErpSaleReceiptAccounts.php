<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\support;

use core\exception\CommonException;

/** 将一笔实际已收总额按入账账户拆分，各笔分别走现有应收核销服务。 */
final class ErpSaleReceiptAccounts
{
    public static function validate($rows, float $received): array
    {
        if (!is_array($rows) || !$rows || count($rows) > 30) throw new CommonException('请核对分笔收款明细');
        $result = []; $sum = 0;
        foreach ($rows as $row) {
            $id = (int)($row['account_id'] ?? 0); $amount = $row['amount'] ?? null;
            if ($id <= 0 || isset($result[$id]) || !is_scalar($amount) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',(string)$amount))
                throw new CommonException('分笔收款的账户或金额无效');
            $cents = (int)round((float)$amount * 100);
            if ($cents <= 0) throw new CommonException('每笔实际收款必须大于零');
            $sum += $cents;
            $result[$id] = ['account_id'=>$id,'amount'=>number_format($cents/100,2,'.','')];
        }
        if ($sum !== (int)round($received * 100)) throw new CommonException('分笔合计与实际收款不符');
        ksort($result,SORT_NUMERIC);
        return array_values($result);
    }
}
