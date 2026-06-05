<template>
    <el-dialog
        v-model="dialogVisible"
        title="代客户下单"
        :width="isMobile ? '95vw' : '1120px'"
        top="4vh"
        class="add-order-dialog"
        :destroy-on-close="true"
        @closed="handleClosed"
    >
        <el-form
            ref="formRef"
            :model="form"
            :label-width="isMobile ? '80px' : '100px'"
            :label-position="isMobile ? 'top' : 'right'"
        >
            <el-form-item label="选择会员" required>
                <div class="member-select">
                    <el-input v-model="memberSearch" placeholder="输入会员手机号/昵称/用户名搜索" clearable :disabled="!!draftOrder.id"
                        @input="handleSearchInput">
                        <template #append>
                            <el-button @click="searchMember" :disabled="!!draftOrder.id">
                                <el-icon>
                                    <search />
                                </el-icon>
                            </el-button>
                        </template>
                    </el-input>

                    <div v-if="memberSearchResults.length > 0" class="search-results">
                        <div v-for="(user, index) in memberSearchResults" :key="index" class="search-result-item"
                            @click="handleMemberSelect(user)">
                            <div class="flex items-center">
                                <el-avatar :size="30" :src="user.headimg || ''" />
                                <div class="member-info">
                                    <div class="member-name">{{ user.nickname || user.username || '未设置昵称' }}</div>
                                    <div class="member-mobile">{{ user.mobile || '未绑定手机' }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="hasMoreMembers" class="search-result-more" @click="loadMoreMembers">
                            加载更多...
                        </div>
                    </div>

                    <div v-if="form.member_id && selectedMember" class="selected-member transition-all duration-300">
                        <div class="flex items-center justify-between transition-all duration-300">
                            <div class="flex items-center">
                                <el-avatar :size="40" :src="selectedMember.headimg || ''" />
                                <div class="member-info">
                                    <div class="member-name">
                                        {{ selectedMember.nickname || selectedMember.username || '未设置昵称' }}
                                    </div>
                                    <div class="member-mobile">{{ selectedMember.mobile || '未绑定手机' }}</div>
                                </div>
                            </div>
                            <el-button link type="danger" @click="clearSelectedMember" :disabled="!!draftOrder.id">
                                <el-icon>
                                    <delete />
                                </el-icon>
                            </el-button>
                        </div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="下单方式">
                <el-radio-group v-model="form.delivery_type" :disabled="!!draftOrder.id">
                    <el-radio label="1">快递</el-radio>
                    <el-radio label="2">门店</el-radio>
                </el-radio-group>
            </el-form-item>

            <el-form-item label="快递单号" v-if="form.delivery_type === '1'">
                <div class="express-input-wrapper">
                    <el-input ref="expressInput" v-model="form.express_no" placeholder="请输入快递单号或使用扫码枪"
                        @keydown.enter="handleScannerInput" @focus="handleInputFocus" @blur="handleInputBlur" :disabled="!!draftOrder.id" />
                    <el-button type="primary" @click="activateScanMode" :disabled="!!draftOrder.id">
                        <el-icon>
                            <ZoomOut />
                        </el-icon>
                        扫码
                    </el-button>
                </div>
                <div class="scan-tip" v-if="isScanMode">
                    <el-icon>
                        <Loading class="is-loading" />
                    </el-icon>
                    <span>准备扫码中，请对准条码...</span>
                </div>
            </el-form-item>
            <el-form-item label="" class="device-form-item">
                <div class="device-entry">
                    <div class="device-entry__head">
                        <el-tag v-if="draftOrder.id" type="info" effect="plain">草稿订单：{{ draftOrder.order_no }}</el-tag>
                        <span v-else class="device-entry__count">已保存 {{ savedDeviceCount }} 台设备</span>
                        <el-button type="primary" plain size="small" :icon="Plus" @click="addDeviceRow">
                            添加录入行
                        </el-button>
                    </div>
                    <el-table :data="form.devices" border size="small" class="device-entry__table">
                        <el-table-column label="IMEI/SN" min-width="180">
                            <template #default="{ row }">
                                <el-input v-model="row.imei" placeholder="可选，支持扫码枪" clearable :disabled="row.saved" />
                            </template>
                        </el-table-column>
                        <el-table-column label="设备型号" min-width="430">
                            <template #default="{ row }">
                                <el-tooltip v-if="row.saved" :content="row.model || '未填写'" placement="top" :show-after="300">
                                    <div class="model-display">{{ row.model || '未填写' }}</div>
                                </el-tooltip>
                                <div v-else class="model-picker">
                                    <el-cascader
                                        v-if="!row.model_input_mode"
                                        v-model="row.model_path"
                                        :options="modelTreeOptions"
                                        :props="modelCascaderProps"
                                        :filter-method="filterModelNode"
                                        :before-filter="handleModelBeforeFilter"
                                        placeholder="选择品牌/系列/型号"
                                        filterable
                                        clearable
                                        class="model-cascader"
                                        :loading="modelLoading"
                                        @change="value => handleModelPathChange(row, value)"
                                    />
                                    <el-input
                                        v-else
                                        v-model="row.model"
                                        placeholder="输入型号或 品牌/系列/型号"
                                        clearable
                                    />
                                    <el-button
                                        link
                                        type="primary"
                                        class="model-mode-button"
                                        :icon="row.model_input_mode ? List : EditPen"
                                        :title="row.model_input_mode ? '选择型号' : '手动输入'"
                                        @click="toggleModelInputMode(row)"
                                    >
                                    </el-button>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="预估价" width="130">
                            <template #default="{ row }">
                                <el-input-number
                                    v-model="row.initial_price"
                                    :min="0"
                                    :controls="false"
                                    placeholder="选填"
                                    class="w-full"
                                    :disabled="row.saved"
                                />
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="90" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.saved" type="success" size="small">已保存</el-tag>
                                <el-tag v-else type="info" size="small">待保存</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="150" align="center">
                            <template #default="{ row, $index }">
                                <el-button
                                    v-if="!row.saved"
                                    link
                                    type="primary"
                                    :loading="row.saving"
                                    @click="saveDeviceRow(row, $index)"
                                >
                                    保存
                                </el-button>
                                <el-button
                                    link
                                    type="danger"
                                    :icon="Delete"
                                    :disabled="form.devices.length <= 1"
                                    @click="removeDeviceRow($index)"
                                />
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </el-form-item>
        </el-form>

        <template #footer>
            <div :class="isMobile ? 'dialog-footer mobile-footer' : 'dialog-footer'">
                <el-button :class="isMobile ? '!ml-0 w-full' : ''" @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :class="isMobile ? '!ml-0 w-full' : ''" @click="handleConfirm" :loading="loading" :disabled="savedDeviceCount === 0">
                    完成签收
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, defineProps, defineEmits, watch, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete, Loading, Aim, Plus, ZoomOut, EditPen, List } from '@element-plus/icons-vue'
import { addOrderDevice, createRecycleOrder, getUserByMobile, updateRecycleOrder } from '@/addon/hsx_recycle/api/recycle_order'
import { getRecycleDeviceModelDictChildren, getRecycleDeviceModelDictOptions } from '@/addon/hsx_recycle/api/recycle_device_model_dict'
// import { searchMembers } from '@/api/member'

