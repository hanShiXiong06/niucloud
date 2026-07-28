<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? '编辑卡种' : '新建卡种'"
        width="min(760px, calc(100vw - 32px))"
        class="member-card-product-dialog"
        :close-on-click-modal="false"
        destroy-on-close
        append-to-body
    >
        <div v-loading="detailLoading" class="dialog-body">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
                <div class="lifetime-presets">
                    <div>
                        <b>终身贴膜快捷方案</b>
                        <span>自动设置为永久有效、不限总次数、每日限 1 次，应用后仍可继续修改。</span>
                    </div>
                    <el-button-group>
                        <el-button @click="applyLifetimePreset('member')">按会员</el-button>
                        <el-button @click="applyLifetimePreset('imei')">一机一卡</el-button>
                        <el-button @click="applyLifetimePreset('model')">限定型号</el-button>
                    </el-button-group>
                </div>
                <section class="form-section">
                    <div class="section-head">
                        <div class="section-title">基础信息</div>
                        <div class="section-caption">用于开卡时识别卡种及确认收款金额</div>
                    </div>
                    <div class="form-grid form-grid--basic">
                        <el-form-item label="卡种名称" prop="product_name">
                            <el-input v-model="form.product_name" maxlength="100" placeholder="例如：100元贴膜10次卡" />
                        </el-form-item>
                        <el-form-item label="售价" prop="sale_price">
                            <div class="money-input">
                                <span>¥</span>
                                <el-input-number v-model="form.sale_price" :min="0" :precision="2" :controls="false" placeholder="0.00" />
                            </div>
                        </el-form-item>
                        <el-form-item label="划线价">
                            <div class="money-input">
                                <span>¥</span>
                                <el-input-number v-model="form.market_price" :min="0" :precision="2" :controls="false" placeholder="选填" />
                            </div>
                        </el-form-item>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-head">
                        <div class="section-title">核销耗材</div>
                        <div class="section-caption">权益扣 1 次不等于只用 1 张膜；核销时可按实际消耗数量扣库存</div>
                    </div>
                    <div class="form-grid form-grid--consumable">
                        <el-form-item label="默认耗材">
                            <el-input v-model="form.item.consumable_name" maxlength="100" placeholder="例如：钢化膜；留空表示不关联库存" />
                        </el-form-item>
                        <el-form-item label="单位">
                            <el-input v-model="form.item.consumable_unit" maxlength="20" placeholder="张" />
                        </el-form-item>
                        <el-form-item label="标准用量">
                            <el-input-number v-model="form.item.standard_consumable_qty" :min="0" :max="9999" :precision="3" :step="1" class="w-full" />
                        </el-form-item>
                    </div>
                    <div class="consumable-help">正常贴膜默认消耗标准用量；贴坏或返工时，核销人员可把实际数量改为 2、3…，多出的数量单独记录为损耗。</div>
                    <div v-if="inventoryAvailable && form.item.consumable_name" class="stock-adjust">
                        <div>
                            <b>{{ form.id ? '期初 / 盘点库存' : '期初库存（选填）' }}</b>
                            <span>{{ form.id ? '在默认耗材库位写入当前实存数量。' : '保存卡种时一并建立耗材档案和期初库存，省去再次编辑。' }}</span>
                        </div>
                        <el-input-number v-model="stockTarget" :min="0" :max="9999999" :precision="3" :step="1" />
                        <el-button v-if="form.id" :loading="stockSaving" @click="adjustStock">更新库存</el-button>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-head">
                        <div class="section-title">服务权益</div>
                        <div class="section-caption">设置客户每次核销的服务内容和可用次数</div>
                    </div>
                    <div class="form-grid form-grid--three">
                        <el-form-item label="权益名称" prop="item.item_name">
                            <el-input v-model="form.item.item_name" maxlength="100" placeholder="例如：贴膜服务" />
                        </el-form-item>
                        <el-form-item label="次数模式" prop="item.usage_mode">
                            <el-select v-model="form.item.usage_mode" class="w-full">
                                <el-option label="有限次数" value="limited" />
                                <el-option label="不限次数" value="unlimited" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="可用次数" prop="item.total_times">
                            <el-input-number
                                v-model="form.item.total_times"
                                :disabled="form.item.usage_mode === 'unlimited'"
                                :min="1"
                                :max="99999"
                                controls-position="right"
                                class="w-full"
                            />
                        </el-form-item>
                        <el-form-item label="每日核销上限">
                            <el-input-number v-model="form.item.daily_limit" :min="0" :max="999" controls-position="right" class="w-full" />
                            <div class="field-help">0 表示不限；终身不限次活动建议设置每日上限</div>
                        </el-form-item>
                    </div>
                    <el-form-item label="适用对象" prop="item.binding_mode" class="binding-field">
                        <el-radio-group v-model="form.item.binding_mode" class="binding-options">
                            <el-radio-button value="member">按会员本人</el-radio-button>
                            <el-radio-button value="imei">绑定指定设备</el-radio-button>
                            <el-radio-button value="model">限定产品型号</el-radio-button>
                        </el-radio-group>
                        <div class="field-help">{{ bindingHelp }}</div>
                    </el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-head">
                        <div class="section-title">生效与有效期</div>
                        <div class="section-caption">决定开卡后何时开始计算有效期</div>
                    </div>
                    <div class="form-grid form-grid--validity">
                        <el-form-item label="生效方式" prop="effective_mode">
                            <el-select v-model="form.effective_mode" class="w-full">
                                <el-option label="开卡即生效" value="immediate" />
                                <el-option label="首次使用生效" value="first_use" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="有效期" prop="validity_mode">
                            <el-select v-model="form.validity_mode" class="w-full">
                                <el-option label="永久有效" value="permanent" />
                                <el-option label="固定时长" value="duration" />
                            </el-select>
                        </el-form-item>
                        <el-form-item v-if="form.validity_mode === 'duration'" label="有效时长" prop="duration_value">
                            <div class="duration-input">
                                <el-input-number v-model="form.duration_value" :min="1" :max="9999" :controls="false" />
                                <el-select v-model="form.duration_unit">
                                    <el-option label="天" value="day" />
                                    <el-option label="月" value="month" />
                                </el-select>
                            </div>
                        </el-form-item>
                    </div>
                </section>

                <el-form-item label="使用说明" class="notice-field">
                    <el-input
                        v-model="form.usage_notice"
                        type="textarea"
                        :rows="3"
                        maxlength="1000"
                        show-word-limit
                        resize="none"
                        placeholder="选填，例如适用门店、使用时间或其他注意事项"
                    />
                </el-form-item>
            </el-form>
        </div>

        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="saving" @click="submit">保存卡种</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { adjustCardProductStock, getCardProduct, getMemberCardConfig, saveCardProduct } from '../api'

