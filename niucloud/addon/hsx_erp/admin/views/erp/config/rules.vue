<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">业务规则</div>
                    <div class="mt-1 text-sm text-gray-500">控制 ERP 的主流程分支，默认路径保持稳定，差异业务通过配置开关承接。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :loading="loading" @click="loadConfig">刷新</el-button>
                    <el-button type="primary" :loading="saving" @click="submit">保存规则</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="rule-section">
                    <div class="section-title">财务规则</div>
                    <el-form label-width="180px">
                        <el-form-item label="启用折账">
                            <el-switch v-model="form.finance.enable_offset" :active-value="1" :inactive-value="0" />
                            <span class="ml-3 text-sm text-gray-500">开启后仅财务可发起，系统不自动折账。</span>
                        </el-form-item>
                        <el-form-item label="财务确认后锁定">
                            <el-switch v-model="form.finance.finance_fact_lock" :active-value="1" :inactive-value="0" disabled />
                            <span class="ml-3 text-sm text-gray-500">收款、付款、折账后不能直接取消，只能退货或冲正。</span>
                        </el-form-item>
                        <el-form-item label="收付款必须选账户">
                            <el-switch v-model="form.finance.settlement_requires_account" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">自有商城渠道</div>
                    <el-alert class="mb-4" type="info" :closable="false" show-icon title="ERP 始终是主数据；商城只能消费 ERP 数据或维护自己的数据映射，不能反向修改 ERP 分类和规格。" />
                    <el-form label-width="180px">
                        <el-form-item label="启用商城联动">
                            <el-switch v-model="form.marketplace.channels.phone_shop.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="商城分类来源">
                            <div>
                                <el-radio-group v-model="form.marketplace.channels.phone_shop.category_mode" :disabled="form.marketplace.channels.phone_shop.enabled !== 1">
                                    <el-radio-button label="erp">消费 ERP 目录</el-radio-button>
                                    <el-radio-button label="independent">商城独立分类</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs text-gray-400">独立分类不会被 ERP 覆盖，由运营首次对应后保存映射，同类设备可自动复用。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="商城规格来源">
                            <div>
                                <el-radio-group v-model="form.marketplace.channels.phone_shop.spec_mode" :disabled="form.marketplace.channels.phone_shop.enabled !== 1">
                                    <el-radio-button label="erp">消费 ERP 规格</el-radio-button>
                                    <el-radio-button label="independent">商城独立规格</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs text-gray-400">独立规格由运营对应；映射只做翻译，不会改写 ERP 规格。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="渠道发布方式">
                            <div>
                                <el-radio-group v-model="form.marketplace.channels.phone_shop.publish_mode" :disabled="form.marketplace.channels.phone_shop.enabled !== 1">
                                    <el-radio-button label="direct">资料齐全直接发布</el-radio-button>
                                    <el-radio-button label="manual">商城运营逐台确认</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs text-gray-400">
                                    直接发布：ERP 资料齐全且已有必要映射时一键上架，缺少映射会自动转商城待办；逐台确认：所有 ERP 设备均由商城运营核对后发布。
                                </div>
                            </div>
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">自动任务默认负责人</div>
                    <el-alert class="mb-4" type="info" :closable="false" show-icon title="只需设置一次。应收应付或设备进入拍照、商城定价、资料上架环节时，系统自动写入责任人并通知本人。" />
                    <el-form label-width="180px">
                        <el-form-item v-for="stage in taskStages" :key="stage.stage_key" :label="stage.name">
                            <div class="flex items-center gap-3">
                                <el-select v-model="stage.default_uid" class="!w-[240px]" clearable placeholder="自动选择首位岗位员工">
                                    <el-option v-for="user in stage.users" :key="user.uid" :label="user.name" :value="user.uid">
                                        <span>{{ user.name }}</span><span class="float-right text-xs text-gray-400">{{ user.username }}</span>
                                    </el-option>
                                </el-select>
                                <span v-if="stage.users?.length" class="text-xs text-gray-400">候选人来自角色动作权限</span>
                                <el-tag v-else type="danger" effect="plain">未配置岗位权限</el-tag>
                            </div>
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">采购规则</div>
                    <el-form label-width="180px">
                        <el-form-item label="入库立即生成应付">
                            <el-switch v-model="form.purchase.create_payable_on_inbound" :active-value="1" :inactive-value="0" disabled />
                        </el-form-item>
                        <el-form-item label="未付款允许业务取消">
                            <el-switch v-model="form.purchase.allow_cancel_before_finance_fact" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">设备命名规则</div>
                    <el-alert
                        class="mb-4"
                        type="info"
                        :closable="false"
                        show-icon
                        title="采购开单选择分类和规格时，会按这里的规则自动生成设备名称。"
                    />
                    <el-form label-width="180px">
                        <el-form-item label="分类写入名称">
                            <el-radio-group v-model="form.product_title.category_mode">
                                <el-radio-button label="auto">自动</el-radio-button>
                                <el-radio-button label="level_1_2">一级+二级</el-radio-button>
                                <el-radio-button label="level_2_3">二级+三级</el-radio-button>
                                <el-radio-button label="level_3">仅末级</el-radio-button>
                                <el-radio-button label="full">完整路径</el-radio-button>
                            </el-radio-group>
                            <div class="mt-2 text-xs text-gray-500">自动：三级分类默认取二级+三级，二级分类取一级+二级，避免出现“手机 苹果 iPhone”这类冗余名称。</div>
                        </el-form-item>
                        <el-form-item label="规格写入名称">
                            <el-switch v-model="form.product_title.spec_in_title" :active-value="1" :inactive-value="0" />
                            <span class="ml-3 text-sm text-gray-500">如内存、容量、颜色等被标记为标题字段的规格。</span>
                        </el-form-item>
                        <el-form-item label="成色写入名称">
                            <el-switch v-model="form.product_title.grade_in_title" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="名称分隔符">
                            <el-input v-model="form.product_title.separator" class="!w-[160px]" placeholder="默认空格" />
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">销售规则</div>
                    <el-form label-width="180px">
                        <el-form-item label="出库立即生成应收">
                            <el-switch v-model="form.sale.create_receivable_on_outbound" :active-value="1" :inactive-value="0" disabled />
                        </el-form-item>
                        <el-form-item label="未收款允许业务取消">
                            <el-switch v-model="form.sale.allow_cancel_before_finance_fact" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="取消回原仓库">
                            <el-switch v-model="form.sale.return_to_original_location_on_cancel" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="允许同行未结算">
                            <el-switch v-model="form.sale.enable_peer_pending" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="允许客户试卖">
                            <el-switch v-model="form.sale.enable_trial_sale" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="利润确认口径">
                            <el-radio-group v-model="form.sale.profit_confirm_mode">
                                <el-radio-button label="settlement">结算确认</el-radio-button>
                                <el-radio-button label="outbound">出库预估</el-radio-button>
                            </el-radio-group>
                        </el-form-item>
                        <el-divider content-position="left">客户信用控制</el-divider>
                        <el-form-item label="启用开单信用检查">
                            <el-switch v-model="form.sale.credit_control.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="默认处理方式">
                            <el-select v-model="form.sale.credit_control.default_policy" class="!w-[220px]" :disabled="form.sale.credit_control.enabled !== 1">
                                <el-option label="正常交易，不提醒" value="normal" />
                                <el-option label="有欠款时提醒" value="remind" />
                                <el-option label="有欠款时仅现结" value="cash_only" />
                                <el-option label="有欠款时暂停交易" value="blocked" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="最低欠款金额">
                            <el-input-number v-model="form.sale.credit_control.min_outstanding_amount" :min="0" :precision="2" :controls="false" :disabled="form.sale.credit_control.enabled !== 1" />
                            <span class="ml-3 text-sm text-gray-500">达到该金额才触发，0 表示任意欠款。</span>
                        </el-form-item>
                        <el-form-item label="最低欠款账龄">
                            <el-input-number v-model="form.sale.credit_control.min_outstanding_days" :min="0" :max="3650" :precision="0" :disabled="form.sale.credit_control.enabled !== 1" />
                            <span class="ml-3 text-sm text-gray-500">最早未结应收达到该天数才触发，0 表示立即。</span>
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">整备与代卖</div>
                    <el-form label-width="180px">
                        <el-form-item label="启用整备流程">
                            <el-switch v-model="form.refurbish.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="入库默认整备">
                            <el-switch v-model="form.refurbish.default_required" :active-value="1" :inactive-value="0" :disabled="form.refurbish.enabled !== 1" />
                        </el-form-item>
                        <el-form-item label="整备跟踪方式">
                            <div>
                                <el-radio-group v-model="form.refurbish.tracking_mode" :disabled="form.refurbish.enabled !== 1">
                                    <el-radio-button label="simple">简易登记</el-radio-button>
                                    <el-radio-button label="external">外送追踪</el-radio-button>
                                </el-radio-group>
                                <div class="mt-1 text-xs text-gray-400">简易登记只在完工时记录服务商和费用；外送追踪会记录设备当前交给了哪家整备商。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="首页积压提醒">
                            <el-switch v-model="form.refurbish.daily_reminder_enabled" :active-value="1" :inactive-value="0" :disabled="form.refurbish.enabled !== 1" />
                        </el-form-item>
                        <el-form-item label="当日待整备阈值">
                            <div class="flex items-center gap-2">
                                <el-input-number v-model="form.refurbish.daily_reminder_threshold" :min="1" :max="999" :precision="0" :disabled="form.refurbish.daily_reminder_enabled !== 1" />
                                <span class="text-sm text-gray-500">台；达到后提醒老板及时分配处理</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="启用代卖">
                            <el-switch v-model="form.consignment.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="收款后生成寄售应付">
                            <el-switch v-model="form.consignment.settle_payable_after_receipt" :active-value="1" :inactive-value="0" :disabled="form.consignment.enabled !== 1" />
                        </el-form-item>
                        <el-form-item label="转自有必须重采">
                            <el-switch v-model="form.consignment.transfer_to_owned_requires_repurchase" :active-value="1" :inactive-value="0" disabled />
                        </el-form-item>
                    </el-form>
                </section>

                <section class="rule-section">
                    <div class="section-title">库存周转预警</div>
                    <el-alert class="mb-4" type="info" :closable="false" show-icon title="库龄按设备实际入库时间计算；阈值供库存中心、移动端和经营工作台统一使用。" />
                    <el-form label-width="180px">
                        <el-form-item label="关注起始天数">
                            <el-input-number v-model="form.turnover.attention_days" :min="1" :max="365" :precision="0" />
                        </el-form-item>
                        <el-form-item label="预警起始天数">
                            <el-input-number v-model="form.turnover.warning_days" :min="form.turnover.attention_days + 1" :max="730" :precision="0" />
                        </el-form-item>
                        <el-form-item label="严重滞销天数">
                            <el-input-number v-model="form.turnover.critical_days" :min="form.turnover.warning_days + 1" :max="1095" :precision="0" />
                        </el-form-item>
                        <el-form-item label="首页周转提醒">
                            <el-switch v-model="form.turnover.reminder_enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="提醒设备数量">
                            <div class="flex items-center gap-2">
                                <el-input-number v-model="form.turnover.reminder_count_threshold" :min="1" :max="9999" :precision="0" :disabled="form.turnover.reminder_enabled !== 1" />
                                <span class="text-sm text-gray-500">台达到预警或严重滞销后提醒</span>
                            </div>
                        </el-form-item>
                    </el-form>
                </section>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getErpConfig, saveErpConfig, getErpTaskAssignmentSettings, saveErpTaskAssignmentSettings } from '@/addon/hsx_erp/api/config'

