<template>
    <view class="erp-page detail-page">
        <ErpPageHeader title="盘点详情" />

        <view class="detail-wrap">
            <view v-if="loading && !task.id" class="loading-card">
                <u-loading-icon mode="circle" text="盘点任务加载中" />
            </view>

            <template v-else>
                <ErpStocktakeSummary class="summary-card" :data="task" show-footer />

                <view v-if="task.status === 'counting'" class="form-card scan-card">
                    <view class="section-head">
                        <view>
                            <text class="section-head__title">扫码核对设备</text>
                            <text class="section-head__sub">支持 IMEI、SN 和系统资产号，重复扫码不会重复计数</text>
                        </view>
                        <view class="section-head__icon">
                            <u-icon name="scan" color="#3b6ef5" size="22" />
                        </view>
                    </view>

                    <view class="manual-scan">
                        <view class="manual-scan__input">
                            <u-input
                                v-model="scanCode"
                                border="surround"
                                clearable
                                confirmType="done"
                                placeholder="输入设备串号"
                                @confirm="submitScan(scanCode)"
                            />
                        </view>
                        <view class="manual-scan__button">
                            <u-button
                                type="primary"
                                plain
                                :loading="scanLoading"
                                text="登记"
                                @click="submitScan(scanCode)"
                            />
                        </view>
                    </view>

                    <u-button
                        type="primary"
                        icon="scan"
                        :loading="scanLoading"
                        text="扫码盘点"
                        @click="scan"
                    />
                </view>

                <view class="filter-card">
                    <u-tabs
                        :list="filterTabs"
                        :current="filterIndex"
                        lineWidth="24"
                        lineHeight="4"
                        :activeStyle="tabActiveStyle"
                        :inactiveStyle="tabInactiveStyle"
                        @change="changeResult"
                    />
                </view>

                <view class="section-title">设备明细（{{ items.length }}）</view>
                <view v-for="row in items" :key="row.id" class="erp-card item-card">
                    <view class="erp-card__head item-card__head">
                        <view class="item-card__main">
                            <text class="card-title">{{ row.model || '未填写设备名称' }}</text>
                            <text class="card-meta">IMEI {{ row.imei || '-' }}</text>
                        </view>
                        <u-tag
                            :text="resultLabel(row)"
                            :type="row.result_meta?.type || 'info'"
                            plain
                            plainFill
                            size="mini"
                        />
                    </view>

                    <view v-if="row.sn" class="identity-row">
                        <text class="identity-row__label">SN</text>
                        <text class="identity-row__value">{{ row.sn }}</text>
                    </view>

                    <view class="position-list">
                        <view class="position-row">
                            <u-icon name="map" color="#94a3b8" size="14" />
                            <text class="position-row__label">账面位置</text>
                            <text class="position-row__value">{{ expectedPosition(row) }}</text>
                        </view>
                        <view v-if="row.scanned_at" class="position-row actual">
                            <u-icon name="checkmark-circle" color="#16a34a" size="14" />
                            <text class="position-row__label">实盘位置</text>
                            <text class="position-row__value">{{ actualPosition(row) }}</text>
                        </view>
                    </view>

                    <view class="erp-card__foot item-card__foot">
                        <view class="operator-line">
                            <u-icon name="account" color="#94a3b8" size="13" />
                            <text>{{ row.scanner_name ? `扫码人 ${row.scanner_name}` : '尚未扫码' }}</text>
                        </view>
                        <u-tag v-if="row.resolution_status === 'resolved'" text="差异已处理" type="success" plain size="mini" />
                        <view v-else-if="canResolve(row)" class="resolve-button">
                            <u-button type="primary" size="small" plain text="处理差异" @click="openResolve(row)" />
                        </view>
                    </view>
                </view>

                <u-empty v-if="!loading && !items.length" mode="list" text="暂无对应设备" />
            </template>
        </view>

        <view v-if="task.id" class="float-bar bottom-actions">
            <template v-if="task.status === 'counting'">
                <view class="bottom-action secondary">
                    <u-button type="error" plain text="取消盘点" @click="openCancel" />
                </view>
                <view class="bottom-action primary">
                    <u-button type="primary" :loading="actionLoading" text="提交盘点" @click="submitTask" />
                </view>
            </template>
            <view v-else-if="task.status === 'pending_review'" class="bottom-action full">
                <u-button
                    type="primary"
                    :loading="actionLoading"
                    :disabled="Number(task.unresolved_count || 0) > 0"
                    :text="Number(task.unresolved_count || 0) > 0 ? '请先处理差异' : '确认完成盘点'"
                    @click="completeTask"
                />
            </view>
            <view v-else class="bottom-action full">
                <u-button text="返回盘点列表" @click="goBack" />
            </view>
        </view>

        <u-popup
            :show="resolveVisible"
            mode="bottom"
            round="20"
            :safe-area-inset-bottom="true"
            @close="resolveVisible = false"
        >
            <view class="sheet-body">
                <view class="sheet-head">
                    <view>
                        <text class="sheet-title">处理盘点差异</text>
                        <text class="sheet-sub">{{ resolveRow?.model || '-' }} · {{ resolveRow?.imei || resolveRow?.sn || '未填写串号' }}</text>
                    </view>
                    <u-icon name="close" size="20" color="#94a3b8" @click="resolveVisible = false" />
                </view>

                <view class="sheet-section">
                    <text class="sheet-label">处理方式</text>
                    <u-radio-group v-model="resolveForm.action" placement="column" iconPlacement="right">
                        <u-radio
                            v-for="action in resolveActions"
                            :key="action.value"
                            :name="action.value"
                            :label="action.label"
                            activeColor="#3b6ef5"
                            labelColor="#334155"
                            :labelSize="'26rpx'"
                            :customStyle="radioStyle"
                        />
                    </u-radio-group>
                </view>

                <u-cell-group v-if="resolveForm.action === 'correct_location'" :border="false" class="location-cell">
                    <u-cell title="实际库位" :label="selectedLocationName || '请选择修正后的库位'" isLink @click="locationVisible = true" />
                </u-cell-group>

                <view class="sheet-section">
                    <text class="sheet-label">复核说明</text>
                    <u-textarea
                        v-model="resolveForm.remark"
                        maxlength="200"
                        placeholder="请记录复核事实与处理原因"
                        height="130rpx"
                        :customStyle="textareaStyle"
                    />
                </view>

                <view class="sheet-submit">
                    <u-button type="primary" :loading="resolving" text="确认处理" @click="resolveItem" />
                </view>
            </view>
        </u-popup>

        <u-action-sheet
            :show="locationVisible"
            :actions="locationActions"
            title="选择实际库位"
            @select="selectLocation"
            @close="locationVisible = false"
        />

        <u-popup
            :show="cancelVisible"
            mode="bottom"
            round="20"
            :safe-area-inset-bottom="true"
            @close="cancelVisible = false"
        >
            <view class="sheet-body">
                <view class="sheet-head">
                    <view>
                        <text class="sheet-title">取消盘点任务</text>
                        <text class="sheet-sub">取消后不改库存，已扫描记录仍保留用于审计</text>
                    </view>
                    <u-icon name="close" size="20" color="#94a3b8" @click="cancelVisible = false" />
                </view>

                <view class="cancel-notice">
                    <u-icon name="info-circle" color="#dc2626" size="16" />
                    <text>这是敏感操作，请填写真实取消原因。</text>
                </view>
                <u-textarea
                    v-model="cancelRemark"
                    maxlength="200"
                    placeholder="请填写取消原因"
                    height="130rpx"
                    :customStyle="textareaStyle"
                />
                <view class="sheet-submit">
                    <u-button type="error" :loading="cancelling" text="确认取消盘点" @click="cancelTask" />
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import {
    cancelMobileStocktake,
    completeMobileStocktake,
    getMobileStocktakeInfo,
    getMobileStocktakeItems,
    resolveMobileStocktakeItem,
    scanMobileStocktake,
    submitMobileStocktake,
} from '@/addon/hsx_erp/api/erp'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpStocktakeSummary from '@/addon/hsx_erp/components/ErpStocktakeSummary.vue'
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'

