<template>
    <CoopTaskSheet :show="show" :title="detail.can_request === true ? '申请退款' : '退款进度'" :busy="busy" @close="emit('close')">
        <view class="refund-content">
            <view class="refund-order"><text>{{ order.title || order.level_name || (order.purpose === 'membership' ? '会员开通 / 续费' : '成交协作付佣') }}</text><text>{{ order.order_no }}</text></view>
            <view v-if="loading" class="refund-loading">正在核对原付款与退款资料…</view>
            <template v-else-if="detail.order_id">
                <view class="refund-amount"><text>{{ order.purpose === 'commission' ? '成交协作费退款' : detail.can_request === true ? '本次申请金额' : '申请退款金额' }}</text><text>{{ coopMoney(detail.amount) }}</text></view>
                <view class="refund-channel">原路退回 · {{ order.payment_display_name || order.payment_type_name || '以原支付记录为准' }}</view>
                <view v-if="detail.components && order.purpose === 'membership'" class="refund-components"><text>服务费 {{ coopMoney(detail.components.service) }}</text><text>押金 {{ coopMoney(detail.components.deposit) }}</text><text>经验费 {{ coopMoney(detail.components.experience) }}</text></view>
                <view v-if="detail.status !== 'none'" class="refund-state" :class="'is-' + detail.status"><text>{{ detail.status_name || '退款待核对' }}</text><text v-if="detail.message">{{ detail.message }}</text></view>
                <template v-if="detail.can_request === true && !readRequired">
                    <u-form ref="formRef" :model="form" :rules="rules" label-position="top" error-type="message">
                        <u-form-item label="退款原因" prop="reason" required :border-bottom="false">
                            <u-textarea v-model="form.reason" :disabled="busy" :maxlength="500" count height="100" placeholder="请说明退款原因，至少4个字" />
                        </u-form-item>
                    </u-form>
                    <view class="refund-note">提交后由平台审核；退款完成前请保留原付款单。会员退款可能影响对应权益，已结算收益需先核对。</view>
                </template>
                <template v-else>
                    <view v-if="detail.reason" class="refund-info"><text>申请原因</text><text>{{ detail.reason }}</text></view>
                    <view v-if="detail.review_reason" class="refund-info"><text>平台回复</text><text>{{ detail.review_reason }}</text></view>
                    <view v-if="detail.requested_at" class="refund-info"><text>申请时间</text><text>{{ coopDate(detail.requested_at) }}</text></view>
                    <view v-if="detail.refund_no" class="refund-info"><text>退款编号</text><text>{{ detail.refund_no }}</text></view>
                    <view v-if="detail.block_reason" class="refund-note">{{ detail.block_reason }}</view>
                    <view v-if="detail.logs?.length" class="refund-logs"><text class="refund-logs__title">处理记录</text><view v-for="(item, index) in detail.logs" :key="item.id || index" class="refund-log"><text>{{ item.action_name || item.action_label || item.message || item.action }}</text><text v-if="item.create_time || item.at">{{ item.operator_name ? item.operator_name + ' · ' : '' }}{{ coopDate(item.create_time || item.at) }}</text><text v-if="item.reason || item.detail?.reason || item.detail?.message">{{ item.reason || item.detail?.reason || item.detail?.message }}</text></view></view>
                </template>
            </template>
            <view v-if="error" class="refund-error" role="alert">{{ error }}</view>
        </view>
        <template #footer>
            <view class="refund-actions">
                <u-button v-if="readRequired || !detail.order_id" :custom-style="cooperationButtonStyle" :loading="loading" :disabled="busy" shape="circle" type="primary" text="重新读取原申请" @click="emit('reload')" />
                <u-button v-else-if="detail.can_request === true" :custom-style="cooperationButtonStyle" :loading="busy" :disabled="loading" shape="circle" type="primary" text="提交退款申请" @click="submit" />
                <template v-else><button v-if="detail.can_cancel === true" :disabled="busy || loading" @tap="emit('cancel')">撤回申请</button><u-button v-if="detail.can_refresh === true" :custom-style="cooperationButtonStyle" :loading="busy" :disabled="loading" shape="circle" type="primary" text="查询退款进度" @click="emit('refresh')" /><button v-else :disabled="busy || loading" @tap="emit('close')">返回付款单</button></template>
            </view>
        </template>
    </CoopTaskSheet>
