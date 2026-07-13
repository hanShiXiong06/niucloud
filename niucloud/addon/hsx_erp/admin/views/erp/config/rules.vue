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
import { getErpConfig, saveErpConfig } from '@/addon/hsx_erp/api/config'

const loading = ref(false)
const saving = ref(false)
const form = reactive(defaultRules())

function defaultRules() {
    return {
        finance: { enable_offset: 1, finance_fact_lock: 1, settlement_requires_account: 1 },
        purchase: { create_payable_on_inbound: 1, allow_cancel_before_finance_fact: 1 },
        product_title: { category_mode: 'auto', spec_in_title: 1, grade_in_title: 0, separator: ' ' },
        sale: { create_receivable_on_outbound: 1, allow_cancel_before_finance_fact: 1, return_to_original_location_on_cancel: 1, enable_peer_pending: 1, enable_trial_sale: 0, profit_confirm_mode: 'settlement' },
        refurbish: { enabled: 1, default_required: 0, tracking_mode: 'simple', daily_reminder_enabled: 1, daily_reminder_threshold: 25, reminder_dismiss_date: '' },
        turnover: { attention_days: 7, warning_days: 15, critical_days: 30, reminder_enabled: 1, reminder_count_threshold: 1, reminder_dismiss_date: '' },
        consignment: { enabled: 0, settle_payable_after_receipt: 1, transfer_to_owned_requires_repurchase: 1 }
    }
}

async function loadConfig() {
    loading.value = true
    try {
        const res: any = await getErpConfig()
        Object.assign(form.finance, res?.data?.finance || {})
        Object.assign(form.purchase, res?.data?.purchase || {})
        Object.assign(form.product_title, res?.data?.product_title || {})
        Object.assign(form.sale, res?.data?.sale || {})
        Object.assign(form.refurbish, res?.data?.refurbish || {})
        Object.assign(form.turnover, res?.data?.turnover || {})
        Object.assign(form.consignment, res?.data?.consignment || {})
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    try {
        const res: any = await saveErpConfig(JSON.parse(JSON.stringify(form)))
        Object.assign(form.finance, res?.data?.finance || {})
        Object.assign(form.purchase, res?.data?.purchase || {})
        Object.assign(form.product_title, res?.data?.product_title || {})
        Object.assign(form.sale, res?.data?.sale || {})
        Object.assign(form.refurbish, res?.data?.refurbish || {})
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
