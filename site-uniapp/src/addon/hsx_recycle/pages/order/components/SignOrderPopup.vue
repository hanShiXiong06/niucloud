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
                        <view class="card-row">
                            <text class="card-label">分类</text>
                            <text class="card-value">{{ device.category_name || getCategoryName(device.category_id) || '手机' }}</text>
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
                            <u-input
                                v-model="editForm.model"
                                border="none"
                                clearable
                                placeholder="请输入设备型号"
                                inputAlign="right"
                                fontSize="28rpx"
                                placeholderClass="text-[var(--text-color-light9)] text-[28rpx]"
                            ></u-input>
                        </u-form-item>
                    </u-form>

                    <view class="form-item">
                        <view class="form-label">分类</view>
                        <u-radio-group v-model="editForm.category_id" placement="row" iconPlacement="left" class="category-select">
                            <u-radio
                                v-for="cat in categories"
                                :key="cat.id"
                                activeColor="var(--primary-color)"
                                :name="cat.id"
                                :label="cat.name"
                                labelColor="#333"
                                :labelSize="'26rpx'"
                                :customStyle="{ marginRight: '28rpx', marginBottom: '18rpx' }"
                            ></u-radio>
                        </u-radio-group>
                    </view>

                    <view class="form-item">
                        <view class="form-label">预估价格 <text class="text-[22rpx] text-[#999]">（选填）</text></view>
                        <view class="price-input-wrap">
                            <text class="price-symbol">¥</text>
                            <u-input
                                v-model="editForm.initial_price"
                                type="digit"
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
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { updateOrder } from '@/addon/hsx_recycle/api/order'
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

const categories = [
    { id: 1, name: '手机' },
    { id: 2, name: '平板' },
    { id: 3, name: '笔记本' },
    { id: 4, name: '手表' },
    { id: 5, name: '其他' },
]

const getCategoryName = (id: any) => {
    return categories.find(c => c.id == id)?.name || ''
}

const editVisible = ref(false)
const editingIndex = ref(-1)
const editForm = ref({
    model: '',
    imei: '',
    user_sn: '',
    initial_price: '',
    category_id: 1
})

watch(() => props.visible, (val) => {
    show.value = val
    if (val) {
        devices.value = JSON.parse(JSON.stringify(props.deviceList || []))
    }
})

watch(() => show.value, (val) => {
    if (!val) emit('update:visible', false)
})

const handleClose = () => {
    show.value = false
    emit('update:visible', false)
}

const addDevice = () => {
    editingIndex.value = -1
    editForm.value = { model: '', imei: '', user_sn: '', initial_price: '', category_id: 1 }
    editVisible.value = true
}

const editDevice = (device: any, index: number) => {
    editingIndex.value = index
    editForm.value = {
        model: device.model || '',
        imei: device.imei || '',
        user_sn: device.user_sn || '',
        initial_price: device.initial_price || '',
        category_id: device.category_id || 1
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

const saveEdit = () => {
    if (!editForm.value.imei) {
        uni.showToast({ title: '请输入IMEI串号', icon: 'none' })
        return
    }
    if (!editForm.value.model) {
        uni.showToast({ title: '请输入设备型号', icon: 'none' })
        return
    }

    const data = {
        ...editForm.value,
        category_name: getCategoryName(editForm.value.category_id)
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
                category_id: d.category_id || 1
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

.required {
    color: #f56c6c;
}

.form-input {
    width: 100%;
    height: 72rpx;
    padding: 0 20rpx;
    border: 1rpx solid #e0e0e0;
    border-radius: 8rpx;
    font-size: 28rpx;
    box-sizing: border-box;
    background: #fff;
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

.category-select {
    display: flex;
    flex-wrap: wrap;
}
</style>
