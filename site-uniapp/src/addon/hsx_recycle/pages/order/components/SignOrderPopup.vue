<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="sign-popup">
            <!-- 头部 -->
            <view class="popup-header">
                <view class="popup-title">订单签收 · {{ devices.length }} 台设备</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx] text-[#999]" @click="handleClose"></text>
            </view>

            <!-- 提示 -->
            <view class="tip-bar">
                <text>请确认设备信息无误后签收，签收后将进入质检流程</text>
            </view>

            <!-- 设备列表 -->
            <scroll-view scroll-y class="device-list">
                <u-empty v-if="devices.length === 0" text="暂无设备，请点击下方添加" mode="list"></u-empty>

                <view v-for="(device, index) in devices" :key="device.id || index" class="device-card">
                    <view class="card-top">
                        <text class="card-index">{{ index + 1 }}</text>
                        <text class="card-model">{{ device.model || '未填写型号' }}</text>
                        <view class="card-actions">
                            <text class="nc-iconfont nc-icon-bianjiV6xx1 action-icon" @click="editDevice(device, index)"></text>
                            <text class="nc-iconfont nc-icon-shanchuV6xx1 action-icon action-icon--danger" @click="deleteDevice(index)"></text>
                        </view>
                    </view>
                    <view class="card-body">
                        <view class="card-row">
                            <text class="card-label">IMEI</text>
                            <text class="card-value card-value--mono">{{ device.imei || '未填写' }}</text>
                        </view>
                        <view v-if="device.user_sn" class="card-row">
                            <text class="card-label">用户串号</text>
                            <text class="card-value">{{ device.user_sn }}</text>
                        </view>
                        <view v-if="device.initial_price && Number(device.initial_price) > 0" class="card-row">
                            <text class="card-label">预估价</text>
                            <text class="card-value card-value--price">¥{{ device.initial_price }}</text>
                        </view>
                    </view>
                </view>

                <!-- 添加设备 -->
                <view class="add-btn" @click="addDevice">
                    <text class="nc-iconfont nc-icon-tianjiaV6xx1 text-[28rpx] mr-[8rpx]"></text>
                    <text>添加设备</text>
                </view>
            </scroll-view>

            <!-- 底部 -->
            <view class="popup-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">取消</u-button>
                <u-button type="primary" :loading="submitting" @click="handleSubmit" :customStyle="{ flex: 2 }">
                    确认签收
                </u-button>
            </view>
        </view>

        <!-- 编辑设备弹窗 -->
        <u-popup :show="editVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="closeEdit">
            <view class="edit-popup">
                <view class="popup-header">
                    <view class="popup-title">{{ editingIndex === -1 ? '添加设备' : '编辑设备' }}</view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx] text-[#999]" @click="closeEdit"></text>
                </view>

                <scroll-view scroll-y class="edit-body">
                    <u-form labelPosition="left" labelWidth="160rpx" errorType="toast">
                        <u-form-item label="IMEI串号" required :border-bottom="false">
                            <view class="form-control">
                                <ScanCodeInput
                                    v-model="editForm.imei"
                                    type="number"
                                    :maxlength="15"
                                    border="none"
                                    clearable
                                    placeholder="请输入15位IMEI"
                                    inputAlign="right"
                                    fontSize="28rpx"
                                    placeholderClass="text-[var(--text-color-light9)] text-[28rpx]"
                                    @scan="handleImeiScan"
                                />
                            </view>
                        </u-form-item>

                        <u-form-item label="设备型号" required :border-bottom="false">
                            <view class="model-select" @click="openModelPicker">
                                <text class="model-select__text" :class="{ 'model-select__placeholder': !editForm.model }">
                                    {{ editForm.model || '选择设备分类/末级设备' }}
                                </text>
                                <text class="nc-iconfont nc-icon-youV6xx1 model-select__icon"></text>
                            </view>
                        </u-form-item>
                    </u-form>

                    <view class="form-item">
                        <view class="form-label">预估价格 <text class="text-[22rpx] text-[#999]">（选填）</text></view>
                        <view class="price-input-wrap">
                            <text class="price-symbol">¥</text>
                            <u-input
                                v-model="editForm.initial_price"
                                type="number"
                                border="none"
                                clearable
                                placeholder="0.00"
                                class="price-input"
                                inputAlign="right"
                                fontSize="28rpx"
                                placeholderClass="text-[var(--text-color-light9)] text-[28rpx]"
                            ></u-input>
                        </view>
                    </view>

                </scroll-view>

                <view class="popup-footer">
                    <u-button @click="closeEdit" :customStyle="{ flex: 1 }">取消</u-button>
                    <u-button type="primary" @click="saveEdit" :customStyle="{ flex: 2 }">保存</u-button>
                </view>
            </view>
        </u-popup>

        <u-popup :show="modelPickerVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="closeModelPicker">
            <view class="model-picker-popup">
                <view class="popup-header">
                    <view class="popup-title">选择设备分类</view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx] text-[#999]" @click="closeModelPicker"></text>
                </view>

                <view class="model-search">
                    <u-input
                        v-model="modelKeyword"
                        border="none"
                        clearable
                        placeholder="搜索分类、路径或外部ID"
                        fontSize="26rpx"
                        @input="handleModelKeywordInput"
                        @change="handleModelKeywordInput"
                        @confirm="searchModelKeyword"
                    ></u-input>
                </view>
                <scroll-view v-if="hasModelKeyword" scroll-y class="model-search-list">
                    <view v-if="modelSearching" class="model-loading">搜索中...</view>
                    <block v-if="!modelSearching && modelSearchList.length">
                        <view
                            v-for="item in modelSearchList"
                            :key="item.id"
                            class="model-search-item"
                            @click="chooseModelNode(item)"
                        >
                            <text class="model-search-item__name">{{ item.model_full_name || item.node_name }}</text>
                            <text v-if="item.source_node_id" class="model-search-item__code">ID: {{ item.source_node_id }}</text>
                        </view>
                    </block>
                    <view v-if="!modelSearching && !modelSearchList.length" class="model-loading">没有匹配分类</view>
                </scroll-view>
                <view v-else class="model-cascade">
                    <view v-if="selectedModelPath.length" class="model-path">
                        <text
                            v-for="(name, index) in selectedModelPath"
                            :key="`${name}-${index}`"
                            class="model-path__item"
                        >
                            {{ name }}
                        </text>
                    </view>
                    <view class="model-column-row">
                        <scroll-view
                            v-for="column in visibleModelColumns"
                            :key="column.pid || 'root'"
                            scroll-y
                            class="model-column"
                        >
                            <view class="model-column__title">{{ getModelColumnTitle(column.index) }}</view>
                            <view
                                v-for="item in column.items"
                                :key="item.id"
                                class="model-option"
                                :class="{ 'model-option--active': String(column.activeId) === String(item.id) }"
                                @click="selectModelNode(item, column.index)"
                            >
                                <text class="model-option__name">{{ item.node_name }}</text>
                                <text v-if="isLeafNode(item)" class="model-option__leaf">选择</text>
                                <text v-else class="nc-iconfont nc-icon-youV6xx1 model-option__next"></text>
                            </view>
                        </scroll-view>
                        <view v-if="!modelColumns.length || !modelColumns[0].items.length" class="model-empty">
                            <text>暂无可选分类，请到 PC 端维护设备分类后再签收。</text>
                        </view>
                    </view>
                </view>
            </view>
        </u-popup>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getDeviceModelDictChildren, searchDeviceModelDictOptions, updateOrder } from '@/addon/hsx_recycle/api/order'
