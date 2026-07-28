<template>
    <el-dialog v-model="visible" title="快速开卡" width="720px" destroy-on-close :close-on-click-modal="false" class="member-card-issue-dialog">
        <div class="issue-overview">
            <div><b>一次完成开卡</b><span>选择客户、服务卡与收款方式，提交后自动形成业务和财务记录。</span></div>
            <div class="issue-steps"><span><i>1</i>客户</span><em /><span><i>2</i>卡种</span><em /><span><i>3</i>收款</span></div>
        </div>
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" v-loading="loading">
            <div class="issue-block">
                <div class="issue-section"><span>01</span><div><b>购卡客户</b><small>按姓名或手机号检索，也可快速创建客户</small></div></div>
                <el-form-item prop="member_id">
                    <el-select v-model="form.member_id" filterable remote reserve-keyword :remote-method="searchMembers" :loading="memberLoading" placeholder="输入姓名或手机号检索" class="w-full">
                        <el-option v-for="item in members" :key="item.member_id" :value="item.member_id" :label="`${item.display_name} · ${item.mobile_masked}`">
                            <div class="member-option"><span>{{ item.display_name }}</span><small>{{ item.mobile_masked }} · {{ item.card_count || 0 }} 张卡</small></div>
                        </el-option>
                    </el-select>
                    <el-button link type="primary" class="quick-create" @click="openQuickCreate">没有客户？快速创建</el-button>
                </el-form-item>
            </div>
            <div class="issue-block">
                <div class="issue-section"><span>02</span><div><b>选择卡种</b><small>选择本次销售的服务权益</small></div></div>
                <el-form-item prop="product_id">
                    <el-select v-model="form.product_id" placeholder="选择已启用卡种" class="w-full">
                        <el-option v-for="item in products" :key="item.id" :value="item.id" :label="item.product_name">
                            <div class="product-option"><span>{{ item.product_name }}</span><small>¥{{ money(item.sale_price) }} · {{ item.item?.usage_mode === 'unlimited' ? '不限次' : `${item.item?.total_times || 0} 次` }} · {{ item.validity_text }}</small></div>
                        </el-option>
                    </el-select>
                </el-form-item>
                <div v-if="bindingMode !== 'member'" class="binding-panel">
                    <div class="binding-panel__title">
                        <span>{{ bindingMode === 'imei' ? '绑定服务设备' : '限定适用型号' }}</span>
                        <el-tag size="small" effect="light">{{ bindingModeText }}</el-tag>
                    </div>
                    <el-form-item v-if="bindingMode === 'imei'" label="设备 IMEI" prop="bind_imei">
                        <el-input v-model.trim="form.bind_imei" maxlength="40" placeholder="输入或粘贴设备 IMEI" clearable />
                    </el-form-item>
                    <el-form-item :label="bindingMode === 'imei' ? '设备型号（选填）' : '适用产品型号'" prop="bind_model">
                        <el-input v-model.trim="form.bind_model" maxlength="100" :placeholder="bindingMode === 'imei' ? '便于核销记录中识别设备' : '例如：iPhone 17 Pro Max'" clearable />
                    </el-form-item>
                    <div class="form-help">{{ bindingMode === 'imei' ? '核销时必须输入相同 IMEI；换机需重新开卡。' : '核销时设备型号必须与此处一致。' }}</div>
                </div>
            </div>
            <div class="issue-block">
                <div class="issue-section"><span>03</span><div><b>收款处理</b><small>现场收款记入到账账户{{ config.finance_provider === 'erp' ? '，挂账进入 ERP 应收' : '' }}</small></div><el-tag size="small" effect="light" :type="config.finance_provider === 'erp' ? 'success' : 'primary'">{{ config.finance_provider === 'erp' ? 'ERP 账户' : '独立账户' }}</el-tag></div>
                <el-form-item prop="settlement_mode">
                    <el-radio-group v-model="form.settlement_mode" class="settlement-options">
                        <el-radio-button value="immediate">现场收款</el-radio-button>
                        <el-radio-button value="receivable" :disabled="config.allow_receivable !== 1">进入 ERP 应收</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <div v-if="form.settlement_mode === 'immediate'" class="finance-fields">
                    <el-form-item label="实际到账账户" prop="capital_account_id">
                        <el-select v-model="form.capital_account_id" placeholder="选择实际到账账户" class="w-full">
                            <el-option v-for="item in config.capital_account_options || []" :key="item.id" :label="`${item.name}${item.type_name ? ` · ${item.type_name}` : ''}`" :value="item.id" />
                        </el-select>
                        <div v-if="!(config.capital_account_options || []).length" class="form-help">暂无可用账户，请先在工作台打开“收款设置”。</div>
                    </el-form-item>
                    <el-form-item label="收款凭证（选填）"><MemberCardVoucherUpload v-model="voucherValue" /></el-form-item>
                </div>
                <el-form-item label="业务备注（选填）"><el-input v-model="form.remark" type="textarea" :rows="2" maxlength="255" show-word-limit placeholder="记录活动来源或特殊约定" /></el-form-item>
            </div>
        </el-form>
        <template #footer><div class="dialog-footer"><span>提交后将立即生成开卡订单</span><div><el-button @click="visible = false">取消</el-button><el-button type="primary" :loading="submitting" @click="submit">确认开卡</el-button></div></div></template>
    </el-dialog>

    <el-dialog v-model="createVisible" title="快速创建客户" width="440px" append-to-body>
        <el-form label-width="82px">
            <el-form-item label="姓名"><el-input v-model="newMember.name" maxlength="100" placeholder="用于核销时人工核对" /></el-form-item>
            <el-form-item label="手机号"><el-input v-model="newMember.mobile" maxlength="11" placeholder="11 位手机号" /></el-form-item>
            <el-form-item label="初始密码">
                <el-input v-model="newMember.password" type="password" show-password maxlength="32" placeholder="默认手机号后六位" @input="passwordCustomized = true" />
                <div class="form-help">默认取手机号后六位，可在创建前修改</div>
            </el-form-item>
        </el-form>
        <template #footer><el-button @click="createVisible = false">取消</el-button><el-button type="primary" :loading="creating" @click="quickCreate">创建并选中</el-button></template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { createCardOrder, getCardMemberOptions, getCardProductOptions, getMemberCardConfig, memberCardRequestId, quickCreateCardMember } from '../api'
