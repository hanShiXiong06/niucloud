<template>
    <u-popup :show="visible" mode="right" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="order-filter">
            <view class="order-filter__header">
                <view>
                    <text class="order-filter__title">订单筛选</text>
                    <text class="order-filter__desc">按 PC 端常用条件精确定位</text>
                </view>
                <text class="nc-iconfont nc-icon-cuohaoV6mm order-filter__close" @click="handleClose"></text>
            </view>

            <scroll-view scroll-y class="order-filter__body">
                <view class="filter-section">
                    <view class="filter-section__title">订单状态</view>
                    <view class="status-grid">
                        <view
                            v-for="item in statusOptions"
                            :key="item.value"
                            class="status-item"
                            :class="{ 'status-item--active': form.status === item.value }"
                            @click="form.status = item.value"
                        >
                            {{ item.label }}
                        </view>
                    </view>
                </view>

                <view class="filter-section">
                    <view class="filter-section__title">订单信息</view>
                    <view class="form-row">
                        <text class="form-row__label">订单号</text>
                        <input v-model="form.order_no" class="form-row__input" placeholder="精确订单号" confirm-type="search" />
                    </view>
                    <view class="form-row">
                        <text class="form-row__label">快递单号</text>
                        <input v-model="form.express_no" class="form-row__input" placeholder="快递/物流单号" confirm-type="search" />
                    </view>
                    <view class="form-row">
                        <text class="form-row__label">配送方式</text>
                        <view class="segmented">
                            <view
                                class="segmented__item"
                                :class="{ 'segmented__item--active': form.delivery_type === '' }"
                                @click="form.delivery_type = ''"
                            >全部</view>
                            <view
                                class="segmented__item"
                                :class="{ 'segmented__item--active': form.delivery_type === '1' }"
                                @click="form.delivery_type = '1'"
                            >快递</view>
                            <view
                                class="segmented__item"
                                :class="{ 'segmented__item--active': form.delivery_type === '2' }"
                                @click="form.delivery_type = '2'"
                            >自送</view>
                        </view>
                    </view>
                </view>

                <view class="filter-section">
                    <view class="filter-section__title">用户与设备</view>
                    <view class="form-row">
                        <text class="form-row__label">手机号</text>
                        <input v-model="form.user_mobile" class="form-row__input" placeholder="用户手机号" type="number" confirm-type="search" />
                    </view>
                    <view class="form-row">
                        <text class="form-row__label">用户昵称</text>
                        <input v-model="form.user_nickname" class="form-row__input" placeholder="昵称/用户名" confirm-type="search" />
                    </view>
                    <view class="form-row">
                        <text class="form-row__label">IMEI</text>
                        <input v-model="form.device_imei" class="form-row__input" placeholder="设备 IMEI" confirm-type="search" />
                    </view>
                    <view class="form-row">
                        <text class="form-row__label">设备型号</text>
                        <input v-model="form.device_model" class="form-row__input" placeholder="设备型号" confirm-type="search" />
                    </view>
                </view>

                <view class="filter-section">
                    <view class="filter-section__title">提交时间</view>
                    <view class="form-row">
                        <text class="form-row__label">开始日期</text>
                        <picker mode="date" :value="form.create_time_start" @change="onDateChange('create_time_start', $event)">
                            <view class="form-row__picker">{{ form.create_time_start || '请选择' }}</view>
                        </picker>
                    </view>
                    <view class="form-row">
                        <text class="form-row__label">结束日期</text>
                        <picker mode="date" :value="form.create_time_end" @change="onDateChange('create_time_end', $event)">
                            <view class="form-row__picker">{{ form.create_time_end || '请选择' }}</view>
                        </picker>
                    </view>
                </view>
            </scroll-view>

            <view class="order-filter__footer">
                <view class="footer-btn footer-btn--ghost" @click="handleReset">重置</view>
                <view class="footer-btn footer-btn--primary" @click="handleConfirm">确定</view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'

type FilterForm = {
    status: string
    order_no: string
    express_no: string
    delivery_type: string
    user_mobile: string
    user_nickname: string
    device_imei: string
    device_model: string
    create_time_start: string
    create_time_end: string
}

const props = withDefaults(defineProps<{
    visible: boolean
    modelValue?: Record<string, any>
    statusOptions?: Array<{ label: string, value: string }>
}>(), {
    modelValue: () => ({}),
    statusOptions: () => []
})

const emit = defineEmits(['update:visible', 'update:modelValue', 'confirm', 'reset'])

const createDefaultForm = (): FilterForm => ({
    status: '',
    order_no: '',
    express_no: '',
    delivery_type: '',
    user_mobile: '',
    user_nickname: '',
    device_imei: '',
    device_model: '',
    create_time_start: '',
    create_time_end: ''
})