interface Member {
    member_id: string | number;
    nickname?: string;
    username?: string;
    mobile?: string;
    headimg?: string;
}

interface DraftDeviceRow {
    id?: number | string;
    imei: string;
    model: string;
    initial_price: number;
    category_id?: string | number;
    category_path?: Array<string | number>;
    saved?: boolean;
    saving?: boolean;
    model_path?: Array<string | number>;
    model_input_mode?: boolean;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:visible', 'success', 'closed'])

// 内部状态
const dialogVisible = ref(props.visible)
const loading = ref(false)
const form = ref({
    member_id: '',
    delivery_type: '1',
    express_no: '',
    devices: [
        { imei: '', model: '', initial_price: 0 }
    ] as DraftDeviceRow[]
})
const draftOrder = ref<{ id: number | string; order_no: string }>({ id: '', order_no: '' })
const modelLoading = ref(false)
const modelTreeOptions = ref<any[]>([])
const modelNodeMap = ref<Record<string, any>>({})
const modelCascaderProps = {
    value: 'id',
    label: 'node_name',
    children: 'child_list',
    leaf: 'leaf',
    emitPath: true,
    checkStrictly: false,
    expandTrigger: 'hover' as const,
    lazy: true,
    lazyLoad: async (node: any, resolve: (nodes: any[]) => void) => {
        const pid = node?.level ? node.value : 0
        const children = await loadModelChildren(pid)
        resolve(children)
    }
}
const savedDeviceCount = computed(() => form.value.devices.filter(device => device.saved && device.id).length)

// 会员搜索相关
const memberSearch = ref('')
const memberSearchResults = ref<Member[]>([])
const memberPage = ref(1)
const memberPageSize = ref(10)
const hasMoreMembers = ref(false)
const selectedMember = ref<Member | null>(null)
const searchTimer = ref<number | null>(null)
const isMobile = ref(false)

// 扫码相关
const expressInput = ref(null)
const isScanMode = ref(false)
const scanBuffer = ref('')
const scanTimer = ref(null)

const updateResponsiveState = () => {
    isMobile.value = window.innerWidth <= 768
}

const loadModelOptions = async () => {
    modelLoading.value = true
    try {
        modelNodeMap.value = {}
        modelTreeOptions.value = await loadModelChildren(0)
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

const normalizeModelNodes = (nodes: any[]): any[] => {
    return (nodes || []).map((item) => {
        const hasChildren = Number(item.has_children || 0) === 1
        const node = {
            ...item,
            leaf: !hasChildren,
            child_list: undefined
        }
        modelNodeMap.value[String(node.id)] = node
        return node
    })
}

const normalizeModelSearchNodes = (nodes: any[]): any[] => {
    return (nodes || []).map((item) => {
        const node = {
            ...item,
            leaf: true,
            child_list: undefined
        }
        modelNodeMap.value[String(node.id)] = node
        return node
    })
}

const handleModelBeforeFilter = async (keyword: string) => {
    const value = String(keyword || '').trim()
    modelLoading.value = true
    try {
        if (!value) {
            modelTreeOptions.value = await loadModelChildren(0)
            return true
        }
        const res = await getRecycleDeviceModelDictOptions({ keyword: value })
        modelTreeOptions.value = normalizeModelSearchNodes(res.data || [])
        return true
    } catch (error) {
        console.error('搜索型号字典失败:', error)
        return false
    } finally {
        modelLoading.value = false
    }
}

 const filterModelNode = (node: any, keyword: string) => {
      const value = String(keyword || '').toLowerCase()
      return [
          node.text,
          node.label,
          node.data?.node_name,
          node.data?.model_full_name,
          node.data?.source_node_id
      ].some(item => String(item || '').toLowerCase().includes(value))
  }

const handleModelPathChange = (row: DraftDeviceRow, value: Array<string | number> | string | number) => {
    const path = Array.isArray(value) ? value : [value]
    const leafId = path[path.length - 1]
    const leaf = modelNodeMap.value[String(leafId)] || null
    row.model =  leaf?.node_name || ''
    row.category_id = leafId || 0
    row.category_path = path.filter(item => item !== undefined && item !== null && item !== '')
}

const toggleModelInputMode = (row: DraftDeviceRow) => {
    row.model_input_mode = !row.model_input_mode
    if (row.model_input_mode) {
        row.model_path = []
    }
}

const addDeviceRow = () => {
    form.value.devices.push({ imei: '', model: '', initial_price: 0 })
}

const removeDeviceRow = (index: number) => {
    if (form.value.devices.length <= 1) return
    if (form.value.devices[index]?.saved) {
        ElMessage.warning('已保存设备请在订单详情中删除，避免误删')
        return
    }
    form.value.devices.splice(index, 1)
}

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
    if (newVal) {
        loadModelOptions()
    }
})

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 处理会员搜索输入
const handleSearchInput = () => {
    // 防抖处理
    if (searchTimer.value) {
        clearTimeout(searchTimer.value)
    }
    searchTimer.value = window.setTimeout(() => {
        if (memberSearch.value) {
            searchMember()
        } else {
            memberSearchResults.value = []
        }
    }, 300) as unknown as null
}

// 搜索会员
const searchMember = async () => {
    if (!memberSearch.value) {
        memberSearchResults.value = []
        return
    }

    try {
        const params = {
            page: memberPage.value,
            page_size: memberPageSize.value,
            keyword: memberSearch.value
        }
        const res = await getUserByMobile(params.keyword)
        if (res.code === 1) {
            memberSearchResults.value = res.data.data || []
            hasMoreMembers.value = res.data.count > memberPage.value * memberPageSize.value
        } else {
            ElMessage.error(res.message || '搜索会员失败')
        }
    } catch (error) {
        console.error('搜索会员失败:', error)
        ElMessage.error('搜索会员失败')
    }
}

// 加载更多会员
const loadMoreMembers = async () => {
    memberPage.value++
    await searchMember()
}

// 选择会员
const handleMemberSelect = (user: Member) => {
    selectedMember.value = user
    form.value.member_id = user.member_id
    memberSearchResults.value = [] // 清空搜索结果
}

// 清除已选会员
const clearSelectedMember = () => {
    selectedMember.value = null
    form.value.member_id = ''
}

// 激活扫码模式
const activateScanMode = () => {
    if (expressInput.value) {
        // 使用原生DOM方法获取焦点
        expressInput.value.$el.querySelector('input').focus()
        isScanMode.value = true
        ElMessage.info('请将扫码枪对准条码进行扫描')
    }
}

// 处理输入框获取焦点
const handleInputFocus = () => {
    isScanMode.value = true
}

// 处理输入框失去焦点
const handleInputBlur = () => {
    // 使用window.setTimeout而不是直接绑定到组件上
    window.setTimeout(() => {
        isScanMode.value = false
    }, 100)
}

// 处理扫码枪输入
const handleScannerInput = (event: KeyboardEvent) => {
    if (isScanMode.value) {
        // 大多数扫码枪会在扫描完成后自动发送回车，这里我们捕获回车事件
        if (event.key === 'Enter') {
            // 如果输入框有值，说明扫码成功
            if (form.value.express_no) {
                ElMessage.success('扫码成功：' + form.value.express_no)
                isScanMode.value = false
            }
        }
    }
}

// 监听键盘事件（针对更复杂的扫码器行为）
const handleKeyDown = (event: KeyboardEvent) => {
    if (isScanMode.value) {
        // 某些扫码枪会快速输入字符，我们可以通过检测输入速度来判断是否是扫码枪

        if (scanTimer.value) {
            clearTimeout(scanTimer.value)
        }

        // 设置一个超时，如果一段时间内没有新输入，就认为扫码结束
        scanTimer.value = window.setTimeout(() => {
            if (scanBuffer.value) {
                // 将缓冲区的内容设置到表单中
                form.value.express_no = scanBuffer.value
                scanBuffer.value = ''
                ElMessage.success('扫码成功：' + form.value.express_no)
                isScanMode.value = false
            }
        }, 100) as unknown as null
    }
}

// 生命周期钩子
onMounted(() => {
    updateResponsiveState()
    window.addEventListener('resize', updateResponsiveState)
    // 添加全局键盘事件监听
    window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateResponsiveState)
    // 移除全局键盘事件监听
    window.removeEventListener('keydown', handleKeyDown)

    // 清理定时器
    if (scanTimer.value) {
        clearTimeout(scanTimer.value)
    }
    if (searchTimer.value) {
        clearTimeout(searchTimer.value)
    }
})

