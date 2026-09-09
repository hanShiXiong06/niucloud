<template>
    <view class="mc-page">
        <view class="mc-content">
            <view class="mc-card mc-query-card">
                <view class="mc-query-title">手机号核销</view>
                <view class="mc-sub" style="margin-bottom: 18px">查询 → 核对客户 → 完成服务</view>
                <view class="mc-query-input">
                    <u-input
                        v-model="mobile"
                        type="number"
                        maxlength="11"
                        placeholder="完整手机号 / 后四位"
                        confirm-type="search"
                        border="none"
                        fontSize="18"
                        clearable
                        :customStyle="{ padding: '12px', background: '#f2f5f9', borderRadius: '8px' }"
                        @confirm="search"
                    />
                    <view class="mc-query-button"
                        ><MemberCardButton
                            type="primary"
                            text="查询"
                            :loading="loading"
                            loadingText="查询中"
                            @click="search"
                    /></view>
                </view>
                <MemberCardCollapse title="按姓名精确筛选" :summary="name || '重名或多人命中时使用'">
                    <view class="mc-input">
                        <u-input v-model="name" border="none" placeholder="完整购卡姓名（选填）" @confirm="search" />
                    </view>
                </MemberCardCollapse>
                <view class="mc-footnote">输入后四位即可查卡，核销前请当面核对姓名。</view>
            </view>
            <MemberCardNotice
                v-if="outcome"
                :tone="outcome.warning ? 'warning' : 'success'"
                :title="outcome.title"
                :text="outcome.text"
                action="服务下一位"
                @action="clear"
            />
            <MemberCardState
                v-if="error || (searched && !loading && !candidates.length)"
                :error="error"
                text="没有找到匹配的会员卡，请核对手机号"
                :action="error ? '重新查询' : ''"
                @action="search"
            />
            <view v-if="candidates.length" class="mc-between" style="margin: 22rpx 0 16rpx">
                <text class="mc-title">查询结果</text>
                <text class="mc-small">{{ candidates.length }} 位客户</text>
            </view>
            <view v-for="candidate in candidates" :key="candidate.member_id" class="mc-card">
                <view class="mc-row">
                    <view class="mc-avatar">{{ String(candidate.holder_name || '客').slice(0, 1) }}</view>
                    <view class="mc-grow">
                        <view class="mc-title">{{ candidate.holder_name }}</view>
                        <view class="mc-sub">{{ candidate.mobile_masked }}</view>
                    </view>
                    <text class="mc-badge" :class="candidate.available_card_count ? 'mc-badge--success' : ''">
                        {{ candidate.available_card_count }} 张可用
                    </text>
                </view>
                <view v-for="card in candidate.cards" :key="card.card_id" class="mc-select">
                    <view class="mc-grow">
                        <view>{{ card.product_name }}</view>
                        <view class="mc-sub">{{ card.item_name }} · {{ card.validity_text }}</view>
                        <view class="mc-sub" :style="{ color: card.available ? '#287951' : '#94651d' }">
                            {{
                                card.available
                                    ? card.usage_mode === 'unlimited'
                                        ? '不限次'
                                        : '剩余 ' + card.remaining_times + ' 次'
                                    : unavailableReason(card)
                            }}
                        </view>
                        <view v-if="card.binding_mode !== 'member'" class="mc-small" style="margin-top: 6rpx">
                            {{
                                card.binding_mode === 'imei'
                                    ? '绑定 IMEI：' + card.bound_imei
                                    : '适用型号：' + card.bound_model
                            }}
                        </view>
                    </view>
                    <view style="width: 136rpx; flex-shrink: 0">
                        <MemberCardButton
                            compact
                            type="primary"
                            text="核销"
                            :disabled="!card.available || loading"
                            @click="openConfirm(candidate, card)"
                        />
                    </view>
                </view>
            </view>
        </view>
        <MemberCardSheet
            v-model:show="confirmVisible"
            title="核对并核销"
            :subtitle="
                selected?.card?.usage_mode === 'unlimited'
                    ? '记录本次服务，不限次权益不扣减余额次数'
                    : '确认后扣减 1 次，并记录当前操作人'
            "
            :busy="redeeming"
            :height="selected?.card?.binding_mode === 'member' && inventoryMode === 'none' ? '52vh' : '72vh'"
        >
            <template v-if="selected">
                <view class="mc-selection">
                    <view class="mc-title">
                        {{ selected.candidate.holder_name }} · {{ selected.candidate.mobile_masked }}
                    </view>
                    <view class="mc-sub">{{ selected.card.product_name }} / {{ selected.card.item_name }}</view>
                </view>
                <view v-if="selected.card.binding_mode === 'imei'" style="margin-top: 20rpx">
                    <view class="mc-section-title mc-required">核验本次服务设备</view>
                    <view class="mc-input mc-row">
                        <view class="mc-grow">
                            <u-input v-model="serviceImei" border="none" placeholder="扫描或输入本次设备 IMEI" />
                        </view>
                        <text class="mc-link" @click="scanServiceImei">扫码</text>
                    </view>
                </view>
                <view v-if="selected.card.binding_mode === 'model'" style="margin-top: 20rpx">
                    <view class="mc-section-title mc-required">核验本次设备型号</view>
                    <view class="mc-input">
                        <u-input
                            v-model="serviceModel"
                            border="none"
                            :placeholder="'请输入：' + selected.card.bound_model"
                        />
                    </view>
                </view>
                <view v-if="inventoryMode !== 'none' && selected.card.consumable_name" class="mc-summary">
                    <view class="mc-between">
                        <text>实际耗材</text>
                        <text class="mc-badge">{{ inventoryMode === 'strict' ? '严格库存' : '自动扣减' }}</text>
                    </view>
                    <view class="mc-sub">
                        {{ selected.card.consumable_name }} · 标准 {{ quantity(selected.card.standard_consumable_qty)
                        }}{{ selected.card.consumable_unit }}
                    </view>
                    <view class="mc-row" style="margin-top: 20rpx">
                        <u-number-box v-model="actualConsumableQty" :min="1" :max="99" />
                        <text class="mc-sub">{{ selected.card.consumable_unit }}</text>
                    </view>
                    <view class="mc-sub">
                        {{
                            lossQuantity > 0
                                ? '多耗 ' + quantity(lossQuantity) + selected.card.consumable_unit + '，将记录为损耗。'
                                : inventoryMode === 'strict'
                                  ? '库存不足会阻止核销，不扣会员卡次数。'
                                  : '库存不足仍可核销，需随后补齐库存。'
                        }}
                    </view>
                </view>
                <MemberCardCollapse title="服务备注" :summary="remark ? '已填写' : '选填'">
                    <u-textarea v-model="remark" maxlength="255" placeholder="本次服务说明" count />
                </MemberCardCollapse>
                <view class="mc-verification">
                    <u-checkbox-group
                        :modelValue="confirmed ? ['verified'] : []"
                        :disabled="redeeming"
                        @change="verificationChanged"
                    >
                        <u-checkbox
                            name="verified"
                            label="已核对客户，本次服务已完成"
                            :labelSize="13"
                            shape="square"
                            :size="19"
                            activeColor="#2868ce"
                        />
                    </u-checkbox-group>
                </view>
                <MemberCardNotice v-if="redeemError" tone="error" :text="redeemError" />
            </template>
            <template #footer>
                <MemberCardButton
                    type="primary"
                    text="确认核销"
                    :disabled="!confirmed"
                    :loading="redeeming"
                    loadingText="核销中…"
                    @click="redeem"
                />
            </template>
        </MemberCardSheet>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { computed, ref, watch } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { memberCardRequestId, redeemMemberCard, searchMemberCards } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardSheet from '../../components/MemberCardSheet.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardNotice from '../../components/MemberCardNotice.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import { quantity, errorText, markMemberCardChanged } from '../../utils/presentation'