const emit = defineEmits<{
    (event: 'saved'): void
}>()

const visible = ref(false)
const detailLoading = ref(false)
const saving = ref(false)
const stockSaving = ref(false)
const stockTarget = ref(0)
const inventoryConfig = ref<any>({})
const inventoryAvailable = computed(() => Number(inventoryConfig.value.inventory_available || 0) === 1)
const formRef = ref<FormInstance>()

const blankForm = () => ({
    id: 0,
    product_name: '',
    sale_price: 100,
    market_price: 0,
    effective_mode: 'immediate',
    validity_mode: 'permanent',
    duration_value: 30,
    duration_unit: 'day',
    usage_notice: '',
    sort: 0,
    item: {
        item_code: 'film_service',
        item_name: '贴膜服务',
        binding_mode: 'member',
        usage_mode: 'limited',
        total_times: 10,
        daily_limit: 0,
        recognition_mode: 'average',
        recognition_amount: 0,
        reference_price: 0,
        consumable_code: '',
        consumable_name: '钢化膜',
        consumable_unit: '张',
        standard_consumable_qty: 1
    }
})

const form = reactive<any>(blankForm())
const rules: FormRules = {
    product_name: [{ required: true, message: '请填写卡种名称', trigger: 'blur' }],
    sale_price: [{ required: true, message: '请填写售价', trigger: 'change' }],
    'item.item_name': [{ required: true, message: '请填写权益名称', trigger: 'blur' }],
    'item.usage_mode': [{ required: true, message: '请选择次数模式', trigger: 'change' }],
    effective_mode: [{ required: true, message: '请选择生效方式', trigger: 'change' }],
    validity_mode: [{ required: true, message: '请选择有效期', trigger: 'change' }]
}
const bindingHelp = computed(() => ({
    member: '客户本人名下任意设备均可使用，核销时核对手机号和姓名。',
    imei: '开卡时必须绑定 IMEI；核销时扫描或输入同一设备才可使用。',
    model: '开卡时指定产品型号；相同型号设备可使用，适合型号专属活动。'
}[form.item.binding_mode] || ''))
const applyLifetimePreset = (mode: 'member' | 'imei' | 'model') => {
    form.product_name = form.product_name || '终身贴膜服务卡'
    form.item.item_name = '终身贴膜服务'
    form.item.binding_mode = mode
    form.item.usage_mode = 'unlimited'
    form.item.daily_limit = 1
    form.effective_mode = 'immediate'
    form.validity_mode = 'permanent'
}

const resetForm = (data: Record<string, any> = {}) => {
    const empty = blankForm()
    Object.assign(form, empty, data, {
        item: { ...empty.item, ...(data.item || {}) }
    })
    formRef.value?.clearValidate()
}

