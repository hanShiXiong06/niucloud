<template>
    <HsxDialog :confirm-loading="submitting"
        v-model="dialogVisible"
        :width="isMobile ? '95vw' : '1120px'"
        top="4vh"
        center
        class="device-confirm-dialog "
        :destroy-on-close="true"
        @closed="handleClosed"
    >
        <template #header>
            <span class="text-lg font-bold">设备信息确认，共 {{ rows.length }} 台</span>
        </template>

        <DeviceEntryList :devices="rows" :order-id="orderId" />

        <template #footer>
            <div class="handoff-footer" :class="{ 'is-mobile': isMobile }">
                <NextAssigneeSelect v-model="nextAssigneeUid" stage-key="check" label="下一步 · 质检负责人" compact />
                <div :class="isMobile ? 'flex w-full flex-col gap-2' : 'dialog-footer'">
                    <el-button :disabled="submitting" :class="isMobile ? '!ml-0 w-full' : ''" @click="handleCancel">取消</el-button>
                    <el-button
                        type="primary"
                        :class="isMobile ? '!ml-0 w-full' : ''"
                        :loading="submitting"
                        :disabled="(savedCount === 0) || (submitting)"
                        @click="handleConfirm"
                    >确认并签收</el-button>
                </div>
            </div>
        </template>
    </HsxDialog>
</template>

<script setup lang="ts">
import { HsxDialog, useFeedback } from '@/addon/hsx_components/core'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'

import DeviceEntryList from '@/addon/hsx_recycle/components/device-entry/DeviceEntryList.vue'
import NextAssigneeSelect from '@/addon/hsx_recycle/components/task/NextAssigneeSelect.vue'
import { normalizeDevice } from '@/addon/hsx_recycle/components/device-entry/deviceUtil'
import type { DeviceEntryRow } from '@/addon/hsx_recycle/components/device-entry/types'
const hsxFeedback = useFeedback()


const props = defineProps({
    visible: { type: Boolean, default: false },
    deviceList: { type: Array as () => any[], default: () => [] },
    orderId: { type: [Number, String], required: true }
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

const isMobile = ref(false)
const submitting = ref(false)
const rows = ref<DeviceEntryRow[]>([])
const nextAssigneeUid = ref(0)

const dialogVisible = computed({
    get: () => props.visible,
    set: (v: boolean) => emit('update:visible', v)
})

const savedCount = computed(() => rows.value.filter(r => r.saved && r.id).length)

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

let rowKeySeed = 1

// 后端设备 → 录入行
const mapDeviceToRow = (d: any): DeviceEntryRow => {
    const info = d?.info && typeof d.info === 'object' ? d.info : {}
    const summaryValues: Record<string, any> = { ...info }
    delete summaryValues.goods_category
    delete summaryValues.check_meta
    delete summaryValues.sign_summary
    if (info.sign_summary && typeof info.sign_summary === 'object') {
        Object.assign(summaryValues, info.sign_summary)
    }
    const categoryId = Number(d?.category_id ?? d?.category ?? 0)
    const categoryPath = (Array.isArray(d?.category_path) && d.category_path.length)
        ? d.category_path.map((v: any) => Number(v))
        : (Array.isArray(info.goods_category) && info.goods_category.length
            ? info.goods_category.map((v: any) => Number(v))
            : (categoryId ? [categoryId] : []))
    return {
        _k: rowKeySeed++,
        id: d?.id,
        imei: d?.imei || '',
        model: d?.model || '',
        user_sn: d?.user_sn || '',
        initial_price: Number(d?.initial_price || 0),
        check_images_buyer: String(d?.check_images_buyer || ''),
        category_id: categoryId,
        category_path: categoryPath,
        // 完整 id 路径直接给级联做 id 反显
        model_path: categoryPath,
        check_template_id: Number(d?.check_template_id || 0),
        saved: !!d?.id,
        dirty: false,
        summary_fields: [],
        summary_values: summaryValues
    }
}

const buildRows = () => {
    const list = (props.deviceList || []).map(mapDeviceToRow)
    if (!list.length) {
        list.push({ _k: rowKeySeed++, imei: '', model: '', initial_price: 0, check_images_buyer: '', summary_fields: [], summary_values: {} } as DeviceEntryRow)
    }
    rows.value = list
}

watch(() => props.visible, (v) => {
    if (v) buildRows()
})

const handleConfirm = async () => {
    const pending = rows.value.find(r => (r.imei || r.model) && !r.saved)
    if (pending) {
        hsxFeedback.warning('有未保存的设备，请先逐台保存后再签收')
        return
    }
    const savedDevices = rows.value.filter(r => r.saved && r.id)
    if (!savedDevices.length) {
        hsxFeedback.warning('请先保存至少一台设备')
        return
    }
    submitting.value = true
    try {
        emit('confirm', {
            orderId: props.orderId,
            next_assignee_uid: nextAssigneeUid.value,
            devices: savedDevices.map(r => ({ id: r.id, ...normalizeDevice(r) }))
        })
    } finally {
        submitting.value = false
    }
}

const handleCancel = () => {
    dialogVisible.value = false
    emit('cancel')
}

const handleClosed = () => {
    rows.value = []
    nextAssigneeUid.value = 0
}

onMounted(() => {
    updateResponsiveState()
    window.addEventListener('resize', updateResponsiveState)
    if (props.visible) buildRows()
})
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateResponsiveState)
})
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.handoff-footer { display: flex; align-items: center; justify-content: space-between; gap: 20px; width: 100%; }
.handoff-footer.is-mobile { align-items: stretch; flex-direction: column; }
</style>
