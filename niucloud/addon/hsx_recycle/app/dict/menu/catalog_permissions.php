<?php
/** 补齐资料接口权限；平台与站点复用同一份声明。 */
return static function (array $menus): array {
    $extra = [
        'recycle_printer_template_list' => [
            ['print_copy', '复制为本站打印模板', 'post', 'printer_template/copy/<id>'],
            ['print_variables', '查看打印变量', 'get', 'printer_template/variables'],
        ],
        'recycle_device_model_dict_list' => [
            ['children', '读取下级型号', 'get', 'recycle_device_model_dict/children'],
            ['alias_resolve', '识别型号别名', 'post', 'recycle_device_model_dict/alias/resolve'],
            ['alias_bind', '维护型号别名', 'post', 'recycle_device_model_dict/alias/bind'],
            ['external_import', '批量导入型号', 'post', 'recycle_device_model_dict/external_import'],
            ['model_upload', '上传型号导入文件', 'post', 'recycle_device_model_dict/import_upload'],
            ['model_tasks', '查看型号导入任务', 'get', 'recycle_device_model_dict/import_tasks'],
            ['model_task', '查看型号导入明细', 'get', 'recycle_device_model_dict/import_tasks/<id>'],
            ['model_retry', '重试型号导入', 'post', 'recycle_device_model_dict/import_tasks/<id>/retry'],
            ['model_delete', '删除型号导入任务', 'delete', 'recycle_device_model_dict/import_tasks/<id>'],
        ],
        'recycle_check_template' => [
            ['fillable', '查看回填字段', 'get', 'check_template/fillable_sources'],
            ['template_info', '查看质检模板', 'get', 'check_template/<id>'],
            ['option_default', '设置默认选项', 'post', 'check_template_option/<id>/default'],
        ],
        'recycle_check_catalog_list' => [
            ['check_upload', '上传检测表', 'post', 'check_catalog/import_upload'],
            ['check_chunk', '写入检测表', 'post', 'check_catalog/import_chunk'],
        ],
    ];
    $walk = function (array $rows) use (&$walk, $extra): array {
        foreach ($rows as &$row) {
            if (!empty($row['children'])) $row['children'] = $walk($row['children']);
            foreach ($extra[$row['menu_key']] ?? [] as [$key, $name, $method, $url]) {
                $row['children'][] = ['menu_key' => 'recycle_catalog_extra_' . $key, 'menu_name' => $name,
                    'menu_short_name' => $name, 'parent_select_key' => '', 'menu_type' => '2',
                    'api_url' => 'recycle/' . $url, 'methods' => $method, 'router_path' => '', 'view_path' => '',
                    'status' => 1, 'is_show' => 0, 'sort' => 0];
            }
        }
        return $rows;
    };
    return $walk($menus);
};