import ScanCodeInput from '@/addon/hsx_recycle/components/ScanCodeInput.vue'

interface Props {
    visible: boolean
    orderId: string | number
    deviceList: any[]
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
const devices = ref<any[]>([])
const modelColumns = ref<Array<{ pid: number | string, items: any[], activeId: string | number }>>([])
const modelKeyword = ref('')
const modelSearchList = ref<any[]>([])
const modelSearching = ref(false)
let modelSearchTimer: any = null
const modelPickerVisible = ref(false)

const editVisible = ref(false)
const editingIndex = ref(-1)
const editForm = ref({
    model: '',
    imei: '',
    user_sn: '',
    initial_price: '',
    category_id: 0,
    category_path: [] as Array<string | number>
})

const visibleModelColumns = computed(() => {
    const columns = modelColumns.value.map((column, index) => ({ ...column, index }))
    return columns.slice(Math.max(columns.length - 3, 0))
})

const selectedModelPath = computed(() => {
    return modelColumns.value
        .map(column => column.items.find(item => String(item.id) === String(column.activeId)))
        .filter(Boolean)
        .map(item => item.node_name || item.model_full_name || '')
        .filter(Boolean)
})

const hasModelKeyword = computed(() => modelKeyword.value.trim().length > 0)

const getModelColumnTitle = (index: number) => {
    const titles = ['品类', '品牌/子类', '系列', '型号']
    return titles[index] || `第${index + 1}级`
}

const isLeafNode = (node: any) => Number(node?.has_children || 0) !== 1 && (!Array.isArray(node?.child_list) || node.child_list.length === 0)

const normalizeModelTree = (nodes: any[] = []): any[] => {
    return nodes
        .filter(item => Number(item.status ?? 1) === 1)
        .map(item => {
            const children = normalizeModelTree(Array.isArray(item.child_list) ? item.child_list : [])
            return {
                ...item,
                child_list: children.length ? children : []
            }
        })
}

const loadModelChildren = async (pid = 0) => {
    try {
        const res: any = await getDeviceModelDictChildren({ pid, limit: 300 })
        return normalizeModelTree(res.data || [])
    } catch (error) {
        return []
    }
}

const loadRootModels = async () => {
    const roots = await loadModelChildren(0)
    modelColumns.value = [{ pid: 0, items: roots, activeId: '' }]
}

watch(() => props.visible, (val) => {
    show.value = val
    if (val) {
        devices.value = JSON.parse(JSON.stringify(props.deviceList || []))
    }
})

watch(() => show.value, (val) => {
    if (!val) emit('update:visible', false)
})

watch(() => modelKeyword.value, () => {
    if (modelSearchTimer) clearTimeout(modelSearchTimer)
    modelSearchTimer = setTimeout(searchModelKeyword, 300)
})

const handleClose = () => {
    show.value = false
    emit('update:visible', false)
}

const addDevice = () => {
    editingIndex.value = -1
    editForm.value = { model: '', imei: '', user_sn: '', initial_price: '', category_id: 0, category_path: [] }
    editVisible.value = true
}

const editDevice = (device: any, index: number) => {
    editingIndex.value = index
    editForm.value = {
        model: device.model || '',
        imei: device.imei || '',
        user_sn: device.user_sn || '',
        initial_price: device.initial_price || '',
        category_id: device.category_id || 0,
        category_path: normalizeCategoryPath(device.category_path || device.info?.goods_category || [], device.category_id || 0)
    }
    editVisible.value = true
}

const deleteDevice = (index: number) => {
    uni.showModal({
        title: '删除设备',
        content: '确定删除该设备？',
        success: (res) => {
            if (res.confirm) devices.value.splice(index, 1)
        }
    })
}

const closeEdit = () => {
    editVisible.value = false
}

const openModelPicker = async () => {
    modelKeyword.value = ''
    modelSearchList.value = []
    if (!modelColumns.value.length) {
        await loadRootModels()
    }
    modelPickerVisible.value = true
}

const closeModelPicker = () => {
    modelPickerVisible.value = false
}

const selectModelNode = async (node: any, columnIndex: number) => {
    modelColumns.value[columnIndex].activeId = node.id
    modelColumns.value = modelColumns.value.slice(0, columnIndex + 1)
    if (isLeafNode(node)) {
        chooseModelNode(node)
        return
    }
    const children = await loadModelChildren(node.id)
    modelColumns.value.push({ pid: node.id, items: children, activeId: '' })
}

const chooseModelNode = (node: any) => {
    if (!isLeafNode(node)) return
    editForm.value.model = node.model_full_name || node.node_name || ''
    editForm.value.category_id = node.id || 0
    editForm.value.category_path = resolveNodePath(node)
    closeModelPicker()
}

const resolveNodePath = (node: any): Array<string | number> => {
    if (Array.isArray(node?.category_path) && node.category_path.length) {
        return node.category_path
    }
    const activePath = modelColumns.value
        .map(column => column.activeId)
        .filter(item => item !== undefined && item !== null && item !== '')
    if (activePath.length && String(activePath[activePath.length - 1]) === String(node?.id)) {
        return activePath
    }
    if (node?.model_full_name) {
        return [node.id || 0].filter(Boolean)
    }
    return [node?.id || 0].filter(Boolean)
}

const normalizeCategoryPath = (value: any, categoryId: string | number = 0): Array<string | number> => {
    if (Array.isArray(value)) {
        return value.filter(item => item !== undefined && item !== null && item !== '')
    }
    if (typeof value === 'string' && value.trim()) {
        try {
            const parsed = JSON.parse(value)
            if (Array.isArray(parsed)) {
                return parsed.filter(item => item !== undefined && item !== null && item !== '')
            }
        } catch (error) {
            return value.split(',').map(item => item.trim()).filter(Boolean)
        }
    }
    return categoryId ? [categoryId] : []
}

const resolveInputValue = (event: any) => {
    if (typeof event === 'string' || typeof event === 'number') {
        return String(event)
    }
    return String(event?.detail?.value ?? event?.target?.value ?? event?.value ?? modelKeyword.value ?? '')
}

const handleModelKeywordInput = (event: any) => {
    modelKeyword.value = resolveInputValue(event)
}

const searchModelKeyword = async () => {
    const keyword = modelKeyword.value.trim()
    if (!keyword) {
        modelSearchList.value = []
        return
    }
    modelSearching.value = true
    try {
        const res: any = await searchDeviceModelDictOptions({ keyword })
        modelSearchList.value = normalizeModelTree(res.data || [])
    } catch (error) {
        console.error('搜索设备分类失败', error)
        uni.showToast({ title: '分类搜索失败，请稍后重试', icon: 'none' })
        modelSearchList.value = []
    } finally {
        modelSearching.value = false
    }
}

const saveEdit = () => {
    if (!editForm.value.imei) {
        uni.showToast({ title: '请输入IMEI串号', icon: 'none' })
        return
    }
    if (!editForm.value.model) {
        uni.showToast({ title: '请选择设备分类', icon: 'none' })
        return
    }

    const data = {
        ...editForm.value
    }

    if (editingIndex.value === -1) {
        devices.value.push(data)
    } else {
        devices.value[editingIndex.value] = { ...devices.value[editingIndex.value], ...data }
    }
    closeEdit()
}

const handleImeiScan = () => {
    uni.showToast({ title: '已录入扫码结果', icon: 'success' })
}

const handleSubmit = async () => {
    if (devices.value.length === 0) {
        uni.showToast({ title: '请至少添加一台设备', icon: 'none' })
        return
    }
    for (let i = 0; i < devices.value.length; i++) {
        if (!devices.value[i].imei) {
            uni.showToast({ title: `设备${i + 1}缺少IMEI`, icon: 'none' })
            return
        }
    }

    submitting.value = true
    try {
        await updateOrder(props.orderId, {
            action: 'order_sign',
            devices: devices.value.map(d => ({
                id: d.id,
                imei: d.imei,
                model: d.model,
                user_sn: d.user_sn || '',
                initial_price: Number(d.initial_price) || 0,
                category_id: d.category_id || 0,
                category_path: normalizeCategoryPath(d.category_path, d.category_id || 0)
            }))
        })
        uni.showToast({ title: '签收成功' })
        emit('success')
        handleClose()
    } catch (error: any) {
        uni.showToast({ title: error.message || '签收失败', icon: 'none' })
    } finally {
        submitting.value = false
    }
}
</script>

<style scoped lang="scss">
.sign-popup,
.edit-popup {
    background: #fff;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28rpx 30rpx;
    border-bottom: 1rpx solid #f0f0f0;
    flex-shrink: 0;
}

.popup-title {
    font-size: 30rpx;
    font-weight: 600;
    color: #333;
}

.tip-bar {
    padding: 16rpx 30rpx;
    background: #fffbe6;
    font-size: 24rpx;
    color: #d48806;
    flex-shrink: 0;
}

.device-list {
    flex: 1;
    padding: 20rpx 30rpx;
    max-height: 55vh;
    overflow-y: auto;
    box-sizing: border-box;
}

.empty-state {
    padding: 60rpx 0;
    text-align: center;
}

.empty-text {
    font-size: 26rpx;
    color: #999;
}

.device-card {
    background: #f8f9fa;
    border-radius: 12rpx;
    margin-bottom: 20rpx;
    overflow: hidden;
    box-sizing: border-box;
}

.card-top {
    display: flex;
    align-items: center;
    padding: 16rpx 20rpx;
    background: #f0f2f5;
}

.card-index {
    width: 36rpx;
    height: 36rpx;
    line-height: 36rpx;
    text-align: center;
    background: #2979ff;
    color: #fff;
    border-radius: 50%;
    font-size: 22rpx;
    margin-right: 12rpx;
    flex-shrink: 0;
}

.card-model {
    flex: 1;
    font-size: 28rpx;
    font-weight: 500;
    color: #333;
}

.card-actions {
    display: flex;
    gap: 24rpx;
}

.action-icon {
    font-size: 28rpx;
    color: #666;
}

.action-icon--danger {
    color: #f56c6c;
}

.card-body {
    padding: 16rpx 20rpx;
}

.card-row {
    display: flex;
    align-items: center;
    padding: 6rpx 0;
}

.card-label {
    font-size: 24rpx;
    color: #999;
    width: 120rpx;
    flex-shrink: 0;
}

.card-value {
    font-size: 24rpx;
    color: #333;
    flex: 1;
}

.card-value--mono {
    font-family: 'Courier New', monospace;
    letter-spacing: 1rpx;
}

.card-value--price {
    color: #e6a23c;
    font-weight: 500;
}

.add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24rpx;
    border: 2rpx dashed #ddd;
    border-radius: 12rpx;
    color: #2979ff;
    font-size: 26rpx;
    box-sizing: border-box;
}

