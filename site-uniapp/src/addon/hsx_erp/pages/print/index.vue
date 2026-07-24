<template>
    <view class="erp-page print-page">
        <view class="hero-card">
            <view class="hero-icon"><u-icon name="printer" color="#2563eb" size="25" /></view>
            <view class="hero-main">
                <text class="hero-title">移动打印台</text>
                <text class="hero-sub">云打印自动发送；蓝牙任务由当前手机连接设备后完成</text>
            </view>
            <view class="refresh-button" @click="loadAll"><u-icon name="reload" color="#64748b" size="20" /></view>
        </view>

        <view class="summary-grid">
            <view class="summary-item"><text class="summary-value">{{ printers.length }}</text><text class="summary-label">打印设备</text></view>
            <view class="summary-item"><text class="summary-value orange">{{ waitingJobs.length }}</text><text class="summary-label">待蓝牙打印</text></view>
            <view class="summary-item"><text class="summary-value red">{{ failedCount }}</text><text class="summary-label">失败任务</text></view>
        </view>

        <view class="tab-wrap">
            <u-tabs :list="tabs" :current="tabIndex" lineColor="#2563eb" :activeStyle="tabActiveStyle" :inactiveStyle="tabInactiveStyle" @change="changeTab" />
        </view>

        <view v-if="tabIndex === 0" class="content-wrap">
            <view v-if="waitingJobs.length" class="task-list">
                <view v-for="job in waitingJobs" :key="job.id" class="task-card">
                    <view class="task-head">
                        <view><text class="task-title">{{ job.scene_name || '蓝牙打印任务' }}</text><text class="task-no">{{ job.biz_no || job.job_no }}</text></view>
                        <u-tag text="待手机打印" type="warning" plain size="mini" />
                    </view>
                    <view class="task-meta">
                        <view><u-icon name="print" color="#94a3b8" size="15" /><text class="recycle recycle-printer"></text></view>
                        <view><u-icon name="clock" color="#94a3b8" size="15" /><text>{{ formatTime(job.create_at) }}</text></view>
                    </view>
                    <view class="task-actions"><view class="button-wrap"><u-button type="primary" plain text="选择蓝牙设备打印" @click="chooseDevice(job)" /></view></view>
                </view>
            </view>
            <u-empty v-else mode="list" text="暂无待蓝牙打印任务" marginTop="110" />
        </view>

        <view v-else-if="tabIndex === 1" class="content-wrap device-list">
            <view v-for="printer in printers" :key="printer.id" class="printer-card">
                <view class="printer-icon" :class="printer.connection_mode"><u-icon name="printer" :color="printer.connection_mode === 'bluetooth' ? '#7c3aed' : '#2563eb'" size="23" /></view>
                <view class="printer-main"><text class="printer-name">{{ printer.printer_name }}</text><text class="printer-meta">{{ providerName(printer.driver) }} · {{ printer.print_type === 'label' ? '标签' : '小票' }} · {{ printer.paper_width }}mm</text></view>
                <u-tag :text="printer.status ? '启用' : '停用'" :type="printer.status ? 'success' : 'info'" plain size="mini" />
                <view class="printer-test" @click="testPrinter(printer)"><u-icon name="play-right" color="#2563eb" size="16" /><text>试打</text></view>
            </view>
            <u-empty v-if="!printers.length" mode="list" text="请先在 PC 打印中心添加设备" />
        </view>

        <view v-else class="content-wrap task-list">
            <view v-for="job in jobs" :key="job.id" class="task-card compact">
                <view class="task-head"><view><text class="task-title">{{ job.scene_name }}</text><text class="task-no">{{ job.job_no }}</text></view><u-tag :text="statusName(job.status)" :type="statusType(job.status)" plain size="mini" /></view>
                <text v-if="job.error_message" class="error-text">{{ job.error_message }}</text>
                <view class="task-meta"><view><u-icon name="clock" color="#94a3b8" size="15" /><text>{{ formatTime(job.create_at) }}</text></view></view>
                <view v-if="job.status === 'failed'" class="task-actions"><view class="button-wrap retry"><u-button type="primary" plain text="重新发送" @click="retryJob(job)" /></view></view>
            </view>
            <u-empty v-if="!jobs.length" mode="list" text="暂无打印记录" />
        </view>

        <u-popup :show="deviceVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="deviceVisible = false">
            <view class="device-sheet">
                <view class="sheet-head"><view><text class="sheet-title">选择蓝牙打印机</text><text class="sheet-sub">请保持打印机开机并靠近手机</text></view><u-icon name="close" color="#94a3b8" size="20" @click="deviceVisible = false" /></view>
                <view class="scan-action" @click="scanDevices"><u-icon name="reload" color="#2563eb" size="18" /><text>{{ discovering ? '正在搜索附近设备…' : '重新搜索附近设备' }}</text></view>
                <scroll-view scroll-y class="device-scroll">
                    <view v-for="device in devices" :key="device.deviceId" class="ble-device" @click="printWithDevice(device)">
                        <view class="ble-symbol"><u-icon name="wifi" color="#7c3aed" size="20" /></view>
                        <view class="ble-main"><text class="ble-name">{{ device.name }}</text><text class="ble-id">{{ device.deviceId }}</text></view>
                        <text class="ble-rssi">{{ device.RSSI || '' }}</text><u-icon name="arrow-right" color="#cbd5e1" size="17" />
                    </view>
                    <u-empty v-if="!discovering && !devices.length" mode="search" text="未发现打印机，请重试" />
                </scroll-view>
            </view>
        </u-popup>
        <u-loading-page :loading="loading || connecting" :loadingText="connecting ? '正在连接并发送打印数据' : '加载中'" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { completeMobileErpPrintJob, getMobileErpPrinters, getMobileErpPrintJobs, getMobileErpPrintMeta, retryMobileErpPrintJob, testMobileErpPrinter } from '@/addon/hsx_erp/api/erp'
