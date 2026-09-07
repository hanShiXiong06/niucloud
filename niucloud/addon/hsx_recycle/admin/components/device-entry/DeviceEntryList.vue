<template>
    <div class="device-entry">
        <div class="device-entry__head">
            <div class="device-entry__title">
                设备清单
                <span class="device-entry__count">已保存 {{ savedDeviceCount }} / 共 {{ devices.length }} 台</span>
                <slot name="head-tip" />
            </div>
            <div class="device-entry__actions">
                <template v-if="enableLocalRead">
                    <el-tooltip content="开启后插入手机会自动读取并补全当前空行" placement="top">
                        <span class="device-entry__auto">
                            <el-switch v-model="autoLocal" size="small" @change="toggleAutoLocal" />
                            <span class="device-entry__auto-label">自动检测</span>
                        </span>
                    </el-tooltip>
                    <el-button size="small" :icon="Connection" :loading="localFetching" @click="readLocalDevices">
                        读取本地设备
                    </el-button>
                </template>
                <el-button type="primary" plain size="small" :icon="Plus" @click="addDeviceRow">
                    添加设备
                </el-button>
            </div>
        </div>

        <el-alert
            v-if="showModelEntryTip"
            class="model-entry-tip"
            type="info"
            show-icon
            closable
            @close="dismissModelEntryTip"
        >
            <template #title>
                <span>型号必须选择到最后一级，首次了解后可关闭此提示。</span>
            </template>
        </el-alert>

        <div class="device-table-head">
            <span></span>
            <span>SN / IMEI</span>
            <span>标准型号</span>
            <span>预估价</span>
            <span>买家图</span>
            <span>状态</span>
            <span class="is-right">操作</span>
        </div>

        <div class="device-list">
            <div
                v-for="(row, index) in devices"
                :key="row.id || row._k || index"
                class="device-entry-item"
            >
                <DeviceEntryCard
                    :device="row"
                    :index="index"
                    :can-remove="devices.length > 1"
                    @save="saveDeviceRow(row, index)"
                    @update="updateDeviceRow(row)"
                    @remove="removeDeviceRow(index)"
                    @edit-summary="openSummaryDialog(row)"
                    @configure-template="openTemplateConfig(row)"
                >
                    <template #model>
                        <div class="model-picker-wrap">
                            <div class="model-picker">
                                <el-cascader
                                    v-model="row.model_path"
                                    :options="modelTreeOptions"
                                    :props="modelCascaderProps"
                                    :before-filter="keyword => handleModelBeforeFilter(row, keyword)"
                                    :filter-method="modelSearchFilterMethod"
                                    :show-all-levels="false"
                                    placeholder="搜索或逐级选择型号"
                                    filterable
                                    clearable
                                    size="small"
                                    class="model-cascader"
                                    :loading="modelLoading"
                                    @visible-change="visible => onModelVisibleChange(row, visible)"
                                    @change="value => handleModelPathChange(row, value)"
                                />
                            </div>
                            <div v-if="row.model_search_empty" class="model-picker-feedback is-warning">
                                未找到“{{ row.model_search_keyword }}”，可选择已有型号或
                                <el-button link type="primary" size="small" @click="openQuickAddModel(row)">新增并关联</el-button>
                            </div>
                            <div v-else-if="row.model && !row.category_id" class="model-picker-feedback is-warning">
                                已识别“{{ row.model }}”，请选择标准型号；型号库没有时可
                                <el-button link type="primary" size="small" @click="openQuickAddModel(row)">新增并关联</el-button>
                            </div>
                        </div>
                    </template>
                    <template v-if="row.device_readings" #summary-actions>
                        <HsxDataArchive :data="row.device_readings" :reset-key="row.id || row._k"
                            :labels="{ local: '本地读取原文与提取值', model_match: '型号匹配记录', external_queries: '外部查询记录（如保修）' }" />
                    </template>
                </DeviceEntryCard>
            </div>
        </div>

        <CheckSummaryDialog
            v-model:visible="summaryDialogVisible"
            :fields="activeRow?.summary_fields || []"
            :values="activeRow?.summary_values || {}"
            :template-name="activeRow?.check_template_name || ''"
            :device-title="activeRow?.model || ''"
            :imei="activeRow?.imei || ''"
            :loading="!!activeRow?.summary_loading"
            :prefilled-keys="activeRow?.local_prefilled_keys || []"
            @query-result="handleQueryResult"
            @confirm="handleSummaryConfirm"
        />
        <CheckTemplateConfigDrawer
            v-model:visible="templateConfigVisible"
            :category-id="templateConfigRow?.category_id || 0"
            :model-name="templateConfigRow?.model || ''"
            @saved="handleTemplateConfigSaved"
        />
        <QuickAddModelDialog
            v-model:visible="quickAddModelVisible"
            :suggested-name="quickAddModelName"
            @created="handleQuickModelCreated"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Connection } from '@element-plus/icons-vue'
