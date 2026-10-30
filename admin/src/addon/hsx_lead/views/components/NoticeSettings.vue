<template>
    <HsxDrawer v-model="visible" title="通知模板设置（高级）" subtitle="使用框架已有微信订阅消息能力；只配置真实可用的公共模板，不伪造默认模板。" size="md" :body-loading="loading" show-footer :confirm-loading="saving" :confirm-disabled="loading || !!error" confirm-text="保存真实模板配置" @confirm="save">
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" /><el-alert :title="notice || '保存后还需在框架通知管理中获取并开启模板；会员需自行在小程序中点击订阅。保存配置不代表微信已投递。'" type="warning" show-icon :closable="false" class="lead-banner" />
        <el-collapse><el-collapse-item v-for="item in templates" :key="item.phase" :name="item.phase" :title="`${item.name || phases[item.phase]} · ${item.enabled ? '框架已开启' : '待框架开启'}`"><el-form label-position="top" class="lead-form"><el-form-item label="真实微信公共模板编号（tid）"><el-input v-model.trim="item.tid" placeholder="从微信模板库选择，不能编造" /></el-form-item><el-form-item label="关键词 ID 列表（kid_list）"><el-input v-model="item.kid_list_text" placeholder="如由微信模板提供的关键词 ID，以逗号分隔" /></el-form-item><el-form-item label="使用场景"><el-input v-model="item.scene_desc" maxlength="200" /></el-form-item><el-form-item label="关键词与允许变量对应"><div v-for="(field, index) in item.fields" :key="index" class="lead-actions" style="width: 100%; margin-bottom: 8px"><el-input v-model="field.label" placeholder="真实关键词名称" style="width: 145px" /><el-input v-model="field.keyword" placeholder="真实 keyword 字段" style="width: 145px" /><el-select v-model="field.variable" placeholder="选择允许变量" style="width: 180px"><el-option v-for="variable in variables" :key="variable.key" :label="variable.label" :value="variable.key" /></el-select><el-button link type="danger" @click="item.fields.splice(index, 1)">移除</el-button></div><el-button link type="primary" @click="item.fields.push({ label: '', keyword: '', variable: '' })">添加关键词映射</el-button></el-form-item><el-checkbox v-model="item.confirmed">我已核对真实公共模板与关键词；清空本阶段配置时也需确认</el-checkbox><p class="lead-muted">{{ item.message || item.status }} · 模板消息不包含客户电话、姓名、地址等隐私。</p></el-form></el-collapse-item></el-collapse>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import { getNoticeConfig, saveNoticeConfig } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const loading = ref(false), saving = ref(false), error = ref(''), notice = ref(''), templates = ref<Row[]>([]), variables = ref<Row[]>([]), phases: Record<string, string> = { assigned: '新派单通知', changed: '工单变更通知' }
let sequence = 0
async function load() { const token = ++sequence; loading.value = true; error.value = ''; try { const result = await getNoticeConfig(); if (token !== sequence) return; templates.value = (result.data.templates || []).map((item: Row) => ({ ...item, tid: item.tid || '', kid_list_text: Array.isArray(item.kid_list) ? item.kid_list.join(',') : String(item.kid_list || ''), fields: (item.fields || []).map((field: Row) => ({ ...field })), confirmed: false })); variables.value = result.data.variables || []; notice.value = result.data.notice || '' } catch (cause) { if (token === sequence) error.value = errorText(cause, '通知配置读取失败。') } finally { if (token === sequence) loading.value = false } }
async function save() { if (templates.value.some(item => !item.confirmed)) { ElMessage.warning('请分别确认真实模板与关键词；不需要的阶段请完整清空后确认'); return }; saving.value = true; try { await saveNoticeConfig({ templates: templates.value.map(item => ({ phase: item.phase, tid: item.tid, kid_list: String(item.kid_list_text || '').split(/[，,;；\s]+/).filter(Boolean).map(Number), scene_desc: item.scene_desc || '', fields: item.fields, confirmed: true })) }); ElMessage.success('配置已保存；请在框架通知管理获取并开启，投递结果以实际状态为准'); await load() } catch (cause) { ElMessage.error(errorText(cause, '模板未保存，请核对真实编号与关键词。')) } finally { saving.value = false } }
watch(visible, value => { if (value) void load(); else sequence++ })
onBeforeUnmount(() => { sequence++ })
</script>
