<template>
    <u-popup :show="show" mode="bottom" :round="18" @close="close">
        <view class="more-popup" :style="themeColor()">
            <view class="more-popup__head">
                <view>
                    <view class="more-popup__title">更多筛选</view>
                    <view class="more-popup__tip">价格、品牌、货源和库存状态</view>
                </view>
                <view class="more-popup__close" @click="close">
                    <u-icon name="close" size="20" color="#64748b" />
                </view>
            </view>
            <scroll-view scroll-y class="more-popup__body">
                <view class="more-section">
                    <view class="more-section__title">排序方式</view>
                    <view class="sort-grid">
                        <view
                            v-for="item in sortOptions"
                            :key="item.value"
                            class="sort-option"
                            :class="{ 'sort-option--active': draft.order === item.value }"
                            @click="selectSort(item.value)"
                        >
                            <view>
                                <view class="sort-option__name">{{ item.label }}</view>
                                <view class="sort-option__desc">{{ item.desc }}</view>
                            </view>
                            <view class="sort-option__radio" :class="{ 'sort-option__radio--active': draft.order === item.value }">
                                <view v-if="draft.order === item.value" class="sort-option__radio-dot"></view>
                            </view>
                        </view>
                    </view>
                    <view v-if="draft.order === 'price'" class="price-sort-switch">
                        <view :class="{ 'price-sort-switch__item--active': draft.sort === 'asc' }" class="price-sort-switch__item" @click="draft.sort = 'asc'">价格从低到高</view>
                        <view :class="{ 'price-sort-switch__item--active': draft.sort === 'desc' }" class="price-sort-switch__item" @click="draft.sort = 'desc'">价格从高到低</view>
                    </view>
                </view>
                <view class="more-section">
                    <view class="more-section__title">价格区间</view>
                    <view class="price-inputs">
                        <input v-model="draft.start_price" type="digit" placeholder="最低价" />
                        <view class="price-inputs__line"></view>
                        <input v-model="draft.end_price" type="digit" placeholder="最高价" />
                    </view>
                    <view class="chip-grid chip-grid--three">
                        <view
                            v-for="range in options.price_ranges || []"
                            :key="range.label"
                            class="filter-chip"
                            :class="{ 'filter-chip--active': isPriceRangeActive(range) }"
                            @click="selectPriceRange(range)"
                        >
                            {{ range.label }}
                        </view>
                    </view>
                </view>
                <view v-if="options.brands?.length" class="more-section">
                    <view class="more-section__title">品牌</view>
                    <view class="chip-grid chip-grid--three">
                        <view
                            v-for="brand in options.brands"
                            :key="brand.brand_id"
                            class="filter-chip"
                            :class="{ 'filter-chip--active': draft.brand_ids.includes(String(brand.brand_id)) }"
                            @click="toggleBrand(brand.brand_id)"
                        >
                            {{ brand.brand_name }}
                        </view>
                    </view>
                </view>
                <view v-if="options.warehouses?.show" class="more-section">
                    <view class="more-section__title">商品仓</view>
                    <view class="chip-grid">
                        <view class="filter-chip" :class="{ 'filter-chip--active': draft.warehouse === 'local' }" @click="draft.warehouse = draft.warehouse === 'local' ? '' : 'local'">
                            {{ options.warehouses.local_name }}
                        </view>
                        <view class="filter-chip" :class="{ 'filter-chip--active': draft.warehouse === 'agent' }" @click="draft.warehouse = draft.warehouse === 'agent' ? '' : 'agent'">
                            {{ options.warehouses.agent_name }}
                        </view>
                    </view>
                </view>
                <view class="more-section more-section--row">
                    <view>
                        <view class="more-section__title mb-0">仅看有货</view>
                        <view class="more-section__desc">隐藏暂时售罄的商品</view>
                    </view>
                    <u-switch v-model="draft.in_stock" active-color="var(--primary-color)" />
                </view>
                <view class="subscription-card" :class="{ 'subscription-card--active': subscribed }">
                    <view class="subscription-card__icon">
                        <u-icon :name="subscribed ? 'bell-fill' : 'bell'" :color="subscribed ? 'var(--primary-color)' : '#64748b'" size="22" />
                    </view>
                    <view class="subscription-card__content">
                        <view class="subscription-card__title">{{ subscribed ? '已订阅当前筛选' : '订阅当前筛选' }}</view>
                        <view class="subscription-card__desc">
                            {{ canSubscribe ? '有符合条件的新商品时通知我' : '请先选择分类、内存、成色等条件' }}
                        </view>
                    </view>
                    <view
                        class="subscription-card__action"
                        :class="{ 'subscription-card__action--active': subscribed, 'subscription-card__action--disabled': !canSubscribe || subscriptionLoading }"
                        @click="toggleSubscription"
                    >
                        {{ subscriptionLoading ? '处理中' : (subscribed ? '取消' : '订阅') }}
                    </view>
                </view>
            </scroll-view>
            <view class="more-popup__footer">
                <view class="more-button more-button--plain" @click="reset">重置</view>
                <view class="more-button more-button--primary" @click="confirm">确定</view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'

