<template>
    <section class="erp-integration" v-loading="loading">
        <div class="integration-head">
            <div>
                <h3>ERP 联动</h3>
                <p>安装 ERP 不等于启用联动；本区单独保存，不会随页面顶部的“保存设置”一起提交。</p>
            </div>
            <el-button :loading="loading" :disabled="saving" @click="loadIntegration">刷新状态</el-button>
        </div>

        <HsxNotice default-expanded v-if="loadError" type="error" :closable="false" show-icon :title="loadError" />
        <template v-else-if="integration">
            <div class="integration-status">
                <el-tag :type="integration.installed ? 'info' : 'warning'">{{ integration.installed ? '自有 ERP 已安装' : '自有 ERP 未安装' }}</el-tag>
                <el-tag :type="!integration.configured ? 'warning' : integration.mode === 'self_erp' ? 'success' : 'info'">
                    {{ !integration.configured ? '兼容旧规则 · 待确认' : integration.mode === 'self_erp' ? '自有 ERP 联动已启用' : '新创建设备仅回收' }}
                </el-tag>
                <span v-if="changedAtText">上次确认：{{ changedAtText }}</span>
            </div>

            <HsxNotice default-expanded
                v-if="!integration.configured"
                type="warning"
                :closable="false"
                show-icon
                title="尚未明确确认联动方式，当前兼容旧规则；这不表示已经授权启用 ERP 联动。请选择方式并单独确认保存。"
            />
            <p v-if="integration.message" class="integration-message">{{ integration.message }}</p>

            <div class="integration-modes" role="group" aria-label="ERP 联动方式">
                <button type="button" class="integration-mode" :class="{ selected: selectedMode === 'local' }" :aria-pressed="selectedMode === 'local'" :disabled="saving" @click="selectedMode = 'local'">
                    <strong>仅使用回收</strong>
                    <span>切换后新创建的设备不自动转入自有 ERP，按回收端流程处理。</span>
                </button>
                <button type="button" class="integration-mode" :class="{ selected: selectedMode === 'self_erp' }" :aria-pressed="selectedMode === 'self_erp'" :disabled="!integration.installed || saving" @click="selectedMode = 'self_erp'">
                    <strong>联动自有 ERP</strong>
                    <span>{{ integration.installed ? '明确启用后，新创建的设备按联动规则进入自有 ERP；付款入口按每个订单的实际归属判断。' : '需要先安装自有 ERP；安装完成后仍需在这里明确启用。' }}</span>
                </button>
            </div>

            <HsxNotice default-expanded class="integration-impact" type="info" :closable="false" show-icon title="切换后新创建的设备按新配置办理；已有设备按已记录归属或历史规则办理，实际已入 ERP 的设备仍由 ERP 处理。保存不会迁移或重新同步历史设备，也不会执行付款。" />
            <div class="integration-footer">
                <span>{{ !integration.configured || selectedMode !== integration.mode ? '当前选择尚未生效，需点击右侧按钮单独保存。' : '当前联动方式已保存；如需变更，请重新选择并单独保存。' }}</span>
                <el-button type="primary" :loading="saving" :disabled="!canSave" @click="saveIntegration">
                    {{ integration.configured ? '保存 ERP 联动设置' : '确认并保存联动方式' }}
                </el-button>
            </div>
        </template>
    </section>
</template>

<script setup lang="ts">
import { HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { computed, onMounted, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { getRecycleErpIntegration, saveRecycleErpIntegration, type RecycleErpIntegration, type RecycleErpMode } from '@/addon/hsx_recycle/api/erp_integration'
const hsxFeedback = useFeedback()


const integration = ref<RecycleErpIntegration | null>(null)
const selectedMode = ref<RecycleErpMode>('local')
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')

const changedAtText = computed(() => {
    const value = Number(integration.value?.changed_at || 0)
    return value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : ''
})
const canSave = computed(() => Boolean(
    integration.value && !loadError.value && !loading.value
    && (selectedMode.value === 'local' || integration.value.installed)
    && (!integration.value.configured || selectedMode.value !== integration.value.mode)
))

const loadIntegration = async () => {
    loading.value = true
    loadError.value = ''
    try {
        const res: any = await getRecycleErpIntegration()
        const data = res?.data
        if (!data || !['local', 'self_erp'].includes(data.mode)) throw new Error('联动状态不完整')
        integration.value = data
        selectedMode.value = data.mode
    } catch (error) {
        loadError.value = 'ERP 联动状态查询失败，未改变任何设置。请刷新状态后再操作。'
    } finally {
        loading.value = false
    }
}

const saveIntegration = async () => {
    if (saving.value || !canSave.value) return
    const mode = selectedMode.value
    const effect = mode === 'self_erp'
        ? '确认启用自有 ERP 联动？切换后新创建的设备将按联动规则处理。'
        : '确认新创建的设备仅使用回收？切换后新创建的设备将不再自动转入自有 ERP。'
    try {
        await ElMessageBox.confirm(`${effect} 已有设备按已记录归属或历史规则办理，实际已入 ERP 的设备仍由 ERP 处理；本次不会迁移或重新同步历史设备，也不会执行付款。`, '确认 ERP 联动方式', {
            confirmButtonText: mode === 'self_erp' ? '确认启用联动' : '确认仅使用回收',
            cancelButtonText: '取消',
            type: 'warning'
        })
    } catch {
        return
    }
    saving.value = true
    try {
        await saveRecycleErpIntegration(mode)
        hsxFeedback.success('ERP 联动设置已保存，新创建设备按新配置办理；已有设备沿用已记录归属或历史规则')
        await loadIntegration()
    } catch (error: any) {
        hsxFeedback.error(error?.msg || error?.message || '联动设置保存失败，请刷新状态核对后重试')
    } finally {
        saving.value = false
    }
}

onMounted(loadIntegration)
</script>

<style scoped lang="scss">
.erp-integration { grid-column: 1 / -1; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; background: #fff; }
.integration-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.integration-head h3 { margin: 0; font-size: 16px; color: #1f2937; }
.integration-head p, .integration-message { font-size: 12px; line-height: 1.7; color: #64748b; }
.integration-head p { margin: 6px 0 16px; }
.integration-status { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.integration-status span { font-size: 12px; color: #64748b; }
.integration-modes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
.integration-mode { display: flex; flex-direction: column; gap: 8px; text-align: left; padding: 18px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; cursor: pointer; }
.integration-mode strong { font-size: 14px; color: #1f2937; }
.integration-mode span { font-size: 12px; line-height: 1.7; color: #64748b; }
.integration-mode.selected { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
.integration-mode:disabled { opacity: .6; cursor: not-allowed; }
.integration-impact { margin-top: 16px; }
.integration-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; }
.integration-footer > span { font-size: 12px; color: #64748b; }
@media (max-width: 768px) { .integration-modes { grid-template-columns: 1fr; } .integration-head { flex-wrap: wrap; } }
</style>
