<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;

use addon\hsx_lead\app\service\cooperation\PosterShareService;
use core\base\BaseApiController;
use core\exception\CommonException;

final class Poster extends BaseApiController
{
    public function generate()
    {
        $input = $this->request->param();
        // This endpoint intentionally accepts a scene, never caller-selected identity or destinations.
        if (!is_string($input['scene'] ?? null)) throw new CommonException('请选择需要生成的分享海报');
        return success(PosterShareService::fromRequest((int) $this->request->siteId())->generate(
            $input['scene'], (string) $this->request->getChannel()
        ))->header(['Cache-Control' => 'private, no-store']);
    }
}
