<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;

/** Pure quantity/amount rules. Reporting a batch is distinct from fulfilling the whole demand. */
final class DealProgress
{
    public static function requirements(array $lead): array
    {
        $items=Data::decode($lead['requirements_json']??'');
        if (!$items) $items=[['catalog_id'=>'single','title'=>($lead['model']??'')?:'原工单设备','quantity'=>1]];
        return $items;
    }

    private static function confirmed(array $task): array
    {
        $map=[];
        foreach(Data::decode($task['confirmed_items_json']??'') as $row) {
            if (is_array($row) && isset($row['catalog_id'])) $map[(string)$row['catalog_id']]=$row;
        }
        return $map;
    }

    public static function view(array $task,array $lead): array
    {
        $confirmed=self::confirmed($task); $selected=null; $total='0.00';
        foreach($confirmed as $row) {
            if ((int)$row['quantity']>0) $selected=$selected??(string)$row['catalog_id'];
            $total=bcadd($total,(string)$row['amount'],2);
        }
        $requirements=self::requirements($lead); $any=($lead['demand_mode']??'any')==='any';
        foreach($requirements as &$row) {
            $id=(string)$row['catalog_id']; $done=(int)($confirmed[$id]['quantity']??0);
            $row['confirmed_quantity']=$done;
            $row['remaining_quantity']=max(0,(int)$row['quantity']-$done);
            $row['confirmed_amount']=(string)($confirmed[$id]['amount']??'0.00');
            $row['alternative_not_selected']=$any && $selected!==null && $selected!==$id;
            $row['can_report']=!$row['alternative_not_selected'] && $row['remaining_quantity']>0;
        }
        unset($row);
        return ['requirements'=>$requirements,'any_selected_catalog_id'=>$any?$selected:null,'total_confirmed_amount'=>$total,
            'deal_items'=>Data::decode($task['deal_items_json']??''),'deal_history'=>Data::decode($task['deal_history_json']??''),
            'completion_status'=>$task['completion_status']??'none'];
    }

    public static function prepare(array $task,array $lead,array $input): array
    {
        // A free-text/single-device demand has the same concurrency contract.
        $base=$input['expected_revision']??null;
        if ((!is_int($base) && !is_string($base)) || !preg_match('/^(?:0|[1-9][0-9]*)$/D',(string)$base)
            || (int)$base!==(int)($task['last_verified_revision']??0)) throw new CommonException('成交进度已变化或缺少版本，请刷新后重新填写本批，未重复登记');
        $report=$input['expected_deal_revision']??null;
        if ((!is_int($report) && !is_string($report)) || !preg_match('/^(?:0|[1-9][0-9]*)$/D',(string)$report)
            || (int)$report!==(int)($task['deal_revision']??0)) throw new CommonException('成交申报已被修改，请刷新后重新提交，未覆盖新内容');
        $view=self::view($task,$lead);
        $requirements=array_column($view['requirements'],null,'catalog_id');
        $requested=$input['deal_items']??null;
        // A pre-directory single-device work order can still be explicitly recorded as one device.
        if ($requested===null && !Data::decode($lead['requirements_json']??'') && isset($input['deal_amount'])) {
            $requested=[['catalog_id'=>'single','quantity'=>1,'unit_price'=>$input['deal_amount']]];
        }
        if (!is_array($requested) || !array_is_list($requested) || !$requested || count($requested)>20) throw new CommonException('请填写本次实际成交的设备、台数和单价');
        $items=[]; $seen=[]; $amount='0.00';
        foreach($requested as $entry) {
            if (!is_array($entry)) throw new CommonException('成交明细格式不正确');
            $id=Data::text($entry,'catalog_id',100,true);
            if (!isset($requirements[$id]) || isset($seen[$id])) throw new CommonException('成交设备不属于本工单需求或重复，请重新选择');
            $seen[$id]=true; $quantity=$entry['quantity']??0;
            if ((!is_int($quantity) && !is_string($quantity)) || !preg_match('/^(?:0|[1-9]\d{0,2})$/D',(string)$quantity)) throw new CommonException('本次成交台数必须是0—999的整数');
            $quantity=(int)$quantity;
            if ($quantity===0) continue;
            $requirement=$requirements[$id];
            if (!$requirement['can_report'] || $quantity>(int)$requirement['remaining_quantity']) throw new CommonException('成交台数超过剩余需求，或不是已选定的备选机型');
            $price=Data::amount($entry['unit_price']??'');
            if (bccomp($price,'0',2)<=0) throw new CommonException('实际成交单价必须大于0');
            $line=Data::amount(bcmul($price,(string)$quantity,2));
            $amount=Data::amount(bcadd($amount,$line,2));
            $items[]=['catalog_id'=>$id,'title'=>$requirement['title'],'quantity'=>$quantity,'unit_price'=>$price,'amount'=>$line];
        }
        if (!$items) throw new CommonException('请至少填写一项大于0台的实际成交');
        if (($lead['demand_mode']??'any')==='any' && count($items)>1) throw new CommonException('任选模式本次只能成交一种备选机型；需混合采购请在派单前调整需求');
        if (array_key_exists('deal_amount',$input) && $input['deal_amount']!=='' && bccomp(Data::amount($input['deal_amount']),$amount,2)!==0) throw new CommonException('成交总额与逐项台数×单价不一致，请核对');
        if (!in_array($input['close_remaining']??false,[true,false,1,0,'1','0'],true)) throw new CommonException('请明确是否结束剩余需求');
        $closing=(bool)($input['close_remaining']??false);
        $reason=Data::text($input,'remaining_reason',1000,$closing);
        // A pending edit must invalidate an operator's already-open verification form.
        $revision=max((int)($task['deal_revision']??0),(int)($task['last_verified_revision']??0))+1;
        if ($revision>100) throw new CommonException('本工单成交批次已达上限，请联系管理员拆分后续需求');
        return ['deal_items_json'=>Data::json($items),'deal_amount'=>$amount,'deal_revision'=>$revision,
            'close_remaining'=>$closing?1:0,'remaining_reason'=>$reason];
    }

