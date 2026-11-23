<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\tests;

use addon\hsx_boyuan\app\gateway\OpenApi;

/** State of a fully local synthetic counterparty. Refuses any operation not used by the case.
 * Separate immutable receipt, detail and serial records are read back via distinct official operations.
 */
final class LocalCycleExchange
{
    public array $calls=[];
    private array $purchases=[], $serials=[], $sales=[], $inventory=[];
    public function __construct(private array $examples,private string $account,private string $secret) {}
    public function exchange(string $path,array $params):array {
        if ($path!=='/gateway.do' || $params['big_gsdm']!==$this->account || OpenApi::sign($params,$this->secret)!==$params['sign']) throw new \RuntimeException('隔离签名或账套不匹配');
        $name=$params['service'];
        $this->calls[$name]=($this->calls[$name]??0)+1;
        if (isset($this->examples[$name])) {
            $list=array_map(static fn($row)=>$row+['is_effective'=>1],$this->examples[$name]);
            return $this->page($list,$params);
        }
        if ($name==='boyuan.biz.bill.purchase.generate' || $name==='boyuan.biz.bill.sale.generate') {
            $buy=$name==='boyuan.biz.bill.purchase.generate';
            $biz=json_decode($params['biz'],true,512,JSON_THROW_ON_ERROR); $h=$biz['head']; $lines=$biz['product_details']['add_details'];
            $line=$lines[0]; $p=$buy?'PUR':'WSA'; $serial=$line['imei'];
            if (count($lines)!==1 || $this->calls[$name]!==1 || $serial!=='990000000000010' || $line['product_id']!=='PRO01'
                || $h['warehouse_id']!=='WH01' || $h['cash_term']!==0 || array_key_exists('cash_details',$biz) || array_key_exists('sale_cash_details',$biz)
                || $h[$buy?'supplier_id':'cust_id']!==($buy?'SUP01':'CUS01') || (float)$line['amounts']!==($buy?2000.0:2500.0)) throw new \RuntimeException('测试对端不接受串号、货品、仓库、往来单位、数量或金额不符的操作');
            $rHead=$h+['id'=>$p.'_HEAD','bill_id'=>$p.'_LOCAL_1','bill_type'=>$p,'big_gsdm'=>$this->account];
            $rLine=$line+['id'=>$p.'_LINE','seq_no'=>1];
            $identity=['company_id'=>'COMP01','bill_id'=>$rHead['bill_id'],'bill_type'=>$p,'business_date'=>$h['business_date'],
                'warehouse_id'=>$h['warehouse_id'],'is_effective'=>1,'product_id'=>$line['product_id'],'qty'=>1,'unit_price'=>$line['unit_price'],'amounts'=>$line['amounts']];
            if ($buy) {
                $identity+=['supplier_id'=>$h['supplier_id'],'trans_type'=>'购'];
                $this->purchases[]=$identity+['id'=>$rLine['id'],'seq_no'=>1]; // documented UAT readback lacks head_id
                $sr=$identity+['id'=>'SN_RECORD_1','detail_id'=>$rLine['id'],'company_code'=>$this->account,'imei'=>$serial,'imei1'=>$serial,
                    'imei2'=>null,'imei3'=>null,'amount'=>$identity['amounts']];
                unset($sr['amounts']); $this->serials[]=$sr;
                $this->inventory[$serial]=['gsdm'=>$this->account,'company_id'=>'COMP01','imei'=>$serial,'imei1'=>$serial,
                    'imei2'=>null,'imei3'=>null,'product_id'=>'PRO01','warehouse_id'=>'WH01','supplier_id'=>'SUP01','qty'=>1,'imei_in_stock_status'=>1];
            } else {
                if (!isset($this->inventory[$serial])) throw new \RuntimeException('不能销售未在隔离对端入库或已出库的串号');
                unset($this->inventory[$serial]);
                // The documented wholesale query has no IMEI; link to the create receipt by both primary keys.
                $this->sales[]=$identity+['id'=>$rLine['id'],'head_id'=>$rHead['id'],'cash_term'=>0,'cust_id'=>$h['cust_id'],'manual_number'=>$h['manual_number']];
            }
            return ['return_code'=>'SUCCESS','api_response'=>['result'=>['head'=>$rHead,'product_details'=>['add_details'=>[$rLine]]]]];
        }
        if ($name==='boyuan.biz.purchases.query') return $this->page($this->purchases,$params);
        if ($name==='boyuan.biz.purchase.imeidetails.query') return $this->page(array_filter($this->serials,fn($r)=>$r['bill_id']===$params['bill_id']),$params);
        if ($name==='boyuan.biz.inventory.imeidetails.query') return $this->page(isset($this->inventory[$params['imei']])?[$this->inventory[$params['imei']]]:[],$params);
        if ($name==='boyuan.biz.sales.query') return $this->page($this->sales,$params);
        throw new \RuntimeException('未授权的隔离接口：'.$name);
    }
    private function page(array $rows,array $params):array {
        $size=(int)($params['page_size']??100); $page=(int)($params['page_no']??1);
        return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>array_values(array_slice($rows,($page-1)*$size,$size)),'total_results'=>count($rows)]];
    }
}
