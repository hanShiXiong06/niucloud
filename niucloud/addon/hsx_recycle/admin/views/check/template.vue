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
                    <el-table-column label="展示样式" width="140">
                        <template #default="{ row }">
                            <div class="option-style-preview">
                                <span
                                    class="option-style-chip"
                                    :style="getOptionPreviewStyle(row)"
                                >{{ row.option_label || '预览' }}</span>
                            </div>
                        </template>
                    </el-table-column>
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
                <el-form-item label="字段名称">
                    <el-input v-model="fieldDialog.form.field_name" placeholder="例如：外屏规格、电池健康度" />
                    <div class="form-tip">显示在质检弹窗中间的字段标题；勾选设备摘要后，也会作为左侧摘要标题展示。</div>
                </el-form-item>
                <el-form-item label="字段标识">
                    <el-input v-model="fieldDialog.form.field_key" placeholder="例如：screen_id、battery" />
                    <div class="form-tip">用于保存数据、恢复草稿和 API 回填的唯一标识。已上线字段不要随意改，否则历史质检数据无法自动对应。</div>
                </el-form-item>
                <el-form-item label="组件类型">
                    <el-select v-model="fieldDialog.form.component" class="w-full">
                        <el-option v-for="item in componentOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                    <div class="form-tip">决定质检时的填写方式：输入框适合文本，数字输入适合电池/次数，单选/多选/下拉需要维护下方选项。</div>
                </el-form-item>
                <el-form-item label="选择模式">
                    <el-select v-model="fieldDialog.form.selection_mode" clearable class="w-full">
                        <el-option label="单选" value="single" />
                        <el-option label="多选" value="multiple" />
                    </el-select>
                    <div class="form-tip">用于表达业务含义。单选表示只能取一个结果，多选表示可以同时记录多个问题或状态。</div>
                </el-form-item>
                <el-form-item label="单位">
                    <el-input v-model="fieldDialog.form.unit" placeholder="例如：%、次、GB" />
                    <div class="form-tip">显示在字段旁边，也会参与质检结果表达；没有单位可以留空。</div>
                </el-form-item>
                <el-form-item label="提示语">
                    <el-input v-model="fieldDialog.form.placeholder" placeholder="例如：请输入电池健康度" />
                    <div class="form-tip">显示在输入框或选择框内部，引导质检人员填写正确内容。</div>
                </el-form-item>
                <el-form-item label="结果文案">
                    <el-input v-model="fieldDialog.form.result_template" placeholder="例如：外屏{label} / 电池健康度{value}%" />
                    <div class="form-tip">参与自动生成质检结果。可用 {label} 表示选项名称，{value} 表示填写值；不填写则按字段名称和结果自动拼接。</div>
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="fieldDialog.form.sort" :min="0" />
                    <div class="form-tip">数字越小越靠前，影响质检弹窗中间表单、设备摘要和选项展示顺序。</div>
                </el-form-item>
                <el-form-item label="开关">
                    <div class="inline-switches">
                        <el-checkbox v-model="fieldDialog.form.is_show" :true-label="1" :false-label="0">显示</el-checkbox>
                        <el-checkbox v-model="fieldDialog.form.is_required" :true-label="1" :false-label="0">必填</el-checkbox>
                        <el-checkbox v-model="fieldDialog.form.result_visible" :true-label="1" :false-label="0">参与文案</el-checkbox>
                        <el-checkbox v-model="fieldDialog.form.api_fill_enabled" :true-label="1" :false-label="0">API回填</el-checkbox>
                        <el-checkbox
                            v-model="fieldDialog.form.extra_config.summary_visible"
                            :true-label="1"
                            :false-label="0"
                            @change="handleSummaryVisibleChange"
                        >设备摘要</el-checkbox>
                    </div>
                    <div class="form-tip">
                        显示：控制质检弹窗是否出现这个字段；必填：提交质检时必须填写；
                        参与文案：会进入卖家/买家质检结果；API回填：允许第三方查询结果自动写入；
                        设备摘要：显示在左侧设备摘要中，最多可配置 5 个。
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
                <el-form-item label="文字颜色">
                    <el-color-picker v-model="optionDialog.form.extra_config.result_style.text_color" show-alpha />
                    <span class="color-value">{{ optionDialog.form.extra_config.result_style.text_color || '默认' }}</span>
                </el-form-item>
                <el-form-item label="背景颜色">
                    <el-color-picker v-model="optionDialog.form.extra_config.result_style.background_color" show-alpha />
                    <span class="color-value">{{ optionDialog.form.extra_config.result_style.background_color || '默认' }}</span>
                </el-form-item>
                <el-form-item label="边框颜色">
                    <el-color-picker v-model="optionDialog.form.extra_config.result_style.border_color" show-alpha />
                    <span class="color-value">{{ optionDialog.form.extra_config.result_style.border_color || '默认' }}</span>
                </el-form-item>
                <el-form-item label="效果预览">
                    <div class="option-style-preview">
                        <span
                            class="option-style-chip option-style-chip--large"
                            :style="getOptionPreviewStyle(optionDialog.form)"
                        >{{ optionDialog.form.option_label || '选项预览' }}</span>
                        <el-button link type="primary" @click="resetOptionStyle">清空样式</el-button>
                    </div>
                    <div class="form-tip">颜色会随质检结果元数据保存，用户移动端验机报告会按这里的配置展示。</div>
                </el-form-item>
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
import { ElMessage, ElMessageBox } from 'element-plus'
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
} from '@/addon/hsx_recycle/api/check_template'

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
const optionDialog = reactive({
    visible: false,
    form: {
        extra_config: {
            result_style: {
                text_color: '',
                background_color: '',
                border_color: ''
            }
        }
    } as any
})

