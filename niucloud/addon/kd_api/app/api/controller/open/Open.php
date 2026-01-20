<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\api\controller\open;

use addon\kd_api\app\service\api\OpenService;
use core\base\BaseApiController;

class Open extends BaseApiController
{
    public function getLink()
    {
        $data = $this->request->params([
            ['content', '']
        ]);
        $data=$data['content'];
        $res=(new OpenService())->getLink($data);
        return success($res);
    }
    public function getOrder()
    {
        $data = $this->request->params([
            ['content', '']
        ]);
        $data=$data['content'];
        $res=(new OpenService())->getOrder($data);
        return success($res);
    }
}
