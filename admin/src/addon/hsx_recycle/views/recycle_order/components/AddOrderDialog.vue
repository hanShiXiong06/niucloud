<template>
    <el-dialog
        v-model="dialogVisible"
        :width="isMobile ? 'calc(100vw - 24px)' : '1120px'"
        top="4vh"
        class="add-order-dialog hsx-premium-overlay"
        :destroy-on-close="true"
        :close-on-click-modal="false"
        @closed="handleClosed"
    >
        <template #header>
            <div class="dialog-heading">
                <div class="dialog-heading__icon">
                    <el-icon><DocumentAdd /></el-icon>
                </div>
                <div>
                    <div class="dialog-heading__title">代客户下单</div>
                    <div class="dialog-heading__desc">登记客户与到货信息，保存设备后完成签收</div>
                </div>
            </div>
        </template>

        <el-form ref="formRef" :model="form" label-position="top" class="order-workbench">
            <section class="order-section order-section--base">
                <div class="section-heading">
                    <div>
                        <div class="section-heading__title">客户与收货信息</div>
                        <div class="section-heading__desc">创建首台设备时会同步生成草稿订单</div>
                    </div>
                    <el-tag v-if="draftOrder.id" type="info" effect="plain" round>
                        草稿 {{ draftOrder.order_no }}
                    </el-tag>
                </div>

                <div class="base-grid">
                    <div class="base-field base-field--member">
                        <div class="field-label"><span class="is-required">*</span>客户</div>
                        <MemberSelect
                            v-model="form.member_id"
                            :disabled="!!draftOrder.id"
                            placeholder="输入手机号、昵称或会员编号搜索"
                            @change="handleMemberChange"
                        />

                        <div v-if="selectedMember" class="selected-member">
                            <el-avatar :size="38" :src="selectedMember.headimg ? img(selectedMember.headimg) : ''">
                                {{ memberInitial }}
                            </el-avatar>
                            <div class="selected-member__main">
                                <div class="selected-member__name">
                                    {{ selectedMember.nickname || selectedMember.username || '未设置昵称' }}
                                    <el-tag v-if="selectedMember.member_level_name" size="small" type="info" effect="plain">
                                        {{ selectedMember.member_level_name }}
                                    </el-tag>
                                </div>
                                <div class="selected-member__meta">
                                    <span>{{ selectedMember.mobile || '未绑定手机' }}</span>
                                    <span v-if="selectedMember.member_no">编号 {{ selectedMember.member_no }}</span>
                                </div>
                            </div>
                            <el-button
                                v-if="!draftOrder.id"
                                link
                                type="primary"
                                @click="clearSelectedMember"
                            >更换客户</el-button>
                        </div>
                    </div>

                    <div class="base-field">
                        <div class="field-label">到货方式</div>
                        <el-radio-group v-model="form.delivery_type" :disabled="!!draftOrder.id" class="delivery-switch">
                            <el-radio-button label="1">
                                <el-icon><Van /></el-icon>
                                快递到店
                            </el-radio-button>
                            <el-radio-button label="2">
                                <el-icon><Shop /></el-icon>
                                客户到店
                            </el-radio-button>
                        </el-radio-group>
                    </div>

                    <div v-if="form.delivery_type === '1'" class="base-field base-field--express">
                        <div class="field-label"><span class="is-required">*</span>快递单号</div>
                        <el-input
                            ref="expressInput"
                            v-model.trim="form.express_no"
                            placeholder="输入或使用扫码枪扫描快递单号"
                            clearable
                            :disabled="!!draftOrder.id"
                            @keydown.enter.prevent="handleScannerInput"
                            @focus="handleInputFocus"
                            @blur="handleInputBlur"
                        >
                            <template #prefix><el-icon><Tickets /></el-icon></template>
                            <template #append>
                                <el-button :disabled="!!draftOrder.id" @click="activateScanMode">
                                    <el-icon><Aim /></el-icon>
                                    扫码
                                </el-button>
                            </template>
                        </el-input>
                        <div class="field-help" :class="{ 'is-active': isScanMode }">
                            <el-icon v-if="isScanMode"><Loading class="is-loading" /></el-icon>
                            {{ isScanMode ? '等待扫码，扫描完成后按回车确认' : '支持扫码枪直接录入' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="order-section order-section--devices">
                <DeviceEntryList
                    :devices="form.devices"
                    :order-id="draftOrder.id || ''"
                    :ensure-order="ensureDraftOrder"
                />
            </section>
        </el-form>

        <template #footer>
            <div class="dialog-footer" :class="{ 'is-mobile': isMobile }">
                <div class="dialog-footer__status">
                    <el-icon :class="{ 'is-warning': pendingDeviceCount, 'is-success': !pendingDeviceCount && savedDeviceCount }">
                        <component :is="savedDeviceCount && !pendingDeviceCount ? CircleCheck : InfoFilled" />
                    </el-icon>
                    <span v-if="pendingDeviceCount">还有 {{ pendingDeviceCount }} 台已录入设备尚未保存</span>
                    <span v-else-if="savedDeviceCount">已保存 {{ savedDeviceCount }} 台设备，可以完成签收</span>
                    <span v-else>请先选择客户并保存至少一台设备</span>
                </div>
                <div class="dialog-footer__actions">
                    <el-button @click="closeDialog">
                        {{ draftOrder.id ? '暂存并关闭' : '取消' }}
                    </el-button>
                    <el-button
                        type="primary"
                        :loading="loading"
                        :disabled="savedDeviceCount === 0"
                        @click="handleConfirm"
                    >
                        确认签收{{ savedDeviceCount ? `（${savedDeviceCount} 台）` : '' }}
                    </el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    Aim,
    CircleCheck,
    DocumentAdd,
    InfoFilled,
    Loading,
    Shop,
    Tickets,
    Van
} from '@element-plus/icons-vue'
import { createRecycleOrder, updateRecycleOrder } from '@/addon/hsx_recycle/api/recycle_order'
import DeviceEntryList from '@/addon/hsx_recycle/components/device-entry/DeviceEntryList.vue'
import MemberSelect from '@/addon/hsx_recycle/components/member-select/index.vue'
import { normalizeDevice } from '@/addon/hsx_recycle/components/device-entry/deviceUtil'
import type { DeviceEntryRow } from '@/addon/hsx_recycle/components/device-entry/types'
import { img } from '@/utils/common'

interface Member {
    member_id: string | number
    member_no?: string
    nickname?: string
    username?: string
    mobile?: string
    headimg?: string
    member_level_name?: string
}

const props = defineProps({
    visible: { type: Boolean, default: false }
})
const emit = defineEmits(['update:visible', 'success', 'closed'])

const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
})
const loading = ref(false)
const formRef = ref()
const form = ref({
    member_id: '' as string | number,
    delivery_type: '1',
    express_no: '',
    devices: [
        { imei: '', model: '', initial_price: 0, summary_fields: [], summary_values: {} }
    ] as DeviceEntryRow[]
})
const draftOrder = ref<{ id: number | string; order_no: string }>({ id: '', order_no: '' })
const selectedMember = ref<Member | null>(null)
const isMobile = ref(false)

