<template>
    <el-drawer
        v-model="drawerVisible"
        title="配置质检模板"
        size="min(680px, 100vw)"
        append-to-body
        destroy-on-close
        :close-on-click-modal="false"
        class="check-template-drawer"
        @open="loadConfig"
    >
        <div v-loading="loading" class="template-config">
            <section class="template-context">
                <div class="template-context__icon"><el-icon><Iphone /></el-icon></div>
                <div class="template-context__body">
                    <div class="template-context__label">当前标准型号</div>
                    <div class="template-context__name">{{ modelName || '未命名型号' }}</div>
                    <div class="template-context__meta">型号库节点 ID：{{ categoryId || '-' }}</div>
                </div>
                <el-tag v-if="effective.check_template_id" type="success" effect="plain">模板已生效</el-tag>
                <el-tag v-else type="warning" effect="plain">待配置</el-tag>
            </section>

            <el-alert
                type="info"
                show-icon
                :closable="false"
                class="template-config__notice"
                title="模板决定该型号需要录入哪些质检信息；摘要字段决定设备卡片中优先展示哪些结果。"
            />

            <section class="config-section">
                <div class="config-section__head">
                    <div>
                        <div class="config-section__title">1. 关联质检模板</div>
                        <div class="config-section__desc">当前型号可独立绑定，也可以继续使用上级型号或全局模板。</div>
                    </div>
                    <el-button v-if="binding.id" link type="primary" :loading="resetting" @click="restoreInheritance">
                        恢复继承
                    </el-button>
                </div>

                <el-form label-position="top">
                    <el-form-item label="质检模板">
                        <el-select
                            v-model="selectedTemplateId"
                            filterable
                            class="w-full"
                            placeholder="请选择质检模板"
                            @change="loadSelectedTemplateSchema"
                        >
                            <el-option
                                v-for="item in templateOptions"
                                :key="item.id"
                                :label="item.template_name"
                                :value="Number(item.id)"
                            >
                                <div class="template-option">
                                    <span>{{ item.template_name }}</span>
                                    <el-tag v-if="Number(item.is_default) === 1" size="small" type="success" effect="plain">默认</el-tag>
                                    <el-tag v-if="item.scene === 'pjt'" size="small" type="info" effect="plain">导入</el-tag>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>
                </el-form>

                <div v-if="effective.check_template_id" class="effective-rule">
                    <span>当前生效：</span>
                    <strong>{{ effective.check_template_name || '未命名模板' }}</strong>
                    <span>，来源于 {{ effective.source_name || sourceTypeLabel(effective.source_type) }}</span>
                </div>

                <el-empty v-if="!templateOptions.length && !loading" description="还没有可用的质检模板" :image-size="72">
                    <el-button type="primary" :loading="initializing" @click="initializeDefaultTemplate">初始化默认模板</el-button>
                </el-empty>
            </section>

            <section v-if="selectedTemplateId" class="config-section">
                <div class="config-section__head">
                    <div>
                        <div class="config-section__title">2. 设置设备摘要</div>
                        <div class="config-section__desc">选择最常用的质检项，最多 10 项。摘要属于模板级配置，使用同一模板的型号会同步生效。</div>
                    </div>
                    <el-tag :type="summaryCount >= 10 ? 'warning' : 'info'" effect="plain">
                        已选 {{ summaryCount }} / 10
                    </el-tag>
                </div>

                <el-alert
                    v-if="isImportedTemplate"
                    type="warning"
                    show-icon
                    :closable="false"
                    title="当前是外部导入模板，其结构由数据源维护，暂不能在这里修改摘要字段。可改绑手工模板后设置。"
                    class="template-config__notice"
                />

                <el-collapse v-else-if="fieldGroups.length" v-model="activeGroups" class="summary-groups">
                    <el-collapse-item v-for="group in fieldGroups" :key="group.id" :name="String(group.id)">
                        <template #title>
                            <div class="summary-group__title">
                                <span>{{ group.group_name || '未命名分组' }}</span>
                                <span>{{ groupSummaryCount(group) }} 项摘要</span>
                            </div>
                        </template>
                        <div class="summary-field-list">
                            <div v-for="field in group.fields || []" :key="field.id" class="summary-field">
                                <div class="summary-field__main">
                                    <div class="summary-field__name">
                                        {{ field.field_name }}
                                        <el-tag v-if="Number(field.is_required) === 1" size="small" type="danger" effect="plain">必填</el-tag>
                                    </div>
                                    <div class="summary-field__meta">
                                        {{ componentLabel(field.component) }} · {{ field.field_key }}
                                    </div>
                                </div>
                                <el-switch
                                    :model-value="isSummaryVisible(field)"
                                    inline-prompt
                                    active-text="展示"
                                    inactive-text="隐藏"
                                    @change="value => toggleSummary(field, value)"
                                />
                            </div>
                        </div>
                    </el-collapse-item>
                </el-collapse>

                <el-empty v-else-if="!schemaLoading" description="该模板还没有质检字段" :image-size="72" />
            </section>
        </div>

        <template #footer>
            <div class="drawer-footer">
                <span class="drawer-footer__tip">保存后会立即刷新当前设备的质检摘要。</span>
                <div class="drawer-footer__actions">
                    <el-button @click="drawerVisible = false">取消</el-button>
                    <el-button
                        type="primary"
                        :loading="saving"
                        :disabled="!selectedTemplateId"
                        @click="saveConfig"
                    >保存并应用</el-button>
                </div>
            </div>
        </template>
    </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Iphone } from '@element-plus/icons-vue'