const validateBaseOrder = () => {
    if (!form.value.member_id) {
        // ElMessage.warning('请选择会员')
        return false
    }

    if (form.value.delivery_type === '1' && !form.value.express_no) {
        ElMessage.warning('请输入快递单号')
        return false
    }

    return true
}

const ensureDraftOrder = async () => {
    if (draftOrder.value.id) {
        return draftOrder.value
    }
    if (!validateBaseOrder()) {
        throw new Error('请先补全订单信息')
    }

    const res = await createRecycleOrder({
        member_id: form.value.member_id,
        delivery_type: form.value.delivery_type,
        express_no: form.value.delivery_type === '1' ? form.value.express_no : '',
        count: 0,
        order_source: 'agent',
        sign_after_create: false,
        draft_device_entry: true,
        devices: []
    })
    if (res.code !== 1) {
        throw new Error(res.message || '创建草稿订单失败')
    }
    draftOrder.value = {
        id: res.data.id,
        order_no: res.data.order_no
    }
    ElMessage.success(`草稿订单已创建：${res.data.order_no}`)
    return draftOrder.value
}

const normalizeDevice = (device: DraftDeviceRow) => ({
    imei: (device.imei || '').trim(),
    model: (device.model || '').trim(),
    initial_price: Number(device.initial_price || 0),
    category_id: device.category_id || 0,
    category_path: Array.isArray(device.category_path) ? device.category_path : []
})

