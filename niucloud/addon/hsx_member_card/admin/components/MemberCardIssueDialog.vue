<template>
    <el-dialog v-model="visible" title="快速开卡" width="680px" destroy-on-close :close-on-click-modal="false">
        <div class="issue-tip">一次完成选客户、选卡种和收款。挂账会自动进入 ERP 应收，现场收款直接登记到所选资金账户。</div>
        <el-form ref="formRef" :model="form" :rules="rules" label-width="92px" v-loading="loading">
            <el-form-item label="购卡客户" prop="member_id">
                <el-select v-model="form.member_id" filterable remote reserve-keyword :remote-method="searchMembers" :loading="memberLoading" placeholder="输入姓名或手机号检索" class="w-full">
                    <el-option v-for="item in members" :key="item.member_id" :value="item.member_id" :label="`${item.display_name} · ${item.mobile_masked}`">
                        <div class="member-option"><span>{{ item.display_name }}</span><small>{{ item.mobile_masked }} · {{ item.card_count || 0 }} 张卡</small></div>
                    </el-option>
                </el-select>
                <el-button link type="primary" class="quick-create" @click="createVisible = true">没有客户？快速创建</el-button>
            </el-form-item>
            <el-form-item label="选择卡种" prop="product_id">
                <el-select v-model="form.product_id" placeholder="选择已启用卡种" class="w-full">
                    <el-option v-for="item in products" :key="item.id" :value="item.id" :label="item.product_name">
                        <div class="product-option"><span>{{ item.product_name }}</span><small>¥{{ money(item.sale_price) }} · {{ item.item?.usage_mode === 'unlimited' ? '不限次' : `${item.item?.total_times || 0} 次` }} · {{ item.validity_text }}</small></div>
                    </el-option>
                </el-select>
            </el-form-item>
            <el-form-item label="结算方式" prop="settlement_mode">
                <el-radio-group v-model="form.settlement_mode">
                    <el-radio-button value="immediate">现场收款</el-radio-button>
                    <el-radio-button value="receivable" :disabled="config.allow_receivable !== 1">进入 ERP 应收</el-radio-button>
                </el-radio-group>
            </el-form-item>
            <template v-if="form.settlement_mode === 'immediate'">
                <el-form-item label="收款账户" prop="capital_account_id">
                    <el-select v-model="form.capital_account_id" placeholder="选择实际到账账户" class="w-full">
                        <el-option v-for="item in config.capital_account_options || []" :key="item.id" :label="item.name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="收款凭证"><ErpFinanceVoucherUpload v-model="voucherValue" /></el-form-item>
            </template>
            <el-form-item label="开卡备注"><el-input v-model="form.remark" type="textarea" :rows="2" maxlength="255" show-word-limit placeholder="选填，记录活动来源或特殊约定" /></el-form-item>
        </el-form>
        <template #footer><el-button @click="visible = false">取消</el-button><el-button type="primary" :loading="submitting" @click="submit">确认开卡</el-button></template>
    </el-dialog>

    <el-dialog v-model="createVisible" title="快速创建客户" width="440px" append-to-body>
        <el-form label-width="74px"><el-form-item label="姓名"><el-input v-model="newMember.name" maxlength="100" placeholder="用于核销时人工核对" /></el-form-item><el-form-item label="手机号"><el-input v-model="newMember.mobile" maxlength="11" placeholder="11 位手机号" /></el-form-item></el-form>
        <template #footer><el-button @click="createVisible = false">取消</el-button><el-button type="primary" :loading="creating" @click="quickCreate">创建并选中</el-button></template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { createCardOrder, getCardMemberOptions, getCardProductOptions, getMemberCardConfig, memberCardRequestId, quickCreateCardMember } from '../api'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void; (e: 'success', value: any): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const formRef = ref<any>(), loading = ref(false), submitting = ref(false), memberLoading = ref(false)
const createVisible = ref(false), creating = ref(false), voucherValue = ref('')
const members = ref<any[]>([]), products = ref<any[]>([]), config = reactive<any>({ allow_receivable: 1, default_capital_account_id: 0, capital_account_options: [] })
const form = reactive<any>({ member_id: undefined, product_id: undefined, settlement_mode: 'immediate', capital_account_id: undefined, remark: '' })
const newMember = reactive({ name: '', mobile: '' })
const rules = { member_id: [{ required: true, message: '请选择购卡客户', trigger: 'change' }], product_id: [{ required: true, message: '请选择卡种', trigger: 'change' }], settlement_mode: [{ required: true, message: '请选择结算方式', trigger: 'change' }], capital_account_id: [{ validator: (_: any, value: any, callback: any) => form.settlement_mode === 'immediate' && !value ? callback(new Error('现场收款必须选择到账账户')) : callback(), trigger: 'change' }] }
const money = (value: any) => Number(value || 0).toFixed(2)
const reset = () => { Object.assign(form, { member_id: undefined, product_id: undefined, settlement_mode: 'immediate', capital_account_id: config.default_capital_account_id || undefined, remark: '' }); voucherValue.value = '' }
const loadBase = async () => { loading.value = true; try { const [p, c] = await Promise.all([getCardProductOptions(), getMemberCardConfig()]); products.value = (p as any).data || []; Object.assign(config, (c as any).data || {}); reset(); await searchMembers('') } finally { loading.value = false } }
const searchMembers = async (keyword: string) => { memberLoading.value = true; try { members.value = ((await getCardMemberOptions({ keyword, limit: 30 })) as any).data || [] } finally { memberLoading.value = false } }
const quickCreate = async () => { if (!newMember.name.trim() || !/^1\d{10}$/.test(newMember.mobile)) return ElMessage.warning('请填写姓名和正确的11位手机号'); creating.value = true; try { const row: any = (await quickCreateCardMember({ ...newMember, request_id: memberCardRequestId('member') })).data; members.value.unshift({ member_id: row.member_id, display_name: row.member_name, mobile_masked: row.mobile_masked, card_count: 0 }); form.member_id = row.member_id; createVisible.value = false; Object.assign(newMember, { name: '', mobile: '' }); ElMessage.success(row.created ? '客户已创建' : '已找到原客户并选中') } finally { creating.value = false } }
const submit = async () => { await formRef.value?.validate(); submitting.value = true; try { const result: any = (await createCardOrder({ ...form, request_id: memberCardRequestId('issue'), voucher_urls: voucherValue.value ? voucherValue.value.split(',').map(v => v.trim()).filter(Boolean) : [] })).data; if (!result.success) ElMessage.warning(result.message || '开卡单已保存，请处理财务异常'); else ElMessage.success(result.message || '开卡成功'); emit('success', result); visible.value = false } finally { submitting.value = false } }
watch(visible, value => { if (value) loadBase() })
</script>

<style scoped>
.issue-tip { margin: -4px 0 20px; padding: 11px 14px; background: var(--el-color-primary-light-9); color: var(--el-text-color-regular); line-height: 1.6; }
.w-full { width: 100%; }.quick-create { margin-top: 6px; }.member-option,.product-option { display:flex; align-items:center; justify-content:space-between; gap:20px; }.member-option small,.product-option small { color:var(--el-text-color-secondary); }
</style>
