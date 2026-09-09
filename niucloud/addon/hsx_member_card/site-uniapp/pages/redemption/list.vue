<template>
    <view class="mc-page">
        <z-paging ref="paging" v-model="rows" @query="query">
            <template #top>
                <MemberCardListHeader
                    v-model="keyword"
                    :activeTab="status"
                    :tabs="tabs"
                    placeholder="客户 / 手机号 / 卡号 / 操作人"
                    @search="reload"
                    @tab-change="changeTab"
                />
            </template>
            <template #empty>
                <MemberCardState text="暂无匹配的核销记录" :error="listError" @action="reload" />
            </template>
            <view class="mc-content">
                <MemberCardNotice v-if="notice" tone="warning" :text="notice" closable />
                <view v-for="row in rows" :key="row.id" class="mc-card mc-record-card" @click="open(row)">
                    <view class="mc-between">
                        <view class="mc-grow">
                            <view class="mc-title">{{ row.holder_name }}</view>
                            <view class="mc-sub">{{ phone(row.holder_mobile) }}</view>
                        </view>
                        <text class="mc-badge" :class="row.status === 'success' ? 'mc-badge--success' : ''">
                            {{ statusLabel(row.status) }}
                        </text>
                    </view>
                    <view class="mc-record-body">
                        <view class="mc-between">
                            <text class="mc-grow">{{ row.item_name }}</text>
                            <text>{{ row.status === 'reversed' ? '撤销' : '核销' }} 1 次</text>
                        </view>
                        <view class="mc-sub">
                            {{ row.status === 'reversed' ? '已撤回确认收入' : '本次确认收入' }} ¥{{
                                money(row.recognized_amount)
                            }}
                        </view>
                    </view>
                    <view v-if="isStockWarning(row)" class="mc-sub" style="color: #94651d; margin-top: 16rpx">
                        {{ stockLabel(row.inventory_status) }}，请查看详情处理
                    </view>
                    <view class="mc-record-foot">
                        <text class="mc-small">{{ dateTime(row.occurred_at) }} · {{ row.operator_name || '—' }}</text>
                        <text class="mc-link">详情 ›</text>
                    </view>
                </view>
            </view>
        </z-paging>
        <MemberCardSheet v-model:show="detailVisible" :title="reversingMode ? '撤销本次核销' : '核销详情'" :busy="busy">
            <template v-if="selected">
                <view class="mc-title">{{ selected.item_name }}</view>
                <view class="mc-sub">{{ selected.holder_name }} · {{ phone(selected.holder_mobile) }}</view>
                <template v-if="reversingMode">
                    <MemberCardNotice
                        tone="warning"
                        title="仅用于误核销"
                        text="撤销本次服务记录，恢复有限次数并撤回对应确认收入；如曾扣减库存，将按原数量申请返库。请确认实际耗材也应返还。此操作不是给客户退款。"
                    />
                    <u-textarea v-model="reason" maxlength="255" placeholder="请填写撤销原因（必填）" count />
                </template>
                <template v-else>
                    <view class="mc-summary">
                        <view class="mc-between">
                            <text>{{ statusLabel(selected.status) }}</text>
                            <text>¥{{ money(selected.recognized_amount) }}</text>
                        </view>
                        <view class="mc-small">
                            {{ selected.status === 'reversed' ? '该笔确认收入已撤回' : '本次确认收入，不等于实际收款' }}
                        </view>
                    </view>
                    <view
                        v-if="Number(selected.before_remaining) || Number(selected.after_remaining)"
                        class="mc-detail-row"
                    >
                        <text>原核销次数变化</text>
                        <text>{{ selected.before_remaining }} → {{ selected.after_remaining }}</text>
                    </view>
                    <view class="mc-detail-row">
                        <text>操作人</text>
                        <text>{{ selected.operator_name || '—' }}</text>
                    </view>
                    <view class="mc-detail-row">
                        <text>服务时间</text>
                        <text>{{ dateTime(selected.occurred_at) }}</text>
                    </view>
                    <view v-if="selected.service_imei" class="mc-detail-row">
                        <text>设备 IMEI</text>
                        <text selectable>{{ selected.service_imei }}</text>
                    </view>
                    <view v-if="selected.service_model" class="mc-detail-row">
                        <text>设备型号</text>
                        <text>{{ selected.service_model }}</text>
                    </view>
                    <view v-if="selected.remark" class="mc-sub">备注：{{ selected.remark }}</view>
                    <MemberCardNotice
                        v-if="isStockWarning(selected)"
                        tone="warning"
                        :title="stockLabel(selected.inventory_status)"
                        :text="selected.inventory_message || '请到库存管理核对耗材数量，本次服务记录已保存。'"
                    />
                    <MemberCardCollapse
                        v-if="selected.inventory_mode !== 'none' && selected.consumable_name"
                        title="耗材与库存"
                        :summary="stockLabel(selected.inventory_status)"
                    >
                        <view class="mc-detail-row">
                            <text>实际使用</text>
                            <text>
                                {{ selected.consumable_name }} × {{ quantity(selected.actual_consumable_qty)
                                }}{{ selected.consumable_unit }}
                            </text>
                        </view>
                        <view class="mc-detail-row">
                            <text>其中损耗</text>
                            <text>{{ quantity(selected.loss_consumable_qty) }}{{ selected.consumable_unit }}</text>
                        </view>
                        <view class="mc-detail-row">
                            <text>仓库 / 库位</text>
                            <text>
                                {{ selected.inventory_warehouse_name || '—' }} /
                                {{ selected.inventory_location_name || '—' }}
                            </text>
                        </view>
                        <view class="mc-detail-row">
                            <text>库存变化</text>
                            <text>
                                {{ quantity(selected.inventory_stock_before) }} →
                                {{ quantity(selected.inventory_stock_after) }}
                            </text>
                        </view>
                    </MemberCardCollapse>
                    <MemberCardCollapse
                        title="业务凭据"
                        :summary="selected.status === 'reversed' ? '含撤销信息' : '核销单号与卡号'"
                    >
                        <view class="mc-detail-row">
                            <text>核销单号</text>
                            <text selectable @click="copyText(selected.redeem_no)">{{ selected.redeem_no }}</text>
                        </view>
                        <view class="mc-detail-row">
                            <text>会员卡号</text>
                            <text selectable @click="copyText(selected.card_no)">{{ selected.card_no || '—' }}</text>
                        </view>
                        <template v-if="selected.status === 'reversed'">
                            <view class="mc-detail-row">
                                <text>撤销人</text>
                                <text>{{ selected.reverse_name || '—' }}</text>
                            </view>
                            <view class="mc-detail-row">
                                <text>撤销时间</text>
                                <text>{{ dateTime(selected.reversed_at) }}</text>
                            </view>
                            <view class="mc-sub">撤销原因：{{ selected.reverse_reason || '—' }}</view>
                        </template>
                    </MemberCardCollapse>
                </template>
                <MemberCardNotice v-if="error" tone="error" :text="error" />
            </template>
            <template #footer>
                <view class="mc-sheet__actions">
                    <view>
                        <MemberCardButton :text="reversingMode ? '返回详情' : '关闭'" :disabled="busy" @click="back" />
                    </view>
                    <view v-if="selected?.status === 'success'">
                        <MemberCardButton
                            :type="reversingMode ? 'warning' : 'default'"
                            :text="reversingMode ? '确认撤销' : '误核销撤销'"
                            :loading="busy"
                            @click="primary"
                        />
                    </view>
                </view>
            </template>
        </MemberCardSheet>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { ref } from 'vue'