import MemberCardVoucherUpload from './MemberCardVoucherUpload.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void; (e: 'success', value: any): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const formRef = ref<any>(), loading = ref(false), submitting = ref(false), memberLoading = ref(false)
const createVisible = ref(false), creating = ref(false), voucherValue = ref('')
const members = ref<any[]>([]), products = ref<any[]>([]), config = reactive<any>({ allow_receivable: 0, default_capital_account_id: 0, capital_account_options: [], finance_provider: 'local' })
const form = reactive<any>({ member_id: undefined, product_id: undefined, bind_imei: '', bind_model: '', settlement_mode: 'immediate', capital_account_id: undefined, remark: '' })
const newMember = reactive({ name: '', mobile: '', password: '' })
const passwordCustomized = ref(false)
const selectedProduct = computed(() => products.value.find(item => Number(item.id) === Number(form.product_id)))
const bindingMode = computed(() => selectedProduct.value?.item?.binding_mode || 'member')
const bindingModeText = computed(() => ({ imei: '一机一卡', model: '同型号可用' }[bindingMode.value] || '按会员'))
const rules = { member_id: [{ required: true, message: '请选择购卡客户', trigger: 'change' }], product_id: [{ required: true, message: '请选择卡种', trigger: 'change' }], bind_imei: [{ validator: (_: any, value: any, callback: any) => bindingMode.value === 'imei' && !String(value || '').trim() ? callback(new Error('请输入设备 IMEI')) : callback(), trigger: 'blur' }], bind_model: [{ validator: (_: any, value: any, callback: any) => bindingMode.value === 'model' && !String(value || '').trim() ? callback(new Error('请输入适用产品型号')) : callback(), trigger: 'blur' }], settlement_mode: [{ required: true, message: '请选择结算方式', trigger: 'change' }], capital_account_id: [{ validator: (_: any, value: any, callback: any) => form.settlement_mode === 'immediate' && Number(selectedProduct.value?.sale_price || 0) > 0 && !value ? callback(new Error('现场收款必须选择到账账户')) : callback(), trigger: 'change' }] }
const money = (value: any) => Number(value || 0).toFixed(2)
const reset = () => { Object.assign(form, { member_id: undefined, product_id: undefined, bind_imei: '', bind_model: '', settlement_mode: 'immediate', capital_account_id: config.default_capital_account_id || undefined, remark: '' }); voucherValue.value = '' }
const loadBase = async () => { loading.value = true; try { const [p, c] = await Promise.all([getCardProductOptions(), getMemberCardConfig()]); products.value = (p as any).data || []; Object.assign(config, (c as any).data || {}); if (!config.default_capital_account_id) config.default_capital_account_id = Number(config.capital_account_options?.[0]?.id || 0); reset(); await searchMembers('') } finally { loading.value = false } }
const searchMembers = async (keyword: string) => { memberLoading.value = true; try { members.value = ((await getCardMemberOptions({ keyword, limit: 30 })) as any).data || [] } finally { memberLoading.value = false } }
const openQuickCreate = () => { Object.assign(newMember, { name: '', mobile: '', password: '' }); passwordCustomized.value = false; createVisible.value = true }
const quickCreate = async () => { if (!newMember.name.trim() || !/^1\d{10}$/.test(newMember.mobile)) return ElMessage.warning('请填写姓名和正确的11位手机号'); if (newMember.password.length < 6 || newMember.password.length > 32 || /\s/.test(newMember.password)) return ElMessage.warning('密码需为6至32位且不能包含空格'); creating.value = true; try { const row: any = (await quickCreateCardMember({ ...newMember, request_id: memberCardRequestId('member') })).data; members.value.unshift({ member_id: row.member_id, display_name: row.member_name, mobile_masked: row.mobile_masked, card_count: 0 }); form.member_id = row.member_id; createVisible.value = false; Object.assign(newMember, { name: '', mobile: '', password: '' }); passwordCustomized.value = false; ElMessage.success(row.created ? '客户已创建' : '已找到原客户并选中') } finally { creating.value = false } }
const submit = async () => { await formRef.value?.validate(); submitting.value = true; try { const result: any = (await createCardOrder({ ...form, request_id: memberCardRequestId('issue'), voucher_urls: voucherValue.value ? voucherValue.value.split(',').map(v => v.trim()).filter(Boolean) : [] })).data; if (!result.success) ElMessage.warning(result.message || '开卡单已保存，请处理财务异常'); else ElMessage.success(result.message || '开卡成功'); emit('success', result); visible.value = false } finally { submitting.value = false } }
watch(visible, value => { if (value) loadBase() })
watch(() => form.product_id, () => { form.bind_imei = ''; form.bind_model = ''; formRef.value?.clearValidate(['bind_imei', 'bind_model']) })
watch(() => newMember.mobile, mobile => { if (!passwordCustomized.value) newMember.password = /^1\d{5,10}$/.test(mobile) ? mobile.slice(-6) : '' })
</script>

