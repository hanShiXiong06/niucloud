<template>
    <div class="device-card" :class="{ 'device-card--active': !device.saved }">
        <div class="device-card__main">
            <span class="device-card__index">{{ index + 1 }}</span>

            <div class="device-card__identity">
                <div class="device-card__imei-field" :class="{ 'has-error': imeiError }">
                    <el-input v-model="device.imei" placeholder="必填，6–15 位字母或数字" aria-label="IMEI（必填）" aria-required="true"
                        :aria-invalid="!!imeiError" clearable size="small" @input="markDirty" @blur="device.imei_touched = true" />
                    <div v-if="imeiError" class="device-card__imei-error" role="alert">{{ imeiError }}</div>
                </div>
                <el-tooltip v-if="device.user_sn && device.user_sn !== device.imei" :content="'用户自助提交的串号：' + device.user_sn" placement="top">
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

            <div class="device-card__quote">
                <el-select :model-value="device.initial_price_uid || undefined" :loading="quoteLoading"
                    :disabled="quoteLoading || quoteError" clearable filterable size="small" placeholder="选填" aria-label="报价员（选填）" @update:model-value="updateQuoteUser">
                    <el-option v-if="device.initial_price_uid && !quoteUsers?.some(user => user.uid === device.initial_price_uid)"
                        :value="device.initial_price_uid" :label="(device.initial_price_name || '原报价员') + '（待核对）'" disabled />
                    <el-option v-for="user in quoteUsers" :key="user.uid" :value="user.uid" :label="user.name + (user.disabled ? '（无定价权限）' : '')" :disabled="user.disabled" />
                </el-select>
            </div>

            <el-popover placement="bottom" :width="390" trigger="click" popper-class="device-buyer-image-popover">
                <template #reference>
                    <el-button size="small" :icon="Picture" class="device-card__images"
                        :aria-label="buyerImageCount ? `查看或编辑 ${buyerImageCount} 张买家图` : '添加买家图'" :title="buyerImageCount ? `${buyerImageCount} 张买家图` : '添加买家图'">
                        {{ buyerImageCount ? `${buyerImageCount}` : '' }}
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
                <el-button v-if="!device.saved || device.dirty" type="primary" :plain="device.saved" size="small"
                    :loading="device.saving" :title="device.saved ? '保存修改' : '保存设备'"
                    @click="device.saved ? emit('update') : emit('save')">保存</el-button>
                <!-- 2.7.x 的混合 trigger 不会保持菜单 hover；单一 hover 才能在移入菜单时取消关闭。 -->
                <el-dropdown trigger="hover" :show-timeout="0" :hide-timeout="250" placement="bottom-end" @command="handleCommand">
                    <el-button link size="small" :icon="MoreFilled" aria-label="更多设备操作" title="更多操作" class="device-card__more" />
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="remove" :icon="Delete" :disabled="!canRemove || device.saving" class="device-card__delete">删除设备</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <div v-if="device.category_id || device.summary_loading || (device.summary_fields && device.summary_fields.length) || $slots['summary-actions']" class="device-card__summary">
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
                    <slot name="summary-actions" />
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
import { CircleCheck, Clock, Delete, EditPen, Loading, MoreFilled, Picture, Setting } from '@element-plus/icons-vue'
import { buildSummaryChips } from './summaryUtil'
import { deviceImeiError } from './imeiValidation'
import type { DeviceEntryRow } from './types'

const props = defineProps<{ device: DeviceEntryRow; index: number; canRemove?: boolean;
    quoteUsers?: Array<{ uid: number; name: string; disabled?: boolean }>; quoteLoading?: boolean; quoteError?: boolean; assignToQuoteUser?: boolean }>()
const emit = defineEmits<{
    (e: 'save'): void
    (e: 'update'): void
    (e: 'remove'): void
    (e: 'edit-summary'): void
    (e: 'configure-template'): void
    (e: 'reload-quote-users'): void
}>()

const chips = computed(() => buildSummaryChips(props.device.summary_fields || [], props.device.summary_values || {}))
const buyerImageCount = computed(() => String(props.device.check_images_buyer || '').split(',').filter(Boolean).length)
const imeiError = computed(() => props.device.imei_touched ? deviceImeiError(props.device.imei) : '')

const markDirty = () => {
    if (props.device.saved) props.device.dirty = true
}

const handleCommand = (command: string) => {
    if (command === 'remove' && props.canRemove && !props.device.saving) emit('remove')
}

const updateQuoteUser = (uid?: number) => {
    props.device.initial_price_uid = Number(uid || 0)
    props.device.initial_price_name = props.quoteUsers?.find(user => user.uid === Number(uid))?.name || ''
    markDirty()
}

const updateBuyerImages = (value: string) => {
    props.device.check_images_buyer = value
    markDirty()
}
</script>

<style lang="scss" scoped>
.device-card {
    --device-entry-row-bg: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-top: 0;
    padding: 8px 10px;
    background-color: var(--device-entry-row-bg);
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: background-color 0.2s;
}

.device-card--active { --device-entry-row-bg: var(--el-color-primary-light-9); }
.device-card__quote { min-width: 0; }
.device-card__quote :deep(.el-select) { width: 100%; }
.device-card:hover { --device-entry-row-bg: var(--el-fill-color-light); }

.device-card__main {
    display: grid;
    grid-template-columns: var(--device-entry-columns, 24px minmax(190px, 1fr) minmax(150px, 220px) 100px 120px 52px 48px 84px);
    align-items: center;
    gap: 8px;
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

.device-card__identity { display: flex; flex-direction: column; align-items: stretch; gap: 3px; }
.device-card__imei-field { min-width: 0; }
.device-card__imei-field :deep(.el-input__inner) { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; font-size: 13px; }
.device-card__imei-error { margin-top: 4px; color: var(--el-color-danger); font-size: 12px; line-height: 1.4; }
.device-card__imei-field.has-error :deep(.el-input__wrapper) { box-shadow: 0 0 0 1px var(--el-color-danger) inset; }
.device-card__usersn { overflow: hidden; text-overflow: ellipsis; color: var(--el-text-color-placeholder); font-size: 11px; white-space: nowrap; }

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
.device-card__images { width: 52px; margin: 0; padding: 5px 8px; }

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
.device-card__ops { position: sticky; right: 0; z-index: 1; min-height: 32px; display: flex; align-items: center; justify-content: flex-end; gap: 4px; background-color: var(--device-entry-row-bg); box-shadow: -8px 0 8px -8px var(--el-border-color); }
.device-card__ops :deep(.el-button + .el-button) { margin-left: 0; }
.device-card__more { width: 24px; height: 28px; }
.device-card__delete { color: var(--el-color-danger); }

.device-card__summary {
    margin-left: 32px;
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

</style>