import { getCardRedemptions, memberCardRequestId, reverseCardRedemption } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardSheet from '../../components/MemberCardSheet.vue'
import MemberCardNotice from '../../components/MemberCardNotice.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import { useMemberCardList } from '../../hooks/useMemberCardList'
import {
    money,
    quantity,
    phone,
    dateTime,
    copyText,
    stockLabel,
    errorText,
    markMemberCardChanged
} from '../../utils/presentation'
const keyword = ref(''),
    status = ref(''),
    notice = ref(''),
    error = ref(''),
    reason = ref('')
const selected = ref<any>(null),
    detailVisible = ref(false),
    reversingMode = ref(false),
    busy = ref(false)
const tabs = [
    { label: '全部', value: '' },
    { label: '核销成功', value: 'success' },
    { label: '已冲正', value: 'reversed' }
]
const { paging, rows, listError, query, reload, refresh } = useMemberCardList(getCardRedemptions, () => ({
    keyword: keyword.value.trim(),
    status: status.value
}))
const statusLabel = (value: string) =>
    value === 'success' ? '核销成功' : value === 'reversed' ? '已冲正' : '状态待确认'
const isStockWarning = (row: any) => ['negative', 'failed', 'restore_failed'].includes(row.inventory_status)
const changeTab = (value: string) => {
    status.value = value
    reload()
}
let attemptId = ''
const open = (row: any) => {
    selected.value = row
    detailVisible.value = true
    reversingMode.value = false
    error.value = ''
    reason.value = ''
    attemptId = memberCardRequestId('reverse')
}
const back = () => {
    if (busy.value) return
    if (reversingMode.value) reversingMode.value = false
    else detailVisible.value = false
}
const primary = () => {
    if (reversingMode.value) void reverse()
    else reversingMode.value = true
}
const reverse = async () => {
    if (busy.value || !selected.value || selected.value.status !== 'success') return
    if (!reason.value.trim()) {
        error.value = '请填写撤销原因'
        return
    }
    busy.value = true
    error.value = ''
    try {
        const result: any = (
            await reverseCardRedemption(selected.value.id, { request_id: attemptId, reason: reason.value.trim() })
        )?.data
        if (result?.redemption_status !== 'reversed') throw new Error('unconfirmed reverse')
        markMemberCardChanged()
        detailVisible.value = false
        if (result.inventory_status === 'restore_failed')
            notice.value = '核销已撤销，但耗材返库未完成。请到库存管理核对，不要重复核销或冲正。'
        else uni.showToast({ title: '本次核销已撤销', icon: 'success' })
        refresh()
    } catch (e) {
        error.value = errorText(e, '未能确认撤销结果，请刷新记录核对后再试')
    } finally {
        busy.value = false
    }
}
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
