<template>
    <view class="mc-page product-edit-page">
        <view class="edit-summary mc-surface">
            <view class="edit-summary__icon"><u-icon name="order" color="#2563eb" size="24" /></view>
            <view><strong>{{ id ? '编辑服务卡' : '新增服务卡' }}</strong><text>设置售价、权益次数和有效期</text></view>
        </view>

        <view class="form-card mc-surface">
            <view class="mc-form-title">基础信息</view>
            <view class="form-row"><text class="required">卡种名称</text><u-input v-model="form.product_name" border="none" inputAlign="right" placeholder="例如：9.9 元贴膜 10 次卡" /></view>
            <view class="form-row"><text class="required">销售价</text><u-input v-model="form.sale_price" type="digit" border="none" inputAlign="right" placeholder="0.00"><template #suffix><text class="suffix">元</text></template></u-input></view>
            <view class="form-row"><text>划线价</text><u-input v-model="form.market_price" type="digit" border="none" inputAlign="right" placeholder="选填"><template #suffix><text class="suffix">元</text></template></u-input></view>
        </view>

        <view class="form-card mc-surface">
            <view class="mc-form-title">服务权益</view>
            <view class="form-row"><text class="required">权益名称</text><u-input v-model="form.item.item_name" border="none" inputAlign="right" placeholder="例如：贴膜服务" /></view>
            <view class="choice-block">
                <text class="choice-label">次数模式</text>
                <view class="choice-list"><view v-for="option in usageOptions" :key="option.value" class="choice-chip" :class="{ active: form.item.usage_mode === option.value }" @click="form.item.usage_mode = option.value">{{ option.label }}</view></view>
            </view>
            <view v-if="form.item.usage_mode === 'limited'" class="form-row"><text class="required">可用次数</text><u-number-box v-model="form.item.total_times" :min="1" :max="9999" /></view>
            <view class="form-row"><text>每日上限</text><view class="number-with-tip"><u-number-box v-model="form.item.daily_limit" :min="0" :max="99" /><small>0 表示不限</small></view></view>
        </view>

        <view class="form-card mc-surface">
            <view class="mc-form-title">生效与有效期</view>
            <view class="choice-block">
                <text class="choice-label">生效方式</text>
                <view class="choice-list"><view v-for="option in effectiveOptions" :key="option.value" class="choice-chip" :class="{ active: form.effective_mode === option.value }" @click="form.effective_mode = option.value">{{ option.label }}</view></view>
            </view>
            <view class="choice-block choice-block--border">
                <text class="choice-label">有效期</text>
                <view class="choice-list"><view v-for="option in validityOptions" :key="option.value" class="choice-chip" :class="{ active: form.validity_mode === option.value }" @click="form.validity_mode = option.value">{{ option.label }}</view></view>
            </view>
            <view v-if="form.validity_mode === 'duration'" class="form-row">
                <text class="required">固定时长</text>
                <view class="duration-field"><u-number-box v-model="form.duration_value" :min="1" :max="3650" /><view class="unit-toggle" @click="form.duration_unit = form.duration_unit === 'day' ? 'month' : 'day'">{{ form.duration_unit === 'day' ? '天' : '个月' }}</view></view>
            </view>
        </view>

        <view class="form-card mc-surface">
            <view class="mc-form-title">使用说明</view>
            <u-textarea v-model="form.usage_notice" height="130rpx" maxlength="1000" placeholder="填写适用范围、注意事项等（选填）" />
        </view>

        <view class="mc-bottom-action"><MemberCardButton type="primary" icon="checkmark" :text="id ? '保存修改' : '保存并启用'" :loading="submitting" @click="submit" /></view>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { enableCardProduct, getCardProduct, saveCardProduct } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const id = ref(0)