const expressInput = ref<any>(null)
const isScanMode = ref(false)
const scanTimer = ref<ReturnType<typeof window.setTimeout> | null>(null)

const savedDeviceCount = computed(() => form.value.devices.filter(device => device.saved && device.id).length)
const pendingDeviceCount = computed(() => form.value.devices.filter(device => {
    return !device.saved && Boolean(String(device.imei || '').trim() || String(device.model || '').trim())
}).length)
const memberInitial = computed(() => {
    const name = selectedMember.value?.nickname || selectedMember.value?.username || '客户'
    return String(name).slice(0, 1)
})

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

const closeDialog = () => {
    emit('update:visible', false)
}

const handleMemberChange = (memberId: string | number | null, member: Member | null) => {
    form.value.member_id = memberId || ''
    selectedMember.value = member
}

const clearSelectedMember = () => {
    form.value.member_id = ''
    selectedMember.value = null
}

const activateScanMode = () => {
    const input = expressInput.value?.input || expressInput.value?.$el?.querySelector('input')
    input?.focus()
    isScanMode.value = true
    ElMessage.info('请扫描快递面单条码')
}

const handleInputFocus = () => { isScanMode.value = true }
const handleInputBlur = () => {
    if (scanTimer.value) window.clearTimeout(scanTimer.value)
    scanTimer.value = window.setTimeout(() => { isScanMode.value = false }, 120)
}
const handleScannerInput = () => {
    if (!form.value.express_no) return
    ElMessage.success('快递单号已录入')
    isScanMode.value = false
}

onMounted(() => {
    updateResponsiveState()
    window.addEventListener('resize', updateResponsiveState)
})
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateResponsiveState)
    if (scanTimer.value) window.clearTimeout(scanTimer.value)
})

const validateBaseOrder = (): string => {
    if (!form.value.member_id) return '请先选择客户'
    if (form.value.delivery_type === '1' && !form.value.express_no.trim()) return '请填写快递单号'
    return ''
}

const ensureDraftOrder = async (): Promise<number | string> => {
    if (draftOrder.value.id) return draftOrder.value.id
    const errorMessage = validateBaseOrder()
    if (errorMessage) {
        ElMessage.warning(errorMessage)
        throw new Error(errorMessage)
    }
    const res = await createRecycleOrder({
        member_id: form.value.member_id,
        delivery_type: form.value.delivery_type,
        express_no: form.value.delivery_type === '1' ? form.value.express_no : '',
        count: 0,
        order_source: 'agent',
        sign_after_create: false,
        draft_device_entry: true,
        devices: []
    })
    if (res.code !== 1) throw new Error(res.message || '创建草稿订单失败')
    draftOrder.value = { id: res.data.id, order_no: res.data.order_no }
    ElMessage.success('草稿订单已创建，客户与到货信息已锁定')
    return draftOrder.value.id
}

