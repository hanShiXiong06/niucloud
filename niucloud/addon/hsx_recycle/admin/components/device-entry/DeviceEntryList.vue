<template>
    <div class="device-entry">
        <div class="device-entry__head">
            <div class="device-entry__title">
                设备清单
                <span class="device-entry__count">已保存 {{ savedDeviceCount }} / 共 {{ devices.length }} 台</span>
                <slot name="head-tip" />
            </div>
            <div class="device-entry__actions">
                <!-- 本地读取暂时关闭（保留代码，置 enableLocalRead=true 即可恢复） -->
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

        <div class="device-list">
            <DeviceEntryCard
                v-for="(row, index) in devices"
                :key="row.id || row._k || index"
                :device="row"
                :index="index"
                :can-remove="devices.length > 1"
                @save="saveDeviceRow(row, index)"
                @update="updateDeviceRow(row)"
                @remove="removeDeviceRow(index)"
                @edit-summary="openSummaryDialog(row)"
            >
                <template #model>
                    <div class="model-picker">
                        <el-cascader
                            v-if="!row.model_input_mode"
                            v-model="row.model_path"
                            :options="modelTreeOptions"
                            :props="modelCascaderProps"
                            :before-filter="handleModelBeforeFilter"
                            :filter-method="modelSearchFilterMethod"
                            :show-all-levels="false"
                            placeholder="选择/搜索型号"
                            filterable
                            clearable
                            size="small"
                            class="model-cascader"
                            :loading="modelLoading"
                            @visible-change="onModelVisibleChange"
                            @change="value => handleModelPathChange(row, value)"
                        />
                        <el-input
                            v-else
                            v-model="row.model"
                            placeholder="输入型号或 品牌/系列/型号"
                            clearable
                            size="small"
                        />
                        <el-button
                            link
                            type="primary"
                            class="model-mode-button"
                            :icon="row.model_input_mode ? List : EditPen"
                            :title="row.model_input_mode ? '选择型号' : '手动输入'"
                            @click="toggleModelInputMode(row)"
                        />
                    </div>
                </template>
            </DeviceEntryCard>
        </div>

        <CheckSummaryDialog
            v-model:visible="summaryDialogVisible"
            :fields="activeRow?.summary_fields || []"
            :values="activeRow?.summary_values || {}"
            :template-name="activeRow?.check_template_name || ''"
            :device-title="activeRow?.model || ''"
            :imei="activeRow?.imei || ''"
            :loading="!!activeRow?.summary_loading"
            @confirm="handleSummaryConfirm"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, EditPen, List, Connection } from '@element-plus/icons-vue'
