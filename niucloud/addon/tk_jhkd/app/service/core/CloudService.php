<?php

namespace addon\tk_jhkd\app\service\core;

use app\service\core\addon\CoreAddonService;
use core\base\BaseAdminService;
use core\exception\AddonException;
use core\exception\CommonException;
use think\db\exception\PDOException;
use think\Exception;
use think\facade\Db;
use think\facade\Request;
use app\service\core\niucloud\CoreNiucloudConfigService;

/**
 * 公用服务层
 */
class CloudService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->mainInfo = (new CoreNiucloudConfigService())->getNiucloudConfig();
        $this->addonInfo = (new CoreAddonService())->getAddonConfig('tk_jhkd');
    }

    /**
     * 获取授权码
     */
    public function getAuthCode()
    {
        $fromPath=root_path() . 'addon' . DIRECTORY_SEPARATOR . 'tk_jhkd' . DIRECTORY_SEPARATOR . 'auth.crt';
        if (file_exists($fromPath)) {
            $content = file_get_contents($fromPath);
            return $content=='100000'?'':$content;
        } else {
            file_put_contents($fromPath,100000);
            return '';
        }
    }

    public function auth()
    {
        $addonInfo = $this->addonInfo;
        $data = [
            'domain' => Request::domain(),
            'ip' => Request::ip(),
            'addon_key' => $addonInfo['key'],
            'version' => $addonInfo['version'],
            'main_code' => $this->mainInfo['auth_code'] == '' ? '100000' : $this->mainInfo['auth_code'],
            'main_key' => $this->mainInfo['auth_secret'] == '' ? '100000' : $this->mainInfo['auth_secret'],
            'is_saas' => '1'
        ];
        $auth_code = $this->getAuthCode();
        if ($auth_code != '') {
            $data['main_code'] = '100000';
            $data['main_key'] = '100000';
            $data['auth_code'] = $auth_code;
        }
        $res = $this->cloudPost('api/tkcloud/auth', $data);
        if (isset($res['data']['action']) && $res['data']['action'] != 'success') {
            throw new CommonException($res['data']['msg']);
        }
        return $res;
    }

    public function updateVersion()
    {
        $addonInfo = (new CoreAddonService())->getAddonConfig('tk_pan');
        $data = [
            'domain' => Request::domain(),
            'ip' => Request::ip(),
            'addon_key' => $addonInfo['key'],
            'version' => $addonInfo['version'],
            'main_code' => $this->mainInfo['auth_code'] == '' ? '100000' : $this->mainInfo['auth_code'],
            'main_key' => $this->mainInfo['auth_secret'] == '' ? '100000' : $this->mainInfo['auth_secret'],
            'is_saas' => '1'
        ];
        $auth_code = $this->getAuthCode();
        if ($auth_code != '') {
            $data['main_code'] = '100000';
            $data['main_key'] = '100000';
            $data['auth_code'] = $auth_code;
        }
        $res = $this->cloudPost('api/tkcloud/updateversion', $data);
        return $res['data'] ?? [];
    }

    public function versionList($key)
    {
        $addonInfo = $this->addonInfo;
        $data = [
            'domain' => Request::domain(),
            'ip' => Request::ip(),
            'addon_key' => $addonInfo['key'],
            'version' => $addonInfo['version'],
            'auth_code' => '100000',
            'main_code' => $this->mainInfo['auth_code'] == '' ? '100000' : $this->mainInfo['auth_code'],
            'main_key' => $this->mainInfo['auth_secret'] == '' ? '100000' : $this->mainInfo['auth_secret'],
            'is_saas' => '1'
        ];
        $auth_code = $this->getAuthCode();
        if ($auth_code != '') {
            $data['main_code'] = '100000';
            $data['main_key'] = '100000';
            $data['auth_code'] = $auth_code;
        }
        $res = $this->cloudPost('api/tkcloud/versioninfo', $data);
        return $res['data']['data'] ?? [];
    }

    public function cloudPost($method, $data = null, $headers = false)
    {
        $baseUrl = base64_decode("aHR0cDovLzQ3LjEwOC42NS41MToyNzM1Lw==");
        $url = $baseUrl . $method;
        $curl = curl_init();
        //设置header头
        if ($headers !== false) curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new Exception($err);
        } else {
            return json_decode($result, true);
        }
    }

    public static function executeSql(string $sql_file): bool
    {
        if (is_file($sql_file)) {
            $sql = file_get_contents($sql_file);
            // 执行sql
            $sql_arr = parse_sql($sql);
            if (!empty($sql_arr)) {
                $prefix = config('database.connections.mysql.prefix');
                Db::startTrans();
                try {
                    foreach ($sql_arr as $sql_line) {
                        $sql_line = trim($sql_line);
                        if (!empty($sql_line)) {
                            $sql_line = str_ireplace('{{prefix}}', $prefix, $sql_line);
                            $sql_line = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $sql_line);
                            Db::execute($sql_line);
                        }
                    }
                    Db::commit();
                    return true;
                } catch (PDOException $e) {
                    Db::rollback();
                    throw new AddonException($e->getMessage());
                }
            }
        }
        return true;
    }
}