import { useErpBluetoothPrinter, type ErpBluetoothDevice } from '@/addon/hsx_erp/hooks/useErpBluetoothPrinter'

const loading = ref(false)
const tabIndex = ref(0)
const printers = ref<any[]>([])
const jobs = ref<any[]>([])
const meta = ref<any>({ providers: [], status_map: {} })
const activeJob = ref<any>(null)
const deviceVisible = ref(false)
const { discovering, connecting, devices, startDiscovery, send } = useErpBluetoothPrinter()
const tabs = [{ name: '待打印' }, { name: '打印设备' }, { name: '任务记录' }]
const tabActiveStyle = { color: '#2563eb', fontWeight: '650', fontSize: '28rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '28rpx' }
const waitingJobs = computed(() => jobs.value.filter((job) => job.status === 'waiting_client'))
const failedCount = computed(() => jobs.value.filter((job) => job.status === 'failed').length)
const providerName = (key: string) => meta.value.providers?.find((item: any) => item.key === key)?.name || key
const statusName = (status: string) => meta.value.status_map?.[status] || status
const statusType = (status: string) => ({ success: 'success', failed: 'error', waiting_client: 'warning', sending: 'primary' } as any)[status] || 'info'
const formatTime = (value: number) => value ? new Date(value * 1000).toLocaleString() : '-'

async function loadAll() {
    loading.value = true
    try {
        const [metaResult, printerResult, jobResult]: any[] = await Promise.all([
            getMobileErpPrintMeta(),
            getMobileErpPrinters(),
            getMobileErpPrintJobs({ page: 1, limit: 50 }),
        ])
        meta.value = metaResult?.data || {}
        printers.value = printerResult?.data || []
        const jobData = jobResult?.data || {}
        jobs.value = jobData?.data || jobData?.list || []
    } finally { loading.value = false }
}

function changeTab(value: any) { tabIndex.value = Number(value?.index ?? value ?? 0) }
async function chooseDevice(job: any) { activeJob.value = job; deviceVisible.value = true; await scanDevices() }
async function scanDevices() {
    try { await startDiscovery() } catch (error: any) { uni.showToast({ title: error?.errMsg || error?.message || '蓝牙搜索失败', icon: 'none' }) }
}
async function printWithDevice(device: ErpBluetoothDevice) {
    const job = activeJob.value
    if (!job) return
    let payload: any = {}
    try { payload = JSON.parse(String(job.client_payload || '{}')) } catch (_) {}
    try {
        await send(device, payload)
        await completeMobileErpPrintJob(Number(job.id), true)
        deviceVisible.value = false
        uni.showToast({ title: '打印数据已发送', icon: 'success' })
        await loadAll()
    } catch (error: any) {
        const message = error?.errMsg || error?.message || '蓝牙打印失败'
        await completeMobileErpPrintJob(Number(job.id), false, message).catch(() => undefined)
        uni.showToast({ title: message, icon: 'none' })
    }
}
async function testPrinter(printer: any) {
    try {
        const result: any = await testMobileErpPrinter(Number(printer.id))
        if (result?.data?.status === 'waiting_client') uni.showToast({ title: '测试任务已进入待打印', icon: 'none' })
        else uni.showToast({ title: '测试任务已发送', icon: 'success' })
        await loadAll()
        if (result?.data?.status === 'waiting_client') tabIndex.value = 0
    } catch (_) {}
}
async function retryJob(job: any) {
    try {
        const result: any = await retryMobileErpPrintJob(Number(job.id))
        uni.showToast({ title: result?.data?.status === 'waiting_client' ? '已恢复到待打印' : '任务已重新发送', icon: 'none' })
        if (result?.data?.status === 'waiting_client') tabIndex.value = 0
        await loadAll()
    } catch (_) {}
}

onShow(loadAll)
</script>

<style lang="scss" scoped>
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.print-page{padding:20rpx 0 80rpx}.hero-card{display:flex;align-items:center;gap:18rpx;margin:0 24rpx;padding:26rpx;border-radius:24rpx;background:linear-gradient(135deg,#fff,#f4f7ff);box-shadow:0 6rpx 24rpx rgba(30,64,175,.07)}.hero-icon{display:flex;width:78rpx;height:78rpx;align-items:center;justify-content:center;border-radius:22rpx;background:#eaf1ff}.hero-main{min-width:0;flex:1}.hero-title,.hero-sub{display:block}.hero-title{font-size:32rpx;font-weight:700;color:#0f172a}.hero-sub{margin-top:7rpx;font-size:23rpx;line-height:1.5;color:#64748b}.refresh-button{padding:12rpx}.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14rpx;margin:18rpx 24rpx}.summary-item{padding:22rpx 12rpx;text-align:center;border-radius:20rpx;background:#fff}.summary-value,.summary-label{display:block}.summary-value{font-size:36rpx;font-weight:750;color:#2563eb}.summary-value.orange{color:#ea580c}.summary-value.red{color:#dc2626}.summary-label{margin-top:5rpx;font-size:22rpx;color:#94a3b8}.tab-wrap{padding:0 18rpx;background:#fff;border-bottom:1rpx solid #eef2f7}.content-wrap{padding:20rpx 24rpx}.task-list,.device-list{display:flex;flex-direction:column;gap:18rpx}.task-card,.printer-card{border-radius:24rpx;background:#fff;box-shadow:0 3rpx 14rpx rgba(15,23,42,.04)}.task-card{padding:24rpx}.task-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18rpx}.task-title,.task-no{display:block}.task-title{font-size:29rpx;font-weight:650;color:#172033}.task-no{margin-top:6rpx;font-size:22rpx;color:#94a3b8}.task-meta{display:flex;gap:25rpx;margin-top:18rpx;padding-top:16rpx;border-top:1rpx solid #eef2f7}.task-meta>view{display:flex;align-items:center;gap:7rpx;font-size:22rpx;color:#64748b}.task-actions{display:flex;justify-content:flex-end;margin-top:18rpx}.button-wrap{width:300rpx}.button-wrap.retry{width:210rpx}.error-text{display:block;margin-top:14rpx;padding:13rpx 15rpx;border-radius:12rpx;background:#fff2f2;font-size:22rpx;line-height:1.45;color:#dc2626}.printer-card{display:grid;grid-template-columns:72rpx 1fr auto;align-items:center;gap:16rpx;padding:22rpx}.printer-icon{display:flex;width:68rpx;height:68rpx;align-items:center;justify-content:center;border-radius:18rpx;background:#ebf2ff}.printer-icon.bluetooth{background:#f1edff}.printer-name,.printer-meta{display:block}.printer-name{font-size:28rpx;font-weight:650;color:#172033}.printer-meta{margin-top:5rpx;font-size:22rpx;color:#64748b}.printer-test{grid-column:2/4;display:flex;align-items:center;justify-content:flex-end;gap:6rpx;padding-top:13rpx;border-top:1rpx solid #eef2f7;font-size:23rpx;color:#2563eb}.device-sheet{min-height:680rpx;padding:30rpx 26rpx calc(26rpx + env(safe-area-inset-bottom))}.sheet-head{display:flex;align-items:flex-start;justify-content:space-between}.sheet-title,.sheet-sub{display:block}.sheet-title{font-size:34rpx;font-weight:700;color:#0f172a}.sheet-sub{margin-top:8rpx;font-size:23rpx;color:#94a3b8}.scan-action{display:flex;align-items:center;justify-content:center;gap:9rpx;margin:24rpx 0;padding:20rpx;border-radius:18rpx;background:#f1f5ff;font-size:25rpx;color:#2563eb}.device-scroll{height:480rpx}.ble-device{display:flex;align-items:center;gap:15rpx;padding:21rpx 8rpx;border-bottom:1rpx solid #eef2f7}.ble-symbol{display:flex;width:64rpx;height:64rpx;align-items:center;justify-content:center;border-radius:17rpx;background:#f3efff}.ble-main{min-width:0;flex:1}.ble-name,.ble-id{display:block}.ble-name{font-size:27rpx;font-weight:600;color:#172033}.ble-id{margin-top:4rpx;overflow:hidden;font-size:20rpx;color:#94a3b8;text-overflow:ellipsis;white-space:nowrap}.ble-rssi{font-size:21rpx;color:#94a3b8}
</style>