const open = async (productId = 0) => {
    resetForm()
    stockTarget.value = 0
    visible.value = true
    try {
        inventoryConfig.value = ((await getMemberCardConfig()) as any)?.data || {}
    } catch {
        inventoryConfig.value = {}
    }
    if (!productId) return
    detailLoading.value = true
    try {
        const response: any = await getCardProduct(productId)
        resetForm(response.data || {})
    } finally {
        detailLoading.value = false
    }
}

const adjustStock = async () => {
    stockSaving.value = true
    try {
        const result: any = await adjustCardProductStock(Number(form.id), { target_quantity: Number(stockTarget.value), remark: '卡种设置中初始化/盘点库存' })
        const data = result?.data || {}
        ElMessage.success(`库存已更新：${data.stock_before ?? 0} → ${data.stock_after ?? stockTarget.value}`)
    } finally {
        stockSaving.value = false
    }
}

const submit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()
    if (form.item.usage_mode === 'limited' && Number(form.item.total_times) < 1) {
        ElMessage.warning('有限次数卡至少需要设置 1 次可用次数')
        return
    }
    saving.value = true
    try {
        const wasNew = !form.id
        const result: any = await saveCardProduct(form, form.id)
        const savedId = Number(result?.data?.id || form.id)
        if (wasNew && savedId) form.id = savedId
        if (wasNew && savedId && inventoryAvailable.value && form.item.consumable_name) {
            await adjustCardProductStock(savedId, {
                target_quantity: Number(stockTarget.value),
                remark: '新建卡种时录入期初库存'
            })
        }
        ElMessage.success('卡种已保存')
        visible.value = false
        emit('saved')
    } finally {
        saving.value = false
    }
}

defineExpose({ open })
</script>

<style scoped>
:global(.member-card-product-dialog) {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 32px);
    margin: 16px auto !important;
    overflow: hidden;
}
:global(.member-card-product-dialog .el-dialog__body) {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}
.dialog-body { min-height: 160px; }
.lifetime-presets { display: flex; margin-bottom: 16px; padding: 14px 16px; align-items: center; justify-content: space-between; gap: 16px; border: 1px solid #dbeafe; border-radius: 6px; background: #f8fbff; }
.lifetime-presets > div:first-child { display: flex; min-width: 0; flex-direction: column; gap: 4px; }
.lifetime-presets b { color: #334155; font-size: 14px; }
.lifetime-presets span { color: #718096; font-size: 12px; line-height: 1.5; }
.form-section { margin-bottom: 20px; padding: 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 4px; background: var(--el-fill-color-extra-light); }
.section-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 16px; }
.section-title { color: var(--el-text-color-primary); font-size: 15px; font-weight: 600; }
.section-caption { color: var(--el-text-color-secondary); font-size: 12px; }
.form-grid { display: grid; gap: 0 16px; }
.form-grid--basic { grid-template-columns: minmax(260px, 2fr) minmax(140px, 1fr) minmax(140px, 1fr); }
.form-grid--three, .form-grid--validity, .form-grid--consumable { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.w-full, .money-input, .duration-input { width: 100%; }
.money-input { position: relative; }
.money-input > span { position: absolute; z-index: 2; top: 50%; left: 12px; color: var(--el-text-color-secondary); transform: translateY(-50%); }
.money-input :deep(.el-input-number), .duration-input :deep(.el-input-number) { width: 100%; }
.money-input :deep(.el-input__wrapper) { padding-left: 28px; }
.duration-input { display: grid; grid-template-columns: minmax(0, 1fr) 76px; gap: 8px; }
.notice-field { margin-bottom: 0; }
.binding-field { margin: 2px 0 0; }
.binding-options { width: 100%; }
.binding-options :deep(.el-radio-button) { flex: 1; }
.binding-options :deep(.el-radio-button__inner) { width: 100%; }
.field-help { margin-top: 6px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.5; }
.consumable-help{margin-top:-5px;color:var(--el-text-color-secondary);font-size:12px;line-height:1.6}.stock-adjust{display:flex;align-items:center;gap:12px;margin-top:14px;padding:12px 14px;border-radius:6px;background:#fff}.stock-adjust>div{display:flex;min-width:0;flex:1;flex-direction:column;gap:3px}.stock-adjust b{color:#475569;font-size:13px}.stock-adjust span{color:#94a3b8;font-size:12px}

@media (max-width: 680px) {
    .lifetime-presets { align-items: stretch; flex-direction: column; }
    .form-grid--basic, .form-grid--three, .form-grid--validity { grid-template-columns: 1fr; }
    .section-head { display: block; }
    .section-caption { margin-top: 4px; }
}
</style>
