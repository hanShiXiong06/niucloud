<?php
namespace addon\recycle\app\service\api\recycle_price;

use addon\recycle\app\service\core\RecyclePrice\CoreRecyclePriceService;
//  addon/recycle/app/service/core/RecyclePrice/CoreRecyclePriceService.php
use core\base\BaseAdminService;
use addon\recycle\app\service\core\RecyclePrice\HttpClient;

class RecycleCategoryService extends BaseAdminService
{
    

    // public function __construct()
    // {
    //     parent::__construct();
    //     $this->model = new RecyclePrice();
    // }
    
    public function getTree(){
       
        return ( new CoreRecyclePriceService() )->getTree();
    }
    // 获取 getModel 机型 列表
    public function getModel ($params){
       
        // return $params;
        // 这暂时需要通过网络请求 获取其他 服务器的数据 
        try {
            // 创建 HttpClient 实例
            $client = new HttpClient();
        
            // 设置请求头（如果需要）
            // $client->setDefaultHeaders(['Authorization' => 'Bearer YOUR_TOKEN']);
           //  'channel'=>'weixin'
            
            $client->setDefaultHeaders(['channel' =>  'weixin']);
            // 发送 GET 请求
            $getResponse = $client->get('prod/evaluate-good/getModelByPage', ['typeId' => (int)$params['typeId'],'brandId'=>(int)$params['brandId'],'limit'=>(int)$params['limit'],'page'=>(int)$params['page']]);
            //  print_r($getResponse);
            return $getResponse['data'];
            // print_r($getResponse);
        
            // 发送 POST 请求
            // $postResponse = $client->post('https://api.example.com/data', ['key1' => 'value1', 'key2' => 'value2']);
            //print_r($postResponse);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    // 获取getQuestionList 回收型号的机框问题列表
    public function getQuestionList($params){
        // 请求 /prod/evaluate-question/getModelEvaluateConfig?modelId=4184
        try {
            // 创建 HttpClient 实例
            $client = new HttpClient();
            $client->setDefaultHeaders(['channel' =>  'weixin']);
            // 发送 GET 请求
            $getResponse = $client->get('prod/evaluate-question/getModelEvaluateConfig', ['modelId' => $params['modelId']]);
            return $getResponse['data'];
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
    // 获取 getCalculatePrice 回收价格
    // 参数 {"modelId":4184,"questionList":[{"questionId":32653,"answerIdList":[332319]},{"questionId":32654,"answerIdList":[332323]},{"questionId":32655,"answerIdList":[332327]},{"questionId":32656,"answerIdList":[332330]},{"questionId":32657,"answerIdList":[332342]},{"questionId":32658,"answerIdList":[332338]},{"questionId":32659,"answerIdList":[332346]},{"questionId":32660,"answerIdList":[332348]}]}
    // url /prod/evaluate-calculate/calculatePrice
    public function getCalculatePrice($params){
        // 请求 /prod/evaluate-question/getModelEvaluateConfig?modelId=4184
        try {
            // 创建 HttpClient 实例
            $client = new HttpClient();
            $client->setDefaultHeaders(['channel' =>  'weixin']);
            // 发送 POST 请求
            // 
           
            $postResponse = $client->post('/prod/evaluate-calculate/calculatePrice',$params);
            return $postResponse['data'];;
        }
        catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}