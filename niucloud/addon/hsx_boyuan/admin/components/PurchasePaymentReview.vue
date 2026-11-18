<template>
    <section v-if="showEntry" class="payment-summary">
        <div class="heading"><div><h3>付款同步</h3><p v-if="p.summaryMessage" class="danger">{{ p.summaryMessage }}</p><p v-else-if="!p.enabled && !p.summary?.task_id" class="secondary">当前账套未开启付款同步。</p></div>
            <el-button v-if="p.canCapture || p.summary?.task_id" :type="p.entryIsNew || p.entryLabel === '继续核对' ? 'primary' : 'default'" :disabled="p.locked" @click="p.open()">{{ p.entryLabel }}</el-button>
            <el-button v-else-if="canEnable" :disabled="enableBusy" @click="enableVisible = true">设置付款同步</el-button>
        </div>
    </section>
    <el-dialog :model-value="p.visible" :title="p.lookup ? (p.lookup === 'creator_id' ? '选择制单人' : '选择记账账户') : p.registrationDone && !p.candidateMode ? '付款回执' : '同步付款记录到博远'" width="min(680px, 94vw)" top="5vh" append-to-body :close-on-click-modal="false" :close-on-press-escape="!p.locked" @update:model-value="value => { if (!value) close() }">
        <div class="payment-body" v-loading="p.loading">
            <p v-if="!p.uatContext" class="secondary">当前账套只可查看，不可同步付款。</p>
            <template v-if="p.lookup">
                <p class="secondary">仅显示可用档案；记账账户须属于原采购公司。</p>
                <el-input v-model="p.keyword" clearable placeholder="搜索已有档案名称 / 编码" @keyup.enter="p.loadChoices(false)" @clear="p.loadChoices(false)"><template #append><el-button :loading="p.choiceLoading" @click="p.loadChoices(false)">搜索</el-button></template></el-input>
                <el-alert v-if="p.lookupError" :title="p.lookupError" type="error" :closable="false" class="notice" />
                <el-table :data="p.choices" max-height="36vh" v-loading="p.choiceLoading" :empty-text="p.lookupError ? '读取未完成，请重试' : p.lookup === 'account_id' ? '没有本采购公司的可用测试账户，请同步或核对基础资料' : '没有匹配的当前启用档案'"><el-table-column prop="name" label="已有档案" min-width="230" /><el-table-column label="公司" min-width="180"><template #default="{ row }">{{ row.extra?.company_name || '已按原采购公司范围筛选' }}</template></el-table-column><el-table-column width="80"><template #default="{ row }"><el-button link type="primary" :disabled="p.locked || !p.editable" @click="p.choose(row)">选择</el-button></template></el-table-column></el-table>
                <el-button v-if="p.cursor" :loading="p.choiceLoading" class="more" @click="p.loadChoices(true)">加载更多档案</el-button>
            </template>
            <template v-else-if="p.candidateMode">
                <h4>选择 ERP 已确认的付款</h4><p class="secondary">选中后还可核对，不会立即同步。</p>
                <el-table :data="p.candidates" max-height="38vh" :empty-text="p.error ? '读取失败，未确认候选付款' : '暂无符合条件的已确认付款'"><el-table-column prop="settlement_no" label="本站付款号" min-width="180" /><el-table-column label="确认时间" min-width="170"><template #default="{ row }">{{ purchaseCheckedAt(row.confirmed_at) }}</template></el-table-column><el-table-column label="金额" min-width="110"><template #default="{ row }">{{ purchaseMoney(row.amount) }}</template></el-table-column><el-table-column label="状态 / 操作" min-width="160"><template #default="{ row }"><el-button v-if="row.eligible === true && !row.task_id" link type="primary" :disabled="p.locked || !p.canCapture" @click="p.capture(row)">核对这笔付款</el-button><span v-else-if="!row.task_id">{{ row.reason || '不符合登记条件' }}</span><el-button v-if="row.task_id" link :disabled="p.locked" @click="p.openExisting(row)">查看原记录</el-button></template></el-table-column></el-table>
                <el-button v-if="p.candidateMore" :loading="p.loading" @click="p.loadCandidates(true)">加载更多已确认付款</el-button>
            </template>
            <template v-else-if="p.taskView">
                <el-alert v-if="p.registrationDone || p.isUnknown" :title="p.resultMessage" :type="p.registrationDone ? 'success' : 'warning'" :closable="false" show-icon/>
                <h4 v-else>{{ p.resultMessage }}</h4>
                <dl><dt>{{ p.registrationDone ? '同步金额' : '本笔金额' }}</dt><dd class="amount">{{ purchaseMoney(p.taskView.source.amount) }}</dd>
                    <template v-if="p.taskView.task.external_no"><dt>博远付款单号</dt><dd>{{ p.taskView.task.external_no }}</dd></template>
                    <template v-if="p.editable"><dt>付款对象</dt><dd>{{ p.labels.supplier?.name || '未取得' }}</dd><dt>记账公司</dt><dd>{{ p.labels.company?.name || '未取得' }}</dd></template>
                </dl>
                <div v-if="p.editable" class="selection-grid">
                    <div><label>博远记账账户</label><el-button :disabled="p.locked" @click="p.openLookup('account_id')">{{ p.labels.account?.name || '选择账户' }}</el-button></div>
                    <div><label>制单人</label><el-button :disabled="p.locked" @click="p.openLookup('creator_id')">{{ p.labels.creator?.name || '选择制单人' }}</el-button></div>
                </div>
                <p v-if="p.editable" class="secondary">只同步 ERP 付款记录，不会再次扣款。</p>
                <el-checkbox v-if="p.editable && p.needsExperiment && !p.saveFirst" v-model="p.experimentAck" class="experiment" :disabled="p.locked">{{ PAYMENT_EXPERIMENT_WARNING }}</el-checkbox>
                <p v-if="p.taskView.task.status === 'awaiting_confirmation' && !p.editable && !p.isUnknown" class="secondary">当前只可查看。请有权限的财务核对账套及同步设置。</p>
                <p v-for="issue in p.issues" :key="issue" class="danger">{{ issue }}</p>
                <el-button link class="details-toggle" :aria-expanded="p.detailsOpen" @click="p.detailsOpen = !p.detailsOpen">{{ p.detailsOpen ? '收起明细' : '查看明细' }}</el-button>
                <div v-if="p.detailsOpen" class="details">
                    <dl><dt>ERP 付款号</dt><dd>{{ p.taskView.source.settlement_no || '未取得' }}</dd><dt>博远采购单号</dt><dd>{{ p.taskView.source.external_purchase_no || '未取得' }}</dd><dt>财务确认时间</dt><dd>{{ purchaseCheckedAt(p.taskView.source.confirmed_at) }}</dd></dl>
                    <p v-if="!p.registrationDone && !p.editable && p.taskView.task.reason">{{ p.taskView.task.reason }}</p>
                    <p>博远应付形成：{{ paymentApLabel(p.apEvidence) }}。采购核销由博远处理；回执不是核销结果或银行转账凭证。</p>
                    <el-button v-if="p.canChooseAnother" link :disabled="p.locked || p.dirty" @click="p.loadCandidates(false)">查看本批其他付款</el-button>
                </div>
            </template>
            <el-alert v-if="p.error" :title="p.error" type="error" :closable="false" class="notice" />
        </div>
        <template #footer><div class="footer">
            <el-button v-if="p.lookup" @click="p.exitLookup">返回核对</el-button>
            <el-button v-else-if="p.registrationDone && !p.candidateMode" type="primary" @click="close">完成</el-button>
            <template v-else><el-button :disabled="p.locked" @click="close">关闭</el-button>
                <el-button v-if="p.editable && !p.candidateMode && p.saveFirst" type="primary" :disabled="p.locked || !p.selectionChanged" :loading="p.busy" @click="p.save">保存核对（不发送）</el-button>
                <el-button v-else-if="p.editable && !p.candidateMode" type="primary" :disabled="!p.canSubmit || p.locked" :loading="p.busy" @click="submit">同步到博远</el-button>
                <el-button v-else-if="!p.candidateMode || p.error" :disabled="p.locked || p.dirty" :loading="p.loading" @click="p.refresh">刷新状态</el-button>
            </template>
        </div></template>
    </el-dialog>
    <el-dialog :model-value="enableVisible" title="启用UAT付款登记实验" width="min(560px, 94vw)" append-to-body :close-on-click-modal="false" :close-on-press-escape="!enableBusy" @update:model-value="value => { if (!value && !enableBusy) enableVisible = false }"><el-alert title="仅启用UAT账务登记实验，不是银行转账；不会自动提交任何付款任务。" type="warning" :closable="false" /><p>目标UAT账套：{{ config.profiles?.test?.account_id }}</p><el-input v-model="enableAccount" placeholder="请手动输入目标账套代码确认" :disabled="enableBusy" /><el-checkbox v-model="enableAck" :disabled="enableBusy" class="enable-ack">我确认只启用UAT登记实验，应付形成和指定核销尚未核实，不是银行转账，每笔仍需确认。</el-checkbox><el-alert v-if="enableError" :title="enableError" type="error" :closable="false" class="notice" /><template #footer><el-button :disabled="enableBusy" @click="enableVisible = false">取消</el-button><el-button type="primary" :loading="enableBusy" :disabled="!enableAck || !enableAccount.trim() || enableBusy" @click="enableUat">确认启用UAT实验</el-button></template></el-dialog>