interface MoreFilterValue {
    start_price: string | number
    end_price: string | number
    brand_ids: string[]
    warehouse: string
    in_stock: boolean
    order: string
    sort: string
}

const props = defineProps<{
    show: boolean
    modelValue: MoreFilterValue
    options: Record<string, any>
    subscribed?: boolean
    canSubscribe?: boolean
    subscriptionLoading?: boolean
}>()
const emit = defineEmits(['update:show', 'confirm', 'subscribe', 'cancel-subscription'])

const emptyValue = (): MoreFilterValue => ({
    start_price: '',
    end_price: '',
    brand_ids: [],
    warehouse: '',
    in_stock: false,
    order: 'all',
    sort: ''
})
const draft = reactive<MoreFilterValue>(emptyValue())
const sortOptions = [
    { label: '综合', value: 'all', desc: '按商城默认规则' },
    { label: '最新', value: 'latest', desc: '新上架优先' },
    { label: '销量', value: 'sale_num', desc: '热销商品优先' },
    { label: '价格', value: 'price', desc: '可选升序或降序' }
]

watch(() => props.show, (value) => {
    if (!value) return
    Object.assign(draft, emptyValue(), JSON.parse(JSON.stringify(props.modelValue || {})))
}, { immediate: true })

const close = () => emit('update:show', false)
const reset = () => Object.assign(draft, emptyValue())

const selectSort = (value: string) => {
    draft.order = value
    draft.sort = value === 'price' ? (draft.sort || 'asc') : (value === 'sale_num' ? 'desc' : '')
}

const toggleBrand = (value: string | number) => {
    const normalized = String(value)
    draft.brand_ids = draft.brand_ids.includes(normalized)
        ? draft.brand_ids.filter(item => item !== normalized)
        : [...draft.brand_ids, normalized]
}

const selectPriceRange = (range: any) => {
    if (isPriceRangeActive(range)) {
        draft.start_price = ''
        draft.end_price = ''
    } else {
        draft.start_price = range.min
        draft.end_price = range.max
    }
}

const isPriceRangeActive = (range: any) => String(draft.start_price) === String(range.min) && String(draft.end_price) === String(range.max)

const confirm = () => {
    emit('confirm', JSON.parse(JSON.stringify(draft)))
    close()
}

const toggleSubscription = () => {
    if (!props.canSubscribe || props.subscriptionLoading) return
    emit(props.subscribed ? 'cancel-subscription' : 'subscribe')
}
</script>

<style lang="scss" scoped>
.more-popup {
    height: 78vh;
    max-height: 1080rpx;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.more-popup__head {
    height: 124rpx;
    padding: 0 30rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1rpx solid #eef2f7;
}

.more-popup__title {
    color: #0f172a;
    font-size: 32rpx;
    font-weight: 600;
}

.more-popup__tip {
    margin-top: 7rpx;
    color: #94a3b8;
    font-size: 22rpx;
}

.more-popup__close {
    width: 60rpx;
    height: 60rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f5f7fa;
}

.more-popup__body {
    flex: 1;
    min-height: 0;
    box-sizing: border-box;
    padding: 0 30rpx 30rpx;
}

.more-section {
    padding: 30rpx 0;
    border-bottom: 1rpx solid #eef2f7;
}

.more-section--row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.more-section__title {
    margin-bottom: 20rpx;
    color: #334155;
    font-size: 27rpx;
    font-weight: 600;
}

.more-section__title.mb-0 {
    margin-bottom: 0;
}

.more-section__desc {
    margin-top: 8rpx;
    color: #94a3b8;
    font-size: 22rpx;
}

.sort-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
}