.popup-footer {
    display: flex;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f0f0f0;
    gap: 20rpx;
    flex-shrink: 0;
    box-sizing: border-box;
}

/* 编辑弹窗 */
.edit-body {
    flex: 1;
    padding: 30rpx;
    max-height: 60vh;
    overflow-y: auto;
    box-sizing: border-box;
}

.form-item {
    margin-bottom: 28rpx;
}

.form-control {
    width: 100%;
    flex: 1;
    min-width: 0;
}

.form-label {
    font-size: 26rpx;
    color: #333;
    margin-bottom: 12rpx;
    font-weight: 500;
}

.model-select {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
    box-sizing: border-box;
    min-height: 64rpx;
}

.model-select__text {
    flex: 1;
    min-width: 0;
    font-size: 24rpx;
    color: #333;
    text-align: right;
    word-break: break-all;
}

.model-select__placeholder {
    color: var(--text-color-light9);
}

.model-select__icon {
    margin-left: 12rpx;
    color: #999;
    font-size: 24rpx;
}

.price-input-wrap {
    display: flex;
    align-items: center;
    border: 1rpx solid #e0e0e0;
    border-radius: 8rpx;
    padding: 20rpx;
    box-sizing: border-box;
}

.price-symbol {
    color: #e6a23c;
    font-size: 30rpx;
    font-weight: 600;
    margin-right: 8rpx;
}

