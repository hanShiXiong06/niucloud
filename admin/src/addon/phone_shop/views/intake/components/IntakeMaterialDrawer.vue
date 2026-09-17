<template>
    <el-drawer v-model="visible" title="完善商城资料" size="min(680px, 100vw)" :close-on-click-modal="false" :before-close="beforeClose" destroy-on-close>
        <div v-loading="loading" class="material-body">
            <el-result v-if="loadError" icon="warning" title="资料未加载" :sub-title="loadError">
                <template #extra><el-button @click="load">重新加载</el-button></template>
            </el-result>
            <template v-else-if="info">
                <div class="material-summary">
                    <strong>{{ info.model_name }}</strong>
                    <span>IMEI {{ info.imei || '未记录' }}</span>
                    <div class="material-tags">
                        <el-tag effect="plain">{{ info.listing_state_name }}</el-tag>
                        <el-tag :type="info.material_task.status === 'completed' ? 'success' : 'warning'">{{ info.material_task.status_name }}</el-tag>
                    </div>
                </div>
                <el-alert class="mb-4" type="info" show-icon :closable="true" title="本页只补展示资料，不修改价格、库存或财务。已售或已下架商品不会重新上架。" />
                <div v-if="images.length" class="material-images">
                    <el-image v-for="(url, index) in images" :key="url" :src="url" fit="cover" :preview-src-list="images" :initial-index="index" :preview-teleported="true" />
                </div>
                <el-form :model="form" label-position="top" :disabled="saving">
                    <el-form-item label="商品标题" required><el-input v-model="form.goods_name" maxlength="255" /></el-form-item>
                    <el-form-item label="展示摘要"><el-input v-model="form.sub_title" maxlength="255" placeholder="仅填写已确认的卖点，不确定内容不作承诺" /></el-form-item>
                    <div class="material-grid">
                        <el-form-item label="容量 / 规格"><el-input v-model="form.memory_group" maxlength="50" placeholder="例如 256G，未确认可留空" /></el-form-item>
                        <el-form-item label="颜色"><el-input v-model="form.device_color" maxlength="50" placeholder="未确认可留空" /></el-form-item>
                        <el-form-item label="成色"><el-input v-model="form.condition_grade" maxlength="50" placeholder="填写实际成色，未确认可留空" /></el-form-item>
                        <el-form-item label="电池健康度（%）"><el-input-number v-model="form.battery_health" :min="0" :max="100" :precision="0" :controls="false" placeholder="未确认可留空" /></el-form-item>
                        <el-form-item label="保修到期日"><el-date-picker v-model="form.warranty_date" type="date" value-format="YYYY-MM-DD" placeholder="未确认可留空" clearable /></el-form-item>
                    </div>
                </el-form>
                <el-collapse class="mt-3">
                    <el-collapse-item title="查看 ERP 带入的质检报告（只读）" name="qc">
                        <CheckResultPanel v-if="info.check?.result_items?.length || info.check?.summary_fields?.length"
                            :summary-fields="info.check.summary_fields" :severity-summary="info.check.severity_summary"
                            :abnormal-items="info.check.abnormal_items" :items="info.check.result_items" />
                        <el-empty v-else description="尚无结构化质检报告，不代表检测正常" :image-size="64" />
                    </el-collapse-item>
                    <el-collapse-item v-if="info.material_task.history?.length" title="处理记录" name="history">
                        <div v-for="(item, index) in [...info.material_task.history].reverse()" :key="index" class="material-history">
                            <span>{{ item.operator_name || '商城运营' }} · {{ item.action === 'complete' ? '核对完成' : '保存进度' }}</span>
                            <span>{{ formatTime(item.at) }}</span>
                        </div>
                    </el-collapse-item>
                </el-collapse>
            </template>
        </div>
        <template #footer>
            <div class="material-footer">
                <el-button :disabled="saving" @click="beforeClose(() => { visible = false })">关闭</el-button>
                <el-button :disabled="!info || loading || !!loadError" :loading="saving" @click="submit('save')" v-permission="'phone_shop_intake_material_edit'">保存进度</el-button>
                <el-button type="primary" :disabled="!info || loading || !!loadError" :loading="saving" @click="submit('complete')" v-permission="'phone_shop_intake_material_edit'">核对完成</el-button>
            </div>
        </template>
    </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { img } from '@/utils/common'
import { getDeviceIntakeMaterial, saveDeviceIntakeMaterial } from '@/addon/phone_shop/api/device_intake'
import CheckResultPanel from '@/addon/phone_shop/components/CheckResultPanel.vue'

