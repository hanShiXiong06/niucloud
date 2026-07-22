<template>
    <div class="device-card" :class="{ 'device-card--active': !device.saved }">
        <!-- 主行：序号 + 型号 + SN + 价格 + 状态 + 操作 -->
        <div class="device-card__main">
            <span class="device-card__index">{{ index + 1 }}</span>

            <el-tooltip v-if="device.user_sn" content="用户自助提交的串号" placement="top">
                <span class="device-card__usersn">用户串号 {{ device.user_sn }}</span>
            </el-tooltip>

            <el-input
                v-model="device.imei"
                placeholder="SN / IMEI"
                clearable
                size="small"
                class="device-card__sn"
                @input="markDirty"
            />
             <div class="device-card__model">
                <slot name="model" />
            </div>

            <div class="device-card__price">
                <span class="device-card__price-prefix">¥</span>
                <el-input-number
                    v-model="device.initial_price"
                    :min="0"
                    :controls="false"
                    placeholder="0"
                    size="small"
                    class="device-card__price-input"
                    @change="markDirty"
                />
            </div>

            <span class="device-card__status" :class="device.saved ? 'is-saved' : 'is-pending'">
                <el-icon><component :is="device.saved ? CircleCheck : Clock" /></el-icon>
                {{ device.saved ? '已保存' : '待保存' }}
            </span>

            <div class="device-card__ops">
                <el-button
                    v-if="!device.saved"
                    type="primary"
                    size="small"
                    :loading="device.saving"
                    @click="emit('save')"
                >保存</el-button>
                <el-button
                    v-else-if="device.dirty"
                    type="primary"
                    plain
                    size="small"
                    :loading="device.saving"
                    @click="emit('update')"
                >保存修改</el-button>
                <el-button
                    link
                    type="danger"
                    size="small"
                    :icon="Delete"
                    :disabled="!canRemove"
                    @click="emit('remove')"
                />
            </div>
        </div>

        <!-- 次行：质检摘要（标签展示 + 二次弹窗录入/编辑） -->
        <div class="device-card__summary">
            <div v-if="device.summary_loading" class="summary-muted">
                <el-icon class="is-loading"><Loading /></el-icon>
                <span>加载质检模板...</span>
            </div>
            <template v-else-if="device.summary_fields && device.summary_fields.length">
                <div class="summary-chips">
                    <span v-for="chip in chips" :key="chip.key" class="summary-chip">
                        <span class="summary-chip__label">{{ chip.name }}</span>
                        <span class="summary-chip__value">{{ chip.value }}</span>
                    </span>
                    <span v-if="!chips.length" class="summary-empty">未录入质检信息</span>
                </div>
                <el-button link type="primary" size="small" :icon="EditPen" class="summary-edit" @click="emit('edit-summary')">
                    {{ chips.length ? '编辑' : '录入' }}
                </el-button>
            </template>
            <div v-else class="summary-muted">选择型号后可录入质检信息</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { CircleCheck, Clock, Delete, EditPen, Loading } from '@element-plus/icons-vue'
import { buildSummaryChips } from './summaryUtil'
import type { DeviceEntryRow } from './types'

const props = defineProps<{
    device: DeviceEntryRow
    index: number
    /** 是否允许删除（如最后一行不可删） */
    canRemove?: boolean
}>()

const emit = defineEmits<{
    (e: 'save'): void
    (e: 'update'): void
    (e: 'remove'): void
    (e: 'edit-summary'): void
}>()

const chips = computed(() => buildSummaryChips(props.device.summary_fields || [], props.device.summary_values || {}))

// 已保存设备发生编辑后标脏，露出「保存修改」
const markDirty = () => {
    if (props.device.saved) props.device.dirty = true
}
</script>

<style lang="scss" scoped>
.device-card {
    border: 1px solid #ebeef5;
    border-radius: 10px;
    padding: 10px 12px;
    background-color: #fff;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.device-card--active {
    border-color: #d4e0f5;
}

.device-card:hover {
    box-shadow: 0 2px 10px rgba(64, 110, 230, 0.06);
}

.device-card__main {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.device-card__index {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background-color: #f0f2f5;
    color: #909399;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.device-card__model {
    flex: 1 1 220px;
    min-width: 180px;
}

.device-card__usersn {
    flex: 0 0 auto;
    font-size: 12px;
    color: #909399;
    background-color: #f4f4f5;
    border-radius: 4px;
    padding: 2px 8px;
    white-space: nowrap;
}

.device-card__sn {
    flex: 0 0 150px;
}

.device-card__price {
    flex: 0 0 116px;
    display: flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #dcdfe6;
    border-radius: 6px;
    padding: 0 10px;
    height: 28px;
    background-color: #fff;
}

.device-card__price-prefix {
    color: #909399;
    font-size: 13px;
}

.device-card__status {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    padding: 2px 9px;
    border-radius: 999px;
    white-space: nowrap;
}

.device-card__status.is-saved {
    background-color: #e7f6ec;
    color: #2ba471;
}

.device-card__status.is-pending {
    background-color: #fdf3e6;
    color: #e6a23c;
}

.device-card__ops {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 2px;
}

.device-card__summary {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    background-color: #f7f9fc;
    border-radius: 8px;
}

.summary-chips {
    flex: 1 1 auto;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.summary-chip {
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    font-size: 12px;
    line-height: 1.4;
    padding: 3px 9px;
    border-radius: 6px;
    background-color: #fff;
    border: 1px solid #e7eaf0;
}

.summary-chip__label {
    color: #9aa3b2;
}

.summary-chip__value {
    color: #303133;
    font-weight: 500;
}

.summary-empty {
    font-size: 12px;
    color: #c0c4cc;
}

.summary-edit {
    flex: 0 0 auto;
}

.summary-muted {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #a8abb2;
}

:deep(.device-card__price-input) {
    width: 100%;
}

:deep(.device-card__price-input .el-input__wrapper) {
    box-shadow: none !important;
    padding: 0;
    background-color: transparent;
}

:deep(.device-card__price-input .el-input__inner) {
    text-align: left;
}
</style>
