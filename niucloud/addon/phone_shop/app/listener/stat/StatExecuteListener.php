<?php
declare (strict_types = 1);

namespace addon\phone_shop\app\listener\stat;


use addon\phone_shop\app\job\stat\ShopStatJob;

/**
 *统计执行
 */
class StatExecuteListener
{
    /**
     * 统计
     * @param $data
     * @return boolean
     */
    public function handle($data)
    {
        //ShopStatJob::dispatch(['site_id' => 1], secs: 10);
        return true;
    }
}
