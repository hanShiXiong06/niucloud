<template>
<CoopTaskSheet :show="show" title="交付详情" :subtitle="lead.status_label || coopStatusLabels[lead.status]" :busy="busy" full @close="$emit('close')">
<CoopState v-if="loading || error" :loading="loading" :error="error" @retry="$emit('reload')" />
<template v-else-if="lead.id">
<view v-if="lead.attention_reasons?.length" class="coop-task-rule">
<view v-for="reason in lead.attention_reasons" :key="reason">{{ reason }}</view>
</view>
<view class="coop-state-heading">
<view>{{ lead.can_accept ? '等待你接单' : lead.can_report ? '联系客户，反馈结果' : lead.can_pay ? '已成交，等待付佣' : lead.status_label || coopStatusLabels[lead.status] }}</view>
<text>{{ lead.can_accept ? '接单后开放必要联系方式与附件' : '客户货款由客户与门店直接结算' }}</text>
</view>
<view class="coop-form-section">
<view class="coop-section-title">客户需求<text>{{ businessLabels[lead.business_type] || '购买' }}</text>
</view>
<view class="coop-customer-contact">
<view>
<view class="coop-customer-name">{{ lead.customer_name || '客户' }}</view>
<view class="coop-contact-number">{{ lead.mobile || '接单后查看联系电话' }}</view>
</view>
<view v-if="canCall" class="coop-call" @tap="call">
<u-icon name="phone" size="25" color="var(--primary-color, #d93833)" />
<text>联系客户</text>
</view>
</view>
<view class="coop-detail-region">{{ lead.region_text }}</view>
<CoopModels :items="lead.models || []" />
<view v-if="lead.remark" class="coop-detail-remark">{{ lead.remark }}</view>
<view v-if="lead.attachments?.length" class="coop-detail-images">
<image v-for="url in lead.attachments" :key="url" :src="img(url)" mode="aspectFill" @tap="preview(url)" />
</view>
</view>
<view class="coop-form-section">
<view class="coop-section-title">交付门店</view>
<view class="coop-detail-shop coop-detail-party">
<text>发布方</text>
<view v-if="lead.publisher?.name" class="coop-detail-merchant">
<CoopMerchantAvatar :image="lead.publisher.storefront_image" :name="lead.publisher.name" :reliable="lead.publisher.is_reliable" size="small" />
<text>{{ lead.publisher.name }}</text>
</view>
<text v-else>—</text>
</view>
<view class="coop-detail-shop coop-detail-party">
<text>承接方</text>
<view v-if="lead.receiver?.name" class="coop-detail-merchant">
<CoopMerchantAvatar :image="lead.receiver.storefront_image" :name="lead.receiver.name" :reliable="lead.receiver.is_reliable" size="small" />
<text>{{ lead.receiver.name }}</text>
</view>
<text v-else>尚未选定</text>
</view>
</view>
<view v-if="lead.status === 'unpaid' || lead.status === 'paid'" class="coop-form-section">
<view class="coop-section-title">协作佣金</view>
<view class="coop-detail-shop">
<text>{{ lead.status === 'paid' ? '已付佣金' : '待付佣金' }}</text>
<text>{{ coopMoney(lead.commission_amount) }}</text>
</view>
<view v-if="lead.status === 'paid'" class="coop-detail-shop">
<text>发布方应得净收益</text>
<text>{{ coopMoney(lead.net_amount) }}</text>
</view>
</view>
<view class="coop-task-rule">成交后支付协作佣金，最低 {{ coopMoney(config.commission_minimum || 50) }}；未成交不收费。认证与库存匹配不代表平台担保或锁货。</view>
<view class="coop-form-section">
<view class="coop-fold-title" @tap="history = !history">编号与处理记录<u-icon :name="history ? 'arrow-up' : 'arrow-down'" size="12" color="#89939f" />
</view>
<template v-if="history">
<view class="coop-muted coop-detail-remark">{{ lead.lead_no }} · {{ coopDate(lead.create_time) }}</view>
<view v-for="item in lead.history || []" :key="item.id || item.create_time" class="coop-history-item">
<text>{{ item.content || item.remark || item.action_label || item.action }}</text>
<text>{{ coopDate(item.create_time) }}</text>
</view>
<view v-if="!lead.history?.length" class="coop-muted">暂无处理记录</view>
</template>
</view>
</template>
<template #footer>
<view v-if="lead.id && !loading && !error" class="coop-footer-actions">
<template v-if="lead.can_accept">
<view class="coop-secondary">
<u-button shape="circle" text="暂不承接" :disabled="busy" @click="$emit('action', 'reject')" />
</view>
<view class="coop-primary">
<u-button type="primary" shape="circle" text="接受交付" :disabled="busy" :loading="busy" @click="$emit('action', 'accept')" />
</view>
</template>
<template v-else-if="lead.can_report">
<view class="coop-secondary">
<u-button shape="circle" text="未成交" :disabled="busy" @click="$emit('action', 'no_deal')" />
</view>
<view class="coop-primary">
<u-button type="primary" shape="circle" text="已成交，去付佣" :disabled="busy" :loading="busy" @click="$emit('action', 'deal')" />
</view>
</template>
<view v-else-if="lead.can_pay" class="coop-primary">
<u-button type="primary" shape="circle" text="支付协作佣金" :disabled="busy" @click="$emit('pay')" />
</view>
<template v-else-if="lead.can_assign">
<view v-if="lead.can_cancel" class="coop-secondary">
<u-button shape="circle" text="取消线索" :disabled="busy" @click="$emit('action', 'cancel')" />
</view>
<view class="coop-primary">
<u-button type="primary" shape="circle" text="选择交付门店" :disabled="busy" @click="$emit('select')" />
</view>
</template>
<template v-else-if="lead.can_withdraw">
<view class="coop-secondary">
<u-button shape="circle" text="撤回交付" :disabled="busy" @click="$emit('action', 'withdraw')" />
</view>
<view class="coop-primary">
<u-button type="primary" shape="circle" text="返回列表" @click="$emit('close')" />
</view>
</template>
<view v-else class="coop-primary">
<u-button shape="circle" text="返回列表" @click="$emit('close')" />
</view>
</view>
</template>
</CoopTaskSheet>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { businessLabels, coopDate, coopMoney, coopStatusLabels, type CoopRow, type LeadAction } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
import CoopModels from './CoopModels.vue'
import CoopMerchantAvatar from './CoopMerchantAvatar.vue'
const props = withDefaults(defineProps<{ show: boolean; lead: CoopRow; config: CoopRow; loading?: boolean; busy?: boolean; error?: string }>(), { loading: false, busy: false, error: '' })
defineEmits<{ (event: 'close' | 'reload' | 'pay' | 'select'): void; (event: 'action', value: LeadAction): void }>()
const history = ref(false), canCall = computed(() => /^1\d{10}$/.test(String(props.lead.mobile || '')))
function call() { if (canCall.value) uni.makePhoneCall({ phoneNumber: String(props.lead.mobile), fail: () => uni.showToast({ title: '未能拨打，请手动联系', icon: 'none' }) }) }
function preview(url: string) { uni.previewImage({ urls: (props.lead.attachments || []).map(img), current: img(url) }) }
watch(() => props.show, () => { history.value = false })
</script>

