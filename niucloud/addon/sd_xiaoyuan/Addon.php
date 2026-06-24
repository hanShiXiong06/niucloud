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
        $prefix = config('database.connections.mysql.prefix');
        try {
            Db::execute("ALTER TABLE `{$prefix}xiaoyuan_runner` ADD COLUMN `weapp_subscribe_num` int(11) NOT NULL DEFAULT 0 COMMENT '小程序订单订阅剩余次数' AFTER `accept_types`");
        } catch (\Throwable $e) {
        }
        try {
            Db::execute("ALTER TABLE `{$prefix}xiaoyuan_lost_found` ADD COLUMN `contact_wechat` varchar(50) NOT NULL DEFAULT '' COMMENT '联系微信' AFTER `contact_mobile`");
        } catch (\Throwable $e) {
        }
        try {
            Db::execute("ALTER TABLE `{$prefix}xiaoyuan_secondhand` ADD COLUMN `contact_wechat` varchar(50) NOT NULL DEFAULT '' COMMENT '联系微信' AFTER `contact_mobile`");
        } catch (\Throwable $e) {
        }
        try {
            
            // 升级DIY页面配置
            $this->upgradeDiyPages($prefix);
            $this->removeCommunityFromMenuGrid();
            $this->syncCardGridDiyConfig();
            // 补全个人中心装修页（老站点可能只有首页没有个人中心）
            $this->ensureMemberDiyPage();
            // 未改过的个人中心装修：同步最新默认菜单
            $this->syncMemberDiyDefaultIfUnchanged();
            $this->syncUniappPagesJson();
            $this->initServiceCards();
            $this->compileDiyComponentsToUniapp();
            
        } catch (\Throwable $e) {
            // 升级补丁失败不阻塞安装流程
        }

        return true;
    }
    
    private function syncUniappPagesJson()
    {
        $root = dirname(__DIR__, 3);
        $pagesJsonPath = $root . DIRECTORY_SEPARATOR . 'uni-app' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'pages.json';
        $packageFile = __DIR__ . DIRECTORY_SEPARATOR . 'package' . DIRECTORY_SEPARATOR . 'uni-app-pages.php';
        if (!is_file($pagesJsonPath) || !is_file($packageFile)) {
            return;
        }
        $uniappPages = require $packageFile;
        if (empty($uniappPages['pages'])) {
            return;
        }
        $newBlock = trim((string)$uniappPages['pages']);
        $newBlock = str_replace('PAGE_BEGIN', 'SD_XIAOYUAN_PAGE_BEGIN', $newBlock);
        $newBlock = str_replace('PAGE_END', 'SD_XIAOYUAN_PAGE_END', $newBlock);
        $content = (string)file_get_contents($pagesJsonPath);
        if (!str_contains($content, '// SD_XIAOYUAN_PAGE_BEGIN')) {
            return;
        }
        $content = preg_replace(
            '/\/\/ SD_XIAOYUAN_PAGE_BEGIN[\s\S]*?\/\/ SD_XIAOYUAN_PAGE_END/',
            $newBlock,
            $content,
            1
        );
        if ($content) {
            file_put_contents($pagesJsonPath, $content);
        }
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

    private function syncCardGridDiyConfig()
    {
        $defaultList = [
            ['cardType' => 'EXPRESS', 'name' => '快递卡', 'subtitle' => '代取快递更省心', 'isShow' => true],
            ['cardType' => 'ERRAND', 'name' => '跑腿卡', 'subtitle' => '校园跑腿一键下单', 'isShow' => true],
            ['cardType' => 'PRINT', 'name' => '打印卡', 'subtitle' => '代打印省时省力', 'isShow' => true],
        ];

        $rows = Db::name('diy_page')->field('id,value')->select()->toArray();

        foreach ($rows as $row) {
            $data = json_decode($row['value'] ?? '', true);
            if (!is_array($data) || empty($data['value']) || !is_array($data['value'])) {
                continue;
            }

            $changed = false;
            foreach ($data['value'] as &$component) {
                if (($component['componentName'] ?? '') !== 'XiaoyuanCardGrid') {
                    continue;
                }
                if (empty($component['path'])) {
                    $component['path'] = 'edit-xiaoyuan-card-grid';
                    $changed = true;
                }
                if (empty($component['componentTitle'])) {
                    $component['componentTitle'] = '次卡网格';
                    $changed = true;
                }
                if (!isset($component['isShow'])) {
                    $component['isShow'] = true;
                    $changed = true;
                }
                if (!isset($component['showApply'])) {
                    $component['showApply'] = true;
                    $changed = true;
                }
                if (empty($component['applyTitle'])) {
                    $component['applyTitle'] = '申请接单';
                    $changed = true;
                }
                if (empty($component['applySubtitle'])) {
                    $component['applySubtitle'] = '成为校园跑腿员';
                    $changed = true;
                }
                if (empty($component['list']) || !is_array($component['list'])) {
                    $component['list'] = $defaultList;
                    $changed = true;
                    continue;
                }
                $listMap = [];
                foreach ($component['list'] as $idx => $item) {
                    $type = $item['cardType'] ?? '';
                    if ($type !== '') {
                        $listMap[$type] = $idx;
                    }
                }
                foreach ($defaultList as $def) {
                    $type = $def['cardType'];
                    if (!isset($listMap[$type])) {
                        $component['list'][] = $def;
                        $changed = true;
                        continue;
                    }
                    $idx = $listMap[$type];
                    if (!isset($component['list'][$idx]['isShow'])) {
                        $component['list'][$idx]['isShow'] = true;
                        $changed = true;
                    }
                    if (empty($component['list'][$idx]['name'])) {
                        $component['list'][$idx]['name'] = $def['name'];
                        $changed = true;
                    }
                    if (empty($component['list'][$idx]['subtitle'])) {
                        $component['list'][$idx]['subtitle'] = $def['subtitle'];
                        $changed = true;
                    }
                    if (empty($component['list'][$idx]['cardType'])) {
                        $component['list'][$idx]['cardType'] = $def['cardType'];
                        $changed = true;
                    }
                }
            }
            unset($component);

            if ($changed) {
                Db::name('diy_page')->where('id', $row['id'])->update([
                    'value' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'update_time' => time(),
                ]);
            }
        }
    }

    private function removeCommunityFromMenuGrid()
    {
        $rows = Db::name('diy_page')
            ->whereIn('type', ['DIY_SD_XIAOYUAN_INDEX', 'DIY_SD_XIAOYUAN_MEMBER'])
            ->field('id,value')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $data = json_decode($row['value'] ?? '', true);
            if (!is_array($data) || empty($data['value']) || !is_array($data['value'])) {
                continue;
            }

            $changed = false;
            foreach ($data['value'] as &$component) {
                if (($component['componentName'] ?? '') !== 'XiaoyuanMenuGrid') {
                    continue;
                }
                if (empty($component['list']) || !is_array($component['list'])) {
                    continue;
                }
                $before = count($component['list']);
                $component['list'] = array_values(array_filter($component['list'], function ($item) {
                    return ($item['name'] ?? '') !== '校园树洞';
                }));
                if (count($component['list']) < $before) {
                    $changed = true;
                }
            }
            unset($component);

            if ($changed) {
                Db::name('diy_page')->where('id', $row['id'])->update([
                    'value' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'update_time' => time(),
                ]);
            }
        }
    }

    /**
     * 为各站点补插校园帮个人中心默认装修数据
     */
    private function ensureMemberDiyPage()
    {
        $type = 'DIY_SD_XIAOYUAN_MEMBER';
        $pagesFile = __DIR__ . '/app/dict/diy/pages.php';
        if (!is_file($pagesFile)) {
            return;
        }
        $pagesDict = include $pagesFile;
        if (empty($pagesDict[$type]['sd_xiaoyuan_member_default'])) {
            return;
        }
        $tpl = $pagesDict[$type]['sd_xiaoyuan_member_default'];
        $valueJson = json_encode($tpl['data'], JSON_UNESCAPED_UNICODE);
        $now = time();

        // 已有首页装修的站点，优先为这些站点补个人中心
        $siteIds = Db::name('diy_page')
            ->where('type', 'DIY_SD_XIAOYUAN_INDEX')
            ->column('site_id');
        $siteIds = array_values(array_unique(array_filter($siteIds)));
        if (empty($siteIds)) {
            $siteIds = Db::name('diy_page')->column('site_id');
            $siteIds = array_values(array_unique(array_filter($siteIds)));
        }

        foreach ($siteIds as $siteId) {
            $exists = Db::name('diy_page')
                ->where([['site_id', '=', $siteId], ['type', '=', $type]])
                ->count();
            if ($exists > 0) {
                continue;
            }
            Db::name('diy_page')->insert([
                'site_id' => $siteId,
                'name' => $type,
                'type' => $type,
                'title' => $tpl['title'] ?? '个人中心',
                'page_title' => $tpl['title'] ?? '校园帮个人中心',
                'template' => 'sd_xiaoyuan_member_default',
                'mode' => $tpl['mode'] ?? 'diy',
                'value' => $valueJson,
                'is_default' => 1,
                'is_change' => 0,
                'share' => '',
                'visit_count' => 0,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }
    }

    /**
     * 个人中心装修未手动修改过时，刷新为最新默认菜单（含完整入口与开关字段）
     */
    private function syncMemberDiyDefaultIfUnchanged()
    {
        $type = 'DIY_SD_XIAOYUAN_MEMBER';
        $pagesFile = __DIR__ . '/app/dict/diy/pages.php';
        if (!is_file($pagesFile)) {
            return;
        }
        $pagesDict = include $pagesFile;
        if (empty($pagesDict[$type]['sd_xiaoyuan_member_default']['data'])) {
            return;
        }
        $valueJson = json_encode($pagesDict[$type]['sd_xiaoyuan_member_default']['data'], JSON_UNESCAPED_UNICODE);
        Db::name('diy_page')
            ->where('type', $type)
            ->where('is_change', 0)
            ->update([
                'value' => $valueJson,
                'update_time' => time(),
            ]);
    }

    private function initServiceCards()
    {
        $siteRows = Db::name('site')->field('site_id')->select()->toArray();
        if (empty($siteRows)) {
            return;
        }
        $service = new \addon\sd_xiaoyuan\app\service\core\CardService();
        foreach ($siteRows as $row) {
            $service->initDefaultCards((int)$row['site_id']);
        }
    }

    private function compileDiyComponentsToUniapp()
    {
        $root = dirname(__DIR__, 3);
        $compilePath = $root . DIRECTORY_SEPARATOR . 'uni-app' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        if (!is_dir($compilePath)) {
            return;
        }
        \app\service\core\addon\CoreAddonInstallService::instance('sd_xiaoyuan')->compileDiyComponentsCode($compilePath, 'sd_xiaoyuan');
    }
}
