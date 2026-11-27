<template>
    <el-dialog v-model="visible" title="收款与耗材设置" width="760px" destroy-on-close :close-on-click-modal="false" @open="load">
        <div v-loading="loading" class="payment-settings">
            <div class="provider-card" :class="{ erp: config.finance_provider === 'erp' }">
                <div class="provider-icon"><el-icon><Wallet /></el-icon></div>
                <div class="provider-copy">
                    <b>{{ config.finance_provider_name || '会员卡独立收款' }}</b>
                    <span v-if="config.finance_provider === 'erp'">已检测到 ERP，开卡收款自动使用 ERP 资金账户，账目同步进入 ERP。</span>
                    <span v-else>当前独立运行，收款记录保存在会员卡订单中，可在下方维护到账账户。</span>
                </div>
                <el-tag :type="config.finance_provider === 'erp' ? 'success' : 'primary'" effect="light">
                    {{ config.finance_provider === 'erp' ? 'ERP 已接管' : '独立模式' }}
                </el-tag>
            </div>

            <div class="setting-section">
                <div class="section-head">
                    <div><b>核销耗材库存</b><span>会员卡次数与实际耗材分别记录；贴坏重贴时可按实际数量扣减。</span></div>
                    <el-tag :type="config.inventory_available ? 'success' : 'info'" effect="light">
                        {{ config.inventory_available ? config.inventory_provider_name : '未接入 ERP' }}
                    </el-tag>
                </div>
                <div class="inventory-modes">
                    <button
                        v-for="item in inventoryModes"
                        :key="item.value"
                        type="button"
                        class="inventory-mode"
                        :class="{ active: form.inventory_mode === item.value, disabled: item.value !== 'none' && !config.inventory_available }"
                        @click="chooseInventoryMode(item.value)"
                    >
                        <span class="mode-radio"><i /></span>
                        <span class="mode-copy"><b>{{ item.label }}<em v-if="item.recommended">推荐</em></b><small>{{ item.description }}</small></span>
                    </button>
                </div>
                <el-alert
                    v-if="config.inventory_warning"
                    :title="config.inventory_warning"
                    type="info"
                    :closable="false"
                    show-icon
                />
                <div v-if="form.inventory_mode !== 'none'" class="inventory-position">
                    <el-form label-width="88px">
                        <el-form-item label="耗材仓库" required>
                            <el-select v-model="form.inventory_warehouse_id" class="w-full" placeholder="选择 ERP 仓库" @change="onWarehouseChange">
                                <el-option v-for="item in config.inventory_warehouses || []" :key="item.id" :label="item.name" :value="Number(item.id)" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="具体库位" required>
                            <el-select v-model="form.inventory_location_id" class="w-full" placeholder="选择库存扣减库位">
                                <el-option v-for="item in warehouseLocations" :key="item.id" :label="item.name" :value="Number(item.id)" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                    <div class="position-tip">卡种中设置默认耗材；核销时填写实际消耗数量，系统自动记录正常消耗与贴坏返工损耗。</div>
                </div>
            </div>

            <div class="setting-section">
                <div class="section-head">
                    <div><b>到账账户</b><span>{{ config.finance_provider === 'erp' ? '账户由 ERP 统一维护，此处只选择默认项' : '用于开卡现场收款，可新增、编辑、停用或删除' }}</span></div>
                    <el-button v-if="config.finance_provider !== 'erp'" type="primary" plain @click="openAccount()">
                        <el-icon><Plus /></el-icon>新增账户
                    </el-button>
                </div>
                <el-table :data="displayAccounts" border>
                    <el-table-column label="账户名称" min-width="180">
                        <template #default="{ row }"><div class="account-name"><span class="account-dot" :class="row.type" />{{ row.name }}</div></template>
                    </el-table-column>
                    <el-table-column label="类型" width="110">
                        <template #default="{ row }">{{ row.type_name || typeName(row.type) }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }"><el-tag :type="Number(row.status ?? 1) === 1 ? 'success' : 'info'" effect="light">{{ Number(row.status ?? 1) === 1 ? '启用' : '停用' }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="默认" width="84" align="center">
                        <template #default="{ row }"><el-radio v-model="form.default_capital_account_id" :value="Number(row.id)" :disabled="Number(row.status ?? 1) !== 1"><span /></el-radio></template>
                    </el-table-column>
                    <el-table-column v-if="config.finance_provider !== 'erp'" label="操作" width="130" align="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="openAccount(row)">编辑</el-button>
                            <el-button link type="danger" @click="removeAccount(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!displayAccounts.length" :image-size="64" description="暂无可用收款账户" />
            </div>

            <div class="setting-row">
                <div><b>允许挂账开卡</b><span>{{ config.receivable_available ? '开启后可生成 ERP 应收，由财务后续收款' : '独立模式不提供应收账款，安装并启用 ERP 后自动开放' }}</span></div>
                <el-switch v-model="form.allow_receivable" :active-value="1" :inactive-value="0" :disabled="!config.receivable_available" />
            </div>
        </div>
        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="saving" @click="save">保存设置</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="accountVisible" :title="accountForm.id ? '编辑收款账户' : '新增收款账户'" width="460px" append-to-body>
        <el-form label-width="86px">
            <el-form-item label="账户名称" required><el-input v-model="accountForm.name" maxlength="40" placeholder="例如：门店微信、现金备用金" /></el-form-item>
            <el-form-item label="账户类型"><el-select v-model="accountForm.type" class="w-full"><el-option v-for="item in accountTypes" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
            <el-form-item label="排序"><el-input-number v-model="accountForm.sort" :min="0" :max="9999" /></el-form-item>
            <el-form-item label="启用"><el-switch v-model="accountForm.status" :active-value="1" :inactive-value="0" /></el-form-item>
            <el-form-item label="设为默认"><el-switch v-model="accountForm.is_default" :active-value="1" :inactive-value="0" /></el-form-item>
        </el-form>
        <template #footer><el-button @click="accountVisible = false">取消</el-button><el-button type="primary" :loading="accountSaving" @click="saveAccount">保存</el-button></template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Wallet } from '@element-plus/icons-vue'