    public static function approve(array $task,array $lead,string $commission,int $uid): array
    {
        $revision=(int)$task['deal_revision'];
        if ($revision<=0 || $revision<=(int)$task['last_verified_revision']) throw new CommonException('本批成交已经核验，请刷新查看');
        $items=Data::decode($task['deal_items_json']??'');
        if (!$items) throw new CommonException('本批缺少逐项成交明细，请退回商户重新提交');
        $confirmed=self::confirmed($task); $requirements=array_column(self::requirements($lead),null,'catalog_id');
        foreach($items as $item) {
            $id=(string)$item['catalog_id'];
            if (!isset($requirements[$id])) throw new CommonException('成交明细与需求不对应，未核验');
            $quantity=(int)($confirmed[$id]['quantity']??0)+(int)$item['quantity'];
            if ($quantity>(int)$requirements[$id]['quantity']) throw new CommonException('累计成交数量超过需求，本批未核验');
            $confirmed[$id]=['catalog_id'=>$id,'title'=>$requirements[$id]['title'],'quantity'=>$quantity,
                'amount'=>bcadd((string)($confirmed[$id]['amount']??'0'),(string)$item['amount'],2)];
        }
        $full=($lead['demand_mode']??'any')==='all';
        foreach($requirements as $id=>$required) {
            $satisfied=(int)($confirmed[$id]['quantity']??0)>=(int)$required['quantity'];
            $full=($lead['demand_mode']??'any')==='all' ? $full && $satisfied : $full || $satisfied;
        }
        $closed=(bool)$task['close_remaining'];
        $status=$full||$closed?'completed':'following';
        $history=Data::decode($task['deal_history_json']??'');
        $history[]=['revision'=>$revision,'items'=>$items,'deal_no'=>$task['deal_no'],'amount'=>$task['deal_amount'],
            'commission_amount'=>$commission,'evidence'=>$task['evidence'],'verified_at'=>time(),'verified_uid'=>$uid,
            'close_remaining'=>$closed,'remaining_reason'=>$task['remaining_reason']];
        return ['status'=>$status,'completion_status'=>$full?'complete':($closed?'partial_closed':'partial'),
            'confirmed_items_json'=>Data::json(array_values($confirmed)),'deal_history_json'=>Data::json($history),
            'last_verified_revision'=>$revision,'verified_at'=>time(),'verified_uid'=>$uid,'commission_amount'=>$commission];
    }
}