</template>
<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import CoopTaskSheet from '../../../components/cooperation/CoopTaskSheet.vue'
import { coopDate, coopMoney, type CoopRow } from '../../../api/cooperation'
import { cooperationButtonStyle } from '../../../utils/mobile-ui'
const props = withDefaults(defineProps<{ show: boolean; order: CoopRow; detail: CoopRow; loading?: boolean; busy?: boolean; error?: string; readRequired?: boolean }>(), { loading: false, busy: false, error: '', readRequired: false })
const emit = defineEmits<{ (event: 'close' | 'reload' | 'refresh' | 'cancel'): void; (event: 'submit', reason: string): void }>()
const formRef = ref<any>(null), form = reactive({ reason: '' })
const rules = { reason: [{ validator: (_rule: unknown, value: unknown) => typeof value === 'string' && value.trim().length >= 4 && value.trim().length <= 500, message: '请填写4至500个字的退款原因', trigger: ['blur', 'change'] }] }
watch(() => [props.show, props.order.id], () => { if (props.show) { form.reason = ''; formRef.value?.clearValidate?.() } })
async function submit() {
    if (props.busy || props.loading || props.readRequired || props.detail.can_request !== true) return
    if (!formRef.value?.validate) { uni.showToast({ title: '表单尚未就绪，请稍后重试', icon: 'none' }); return }
    try { await formRef.value.validate() } catch { return }
    if (!props.show || props.busy || props.loading || props.readRequired || props.detail.can_request !== true) return
    emit('submit', form.reason.trim())
}
</script>
<style scoped lang="scss">
.refund-content { padding:20px 16px;box-sizing:border-box; }.refund-order { display:flex;flex-direction:column;gap:6px;color:#243247;font-size:16px;line-height:1.6;overflow-wrap:break-word; }.refund-order>text+text { font-size:11px;color:#8b95a3;word-break:break-all; }.refund-loading { padding:24px 0;color:#7b8796;font-size:13px; }
.refund-amount { display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:24px;font-size:13px;color:#758296; }.refund-amount>text:last-child { color:#20252d;font-size:26px;font-weight:650; }.refund-channel { text-align:right;color:#8591a2;font-size:11px;margin-top:6px; }.refund-components { display:flex;flex-wrap:wrap;gap:6px 12px;margin:14px 0 8px;font-size:11px;color:#8591a2; }
.refund-state { display:flex;flex-direction:column;gap:6px;margin:24px 0 18px;padding:14px 0;border-top:1px solid #e7ebf1;border-bottom:1px solid #e7ebf1;color:#536f97;font-size:14px;font-weight:600; }.refund-state>text+text { font-size:12px;line-height:1.7;font-weight:400; }.refund-state.is-refunded { color:#427b67; }.refund-state.is-attention,.refund-state.is-failed { color:#9a6750; }
.refund-info { display:flex;gap:16px;margin-top:14px;color:#465469;font-size:13px;line-height:1.7; }.refund-info>text:first-child { width:66px;flex:none;color:#8c96a3; }.refund-info>text+text { flex:1;min-width:0;word-break:break-word; }.refund-note { color:#8791a0;font-size:12px;line-height:1.8;margin-top:14px; }.refund-error { color:#a1604b;background:#fff7f2;border-radius:6px;padding:12px;margin-top:16px;font-size:12px;line-height:1.8; }
.refund-actions { display:flex;align-items:center;gap:12px; }.refund-actions>button { margin:0;flex:none;min-height:44px;line-height:44px;padding:0 18px;background:#fff;color:#66778c;border:1px solid #dce2eb;border-radius:24px;font-size:13px; }.refund-actions>button::after { border:0; }.refund-actions :deep(.u-button) { flex:1; }.refund-logs { margin-top:24px; }.refund-logs__title { color:#465469;font-size:13px;font-weight:600; }.refund-log { display:flex;flex-direction:column;gap:3px;border-left:2px solid #e3e9f3;margin-top:14px;padding-left:12px;font-size:12px;line-height:1.6;color:#65758a; }.refund-log>text+text { font-size:11px;color:#929baa; }
</style>