import {
    getTemplateBindingInfo,
    resetTemplateBinding,
    saveTemplateBinding
} from '@/addon/hsx_recycle/api/recycle_device_model_dict'
import {
    getCheckTemplateSchema,
    initDefaultCheckTemplate,
    saveCheckField
} from '@/addon/hsx_recycle/api/check_template'

const props = withDefaults(defineProps<{
    visible: boolean
    categoryId?: number | string
    modelName?: string
}>(), {
    visible: false,
    categoryId: 0,
    modelName: ''
})

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
    (event: 'saved'): void
}>()

const SCENE_KEY = 'manual_device_label'
const loading = ref(false)
const schemaLoading = ref(false)
const saving = ref(false)
const resetting = ref(false)
const initializing = ref(false)
const binding = ref<Record<string, any>>({})
const effective = ref<Record<string, any>>({})
const templateOptions = ref<any[]>([])
const selectedTemplateId = ref(0)
const selectedTemplate = ref<Record<string, any>>({})
const fieldGroups = ref<any[]>([])
const activeGroups = ref<string[]>([])
const originalSummaryState = ref<Record<string, boolean>>({})

const drawerVisible = computed({
    get: () => props.visible,
    set: value => emit('update:visible', value)
})

const summaryCount = computed(() => fieldGroups.value.reduce((count, group) => {
    return count + (group.fields || []).filter(isSummaryVisible).length
}, 0))
const isImportedTemplate = computed(() => selectedTemplate.value?.scene === 'pjt')

const normalizeExtraConfig = (config: any) => {
    if (!config) return {}
    if (typeof config === 'string') {
        try { return JSON.parse(config) || {} } catch (error) { return {} }
    }
    return { ...config }
}

const normalizeSchemaGroups = (groups: any[]) => (groups || []).map(group => ({
    ...group,
    fields: (group.fields || []).map((field: any) => ({
        ...field,
        group_id: field.group_id || group.id,
        extra_config: normalizeExtraConfig(field.extra_config)
    }))
}))

const isSummaryVisible = (field: any) => Number(field?.extra_config?.summary_visible || 0) === 1
const groupSummaryCount = (group: any) => (group.fields || []).filter(isSummaryVisible).length
const sourceTypeLabel = (type: string) => type === 'global' ? '通用兜底' : '上级型号规则'
const componentLabel = (component: string) => ({
    input: '输入框', textarea: '多行文本', radio: '单选', checkbox: '多选', select: '下拉选择', switch: '开关'
} as Record<string, string>)[component] || '质检项'

const loadConfig = async () => {
    if (!Number(props.categoryId)) return
    loading.value = true
    try {
        const res: any = await getTemplateBindingInfo({
            target_type: 'model_dict',
            target_id: Number(props.categoryId),
            scene_key: SCENE_KEY
        })
        const data = res.data || {}
        binding.value = data.binding || {}
        effective.value = data.effective || {}
        templateOptions.value = data.check_template_options || []
        selectedTemplateId.value = Number(binding.value.check_template_id || effective.value.check_template_id || 0)
        await loadSelectedTemplateSchema()
    } finally {
        loading.value = false
    }
}

const loadSelectedTemplateSchema = async () => {
    fieldGroups.value = []
    activeGroups.value = []
    originalSummaryState.value = {}
    selectedTemplate.value = templateOptions.value.find(item => Number(item.id) === Number(selectedTemplateId.value)) || {}
    if (!selectedTemplateId.value) return
    schemaLoading.value = true
    try {
        const res: any = await getCheckTemplateSchema({ template_id: selectedTemplateId.value })
        selectedTemplate.value = res.data?.template || selectedTemplate.value
        fieldGroups.value = normalizeSchemaGroups(res.data?.groups || [])
        activeGroups.value = fieldGroups.value.map(group => String(group.id))
        fieldGroups.value.forEach(group => (group.fields || []).forEach((field: any) => {
            originalSummaryState.value[String(field.id)] = isSummaryVisible(field)
        }))
    } finally {
        schemaLoading.value = false
    }
}