import { deleteMemberCardCapitalAccount, getMemberCardConfig, saveMemberCardCapitalAccount, saveMemberCardConfig } from '../api'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'change'): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const loading = ref(false), saving = ref(false), accountSaving = ref(false), accountVisible = ref(false)
const config = reactive<any>({ finance_provider: 'local', capital_account_options: [], local_capital_accounts: [], receivable_available: 0 })
const form = reactive<any>({ default_capital_account_id: 0, allow_receivable: 0, allow_unpaid_redemption: 1, finance_retry_limit: 8, inventory_mode: 'none', inventory_warehouse_id: 0, inventory_location_id: 0 })
const inventoryModes = [
    { value: 'none', label: '不管理库存', description: '只核销会员卡次数，不记录膜、壳等耗材库存。' },
    { value: 'auto', label: '自动管理', description: '自动关联并扣减 ERP 库存；不足时允许核销并形成缺货提醒。', recommended: true },
    { value: 'strict', label: '严格库存', description: '库存不足时禁止核销，适合库存管理规范的团队。' },
]
const accountForm = reactive<any>({ id: 0, name: '', type: 'wechat', status: 1, is_default: 0, sort: 10 })
const accountTypes = [{ value: 'wechat', label: '微信' }, { value: 'alipay', label: '支付宝' }, { value: 'bank', label: '银行卡' }, { value: 'cash', label: '现金' }, { value: 'other', label: '其他' }]
const typeName = (type: string) => accountTypes.find(item => item.value === type)?.label || '其他'
const displayAccounts = computed(() => config.finance_provider === 'erp' ? config.capital_account_options || [] : config.local_capital_accounts || [])
const warehouseLocations = computed(() => (config.inventory_warehouses || []).find((item: any) => Number(item.id) === Number(form.inventory_warehouse_id))?.locations || [])
const apply = (data: any) => {
    Object.assign(config, data || {})
    Object.assign(form, {
        default_capital_account_id: Number(data?.default_capital_account_id || 0),
        allow_receivable: Number(data?.allow_receivable || 0),
        allow_unpaid_redemption: Number(data?.allow_unpaid_redemption ?? 1),
        finance_retry_limit: Number(data?.finance_retry_limit || 8),
        inventory_mode: ['none', 'auto', 'strict'].includes(data?.inventory_mode) ? data.inventory_mode : 'none',
        inventory_warehouse_id: Number(data?.inventory_warehouse_id || 0),
        inventory_location_id: Number(data?.inventory_location_id || 0),
    })
}
const chooseInventoryMode = (mode: string) => {
    if (mode !== 'none' && !config.inventory_available) return ElMessage.warning('请先安装并启用 ERP，再使用耗材库存管理')
    form.inventory_mode = mode
    if (mode !== 'none' && !form.inventory_warehouse_id) {
        const warehouse = (config.inventory_warehouses || [])[0]
        form.inventory_warehouse_id = Number(warehouse?.id || 0)
        form.inventory_location_id = Number(warehouse?.locations?.[0]?.id || 0)
    }
}
const onWarehouseChange = () => {
    form.inventory_location_id = Number(warehouseLocations.value[0]?.id || 0)
}
const load = async () => {
    loading.value = true
    try { apply((await getMemberCardConfig() as any).data || {}) } finally { loading.value = false }
}
const openAccount = (row?: any) => {
    Object.assign(accountForm, row ? { ...row } : { id: 0, name: '', type: 'wechat', status: 1, is_default: displayAccounts.value.length ? 0 : 1, sort: (displayAccounts.value.length + 1) * 10 })
    accountVisible.value = true
}
const saveAccount = async () => {
    if (!String(accountForm.name || '').trim()) return ElMessage.warning('请填写账户名称')
    accountSaving.value = true
    try {
        apply((await saveMemberCardCapitalAccount({ ...accountForm }) as any).data || {})
        accountVisible.value = false
        ElMessage.success('收款账户已保存')
    } finally { accountSaving.value = false }
}
const removeAccount = async (row: any) => {
    await ElMessageBox.confirm(`确认删除“${row.name}”吗？历史订单仍保留账户名称。`, '删除账户', { type: 'warning' })
    apply((await deleteMemberCardCapitalAccount(Number(row.id)) as any).data || {})
    ElMessage.success('收款账户已删除')
}
const save = async () => {
    if (displayAccounts.value.length && !form.default_capital_account_id) return ElMessage.warning('请选择默认到账账户')
    if (form.inventory_mode !== 'none' && (!form.inventory_warehouse_id || !form.inventory_location_id)) return ElMessage.warning('请选择耗材仓库和具体库位')
    saving.value = true
    try {
        apply((await saveMemberCardConfig({ ...form }) as any).data || {})
        ElMessage.success('收款与耗材规则已保存')
        emit('change')
        visible.value = false
    } finally { saving.value = false }
}
</script>

