<template>
    <HsxDrawer v-model="visible" title="商户工单通知" subtitle="统一使用牛云通知渠道；工单始终保留在商户工作台，不以消息送达代替业务状态。" size="lg" :body-loading="loading" :confirm-loading="saving">
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" />
        <el-tabs v-model="channel" class="notice-tabs">
            <el-tab-pane label="公众号" name="wechat" />
            <el-tab-pane label="短信" name="sms" />
            <el-tab-pane label="小程序" name="weapp" />
        </el-tabs>
        <div class="notice-channel-summary">
            <div><strong>{{ channelName }}</strong><p class="lead-muted">{{ currentSetting.message || channelHelp }}</p></div>
            <el-button @click="openFramework(channel)">打开框架配置</el-button>
        </div>
        <template v-if="channel !== 'sms'">
            <el-alert :title="channel === 'wechat' ? '先在本站公众号核对真实模板，再登记下面的字段映射；保存后到框架获取本站模板并开启。商户会员还需关注并绑定同一公众号身份。' : '先在本站小程序核对真实公共模板，再登记字段映射；保存后到框架获取并开启，商户需在小程序中主动订阅。'" type="info" :closable="false" show-icon class="lead-banner" />
            <el-collapse class="notice-phases">
                <el-collapse-item v-for="item in activeTemplates" :key="channel + item.phase" :name="item.phase">
                    <template #title><div class="notice-phase-heading"><strong>{{ item.name || phases[item.phase] }}</strong><el-tag size="small" :type="item.enabled ? 'success' : 'info'">{{ item.enabled ? '框架已开启' : item.configured ? '待框架配置' : '未配置映射' }}</el-tag><el-tag v-if="isDirty(item, channel)" size="small" type="warning">未保存</el-tag></div></template>
                    <p class="lead-muted">{{ item.message || '模板消息仅包含工单提示，不包含客户姓名、电话和地址。' }}</p>
                    <el-form label-position="top" class="lead-form" :disabled="saving">
                        <template v-if="channel === 'weapp'">
                            <el-form-item label="真实微信公共模板编号（tid）"><el-input v-model.trim="item.tid" placeholder="从本站小程序模板库核对，不填写私有模板 ID" /></el-form-item>
                            <el-form-item label="关键词 ID 列表（kid_list）"><el-input v-model="item.kid_list_text" placeholder="填写该模板实际提供的关键词 ID，以逗号分隔" /></el-form-item>
                            <el-form-item label="使用场景"><el-input v-model="item.scene_desc" maxlength="100" /></el-form-item>
                        </template>
                        <el-form-item v-else label="公众号模板库编号（temp_key）"><el-input v-model.trim="item.temp_key" placeholder="按本站公众号真实模板库编号填写，不编造默认值" /></el-form-item>
                        <el-form-item label="真实关键词与允许变量">
                            <div v-for="(field, index) in item.fields" :key="index" class="notice-field-row">
                                <el-input v-model="field.label" aria-label="关键词名称" placeholder="真实关键词名称" maxlength="30" />
                                <el-input v-model="field.keyword" aria-label="关键词字段" :placeholder="channel === 'wechat' ? '如 keyword1，以实际模板为准' : '如 thing1，以实际模板为准'" maxlength="40" />
                                <el-select v-model="field.variable" aria-label="通知变量" placeholder="选择通知变量"><el-option v-for="variable in variables" :key="variable.key" :label="variable.label" :value="variable.key" /></el-select>
                                <el-button link type="danger" @click="item.fields.splice(index, 1)">移除</el-button>
                            </div>
                            <el-button link type="primary" @click="item.fields.push({ label: '', keyword: '', variable: '' })">添加关键词映射</el-button>
                        </el-form-item>
                        <div class="notice-phase-footer"><el-checkbox v-model="item.confirmed">我已核对本阶段的真实模板与关键词</el-checkbox><el-button link type="danger" @click="clearPhase(item)">清空本阶段映射</el-button></div>
                    </el-form>
                </el-collapse-item>
            </el-collapse>
            <el-empty v-if="!loading && !activeTemplates.length && !error" description="尚未返回通知场景，请刷新后重试" :image-size="70" />
        </template>
        <template v-else>
            <div class="lead-card notice-sms-card"><h3>沿用框架短信设置，不另建一套发送服务</h3><ol><li>在“短信管理”配置本站短信服务商与真实模板。</li><li>在“通知设置”找到“商户新工单提醒”和“商户工单处理结果提醒”，配置短信内容并开启。</li><li>商户必须绑定本站会员，且该会员有有效手机号。短信按服务商规则计费。</li></ol><div class="lead-actions"><el-button type="primary" @click="openFramework('sms')">短信服务商配置</el-button><el-button @click="openFramework('notice')">通知场景与开关</el-button><el-button @click="openFramework('sms_records')">查看短信记录</el-button></div></div>
            <el-descriptions v-if="currentSetting.phases?.length" :column="1" border><el-descriptions-item v-for="item in currentSetting.phases" :key="item.phase" :label="phases[item.phase]">{{ item.message || item.status || (item.enabled ? '已开启，请核对真实投递记录' : '尚未开启') }}</el-descriptions-item></el-descriptions>
        </template>
        <el-collapse class="notice-extra"><el-collapse-item title="企业微信与站内待办说明"><p class="lead-muted">当前商户使用本站会员身份。企业微信通知面向已绑定的员工身份，不能把商户会员当作员工直接发送；本页不提供无绑定关系的企微开关。</p><p class="lead-muted">工单本身就是商户工作台的站内待办，目前不是独立站内信。配置齐全、已入队或框架有记录，都不等于客户已经收到通知。</p></el-collapse-item></el-collapse>
        <template #footer><div class="notice-footer"><span class="lead-muted">{{ channel === 'sms' ? '短信在框架配置，本页不重复保存' : changedTemplates.length ? '当前渠道有 ' + changedTemplates.length + ' 个阶段待保存；其他渠道不会被覆盖' : '当前渠道没有待保存修改' }}</span><div class="lead-actions"><el-button :disabled="saving" @click="visible = false">关闭</el-button><el-button :loading="loading" :disabled="saving" @click="load">刷新状态</el-button><el-button v-if="channel !== 'sms'" type="primary" :loading="saving" :disabled="loading || !!error || !changedTemplates.length" @click="save">保存当前渠道映射</el-button></div></div></template>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import { getNoticeConfig, saveNoticeConfig } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