const id = ref(0)
const task = ref<any>({})
const items = ref<any[]>([])
const loading = ref(false)
const result = ref('')
const scanCode = ref('')
const scanLoading = ref(false)
const actionLoading = ref(false)
const resolveVisible = ref(false)
const resolveRow = ref<any>(null)
const resolving = ref(false)
const locationVisible = ref(false)
const cancelVisible = ref(false)
const cancelRemark = ref('')
const cancelling = ref(false)

const filters = [
    { label: '全部', value: '' },
    { label: '待盘', value: 'pending' },
    { label: '正常', value: 'normal' },
    { label: '盘亏', value: 'missing' },
    { label: '盘盈', value: 'surplus' },
    { label: '异常', value: 'abnormal' },
]
const resultLabels: Record<string, string> = { pending: '待盘', normal: '正常', missing: '盘亏', surplus: '盘盈', location_mismatch: '库位不符', status_abnormal: '状态异常' }
const resultLabel = (row: any) => erpEnumLabel(row.result_meta?.label, resultLabels, erpEnumLabel(row.result, resultLabels, '盘点结果待确认'))
const filterTabs = filters.map(item => ({ name: item.label, value: item.value }))
const filterIndex = computed(() => Math.max(0, filters.findIndex(item => item.value === result.value)))
const tabActiveStyle = { color: '#2563eb', fontWeight: '650', fontSize: '25rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '25rpx' }
const radioStyle = { padding: '22rpx 0', borderBottom: '1rpx solid #eef2f7' }
const textareaStyle = { background: '#f8fafc', padding: '18rpx', borderRadius: '12rpx' }

const resolveForm = reactive<any>({ action: '', actual_location_id: 0, remark: '' })
const resolveActionMap: Record<string, Array<{ value: string; label: string }>> = {
    missing: [
        { value: 'confirm_missing', label: '确认盘亏，设备退出可售库存' },
        { value: 'keep_in_stock', label: '设备仍在库，保留库存' },
    ],
    surplus: [
        { value: 'pending_inbound', label: '转待入库登记，不自动新增库存' },
        { value: 'exclude', label: '排除，不属于本次库存' },
    ],
    location_mismatch: [
        { value: 'correct_location', label: '按实际位置修正库位' },
        { value: 'keep_current', label: '保留账面位置' },
    ],
    status_abnormal: [
        { value: 'business_review', label: '转业务人工核查' },
        { value: 'keep_current', label: '保留当前业务状态' },
    ],
}

const resolveActions = computed(() => resolveActionMap[resolveRow.value?.result] || [])
const locationActions = computed(() => (task.value.locations || []).map((item: any) => ({
    name: item.location_name,
    id: item.id,
})))
const selectedLocationName = computed(() => locationActions.value.find(
    (item: any) => Number(item.id) === Number(resolveForm.actual_location_id),
)?.name || '')

async function load() {
    if (!id.value) return
    loading.value = true
    try {
        const [infoResponse, itemResponse]: any = await Promise.all([
            getMobileStocktakeInfo(id.value),
            getMobileStocktakeItems(id.value, {
                result: result.value === 'abnormal' ? '' : result.value,
                page: 1,
                limit: 300,
            }),
        ])
        task.value = infoResponse?.data || {}
        let rows = itemResponse?.data?.data || []
        if (result.value === 'abnormal') {
            rows = rows.filter((item: any) => ['location_mismatch', 'status_abnormal'].includes(item.result))
        }
        items.value = rows
    } finally {
        loading.value = false
    }
}

function changeResult(payload: any) {
    const selected = filters[Number(payload?.index ?? payload)] || filters[0]
    result.value = selected.value
    load()
}

async function scan() {
    try {
        const code = await scanErpCode()
        await submitScan(code)
    } catch (error: any) {
        if (!String(error?.errMsg || '').includes('cancel')) {
            uni.showToast({ title: error?.message || '扫码失败', icon: 'none' })
        }
    }
}

async function submitScan(code: string) {
    const normalizedCode = String(code || '').trim()
    if (!normalizedCode || scanLoading.value) return

    scanLoading.value = true
    try {
        const response: any = await scanMobileStocktake(id.value, { code: normalizedCode })
        scanCode.value = ''
        uni.showToast({
            title: response?.data?.duplicate ? '该设备已盘点' : '盘点成功',
            icon: response?.data?.duplicate ? 'none' : 'success',
        })
        await load()
    } finally {
        scanLoading.value = false
    }
}

async function submitTask() {
    const confirmed = await confirmAction('提交后，未扫码设备将进入盘亏复核。确认提交吗？')
    if (!confirmed) return

    actionLoading.value = true
    try {
        await submitMobileStocktake(id.value, task.value.workflow_mode === 'simple')
        uni.showToast({ title: '已提交', icon: 'success' })
        await load()
    } finally {
        actionLoading.value = false
    }
}

function openResolve(row: any) {
    resolveRow.value = row
    Object.assign(resolveForm, {
        action: '',
        actual_location_id: Number(row.actual_location_id || 0),
        remark: '',
    })
    resolveVisible.value = true
}

function selectLocation(action: any) {
    resolveForm.actual_location_id = action.id
    locationVisible.value = false
}

async function resolveItem() {
    if (!resolveForm.action || !resolveForm.remark.trim()) {
        uni.showToast({ title: '请选择处理方式并填写说明', icon: 'none' })
        return
    }
    if (resolveForm.action === 'correct_location' && !resolveForm.actual_location_id) {
        uni.showToast({ title: '请选择实际库位', icon: 'none' })
        return
    }

    resolving.value = true
    try {
        await resolveMobileStocktakeItem(id.value, resolveRow.value.id, resolveForm)
        resolveVisible.value = false
        uni.showToast({ title: '差异已处理', icon: 'success' })
        await load()
    } finally {
        resolving.value = false
    }
}

async function completeTask() {
    const confirmed = await confirmAction('完成后将正式写入盘亏状态或库位修正，确认继续吗？')
    if (!confirmed) return

    actionLoading.value = true
    try {
        await completeMobileStocktake(id.value)
        uni.showToast({ title: '盘点已完成', icon: 'success' })
        await load()
    } finally {
        actionLoading.value = false
    }
}

function openCancel() {
    cancelRemark.value = ''
    cancelVisible.value = true
}

async function cancelTask() {
    if (!cancelRemark.value.trim()) {
        uni.showToast({ title: '请填写取消原因', icon: 'none' })
        return
    }

    cancelling.value = true
    try {
        await cancelMobileStocktake(id.value, cancelRemark.value.trim())
        cancelVisible.value = false
        uni.showToast({ title: '盘点已取消', icon: 'success' })
        await load()
    } finally {
        cancelling.value = false
    }
}

function confirmAction(content: string) {
    return new Promise<boolean>((resolve) => {
        uni.showModal({
            title: '操作确认',
            content,
            confirmText: '确认',
            success: (response: any) => resolve(Boolean(response.confirm)),
        })
    })
}

function expectedPosition(row: any) {
    return `${row.expected_warehouse_name || '-'}${row.expected_location_name ? ` / ${row.expected_location_name}` : ''}`
}

function actualPosition(row: any) {
    return `${row.actual_warehouse_name || '-'}${row.actual_location_name ? ` / ${row.actual_location_name}` : ''}`
}

function canResolve(row: any) {
    return task.value.status === 'pending_review' && !['normal', 'pending'].includes(row.result)
}

function goBack() {
    uni.navigateBack()
}

onLoad((query: any) => {
    id.value = Number(query?.id || 0)
})
onShow(load)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.detail-page {
    padding-bottom: 150rpx;
}

.summary-card {
    margin: 4rpx 24rpx 20rpx;
}

.loading-card {
    margin: 24rpx;
    padding: 90rpx 20rpx;
    border-radius: 24rpx;
    background: #fff;
}

.scan-card {
    padding: 24rpx 28rpx;
}

.section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.section-head__title,
.section-head__sub {
    display: block;
}

.section-head__title {
    color: #0f172a;
    font-size: 29rpx;
    font-weight: 650;
}

.section-head__sub {
    margin-top: 6rpx;
    color: #94a3b8;
    font-size: 21rpx;
    line-height: 1.5;
}

.section-head__icon {
    width: 64rpx;
    height: 64rpx;
    flex: none;
    border-radius: 18rpx;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.manual-scan {
    display: flex;
    align-items: stretch;
    gap: 12rpx;
    margin: 22rpx 0 14rpx;
}

.manual-scan__input {
    min-width: 0;
    flex: 1;
}

.manual-scan__button {
    width: 130rpx;
}

.filter-card {
    margin: 0 24rpx 16rpx;
    border-radius: 20rpx;
    background: #fff;
    overflow: hidden;
}

.item-card {
    padding: 24rpx 28rpx;
}

.item-card__head {
    align-items: flex-start;
}

.item-card__main {
    min-width: 0;
}

.identity-row,
.position-row,
.operator-line {
    display: flex;
    align-items: center;
}

.identity-row {
    gap: 16rpx;
    margin-top: 12rpx;
    color: #64748b;
    font-size: 22rpx;
}

.identity-row__label {
    color: #94a3b8;
}

.identity-row__value {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.position-list {
    margin-top: 18rpx;
    padding: 4rpx 18rpx;
    border-radius: 14rpx;
    background: #f8fafc;
}

.position-row {
    min-height: 66rpx;
    gap: 10rpx;
    color: #64748b;
    font-size: 22rpx;
}

.position-row + .position-row {
    border-top: 1rpx solid #eef2f7;
}

.position-row__label {
    width: 112rpx;
    color: #94a3b8;
}

.position-row__value {
    min-width: 0;
    flex: 1;
    color: #334155;
    text-align: right;
}

.item-card__foot {
    justify-content: space-between;
}

.operator-line {
    gap: 7rpx;
    color: #94a3b8;
    font-size: 21rpx;
}

.resolve-button {
    min-width: 150rpx;
}

.bottom-actions {
    align-items: center;
}

.bottom-action {
    min-width: 0;
}

.bottom-action.secondary {
    flex: 1;
}

.bottom-action.primary {
    flex: 2;
}

.bottom-action.full {
    flex: 1;
}

.sheet-body {
    padding: 30rpx 28rpx 24rpx;
}

.sheet-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 22rpx;
}

.sheet-title,
.sheet-sub {
    display: block;
}

.sheet-title {
    color: #0f172a;
    font-size: 32rpx;
    font-weight: 700;
}

.sheet-sub {
    margin-top: 7rpx;
    color: #64748b;
    font-size: 22rpx;
}

.sheet-section {
    margin-top: 24rpx;
}

.sheet-label {
    display: block;
    margin-bottom: 12rpx;
    color: #334155;
    font-size: 25rpx;
    font-weight: 600;
}

.location-cell {
    display: block;
    margin-top: 20rpx;
    border: 1rpx solid #eef2f7;
    border-radius: 16rpx;
    overflow: hidden;
}

.sheet-submit {
    margin-top: 24rpx;
}

.cancel-notice {
    display: flex;
    align-items: flex-start;
    gap: 9rpx;
    margin: 22rpx 0 18rpx;
    padding: 16rpx 18rpx;
    border-radius: 12rpx;
    background: #fef2f2;
    color: #991b1b;
    font-size: 22rpx;
    line-height: 1.5;
}
</style>
