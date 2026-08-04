<template>
    <div class="device-card" :class="{ 'device-card--active': !device.saved }">
        <div class="device-card__main">
            <span class="device-card__index">{{ index + 1 }}</span>

            <div class="device-card__identity">
                <el-input v-model="device.imei" placeholder="扫描或输入" clearable size="small" @input="markDirty" />
                <el-tooltip v-if="device.user_sn" content="用户自助提交的串号" placement="top">
                    <span class="device-card__usersn">原 {{ device.user_sn }}</span>
                </el-tooltip>
            </div>

            <div class="device-card__model"><slot name="model" /></div>

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

            <el-popover placement="bottom" :width="390" trigger="click" popper-class="device-buyer-image-popover">
                <template #reference>
                    <el-button size="small" :icon="Picture" class="device-card__images">
                        {{ buyerImageCount ? `${buyerImageCount} 张` : '添加' }}
                    </el-button>
                </template>
                <div class="buyer-image-panel">
                    <div class="buyer-image-panel__head">
                        <strong>买家图</strong>
                        <span>选填，最多 6 张</span>
                    </div>
                    <upload-image
                        :model-value="device.check_images_buyer || ''"
                        :limit="6"
                        width="64px"
                        height="64px"
                        image-text="选择图片"
                        @update:model-value="updateBuyerImages"
                    />
                </div>
            </el-popover>

            <span class="device-card__status" :class="device.saved ? 'is-saved' : 'is-pending'">
                <el-icon><component :is="device.saved ? CircleCheck : Clock" /></el-icon>
                {{ device.saved ? '已存' : '待存' }}
            </span>

            <div class="device-card__ops">
                <el-button v-if="!device.saved" type="primary" size="small" :loading="device.saving" @click="emit('save')">保存</el-button>
                <el-button v-else-if="device.dirty" type="primary" plain size="small" :loading="device.saving" @click="emit('update')">保存修改</el-button>
                <el-button link type="danger" size="small" :icon="Delete" :disabled="!canRemove" @click="emit('remove')" />
            </div>
        </div>

        <div v-if="device.category_id || device.summary_loading || (device.summary_fields && device.summary_fields.length)" class="device-card__summary">
            <div v-if="device.summary_loading" class="summary-muted">
                <el-icon class="is-loading"><Loading /></el-icon>
                <span>加载质检模板...</span>
            </div>
            <template v-else>
                <div class="summary-chips">
                    <span v-for="chip in chips" :key="chip.key" class="summary-chip">
                        <span class="summary-chip__label">{{ chip.name }}</span>
                        <span class="summary-chip__value">{{ chip.value }}</span>
                    </span>
                    <span v-if="!chips.length" class="summary-empty">质检摘要未录入</span>
                </div>
                <div class="summary-actions">
                    <el-tooltip content="切换该型号使用的质检模板" placement="top">
                        <el-button link type="primary" size="small" :icon="Setting" @click="emit('configure-template')">模板</el-button>
                    </el-tooltip>
                    <el-button link type="primary" size="small" :icon="EditPen" @click="emit('edit-summary')">
                        {{ chips.length ? '编辑' : '录入' }}
                    </el-button>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { CircleCheck, Clock, Delete, EditPen, Loading, Picture, Setting } from '@element-plus/icons-vue'
import { buildSummaryChips } from './summaryUtil'
import type { DeviceEntryRow } from './types'

const props = defineProps<{ device: DeviceEntryRow; index: number; canRemove?: boolean }>()
const emit = defineEmits<{
    (e: 'save'): void
    (e: 'update'): void
    (e: 'remove'): void
    (e: 'edit-summary'): void
    (e: 'configure-template'): void
}>()

const chips = computed(() => buildSummaryChips(props.device.summary_fields || [], props.device.summary_values || {}))
const buyerImageCount = computed(() => String(props.device.check_images_buyer || '').split(',').filter(Boolean).length)

const markDirty = () => {
    if (props.device.saved) props.device.dirty = true
}