const form = reactive<FilterForm>(createDefaultForm())

watch(() => props.visible, (value) => {
    if (value) fillForm(props.modelValue || {})
})

watch(() => props.modelValue, (value) => {
    if (props.visible) fillForm(value || {})
}, { deep: true })

const fillForm = (value: Record<string, any>) => {
    Object.assign(form, createDefaultForm(), {
        status: stringifyValue(value.status),
        order_no: stringifyValue(value.order_no),
        express_no: stringifyValue(value.express_no),
        delivery_type: stringifyValue(value.delivery_type),
        user_mobile: stringifyValue(value.user_mobile),
        user_nickname: stringifyValue(value.user_nickname),
        device_imei: stringifyValue(value.device_imei || value.imei),
        device_model: stringifyValue(value.device_model),
        create_time_start: stringifyValue(value.create_time_start),
        create_time_end: stringifyValue(value.create_time_end)
    })
}

const stringifyValue = (value: any) => value === undefined || value === null ? '' : String(value)

const onDateChange = (field: 'create_time_start' | 'create_time_end', event: any) => {
    form[field] = event?.detail?.value || ''
}

const buildParams = () => {
    const params: Record<string, any> = {}
    Object.keys(form).forEach((key) => {
        const value = String((form as any)[key] || '').trim()
        if (value) params[key] = value
    })
    return params
}

const handleConfirm = () => {
    const params = buildParams()
    emit('update:modelValue', params)
    emit('confirm', params)
    emit('update:visible', false)
}

const handleReset = () => {
    Object.assign(form, createDefaultForm())
    emit('update:modelValue', {})
    emit('reset')
}

const handleClose = () => {
    emit('update:visible', false)
}
</script>

<style scoped lang="scss">
.order-filter {
    width: 640rpx;
    max-width: 86vw;
    height: 100vh;
    background: #f6f7fb;
    display: flex;
    flex-direction: column;
}

.order-filter__header {
    flex-shrink: 0;
    padding: 34rpx 28rpx 24rpx;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1rpx solid #eef2f7;
}

.order-filter__title {
    display: block;
    font-size: 32rpx;
    line-height: 40rpx;
    font-weight: 700;
    color: #111827;
}

.order-filter__desc {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.order-filter__close {
    width: 56rpx;
    height: 56rpx;
    border-radius: 28rpx;
    background: #f3f4f6;
    color: #64748b;
    font-size: 26rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.order-filter__body {
    flex: 1;
    min-height: 0;
    box-sizing: border-box;
    padding: 18rpx 22rpx 22rpx;
}

.filter-section {
    margin-bottom: 18rpx;
    padding: 22rpx;
    border-radius: 14rpx;
    background: #fff;
}

.filter-section__title {
    margin-bottom: 18rpx;
    font-size: 25rpx;
    line-height: 32rpx;
    font-weight: 700;
    color: #1f2937;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12rpx;
}

.status-item {
    height: 58rpx;
    border-radius: 10rpx;
    background: #f5f7fa;
    color: #475569;
    font-size: 23rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-item--active {
    background: #eff6ff;
    color: #2563eb;
    font-weight: 700;
}

.form-row {
    min-height: 72rpx;
    display: flex;
    align-items: center;
    border-bottom: 1rpx solid #f1f5f9;
}

.form-row:last-child {
    border-bottom: 0;
}

.form-row__label {
    width: 138rpx;
    flex-shrink: 0;
    font-size: 24rpx;
    color: #475569;
}

.form-row__input {
    flex: 1;
    min-width: 0;
    height: 72rpx;
    text-align: right;
    font-size: 24rpx;
    color: #111827;
}

.form-row__picker {
    min-width: 300rpx;
    height: 72rpx;
    line-height: 72rpx;
    text-align: right;
    font-size: 24rpx;
    color: #111827;
}

.segmented {
    flex: 1;
    min-width: 0;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8rpx;
    padding: 8rpx 0;
}

.segmented__item {
    height: 52rpx;
    border-radius: 10rpx;
    background: #f5f7fa;
    color: #64748b;
    font-size: 22rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.segmented__item--active {
    background: #eff6ff;
    color: #2563eb;
    font-weight: 700;
}

.order-filter__footer {
    flex-shrink: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16rpx;
    padding: 18rpx 24rpx calc(18rpx + constant(safe-area-inset-bottom));
    padding-bottom: calc(18rpx + env(safe-area-inset-bottom));
    background: #fff;
    border-top: 1rpx solid #eef2f7;
}

.footer-btn {
    height: 76rpx;
    border-radius: 12rpx;
    font-size: 26rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.footer-btn--ghost {
    background: #f6f7fb;
    color: #475569;
}

.footer-btn--primary {
    background: #2563eb;
    color: #fff;
    font-weight: 700;
}
</style>
