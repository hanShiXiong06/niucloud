<?php

namespace app\listener\qrcode;

use app\listener\notice_template\BaseNoticeTemplate;
use app\service\core\weapp\CoreWeappConfigService;
use app\service\core\weapp\CoreWeappService;

/**
 * 生成小程序二维码
 */
class WeappQrcodeListener extends BaseNoticeTemplate
{

    public function handle(array $params)
    {
        if ('weapp' == $params[ 'channel' ]) {
            if ($this->hasWeappConfig((int)$params[ 'site_id' ])) {
                try {
                    return ( new CoreWeappService() )->qrcode($params[ 'site_id' ], $params[ 'page' ], $params[ 'data' ], $params[ 'filepath' ]);
                } catch (\Throwable $e) {
                    return $this->createUrlQrcode($params);
                }
            }

            return $this->createUrlQrcode($params);
        }
    }

    private function hasWeappConfig(int $site_id): bool
    {
        $config = ( new CoreWeappConfigService() )->getWeappConfig($site_id);
        if (!empty($config[ 'is_authorization' ])) {
            return true;
        }

        return !empty($config[ 'app_id' ]) && !empty($config[ 'app_secret' ]);
    }

    private function createUrlQrcode(array $params)
    {
        $url = (string)($params[ 'url' ] ?? '');
        $page = (string)($params[ 'page' ] ?? '');
        $data = $params[ 'data' ] ?? [];
        $path = $params[ 'filepath' ] ?? '';
        $outfile = $params[ 'outfile' ] ?? true;

        if ($outfile === false) {
            $path = false;
        }
        if (!empty($page)) {
            $url = rtrim($url, '/') . '/' . ltrim($page, '/');
        }
        if (!empty($data)) {
            $scene = [];
            foreach ($data as $v) {
                if (!isset($v[ 'key' ], $v[ 'value' ])) {
                    continue;
                }
                $scene[] = $v[ 'key' ] . '=' . $v[ 'value' ];
            }
            if (!empty($scene)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . implode('&', $scene);
            }
        }

        ob_start();
        \core\util\QRcode::png($url, $path, QR_ECLEVEL_L, 10, 1);
        if ($outfile === false) {
            $img = ob_get_contents();
            $path = 'data:image/png;base64,' . base64_encode($img);
        }
        ob_end_clean();
        ob_flush();

        return $path;
    }
}
