<template>
    <view class="income-panel">
        <view class="income-account">
            <view class="income-account__heading"
                ><text>可提现佣金</text
                ><button aria-label="账户说明" @tap="showAccountHelp"
                    ><u-icon name="info-circle" size="17" color="#8993a2" /></button
            ></view>
            <view class="income-account__main"
                ><text
                    class="income-account__balance"
                    >{{ ui.ready.value ? coopMoney(ui.account.value.available) : '—' }}</text
                ><button
                    class="income-withdraw"
                    :disabled="!ui.ready.value || !!ui.error.value || ui.loading.value || Number(ui.account.value.available || 0) <= 0"
                    @tap="withdraw"
                    >提现</button
                ></view
            >
            <view class="income-account__foot"
                ><text>提现处理中 {{ ui.ready.value ? coopMoney(ui.account.value.frozen) : '—' }}</text
                ><button @tap="cashOutRecords">提现记录<u-icon name="arrow-right" size="11" color="#7f8a9b" /></button
            ></view>
        </view>
        <view class="income-filter"
            ><scroll-view scroll-x :show-scrollbar="false" class="income-types"
                ><view class="income-types__inner"
                    ><button
                        v-for="item in cooperationIncomeTypes"
                        :key="item.value"
                        :class="{ 'is-active': ui.incomeType.value === item.value }"
                        @tap="selectType(item.value)"
                        >{{ item.label }}</button
                    ></view
                ></scroll-view
            ></view
        >
        <view class="income-list-heading"
            ><text>收益明细</text
            ><button @tap="selectStatus"
                ><text>{{ statusLabel }}</text
                ><u-icon name="arrow-down" size="10" color="#8692a2" /></button
        ></view>
        <view class="income-list-area">
            <z-paging
                v-if="active"
                :key="revision"
                ref="pagingRef"
                :model-value="ui.rows.value"
                class="income-pager"
                :fixed="false"
                height="100%"
                :auto="true"
                :concat="false"
                :scrollable="!detail"
                :refresher-enabled="!detail"
                :default-page-size="20"
                :auto-clean-list-when-reload="false"
                :safe-area-inset-bottom="false"
                :call-network-reject="false"
                :show-loading-more-no-more-line="false"
                loading-more-fail-text="读取失败，点击重试"
                loading-more-no-more-text="已显示全部收益"
                @query="pagingQuery"
            >
                <view
                    v-if="ui.error.value && ui.rows.value.length"
                    class="income-read-error"
                    role="alert"
                    >{{ ui.error.value }}</view
                >
                <view
                    v-for="item in ui.rows.value"
                    :key="cooperationIncomeKey(item)"
                    class="income-row"
                    role="button"
                    @tap="openDetail(item)"
                >
                    <view class="income-row__icon" :class="'is-' + item.income_type"
                        ><u-icon
                            :name="item.income_type === 'publisher' ? 'order' : 'share'"
                            size="19"
                            :color="item.income_type === 'publisher' ? '#9d7b4c' : 'var(--primary-color,#3978c2)'"
                    /></view>
                    <view class="income-row__content"
                        ><view class="income-row__title"
                            ><text
                                >{{ cooperationIncomeLabel(item.income_type)
                                }}<text
                                    v-if="rewardLevel(item)"
                                    class="income-row__level"
                                    >{{ rewardLevel(item) }}</text
                                ></text
                            ><text
                                class="income-row__amount"
                                :class="{ 'is-credited': cooperationIncomeCredited(item) }"
                                >{{ cooperationIncomeCredited(item) ? '+' : '' }}{{ coopMoney(item.amount) }}</text
                            ></view
                        ><view
                            class="income-row__person"
                            >{{ item.payer_name || item.customer_name || '协作收入' }}</view
                        ><view class="income-row__meta"
                            ><text>{{ coopDate(item.create_time) }}</text
                            ><text
                                :class="{ 'is-pending': item.status === 'pending' }"
                                >{{ cooperationIncomeStatus(item) }}</text
                            ></view
                        ></view
                    >
                </view>
                <template #empty="{ isLoadFailed }"
                    ><view class="income-empty"
                        ><CoopState
                            :error="isLoadFailed ? ui.error.value || '收益读取失败，请重试' : ''"
                            title="还没有这类收益"
                            hint="符合规则的付款后，收益会记录在这里"
                            icon="order"
                            @retry="refresh" /></view
                ></template>
            </z-paging>
        </view>
        <CoopTaskSheet :show="!!detail" title="收益详情" @close="detail = null">
            <view v-if="detail && rewardLevel(detail)" class="income-detail__level"
                >{{ rewardLevel(detail) }} · 按原订单的有效计佣金额计算</view
            >
            <view v-if="detail" class="income-detail"
                ><view class="income-detail__amount">{{ coopMoney(detail.amount) }}</view
                ><view class="income-detail__status">{{ cooperationIncomeStatus(detail) }}</view
                ><view class="income-detail__row"
                    ><text>收益类型</text><text>{{ cooperationIncomeLabel(detail.income_type) }}</text></view
                ><view class="income-detail__row"
                    ><text>{{ detail.income_type === 'publisher' ? '客户' : '付费会员' }}</text
                    ><text>{{ detail.payer_name || detail.customer_name || '—' }}</text></view
                ><template v-if="detail.income_type !== 'publisher'"
                    ><view class="income-detail__row"
                        ><text>计佣基数</text><text>{{ coopMoney(detail.base_amount) }}</text></view
                    ><view class="income-detail__row"
                        ><text>付款时比例</text><text>{{ detail.rate }}%</text></view
                    ></template
                ><view class="income-detail__row"
                    ><text>产生时间</text><text>{{ coopDate(detail.create_time) }}</text></view
                ><view v-if="Number(detail.available_at) > 0" class="income-detail__row"
                    ><text>预计可结算</text><text>{{ coopDate(detail.available_at) }}</text></view
                ><view v-if="detail.reason" class="income-detail__reason">{{ detail.reason }}</view
                ><view v-if="detail.income_type === 'membership'" class="income-detail__basis"
                    >只按实付服务费计算，不含押金和经验费</view
                ></view
            >
        </CoopTaskSheet>
    </view>
