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
                    <view class="member-search">
                        <view class="member-search__bar">
                            <input
                                v-model="memberKeyword"
                                class="member-search__input"
                                placeholder="手机号/昵称/会员编号"
                                confirm-type="search"
                                @confirm="handleMemberSearch"
                            />
                            <view v-if="memberKeyword" class="member-search__clear" @click="clearMemberKeyword">
                                <text class="nc-iconfont nc-icon-cuohaoV6xx1"></text>
                            </view>
                            <view class="member-search__btn" :class="{ 'member-search__btn--loading': memberLoading }" @click="handleMemberSearch">
                                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1"></text>
                            </view>
                        </view>
                        <view v-if="selectedMember" class="selected-member">
                            <u-avatar :src="selectedMember.headimg || selectedMember.head_img || ''" :size="'58rpx'" />
                            <view class="selected-member__info">
                                <view class="selected-member__name">{{ getMemberName(selectedMember) }}</view>
                                <view class="selected-member__meta">{{ getMemberMobile(selectedMember) }}</view>
                            </view>
                            <view class="selected-member__remove" @click="clearSelectedMember">清除</view>
                        </view>
                        <view v-if="memberOptions.length" class="member-result">
                            <view
                                v-for="item in memberOptions"
                                :key="item.member_id"
                                class="member-result__item"
                                :class="{ 'member-result__item--active': String(form.member_id) === String(item.member_id) }"
                                @click="selectMember(item)"
                            >
                                <u-avatar :src="item.headimg || item.head_img || ''" :size="'52rpx'" />
                                <view class="member-result__info">
                                    <view class="member-result__name">{{ getMemberName(item) }}</view>
                                    <view class="member-result__meta">
                                        {{ getMemberMobile(item) }}
                                        <text v-if="item.member_no"> / {{ item.member_no }}</text>
                                    </view>
                                </view>
                            </view>
                        </view>
                        <view v-else-if="memberSearched && !memberLoading" class="member-empty">未找到匹配会员</view>
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
import { reactive, ref, watch } from 'vue'
import { searchMemberList } from '@/addon/hsx_recycle/api/order'

type FilterForm = {
    status: string
    order_no: string
    express_no: string
    delivery_type: string
    member_id: string
    device_imei: string
    device_model: string
    create_time_start: string
    create_time_end: string
}

type MemberOption = {
    member_id: number | string
    member_no?: string
    nickname?: string
    username?: string
    mobile?: string
    headimg?: string
    head_img?: string
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
    member_id: '',
    device_imei: '',
    device_model: '',
    create_time_start: '',
    create_time_end: ''
})

const form = reactive<FilterForm>(createDefaultForm())
const memberKeyword = ref('')
const memberOptions = ref<MemberOption[]>([])
const memberLoading = ref(false)
const memberSearched = ref(false)
const selectedMember = ref<MemberOption | null>(null)

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
        member_id: stringifyValue(value.member_id),
        device_imei: stringifyValue(value.device_imei || value.imei),
        device_model: stringifyValue(value.device_model),
        create_time_start: stringifyValue(value.create_time_start),
        create_time_end: stringifyValue(value.create_time_end)
    })
    if (!form.member_id || String(selectedMember.value?.member_id || '') !== form.member_id) {
        selectedMember.value = null
    }
}

const stringifyValue = (value: any) => value === undefined || value === null ? '' : String(value)

const onDateChange = (field: 'create_time_start' | 'create_time_end', event: any) => {
    form[field] = event?.detail?.value || ''
}

const normalizeMemberList = (res: any): MemberOption[] => {
    const data = res?.data?.data || res?.data || []
    return Array.isArray(data) ? data : []
}

const getMemberName = (item: MemberOption | null) => {
    return item?.nickname || item?.username || item?.member_no || `会员${item?.member_id || ''}`
}

const getMemberMobile = (item: MemberOption | null) => {
    return item?.mobile || item?.username || '未绑定手机号'
}

const handleMemberSearch = () => {
    const keyword = memberKeyword.value.trim()
    if (!keyword || memberLoading.value) return
    memberLoading.value = true
    memberSearched.value = true
    searchMemberList({ page: 1, limit: 8, keyword })
        .then((res: any) => {
            memberOptions.value = normalizeMemberList(res)
        })
        .catch(() => {
            memberOptions.value = []
        })
        .finally(() => {
            memberLoading.value = false
        })
}

const selectMember = (item: MemberOption) => {
    selectedMember.value = item
    form.member_id = stringifyValue(item.member_id)
    memberKeyword.value = `${getMemberName(item)} ${getMemberMobile(item)}`.trim()
    memberOptions.value = []
    memberSearched.value = false
}

const clearSelectedMember = () => {
    selectedMember.value = null
    form.member_id = ''
}

const clearMemberKeyword = () => {
    memberKeyword.value = ''
    memberOptions.value = []
    memberSearched.value = false
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
    if (memberKeyword.value.trim() && !form.member_id) {
        uni.showToast({ title: '请先选择会员', icon: 'none' })
        return
    }
    const params = buildParams()
    emit('update:modelValue', params)
    emit('confirm', params)
    emit('update:visible', false)
}

const handleReset = () => {
    Object.assign(form, createDefaultForm())
    selectedMember.value = null
    memberKeyword.value = ''
    memberOptions.value = []
    memberSearched.value = false
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

.member-search {
    padding-bottom: 10rpx;
}

.member-search__bar {
    height: 68rpx;
    padding-left: 20rpx;
    border-radius: 12rpx;
    background: #f6f8fb;
    display: flex;
    align-items: center;
}

.member-search__input {
    flex: 1;
    min-width: 0;
    height: 68rpx;
    font-size: 24rpx;
    color: #111827;
}

.member-search__clear,
.member-search__btn {
    width: 64rpx;
    height: 68rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 24rpx;
}

.member-search__btn {
    color: #2563eb;
}

.member-search__btn--loading {
    opacity: .45;
}

.selected-member {
    margin-top: 14rpx;
    padding: 14rpx;
    border-radius: 12rpx;
    background: #eff6ff;
    display: flex;
    align-items: center;
}

.selected-member__info {
    flex: 1;
    min-width: 0;
    margin-left: 14rpx;
}

.selected-member__name,
.member-result__name {
    font-size: 24rpx;
    line-height: 32rpx;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-member__meta,
.member-result__meta {
    margin-top: 2rpx;
    font-size: 21rpx;
    line-height: 28rpx;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-member__remove {
    flex-shrink: 0;
    margin-left: 12rpx;
    font-size: 22rpx;
    color: #2563eb;
}

.member-result {
    margin-top: 10rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    overflow: hidden;
}

.member-result__item {
    padding: 12rpx 14rpx;
    display: flex;
    align-items: center;
    border-bottom: 1rpx solid #eef2f7;
}

.member-result__item:last-child {
    border-bottom: 0;
}

.member-result__item--active {
    background: #eff6ff;
}

.member-result__info {
    flex: 1;
    min-width: 0;
    margin-left: 14rpx;
}

.member-empty {
    margin-top: 10rpx;
    height: 56rpx;
    line-height: 56rpx;
    border-radius: 10rpx;
    background: #f8fafc;
    text-align: center;
    font-size: 22rpx;
    color: #94a3b8;
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