const updateBuyerImages = (value: string) => {
    props.device.check_images_buyer = value
    markDirty()
}
</script>

<style lang="scss" scoped>
.device-card {
    border: 1px solid var(--el-border-color-lighter);
    border-top: 0;
    padding: 8px 10px;
    background-color: var(--el-bg-color);
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: background-color 0.2s;
}

.device-card--active { background-color: var(--el-color-primary-light-9); }
.device-card:hover { background-color: var(--el-fill-color-light); }

.device-card__main {
    display: grid;
    grid-template-columns: 30px minmax(150px, 0.8fr) minmax(200px, 1.5fr) 100px 82px 64px 100px;
    align-items: center;
    gap: 10px;
    min-height: 32px;
}

.device-card__index {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    background-color: var(--el-fill-color);
    color: var(--el-text-color-secondary);
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.device-card__model,
.device-card__identity { min-width: 0; }

.device-card__identity { display: flex; align-items: center; gap: 5px; }
.device-card__usersn { color: var(--el-text-color-placeholder); font-size: 11px; white-space: nowrap; }

.device-card__price {
    display: flex;
    align-items: center;
    gap: 4px;
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
    padding: 0 8px;
    height: 30px;
    background-color: var(--el-bg-color);
}

.device-card__price-prefix { color: var(--el-text-color-placeholder); font-size: 13px; }
.device-card__images { width: 82px; margin: 0; }

.device-card__status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    font-size: 12px;
    padding: 3px 6px;
    border-radius: 4px;
    white-space: nowrap;
}

.device-card__status.is-saved { background-color: var(--el-color-success-light-9); color: var(--el-color-success-dark-2); }
.device-card__status.is-pending { background-color: var(--el-color-warning-light-9); color: var(--el-color-warning-dark-2); }
.device-card__ops { display: flex; align-items: center; justify-content: flex-end; gap: 2px; }

.device-card__summary {
    margin-left: 40px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    background-color: var(--el-fill-color-lighter);
    border-radius: 4px;
}

.summary-chips { flex: 1 1 auto; display: flex; flex-wrap: wrap; gap: 5px; min-width: 0; }
.summary-chip { display: inline-flex; gap: 5px; font-size: 12px; padding: 2px 7px; border: 1px solid var(--el-border-color-lighter); border-radius: 4px; background-color: var(--el-bg-color); }
.summary-chip__label { color: var(--el-text-color-secondary); }
.summary-chip__value { color: var(--el-text-color-primary); font-weight: 500; }
.summary-empty, .summary-muted { color: var(--el-text-color-placeholder); font-size: 12px; }
.summary-muted { display: flex; align-items: center; gap: 5px; }
.summary-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 2px; }

.buyer-image-panel__head { margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; color: var(--el-text-color-primary); font-size: 13px; }
.buyer-image-panel__head span { color: var(--el-text-color-secondary); font-size: 12px; }

:deep(.device-card__price-input) { width: 100%; }
:deep(.device-card__price-input .el-input__wrapper) { box-shadow: none !important; padding: 0; background-color: transparent; }
:deep(.device-card__price-input .el-input__inner) { text-align: left; }

@media (max-width: 768px) {
    .device-card { border-top: 1px solid var(--el-border-color-lighter); border-radius: 6px; padding: 10px; }
    .device-card__main { grid-template-columns: 28px minmax(0, 1fr) 88px; }
    .device-card__index { grid-column: 1; grid-row: 1; }
    .device-card__identity { grid-column: 2 / 4; grid-row: 1; }
    .device-card__model { grid-column: 2 / 4; grid-row: 2; }
    .device-card__price { grid-column: 2; grid-row: 3; }
    .device-card__images { grid-column: 3; grid-row: 3; }
    .device-card__status { grid-column: 2; grid-row: 4; justify-self: start; }
    .device-card__ops { grid-column: 3; grid-row: 4; justify-self: end; }
    .device-card__summary { margin-left: 0; }
}
</style>
