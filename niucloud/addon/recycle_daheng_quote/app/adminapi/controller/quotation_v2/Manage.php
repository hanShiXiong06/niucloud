<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2;

use addon\recycle_daheng_quote\app\service\admin\quotation_v2\ManageService;
use core\base\BaseAdminController;

/**
 * 报价 2.0 明细管理
 */
class Manage extends BaseAdminController
{
    public function addModel()
    {
        return success('ADD_SUCCESS', ['id' => (new ManageService())->addModel($this->request->post())]);
    }

    public function editModel(int $id)
    {
        (new ManageService())->editModel($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function deleteModel(int $id)
    {
        (new ManageService())->deleteModel($id);
        return success('DELETE_SUCCESS');
    }

    public function addCapacity()
    {
        return success('ADD_SUCCESS', ['id' => (new ManageService())->addCapacity($this->request->post())]);
    }

    public function editCapacity(int $id)
    {
        (new ManageService())->editCapacity($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function deleteCapacity(int $id)
    {
        (new ManageService())->deleteCapacity($id);
        return success('DELETE_SUCCESS');
    }

    public function addField()
    {
        return success('ADD_SUCCESS', ['id' => (new ManageService())->addField($this->request->post())]);
    }

    public function editField(int $id)
    {
        (new ManageService())->editField($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function deleteField(int $id)
    {
        (new ManageService())->deleteField($id);
        return success('DELETE_SUCCESS');
    }

    public function addNote()
    {
        return success('ADD_SUCCESS', ['id' => (new ManageService())->addNote($this->request->post())]);
    }

    public function editNote(int $id)
    {
        (new ManageService())->editNote($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function editNoteGroup(int $id)
    {
        (new ManageService())->editNoteGroup($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function deleteNote(int $id)
    {
        (new ManageService())->deleteNote($id);
        return success('DELETE_SUCCESS');
    }

    public function deleteNoteGroup(int $id)
    {
        (new ManageService())->deleteNoteGroup($id);
        return success('DELETE_SUCCESS');
    }
}
