<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use think\facade\Db;
final class NoticeService
{
    public function send(int $site,int $id): void
    {
        $config=(new ConfigService())->get($site); $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->find();
        if (!$task || !in_array($task['status'],['blocked','rejected','partial','attention'],true) || $task['notice_at']>time()-900) return;
        $where=[['site_id','=',$site],['id','=',$id],['notice_at','=',$task['notice_at']]];
        if (!Db::name('hsx_boyuan_task')->where($where)->update(['notice_at'=>time()])) return;
        $state='unavailable';
        if ($task['mode']==='mock') $state='simulated';
        elseif ($config['assignee_uid']>0) try {
            foreach (event('HsxBusinessTaskAssigned',[
                'site_id'=>$site,'assignee_uid'=>$config['assignee_uid'],'event_id'=>'hsx_boyuan:'.$site.':'.$id.':'.$task['revision'],
                'source_plugin'=>'hsx_boyuan','source_type'=>'sync_task','source_id'=>$id,'title'=>'博远对接需要处理','business_no'=>$task['business_no'],
                'description'=>'请检查任务中的阻塞原因和博远实际结果，不要重复录单。',
                'target'=>['plugin'=>'hsx_boyuan','route_key'=>'hsx_boyuan.workbench','web_path'=>'addon/hsx_boyuan/pages/workbench/index?task_id='.$id],
            ]) as $result) if (is_array($result) && (!empty($result['queued']) || !empty($result['duplicate']))) $state='queued';
        } catch (\Throwable $e) { $state='retry'; }
        Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['notice_status'=>$state]);
        TaskService::audit($site,$id,$task['connection_key'],'notice',$state,match($state){'simulated'=>'模拟提醒，不发送真实消息','queued'=>'提醒已入队，不代表员工已收到','retry'=>'提醒暂时失败，后台待办仍保留',default=>'提醒未送出，请检查责任人、消息插件及员工绑定'});
    }
}
