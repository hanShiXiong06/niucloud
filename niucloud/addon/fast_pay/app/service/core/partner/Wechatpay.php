<?php

namespace addon\fast_pay\app\service\core\partner;

use app\dict\pay\OnlinePayDict;
use app\dict\pay\RefundDict;
use app\dict\pay\TransferDict;
use core\exception\PayException;
use core\pay\BasePay;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use think\facade\Log;
use think\Response;
use Throwable;
use Yansongda\Pay\Exception\ContainerException;
use Yansongda\Pay\Exception\InvalidParamsException;
use Yansongda\Pay\Exception\ServiceNotFoundException;
use Yansongda\Pay\Pay;
use Yansongda\Supports\Collection;


/**
 * 微信支付管理驱动类  todo  注意:暂时不考虑合单类业务
 * Class FileDriver
 * @package core\file
 */
class Wechatpay
{

    /**
     * @param array $config

     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $config['mch_secret_cert'] = url_to_path($config['mch_secret_cert'] ?? '');
        $config['mch_public_cert_path'] = url_to_path($config['mch_public_cert_path'] ?? '');
        $config['mp_app_id']=$config['app_id'];
        $config['mini_app_id']=$config['app_id'];

        // 选填-默认为正常模式。可选为： MODE_NORMAL, MODE_SERVICE
        $config['mode'] = Pay::MODE_SERVICE;
        Pay::config($this->payConfig($config, 'wechat'));
    }

    /**
     * 支付
     * @param $params
     * @return void
     */
    public function pay($params) {
        $out_trade_no = $params[ 'out_trade_no' ];//交易流水号
        $site_id = $params[ 'site_id' ];
        return ['url' => '/addon/fast_pay/pages/pay/index', 'param' => ['out_trade_no' => $out_trade_no ]];
    }
    /**
     * 公众号支付
     * @param array $params
     * @return mixed|Collection
     */
    public function mp(array $params)
    {
        $result = $this->returnFormat(Pay::wechat()->mp([
            'out_trade_no' => $params['out_trade_no'],
            'description' => $params['body'],
            'amount' => [
                'total' => $params['money'],
            ],
            'payer' => [
                'sub_openid' => $params['openid'],
            ],
        ]));
        $code = $result['code'] ?? 0;
        if ($code == 0) return $result;
        //支付错误抛出
        throw new PayException($result['message']);
    }


    /**
     * 手机网页支付
     * @param array $params
     * @return mixed
     */
    public function wap(array $params)
    {
        $order = [
            'out_trade_no' => $params['out_trade_no'],
            'description' => $params['body'],
            'amount' => [
                'total' => $params['money'],
            ],
            'scene_info' => [
                'payer_client_ip' => request()->ip(),
                'h5_info' => [
                    'type' => 'Wap',
                ]
            ],
        ];
        //这儿有些特殊, 默认情况下，H5 支付所使用的 appid 是微信公众号的 appid，即配置文件中的 mp_app_id 参数，如果想使用关联的小程序的 appid，则只需要在调用参数中增加 ['_type' => 'mini'] 即可
        if (!empty($order['type'])) {
            $order['_type'] = 'mini'; // 注意这一行
        }
        return $this->returnFormat(Pay::wechat()->h5($order));
    }

    public function web(array $params)
    {

    }

    /**
     * app支付
     * @param array $params
     * @return mixed|ResponseInterface
     */
    public function app(array $params)
    {
        return $this->returnFormat(Pay::wechat()->app([
            'out_trade_no' => $params['out_trade_no'],
            'description' => $params['body'],
            'amount' => [
                'total' => $params['money'],
            ],
        ]));
    }

    /**
     * 小程序支付
     * @param array $params
     * @return mixed|ResponseInterface
     */
    public function mini(array $params)
    {
        return $this->returnFormat(Pay::wechat()->mini([
            'out_trade_no' => $params['out_trade_no'],
            'description' => $params['body'],
            'amount' => [
                'total' => $params['money'],
                'currency' => 'CNY',//一般是人民币
            ],
            'payer' => [
                'sub_openid' => $params['openid'],
            ]
        ]));
    }

    /**
     * 付款码支付
     * @param array $params
     * @return mixed|Collection
     */
    public function pos(array $params)
    {
        $order = [
            'out_trade_no' => $params['out_trade_no'],
            'body' => $params['body'],
            'total_fee' => $params['money'],
            'spbill_create_ip' => request()->ip(),
            'auth_code' => $params["auth_code"],
        ];
        $result = Pay::wechat()->pos($order);
        return $this->returnFormat($result);
    }

    /**
     * 扫码支付
     * @param array $params
     * @return mixed|Collection
     */
    public function scan(array $params)
    {
        return $this->returnFormat(Pay::wechat()->scan([
            'out_trade_no' => $params['out_trade_no'],
            'description' => $params['body'],
            'amount' => [
                'total' => $params['money'],
            ],
        ]));
    }