const submitting = ref(false)
const usageOptions = [{ label: '有限次数', value: 'limited' }, { label: '不限次数', value: 'unlimited' }]
const effectiveOptions = [{ label: '开卡即生效', value: 'immediate' }, { label: '首次核销生效', value: 'first_use' }]
const validityOptions = [{ label: '永久有效', value: 'permanent' }, { label: '固定时长', value: 'duration' }]
const form = reactive<any>({
    product_name: '', sale_price: '', market_price: '', effective_mode: 'immediate', validity_mode: 'permanent',
    duration_value: 30, duration_unit: 'day', usage_notice: '', sort: 0,
    item: { item_code: 'film_service', item_name: '贴膜服务', usage_mode: 'limited', total_times: 10, daily_limit: 0, recognition_mode: 'average', recognition_amount: 0 },
})
const submit = async () => {
    if (!form.product_name.trim()) return uni.showToast({ title: '请填写卡种名称', icon: 'none' })
    if (form.sale_price === '' || Number(form.sale_price) < 0) return uni.showToast({ title: '请填写正确销售价', icon: 'none' })
    if (!form.item.item_name.trim()) return uni.showToast({ title: '请填写权益名称', icon: 'none' })
    if (form.item.usage_mode === 'limited' && Number(form.item.total_times) <= 0) return uni.showToast({ title: '可用次数必须大于 0', icon: 'none' })
    submitting.value = true
    try {
        const result: any = await saveCardProduct(form, id.value)
        const savedId = Number(result?.data?.id || id.value)
        if (!id.value && savedId) await enableCardProduct(savedId)
        uni.showToast({ title: id.value ? '保存成功' : '卡种已创建并启用', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 650)
    } finally { submitting.value = false }
}
onLoad(async (options: any) => {
    id.value = Number(options?.id || 0)
    if (!id.value) return
    const result: any = await getCardProduct(id.value)
    const data = result?.data || {}
    Object.assign(form, data, { item: { ...form.item, ...(data.item || {}) } })
})
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';
.product-edit-page { padding-bottom: 150rpx; }
.edit-summary { display: flex; margin-bottom: 18rpx; padding: 24rpx; align-items: center; gap: 16rpx; }
.edit-summary__icon { display: flex; width: 64rpx; height: 64rpx; align-items: center; justify-content: center; border-radius: 16rpx; background: #eff6ff; }
.edit-summary > view:last-child { display: flex; flex-direction: column; gap: 5rpx; }.edit-summary strong { font-size: 31rpx; }.edit-summary text { color: #64748b; font-size: 21rpx; }
.form-card { margin-bottom: 18rpx; padding: 0 24rpx; overflow: hidden; }
.mc-form-title { padding: 22rpx 0 12rpx; color: #0f172a; font-size: 25rpx; font-weight: 700; }
.form-row { display: flex; min-height: 94rpx; align-items: center; justify-content: space-between; gap: 24rpx; border-top: 1rpx solid #edf1f6; }
.form-row > text { flex: 0 0 auto; font-size: 25rpx; }.form-row :deep(.u-input) { min-width: 0; flex: 1; }
.required::after { margin-left: 5rpx; color: #dc2626; content: '*'; }.suffix { color: #475569; font-size: 24rpx; }
.choice-block { padding: 20rpx 0; border-top: 1rpx solid #edf1f6; }.choice-block--border { border-top: 1rpx solid #edf1f6; }
.choice-label { display: block; margin-bottom: 15rpx; font-size: 25rpx; }
.choice-list { display: flex; flex-wrap: wrap; gap: 12rpx; }.choice-chip { padding: 15rpx 22rpx; border: 1rpx solid #e2e8f0; border-radius: 12rpx; background: #f8fafc; color: #475569; font-size: 23rpx; }.choice-chip.active { border-color: #93c5fd; background: #eff6ff; color: #2563eb; font-weight: 650; }
.number-with-tip { display: flex; align-items: center; gap: 12rpx; }.number-with-tip small { color: #94a3b8; font-size: 19rpx; }
.duration-field { display: flex; align-items: center; gap: 12rpx; }.unit-toggle { min-width: 78rpx; padding: 12rpx 15rpx; border-radius: 10rpx; background: #eff6ff; color: #2563eb; text-align: center; font-size: 22rpx; }
.form-card :deep(.u-textarea) { margin: 8rpx 0 24rpx; background: #f8fafc; }
</style>