.sort-option {
    min-height: 94rpx;
    padding: 16rpx 18rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-sizing: border-box;
    border: 2rpx solid transparent;
    border-radius: 14rpx;
    background: #f5f7fa;
}

.sort-option--active {
    border-color: var(--primary-color);
    background: var(--primary-color-light);
}

.sort-option__name {
    color: #334155;
    font-size: 25rpx;
    font-weight: 600;
}

.sort-option__desc {
    margin-top: 5rpx;
    color: #94a3b8;
    font-size: 20rpx;
}

.sort-option__radio {
    width: 34rpx;
    height: 34rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-sizing: border-box;
    border: 2rpx solid #cbd5e1;
    border-radius: 50%;
}

.sort-option__radio--active {
    border-color: var(--primary-color);
}

.sort-option__radio-dot {
    width: 18rpx;
    height: 18rpx;
    border-radius: 50%;
    background: var(--primary-color);
}

.price-sort-switch {
    margin-top: 16rpx;
    padding: 6rpx;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6rpx;
    border-radius: 14rpx;
    background: #f1f4f8;
}

.price-sort-switch__item {
    height: 60rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11rpx;
    color: #64748b;
    font-size: 23rpx;
}

.price-sort-switch__item--active {
    color: var(--primary-color);
    background: #fff;
    font-weight: 600;
    box-shadow: 0 3rpx 12rpx rgba(15, 23, 42, 0.07);
}

.price-inputs {
    margin-bottom: 20rpx;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 42rpx minmax(0, 1fr);
    align-items: center;
}

.price-inputs input {
    height: 72rpx;
    padding: 0 20rpx;
    box-sizing: border-box;
    border-radius: 14rpx;
    background: #f5f7fa;
    text-align: center;
    font-size: 25rpx;
}

.price-inputs__line {
    width: 18rpx;
    height: 2rpx;
    margin: 0 auto;
    background: #cbd5e1;
}

.chip-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
}

.chip-grid--three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.filter-chip {
    height: 68rpx;
    padding: 0 12rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-sizing: border-box;
    border: 2rpx solid transparent;
    border-radius: 13rpx;
    background: #f5f7fa;
    color: #475569;
    font-size: 23rpx;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.filter-chip--active {
    color: var(--primary-color);
    border-color: var(--primary-color);
    background: var(--primary-color-light);
}

.more-popup__footer {
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    display: grid;
    grid-template-columns: 220rpx minmax(0, 1fr);
    gap: 20rpx;
    border-top: 1rpx solid #eef2f7;
}

.subscription-card {
    margin: 26rpx 0 8rpx;
    padding: 22rpx;
    display: flex;
    align-items: center;
    border: 2rpx solid #e8edf3;
    border-radius: 18rpx;
    background: #f8fafc;
}

.subscription-card--active {
    border-color: var(--primary-color);
    background: var(--primary-color-light);
}

.subscription-card__icon {
    width: 66rpx;
    height: 66rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 18rpx;
    background: #fff;
}

.subscription-card__content {
    min-width: 0;
    margin-left: 18rpx;
    flex: 1;
}

.subscription-card__title {
    color: #1e293b;
    font-size: 26rpx;
    font-weight: 600;
}

.subscription-card__desc {
    margin-top: 7rpx;
    color: #94a3b8;
    font-size: 21rpx;
}

.subscription-card__action {
    min-width: 108rpx;
    height: 58rpx;
    margin-left: 16rpx;
    padding: 0 20rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border-radius: 30rpx;
    color: #fff;
    background: var(--primary-color);
    font-size: 23rpx;
    font-weight: 600;
}

.subscription-card__action--active {
    color: #64748b;
    background: #fff;
}

.subscription-card__action--disabled {
    opacity: .45;
}

.more-button {
    height: 82rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 42rpx;
    font-size: 28rpx;
    font-weight: 600;
}

.more-button--plain {
    border: 1rpx solid #dbe1ea;
    color: #475569;
}

.more-button--primary {
    color: #fff;
    background: var(--primary-color);
}
</style>
