<template>
    <el-drawer v-model="visible" title="取件通知执行记录" size="min(960px, 96vw)" :destroy-on-close="true">
        <el-alert
            title="这里读取牛云框架的通知执行记录。存在记录不代表微信已受理、客户已收到或已读；送达未确认时不要直接重发。"
            type="info"
            :closable="false"
            show-icon
        />
        <div class="my-[16px] flex items-center justify-between gap-[12px]">
            <span class="text-xs text-gray-500">开关全部关闭、预约日志写入失败等未进入发送渠道的情况，请查看回收订单通知历史。</span>
            <el-button :loading="loading" @click="loadReceipts(page)">刷新</el-button>
        </div>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="mb-[16px]" />
        <el-table :data="rows" v-loading="loading" row-key="id" empty-text="暂无取件通知执行记录" class="w-full">
            <el-table-column prop="time" label="记录时间" min-width="160" />
            <el-table-column prop="channel" label="渠道" min-width="105" />
            <el-table-column prop="order" label="回收订单" min-width="150" />
            <el-table-column label="记录状态" min-width="125">
                <template #default="{ row }">
                    <el-tag :type="row.tagType">{{ row.statusText }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="message" label="执行说明 / 送达边界" min-width="260">
                <template #default="{ row }">
                    <span class="break-words whitespace-normal">{{ row.message }}</span>
                </template>
            </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end overflow-x-auto">
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :page-sizes="[10, 20, 50]"
                :total="total"
                :disabled="loading"
                layout="total, sizes, prev, pager, next"
                @size-change="loadReceipts(1)"
                @current-change="loadReceipts"
            />
        </div>
    </el-drawer>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { getNoticeLog } from '@/app/api/notice'
import { PICKUP_NOTICE_KEY, toPickupReceiptRow } from '@/addon/hsx_recycle/utils/pickup-notice-receipts'
import type { PickupReceiptRow } from '@/addon/hsx_recycle/utils/pickup-notice-receipts'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const visible = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value)
})
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const loading = ref(false)
const error = ref('')
const rows = ref<PickupReceiptRow[]>([])
let requestVersion = 0

const loadReceipts = async (requestedPage = 1) => {
    if (!props.modelValue) return
    const version = ++requestVersion
    page.value = requestedPage
    loading.value = true
    error.value = ''
    rows.value = []
    total.value = 0
    try {
        const response: any = await getNoticeLog({ key: PICKUP_NOTICE_KEY, page: page.value, limit: limit.value })
        if (version !== requestVersion || !props.modelValue) return
        const data = response?.data
        if (!Array.isArray(data?.data)) throw new Error('通知记录返回格式异常')
        rows.value = data.data
            .filter((row: any) => row && row.key === PICKUP_NOTICE_KEY)
            .map(toPickupReceiptRow)
        const count = Number(data.total)
        total.value = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0
    } catch (cause: any) {
        if (version !== requestVersion || !props.modelValue) return
        const reason = cause?.msg || cause?.response?.data?.msg || cause?.message || '请求失败'
        error.value = `无法读取取件通知记录：${reason}。请确认具有框架“消息发送记录”查看权限，或稍后重试。`
    } finally {
        if (version === requestVersion) loading.value = false
    }
}

watch(() => props.modelValue, (open) => {
    if (open) {
        void loadReceipts(1)
    } else {
        requestVersion++
        loading.value = false
        rows.value = []
        total.value = 0
        error.value = ''
    }
}, { immediate: true })
</script>
