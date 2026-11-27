<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\check;

use addon\hsx_recycle\app\service\admin\check\RecycleCheckTemplateService;
use core\base\BaseAdminController;

class RecycleCheckTemplate extends BaseAdminController
{
    public function pages()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['scene', ''],
            ['status', ''],
            ['source', ''],
            ['category_id', 0],
        ]);
        return success((new RecycleCheckTemplateService())->getPage($data));
    }

    public function all()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['scene', ''],
            ['status', ''],
            ['category_id', 0],
            ['limit', 0],
        ]);
        return success((new RecycleCheckTemplateService())->all($data));
    }

    public function info(int $id)
    {
        return success((new RecycleCheckTemplateService())->info($id));
    }

    public function add()
    {
        $data = $this->request->params($this->templateParams());
        return success('ADD_SUCCESS', ['id' => (new RecycleCheckTemplateService())->addTemplate($data)]);
    }

    public function edit(int $id)
    {
        $data = $this->request->params($this->templateParams());
        (new RecycleCheckTemplateService())->editTemplate($id, $data);
        return success('EDIT_SUCCESS');
    }

    public function del(int $id)
    {
        (new RecycleCheckTemplateService())->deleteTemplate($id);
        return success('DELETE_SUCCESS');
    }

    public function setDefault(int $id)
    {
        (new RecycleCheckTemplateService())->setDefault($id);
        return success('MODIFY_SUCCESS');
    }

    public function schema()
    {
        $data = $this->request->params([
            ['template_id', 0],
            ['device_id', 0],
            ['category_id', 0],
            ['scene', 'phone'],
            ['brand', ''],
        ]);
        return success((new RecycleCheckTemplateService())->schema($data));
    }

    /** 可由验机查询回填的字段对照表(反黑盒：明示哪个字段对应查询的什么) */
    public function fillableSources()
    {
        return success((new RecycleCheckTemplateService())->fillableSourceMap());
    }

    public function initDefault()
    {
        return success((new RecycleCheckTemplateService())->initDefault());
    }

    public function groups()
    {
        $data = $this->request->params([
            ['template_id', 0],
        ]);
        return success((new RecycleCheckTemplateService())->groups((int)$data['template_id']));
    }

    public function saveGroup()
    {
        $data = $this->request->params([
            ['id', 0],
            ['template_id', 0],
            ['group_key', ''],
            ['group_name', ''],
            ['description', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        return success('SAVE_SUCCESS', ['id' => (new RecycleCheckTemplateService())->saveGroup($data)]);
    }

    public function deleteGroup(int $id)
    {
        (new RecycleCheckTemplateService())->deleteGroup($id);
        return success('DELETE_SUCCESS');
    }

    public function fields()
    {
        $data = $this->request->params([
            ['template_id', 0],
            ['group_id', 0],
        ]);
        return success((new RecycleCheckTemplateService())->fields((int)$data['template_id'], (int)$data['group_id']));
    }

    public function saveField()
    {
        $data = $this->request->params([
            ['id', 0],
            ['template_id', 0],
            ['group_id', 0],
            ['field_key', ''],
            ['field_name', ''],
            ['component', 'input'],
            ['selection_mode', ''],
            ['unit', ''],
            ['placeholder', ''],
            ['default_value', ''],
            ['is_required', 0],
            ['is_show', 1],
            ['seller_visible', 1],
            ['buyer_visible', 0],
            ['result_visible', 1],
            ['result_template', ''],
            ['api_fill_enabled', 0],
            ['api_fill_policy', 'empty_only'],
            ['sort', 0],
            ['extra_config', []],
        ], false);
        return success('SAVE_SUCCESS', ['id' => (new RecycleCheckTemplateService())->saveField($data)]);
    }

    public function deleteField(int $id)
    {
        (new RecycleCheckTemplateService())->deleteField($id);
        return success('DELETE_SUCCESS');
    }

    public function saveOption()
    {
        $data = $this->request->params([
            ['id', 0],
            ['field_id', 0],
            ['option_label', ''],
            ['option_value', ''],
            ['is_default', 0],
            ['is_show', 1],
            ['sort', 0],
            ['extra_config', []],
        ], false);
        return success('SAVE_SUCCESS', ['id' => (new RecycleCheckTemplateService())->saveOption($data)]);
    }

    public function deleteOption(int $id)
    {
        (new RecycleCheckTemplateService())->deleteOption($id);
        return success('DELETE_SUCCESS');
    }

    public function setOptionDefault(int $id)
    {
        $data = $this->request->params([
            ['is_default', 1],
        ]);
        (new RecycleCheckTemplateService())->setOptionDefault($id, (int)$data['is_default']);
        return success('MODIFY_SUCCESS');
    }

    private function templateParams(): array
    {
        return [
            ['template_key', ''],
            ['template_name', ''],
            ['scene', 'phone'],
            ['is_default', 0],
            ['status', 1],
            ['sort', 0],
        ];
    }
}