const mobile = ref(''),
    name = ref(''),
    error = ref(''),
    redeemError = ref('')
const loading = ref(false),
    searched = ref(false),
    confirmVisible = ref(false),
    confirmed = ref(false),
    redeeming = ref(false)
const candidates = ref<any[]>([]),
    inventoryConfig = ref<any>({ mode: 'none' }),
    selected = ref<any>(null),
    outcome = ref<any>(null)
const remark = ref(''),
    serviceImei = ref(''),
    serviceModel = ref('')
const actualConsumableQty = ref(1)
const verificationChanged = (values: string[]) => {
    if (!redeeming.value) confirmed.value = values.includes('verified')
}
const inventoryMode = computed(() =>
    ['auto', 'strict'].includes(inventoryConfig.value?.mode) ? inventoryConfig.value.mode : 'none'
)
const lossQuantity = computed(() =>
    Math.max(0, Number(actualConsumableQty.value || 0) - Number(selected.value?.card?.standard_consumable_qty || 0))
)
const unavailableReason = (card: any) => {
    const states: Record<string, string> = {
        frozen: '会员卡已冻结',
        expired: '会员卡已过期',
        exhausted: '次数已用完',
        cancelled: '会员卡已取消',
        refunded: '会员卡已退款',
        refund_pending: '退款处理中'
    }
    if (states[card.status]) return states[card.status]
    if (card.finance_status !== 'settled') return '收款未完成，请查看开卡订单'
    if (card.usage_mode === 'limited' && Number(card.remaining_times) <= 0) return '剩余次数不足'
    return '暂不可核销，请查看会员卡状态'
}
let sequence = 0,
    attemptId = ''