</template>
<script lang="ts">
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import ZPaging from 'z-paging/components/z-paging/z-paging.vue'
import { redirect } from '@/utils/common'
import { coopDate, coopMoney, type CoopRow } from '../../api/cooperation'
import { useCooperationIncome } from '../../composables/useCooperationIncome'
import { cooperationIncomeTypes, cooperationIncomeType, cooperationIncomeKey, cooperationIncomeLabel, cooperationIncomeCredited, cooperationIncomeStatus } from '../../utils/cooperation-income'
import { createCooperationPagingGuard } from '../../utils/cooperation-paging'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
const props = withDefaults(defineProps<{ active?: boolean; initialType?: string; initialStatus?: string }>(), { active: true, initialType: 'all', initialStatus: '' })
const emit = defineEmits<{ (event: 'overlay', value: boolean): void }>()
const ui = useCooperationIncome(), pagingRef = ref<any>(null), revision = ref(0), detail = ref<CoopRow | null>(null)
const pagingGuard = createCooperationPagingGuard()
ui.incomeType.value = cooperationIncomeType(props.initialType)
const statusOptions = [{ value: '', label: '全部状态' }, { value: 'pending', label: '待结算' }, { value: 'settled', label: '已入账' }, { value: 'held', label: '待核对' }]
ui.status.value = statusOptions.some(item => item.value === props.initialStatus) ? props.initialStatus : ''
const statusLabel = computed(() => statusOptions.find(item => item.value === ui.status.value)?.label || '全部状态')
const pagingQuery = computed(() => { const version = revision.value; return (page: number, limit: number) => query(page, limit, version) })
async function query(page: number, limit: number, version: number) {
    if (!props.active || version !== revision.value) return
    if (!pagingGuard.begin(page)) { refresh(); return }
    const loaded = await ui.read(page, limit)
    if (!props.active || version !== revision.value) return
    if (loaded) { pagingGuard.succeed(page); void pagingRef.value?.completeByNoMore([...ui.rows.value], !ui.hasMore.value) }
    else if (ui.error.value) void pagingRef.value?.complete(false)
}
function refresh() { pagingGuard.reset(); ui.pause(); ui.resume(); revision.value++ }
function selectType(value: string) { if (ui.filter(value)) refresh() }
function selectStatus() { uni.showActionSheet({ itemList: statusOptions.map(item => item.label), success: result => { const next = statusOptions[result.tapIndex]?.value; if (next === undefined || next === ui.status.value) return; ui.status.value = next; ui.rows.value = []; ui.total.value = 0; refresh() } }) }
function openDetail(row: CoopRow) { detail.value = { ...row } }
function rewardLevel(row: CoopRow) { return row.income_type!=='publisher'&&[1,2].includes(Number(row.commission_level)) ? Number(row.commission_level)===1?'直接邀请奖励':'推广奖励' : '' }
function withdraw() { if (!ui.ready.value || ui.error.value || ui.loading.value || Number(ui.account.value.available || 0) <= 0) return; uni.setStorageSync('cashOutAccountType', 'commission'); redirect({ url: '/app/pages/member/apply_cash_out' }) }
function cashOutRecords() { uni.setStorageSync('cashOutAccountType', 'commission'); redirect({ url: '/app/pages/member/cash_out' }) }
function showAccountHelp() { uni.showModal({ title: '会员佣金账户', content: '收益沿用本站会员佣金账户。待结算、待核对的记录尚不可提现；账户也可能包含本站其他业务的佣金。提现条件以申请页为准。', showCancel: false }) }
watch(detail, value => emit('overlay', !!value))
watch(() => props.active, active => { if (active) refresh(); else { detail.value = null; ui.pause() } }, { immediate: true })
onBeforeUnmount(() => { ui.pause(); emit('overlay', false) })
defineExpose({ refresh })
</script>
<style scoped lang="scss">
.income-panel { display:flex;flex:1 1 0;min-height:0;flex-direction:column;overflow:hidden;color:#20252d; }
.income-account { flex:none;margin:12px 12px 0;padding:16px 18px;background:#fff;border-radius:10px; }
.income-account__heading { display:flex;align-items:center;justify-content:space-between;font-size:13px;color:#6b7687; }
.income-account button { margin:0;padding:0;border:0;background:transparent; }
.income-account button::after,.income-types button::after { border:0; }
.income-account__heading button { width:44px;height:44px;margin:-8px -12px -8px 0;display:flex;align-items:center;justify-content:center; }
.income-account__main { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:8px; }
.income-account__balance { font-size:32px;line-height:1.4;font-weight:700;min-width:0;overflow-wrap:anywhere;font-variant-numeric:tabular-nums; }
.income-account .income-withdraw { display:flex;align-items:center;justify-content:center;min-width:72px;min-height:44px;padding:0 14px;box-sizing:border-box;font-size:14px;background:var(--primary-color,#3978c2);color:#fff;border-radius:22px;flex:none; }
.income-account .income-withdraw[disabled] { background:#edf0f4;color:#9ba4b2; }
.income-account__foot { display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px;font-size:12px;color:#8993a2; }
.income-account__foot button { display:flex;align-items:center;justify-content:flex-end;min-height:44px;gap:4px;color:#65748a;font-size:12px; }
.income-filter { flex:none;padding:8px 12px 0; }
.income-types { white-space:nowrap;width:100%; }
.income-types__inner { display:flex;align-items:center;gap:8px; }
.income-types button { flex:none;padding:0 13px;margin:0;min-height:44px;line-height:44px;border:0;border-radius:8px;background:transparent;font-size:13px;color:#748094; }
.income-types button.is-active { color:var(--primary-color,#3978c2);background:#fff;font-weight:600; }
.income-list-heading { flex:none;display:flex;align-items:center;justify-content:space-between;padding:2px 16px 0;font-size:12px;color:#8993a2; }.income-list-heading button { margin:0 -8px 0 0;padding:0 8px;min-height:44px;display:flex;align-items:center;gap:5px;font-size:12px;color:#7b8696;background:transparent;border:0; }.income-list-heading button::after { border:0; }
.income-list-area { flex:1 1 0;min-height:0; }
.income-pager { height:100%;min-height:0; }
.income-row { display:flex;gap:12px;padding:18px 16px;background:#fff;border-bottom:1px solid #f0f1f4; }
.income-row__icon { flex:none;width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--primary-color-light,#edf4fc);border-radius:10px; }
.income-row__icon.is-publisher { background:#faf4eb; }
.income-row__content { flex:1;min-width:0; }
.income-row__title { display:flex;align-items:flex-start;justify-content:space-between;gap:12px;font-size:14px;font-weight:600;line-height:1.6; }
.income-row__title>text:first-child { flex:1;min-width:0; }
.income-row__level { display:inline-block;margin-left:6px;font-size:12px;font-weight:400;line-height:18px;padding:0 5px;border-radius:4px;color:var(--primary-color,#3978c2);background:var(--primary-color-light,#edf4fc);vertical-align:middle; }.income-detail__level { padding:16px 20px 0;background:#fff;font-size:12px;line-height:1.7;color:#7b8696; }
.income-row__amount { max-width:50%;overflow-wrap:anywhere;font-size:16px;font-variant-numeric:tabular-nums; }
.income-row__amount.is-credited { color:#303b4a; }
.income-row__person { font-size:12px;color:#7b8696;line-height:1.6;margin-top:3px;overflow-wrap:anywhere; }
.income-row__meta { display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-top:8px;font-size:12px;color:#9aa2af;line-height:1.6; }
.income-row__meta>text:last-child { flex:none;max-width:40%;text-align:right; }.income-row__meta .is-pending { color:#9b7e4d; }
.income-empty { padding:24px 16px; }.income-read-error { padding:12px 16px;font-size:12px;line-height:1.6;color:#a1604b; }
.income-detail { padding:12px 20px 24px;background:#fff; }.income-detail__amount { text-align:center;margin-top:12px;font-size:32px;font-weight:700;overflow-wrap:anywhere; }.income-detail__status { text-align:center;font-size:13px;color:#7b8696;margin:6px 0 20px; }
.income-detail__row { display:flex;justify-content:space-between;gap:20px;padding:13px 0;border-top:1px solid #f0f1f4;font-size:13px;line-height:1.6; }.income-detail__row>text:first-child { color:#8c95a3;flex:none; }.income-detail__row>text:last-child { min-width:0;text-align:right;overflow-wrap:anywhere; }
.income-detail__reason,.income-detail__basis { font-size:12px;line-height:1.8;margin-top:12px;color:#8a93a0; }.income-detail__reason { color:#a1604b; }
</style>