    /**
     * 转账(微信的转账是很多笔的)
     * @param array $params
     * @return array
     */
    public function transfer(array $params)
    {
        //这儿的批次信息可能是这儿生成的,但依然需要记录
        $order = [
            'out_batch_no' => $params['transfer_no'] . '',//
            'batch_name' => $params['remark'],
            'batch_remark' => $params['remark'],
        ];
        $transfer_list = $params['transfer_list'];
        //单笔转账
        if (empty($transfer_list)) {
            $transfer_list = [
                [
                    'transfer_no' => $params['transfer_no'] . '1',
                    'money' => (int)$params['money'],
                    'remark' => $params['remark'],
                    'openid' => $params['to_no']
                ]
            ];
        }
        $total_amount = 0;
        $total_num = 0;

        foreach ($transfer_list as $k => $v) {
            $item_transfer = [
                'out_detail_no' => $params['transfer_no'] . $k,
                'transfer_amount' => (int)$v['money'],
                'transfer_remark' => $v['remark'],
                'openid' => $v['openid'],
            ];
            $total_amount += (int)$v['money'];
            $total_num++;
            if (!empty($v['user_name'])) {
                $item_transfer['user_name'] = $v['user_name'];// 明文传参即可，sdk 会自动加密
            }
            $order['transfer_detail_list'][] = $item_transfer;
        }
        $order['total_amount'] = $total_amount;
        $order['total_num'] = $total_num;
        $result = $this->returnFormat(Pay::wechat()->transfer($order));

        if (!empty($result['code'])) {
//            if($result['code'] == 'PARAM_ERROR'){
//                throw new PayException();
//            }else if($result['code'] == 'INVALID_REQUEST'){
//                throw new PayException();
//            }
            if ($result['code'] == 'INVALID_REQUEST') {
                throw new PayException(700010);
            }
            throw new PayException($result['message']);
        }
        return ['batch_id' => $result['batch_id'], 'out_batch_no' => $result['out_batch_no'], 'status' => TransferDict::SUCCESS];
    }

    /**
     * 支付关闭
     * @param string $out_trade_no
     * @return void
     * @throws ContainerException
     * @throws InvalidParamsException
     * @throws ServiceNotFoundException
     */
    public function close(string $out_trade_no)
    {
        try {
            $result = Pay::wechat()->close([
                'out_trade_no' => $out_trade_no,
            ]);
            return $this->returnFormat($result);
        }catch(Throwable $e){
            return false;
        }
        return true;
    }

    /**
     * 退款
     * @param array $params
     * @return array
     * @throws ContainerException
     * @throws InvalidParamsException
     * @throws ServiceNotFoundException
     */
    public function refund(array $params)
    {
        $out_trade_no = $params['out_trade_no'];
        $money = (int)$params['money'];
        $total = $params['total'];
        $refund_no = $params['refund_no'];
        $result = Pay::wechat()->refund([
            'out_trade_no' => $out_trade_no,
            'out_refund_no' => $refund_no,
            'amount' => [
                'refund' => $money,
                'total' => $total,
                'currency' => 'CNY',
            ],
        ]);
        $result = $this->returnFormat($result);

        $refund_status_array = [
            'SUCCESS' => RefundDict::SUCCESS,
            'CLOSED' => RefundDict::FAIL,
            'PROCESSING' => RefundDict::DEALING,
            'ABNORMAL' => RefundDict::FAIL,
        ];
        return [
            'status' => $refund_status_array[$result['status']],
            'refund_no' => $refund_no,
            'out_trade_no' => $out_trade_no,
            'pay_refund_no' => $result['refund_id']
        ];
    }


