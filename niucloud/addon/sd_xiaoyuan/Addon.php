<?php

namespace addon\sd_xiaoyuan;

use think\facade\Db;

class Addon
{
    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        $tables = [
            'xiaoyuan_campus',
            'xiaoyuan_runner',
            'xiaoyuan_order',
            'xiaoyuan_order_log',
            'xiaoyuan_evaluate',
            'xiaoyuan_address',
            'xiaoyuan_coupon',
            'xiaoyuan_coupon_record',
            'xiaoyuan_vip',
            'xiaoyuan_runner_balance_log',
            'xiaoyuan_withdraw',
            'xiaoyuan_appeal',
            'xiaoyuan_config'
        ];
        
        $prefix = config('database.connections.mysql.prefix');
        
        foreach ($tables as $table) {
            $tableName = $prefix . $table;
            Db::execute("DROP TABLE IF EXISTS `{$tableName}`");
        }
        
        return true;
    }

    public function upgrade()
    {
        try {
            $prefix = config('database.connections.mysql.prefix');
            
            // 升级DIY页面配置
            $this->upgradeDiyPages($prefix);
            
        } catch (\Throwable $e) {
            // 升级补丁失败不阻塞安装流程
        }

        return true;
    }
    
    /**
     * 升级DIY页面配置
     */
    private function upgradeDiyPages($prefix)
    {
        $rows = Db::name('diy_page')
            ->whereIn('type', ['DIY_SD_XIAOYUAN_INDEX', 'DIY_SD_XIAOYUAN_MEMBER'])
            ->field('id,value')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $value = $row['value'] ?? '';
            if (empty($value)) continue;

            $data = json_decode($value, true);
            if (!is_array($data)) continue;

            $global = $data['global'] ?? [];
            if (!is_array($global)) $global = [];

            if (empty($global['topStatusBar']) || !is_array($global['topStatusBar'])) {
                $global['topStatusBar'] = [
                    'control' => true,
                    'isShow' => true,
                    'bgColor' => '#ffffff',
                    'rollBgColor' => '#ffffff',
                    'style' => 'style-1',
                    'styleName' => '风格1',
                    'textColor' => '#333333',
                    'rollTextColor' => '#333333',
                    'textAlign' => 'center',
                    'inputPlaceholder' => '请输入搜索关键词',
                    'imgUrl' => '',
                    'link' => ['name' => '']
                ];
            } else {
                $global['topStatusBar']['control'] = $global['topStatusBar']['control'] ?? true;
                $global['topStatusBar']['isShow'] = $global['topStatusBar']['isShow'] ?? true;
                $global['topStatusBar']['bgColor'] = $global['topStatusBar']['bgColor'] ?? '#ffffff';
                $global['topStatusBar']['rollBgColor'] = $global['topStatusBar']['rollBgColor'] ?? $global['topStatusBar']['bgColor'];
                $global['topStatusBar']['textColor'] = $global['topStatusBar']['textColor'] ?? '#333333';
                $global['topStatusBar']['rollTextColor'] = $global['topStatusBar']['rollTextColor'] ?? $global['topStatusBar']['textColor'];
                $global['topStatusBar']['textAlign'] = $global['topStatusBar']['textAlign'] ?? 'center';
                $global['topStatusBar']['inputPlaceholder'] = $global['topStatusBar']['inputPlaceholder'] ?? '请输入搜索关键词';
                $global['topStatusBar']['imgUrl'] = $global['topStatusBar']['imgUrl'] ?? '';
                $global['topStatusBar']['link'] = $global['topStatusBar']['link'] ?? ['name' => ''];
            }

            if ($global['topStatusBar']['style'] ?? '' === 'style-1') {
                $global['topStatusBar']['style'] = 'style-2';
            }

            if (empty($global['bottomTabBar']) || !is_array($global['bottomTabBar'])) {
                $global['bottomTabBar'] = [
                    'control' => true,
                    'isShow' => true,
                    'designNav' => ['title' => '', 'key' => '']
                ];
            } else {
                $global['bottomTabBar']['control'] = $global['bottomTabBar']['control'] ?? true;
                $global['bottomTabBar']['isShow'] = $global['bottomTabBar']['isShow'] ?? true;
                $global['bottomTabBar']['designNav'] = $global['bottomTabBar']['designNav'] ?? ['title' => '', 'key' => ''];
            }

            if (empty($global['copyright']) || !is_array($global['copyright'])) {
                $global['copyright'] = [
                    'control' => true,
                    'isShow' => false,
                    'textColor' => '#ccc'
                ];
            } else {
                $global['copyright']['control'] = $global['copyright']['control'] ?? true;
                $global['copyright']['isShow'] = $global['copyright']['isShow'] ?? false;
                $global['copyright']['textColor'] = $global['copyright']['textColor'] ?? '#ccc';
            }

            if (empty($global['popWindow']) || !is_array($global['popWindow'])) {
                $global['popWindow'] = [
                    'imgUrl' => '',
                    'imgWidth' => '',
                    'imgHeight' => '',
                    'count' => 'once',
                    'show' => 0,
                    'link' => ['name' => '']
                ];
            }

            if (empty($global['template']) || !is_array($global['template'])) {
                $global['template'] = [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => ['top' => 0, 'bottom' => 0, 'both' => 0]
                ];
            }

            $data['global'] = $global;

            Db::name('diy_page')->where('id', $row['id'])->update([
                'value' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'update_time' => time()
            ]);
        }
    }
}