const saveDeviceRow = async (row: DraftDeviceRow, index: number) => {
    const payload = normalizeDevice(row)
    if (!payload.imei && !payload.model) {
        ElMessage.warning('请填写 IMEI/SN 或设备型号')
        return
    }

    row.saving = true
    try {
        const order = await ensureDraftOrder()
        const res = await addOrderDevice(Number(order.id), payload)
        if (res.code !== 1) {
            throw new Error(res.message || '保存设备失败')
        }
        row.id = res.data.device_id
        row.saved = true
        ElMessage.success('设备已保存')
        if (index === form.value.devices.length - 1) {
            addDeviceRow()
        }
    } catch (error: any) {
        console.error('保存设备失败:', error)
        ElMessage.error(error.message || '保存设备失败')
    } finally {
        row.saving = false
    }
}

// 完成签收
const handleConfirm = async () => {
    if (!draftOrder.value.id) {
        ElMessage.warning('请先保存至少一台设备')
        return
    }
    const savedDevices = form.value.devices.filter(device => device.saved && device.id)
    if (savedDevices.length === 0) {
        ElMessage.warning('请先保存至少一台设备')
        return
    }

    try {
        loading.value = true

        await ElMessageBox.confirm(
            `确定签收订单 ${draftOrder.value.order_no} 下的 ${savedDevices.length} 台设备吗？`,
            '完成签收',
            {
                confirmButtonText: '确认签收',
                cancelButtonText: '取消',
                type: 'warning',
            }
        )

        const res = await updateRecycleOrder(Number(draftOrder.value.id), {
            action: 'order_sign',
            devices: savedDevices.map((device) => ({
                id: device.id,
                ...normalizeDevice(device)
            }))
        })
        if (res.code !== 1) {
            throw new Error(res.message || '签收失败')
        }

        ElMessage.success('订单签收成功')
        resetForm()
        dialogVisible.value = false
        emit('success')
    } catch (error) {
        if (error === 'cancel') return // 用户取消操作
        console.error('订单签收失败:', error)
        ElMessage.error(error.message || '订单签收失败')
    } finally {
        loading.value = false
    }
}

