<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\support;

/** 页面路由由 NiuCloud 管理；接口与按钮权限共用这份清单。 */
final class ConsoleMenu
{
    public const PAGES = [
        'connection' => ['连接管理', '配置连接、测试和启用', [
            'lists' => ['查看连接', 'get', 'connection/lists', 'Connection@lists'],
            'info' => ['查看连接配置', 'get', 'connection/:id', 'Connection@info'],
            'add' => ['新建连接', 'post', 'connection/save/0', 'Connection@add'],
            'edit' => ['编辑连接', 'post', 'connection/save/:id', 'Connection@edit'],
            'test' => ['测试连接', 'post', 'connection/test/:id', 'Connection@testConnection'],
            'status' => ['启用或暂停连接', 'post', 'connection/status/:id', 'Connection@changeStatus'],
            'del' => ['删除未使用连接', 'delete', 'connection/:id', 'Connection@del'],
            'adapters' => ['查看配置表单', 'get', 'connection/adapters', 'Connection@getAdapters'],
        ]],
        'mapping' => ['资料映射', '把本站资料与外部档案配对', [
            'lists' => ['查看映射', 'get', 'mapping/lists', 'Mapping@lists'],
            'connections' => ['选择映射连接', 'get', 'mapping/connections', 'Connection@options'],
            'resources' => ['选择本站资料', 'get', 'mapping/resources', 'Mapping@getLocalResources'],
            'add' => ['新建映射', 'post', 'mapping/save/0', 'Mapping@add'],
            'edit' => ['编辑映射', 'post', 'mapping/save/:id', 'Mapping@edit'],
            'del' => ['删除映射', 'delete', 'mapping/:id', 'Mapping@del'],
        ]],
        'task' => ['同步任务', '按原单追踪发送状态和回执', [
            'lists' => ['查看任务', 'get', 'task/lists', 'Task@lists'],
            'info' => ['查看任务详情', 'get', 'task/:id', 'Task@info'],
            'statistics' => ['查看今日统计', 'get', 'task/statistics', 'Task@statistics'],
            'retry' => ['重试发送前校验失败的任务', 'post', 'task/retry/:id', 'Task@retry'],
            'prepare' => ['关联已有原单', 'post', 'task/prepare/:id', 'Task@prepare'],
            'review' => ['读取原单回执', 'post', 'task/review/:id', 'Task@review'],
        ]],
        'failure' => ['异常处理', '核对原单后记录处理结果', [
            'lists' => ['查看异常', 'get', 'failure/lists', 'Failure@lists'],
            'info' => ['查看异常详情', 'get', 'failure/:id', 'Failure@info'],
            'resolve' => ['登记处理结果', 'post', 'failure/resolve/:id', 'Failure@resolve'],
            'batch_delete' => ['删除已处理异常记录', 'delete', 'failure/batch_delete', 'Failure@batchDel'],
        ]],
        'adapter' => ['可用适配器', '查看本站可对接的 ERP', [
            'lists' => ['查看适配器', 'get', 'adapter/list', 'Connection@getAdapters'],
        ]],
    ];

    public static function build(bool $site): array
    {
        $prefix = 'hsx_erp_integration';
        $base = $site ? $prefix : 'integration';
        $common = ['parent_select_key' => '', 'icon' => '', 'status' => 1, 'is_show' => 1,
            'api_url' => '', 'methods' => '', 'router_path' => '', 'view_path' => '', 'sort' => 0];
        $pages = [];
        foreach (self::PAGES as $key => [$name, , $actions]) {
            $children = [];
            foreach ($actions as $action => [$label, $method, $path]) {
                $children[] = ['menu_name' => $label, 'menu_short_name' => $label, 'menu_type' => 2,
                    'menu_key' => "{$prefix}_{$key}_{$action}", 'api_url' => 'integration/' . str_replace(':id', '<id>', $path),
                    'methods' => $method, 'is_show' => 0] + $common;
            }
            $pages[] = ['menu_name' => $name, 'menu_short_name' => $name, 'menu_type' => 1,
                'menu_key' => "{$prefix}_{$key}", 'router_path' => "{$base}/{$key}", 'view_path' => "integration/{$key}",
                'api_url' => 'integration/' . $actions['lists'][2], 'methods' => 'get',
                'sort' => 100 - count($pages), 'children' => $children] + $common;
        }
        return [['menu_name' => 'ERP 集成', 'menu_short_name' => 'ERP 集成', 'menu_key' => $site ? "{$prefix}_root" : $prefix,
            'menu_type' => 0, 'parent_key' => '', 'router_path' => $base, 'sort' => 200, 'children' => $pages] + $common];
    }
}