const loading = ref(false)
const saving = ref(false)
const form = reactive(defaultRules())
const taskStages = ref<any[]>([])

function defaultRules() {
    return {
        finance: { enable_offset: 1, finance_fact_lock: 1, settlement_requires_account: 1 },
        purchase: { create_payable_on_inbound: 1, allow_cancel_before_finance_fact: 1 },
        product_title: { category_mode: 'auto', spec_in_title: 1, grade_in_title: 0, separator: ' ' },
        sale: { create_receivable_on_outbound: 1, allow_cancel_before_finance_fact: 1, return_to_original_location_on_cancel: 1, enable_peer_pending: 1, enable_trial_sale: 0, profit_confirm_mode: 'settlement', credit_control: { enabled: 1, default_policy: 'remind', min_outstanding_amount: 0, min_outstanding_days: 0 } },
        refurbish: { enabled: 1, default_required: 0, tracking_mode: 'simple', daily_reminder_enabled: 1, daily_reminder_threshold: 25, reminder_dismiss_date: '' },
        marketplace: {
            recycle_material_owner: 'erp',
            channels: { phone_shop: { enabled: 1, category_mode: 'erp', spec_mode: 'erp', publish_mode: 'direct' } }
        },
        turnover: { attention_days: 7, warning_days: 15, critical_days: 30, reminder_enabled: 1, reminder_count_threshold: 1, reminder_dismiss_date: '' },
        consignment: { enabled: 0, settle_payable_after_receipt: 1, transfer_to_owned_requires_repurchase: 1 }
    }
}