<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-task-rule;
@include hsx-coop-fold-title;
@include hsx-coop-detail-shop;
.coop-footer-actions { display: flex; align-items: center; gap: 20rpx; }
.coop-secondary { flex: 0 0 31%; min-width: 0; }
.coop-primary { flex: 1; min-width: 0; }
.coop-state-heading { padding: 36rpx 30rpx; background: #fff; margin-bottom: 18rpx; }
.coop-state-heading>view { font-size: 39rpx; font-weight: 650; line-height: 1.6; }
.coop-state-heading>text { font-size: 23rpx; color: #8d97a3; display: block; margin-top: 10rpx; line-height: 1.7; }
.coop-customer-contact { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; margin-bottom: 17rpx; }
.coop-customer-name { font-size: 32rpx; font-weight: 600; }
.coop-contact-number { font-size: 28rpx; color: #616e7d; margin-top: 12rpx; }
.coop-call { width: 112rpx; flex: none; display: flex; flex-direction: column; align-items: center; gap: 7rpx; font-size: 21rpx; color: var(--primary-color, #d93833); border-left: 1rpx solid #edf0f3; padding-left: 20rpx; }
.coop-detail-region { color: #929aa5; font-size: 23rpx; line-height: 1.7; margin-bottom: 23rpx; }
.coop-detail-remark { font-size: 25rpx; color: #758190; line-height: 1.8; margin-top: 20rpx; word-break: break-word; white-space: pre-wrap; }
.coop-detail-images { display: flex; flex-wrap: wrap; gap: 14rpx; margin-top: 22rpx; }
.coop-detail-images>image { width: 128rpx; height: 148rpx; border: 1rpx solid #edf0f3; border-radius: 7rpx; }
.coop-history-item { display: flex; flex-direction: column; gap: 8rpx; padding: 20rpx 0; border-top: 1rpx solid #edf0f3; font-size: 24rpx; color: #758292; line-height: 1.8; }
.coop-history-item>text:last-child { font-size: 21rpx; color: #9ba3ad; }
.coop-detail-party { align-items: center; min-height: 100rpx; }.coop-detail-merchant { display: flex; align-items: center; justify-content: flex-end; gap: 18rpx; max-width: 76%; min-width: 0; }.coop-detail-merchant>text { min-width: 0; word-break: break-word; text-align: right; line-height: 1.6; }
</style>
