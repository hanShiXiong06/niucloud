<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">营销任务</div>
                    <p>把回收交货等真实业务变成可追踪任务，奖励由积分、成长值或商城优惠券提供。</p>
                </div>
                <el-button type="primary" @click="openEditor()">新建活动</el-button>
            </div>

            <div class="summary">
                <div><span>当前活动</span><strong>{{ total }}</strong></div>
                <div><span>运行中</span><strong class="success">{{ runningCount }}</strong></div>
                <div class="summary-tip">规则、进度、发放和通知均有台账；旧“订单完成积分”配置建议关闭，避免重复奖励。</div>
            </div>

            <div class="toolbar">
                <el-input v-model="query.keyword" clearable placeholder="搜索活动名称" class="search" @keyup.enter="load" />
                <el-select v-model="query.status" clearable placeholder="全部状态" class="status-select" @change="load">
                    <el-option v-for="(name, value) in metadata.campaign_statuses" :key="value" :label="name" :value="Number(value)" />
                </el-select>
                <el-button @click="load">查询</el-button>
            </div>

            <el-table :data="rows" v-loading="loading" row-key="id">
                <el-table-column label="活动" min-width="260">
                    <template #default="{ row }">
                        <div class="campaign-title">{{ row.title }}</div>
                        <div class="secondary">{{ row.subtitle || '暂无活动说明' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="任务目标" width="180">
                    <template #default="{ row }">{{ factName(row.fact_key) }} · {{ compact(row.target_value) }}{{ row.target_unit }}</template>
                </el-table-column>
                <el-table-column label="参与/发放" width="150">
                    <template #default="{ row }"><div>{{ row.participation_mode === 'auto' ? '自动参与' : '手动领取任务' }}</div><div class="secondary">{{ row.grant_mode === 'auto' ? '奖励自动到账' : '奖励手动领取' }}</div></template>
                </el-table-column>
                <el-table-column label="任务进度" width="140">
                    <template #default="{ row }">{{ row.completed_count || 0 }} / {{ row.claim_count || 0 }} 人达标</template>
                </el-table-column>
                <el-table-column label="活动时间" width="210">
                    <template #default="{ row }"><div>{{ date(row.start_at) }}</div><div class="secondary">至 {{ date(row.end_at) }}</div></template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }"><el-tag :type="statusType(row.status)" effect="plain">{{ row.status_name }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" width="245" fixed="right" align="right">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="openEditor(row)">编辑</el-button>
                        <el-button v-if="row.status !== 1" link type="success" @click="setStatus(row, 1)">启用</el-button>
                        <el-button v-else link type="warning" @click="setStatus(row, 2)">暂停</el-button>
                        <el-button link type="danger" @click="remove(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total,prev,pager,next" :total="total" @current-change="load" /></div>

            <el-drawer v-model="editorVisible" size="min(760px, 96vw)" :title="form.id ? '编辑营销活动' : '新建营销活动'" destroy-on-close>
                <el-form label-position="top" class="editor-form">
                    <section>
                        <h3>基本信息</h3>
                        <div class="form-grid">
                            <el-form-item label="活动名称" class="span-2"><el-input v-model="form.title" maxlength="120" show-word-limit placeholder="例如：每月交货满 10 台送商城券" /></el-form-item>
                            <el-form-item label="活动说明" class="span-2"><el-input v-model="form.subtitle" maxlength="255" placeholder="用户看到的一句话说明" /></el-form-item>
                            <el-form-item label="活动时间" class="span-2"><el-date-picker v-model="form.date_range" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期" end-placeholder="结束日期" class="!w-full" /></el-form-item>
                            <el-form-item label="参与方式"><el-radio-group v-model="form.participation_mode"><el-radio-button value="manual">用户领任务</el-radio-button><el-radio-button value="auto">自动参与</el-radio-button></el-radio-group></el-form-item>
                            <el-form-item label="统计周期"><el-select v-model="form.cycle_type" class="w-full"><el-option label="自然月" value="calendar_month" /><el-option label="整个活动周期" value="fixed" /><el-option label="领取后若干天" value="rolling_days" /></el-select></el-form-item>
                            <el-form-item v-if="form.cycle_type === 'rolling_days'" label="滚动周期天数"><el-input-number v-model="form.cycle_days" :min="1" :max="365" /></el-form-item>
                        </div>
                    </section>

                    <section>
                        <h3>参与门槛</h3>
                        <el-form-item label="参与资格">
                            <el-select v-model="form.qualification_key" clearable class="w-full" placeholder="不限身份，所有会员均可参与">
                                <el-option v-for="option in metadata.qualification_options" :key="option.key" :label="option.name" :value="option.key">
                                    <span>{{ option.name }}</span><span class="option-desc">{{ option.description }}</span>
                                </el-option>
                            </el-select>
                            <div class="form-help">选择“商城同行身份”后，未满足用户直接进入现有同行申请页，继续使用万能表单、企微审核和审核结果通知，无需重复配置入口。</div>
                        </el-form-item>
                    </section>

                    <section>
                        <h3>任务规则</h3>
                        <div class="rule-row">
                            <el-select v-model="form.fact_key" class="rule-fact">
                                <el-option v-for="fact in metadata.fact_options" :key="fact.key" :label="fact.name" :value="fact.key" :disabled="fact.disabled" />
                            </el-select>
                            <span>累计达到</span>
                            <el-input-number v-model="form.target_value" :min="1" :precision="0" />
                            <span>{{ selectedFact?.unit || '次' }}</span>
                        </div>
                        <div v-if="form.fact_key === 'recycle_device_delivered'" class="amount-filter">
                            <span>仅统计成交价</span>
                            <el-input-number v-model="form.fact_filter_json.min_amount" :min="0" :precision="2" placeholder="最低价" />
                            <span>至</span>
                            <el-input-number v-model="form.fact_filter_json.max_amount" :min="0" :precision="2" placeholder="最高价" />
                            <span>元的设备</span>
                        </div>
                        <div v-if="form.fact_key === 'recycle_device_delivered'" class="form-help">填 0 表示不限制。系统按每台设备最终成交价判断，低价值设备不会计入任务；发生退货时按设备逐台冲减。</div>
                    </section>

                    <section>
                        <div class="section-title"><h3>达标奖励</h3><el-button link type="primary" @click="addReward">添加奖励</el-button></div>
                        <div v-for="(reward, index) in form.rewards" :key="reward.uid" class="reward-row">
                            <div class="reward-index">{{ index + 1 }}</div>
                            <div class="reward-body">
                                <div class="reward-grid">
                                    <el-select v-model="reward.provider_type" placeholder="奖励类型" @change="onProviderChange(reward)">
                                        <el-option v-for="provider in metadata.providers" :key="providerKey(provider)" :label="provider.name" :value="providerKey(provider)" />
                                    </el-select>
                                    <el-input v-model="reward.reward_name" placeholder="奖励显示名称" />
                                    <template v-if="reward.provider_key === 'member'">
                                        <el-input-number v-model="reward.reward_value" :min="0.01" :precision="2" class="!w-full" />
                                    </template>
                                    <template v-else>
                                        <el-select v-model="reward.reward_config_json.option_id" filterable placeholder="选择具体奖励" :loading="reward.optionsLoading" @change="syncOptionName(reward)">
                                            <el-option v-for="option in reward.options" :key="option.id" :label="option.name" :value="option.id"><span>{{ option.name }}</span><span class="option-desc">{{ option.description }}</span></el-option>
                                        </el-select>
                                    </template>
                                    <el-input-number v-model="reward.reward_quantity" :min="1" :max="999" class="!w-full" />
                                </div>
                            </div>
                            <el-button link type="danger" @click="form.rewards.splice(index, 1)">移除</el-button>
                        </div>
                        <el-empty v-if="!form.rewards.length" :image-size="64" description="请至少添加一项奖励" />
                    </section>

                    <section>
                        <h3>发放与提醒</h3>
                        <div class="form-grid">
                            <el-form-item label="奖励发放"><el-radio-group v-model="form.grant_mode"><el-radio-button value="manual">用户领取</el-radio-button><el-radio-button value="auto">自动到账</el-radio-button></el-radio-group></el-form-item>
                            <el-form-item v-if="form.grant_mode === 'manual'" label="领取有效期"><el-input-number v-model="form.claim_valid_days" :min="1" :max="365" /><span class="suffix">天</span></el-form-item>
                            <el-form-item label="失效前提醒" class="span-2"><el-checkbox-group v-model="form.expire_notice_days"><el-checkbox :value="7">提前 7 天</el-checkbox><el-checkbox :value="3">提前 3 天</el-checkbox><el-checkbox :value="1">提前 1 天</el-checkbox></el-checkbox-group></el-form-item>
                        </div>
                    </section>
                </el-form>
                <template #footer><el-button @click="editorVisible = false">取消</el-button><el-button type="primary" :loading="saving" @click="save">保存活动</el-button></template>
            </el-drawer>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { addMarketingCampaign, changeMarketingCampaignStatus, deleteMarketingCampaign, editMarketingCampaign, getMarketingCampaign, getMarketingCampaigns, getMarketingMetadata, getMarketingProviderOptions } from '../../api'

const query = reactive({ status: '' as any, keyword: '', page: 1, limit: 15 })
const rows = ref<any[]>([]), total = ref(0), loading = ref(false), saving = ref(false), editorVisible = ref(false)
const metadata = reactive<any>({ campaign_statuses: {}, fact_options: [], providers: [], member_levels: [], qualification_options: [] })
const emptyForm = () => ({ id: 0, title: '', subtitle: '', status: 0, date_range: [] as string[], participation_mode: 'manual', cycle_type: 'calendar_month', cycle_days: 30, allowed_level_ids: [] as number[], qualification_key: '', application_url: '', fact_key: 'recycle_device_delivered', fact_filter_json: { min_amount: 0, max_amount: 0 }, target_value: 10, grant_mode: 'manual', claim_valid_days: 7, expire_notice_days: [7, 3, 1], notice_channels: ['weapp', 'wechat', 'sms'], rewards: [] as any[], description: '', sort: 0 })
const form = reactive<any>(emptyForm())
const selectedFact = computed(() => metadata.fact_options.find((item: any) => item.key === form.fact_key))
const runningCount = computed(() => rows.value.filter(row => Number(row.status) === 1).length)

const providerKey = (provider: any) => `${provider.provider_key}:${provider.reward_type}`
const compact = (value: any) => Number(value || 0).toString()
const date = (value: any) => value ? new Date(Number(value) * 1000).toLocaleDateString('zh-CN') : '—'
const factName = (key: string) => metadata.fact_options.find((item: any) => item.key === key)?.name || key
const statusType = (status: number) => ({ 0: 'info', 1: 'success', 2: 'warning', 3: 'info' } as any)[status] || 'info'
const normalizeReward = (reward: any = {}) => ({ uid: Math.random().toString(36).slice(2), provider_type: reward.provider_key ? `${reward.provider_key}:${reward.reward_type}` : 'member:point', provider_key: reward.provider_key || 'member', reward_type: reward.reward_type || 'point', reward_name: reward.reward_name || '会员积分', reward_value: Number(reward.reward_value || 0), reward_quantity: Number(reward.reward_quantity || 1), reward_config_json: reward.reward_config_json || {}, options: [] as any[], optionsLoading: false })

const load = async () => { loading.value = true; try { const data: any = (await getMarketingCampaigns(query)).data || {}; rows.value = data.data || []; total.value = Number(data.total || 0) } finally { loading.value = false } }
const loadMetadata = async () => { Object.assign(metadata, (await getMarketingMetadata()).data || {}) }
const openEditor = async (row?: any) => {
    Object.assign(form, emptyForm())
    if (row?.id) {
        const data: any = (await getMarketingCampaign(Number(row.id))).data || {}
        Object.assign(form, data, { id: Number(data.id), date_range: [dateValue(data.start_at), dateValue(data.end_at)], fact_filter_json: data.fact_filter_json || { min_amount: 0, max_amount: 0 }, rewards: (data.rewards || []).map(normalizeReward) })
        for (const reward of form.rewards) if (reward.provider_key !== 'member') await loadOptions(reward)
    } else {
        form.qualification_key = metadata.qualification_options.find((item: any) => item.key === 'shop_goods_forward')?.key || ''
        addReward()
    }
    editorVisible.value = true
}
const dateValue = (value: any) => { const d = new Date(Number(value) * 1000); return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-') }
const addReward = () => form.rewards.push(normalizeReward())
const onProviderChange = async (reward: any) => { [reward.provider_key, reward.reward_type] = reward.provider_type.split(':'); reward.reward_config_json = {}; reward.reward_value = 0; const provider = metadata.providers.find((item: any) => providerKey(item) === reward.provider_type); reward.reward_name = provider?.name || ''; reward.options = []; if (reward.provider_key !== 'member') await loadOptions(reward) }
const loadOptions = async (reward: any) => { reward.optionsLoading = true; try { reward.options = (await getMarketingProviderOptions({ provider_key: reward.provider_key, reward_type: reward.reward_type })).data || [] } finally { reward.optionsLoading = false } }
const syncOptionName = (reward: any) => { const option = reward.options.find((item: any) => Number(item.id) === Number(reward.reward_config_json.option_id)); if (option) reward.reward_name = option.name }
const save = async () => {
    if (!form.date_range?.length) return ElMessage.warning('请选择活动时间')
    if (!form.rewards.length) return ElMessage.warning('请至少添加一项奖励')
    saving.value = true
    try {
        const payload = { ...form, start_at: form.date_range[0], end_at: form.date_range[1], rewards: form.rewards.map(({ uid, provider_type, options, optionsLoading, ...item }: any) => item) }
        form.id ? await editMarketingCampaign(form.id, payload) : await addMarketingCampaign(payload)
        ElMessage.success('活动已保存'); editorVisible.value = false; await load()
    } finally { saving.value = false }
}
const setStatus = async (row: any, status: number) => { await changeMarketingCampaignStatus(Number(row.id), status); ElMessage.success(status === 1 ? '活动已启用' : '活动已暂停'); load() }
const remove = async (row: any) => { await ElMessageBox.confirm('删除后无法恢复，确认删除这个活动吗？', '删除活动', { type: 'warning' }); await deleteMarketingCampaign(Number(row.id)); ElMessage.success('已删除'); load() }
onMounted(async () => { await loadMetadata(); await load() })
</script>

<style scoped lang="scss">
.page-head,.toolbar,.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px}.page-head{align-items:flex-start;margin-bottom:18px}.page-head p{margin:6px 0 0;color:var(--el-text-color-secondary)}.summary{display:flex;align-items:center;gap:42px;margin-bottom:16px;padding:16px 18px;border-radius:10px;background:linear-gradient(110deg,#f4f7ff,#f8fafc)}.summary>div:not(.summary-tip){display:flex;flex-direction:column;gap:5px}.summary span{color:var(--el-text-color-secondary);font-size:13px}.summary strong{font-size:25px}.summary .success{color:#059669}.summary-tip{margin-left:auto;max-width:520px;color:var(--el-text-color-secondary);font-size:12px}.toolbar{justify-content:flex-start;margin-bottom:14px}.search{width:260px}.status-select{width:150px}.campaign-title{font-weight:650}.secondary,.form-help{margin-top:4px;color:var(--el-text-color-secondary);font-size:12px}.pagination{display:flex;justify-content:flex-end;margin-top:16px}.editor-form section{margin-bottom:16px;padding:16px 18px;border:1px solid var(--el-border-color-lighter);border-radius:10px;background:#fff}.editor-form h3{margin:0 0 15px;font-size:16px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.span-2{grid-column:span 2}.w-full{width:100%}.rule-row,.amount-filter{display:flex;align-items:center;gap:12px}.amount-filter{margin-top:14px;padding:12px;border-radius:8px;background:var(--el-fill-color-light)}.rule-fact{flex:1}.reward-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-top:1px solid var(--el-border-color-lighter)}.reward-index{width:26px;height:26px;display:flex;align-items:center;justify-content:center;border-radius:7px;background:#eef3ff;color:#315efb;font-weight:700}.reward-body{flex:1;min-width:0}.reward-grid{display:grid;grid-template-columns:1fr 1.2fr 1fr 100px;gap:10px}.option-desc{float:right;margin-left:16px;color:var(--el-text-color-secondary);font-size:12px}.suffix{margin-left:8px;color:var(--el-text-color-secondary)}@media(max-width:760px){.summary{align-items:flex-start;flex-wrap:wrap}.summary-tip{width:100%;margin-left:0}.form-grid,.reward-grid{grid-template-columns:1fr}.span-2{grid-column:span 1}.rule-row,.amount-filter{align-items:stretch;flex-direction:column}}
</style>