<style scoped>
.issue-overview{display:flex;margin:-6px 0 18px;padding:16px 18px;align-items:center;justify-content:space-between;gap:24px;border-radius:8px;background:linear-gradient(110deg,#f8fbff,#f3f6fb)}.issue-overview>div:first-child{display:flex;flex-direction:column;gap:5px}.issue-overview b{color:#475569;font-size:15px;font-weight:600}.issue-overview>div:first-child span{color:#94a3b8;font-size:12px}.issue-steps{display:flex;flex:0 0 auto;align-items:center;gap:8px}.issue-steps span{display:flex;align-items:center;gap:5px;color:#718096;font-size:12px}.issue-steps i{display:inline-flex;width:22px;height:22px;align-items:center;justify-content:center;border-radius:50%;background:#eaf2ff;color:#2563eb;font-style:normal}.issue-steps em{width:18px;height:1px;background:#dbe3ed}.issue-block{padding:17px 18px 2px;border:1px solid #e8edf3;border-radius:8px}.issue-block+.issue-block{margin-top:12px}.issue-section{display:flex;margin-bottom:15px;align-items:center;gap:11px}.issue-section>span{color:#2563eb;font-size:12px;font-weight:700;letter-spacing:.5px}.issue-section>div{display:flex;flex-direction:column;gap:3px}.issue-section b{color:#475569;font-weight:600}.issue-section small{color:#a0aec0}.w-full{width:100%}.quick-create{margin-top:6px}.member-option,.product-option{display:flex;align-items:center;justify-content:space-between;gap:20px}.member-option span,.product-option span{color:#475569}.member-option small,.product-option small{color:#94a3b8}.settlement-options{width:100%}.settlement-options :deep(.el-radio-button){flex:1}.settlement-options :deep(.el-radio-button__inner){width:100%}.finance-fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}.dialog-footer{display:flex;width:100%;align-items:center;justify-content:space-between}.dialog-footer>span{color:#a0aec0;font-size:12px}.form-help{margin-top:5px;color:#94a3b8;font-size:12px;line-height:1.4}.binding-panel{margin:-2px 0 16px;padding:14px 16px 4px;border:1px solid #dbeafe;border-radius:8px;background:#f8fbff}.binding-panel__title{display:flex;margin-bottom:12px;align-items:center;justify-content:space-between;gap:8px;color:#334155;font-size:14px;font-weight:600}
</style>