const handleConfirm = async () => {
    if (!draftOrder.value.id) {
        ElMessage.warning('请先保存至少一台设备')
        return
    }
    const savedDevices = form.value.devices.filter(device => device.saved && device.id)
    if (!savedDevices.length) {
        ElMessage.warning('请先保存至少一台设备')
        return
    }
    const pendingDevice = form.value.devices.find(device => (device.imei || device.model) && !device.saved)
    if (pendingDevice) {
        ElMessage.warning('还有已录入但未保存的设备，请先保存或删除后再签收')
        return
    }

    try {
        await ElMessageBox.confirm(
            `确认签收 ${savedDevices.length} 台设备？签收后订单将进入后续质检流程。`,
            '确认完成签收',
            { confirmButtonText: '确认签收', cancelButtonText: '继续检查', type: 'warning' }
        )
        loading.value = true
        const res = await updateRecycleOrder(Number(draftOrder.value.id), {
            action: 'order_sign',
            devices: savedDevices.map(device => ({ id: device.id, ...normalizeDevice(device) }))
        })
        if (res.code !== 1) throw new Error(res.message || '签收失败')
        ElMessage.success(`已签收 ${savedDevices.length} 台设备`)
        closeDialog()
        emit('success')
    } catch (error: any) {
        if (error === 'cancel' || error === 'close') return
        console.error('订单签收失败:', error)
        ElMessage.error(error.message || '订单签收失败')
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    form.value = {
        member_id: '',
        delivery_type: '1',
        express_no: '',
        devices: [{ imei: '', model: '', initial_price: 0, summary_fields: [], summary_values: {} }]
    }
    draftOrder.value = { id: '', order_no: '' }
    selectedMember.value = null
    isScanMode.value = false
}

const handleClosed = () => {
    resetForm()
    emit('closed')
}
</script>

<style lang="scss" scoped>
.dialog-heading {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dialog-heading__icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--el-color-primary);
    background-color: var(--el-color-primary-light-9);
    font-size: 20px;
}

.dialog-heading__title {
    color: var(--el-text-color-primary);
    font-size: 17px;
    font-weight: 600;
    line-height: 24px;
}

.dialog-heading__desc,
.section-heading__desc {
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 18px;
}

.order-workbench {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
}

.order-section {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;
    background-color: var(--el-bg-color);
}

.order-section--base {
    padding: 16px 18px;
}

.order-section--devices {
    min-height: 220px;
    padding: 14px 16px 12px;
}

.section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.section-heading__title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    font-weight: 600;
    line-height: 22px;
}

.base-grid {
    display: grid;
    grid-template-columns: minmax(300px, 1.4fr) minmax(240px, 0.8fr) minmax(300px, 1fr);
    gap: 16px;
    align-items: start;
}

.base-field {
    min-width: 0;
}

.field-label {
    margin-bottom: 7px;
    color: var(--el-text-color-regular);
    font-size: 13px;
    font-weight: 500;
    line-height: 20px;
}

.field-label .is-required {
    margin-right: 4px;
    color: var(--el-color-danger);
}

.field-help {
    min-height: 18px;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--el-text-color-placeholder);
    font-size: 12px;
}

.field-help.is-active {
    color: var(--el-color-primary);
}

.selected-member {
    min-height: 54px;
    margin-top: 8px;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 7px;
    background-color: var(--el-fill-color-lighter);
}

.selected-member__main {
    flex: 1;
    min-width: 0;
}

.selected-member__name {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--el-text-color-primary);
    font-size: 13px;
    font-weight: 600;
}

.selected-member__meta {
    margin-top: 2px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
}

.delivery-switch {
    display: flex;
    width: 100%;
}

:deep(.delivery-switch .el-radio-button) {
    flex: 1;
}

:deep(.delivery-switch .el-radio-button__inner) {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

:deep(.order-section--devices .device-list) {
    max-height: calc(92vh - 430px);
    min-height: 128px;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding-right: 4px;
}

.dialog-footer {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.dialog-footer__status {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}

.dialog-footer__status .is-success {
    color: var(--el-color-success);
}

.dialog-footer__status .is-warning {
    color: var(--el-color-warning);
}

.dialog-footer__actions {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 10px;
}

:deep(.dialog-footer__actions .el-button + .el-button) {
    margin-left: 0;
}

@media (max-width: 1100px) {
    .base-grid {
        grid-template-columns: minmax(280px, 1.2fr) minmax(220px, 0.8fr);
    }

    .base-field--express {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    .order-section--base,
    .order-section--devices {
        padding: 14px;
    }

    .base-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .base-field--express {
        grid-column: auto;
    }

    :deep(.order-section--devices .device-list) {
        max-height: none;
        overflow: visible;
    }

    .dialog-footer.is-mobile {
        align-items: stretch;
        flex-direction: column;
    }

    .dialog-footer__actions {
        width: 100%;
    }

    .dialog-footer__actions .el-button {
        flex: 1;
    }
}
</style>