const toggleSummary = (field: any, value: any) => {
    const enabled = Boolean(value)
    if (enabled && !isSummaryVisible(field) && summaryCount.value >= 10) {
        ElMessage.warning('设备摘要最多展示 10 个字段')
        return
    }
    field.extra_config = { ...normalizeExtraConfig(field.extra_config), summary_visible: enabled ? 1 : 0 }
}

const saveConfig = async () => {
    if (!selectedTemplateId.value || saving.value) return
    saving.value = true
    try {
        await saveTemplateBinding({
            target_type: 'model_dict',
            target_id: Number(props.categoryId),
            scene_key: SCENE_KEY,
            check_template_id: selectedTemplateId.value,
            print_template_id: Number(binding.value.print_template_id || 0),
            inherit_enabled: Number(binding.value.inherit_enabled ?? 1),
            status: 1,
            remark: binding.value.remark || '',
            sort: Number(binding.value.sort || 0)
        })

        if (!isImportedTemplate.value) {
            const changedFields: any[] = []
            fieldGroups.value.forEach(group => (group.fields || []).forEach((field: any) => {
                if (originalSummaryState.value[String(field.id)] !== isSummaryVisible(field)) changedFields.push(field)
            }))
            for (const field of changedFields) {
                await saveCheckField({
                    ...field,
                    template_id: selectedTemplateId.value,
                    group_id: field.group_id,
                    extra_config: normalizeExtraConfig(field.extra_config)
                })
            }
        }

        ElMessage.success('质检模板与摘要字段已应用')
        emit('saved')
        drawerVisible.value = false
    } finally {
        saving.value = false
    }
}

const restoreInheritance = async () => {
    await ElMessageBox.confirm('恢复后，该型号将重新使用上级型号或全局模板。', '恢复继承', { type: 'warning' })
    resetting.value = true
    try {
        await resetTemplateBinding({
            target_type: 'model_dict',
            target_id: Number(props.categoryId),
            scene_key: SCENE_KEY
        })
        await loadConfig()
        ElMessage.success('已恢复继承规则')
        emit('saved')
    } finally {
        resetting.value = false
    }
}

const initializeDefaultTemplate = async () => {
    initializing.value = true
    try {
        await initDefaultCheckTemplate()
        await loadConfig()
    } finally {
        initializing.value = false
    }
}
</script>

<style lang="scss" scoped>
.template-config { min-height: 300px; }
.template-context { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid var(--el-border-color-light); border-radius: 8px; background: var(--el-fill-color-lighter); }
.template-context__icon { display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 8px; color: var(--el-color-primary); background: var(--el-color-primary-light-9); font-size: 20px; }
.template-context__body { flex: 1; min-width: 0; }
.template-context__label, .template-context__meta { color: var(--el-text-color-secondary); font-size: 12px; }
.template-context__name { margin: 2px 0; overflow: hidden; color: var(--el-text-color-primary); font-size: 16px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.template-config__notice { margin-top: 12px; }
.config-section { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--el-border-color-lighter); }
.config-section__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.config-section__title { color: var(--el-text-color-primary); font-size: 15px; font-weight: 600; }
.config-section__desc { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 18px; }
.template-option { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.effective-rule { margin-top: -4px; padding: 8px 10px; border-radius: 6px; color: var(--el-text-color-regular); background: var(--el-fill-color-lighter); font-size: 12px; }
.summary-groups { border-top: 0; }
.summary-group__title { display: flex; align-items: center; justify-content: space-between; width: 100%; padding-right: 12px; }
.summary-group__title span:last-child { color: var(--el-text-color-secondary); font-size: 12px; font-weight: 400; }
.summary-field-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; padding-bottom: 10px; }
.summary-field { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-width: 0; padding: 10px; border: 1px solid var(--el-border-color-lighter); border-radius: 6px; }
.summary-field__main { min-width: 0; }
.summary-field__name { display: flex; align-items: center; gap: 6px; color: var(--el-text-color-primary); font-size: 13px; font-weight: 500; }
.summary-field__meta { margin-top: 3px; overflow: hidden; color: var(--el-text-color-secondary); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.drawer-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; }
.drawer-footer__tip { color: var(--el-text-color-secondary); font-size: 12px; }
.drawer-footer__actions { display: flex; gap: 8px; }
@media (max-width: 640px) {
    .summary-field-list { grid-template-columns: 1fr; }
    .drawer-footer { align-items: flex-end; flex-direction: column; }
}
</style>