const currentFieldOptions = computed(() => currentField.value?.options || [])

const normalizeExtraConfig = (config: any) => {
    if (!config) return {}
    if (typeof config === 'string') {
        try {
            const parsed = JSON.parse(config)
            return parsed && typeof parsed === 'object' ? parsed : {}
        } catch (error) {
            return {}
        }
    }
    return typeof config === 'object' ? { ...config } : {}
}

const defaultOptionStyle = () => ({
    text_color: '',
    background_color: '',
    border_color: ''
})

const normalizeOptionExtraConfig = (config: any) => {
    const extra = normalizeExtraConfig(config)
    const rawStyle = normalizeExtraConfig(extra.result_style || extra.option_style || {})
    return {
        ...extra,
        result_style: {
            ...defaultOptionStyle(),
            ...rawStyle,
            text_color: rawStyle.text_color || extra.text_color || '',
            background_color: rawStyle.background_color || extra.background_color || extra.bg_color || '',
            border_color: rawStyle.border_color || extra.border_color || ''
        }
    }
}

const normalizeFieldForm = (field: any) => ({
    ...field,
    extra_config: {
        summary_visible: 0,
        ...normalizeExtraConfig(field?.extra_config)
    }
})

const normalizeOptionForm = (option: any) => ({
    ...option,
    extra_config: normalizeOptionExtraConfig(option?.extra_config)
})

const selectedSummaryFieldCount = computed(() => {
    return fields.value.filter((field: any) => {
        const config = normalizeExtraConfig(field.extra_config)
        const isCurrentField = fieldDialog.form?.id && String(field.id) === String(fieldDialog.form.id)
        return !isCurrentField && Number(config.summary_visible || 0) === 1
    }).length
})

const loadTemplates = async () => {
    templateLoading.value = true
    try {
        const currentId = currentTemplate.value?.id
        const res: any = await getCheckTemplatePages({ ...templateQuery, page: 1, limit: 100 })
        templates.value = res.data.data || []
        if (!templates.value.length) {
            currentTemplate.value = null
            groups.value = []
            fields.value = []
            currentGroup.value = null
            currentField.value = null
            return
        }

        const currentMatch = templates.value.find((item: any) => String(item.id) === String(currentId))
        const defaultTemplate = templates.value.find((item: any) => Number(item.is_default) === 1 && Number(item.status) === 1)
        const nextTemplate = currentTemplate.value
            ? (currentMatch || defaultTemplate || templates.value[0])
            : (defaultTemplate || templates.value[0])
        await selectTemplate(nextTemplate)
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
        fields.value = (res.data || []).map(normalizeFieldForm)
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
    let savedId = templateDialog.form.id || 0
    if (templateDialog.form.id) {
        await editCheckTemplate(templateDialog.form.id, templateDialog.form)
    } else {
        const res: any = await addCheckTemplate(templateDialog.form)
        savedId = res.data?.id || 0
    }
    templateDialog.visible = false
    currentTemplate.value = templateDialog.form.is_default ? null : (savedId ? { id: savedId } : currentTemplate.value)
    await loadTemplates()
}

const handleTemplateCommand = async (cmd: string, row: any) => {
    if (cmd === 'edit') return openTemplateDialog(row)
    if (cmd === 'default') {
        await setDefaultCheckTemplate(row.id)
        currentTemplate.value = null
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
    fieldDialog.form = row ? normalizeFieldForm(row) : normalizeFieldForm({
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
    })
    fieldDialog.visible = true
}

const handleSummaryVisibleChange = (value: any) => {
    if (Number(value) !== 1) return
    if (selectedSummaryFieldCount.value < 5) return
    fieldDialog.form.extra_config.summary_visible = 0
    ElMessage.warning('设备摘要最多展示 5 个字段')
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
    optionDialog.form = row
        ? normalizeOptionForm(row)
        : normalizeOptionForm({ field_id: currentField.value.id, option_label: '', option_value: '', sort: 0, is_show: 1, is_default: 0, extra_config: {} })
    optionDialog.visible = true
}

const getOptionStyleConfig = (option: any) => {
    return normalizeOptionExtraConfig(option?.extra_config).result_style
}

const getOptionPreviewStyle = (option: any) => {
    const style = getOptionStyleConfig(option)
    return {
        color: style.text_color || '#334155',
        backgroundColor: style.background_color || '#f8fafc',
        borderColor: style.border_color || style.background_color || '#dbe4ef'
    }
}

const resetOptionStyle = () => {
    optionDialog.form.extra_config = {
        ...normalizeOptionExtraConfig(optionDialog.form.extra_config),
        result_style: defaultOptionStyle(),
        text_color: '',
        background_color: '',
        bg_color: '',
        border_color: ''
    }
}

const submitOption = async () => {
    const fieldId = currentField.value.id
    await saveCheckOption({ ...normalizeOptionForm(optionDialog.form), field_id: currentField.value.id })
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

.inline-switches {
    flex-wrap: wrap;
}

.form-tip {
    width: 100%;
    margin-top: 6px;
    color: #909399;
    font-size: 12px;
    line-height: 1.5;
}

.color-value {
    margin-left: 10px;
    color: #64748b;
    font-size: 12px;
}

.option-style-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.option-style-chip {
    max-width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 2px 10px;
    border: 1px solid #dbe4ef;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 12px;
    line-height: 1.4;
    word-break: break-all;
}

.option-style-chip--large {
    min-height: 30px;
    padding: 4px 14px;
    font-size: 13px;
    font-weight: 600;
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