type Channel = 'weapp' | 'wechat' | 'sms'
const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void }>()
const visible = computed({ get: () => props.modelValue, set: value => { if (!saving.value) emit('update:modelValue', value) } })
const router = useRouter(), channel = ref<Channel>('wechat')
const loading = ref(false), saving = ref(false), error = ref(''), notice = ref(''), templates = ref<Row[]>([]), wechatTemplates = ref<Row[]>([]), variables = ref<Row[]>([]), channelSettings = ref<Row[]>([])
const baselines = reactive<Record<string, string>>({})
const phases: Record<string, string> = { assigned: '商户新工单提醒', changed: '商户工单处理结果提醒' }
const routes: Record<string, string> = { weapp: '/site/channel/weapp/message', wechat: '/site/channel/wechat/message', sms: '/site/setting/sms/setting', notice: '/site/setting/notice/template', sms_records: '/site/setting/sms/records' }
const channelName = computed(() => ({ wechat: '公众号模板消息', sms: '短信通知', weapp: '小程序订阅消息' })[channel.value])
const channelHelp = computed(() => channel.value === 'sms' ? '短信发送复用本站框架配置，有费用且需要真实手机号；不在此重复填写短信账号。' : '本页只维护已核验模板映射，渠道模板、开关与投递记录继续由牛云管理。')
const currentSetting = computed(() => channelSettings.value.find(item => item.channel === channel.value) || {})
const activeTemplates = computed(() => channel.value === 'wechat' ? wechatTemplates.value : channel.value === 'weapp' ? templates.value : [])
let sequence = 0
function payload(item: Row, current: Channel): Row {
    const common = { phase: item.phase, fields: (item.fields || []).map((field: Row) => ({ label: field.label || '', variable: field.variable || '', keyword: field.keyword || '' })) }
    return current === 'wechat' ? { ...common, temp_key: item.temp_key || '' }
        : { ...common, tid: item.tid || '', kid_list: String(item.kid_list_text || '').split(/[，,;；\s]+/).filter(Boolean).map(Number), scene_desc: item.scene_desc || '' }
}
function signature(item: Row, current: Channel) { return JSON.stringify(payload(item, current)) }
function isDirty(item: Row, current: Channel) { return signature(item, current) !== baselines[current + ':' + item.phase] }
const changedTemplates = computed(() => activeTemplates.value.filter(item => isDirty(item, channel.value)))
function mapRows(rows: Row[], current: Channel, retainEdits = false) {
    const existing = current === 'wechat' ? wechatTemplates.value : templates.value
    const dirty = new Map(retainEdits ? existing.filter(item => isDirty(item, current)).map(item => [item.phase, item]) : [])
    return rows.map(item => {
        const mapped = { ...item, tid: item.tid || '', temp_key: item.temp_key || '', kid_list_text: Array.isArray(item.kid_list) ? item.kid_list.join(',') : String(item.kid_list || ''), fields: (item.fields || []).map((field: Row) => ({ ...field })), confirmed: false }
        baselines[current + ':' + item.phase] = signature(mapped, current)
        const previous = dirty.get(item.phase)
        return previous ? { ...mapped, ...payload(previous, current), kid_list_text: previous.kid_list_text, confirmed: previous.confirmed } : mapped
    })
}
function applyData(data: Row, only?: Channel, retainEdits = false) {
    if (!only || only === 'weapp') templates.value = mapRows(data.templates || [], 'weapp', retainEdits)
    if (!only || only === 'wechat') wechatTemplates.value = mapRows(data.wechat_templates || [], 'wechat', retainEdits)
    variables.value = data.variables || []; channelSettings.value = data.channel_settings || []; notice.value = data.notice || ''
}
async function load() {
    if (saving.value) return
    const token = ++sequence; loading.value = true; error.value = ''
    try { const result = await getNoticeConfig(); if (token === sequence && visible.value) applyData(result.data || {}, undefined, true) }
    catch (cause) { if (token === sequence) error.value = errorText(cause, '通知配置读取失败。') }
    finally { if (token === sequence) loading.value = false }
}
function clearPhase(item: Row) { item.tid = ''; item.temp_key = ''; item.kid_list_text = ''; item.scene_desc = ''; item.fields = []; item.confirmed = false }
async function openFramework(key: string) { if (!routes[key]) return; try { await router.push(routes[key]) } catch { ElMessage.warning('未能打开配置页，请核对当前岗位权限。') } }
async function save() {
    if (saving.value || loading.value || channel.value === 'sms' || !changedTemplates.value.length) return
    const current = channel.value, updates = changedTemplates.value
    if (updates.some(item => !item.confirmed)) { ElMessage.warning('请确认实际修改阶段的真实模板与关键词；清空映射也需确认'); return }
    const token = sequence; saving.value = true
    try {
        const values = updates.map(item => ({ ...payload(item, current), confirmed: true }))
        const result = await saveNoticeConfig(current === 'wechat' ? { wechat_templates: values } : { templates: values })
        if (token !== sequence || !visible.value) return
        applyData(result.data || {}, current)
        ElMessage.success('当前渠道已保存；请到框架获取并开启真实模板，其他渠道未被覆盖')
    } catch (cause) { if (token === sequence) ElMessage.error(errorText(cause, '模板未保存，请核对真实编号与关键词。')) }
    finally { if (token === sequence) saving.value = false }
}
watch(visible, value => { if (value) void load(); else { sequence++; loading.value = false; saving.value = false; templates.value = []; wechatTemplates.value = []; Object.keys(baselines).forEach(key => delete baselines[key]) } }, { immediate: true })
onBeforeUnmount(() => { sequence++ })
</script>
<style scoped>
.notice-tabs { margin-bottom: 10px; }
.notice-channel-summary { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
.notice-channel-summary p { margin: 6px 0 0; line-height: 1.7; }
.notice-phase-heading { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 8px 0; line-height: 1.7; }
.notice-field-row { display: grid; grid-template-columns: 1fr 1fr 1.2fr auto; gap: 8px; margin-bottom: 10px; width: 100%; }
.notice-phase-footer, .notice-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.notice-sms-card { margin-bottom: 18px; }
.notice-sms-card ol { color: var(--el-text-color-regular); line-height: 1.9; padding-left: 22px; margin: 12px 0 20px; }
.notice-extra { margin-top: 20px; }
@media (max-width: 640px) { .notice-channel-summary { flex-direction: column; gap: 12px; } .notice-field-row { grid-template-columns: 1fr 1fr; } .notice-field-row > :nth-child(3) { grid-column: 1 / -1; } .notice-footer > .lead-actions { width: 100%; justify-content: flex-end; } }
</style>