<style scoped>
.payment-settings{display:flex;flex-direction:column;gap:18px}.provider-card{display:flex;align-items:center;gap:14px;padding:17px 18px;border:1px solid #dbeafe;border-radius:10px;background:#f8fbff}.provider-card.erp{border-color:#d1fae5;background:#f3fcf7}.provider-icon{display:flex;width:42px;height:42px;align-items:center;justify-content:center;border-radius:10px;background:#eaf2ff;color:#2563eb;font-size:21px}.provider-card.erp .provider-icon{background:#dcfce7;color:#16a34a}.provider-copy{display:flex;min-width:0;flex:1;flex-direction:column;gap:4px}.provider-copy b,.section-head b,.setting-row b{color:#475569;font-weight:600}.provider-copy span,.section-head span,.setting-row span{color:#94a3b8;font-size:12px;line-height:1.55}.setting-section{display:flex;flex-direction:column;gap:12px}.section-head,.setting-row{display:flex;align-items:center;justify-content:space-between;gap:16px}.section-head>div,.setting-row>div{display:flex;flex-direction:column;gap:4px}.account-name{display:flex;align-items:center;gap:9px;color:#475569}.account-dot{width:8px;height:8px;border-radius:50%;background:#94a3b8}.account-dot.wechat{background:#16a34a}.account-dot.alipay{background:#1677ff}.account-dot.bank{background:#7c3aed}.account-dot.cash{background:#d97706}.setting-row{padding:15px 17px;border-radius:8px;background:#f8fafc}.w-full{width:100%}
.inventory-modes{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.inventory-mode{display:flex;min-width:0;align-items:flex-start;gap:10px;padding:14px;border:1px solid #e2e8f0;border-radius:9px;background:#fff;text-align:left;cursor:pointer;transition:.18s ease}.inventory-mode:hover{border-color:#93c5fd;background:#f8fbff}.inventory-mode.active{border-color:#3b82f6;background:#eff6ff;box-shadow:0 0 0 1px rgba(59,130,246,.08)}.inventory-mode.disabled{cursor:not-allowed;opacity:.48}.mode-radio{display:flex;width:17px;height:17px;flex:none;align-items:center;justify-content:center;border:1.5px solid #cbd5e1;border-radius:50%;margin-top:1px}.inventory-mode.active .mode-radio{border-color:#2563eb}.inventory-mode.active .mode-radio i{width:9px;height:9px;border-radius:50%;background:#2563eb}.mode-copy{display:flex;min-width:0;flex-direction:column;gap:5px}.mode-copy b{color:#334155;font-size:14px;font-style:normal}.mode-copy em{margin-left:6px;padding:2px 5px;border-radius:4px;background:#dbeafe;color:#2563eb;font-size:10px;font-style:normal}.mode-copy small{color:#7c8aa0;font-size:12px;line-height:1.5}.inventory-position{padding:15px 16px 7px;border-radius:9px;background:#f8fafc}.inventory-position :deep(.el-form-item){margin-bottom:12px}.position-tip{padding:0 0 8px 88px;color:#94a3b8;font-size:12px;line-height:1.5}
</style>
