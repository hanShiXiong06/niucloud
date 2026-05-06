<template>
    <div class="check-template-page">
        <section class="page-toolbar">
            <div>
                <div class="page-title">质检模板</div>
                <div class="page-subtitle">维护站点自己的质检表单、选项、文案和 API 回填字段</div>
            </div>
            <div class="toolbar-actions">
                <el-button @click="initDefault">初始化默认模板</el-button>
                <el-button type="primary" @click="openTemplateDialog()">新增模板</el-button>
            </div>
        </section>

        <div class="workspace">
            <aside class="panel template-panel">
                <div class="panel-head">
                    <span>模板</span>
                    <el-input v-model="templateQuery.keyword" clearable placeholder="搜索模板" size="small" @keyup.enter="loadTemplates" />
                </div>
                <el-table
                    v-loading="templateLoading"
                    :data="templates"
                    height="680"
                    row-key="id"
                    highlight-current-row
                    @row-click="selectTemplate"
                >
                    <el-table-column label="模板" min-width="180" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div class="name-main">{{ row.template_name }}</div>
                            <div class="name-sub">{{ row.scene }} · v{{ row.version }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="76">
                        <template #default="{ row }">
                            <el-tag size="small" :type="row.is_default ? 'success' : 'info'">{{ row.is_default ? '默认' : (row.status ? '启用' : '停用') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="86" fixed="right">
                        <template #default="{ row }">
                            <el-dropdown trigger="click" @command="cmd => handleTemplateCommand(String(cmd), row)">
                                <el-button link type="primary">操作</el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="edit">编辑</el-dropdown-item>
                                        <el-dropdown-item command="default">设为默认</el-dropdown-item>
                                        <el-dropdown-item command="delete">删除</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>
            </aside>

            <section class="panel group-panel">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">分组</div>
                        <div class="panel-subtitle">{{ currentTemplate?.template_name || '请选择模板' }}</div>
                    </div>
                    <el-button type="primary" :disabled="!currentTemplate" @click="openGroupDialog()">新增分组</el-button>
                </div>
                <el-table
                    v-loading="groupLoading"
                    :data="groups"
                    height="680"
                    row-key="id"
                    highlight-current-row
                    @row-click="selectGroup"
                >
                    <el-table-column label="分组" min-width="170" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div class="name-main">{{ row.group_name }}</div>
                            <div class="name-sub">{{ row.group_key }} · 排序 {{ row.sort }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="70">
                        <template #default="{ row }">
                            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="saveGroupInline(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="96" fixed="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click.stop="openGroupDialog(row)">编辑</el-button>
                            <el-button link type="danger" @click.stop="removeGroup(row)">删</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </section>

            <section class="panel field-panel">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">字段</div>
                        <div class="panel-subtitle">{{ currentGroup?.group_name || '请选择分组' }}</div>
                    </div>
                    <el-button type="primary" :disabled="!currentGroup" @click="openFieldDialog()">新增字段</el-button>
                </div>
                <el-table
                    v-loading="fieldLoading"
                    :data="fields"
                    height="360"
                    row-key="id"
                    border
                    highlight-current-row
                    @row-click="selectField"
                >
                    <el-table-column label="字段" min-width="180" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div class="name-main">{{ row.field_name }}</div>
                            <div class="name-sub">{{ row.field_key }} · {{ row.component }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="文案" min-width="150" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.result_template || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="控制" width="130">
                        <template #default="{ row }">
                            <div class="switch-line">
                                <span>显示</span>
                                <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @change="saveFieldInline(row)" />
                            </div>
                            <div class="switch-line">
                                <span>结果</span>
                                <el-switch v-model="row.result_visible" :active-value="1" :inactive-value="0" @change="saveFieldInline(row)" />
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click.stop="openFieldDialog(row)">编辑</el-button>
                            <el-button link type="danger" @click.stop="removeField(row)">删</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="option-head">
                    <div>
                        <div class="panel-title">选项</div>
                        <div class="panel-subtitle">{{ currentField?.field_name || '选择 radio / checkbox / select 字段维护选项' }}</div>
                    </div>
                    <el-button type="primary" :disabled="!currentField || !fieldNeedsOptions(currentField)" @click="openOptionDialog()">新增选项</el-button>
                </div>
                <el-table :data="currentFieldOptions" height="260" border>
                    <el-table-column prop="option_label" label="选项名称" min-width="150" />
                    <el-table-column prop="option_value" label="选项值" width="110" />
                    <el-table-column prop="sort" label="排序" width="80" />
                    <el-table-column label="状态" width="86">
                        <template #default="{ row }">
                            <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @change="saveOptionInline(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="openOptionDialog(row)">编辑</el-button>
                            <el-button link type="danger" @click="removeOption(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </section>
        </div>

        <el-dialog v-model="templateDialog.visible" :title="templateDialog.form.id ? '编辑模板' : '新增模板'" width="520px">
            <el-form label-width="92px" :model="templateDialog.form">
                <el-form-item label="模板名称"><el-input v-model="templateDialog.form.template_name" /></el-form-item>
                <el-form-item label="模板标识"><el-input v-model="templateDialog.form.template_key" placeholder="default_phone" /></el-form-item>
                <el-form-item label="场景"><el-input v-model="templateDialog.form.scene" placeholder="phone" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="templateDialog.form.sort" :min="0" /></el-form-item>
                <el-form-item label="启用"><el-switch v-model="templateDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="默认"><el-switch v-model="templateDialog.form.is_default" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="templateDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="submitTemplate">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="groupDialog.visible" :title="groupDialog.form.id ? '编辑分组' : '新增分组'" width="520px">
            <el-form label-width="92px" :model="groupDialog.form">
                <el-form-item label="分组名称"><el-input v-model="groupDialog.form.group_name" /></el-form-item>
                <el-form-item label="分组标识"><el-input v-model="groupDialog.form.group_key" placeholder="appearance" /></el-form-item>
                <el-form-item label="说明"><el-input v-model="groupDialog.form.description" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="groupDialog.form.sort" :min="0" /></el-form-item>
                <el-form-item label="启用"><el-switch v-model="groupDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="groupDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="submitGroup">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="fieldDialog.visible" :title="fieldDialog.form.id ? '编辑字段' : '新增字段'" width="720px">
            <el-form label-width="108px" :model="fieldDialog.form" class="field-form">
                <el-form-item label="字段名称"><el-input v-model="fieldDialog.form.field_name" /></el-form-item>
                <el-form-item label="字段标识"><el-input v-model="fieldDialog.form.field_key" placeholder="screen_id" /></el-form-item>
                <el-form-item label="组件类型">
                    <el-select v-model="fieldDialog.form.component" class="w-full">
                        <el-option v-for="item in componentOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="选择模式">
                    <el-select v-model="fieldDialog.form.selection_mode" clearable class="w-full">
                        <el-option label="单选" value="single" />
                        <el-option label="多选" value="multiple" />
                    </el-select>
                </el-form-item>
                <el-form-item label="单位"><el-input v-model="fieldDialog.form.unit" placeholder="%, 次" /></el-form-item>
                <el-form-item label="提示语"><el-input v-model="fieldDialog.form.placeholder" /></el-form-item>
                <el-form-item label="结果文案"><el-input v-model="fieldDialog.form.result_template" placeholder="外屏{label} / 电池健康度{value}%" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="fieldDialog.form.sort" :min="0" /></el-form-item>
                <el-form-item label="开关">
                    <div class="inline-switches">
                        <el-checkbox v-model="fieldDialog.form.is_show" :true-label="1" :false-label="0">显示</el-checkbox>
                        <el-checkbox v-model="fieldDialog.form.is_required" :true-label="1" :false-label="0">必填</el-checkbox>
                        <el-checkbox v-model="fieldDialog.form.result_visible" :true-label="1" :false-label="0">参与文案</el-checkbox>
                        <el-checkbox v-model="fieldDialog.form.api_fill_enabled" :true-label="1" :false-label="0">API回填</el-checkbox>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="fieldDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="submitField">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="optionDialog.visible" :title="optionDialog.form.id ? '编辑选项' : '新增选项'" width="480px">
            <el-form label-width="92px" :model="optionDialog.form">
                <el-form-item label="选项名称"><el-input v-model="optionDialog.form.option_label" /></el-form-item>
                <el-form-item label="选项值"><el-input v-model="optionDialog.form.option_value" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="optionDialog.form.sort" :min="0" /></el-form-item>
                <el-form-item label="显示"><el-switch v-model="optionDialog.form.is_show" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="optionDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="submitOption">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import {
    addCheckTemplate,
    deleteCheckField,
    deleteCheckGroup,
    deleteCheckOption,
    deleteCheckTemplate,
    editCheckTemplate,
    getCheckFields,
    getCheckGroups,
    getCheckTemplatePages,
    initDefaultCheckTemplate,
    saveCheckField,
    saveCheckGroup,
    saveCheckOption,
    setDefaultCheckTemplate
} from '@/addon/recycle/api/check_template'

const templateLoading = ref(false)
const groupLoading = ref(false)
const fieldLoading = ref(false)
const templates = ref<any[]>([])
const groups = ref<any[]>([])
const fields = ref<any[]>([])
const currentTemplate = ref<any>(null)
const currentGroup = ref<any>(null)
const currentField = ref<any>(null)
const templateQuery = reactive({ keyword: '' })

const componentOptions = [
    { label: '单行输入', value: 'input' },
    { label: '数字输入', value: 'number' },
    { label: '单选标签', value: 'radio' },
    { label: '多选标签', value: 'checkbox' },
    { label: '下拉选择', value: 'select' },
    { label: '开关', value: 'switch' },
    { label: '多行文本', value: 'textarea' }
]

const templateDialog = reactive({ visible: false, form: {} as any })
const groupDialog = reactive({ visible: false, form: {} as any })
const fieldDialog = reactive({ visible: false, form: {} as any })
const optionDialog = reactive({ visible: false, form: {} as any })

const currentFieldOptions = computed(() => currentField.value?.options || [])

const loadTemplates = async () => {
    templateLoading.value = true
    try {
        const res: any = await getCheckTemplatePages({ ...templateQuery, page: 1, limit: 100 })
        templates.value = res.data.data || []
        if (!currentTemplate.value && templates.value.length) {
            await selectTemplate(templates.value[0])
        }
    } finally {
        templateLoading.value = false
    }
}

const selectTemplate = async (row: any) => {
    currentTemplate.value = row
    currentGroup.value = null
    currentField.value = null
    await loadGroups()
}

const loadGroups = async () => {
    if (!currentTemplate.value) return
    groupLoading.value = true
    try {
        const res: any = await getCheckGroups({ template_id: currentTemplate.value.id })
        groups.value = res.data || []
        if (groups.value.length) {
            await selectGroup(groups.value[0])
        } else {
            fields.value = []
        }
    } finally {
        groupLoading.value = false
    }
}

const selectGroup = async (row: any) => {
    currentGroup.value = row
    currentField.value = null
    await loadFields()
}

const loadFields = async (preferredFieldId: number | string = currentField.value?.id || 0) => {
    if (!currentTemplate.value || !currentGroup.value) return
    fieldLoading.value = true
    try {
        const res: any = await getCheckFields({ template_id: currentTemplate.value.id, group_id: currentGroup.value.id })
        fields.value = res.data || []
        currentField.value = fields.value.find((item: any) => String(item.id) === String(preferredFieldId)) || fields.value[0] || null
    } finally {
        fieldLoading.value = false
    }
}

const selectField = (row: any) => {
    currentField.value = row
}

const initDefault = async () => {
    await initDefaultCheckTemplate()
    currentTemplate.value = null
    await loadTemplates()
}

const openTemplateDialog = (row: any = null) => {
    templateDialog.form = row ? { ...row } : { template_name: '', template_key: '', scene: 'phone', sort: 0, status: 1, is_default: 0 }
    templateDialog.visible = true
}

const submitTemplate = async () => {
    if (templateDialog.form.id) {
        await editCheckTemplate(templateDialog.form.id, templateDialog.form)
    } else {
        await addCheckTemplate(templateDialog.form)
    }
    templateDialog.visible = false
    await loadTemplates()
}

const handleTemplateCommand = async (cmd: string, row: any) => {
    if (cmd === 'edit') return openTemplateDialog(row)
    if (cmd === 'default') {
        await setDefaultCheckTemplate(row.id)
        await loadTemplates()
        return
    }
    if (cmd === 'delete') {
        await ElMessageBox.confirm(`确认删除模板「${row.template_name}」？`, '删除确认', { type: 'warning' })
        await deleteCheckTemplate(row.id)
        currentTemplate.value = null
        await loadTemplates()
    }
}

const openGroupDialog = (row: any = null) => {
    if (!currentTemplate.value) return
    groupDialog.form = row ? { ...row } : { template_id: currentTemplate.value.id, group_name: '', group_key: '', description: '', sort: 0, status: 1 }
    groupDialog.visible = true
}

const submitGroup = async () => {
    await saveCheckGroup({ ...groupDialog.form, template_id: currentTemplate.value.id })
    groupDialog.visible = false
    await loadGroups()
}

const saveGroupInline = async (row: any) => {
    await saveCheckGroup(row)
}

const removeGroup = async (row: any) => {
    await ElMessageBox.confirm(`确认删除分组「${row.group_name}」？字段和选项会一起删除。`, '删除确认', { type: 'warning' })
    await deleteCheckGroup(row.id)
    await loadGroups()
}

const openFieldDialog = (row: any = null) => {
    if (!currentTemplate.value || !currentGroup.value) return
    fieldDialog.form = row ? { ...row } : {
        template_id: currentTemplate.value.id,
        group_id: currentGroup.value.id,
        field_name: '',
        field_key: '',
        component: 'input',
        selection_mode: '',
        unit: '',
        placeholder: '',
        default_value: '',
        is_required: 0,
        is_show: 1,
        seller_visible: 1,
        buyer_visible: 0,
        result_visible: 1,
        result_template: '',
        api_fill_enabled: 0,
        api_fill_policy: 'empty_only',
        sort: 0,
        extra_config: {}
    }
    fieldDialog.visible = true
}

const submitField = async () => {
    const res: any = await saveCheckField({ ...fieldDialog.form, template_id: currentTemplate.value.id, group_id: currentGroup.value.id })
    fieldDialog.visible = false
    await loadFields(fieldDialog.form.id || res.data?.id || 0)
}

const saveFieldInline = async (row: any) => {
    const fieldId = row.id
    await saveCheckField(row)
    await loadFields(fieldId)
}

const removeField = async (row: any) => {
    await ElMessageBox.confirm(`确认删除字段「${row.field_name}」？`, '删除确认', { type: 'warning' })
    await deleteCheckField(row.id)
    await loadFields()
}

const fieldNeedsOptions = (field: any) => ['radio', 'checkbox', 'select'].includes(field?.component)

const openOptionDialog = (row: any = null) => {
    if (!currentField.value) return
    optionDialog.form = row ? { ...row } : { field_id: currentField.value.id, option_label: '', option_value: '', sort: 0, is_show: 1, is_default: 0 }
    optionDialog.visible = true
}

const submitOption = async () => {
    const fieldId = currentField.value.id
    await saveCheckOption({ ...optionDialog.form, field_id: currentField.value.id })
    optionDialog.visible = false
    await loadFields(fieldId)
}

const saveOptionInline = async (row: any) => {
    const fieldId = currentField.value?.id || 0
    await saveCheckOption(row)
    await loadFields(fieldId)
}

const removeOption = async (row: any) => {
    const fieldId = currentField.value?.id || 0
    await ElMessageBox.confirm(`确认删除选项「${row.option_label}」？`, '删除确认', { type: 'warning' })
    await deleteCheckOption(row.id)
    await loadFields(fieldId)
}

onMounted(loadTemplates)
</script>

<style scoped>
.check-template-page {
    min-height: 100%;
}

.page-toolbar,
.panel-head,
.option-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.page-title {
    color: #303133;
    font-size: 20px;
    font-weight: 700;
}

.page-subtitle,
.panel-subtitle,
.name-sub {
    color: #909399;
    font-size: 12px;
}

.toolbar-actions,
.inline-switches {
    display: flex;
    align-items: center;
    gap: 10px;
}

.workspace {
    display: grid;
    grid-template-columns: minmax(260px, 0.8fr) minmax(300px, 1fr) minmax(520px, 1.5fr);
    gap: 12px;
}

.panel {
    min-width: 0;
    padding: 14px;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    background: #fff;
}

.panel-title,
.name-main {
    color: #303133;
    font-weight: 600;
}

.switch-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    line-height: 22px;
}

.field-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 14px;
}

.field-form :deep(.el-form-item:last-child) {
    grid-column: 1 / -1;
}

@media (max-width: 1280px) {
    .workspace {
        grid-template-columns: 1fr;
    }
}
</style>