// 重置表单
const resetForm = () => {
    form.value = {
        member_id: '',
        delivery_type: '1',
        express_no: '',
        devices: [
            { imei: '', model: '', initial_price: 0 }
        ]
    }
    draftOrder.value = { id: '', order_no: '' }
    selectedMember.value = null
    memberSearch.value = ''
    memberSearchResults.value = []
    memberPage.value = 1
    isScanMode.value = false
    scanBuffer.value = ''
}

// 对话框关闭处理
const handleClosed = () => {
    resetForm()
    emit('closed')
}

// 初始化
if (props.visible) {
    // 可以在这里进行一些初始化操作
}
</script>

<style lang="scss" scoped>
.member-select {
    width: 100%;
    position: relative;

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        background-color: #fff;
        border: 1px solid #e4e7ed;
        border-radius: 4px;
        box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
        z-index: 100;
        margin-top: 5px;

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;

            &:hover {
                background-color: #f5f7fa;
            }

            .member-info {
                margin-left: 10px;

                .member-name {
                    font-size: 14px;
                    font-weight: 500;
                }

                .member-mobile {
                    font-size: 12px;
                    color: #909399;
                }
            }
        }

        .search-result-more {
            padding: 10px;
            text-align: center;
            color: #409eff;
            cursor: pointer;
            font-size: 14px;

            &:hover {
                background-color: #f5f7fa;
            }
        }
    }

    .selected-member {
        margin-top: 10px;
        padding: 10px;
        border: 1px solid #e4e7ed;
        border-radius: 4px;
        background-color: #f5f7fa;

        .member-info {
            margin-left: 10px;

            .member-name {
                font-size: 14px;
                font-weight: 500;
            }

            .member-mobile {
                font-size: 12px;
                color: #606266;
            }
        }
    }
}

.express-input-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;

    .el-input {
        flex: 1;
    }
}

.scan-tip {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    color: #409eff;
    font-size: 12px;
}

.device-entry {
    width: 100%;
}

.device-form-item {
    :deep(.el-form-item__content) {
        margin-left: 0 !important;
        max-width: 100%;
    }
}

.device-entry__head {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 12px;
}

.device-entry__count {
    color: #64748b;
}

.device-entry__table {
    width: 100%;
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

:deep(.el-input-number) {
    width: 100px;
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

.model-display {
    color: #303133;
    font-weight: 500;
    line-height: 1.45;
    white-space: normal;
    word-break: break-all;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

@media (max-width: 768px) {
    .mobile-footer {
        width: 100%;
        flex-direction: column;
    }

    .express-input-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
}
</style>