async function loadConfig() {
    loading.value = true
    try {
        const [res, taskRes]: any[] = await Promise.all([getErpConfig(), getErpTaskAssignmentSettings()])
        Object.assign(form.finance, res?.data?.finance || {})
        Object.assign(form.purchase, res?.data?.purchase || {})
        Object.assign(form.product_title, res?.data?.product_title || {})
        Object.assign(form.sale, res?.data?.sale || {})
        Object.assign(form.refurbish, res?.data?.refurbish || {})
        Object.assign(form.marketplace, res?.data?.marketplace || {})
        Object.assign(form.turnover, res?.data?.turnover || {})
        Object.assign(form.consignment, res?.data?.consignment || {})
        taskStages.value = (taskRes?.data || []).map((item: any) => ({ ...item, default_uid: Number(item.default_uid || 0) || undefined }))
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    try {
        const defaults = Object.fromEntries(taskStages.value.map((item: any) => [item.stage_key, Number(item.default_uid || 0)]))
        const [res]: any[] = await Promise.all([
            saveErpConfig(JSON.parse(JSON.stringify(form))),
            saveErpTaskAssignmentSettings(defaults)
        ])
        Object.assign(form.finance, res?.data?.finance || {})
        Object.assign(form.purchase, res?.data?.purchase || {})
        Object.assign(form.product_title, res?.data?.product_title || {})
        Object.assign(form.sale, res?.data?.sale || {})
        Object.assign(form.refurbish, res?.data?.refurbish || {})
        Object.assign(form.marketplace, res?.data?.marketplace || {})
        Object.assign(form.turnover, res?.data?.turnover || {})
        Object.assign(form.consignment, res?.data?.consignment || {})
        ElMessage.success('业务规则已保存')
    } finally {
        saving.value = false
    }
}

onMounted(loadConfig)
</script>

<style scoped>
.rule-section {
    border: 1px solid #eef2f7;
    border-radius: 8px;
    padding: 16px;
}
.section-title {
    margin-bottom: 14px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
</style>