import { addOrderDevice, updateOrderDevice, deleteOrderDevice } from '@/addon/hsx_recycle/api/recycle_order'
import { getRecycleDeviceModelDictChildren, getRecycleDeviceModelDictOptions, getRecycleDeviceModelDictTree } from '@/addon/hsx_recycle/api/recycle_device_model_dict'
import { getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check_template'
import DeviceEntryCard from './DeviceEntryCard.vue'
import CheckSummaryDialog from './CheckSummaryDialog.vue'
import { useLocalDevice } from './useLocalDevice'
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
const handleModelBeforeFilter = (keyword: string) => {
    const value = String(keyword || '').trim()
    if (!value) {
        modelSearching.value = false
        return false
    }
    modelLoading.value = true
    return getRecycleDeviceModelDictOptions({ keyword: value })
        .then((res: any) => {
            modelTreeOptions.value = normalizeModelSearchNodes(res.data || [])
            modelSearching.value = true
            return true
        })
        .catch((error: any) => {
            console.error('搜索型号字典失败:', error)
            return false
        })
        .finally(() => {
            modelLoading.value = false
        })
}

// 下拉关闭后复位:清掉搜索态,下次打开回到懒加载浏览
const onModelVisibleChange = (visible: boolean) => {
    if (!visible && modelSearching.value) {
        modelSearching.value = false
        modelTreeOptions.value = []
    }
}

const normalizeModelSearchText = (value: any) => String(value || '').toLowerCase().replace(/[\s\-_\/\\.　]+/g, '')

const filterModelNode = (node: any, keyword: string) => {
    const value = normalizeModelSearchText(keyword)
    if (!value) return true
    return [node.text, node.label, node.data?.node_name, node.data?.model_full_name, node.data?.source_node_id]
        .some(item => normalizeModelSearchText(item).includes(value))
}

const handleModelPathChange = (row: DeviceEntryRow, value: Array<string | number> | string | number) => {
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
    if (row.saved) row.dirty = true
    if (row.category_id) loadCheckTemplate(row)
    else clearCheckTemplate(row)
}

const toggleModelInputMode = (row: DeviceEntryRow) => {
    row.model_input_mode = !row.model_input_mode
    if (row.model_input_mode) {
        row.model_path = []
        clearCheckTemplate(row)
    }
}

// ============ 质检模板 / 摘要 ============
const clearCheckTemplate = (row: DeviceEntryRow) => {
    row.check_template_id = 0
    row.check_template_name = ''
    row.summary_fields = []
    row.summary_values = {}
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
                if (Number(field?.extra_config?.summary_visible || 0) === 1 ) {
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
        row.summary_fields = summaryFields

        const values: Record<string, any> = { ...(row.summary_values || {}) }
        summaryFields.forEach((field) => {
            if (values[field.field_key] === undefined || values[field.field_key] === '') {
                const isMultiple = field.component === 'checkbox' || field.selection_mode === 'multiple'
                if (field.default_value !== undefined && field.default_value !== null && field.default_value !== '') {
                    values[field.field_key] = isMultiple
                        ? (Array.isArray(field.default_value) ? field.default_value : [field.default_value])
                        : field.default_value
                } else {
                    values[field.field_key] = isMultiple ? [] : ''
                }
            }
        })
        row.summary_values = values
    } catch (error) {
        console.error('加载质检模板摘要字段失败:', error)
        clearCheckTemplate(row)
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
    if (activeRow.value.saved) activeRow.value.dirty = true
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

const saveDeviceRow = async (row: DeviceEntryRow, index: number) => {
    const payload = normalizeDevice(row)
    if (!payload.imei && !payload.model) {
        ElMessage.warning('请填写 IMEI/SN 或设备型号')
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

// ============ 本地取机（暂时关闭）============
const enableLocalRead = false
const { fetching: localFetching, fetchConnected, mapToRow, describeError, startAuto, stopAuto } = useLocalDevice()
const autoLocal = ref(false)

const readLocalDevices = async () => {
    try {
        const list = await fetchConnected()
        if (!list.length) {
            ElMessage.warning('未检测到本地连接的设备')
            return
        }
        for (const d of list) await applyLocalDevice(mapToRow(d))
        ElMessage.success(`已读取 ${list.length} 台设备`)
    } catch (error: any) {
        ElMessage.error(describeError(error))
    }
}

const toggleAutoLocal = (val: any) => {
    if (val) {
        startAuto((list: any[]) => {
            list.forEach(async (d) => { await applyLocalDevice(mapToRow(d)) })
            ElMessage.success('检测到设备，已自动录入')
        })
    } else {
        stopAuto()
    }
}

const applyLocalDevice = async (m: any) => {
    let row = props.devices.find(r => !r.saved && !r.model && !r.imei)
    if (!row) {
        addDeviceRow()
        row = props.devices[props.devices.length - 1]
    }
    if (!row) return
    row.imei = m.imei || row.imei
    row.model = m.model || row.model
    row.color = m.color
    row.capacity = m.capacity
    row.system_version = m.system_version
    row.warranty_info = m.warranty_info
    row.battery_health = m.battery_health
    row.model_input_mode = true
    await matchModelToCategory(row, m.model)
    prefillSummaryFromLocal(row, m)
    if (row.saved) row.dirty = true
}

const matchModelToCategory = async (row: DeviceEntryRow, modelName: string, allowFallback = true) => {
    if (!modelName) return
    try {
        const res = await getRecycleDeviceModelDictOptions({ keyword: modelName })
        const nodes = res.data || []
        if (!nodes.length) return
        const norm = (s: any) => String(s || '').toLowerCase().replace(/[\s\-_\/\\.　]+/g, '')
        const target = norm(modelName)
        const best =
            nodes.find((n: any) => norm(n.node_name) === target) ||
            nodes.find((n: any) => norm(n.model_full_name) === target) ||
            nodes.find((n: any) => norm(n.node_name).includes(target) || target.includes(norm(n.node_name))) ||
            (allowFallback ? nodes[0] : null)
        if (best) {
            row.category_id = Number(best.id)
            // 字典搜索接口(searchLeafOptions/formatNode)返回 category_path = 完整 id 路径，用它做 id 反显
            const full = (Array.isArray(best.category_path) && best.category_path.length)
                ? best.category_path.map((v: any) => Number(v))
                : [Number(best.id)]
            row.category_path = full
            row.model_path = full
            // 不再用字典标准名覆盖型号:数据已规范,保留查询/录入的原始型号名(仅用它来匹配分类)。
            // if (best.node_name) row.model = best.node_name
            // 解析到完整路径后切到级联，真正"选中"该分类
            row.model_input_mode = false
            await loadCheckTemplate(row)
        }
    } catch (error) {
        console.error('型号匹配分类失败:', error)
    }
}

const prefillSummaryFromLocal = (row: DeviceEntryRow, m: any) => {
    const fields = row.summary_fields || []
    if (!fields.length) return
    const values = row.summary_values || {}
    const setIf = (key: string, val: any) => {
        if (val && fields.some(f => f.field_key === key)) values[key] = val
    }
    setIf('capacity', m.capacity)
    setIf('color', m.color)
    setIf('system_version', m.system_version)
    setIf('warranty_info', m.warranty_info)
    row.summary_values = { ...values }
}

// ============ 初始化 ============
const initExistingRows = () => {
    props.devices.forEach((row) => {
        if (!row.summary_values) row.summary_values = {}
        if (!row.summary_fields) row.summary_fields = []
        const path = Array.isArray(row.category_path) ? row.category_path : []
        if (path.length > 1) {
            // 已有完整 id 路径：级联可直接按 id 反显选中；同步 model_path，加载质检模板
            row.model_path = path.map((v: any) => Number(v))
            row.model_input_mode = false
            if (!(row.summary_fields && row.summary_fields.length)) loadCheckTemplate(row)
        } else if (row.model) {
            // 无完整路径(后端只给了 category_id 或叶子)：按型号名解析出完整 id 路径并反显选中（严格匹配，避免误判）
            matchModelToCategory(row, row.model, false)
        } else if (row.category_id) {
            loadCheckTemplate(row)
        }
    })
}

onMounted(() => {
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

.device-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.model-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
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

.model-picker .model-mode-button {
    flex: 0 0 auto;
    width: 28px;
    padding: 0;
}
</style>
