<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

/** A durable simulated external service. Requests still use the normal asynchronous worker. */
final class MockTransport implements Transport
{
    public function __construct(private int $site,private string $connection,private string $scenario='success') {}
    private function read(string $key): ?array
    {
        $raw=Db::name('hsx_boyuan_mock_record')->where('site_id',$this->site)->where('connection_key',$this->connection)->where('record_key',$key)->value('value_json');
        return $raw ? Contract::decode($raw) : null;
    }
    private function write(string $key,array $value): void
    {
        $where=[['site_id','=',$this->site],['connection_key','=',$this->connection],['record_key','=',$key]];
        $old=Db::name('hsx_boyuan_mock_record')->where($where)->find(); $values=['value_json'=>Contract::json($value),'update_at'=>time()];
        if ($old) Db::name('hsx_boyuan_mock_record')->where($where)->update($values);
        else Db::name('hsx_boyuan_mock_record')->insert($values+['site_id'=>$this->site,'connection_key'=>$this->connection,'record_key'=>$key]);
    }
    public function send(string $method,string $path,array $body=[]): array
    {
        if ($method==='GET') {
            if (str_contains($path,'/operations/')) return $this->read('op:'.basename($path))['reply'] ?? ['code'=>'OPERATION_NOT_FOUND','message'=>'模拟记录不存在','data'=>[]];
            $resource=$body['resource']??'warehouses'; $name=['warehouses'=>'演示设备仓','products'=>'演示手机型号','staff'=>'演示业务员','payment_methods'=>'演示收付方式','capital_accounts'=>'演示资金账户'][$resource]??'演示档案';
            return ['code'=>'OK','data'=>['account_id'=>'MOCK','items'=>[['id'=>'MOCK_'.strtoupper($resource),'code'=>'CODE_'.strtoupper($resource),'name'=>$name,'enabled'=>true,'unit_code'=>'TAI']],'has_more'=>false,'next_cursor'=>null]];
        }
        if (!isset($body['operation_id']) && isset($body['source_financial_item_ids'])) {
            $items=[]; foreach ($body['source_financial_item_ids'] as $id) if ($record=$this->read('finance:'.$id)) $items[]=$record;
            return ['code'=>'OK','data'=>['items'=>$items,'has_more'=>false,'next_cursor'=>null]];
        }
        Contract::request($body); $key='op:'.$body['operation_id']; $hash=hash('sha256',Contract::json($body));
        $existing=$this->read($key);
        if ($existing) {
            if ($existing['hash']!==$hash) throw new Problem('IDEMPOTENCY_CONFLICT','相同操作编号的内容发生变化');
            return $existing['reply'];
        }
        $reply=Db::transaction(function () use ($key,$hash,$body): array {
            Db::name('site')->where('site_id',$this->site)->lock(true)->find();
            $existing=$this->read($key); if ($existing) return $existing['reply'];
            $data=['operation_id'=>$body['operation_id'],'operation_type'=>$body['operation_type'],'operation_status'=>'effective','result_version'=>1,'processed_at'=>date(DATE_ATOM),'errors'=>[]];
            $value=$body['data']; $suffix=strtoupper(substr(hash('sha256',$body['operation_id']),0,16));
            if ($this->scenario==='rejected') {
                $data['operation_status']='rejected';
                $data['errors']=[['code'=>'PERIOD_CLOSED','message'=>'模拟账期已关账，未创建单据','retryable'=>false,'side_effect'=>'none','external_ids'=>[]]];
            } elseif ($body['operation_type']==='party_resolve') {
                $created=($value['mode']??'match_only')==='match_or_create';
                $data+=['match_status'=>$created?'created':'matched','party'=>['id'=>'MOCK_PARTY_'.$suffix,'code'=>'MOCK_'.$suffix,'name'=>$value['name'],'role'=>$value['role'],'enabled'=>true],'created'=>$created,'candidates'=>[]];
            } elseif ($body['operation_type']==='financial_item_ensure') {
                $data['items']=[];
                foreach ($value['items'] as $item) {
                    $record=$this->read('finance:'.$item['source_financial_item_id']) ?? [
                        'source_financial_item_id'=>$item['source_financial_item_id'],'external_financial_item_id'=>'MOCK_FIN_'.substr(hash('sha256',$item['source_financial_item_id']),0,16),
                        'external_financial_item_no'=>'MOCK_FIN_NO_'.$suffix,'external_party_id'=>$item['external_party_id'],'financial_item_type'=>$item['financial_item_type'],
                        'original_amount'=>$item['amount'],'current_amount'=>$item['amount'],'settled_amount'=>'0.00','balance_amount'=>$item['amount'],'status'=>'open','version'=>1,'created'=>false,'binding_mode'=>'existing_document',
                    ];
                    $this->write('finance:'.$item['source_financial_item_id'],$record); $data['items'][]=$record;
                }
            } elseif ($body['operation_type']==='settlement_register') {
                $allocations=[];
                foreach ($value['allocations'] as $allocation) {
                    $record=$this->read('finance:'.$allocation['source_financial_item_id']);
                    if (!$record || $record['external_party_id']!==$value['external_party_id'] || $record['version']!==$allocation['expected_version']
                        || $record['balance_amount']!==$allocation['expected_balance_amount'] || Contract::cents($allocation['amount'])>Contract::cents($record['balance_amount'])) throw new Problem('BALANCE_CONFLICT','模拟账目余额或版本已变化，未登记款项');
                    $record['settled_amount']=Contract::amount(Contract::cents($record['settled_amount'])+Contract::cents($allocation['amount']));
                    $record['balance_amount']=Contract::amount(Contract::cents($record['balance_amount'])-Contract::cents($allocation['amount']));
                    $record['status']=$record['balance_amount']==='0.00'?'settled':'partially_settled'; $record['version']++;
                    $this->write('finance:'.$allocation['source_financial_item_id'],$record);
                    $allocations[]=$record+['applied_amount'=>$allocation['amount'],'external_allocation_id'=>'MOCK_ALLOC_'.count($allocations).'_'.$suffix];
                }
                $data['settlement']=['external_settlement_id'=>'MOCK_PAY_'.$suffix,'external_settlement_no'=>'MOCK_PAY_NO_'.$suffix,
                    'source_settlement_id'=>$value['source_settlement_id'],'direction'=>$value['direction'],'amount'=>$value['amount'],'funds_recorded'=>true,'allocations_applied'=>true,'allocations'=>$allocations];
            } else {
                $effect=in_array($body['operation_type'],['purchase_inbound','retail_return','wholesale_return'],true)?'in':'out'; $lines=[];
                foreach ($value['lines'] as $index=>$line) {
                    if ($this->scenario==='partial' && $index>0) break;
                    $serial=trim((string)($line['imei']??'')) ?: $line['sn'];
                    $stock=$this->read('stock:'.hash('sha256',$serial));
                    if (($effect==='out' && (!$stock || $stock['state']!=='in')) || ($effect==='in' && $stock && $stock['state']==='in')) throw new Problem('SERIAL_CONFLICT','模拟库存串号状态冲突，未生成重复单据');
                    $this->write('stock:'.hash('sha256',$serial),['state'=>$effect,'device_ref'=>$line['device_ref']]);
                    $lines[]=['source_line_id'=>$line['source_line_id'],'device_ref'=>$line['device_ref'],'external_line_id'=>'MOCK_LINE_'.$suffix.'_'.$index,
                        'serial'=>$serial,'warehouse_id'=>$line['warehouse_id'],'quantity'=>1,'line_amount'=>$line['line_amount'],'inventory_effect'=>$effect,'inventory_applied'=>true];
                }
                $data['documents']=[['external_document_id'=>'MOCK_DOC_'.$suffix,'external_document_no'=>'MOCK_NO_'.$suffix,'document_type'=>$body['operation_type'],
                    'document_status'=>'effective','source_document_id'=>$value['source_document_id'],'batch_ref'=>$value['batch_ref'],
                    'currency'=>'CNY','total_amount'=>Contract::amount(array_sum(array_map(static fn($l)=>Contract::cents($l['line_amount']),$lines))),
                    'inventory_applied'=>true,'funds_effect'=>'none','external_settlement_ids'=>[],'lines'=>$lines]];
                if ($this->scenario==='partial' && count($lines)<count($value['lines'])) { $data['operation_status']='partial'; $data['errors']=[['code'=>'SIMULATED_PARTIAL','message'=>'模拟部分成功，请逐行核对','side_effect'=>'partial','retryable'=>false]]; }
            }
            $reply=['request_id'=>'MOCK_REQ_'.$suffix,'code'=>'OK','message'=>'模拟回执，未访问博远','data'=>$data];
            $this->write($key,['hash'=>$hash,'reply'=>$reply]); return $reply;
        });
        if ($this->scenario==='timeout_after_commit') throw new Problem('REMOTE_UNCERTAIN','模拟响应丢失，远端已经处理，请查询原操作','unknown');
        if ($this->scenario==='malformed') return ['code'=>'OK','data'=>['operation_id'=>'INCORRECT','operation_status'=>'effective']];
        return $reply;
    }
}
