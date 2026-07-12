<template>
    <view class="cost-detail-page">
        <!-- 设备信息 -->
        <view class="card asset-card">
            <view class="asset-card__head">
                <text class="asset-title">{{ asset.model || '设备成本调整' }}</text>
                <u-tag :text="statusLabel(asset.inventory_status)" :type="statusType(asset.inventory_status)" plain plainFill size="mini"></u-tag>
            </view>
            <view class="info-grid">
                <view class="info-item">
                    <text class="info-label">资产号</text>
                    <text class="info-value">{{ asset.asset_no || '-' }}</text>
                </view>
                <view class="info-item">
                    <text class="info-label">IMEI</text>
                    <text class="info-value">{{ asset.imei || '-' }}</text>
                </view>
                <view class="info-item" v-if="asset.warehouse_name">
                    <text class="info-label">仓库库位</text>
                    <text class="info-value">{{ asset.warehouse_name }}{{ asset.location_name ? ' / ' + asset.location_name : '' }}</text>
                </view>
                <view class="info-item" v-if="supplierName">
                    <text class="info-label">供应商</text>
                    <text class="info-value">{{ supplierName }}</text>
                </view>
                <view class="info-item" v-if="stockInText">
                    <text class="info-label">入库时间</text>
                    <text class="info-value">{{ stockInText }}</text>
                </view>
            </view>
        </view>

        <!-- 不可调整状态：只读提示 -->
        <view v-if="blocked" class="card blocked-card">
            <u-icon name="lock-fill" color="#94a3b8" size="22"></u-icon>
            <text class="blocked-text">当前状态「{{ statusLabel(asset.inventory_status) }}」不可调整成本</text>
        </view>

        <template v-else>
            <view v-if="!refurbishMode" class="card">
                <view class="field-label">成本类型（必选）</view>
                <view class="cost-type-list">
                    <view v-for="item in costTypes" :key="item.value" class="cost-type-item relative" :class="{ active: costType === item.value }" @click="selectCostType(item.value)">
                        <view>
                            <text class="cost-type-title">{{ item.label }}</text>
                            <text class="cost-type-desc">{{ item.description }}</text>
                        </view>
                         <view class="absolute top-[10px] right-[10px] ">
                            <u-icon v-if="costType === item.value" name="checkmark-circle-fill" color="#2563eb" size="20" />
                        </view>
                    </view>
                </view>
            </view>
            <view v-if="costType === 'refurbish'" class="card">
                <view class="field-label">整备结果（必选）</view>
                <view class="reason-chips mb-[24rpx]"><view v-for="item in refurbishResults" :key="item.value" class="chip" :class="{ active: refurbishResult === item.value }" @click="refurbishResult=item.value">{{ item.label }}</view></view>
                <view class="section-head">
                    <view>
                        <view class="field-label !mb-0">整备项目与费用</view>
                        <text class="section-desc">逐项填写，系统自动汇总并计入设备成本</text>
                    </view>
                    <view>
                        <u-button size="small" type="primary" plain text="添加项目" @click="addRefurbishItem" />
                    </view>
                </view>
                <view class="refurbish-list">
                    <view v-for="(item, index) in refurbishItems" :key="index" class="refurbish-row">
                        <view class="refurbish-row__main">
                            <view class="refurbish-fields">
                                <u-input v-model="item.name"  customStyle="padding:15rpx" border="none" placeholder="项目，如：换屏、修面容" />
                                <view class="refurbish-amount">
                                    <text>¥</text>
                                    <u-input v-model="item.amount" type="digit" border="none" placeholder="费用" inputAlign="right" />
                                </view>
                            </view>
                            <view class="item-party" :class="{ selected: item.party_id }" @click="openPartyPopup(index)">
                                <view>
                                    <text class="item-party__label">本项服务商</text>
                                    <text class="item-party__hint">分别生成应付</text>
                                </view>
                                <view class="item-party__selection">
                                    <text>{{ item.party_name || '请选择' }}</text>
                                    <u-icon name="arrow-right" color="#94a3b8" size="15" />
                                </view>
                            </view>
                        </view>
                        <view v-if="refurbishItems.length > 1" class="refurbish-delete" @click="removeRefurbishItem(index)">
                            <u-icon name="trash" color="#ef4444" size="18" />
                        </view>
                    </view>
                </view>
                <view class="split-tip">
                    <u-icon name="info-circle" color="#2563eb" size="16" />
                    <text>屏幕、面容、电池等项目可选择不同服务商；系统按项目分别生成应付，不需要人工拆账。</text>
                </view>
            </view>
            <!-- 成本调整 -->
            <view class="card">
                <view class="current-cost">
                    <text class="cc-label">当前成本</text>
                    <text class="cc-value">¥{{ formatMoney(asset.current_cost) }}</text>
                </view>
                <view v-if="costSummary" class="cost-breakdown">
                    <view v-for="item in costBreakdown" :key="item.label" class="cost-breakdown__item">
                        <text class="cost-breakdown__label">{{ item.label }}</text>
                        <text class="cost-breakdown__value" :class="{ muted: Math.abs(item.value) < 0.001 }">{{ signedMoney(item.value, item.signed) }}</text>
                    </view>
                </view>
                <view class="divider"></view>
                <template v-if="costType === 'refurbish'">
                    <view class="system-cost-head">
                        <text>本次整备合计</text>
                        <text class="system-cost-total">+¥{{ formatMoney(refurbishTotal) }}</text>
                    </view>
                    <view class="system-cost-result">
                        <view><text>系统计算后成本</text><text class="system-cost-note">当前成本 + 整备项目</text></view>
                        <text class="system-cost-value">¥{{ formatMoney(refurbishAfterCost) }}</text>
                    </view>
                </template>
                <template v-else>
                <view class="field-label">调整后成本</view>
                <view class="cost-input-row">
                    <text class="cost-prefix">¥</text>
                    <u-input
                        v-model="newCost"
                        type="digit"
                        border="none"
                        placeholder="输入新的成本"
                        :customStyle="{ padding: '0', fontSize: '40rpx', fontWeight: '700' }"
                    ></u-input>
                </view>
                <view v-if="deltaValid && Math.abs(delta) >= 0.001" class="delta-preview" :class="delta >= 0 ? 'up' : 'down'">
                    {{ delta >= 0 ? '↑ 上调' : '↓ 下调' }} ¥{{ formatMoney(Math.abs(delta)) }}
                </view>
                </template>
            </view>

            <!-- 调整原因 -->
            <view class="card">
                <view class="field-label">{{ costType === 'refurbish' ? '整备补充说明（选填）' : '调整原因（必填）' }}</view>
                <view v-if="costType !== 'refurbish'" class="reason-chips">
                    <view
                        v-for="r in reasonPresets"
                        :key="r"
                        class="chip"
                        :class="{ active: reason === r }"
                        @click="reason = r"
                    >{{ r }}</view>
                </view>
                <u-textarea
                    v-model="reason"
                    :placeholder="costType === 'refurbish' ? '可填写维修情况、材料型号等补充信息' : '请选择原因或填写说明'"
                    :maxlength="200"
                    count
                    height="120"
                    :customStyle="{ background: '#f8fafc', marginTop: '16rpx' }"
                ></u-textarea>
                <ErpVoucherUploader v-if="costType === 'refurbish'" v-model="refurbishVoucherUrls" title="整备凭证" hint="可上传维修清单、服务商账单或设备返回照片" />
            </view>

            <!-- 同步应付 -->
            <view class="card" v-if="!isOutbound && costType === 'purchase_adjust'">
                <view class="switch-row">
                    <view class="switch-text">
                        <text class="switch-title">差额自动计入供应商应付</text>
                        <text class="switch-sub">供应商调价必须同步采购本金和应付；已付款设备请走退款或补款流程。</text>
                    </view>
                    <u-icon name="checkmark-circle-fill" color="#16a34a" size="22" />
                </view>
            </view>
            <view class="card outbound-note" v-else-if="isOutbound">
                <u-icon name="info-circle" color="#94a3b8" size="16"></u-icon>
                <text>已出库设备：仅订正成本与毛利口径，不联动应付/应收。</text>
            </view>
            <view class="card outbound-note" v-else>
                <u-icon name="info-circle" color="#2563eb" size="16"></u-icon>
                <text>{{ costType === 'refurbish' ? '整备费用增加设备总成本，并独立生成整备服务商应付，不改变原采购供应商应付。' : '内部成本修正不改变供应商应付，并会保留独立流水。' }}</text>
            </view>

            <!-- 历史调整 -->
            <view class="card" v-if="adjustHistory.length">
                <view class="field-label">历史成本调整</view>
                <view v-for="(h, i) in adjustHistory" :key="i" class="history-item">
                    <view class="history-line">
                        <text class="history-cost">¥{{ formatMoney(h.before_cost) }} → ¥{{ formatMoney(h.after_cost) }}</text>
                        <text class="history-time">{{ fmtTime(h.occurred_at) }}</text>
                    </view>
                    <text class="history-remark"><text class="history-type">{{ h.cost_type_text }}</text>{{ h.remark ? ' · ' + h.remark : '' }}<text v-if="h.operator_name"> · {{ h.operator_name }}</text></text>
                </view>
            </view>

            <!-- 回填说明 -->
            <view class="backfill-note">
                <u-icon name="reload" color="#3b6ef5" size="16"></u-icon>
                <text class="bf-text">在 ERP 调整后，系统会自动把新成本回填到回收 / 财务，无需到各业务端重复操作。</text>
            </view>
        </template>

        <!-- 底部操作条 -->
        <view v-if="!blocked" class="footer-bar">
            <u-button
                type="primary"
                shape="circle"
                :loading="submitting"
                :disabled="!canSubmit"
                :text="refurbishMode ? '确认整备完工' : '确认调整'"
                color="#3b6ef5"
                @click="submit"
            ></u-button>
        </view>
        <ErpPartyPopup v-model:show="partyPopupVisible" v-model:partyId="popupPartyId" v-model:partyName="popupPartyName" roleType="supplier" roleContext="refurbish_provider" @select="selectItemParty" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getErpAssetInfo, adjustErpAssetCost, completeErpAssetRefurbish, getErpFinanceCategories } from '@/addon/hsx_erp/api/asset'