const clear = () => {
    mobile.value = ''
    name.value = ''
    candidates.value = []
    searched.value = false
    outcome.value = null
    error.value = ''
    sequence++
    loading.value = false
}
watch([mobile, name], () => {
    sequence++
    candidates.value = []
    searched.value = false
    loading.value = false
    error.value = ''
})
const search = async () => {
    if (!/^\d{4}$|^1\d{10}$/.test(mobile.value)) {
        uni.showToast({ title: '请输入完整手机号或后四位', icon: 'none' })
        return
    }
    const ticket = ++sequence
    loading.value = true
    error.value = ''
    candidates.value = []
    try {
        const data =
            ((await searchMemberCards({ mobile_keyword: mobile.value, name: name.value.trim() })) as any)?.data || {}
        if (ticket !== sequence) return
        candidates.value = data.candidates || []
        inventoryConfig.value = data.inventory_config || { mode: 'none' }
        searched.value = true
    } catch (e) {
        if (ticket === sequence) error.value = errorText(e, '查询失败，请重试')
    } finally {
        if (ticket === sequence) loading.value = false
    }
}
const openConfirm = (candidate: any, card: any) => {
    if (!card.available || loading.value) return
    selected.value = { candidate, card }
    confirmed.value = false
    remark.value = ''
    serviceImei.value = ''
    serviceModel.value = ''
    redeemError.value = ''
    actualConsumableQty.value = Math.max(1, Number(card.standard_consumable_qty || 1))
    attemptId = memberCardRequestId('redeem')
    confirmVisible.value = true
}
const scanServiceImei = () =>
    uni.scanCode({
        success: (res) => {
            serviceImei.value = String(res.result || '').trim()
        },
        fail: () => uni.showToast({ title: '未读取到串号，可手动填写', icon: 'none' })
    })
const redeem = async () => {
    if (!confirmed.value || redeeming.value || !selected.value) return
    if (selected.value.card.binding_mode === 'imei' && !serviceImei.value.trim()) {
        redeemError.value = '请扫描或输入本次设备 IMEI'
        return
    }
    if (selected.value.card.binding_mode === 'model' && !serviceModel.value.trim()) {
        redeemError.value = '请输入本次设备型号'
        return
    }
    redeeming.value = true
    redeemError.value = ''
    try {
        const data: any = (
            await redeemMemberCard(selected.value.card.card_id, {
                request_id: attemptId,
                card_item_id: selected.value.card.item_id,
                service_imei: serviceImei.value.trim(),
                service_model: serviceModel.value.trim(),
                verification_confirmed: 1,
                actual_consumable_qty: inventoryMode.value === 'none' ? 0 : Number(actualConsumableQty.value),
                remark: remark.value
            })
        )?.data
        if (!data?.redeem_id && !data?.redeem_no) throw new Error('missing redemption')
        confirmVisible.value = false
        markMemberCardChanged()
        const warning = ['negative', 'failed'].includes(data.inventory_status)
        outcome.value = {
            warning,
            title: warning ? '服务已核销，库存需跟进' : '核销成功',
            text: warning
                ? data.inventory_message || '次数已记录，请处理耗材库存；不要重复核销。'
                : '本次服务已记录，可继续服务下一位客户。'
        }
        await search()
    } catch (e) {
        redeemError.value = errorText(e, '未能确认核销结果，请先核对核销记录。重试会沿用本次请求。')
    } finally {
        redeeming.value = false
    }
}
onLoad((options: any) => {
    const value = String(options?.mobile || '').trim()
    if (/^1\d{10}$/.test(value)) {
        mobile.value = value
        Promise.resolve().then(search)
    }
})
onUnload(() => {
    sequence++
})
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