import { addOrderDevice, updateOrderDevice, deleteOrderDevice } from '@/addon/hsx_recycle/api/recycle_order'
import {
    bindRecycleDeviceModelAlias,
    getRecycleDeviceModelDictChildren,
    getRecycleDeviceModelDictOptions,
    getRecycleDeviceModelDictTree,
    resolveRecycleDeviceModelAlias
} from '@/addon/hsx_recycle/api/recycle_device_model_dict'
import { getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check_template'
import DeviceEntryCard from './DeviceEntryCard.vue'
import CheckSummaryDialog from './CheckSummaryDialog.vue'
import CheckTemplateConfigDrawer from './CheckTemplateConfigDrawer.vue'
import QuickAddModelDialog from './QuickAddModelDialog.vue'
import { useLocalDevice } from './useLocalDevice'
import { HsxDataArchive } from '@/addon/hsx_components/core'
import { localReadingArchive, prefillDeviceSummary as prefillSummaryFromLocal, recordModelMatch } from './deviceReadings'
import { validateSummaryRequired } from './summaryUtil'
import { normalizeDevice, buildUpdatePayload } from './deviceUtil'
import type { CheckSummaryField, DeviceEntryRow } from './types'

const props = withDefaults(defineProps<{
    /** 设备行数组（就地修改，建议父组件以响应式数组传入） */
    devices: DeviceEntryRow[]
    /** 已有订单ID；为空表示新建场景，保存首台时通过 ensureOrder 懒创建 */
    orderId?: number | string
    /** 新建场景下用于懒创建草稿订单，返回订单ID */
    ensureOrder?: () => Promise<number | string>
    /** 保存最后一行后是否自动追加空行 */
    autoAppend?: boolean
}>(), {
    orderId: '',
    ensureOrder: undefined,
    autoAppend: true
})

const savedDeviceCount = computed(() => props.devices.filter(d => d.saved && d.id).length)
const MODEL_ENTRY_TIP_CACHE_KEY = 'hsx_recycle:model_entry_tip:dismissed:v1'
const showModelEntryTip = ref(true)

const dismissModelEntryTip = () => {
    showModelEntryTip.value = false
    try {
        window.localStorage.setItem(MODEL_ENTRY_TIP_CACHE_KEY, '1')
    } catch (error) {
        console.warn('保存型号录入提示状态失败:', error)
    }
}

// ============ 型号字典级联 ============
const modelLoading = ref(false)
const modelTreeOptions = ref<any[]>([])
const modelNodeMap = ref<Record<string, any>>({})
const modelSearching = ref(false)
// 懒加载:只按 pid 取一层(children 接口),避免一次性拉 3 万条整树。
// 浏览(modelSearching=false)走 lazyLoad;搜索时切非懒加载、用扁平搜索结果(options,带完整 category_path)。
// 关键:只改 props.lazy 不会重挂组件,输入框焦点不丢,下拉面板按新模式重建。
const modelCascaderProps = computed(() => ({
    value: 'id',
    label: 'node_name',
    children: 'child_list',
    emitPath: true,
    checkStrictly: false,
    expandTrigger: 'hover' as const,
    lazy: !modelSearching.value,
    lazyLoad: async (node: any, resolve: (nodes: any[]) => void) => {
        const pid = node && node.level > 0 ? node.value : 0
        try {
            resolve(await loadModelChildren(pid))
        } catch (e) {
            console.error('懒加载型号子级失败:', e)
            resolve([])
        }
    },
}))
// 搜索结果已由后端按关键字过滤,前端不再二次过滤(否则会把命中父级名的结果误删)
const modelSearchFilterMethod = () => true

// 递归归一化整棵树:建 child_list、登记 modelNodeMap、按有无子节点标 leaf。兼容 child_list / children 两种字段。
const normalizeModelTree = (nodes: any[]): any[] => (nodes || []).map((item) => {
    const rawChildren = item.child_list || item.children || []
    const children = normalizeModelTree(rawChildren)
    const node = { ...item, leaf: children.length === 0, child_list: children.length ? children : undefined }
    modelNodeMap.value[String(node.id)] = node
    return node
})

const loadModelOptions = async () => {
    modelLoading.value = true
    try {
        modelNodeMap.value = {}
        const res = await getRecycleDeviceModelDictTree({})
        modelTreeOptions.value = normalizeModelTree(res.data || [])
    } catch (error) {
        console.error('加载型号字典失败:', error)
    } finally {
        modelLoading.value = false
    }
}

const loadModelChildren = async (pid: string | number = 0) => {
    const res = await getRecycleDeviceModelDictChildren({ pid, limit: 300 })
    return normalizeModelNodes(res.data || [])
}

const normalizeModelNodes = (nodes: any[]): any[] => (nodes || []).map((item) => {
    const node = { ...item, leaf: Number(item.has_children || 0) !== 1, child_list: undefined }
    modelNodeMap.value[String(node.id)] = node
    return node
})

const normalizeModelSearchNodes = (nodes: any[]): any[] => (nodes || []).map((item) => {
    const node = { ...item, leaf: true, child_list: undefined }
    modelNodeMap.value[String(node.id)] = node
    return node
})

// el-cascader 输入关键字时触发:空 → 回到懒加载浏览;有词 → 后端扁平搜索(结果带完整 category_path)并切非懒加载展示。
// 返回 Promise<boolean>:resolve(true) 后级联用新 options(非懒)重建面板展示搜索结果。
const handleModelBeforeFilter = (row: DeviceEntryRow, keyword: string) => {
    const value = String(keyword || '').trim()
    if (!value) {
        modelSearching.value = false
        row.model_search_keyword = ''
        row.model_search_empty = false
        return false
    }
    modelLoading.value = true
    row.model_search_keyword = value
    return getRecycleDeviceModelDictOptions({ keyword: value })
        .then((res: any) => {
            const nodes = normalizeModelSearchNodes(res.data || [])
            modelTreeOptions.value = nodes
            modelSearching.value = true
            row.model_search_empty = nodes.length === 0
            return true
        })
        .catch((error: any) => {
            console.error('搜索型号字典失败:', error)
            row.model_search_empty = false
            return false
        })
        .finally(() => {
            modelLoading.value = false
        })
}

// 下拉关闭后复位:清掉搜索态,下次打开回到懒加载浏览
const onModelVisibleChange = (row: DeviceEntryRow, visible: boolean) => {
    if (!visible && modelSearching.value) {
        modelSearching.value = false
        modelTreeOptions.value = []
    }
    if (!visible && !row.model_search_empty) row.model_search_keyword = ''
}

const normalizeModelSearchText = (value: any) => String(value || '').toLowerCase().replace(/[\s\-_\/\\.　]+/g, '')

const filterModelNode = (node: any, keyword: string) => {
    const value = normalizeModelSearchText(keyword)
    if (!value) return true
    return [node.text, node.label, node.data?.node_name, node.data?.model_full_name, node.data?.source_node_id]
        .some(item => normalizeModelSearchText(item).includes(value))
}

const handleModelPathChange = async (row: DeviceEntryRow, value: Array<string | number> | string | number) => {
    const autoResolvedCategoryId = Number(row.local_model_resolved_category_id || 0)
    const path = Array.isArray(value) ? value : [value]
    const leafId = path[path.length - 1]
    const leaf = modelNodeMap.value[String(leafId)] || null
    // 树形展开时 value 即完整路径；搜索结果是扁平的，节点自带 category_path(完整 id 路径)，优先用它
    const fullPath = (Array.isArray(leaf?.category_path) && leaf.category_path.length)
        ? leaf.category_path.map((v: any) => Number(v))
        : path.filter(item => item !== undefined && item !== null && item !== '').map((v: any) => Number(v))
    row.model = leaf?.node_name || row.model || ''
    row.category_id = Number(leafId) || 0
    row.category_path = fullPath
    row.model_path = fullPath
    row.model_search_keyword = ''
    row.model_search_empty = false
    recordModelMatch(row, row.category_id ? 'manual' : 'unmatched')
    if (row.saved) row.dirty = true
    if (row.category_id) {
        await loadCheckTemplate(row)
        prefillSummaryFromLocal(row, row)
        if ((row.local_model_aliases || []).length && autoResolvedCategoryId !== Number(row.category_id)) {
            await learnLocalModelAliases(row)
        }
    } else clearCheckTemplate(row)
}

// ============ 质检模板 / 摘要 ============
const clearCheckTemplate = (row: DeviceEntryRow) => {
    row.check_template_id = 0
    row.check_template_name = ''
    row.summary_fields = []
    row.summary_values = {}
    row.summary_default_keys = []
    row.local_prefilled_keys = []
    row.check_template_bound = false
    row.check_template_source_name = ''
    row.check_template_summary_count = 0
}

const loadCheckTemplate = async (row: DeviceEntryRow) => {
    const categoryId = Number(row.category_id || 0)
    if (!categoryId) {
        clearCheckTemplate(row)
        return
    }
    row.summary_loading = true
    try {
        const res = await getCheckTemplateSchema({ category_id: categoryId })
        const data = res?.data || {}
        const groups = data.groups || []
        const resolve = data.resolve || {}
        const template = data.template || {}

        const summaryFields: CheckSummaryField[] = []
        groups.forEach((group: any) => {
            (group.fields || []).forEach((field: any) => {
                if (Number(field?.extra_config?.summary_visible || 0) === 1 && summaryFields.length < 10) {
                    summaryFields.push({
                        id: Number(field.id),
                        field_key: String(field.field_key),
                        field_name: String(field.field_name),
                        component: String(field.component || 'input'),
                        selection_mode: field.selection_mode,
                        unit: field.unit,
                        placeholder: field.placeholder,
                        default_value: field.default_value,
                        is_required: Number(field.is_required || 0),
                        options: (field.options || []).map((opt: any) => ({
                            value: opt.value,
                            label: opt.label || opt.name,
                            name: opt.name
                        }))
                    })
                }
            })
        })

        row.check_template_id = Number(resolve.template_id || template.id || 0)
        row.check_template_name = String(resolve.template_name || template.template_name || '')
        row.check_template_bound = Boolean(resolve.matched)
        row.check_template_source_name = String(resolve.source_name || '')
        row.check_template_summary_count = summaryFields.length
        row.summary_fields = summaryFields

        const values: Record<string, any> = { ...(row.summary_values || {}) }
        const defaults = new Set(row.summary_default_keys || [])
        summaryFields.forEach((field) => {
            if (values[field.field_key] === undefined || values[field.field_key] === '') {
                const isMultiple = field.component === 'checkbox' || field.selection_mode === 'multiple'
                if (field.default_value !== undefined && field.default_value !== null && field.default_value !== '') {
                    values[field.field_key] = isMultiple
                        ? (Array.isArray(field.default_value) ? field.default_value : [field.default_value])
                        : field.default_value
                    defaults.add(field.field_key)
                } else {
                    values[field.field_key] = isMultiple ? [] : ''
                }
            }
        })
        row.summary_values = values
        row.summary_default_keys = [...defaults]
        return true
    } catch (error) {
        console.error('加载质检模板摘要字段失败:', error)
        clearCheckTemplate(row)
        return false
    } finally {
        row.summary_loading = false
    }
}

// ============ 质检摘要二次弹窗 ============
const summaryDialogVisible = ref(false)
const activeRow = ref<DeviceEntryRow | null>(null)

const openSummaryDialog = (row: DeviceEntryRow) => {
    activeRow.value = row
    summaryDialogVisible.value = true
}

const handleSummaryConfirm = (values: Record<string, any>) => {
    if (!activeRow.value) return
    activeRow.value.summary_values = { ...values }
    activeRow.value.local_prefilled_keys = []
    activeRow.value.summary_default_keys = []
    if (activeRow.value.saved) activeRow.value.dirty = true
}

const handleQueryResult = (result: Record<string, any>) => {
    const row = activeRow.value
    if (!row || !result.query_record_id) return
    row.device_readings ||= { version: 1 }
    const records = row.device_readings.external_queries || []
    if (!records.some(item => item.query_record_id === result.query_record_id)) {
        row.device_readings.external_queries = [...records, result]
        if (row.saved) row.dirty = true
    }
}

const templateConfigVisible = ref(false)
const templateConfigRow = ref<DeviceEntryRow | null>(null)

const openTemplateConfig = (row: DeviceEntryRow) => {
    if (!row.category_id) {
        ElMessage.warning('请先从型号库选择标准型号')
        return
    }
    templateConfigRow.value = row
    templateConfigVisible.value = true
}

const handleTemplateConfigSaved = async () => {
    const row = templateConfigRow.value
    if (!row) return
    await loadCheckTemplate(row)
    prefillSummaryFromLocal(row, row)
}

const quickAddModelVisible = ref(false)
const quickAddModelRow = ref<DeviceEntryRow | null>(null)
const quickAddModelName = ref('')

const openQuickAddModel = (row: DeviceEntryRow) => {
    quickAddModelRow.value = row
    quickAddModelName.value = String(row.model || row.model_search_keyword || '').trim()
    quickAddModelVisible.value = true
}

const handleQuickModelCreated = async (node: Record<string, any>) => {
    const row = quickAddModelRow.value
    const id = Number(node.id || 0)
    if (!row || !id) return
    const path = Array.isArray(node.category_path) && node.category_path.length
        ? node.category_path.map((value: any) => Number(value))
        : [id]
    row.category_id = id
    row.category_path = path
    row.model_path = path
    row.model = String(node.node_name || quickAddModelName.value)
    row.model_search_keyword = ''
    row.model_search_empty = false
    if (row.saved) row.dirty = true
    modelSearching.value = false
    modelTreeOptions.value = []
    await loadCheckTemplate(row)
    prefillSummaryFromLocal(row, row)
    await learnLocalModelAliases(row)
    recordModelMatch(row, 'manual')
    ElMessage.success(Number(node.created || 0) === 1 ? '型号已新增并关联' : '已关联型号库中的已有型号')
}

// ============ 行的增删 ============
let rowKeySeed = 1
const makeEmptyRow = (): DeviceEntryRow => ({
    imei: '', model: '', initial_price: 0, summary_fields: [], summary_values: {}, _k: rowKeySeed++ } as any)

const addDeviceRow = () => {
    props.devices.push(makeEmptyRow())
}

const removeDeviceRow = async (index: number) => {
    const row = props.devices[index]
    if (!row) return
    try {
        if (row.saved && row.id) {
            await ElMessageBox.confirm('确定删除该设备吗？', '提示', {
                confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
            })
            await deleteOrderDevice(Number(row.id))
            ElMessage.success('已删除')
        }
        props.devices.splice(index, 1)
        if (!props.devices.length) addDeviceRow()
    } catch (error: any) {
        if (error === 'cancel') return
        console.error('删除设备失败:', error)
        ElMessage.error(error.message || '删除设备失败')
    }
}

// ============ 保存 / 修改 ============
const resolveOrderId = async (): Promise<number | string> => {
    if (props.orderId) return props.orderId
    if (props.ensureOrder) return await props.ensureOrder()
    throw new Error('缺少订单信息')
}

const normalizeSerial = (value: any): string => String(value || '').trim().toUpperCase().replace(/[\s-]+/g, '')

const deviceSerialKeys = (device: any): string[] => Array.from(new Set([
    device?.imei,
    device?.imei2,
    device?.serial_number,
    device?.sn,
    device?.user_sn,
].map(normalizeSerial).filter(Boolean)))

const findDuplicateDevice = (device: any, exclude?: DeviceEntryRow): DeviceEntryRow | undefined => {
    const keys = new Set(deviceSerialKeys(device))
    if (!keys.size) return undefined
    return props.devices.find(row => row !== exclude && deviceSerialKeys(row).some(key => keys.has(key)))
}

const saveDeviceRow = async (row: DeviceEntryRow, index: number) => {
    const payload = normalizeDevice(row)
    if (findDuplicateDevice(row, row)) {
        ElMessage.warning('相同 IMEI/SN 已在设备清单中，本行不再保存')
        return
    }
    if (!payload.category_id) {
        ElMessage.warning('请选择到具体设备型号')
        return
    }
    const summaryError = validateSummaryRequired(row.summary_fields || [], row.summary_values || {})
    if (summaryError) {
        ElMessage.warning(summaryError)
        return
    }
    row.saving = true
    try {
        const oid = await resolveOrderId()
        const res = await addOrderDevice(Number(oid), payload)
        if (res.code !== 1) throw new Error(res.message || '保存设备失败')
        row.id = res.data.device_id
        row.saved = true
        row.dirty = false
        ElMessage.success('设备已保存')
        if (props.autoAppend && index === props.devices.length - 1) addDeviceRow()
    } catch (error: any) {
        console.error('保存设备失败:', error)
        ElMessage.error(error.message || '保存设备失败')
    } finally {
        row.saving = false
    }
}

const updateDeviceRow = async (row: DeviceEntryRow) => {
    if (!row.id) return
    if (findDuplicateDevice(row, row)) {
        ElMessage.warning('相同 IMEI/SN 已在设备清单中，本次修改不再保存')
        return
    }
    if (!row.category_id) {
        ElMessage.warning('请选择到具体设备型号')
        return
    }
    const summaryError = validateSummaryRequired(row.summary_fields || [], row.summary_values || {})
    if (summaryError) {
        ElMessage.warning(summaryError)
        return
    }
    row.saving = true
    try {
        const res = await updateOrderDevice(Number(row.id), buildUpdatePayload(row))
        if (res.code !== 1) throw new Error(res.message || '保存修改失败')
        row.dirty = false
        ElMessage.success('已保存修改')
    } catch (error: any) {
        console.error('保存设备修改失败:', error)
        ElMessage.error(error.message || '保存修改失败')
    } finally {
        row.saving = false
    }
}

// ============ 本地取机 ============
const enableLocalRead = true
const { fetching: localFetching, fetchConnected, mapToRow, describeError, startAuto, stopAuto } = useLocalDevice()
const autoLocal = ref(false)

const readLocalDevices = async () => {
    try {
        const list = await fetchConnected()
        if (!list.length) {
            ElMessage.warning('未检测到本地连接的设备')
            return
        }
        let applied = 0
        let skipped = 0
        let unresolved = 0
        for (const d of list) {
            const result = await applyLocalDevice(mapToRow(d))
            if (result.status === 'duplicate') {
                skipped += 1
                continue
            }
            applied += 1
            if (!result.matched) unresolved += 1
        }
        if (applied > 0) ElMessage.success(`已录入 ${applied} 台设备${skipped > 0 ? `，跳过 ${skipped} 台重复设备` : ''}`)
        else if (skipped > 0) ElMessage.info(`检测到的 ${skipped} 台设备均已在清单中`)
        if (unresolved > 0) ElMessage.warning(`${unresolved} 台设备未唯一匹配型号，请确认叶子分类`)
    } catch (error: any) {
        ElMessage.error(describeError(error))
    }
}

const toggleAutoLocal = (val: any) => {
    if (val) {
        startAuto(async (list: any[]) => {
            let applied = 0
            for (const d of list) {
                const result = await applyLocalDevice(mapToRow(d))
                if (result.status === 'applied') applied += 1
            }
            if (applied > 0) ElMessage.success(`检测到设备，已自动录入 ${applied} 台`)
        })
    } else {
        stopAuto()
    }
}

const applyLocalDevice = async (m: any) => {
    if (findDuplicateDevice(m)) {
        return { status: 'duplicate' as const, matched: true }
    }
    let row = props.devices.find(r => !r.saved && !r.model && !r.imei)
    if (!row) {
        addDeviceRow()
        row = props.devices[props.devices.length - 1]
    }
    if (!row) return { status: 'duplicate' as const, matched: true }
    row.imei = m.imei || m.serial_number || row.imei
    row.imei2 = m.imei2
    row.serial_number = m.serial_number
    row.device_readings = localReadingArchive(m)
    row.model = m.model || row.model
    row.color = m.color
    row.color_index = m.color_index
    row.capacity = m.capacity
    row.system_version = m.system_version
    row.warranty_info = m.warranty_info
    row.battery_health = m.battery_health
    row.battery_cycle_count = m.battery_cycle_count
    row.local_model_aliases = Array.from(new Set((m.model_candidates || [m.model])
        .map((item: any) => String(item || '').trim())
        .filter(Boolean)))
    row.local_model_resolved_category_id = 0
    recordModelMatch(row, 'unmatched')
    // 硬件映射优先；名称只用于数据库唯一精确匹配，不学习成公共别名。
    const matched = await matchModelToCategory(row, [...row.local_model_aliases, m.model, m.model ? `苹果 ${m.model}` : ''])
    prefillSummaryFromLocal(row, m)
    if (row.saved) row.dirty = true
    return { status: 'applied' as const, matched }
}

const matchModelToCategory = async (row: DeviceEntryRow, modelNames: string | string[]) => {
    const candidates = Array.from(new Set((Array.isArray(modelNames) ? modelNames : [modelNames])
        .map(item => String(item || '').trim()).filter(Boolean)))
    if (!candidates.length) return false
    try {
        // 人工学习映射优先于字面匹配。员工纠正一次后，相同工具型号可直接回显。
        if ((row.local_model_aliases || []).length) {
            try {
                const aliasRes = await resolveRecycleDeviceModelAlias(row.local_model_aliases || [])
                const mapped = aliasRes?.data || {}
                if (mapped.matched && mapped.node?.id) {
                    await applyResolvedModelNode(row, mapped.node)
                    row.local_model_resolved_category_id = Number(mapped.node.id)
                    recordModelMatch(row, 'alias')
                    return true
                }
            } catch (error) {
                // 映射服务异常不阻断原有精确匹配，确保录入仍可继续。
                console.warn('读取设备型号映射失败，已回退精确匹配:', error)
            }
        }

        const norm = (s: any) => String(s || '').toLowerCase().replace(/[\s\-_\/\\.　]+/g, '')
        for (const candidate of candidates) {
            const res = await getRecycleDeviceModelDictOptions({ keyword: candidate })
            const target = norm(candidate)
            const exact = (res.data || []).filter((node: any) => [
                node.node_name,
                node.model_full_name,
                node.source_node_id,
            ].some(value => norm(value) === target))
            const unique = Array.from(new Map(exact.map((node: any) => [Number(node.id), node])).values()) as any[]
            if (unique.length !== 1) continue
            const best = unique[0]
            await applyResolvedModelNode(row, best)
            row.local_model_resolved_category_id = Number(best.id)
            recordModelMatch(row, 'exact')
            return true
        }
    } catch (error) {
        console.error('型号匹配分类失败:', error)
    }
    return false
}

const applyResolvedModelNode = async (row: DeviceEntryRow, node: Record<string, any>) => {
    const categoryId = Number(node.id || 0)
    if (!categoryId) return
    const fullPath = (Array.isArray(node.category_path) && node.category_path.length)
        ? node.category_path.map((value: any) => Number(value))
        : [categoryId]
    row.category_id = categoryId
    row.category_path = fullPath
    row.model_path = fullPath
    row.model = String(node.node_name || row.model || '')
    row.model_search_keyword = ''
    row.model_search_empty = false
    await loadCheckTemplate(row)
}

const learnLocalModelAliases = async (row: DeviceEntryRow) => {
    const aliases = Array.from(new Set((row.local_model_aliases || [])
        .map(item => String(item || '').trim())
        .filter(Boolean)))
    const categoryId = Number(row.category_id || 0)
    if (!aliases.length || !categoryId || row.model_alias_learning) return

    row.model_alias_learning = true
    try {
        await bindRecycleDeviceModelAlias({ aliases, category_id: categoryId })
        row.local_model_resolved_category_id = categoryId
        ElMessage.success(`已记住“${aliases[0]}”对应的标准型号，下次将自动匹配`)
    } catch (error) {
        console.error('保存设备型号映射失败:', error)
    } finally {
        row.model_alias_learning = false
    }
}


// ============ 初始化 ============
const initExistingRows = () => {
    props.devices.forEach((row) => {
        if (!row.summary_values) row.summary_values = {}
        if (!row.summary_fields) row.summary_fields = []
        const path = Array.isArray(row.category_path) ? row.category_path : []
        if (row.category_id) {
            // 已有完整 id 路径：级联可直接按 id 反显选中；同步 model_path，加载质检模板
            row.model_path = path.map((v: any) => Number(v))
            if (!(row.summary_fields && row.summary_fields.length)) loadCheckTemplate(row)
        } else if (row.model) {
            // 无完整路径(后端只给了 category_id 或叶子)：按型号名解析出完整 id 路径并反显选中（严格匹配，避免误判）
            matchModelToCategory(row, row.model)
        }
    })
}

onMounted(() => {
    try {
        showModelEntryTip.value = window.localStorage.getItem(MODEL_ENTRY_TIP_CACHE_KEY) !== '1'
    } catch (error) {
        showModelEntryTip.value = true
    }
    // 不再一次性拉整棵型号树(3万条);级联改为懒加载,打开时按 pid 取一层
    modelNodeMap.value = {}
    initExistingRows()
})

defineExpose({ savedDeviceCount, addDeviceRow, stopAuto })
</script>

<style lang="scss" scoped>
.device-entry {
    width: 100%;
}

.device-entry__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.device-entry__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #303133;
}