import { INVENTORY_STATUS_MAP, INVENTORY_OUTBOUND, isCostAdjustAllowed } from '@/addon/hsx_erp/api/dict'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'

const assetId = ref<string>('')
const asset = ref<any>({})
const supplierName = ref<string>('')
const adjustHistory = ref<any[]>([])
const newCost = ref<string>('')
const reason = ref<string>('')
const costType = ref<'purchase_adjust' | 'refurbish' | 'internal_adjust'>('internal_adjust')
const refurbishMode = ref(false)
const refurbishResult = ref<'success' | 'partial' | 'failed'>('success')
const refurbishVoucherUrls = ref('')
const refurbishResults = [{ value: 'success', label: '修复成功' }, { value: 'partial', label: '部分修复' }, { value: 'failed', label: '修复失败' }] as const
const submitting = ref<boolean>(false)
const financeCategories = ref<any[]>([])
const expenseTypeKey = ref('')
const partyPopupVisible = ref(false)
const activePartyItemIndex = ref(-1)
const popupPartyId = ref(0)
const popupPartyName = ref('')
const refurbishItems = ref<Array<{ name: string, amount: string, party_id: number, party_name: string }>>([{ name: '', amount: '', party_id: 0, party_name: '' }])
const refurbishExpenseTypes = computed(() => financeCategories.value.filter((row: any) => row.direction === 'expense' && row.scope === 'refurbish' && Number(row.enabled ?? 1) === 1))