</template>
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import * as api from '../api/purchase'
import { purchaseCheckedAt, purchaseMoney } from '../utils/purchase-form'
import { PAYMENT_EXPERIMENT_WARNING, paymentApLabel, usePurchasePayment } from '../utils/purchase-payment'
const props = defineProps<{ purchase: any, config: any }>()
const emit = defineEmits<{ (event: 'changed'): void, (event: 'config-changed', config: any): void }>()
const p = reactive(usePurchasePayment(props, api, text => ElMessage.info(text), () => emit('changed')))
const enableVisible = ref(false), enableAccount = ref(''), enableAck = ref(false), enableBusy = ref(false), enableError = ref('')
const configReady = (value: any) => value?.mode === 'test' && value?.profiles?.test?.protocol === 'openapi_v153' && value.profiles.test.endpoint === 'https://open-api-uat.139erp.com/api' && value.profiles.test.gateway_path === '/gateway.do' && value.profiles.test.connection_key === props.purchase?.task?.connection_key && !!value.profiles.test.probe_at && value.profiles.test.contract_confirmed === true && value.enabled === true && value.purchase_enabled === true && value.paused === false
const canEnable = computed(() => props.purchase?.can_operate !== false && p.uatContext && !props.config.uat_payment_enabled && configReady(props.config))
async function enableUat() {
    if (!canEnable.value || enableBusy.value || !enableAck.value || !enableAccount.value.trim()) return
    enableBusy.value = true; enableError.value = ''
    try {
        const latest: any = await api.getConfig()
        if (!configReady(latest.data) || enableAccount.value.trim() !== latest.data.profiles.test.account_id) throw new Error('连接条件已变化或账套代码不符，请重新核对；未修改开关')
        const result: any = await api.saveConfig({ revision: latest.data.revision, mode: 'test', uat_payment_enabled: true, confirm_account: enableAccount.value.trim() })
        if (result.data?.uat_payment_enabled !== true) throw new Error('未确认开关已开启，请刷新配置核对')
        emit('config-changed', result.data); enableVisible.value = false; enableAccount.value = ''; enableAck.value = false; ElMessage.info('UAT实验开关已开启，未提交任何付款登记')
    } catch (err: any) { enableError.value = String(err?.msg || err?.message || '开关未保存，请核对配置权限').slice(0, 400) }
    finally { enableBusy.value = false }
}
const showEntry = computed(() => p.summary && p.summary.status !== 'permission_denied' && (p.summary.task_id || p.canCapture || (p.hasPayment && canEnable.value && props.purchase?.task?.status === 'succeeded')))
const confirm = async (text: string, title: string, button: string) => { try { await ElMessageBox.confirm(text, title, { type: 'warning', confirmButtonText: button, cancelButtonText: '返回核对', closeOnClickModal: false, customStyle: { whiteSpace: 'pre-line' } }); return true } catch { return false } }
function submit() { return p.submit(text => confirm(text, '同步这笔付款记录？', '确认同步')) }
function close() { return p.close(() => confirm('尚有未保存的选择，关闭后将丢弃。不会发起同步。', '放弃本次修改？', '放弃修改')) }
</script>
<style scoped lang="scss">
.payment-summary { padding: 16px 20px; margin-bottom: 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; background: var(--el-bg-color); }.heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; }h3,h4 { margin: 0; font-size: 15px; }h4 { margin: 18px 0 8px; }small { font-size: 11px; color: var(--el-color-warning); margin-left: 8px; }p { font-size: 12px; line-height: 1.8; margin: 8px 0; overflow-wrap: anywhere; }.secondary { color: var(--el-text-color-regular); }.ap-node { display: flex; gap: 16px; flex-wrap: wrap; align-items: baseline; font-size: 12px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--el-border-color-lighter); }.ap-node span { color: var(--el-color-warning-dark-2); }.payment-body { max-height: 62vh; overflow-y: auto; padding-right: 4px; }.notice { margin-top: 12px; }dl { display: grid; grid-template-columns: 130px minmax(0,1fr); gap: 10px 16px; font-size: 13px; margin: 18px 0; }dt { color: var(--el-text-color-regular); }dd { margin: 0; overflow-wrap: anywhere; }.amount { font-size: 18px; font-weight: 600; }.selection-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }.selection-grid label { display: block; font-size: 12px; color: var(--el-text-color-regular); margin-bottom: 8px; }.selection-grid .el-button { max-width: 100%; height: auto; min-height: 34px; white-space: normal; text-align: left; }.experiment { margin-top: 16px; padding: 12px; background: var(--el-color-warning-light-9); border: 1px solid var(--el-color-warning-light-5); border-radius: 4px; }.experiment :deep(.el-checkbox) { height: auto; white-space: normal; align-items: flex-start; }.experiment :deep(.el-checkbox__label) { white-space: normal; line-height: 1.7; }.danger { color: var(--el-color-danger); }.footer { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }.footer .el-button + .el-button { margin-left: 0; }.more { margin-top: 12px; }@media(max-width:800px) { .heading { align-items: flex-start; flex-direction: column; }.selection-grid { grid-template-columns: 1fr; }dl { grid-template-columns: 110px minmax(0,1fr); }.footer { justify-content: flex-start; } }
.enable-ack { margin-top: 16px; height: auto; white-space: normal; align-items: flex-start; }.enable-ack :deep(.el-checkbox__label) { white-space: normal; line-height: 1.8; }
.details-toggle { margin-top: 10px; }.details { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--el-border-color-lighter); }.details p { color: var(--el-text-color-regular); }.experiment.el-checkbox { height: auto; white-space: normal; line-height: 1.6; }.experiment :deep(.el-checkbox__label) { line-height: 1.6; }
</style>