.price-input-wrap .price-input {
    border: none;
    flex: 1;
}

.model-picker-popup {
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.model-empty {
    min-width: 480rpx;
    padding: 80rpx 48rpx;
    color: #777;
    font-size: 26rpx;
    line-height: 1.6;
    text-align: center;
}

.model-search {
    margin: 18rpx 24rpx;
    padding: 0 20rpx;
    background: #f6f7f9;
    border-radius: 12rpx;
    box-sizing: border-box;
    flex-shrink: 0;
}

.model-search-list {
    height: 58vh;
    min-height: 420rpx;
    max-height: 620rpx;
    border-top: 1rpx solid #f2f3f5;
    box-sizing: border-box;
}

.model-search-item {
    min-height: 82rpx;
    padding: 0 28rpx;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border-bottom: 1rpx solid #f2f3f5;
    box-sizing: border-box;
}

.model-search-item__name {
    font-size: 26rpx;
    color: #333;
    line-height: 1.35;
    width: 100%;
}

.model-search-item__code {
    width: 100%;
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #909399;
    line-height: 1.2;
}

.model-loading {
    padding: 60rpx 0;
    color: #999;
    font-size: 26rpx;
    text-align: center;
}

.model-cascade {
    flex: 1;
    min-height: 0;
    height: 0;
    border-top: 1rpx solid #f2f3f5;
    display: flex;
    flex-direction: column;
    background: #fff;
    overflow: hidden;
}

.model-path {
    min-height: 70rpx;
    padding: 14rpx 24rpx 10rpx;
    display: flex;
    align-items: center;
    overflow-x: auto;
    border-bottom: 1rpx solid #eef0f4;
    box-sizing: border-box;
    background: #fff;
    flex-shrink: 0;
}

.model-path__item {
    flex: 0 0 auto;
    max-width: 240rpx;
    padding: 8rpx 14rpx;
    border-radius: 999rpx;
    background: rgba(58, 107, 255, .08);
    color: var(--primary-color);
    font-size: 22rpx;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.model-path__item + .model-path__item {
    margin-left: 10rpx;
}

.model-column-row {
    flex-shrink: 0;
    height: 58vh;
    min-height: 420rpx;
    max-height: 560rpx;
    display: flex;
    overflow: hidden;
}

.model-column {
    flex: 1 1 0;
    width: 33.3333%;
    min-width: 0;
    height: 58vh;
    min-height: 420rpx;
    max-height: 560rpx;
    border-right: 1rpx solid #f2f3f5;
    background: #fafafa;
    box-sizing: border-box;
}

.model-column:last-child {
    border-right: none;
    background: #fff;
}

.model-column__title {
    position: sticky;
    top: 0;
    z-index: 1;
    height: 58rpx;
    padding: 0 16rpx;
    display: flex;
    align-items: center;
    color: #6b7280;
    font-size: 22rpx;
    font-weight: 600;
    background: #f6f7fb;
    border-bottom: 1rpx solid #eef0f4;
    box-sizing: border-box;
}

.model-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 84rpx;
    padding: 12rpx 14rpx;
    color: #333;
    font-size: 24rpx;
    line-height: 1.35;
    word-break: break-all;
    box-sizing: border-box;
    border-bottom: 1rpx solid #f2f3f5;
}

.model-option__name {
    flex: 1;
    min-width: 0;
    display: -webkit-box;
    overflow: hidden;
    text-overflow: ellipsis;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.model-option--active {
    color: var(--primary-color);
    background: #fff;
    font-weight: 600;
    box-shadow: inset 6rpx 0 0 var(--primary-color);
}

.model-option__leaf {
    margin-left: 8rpx;
    color: var(--primary-color);
    font-size: 22rpx;
    flex-shrink: 0;
}

.model-option__next {
    margin-left: 8rpx;
    color: #c0c4cc;
    font-size: 22rpx;
    flex-shrink: 0;
}
</style>