.device-entry__count {
    color: #909399;
    font-size: 12px;
    font-weight: 400;
}

.device-entry__actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.device-entry__auto {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.device-entry__auto-label {
    font-size: 12px;
    color: #606266;
}

.model-entry-tip {
    margin-bottom: 10px;
}

.device-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.device-entry-item {
    min-width: 0;
}

.device-table-head {
    display: grid;
    grid-template-columns: 30px minmax(150px, 0.8fr) minmax(200px, 1.5fr) 100px 82px 64px 100px;
    align-items: center;
    gap: 10px;
    min-height: 36px;
    padding: 0 10px;
    border: 1px solid var(--el-border-color-lighter);
    background-color: var(--el-fill-color-light);
    color: var(--el-text-color-secondary);
    font-size: 12px;
}

.device-table-head .is-right { text-align: right; }

.model-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.model-picker-wrap {
    width: 100%;
    min-width: 0;
}

.model-picker-feedback {
    margin-top: 4px;
    color: var(--el-text-color-secondary);
    font-size: 11px;
    line-height: 16px;
}

.model-picker-feedback.is-warning {
    color: var(--el-color-warning-dark-2);
}

:deep(.model-picker-feedback .el-button) {
    height: auto;
    padding: 0 2px;
    vertical-align: baseline;
}

:deep(.el-cascader) {
    width: 100%;
}

.model-cascader {
    flex: 1 1 auto;
    min-width: 0;
}

.model-picker .el-input {
    flex: 1 1 auto;
    min-width: 0;
}

@media (max-width: 768px) {
    .device-table-head { display: none; }
    .device-list { gap: 8px; }
}
</style>
