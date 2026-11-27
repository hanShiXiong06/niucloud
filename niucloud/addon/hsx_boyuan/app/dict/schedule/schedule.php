<?php
return [['key'=>'hsx_boyuan_process','name'=>'博远异步对接与异常提醒','desc'=>'按连接环境处理任务、核验回执及提醒；默认关闭',
    'time'=>['type'=>'min','min'=>1],'class'=>'addon\\hsx_boyuan\\app\\job\\Process','function'=>'doJob']];