const costTypes = [
    { value: 'purchase_adjust', label: '供应商调价', description: '退补差价、议价调整；可同步改变应付和采购退货本金。' },
    { value: 'internal_adjust', label: '内部成本修正', description: '修正账面成本但不改供应商往来；提交后标准采购退货会被锁定，直到完成分类。' },
] as const
const reasonPresets = computed(() => costType.value === 'refurbish'
    ? ['维修费', '配件费', '人工费', '检测费']
    : (costType.value === 'purchase_adjust' ? ['退补差价', '议价调整', '供应商补款', '供应商扣款'] : ['录入有误', '复检改判', '盘点修正']))

const statusLabel = (s: string) => INVENTORY_STATUS_MAP[s] || s || '-'
const statusType = (s: string) => {
    if (s === 'in_stock' || s === 'available_for_sale') return 'success'
    if (s === 'sold' || s === 'outbound' || s === 'locked') return 'primary'
    if (s === 'returned') return 'warning'
    if (s === 'void') return 'info'
    if (s === 'lost' || s === 'inbound_rejected') return 'error'
    return 'warning'
}
const formatMoney = (v: any) => Number(v || 0).toFixed(2)
const signedMoney = (v: any, signed = true) => {
    const n = Number(v || 0)
    return `${signed && n > 0 ? '+' : ''}¥${formatMoney(n)}`
}
const fmtTime = (ts: any) => {
    const n = Number(ts)
    if (!n) return ''
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${ d.getFullYear() }-${ p(d.getMonth() + 1) }-${ p(d.getDate()) } ${ p(d.getHours()) }:${ p(d.getMinutes()) }`
}

const blocked = computed(() => !isCostAdjustAllowed(asset.value.inventory_status))
const isOutbound = computed(() => String(asset.value.inventory_status) === INVENTORY_OUTBOUND)
const stockInText = computed(() => fmtTime(asset.value.stock_in_at))
const costSummary = computed(() => asset.value.cost_summary || null)
const costBreakdown = computed(() => {
    const row = costSummary.value || {}
    return [
        { label: '采购本金', value: Number(row.purchase_cost || 0), signed: false },
        { label: '供应商调价', value: Number(row.supplier_adjust_cost || 0), signed: true },
        { label: '整备费用', value: Number(row.refurbish_cost || 0), signed: true },
        { label: '内部成本修正', value: Number(row.internal_adjust_cost || 0), signed: true },
    ]
})

const delta = computed(() => {
    const n = Number(newCost.value)
    if (isNaN(n)) return 0
    return Math.round((n - Number(asset.value.current_cost || 0)) * 100) / 100
})
const deltaValid = computed(() => newCost.value !== '' && !isNaN(Number(newCost.value)))
const normalizedRefurbishItems = computed(() => refurbishItems.value.map(item => ({
    name: item.name.trim(),
    amount: Math.round(Number(item.amount || 0) * 100) / 100,
    party_id: Number(item.party_id || 0),
    party_name: item.party_name.trim(),
})))
const refurbishTotal = computed(() => Math.round(normalizedRefurbishItems.value.reduce((sum, item) => sum + (Number.isFinite(item.amount) ? item.amount : 0), 0) * 100) / 100)
const refurbishAfterCost = computed(() => Math.round((Number(asset.value.current_cost || 0) + refurbishTotal.value) * 100) / 100)
const refurbishReason = computed(() => {
    const detail = normalizedRefurbishItems.value.map(item => `${item.name} ¥${formatMoney(item.amount)}（${item.party_name || '未选服务商'}）`).join('；')
    return [detail, reason.value.trim()].filter(Boolean).join('；')
})
const canSubmit = computed(() => {
    if (costType.value === 'refurbish') {
        const filled = normalizedRefurbishItems.value.filter(item => item.name || item.amount > 0 || item.party_id > 0)
        const itemsValid = filled.every(item => item.name.length > 0 && item.amount > 0 && item.party_id > 0)
        return itemsValid && (refurbishResult.value === 'success' || !!reason.value.trim())
    }
    if (!deltaValid.value) return false
    if (Number(newCost.value) <= 0) return false
    if (Math.abs(delta.value) < 0.001) return false
    if (!reason.value.trim()) return false
    return true
})

function selectCostType(value: 'purchase_adjust' | 'refurbish' | 'internal_adjust') {
    costType.value = value
    reason.value = ''
}

function addRefurbishItem() {
    refurbishItems.value.push({ name: '', amount: '', party_id: 0, party_name: '' })
}

function removeRefurbishItem(index: number) {
    if (refurbishItems.value.length <= 1) return
    refurbishItems.value.splice(index, 1)
}

function openPartyPopup(index: number) {
    activePartyItemIndex.value = index
    popupPartyId.value = Number(refurbishItems.value[index]?.party_id || 0)
    popupPartyName.value = String(refurbishItems.value[index]?.party_name || '')
    partyPopupVisible.value = true
}

function selectItemParty(party: any) {
    const index = activePartyItemIndex.value
    if (index < 0 || !refurbishItems.value[index]) return
    refurbishItems.value[index].party_id = Number(party?.party_id || 0)
    refurbishItems.value[index].party_name = String(party?.party_name || party?.counterparty_name || '')
}

async function loadFinanceCategories() {
    try {
        const res: any = await getErpFinanceCategories()
        financeCategories.value = Array.isArray(res?.data) ? res.data : []
        expenseTypeKey.value = refurbishExpenseTypes.value[0]?.key || ''
    } catch (e) { financeCategories.value = [] }
}

const dec = (v: any) => {
    try { return decodeURIComponent(String(v ?? '')) } catch (e) { return String(v ?? '') }
}

// 后台补全：供应商、入库时间、历史调整（详情接口）
const enrich = async () => {
    try {
        const res: any = await getErpAssetInfo(assetId.value)
        const d = res?.data || {}
        if (d.asset) asset.value = { ...asset.value, ...d.asset }
        supplierName.value = String(d.counterparty?.unit_name || '')
        adjustHistory.value = (d.cost_ledger || []).filter((x: any) => ['cost_adjust', 'refurbish'].includes(String(x.action || x.cost_type))).slice(0, 5)
    } catch (e) {
        // 详情接口失败不影响主流程，页面已用列表数据渲染
    }
}

const submit = async () => {
    if (!canSubmit.value || submitting.value) return
    if (blocked.value) {
        uni.showToast({ title: `当前状态「${statusLabel(asset.value.inventory_status)}」不可调整成本`, icon: 'none' })
        return
    }
    const submittedCost = costType.value === 'refurbish' ? refurbishAfterCost.value : Number(newCost.value)
    const submittedReason = costType.value === 'refurbish' ? refurbishReason.value : reason.value.trim()
    if (submittedCost <= 0) {
        uni.showToast({ title: '成本必须大于0', icon: 'none' })
        return
    }
    const confirmed = await confirmErpSensitiveAction({
        title: costType.value === 'refurbish' ? '确认整备完工' : '确认成本调整',
        content: costType.value === 'refurbish'
            ? `设备：${asset.value.model || asset.value.imei || asset.value.asset_no || '-'}\n项目：${refurbishReason.value}\n整备合计：¥${formatMoney(refurbishTotal.value)}\n成本：¥${formatMoney(asset.value.current_cost)} → ¥${formatMoney(submittedCost)}\n将生成整备服务商应付并保留流水。`
            : `设备：${asset.value.model || asset.value.imei || asset.value.asset_no || '-'}\n成本：¥${formatMoney(asset.value.current_cost)} → ¥${formatMoney(submittedCost)}\n类型：${costTypes.find(item => item.value === costType.value)?.label || costType.value}\n提交后保留成本和账务流水。`,
        confirmText: costType.value === 'refurbish' ? '确认完工' : '确认调整',
    })
    if (!confirmed) return
    submitting.value = true
    try {
        if (costType.value === 'refurbish') {
            await completeErpAssetRefurbish(assetId.value, { result: refurbishResult.value, refurbish_items: normalizedRefurbishItems.value.filter(item => item.name || item.amount > 0 || item.party_id > 0), voucher_urls: refurbishVoucherUrls.value, remark: reason.value.trim() })
        } else {
            await adjustErpAssetCost(assetId.value, submittedCost, submittedReason, !isOutbound.value && costType.value === 'purchase_adjust', costType.value)
        }
        uni.showToast({ title: costType.value === 'refurbish' ? '整备结果、成本与应付已同步' : '成本已调整', icon: 'none' })
        setTimeout(() => uni.navigateBack(), 800)
    } catch (e) {
        // 拦截器已提示具体错误
    } finally {
        submitting.value = false
    }
}

onLoad((options: any) => {
    assetId.value = String(options?.id || '')
    refurbishMode.value = String(options?.mode || '') === 'refurbish_complete'
    if (refurbishMode.value) {
        costType.value = 'refurbish'
        refurbishItems.value = []
        uni.setNavigationBarTitle({ title: '整备完工' })
    }
    if (!assetId.value) {
        uni.showToast({ title: '缺少资产ID', icon: 'none' })
        return
    }
    // 优先用列表带过来的数据：秒开
    if (options?.cost !== undefined && options?.cost !== '') {
        asset.value = {
            id: Number(options.id),
            model: dec(options.model),
            asset_no: dec(options.asset_no),
            imei: dec(options.imei),
            inventory_status: dec(options.status),
            current_cost: Number(options.cost || 0),
            warehouse_name: dec(options.wh),
            location_name: dec(options.loc)
        }
    }
    // 再后台补全供应商/入库时间/历史
    enrich()
    loadFinanceCategories()
})
</script>

<style lang="scss" scoped>
.cost-detail-page {
    min-height: 100vh;
    background: #f6f7fb;
    padding: 24rpx 24rpx 180rpx;
}

.card {
    background: #fff;
    border-radius: 20rpx;
    padding: 28rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}
.cost-type-list { display:flex; flex-direction:column; gap:14rpx; margin-top:16rpx; }
.cost-type-item { display:flex; align-items:center; justify-content:space-between; gap:16rpx; padding:18rpx; border:2rpx solid #e2e8f0; border-radius:16rpx; background:#f8fafc; }
.cost-type-item > view { display:flex; min-width:0; flex:1; flex-direction:column; gap:6rpx; }
.cost-type-item.active { border-color:#60a5fa; background:#eff6ff; }
.cost-type-title { color:#0f172a; font-size:25rpx; font-weight:650; }
.cost-type-desc { color:#64748b; font-size:20rpx; line-height:1.45; }
.split-tip { display:flex; align-items:flex-start; gap:10rpx; margin-top:18rpx; padding:16rpx 18rpx; border-radius:12rpx; background:#eff6ff; color:#475569; font-size:21rpx; line-height:1.55; }
.section-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; }
.section-desc { display:block; margin-top:7rpx; color:#94a3b8; font-size:21rpx; }
.refurbish-list { display:flex; flex-direction:column; gap:14rpx; margin-top:22rpx; }
.refurbish-row { display:flex; align-items:center; gap:12rpx; }
.refurbish-row__main { display:flex; flex:1; flex-direction:column; overflow:hidden; border:1rpx solid #e2e8f0; border-radius:14rpx; background:#f8fafc; }
.refurbish-fields { display:grid; grid-template-columns:minmax(0, 1fr) 190rpx; }
.refurbish-fields > :first-child { padding:0 16rpx; }
.item-party { display:flex; align-items:center; justify-content:space-between; gap:14rpx; min-height:72rpx; padding:0 16rpx; border-top:1rpx solid #e2e8f0; background:#fff; }
.item-party > view:first-child { display:flex; flex-direction:column; }
.item-party__label { color:#475569; font-size:22rpx; font-weight:600; }
.item-party__hint { color:#94a3b8; font-size:18rpx; }
.item-party__selection { display:flex; align-items:center; gap:6rpx; color:#94a3b8; font-size:22rpx; }
.item-party.selected .item-party__selection { color:#2563eb; font-weight:600; }
.refurbish-amount { display:flex; align-items:center; padding:0 16rpx; border-left:1rpx solid #e2e8f0; color:#475569; }
.refurbish-amount :deep(.u-input) { min-width:0; }
.refurbish-delete { display:flex; align-items:center; justify-content:center; width:54rpx; height:54rpx; border-radius:50%; background:#fef2f2; }
.system-cost-head { display:flex; align-items:center; justify-content:space-between; color:#64748b; font-size:25rpx; }
.system-cost-total { color:#f97316; font-size:30rpx; font-weight:700; }
.system-cost-result { display:flex; align-items:center; justify-content:space-between; gap:20rpx; margin-top:18rpx; padding:22rpx; border-radius:16rpx; background:linear-gradient(135deg,#eff6ff,#f0fdf4); color:#334155; font-size:25rpx; }
.system-cost-result > view { display:flex; flex-direction:column; gap:6rpx; }
.system-cost-note { color:#94a3b8; font-size:20rpx; }
.system-cost-value { color:#0f172a; font-size:38rpx; font-weight:750; }

.asset-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}
.asset-title {
    font-size: 34rpx;
    font-weight: 600;
    color: #0f172a;
    flex: 1;
}
.info-grid {
    margin-top: 20rpx;
    display: flex;
    flex-direction: column;
    gap: 14rpx;
}
.info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .info-label { font-size: 24rpx; color: #94a3b8; }
    .info-value { font-size: 26rpx; color: #334155; max-width: 70%; text-align: right; }
}

.blocked-card {
    display: flex;
    align-items: center;
    gap: 14rpx;

    .blocked-text { font-size: 28rpx; color: #64748b; }
}

.current-cost {
    display: flex;
    align-items: baseline;
    justify-content: space-between;

    .cc-label { font-size: 26rpx; color: #94a3b8; }
    .cc-value { font-size: 40rpx; font-weight: 700; color: #0f172a; }
}
.cost-breakdown {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12rpx;
    margin-top: 22rpx;
}
.cost-breakdown__item {
    display: flex;
    flex-direction: column;
    gap: 6rpx;
    padding: 16rpx;
    border-radius: 12rpx;
    background: #f8fafc;
}
.cost-breakdown__label { color: #94a3b8; font-size: 21rpx; }
.cost-breakdown__value {
    color: #334155;
    font-size: 25rpx;
    font-weight: 600;
    &.muted { color: #cbd5e1; font-weight: 400; }
}
.divider {
    height: 1rpx;
    background: #f1f5f9;
    margin: 24rpx 0;
}
.field-label {
    font-size: 26rpx;
    color: #64748b;
    margin-bottom: 18rpx;
}
.cost-input-row {
    display: flex;
    align-items: center;
    border-bottom: 2rpx solid #e2e8f0;
    padding-bottom: 12rpx;

    .cost-prefix { font-size: 36rpx; color: #0f172a; margin-right: 12rpx; }
}
.delta-preview {
    margin-top: 18rpx;
    font-size: 26rpx;
    font-weight: 600;

    &.up { color: #dc2626; }
    &.down { color: #16a34a; }
}

.reason-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}
.chip {
    padding: 12rpx 28rpx;
    border-radius: 999rpx;
    background: #f1f5f9;
    color: #475569;
    font-size: 26rpx;

    &.active {
        background: #eef2ff;
        color: #3b6ef5;
        font-weight: 500;
    }
}

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24rpx;

    .switch-text { flex: 1; }
    .switch-title { font-size: 28rpx; color: #0f172a; }
    .switch-sub {
        display: block;
        margin-top: 8rpx;
        font-size: 22rpx;
        color: #94a3b8;
        line-height: 1.5;
    }
}
.outbound-note {
    display: flex;
    align-items: center;
    gap: 12rpx;
    font-size: 24rpx;
    color: #94a3b8;
}

.history-item {
    padding: 18rpx 0;
    border-bottom: 1rpx solid #f1f5f9;

    &:last-child { border-bottom: none; padding-bottom: 0; }
}
.history-line {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .history-cost { font-size: 26rpx; color: #0f172a; font-weight: 500; }
    .history-time { font-size: 22rpx; color: #94a3b8; }
}
.history-remark {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #94a3b8;
}
.history-type { color: #3b6ef5; }

.backfill-note {
    display: flex;
    align-items: flex-start;
    gap: 12rpx;
    padding: 24rpx 28rpx;
    background: #eef2ff;
    border-radius: 16rpx;

    .bf-text { flex: 1; font-size: 24rpx; color: #5566aa; line-height: 1.6; }
}

.footer-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 20rpx 24rpx calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -8rpx 28rpx rgba(15, 23, 42, 0.08);
}
</style>