const emit = defineEmits<{ (e: 'saved'): void }>()
const visible = ref(false)
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const intakeId = ref(0)
const info = ref<any>(null)
const initial = ref('')
let requestSequence = 0
const form = reactive({ goods_name: '', sub_title: '', memory_group: '', device_color: '', condition_grade: '', battery_health: undefined as number | undefined, warranty_date: '' })
const images = computed(() => (info.value?.images || []).map((url: string) => img(url)))
const formatTime = (value: number) => value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : ''
const formatDate = (value: number) => {
    if (!value) return ''
    const date = new Date(value * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
const populate = (data: any) => {
    info.value = data
    const values = data.form || {}
    Object.assign(form, {
        goods_name: values.goods_name || '', sub_title: values.sub_title || '', memory_group: values.memory_group || '',
        device_color: values.device_color || '', condition_grade: values.condition_grade || '',
        battery_health: values.battery_health == null || Number(values.battery_health) < 0 ? undefined : Number(values.battery_health),
        warranty_date: formatDate(Number(values.warranty_expire_time || 0))
    })
    initial.value = JSON.stringify(form)
}
const load = async () => {
    const sequence = ++requestSequence
    const id = intakeId.value
    loading.value = true
    loadError.value = ''
    try { const res = await getDeviceIntakeMaterial(id); if (sequence === requestSequence && visible.value && id === intakeId.value) populate(res.data) }
    catch (error: any) { if (sequence === requestSequence) loadError.value = error?.message || error?.msg || '请稍后重试' }
    finally { if (sequence === requestSequence) loading.value = false }
}
const open = async (id: number) => {
    intakeId.value = id
    info.value = null
    visible.value = true
    await load()
}
const beforeClose = async (done: () => void) => {
    if (saving.value) return
    if (info.value && initial.value !== JSON.stringify(form)) {
        try { await ElMessageBox.confirm('当前修改尚未保存，关闭后不会保留。确认关闭？', '未保存的资料', { type: 'warning', confirmButtonText: '放弃修改', cancelButtonText: '继续编辑' }) }
        catch { return }
    }
    requestSequence++
    done()
}
const submit = async (action: 'save' | 'complete') => {
    if (saving.value || !info.value) return
    if (!form.goods_name.trim()) return ElMessage.warning('请填写商品标题')
    if (action === 'complete') {
        const missing = [!form.memory_group.trim() && '容量/规格', !form.device_color.trim() && '颜色', !form.condition_grade.trim() && '成色', form.battery_health == null && '电池健康度', !form.warranty_date && '保修到期日'].filter(Boolean)
        try {
            await ElMessageBox.confirm(missing.length ? `${missing.join('、')}仍未记录，客户侧将保持未知。确认已完成本次核对？此操作不改变上架和交易状态。` : '确认资料已核对？仅结束资料待办，不改变上架和交易状态。', '核对完成', { type: missing.length ? 'warning' : 'info', confirmButtonText: '确认完成', cancelButtonText: '继续完善' })
        } catch { return }
    }
    saving.value = true
    try {
        const res = await saveDeviceIntakeMaterial(intakeId.value, {
            ...form, action, revision: info.value.material_task.revision,
            battery_health: form.battery_health ?? -1, warranty_expire_time: form.warranty_date || 0
        })
        populate(res.data)
        emit('saved')
        if (res.data?.warning) ElMessage.warning(res.data.warning)
        else ElMessage.success(action === 'complete' ? '资料已核对完成，商品交易状态未改变' : '进度已保存，可稍后继续完善')
        if (action === 'complete') visible.value = false
    } catch { /* 请求层展示明确失败原因，保留当前输入供核对 */ }
    finally { saving.value = false }
}
defineExpose({ open })
</script>

<style scoped>
.material-body { min-height: 180px; }
.material-summary { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.material-summary > span { color: var(--el-text-color-secondary); font-size: 13px; }
.material-tags, .material-images { display: flex; flex-wrap: wrap; gap: 8px; }
.material-images { margin-bottom: 16px; }
.material-images .el-image { width: 66px; height: 66px; border-radius: 6px; }
.material-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
.material-grid :deep(.el-input-number), .material-grid :deep(.el-date-editor) { width: 100%; }
.material-history { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 12px; color: var(--el-text-color-secondary); }
.material-footer { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; }
.material-footer .el-button { margin-left: 0; }
@media (max-width: 520px) { .material-grid { grid-template-columns: 1fr; } }
</style>