    /**
     * 异步回调
     * @param string $action
     * @param callable $callback
     * @return ResponseInterface|Response
     */
    public function notify(string $action, callable $callback)
    {
        try {
            $result = $this->returnFormat(Pay::wechat()->callback());
            if ($action == 'pay') {//支付
                if ($result['event_type'] == 'TRANSACTION.SUCCESS') {
                    $pay_trade_data = $result['resource']['ciphertext'];

                    $temp_params = [
                        'trade_no' => $pay_trade_data['transaction_id'],
                        'mch_id' => $pay_trade_data['sub_mchid']??0,
                        'status' => OnlinePayDict::getWechatPayStatus($pay_trade_data['trade_state'])
                    ];

                    $callback_result = $callback($pay_trade_data['out_trade_no'], $temp_params);
                    if (is_bool($callback_result) && $callback_result) {
                        return Pay::wechat()->success();
                    }
                }
            } else if ($action == 'refund') {//退款
                if ($result['event_type'] == 'REFUND.SUCCESS') {
                    $refund_trade_data = $result['resource']['ciphertext'];
                    $refund_status_array = [
                        'SUCCESS' => RefundDict::SUCCESS,
                        'CLOSED' => RefundDict::FAIL,
                        'PROCESSING' => RefundDict::DEALING,
                        'ABNORMAL' => RefundDict::FAIL,
                    ];
                    $temp_params = [
                        'trade_no' => $refund_trade_data['transaction_id'],
                        'mch_id' => $refund_trade_data['sub_mchid']??'',
                        'refund_no' => $refund_trade_data['out_refund_no'],
                        'status' => $refund_status_array[$refund_trade_data['refund_status']],
                    ];

                    $callback_result = $callback($refund_trade_data['out_trade_no'], $temp_params);
                    if (is_bool($callback_result) && $callback_result) {
                        return Pay::wechat()->success();
                    }
                }
            }
            return $this->fail();

        } catch ( Throwable $e ) {
//            throw new PayException($e->getMessage());
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 查询普通支付订单
     * @param array $params
     * @return array|MessageInterface|Collection|null
     * @throws ContainerException
     * @throws InvalidParamsException
     * @throws ServiceNotFoundException
     */
    public function getOrder(array $params = [])
    {

        $out_trade_no = $params['out_trade_no'];
        $transaction_id = $params['transaction_id'] ?? '';
        $order = [

        ];
        if (!empty($out_trade_no)) {
            $order['out_trade_no'] = $out_trade_no;
        }
        if (!empty($transaction_id)) {
            $order['transaction_id'] = $transaction_id;
        }
        $result = Pay::wechat()->query($order);
        if (empty($result))
            return $result;
        $result = $this->returnFormat($result);
        return [
            'status' => OnlinePayDict::getWechatPayStatus($result['trade_state']),
        ];
    }

    /**
     * 查询退款单据
     * @param string|null $out_trade_no
     * @param string|null $refund_no
     * @return array|Collection|MessageInterface|null
     * @throws ContainerException
     * @throws InvalidParamsException
     * @throws ServiceNotFoundException
     */
    public function getRefund(?string $out_trade_no, ?string $refund_no = '')
    {
        $order = [
            '_action' => 'refund',
            'transaction_id' => $out_trade_no,
            'out_refund_no' => $refund_no,
            ''
        ];
        $result = Pay::wechat()->query($order);
        if (empty($result))
            return $result;
        $result = $this->returnFormat($result);
        $refund_status_array = [
            'SUCCESS' => RefundDict::SUCCESS,
            'CLOSED' => RefundDict::FAIL,
            'PROCESSING' => RefundDict::DEALING,
            'ABNORMAL' => RefundDict::FAIL,
        ];
        return [
            'status' => $refund_status_array[$result['status']],
            'refund_no' => $refund_no,
            'out_trade_no' => $out_trade_no
        ];
    }

    /**
     * 获取转账订单(todo  切勿调用)
     * @param string $transfer_no
     * @return array
     * @throws ContainerException
     * @throws InvalidParamsException
     */
    public function getTransfer(string $transfer_no, $out_transfer_no = '')
    {
        $order = [
            'out_batch_no' => $out_transfer_no,
            'out_detail_no' => $transfer_no,
            '_action' => 'transfer',
        ];

        $result = Pay::wechat()->query($order);
        $result = $this->returnFormat($result);
        //微信转账状态
        $transfer_status_array = [
            'INIT' => TransferDict::DEALING,//初始态。 系统转账校验中
            'WAIT_PAY' => TransferDict::DEALING,
            'PROCESSING' => TransferDict::DEALING,
            'FAIL' => TransferDict::FAIL,
            'SUCCESS' => TransferDict::SUCCESS,
        ];
        return [
            'status' => $transfer_status_array[$result['status']],
            'transfer_no' => $transfer_no
        ];
    }


    public function fail($message = '')
    {
        $response = [
            'code' => 'FAIL',
            'message' => $message ?: '失败',
        ];
        return response($response, 400, [], 'json');
    }
    protected function payConfig(array $config, string $type)
    {
        return array_merge(
            [
                'logger' => [
                    'enable' => true,
                    'file' => root_path('runtime') . 'paylog' . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . date('d') . '.log',
                    'level' => env('app_debug') ? 'debug' : 'info', // 建议生产环境等级调整为 info，开发环境为 debug
                    'type' => 'single', // optional, 可选 daily.
                    'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
                ],
                'http' => [ // optional
                    'timeout' => 5.0,
                ]
            ],
            [
                $type => [
                    'default' => $config
                ]
            ],
            ['_force' => true]
        );
    }

    public function returnFormat($param)
    {
        if ($param instanceof MessageInterface || $param instanceof \GuzzleHttp\Psr7\Response) {
            $return_value =  $param->getBody()->getContents();
        } else if ($param instanceof Collection) {
            $return_value = $param->toArray();
        } else {
            $return_value = $param;
        }
        return $return_value;
    }

    /**
     * 解析退款返回数据并解析
     * @param $our_trade_no
     * @param $refund_no
     * @param $status
     * @param int $success_time
     * @param string $reason
     * @return array
     */
    public function getRefundData($our_trade_no, $refund_no, $status, $success_time = 0, $reason = '')
    {
        return [
            'our_trade_no' => $our_trade_no,
            'refund_no' => $refund_no,
            'status' => $status,
            'success_time' => $success_time,
            'reason' => $reason
        ];
    }

    /**
     * 获取转账数据并解析
     * @param $transfer_no
     * @param $status
     * @param $reason
     * @return void
     */
    public function getTransferData($transfer_no, $status, $reason)
    {

    }



}
