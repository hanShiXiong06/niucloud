<template>
    <HsxDialog v-model="visible" class="erp-desktop-overlay" title="查询客户收款码" size="lg" :show-fullscreen="false" :close-on-press-escape="!previewing" @close="invalidate">
        <el-form class="payee-search" @submit.prevent="search(1)">
            <el-input ref="searchInput" v-model="keyword" :prefix-icon="Search" clearable maxlength="100" placeholder="客户姓名 / 手机号 / 会员编号" aria-label="查询客户" />
            <el-button type="primary" :icon="Search" native-type="submit" :loading="searching" :disabled="!keyword.trim()">查询</el-button>
        </el-form>
        <div class="payee-workspace">
            <section class="payee-results" aria-label="客户查询结果" :aria-busy="searching">
                <div class="payee-section-title">客户 <span v-if="searched">{{ total }} 位</span></div>
                <el-alert v-if="searchError" :title="searchError" type="error" :closable="false" show-icon />
                <el-table v-else v-loading="searching" :data="customers" row-key="member_id" highlight-current-row :current-row-key="selected?.member_id" :max-height="350" @row-click="selectCustomer">
                    <el-table-column label="姓名 / 会员编号" min-width="150">
                        <template #default="{ row }">
                            <div class="payee-customer-name">{{ row.name }}</div>
                            <div class="payee-secondary">{{ row.member_no || '未设置会员编号' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="手机号" min-width="134">
                        <template #default="{ row }">{{ row.mobile || '未填写' }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="80" align="right">
                        <template #default="{ row }"><el-button link type="primary" @click.stop="selectCustomer(row)">查看</el-button></template>
                    </el-table-column>
                    <template #empty>{{ searched ? '未找到匹配的客户' : '输入客户信息后查询' }}</template>
                </el-table>
                <el-pagination v-if="total > limit" v-model:current-page="page" small layout="prev, pager, next" :pager-count="5" :page-size="limit" :total="total" :disabled="searching" @current-change="search" />
                <el-button v-if="searchError" class="payee-retry" @click="search(page)">重新查询</el-button>
            </section>
            <section v-loading="loading" class="payee-details" aria-label="客户收款资料" :aria-busy="loading">
                <template v-if="selected">
                    <div class="payee-selected">
                        <div>
                            <h3>{{ selected.name }}</h3>
                            <div class="payee-secondary">{{ selected.mobile || '未填写手机号' }}<span v-if="selected.member_no"> · {{ selected.member_no }}</span></div>
                        </div>
                        <el-button :icon="Refresh" text :disabled="loading" @click="selectCustomer(selected)">刷新</el-button>
                    </div>
                    <el-alert v-if="detailError" :title="detailError" type="error" :closable="false" show-icon />
                    <el-result v-else-if="!loading && !available" icon="warning" title="收款资料服务未启用" sub-title="请联系管理员检查回收插件。" />
                    <el-empty v-else-if="!loading && !methods.length" :image-size="72" description="该客户尚未保存收款资料" />
                    <div v-for="(method, index) in methods" :key="index" class="payee-method">
                        <div class="payee-method-heading">
                            <strong>{{ method.pay_type || '其他收款方式' }}</strong>
                            <el-tag v-if="method.is_default" size="small" effect="plain">默认</el-tag>
                        </div>
                        <div class="payee-method-body">
                            <el-image v-if="method.qrcode_image" :src="img(method.qrcode_image)" :alt="`${selected.name}的${method.pay_type || ''}收款码`" :preview-src-list="[img(method.qrcode_image)]" fit="contain" class="payee-qrcode" preview-teleported :z-index="4000" hide-on-click-modal @show="previewing = true" @close="previewing = false">
                                <template #error><div class="payee-image-error"><el-icon><Picture /></el-icon><span>收款码加载失败</span></div></template>
                            </el-image>
                            <div class="payee-account">
                                <div class="payee-secondary">收款账号</div>
                                <ErpCopyText v-if="method.account" :value="method.account" title="收款账号" max-width="100%" :popover-width="300" />
                                <span v-else class="payee-secondary">未填写账号</span>
                                <div v-if="!method.qrcode_image" class="payee-secondary payee-no-code">未上传收款码</div>
                            </div>
                        </div>
                    </div>
                    <el-button v-if="detailError" class="payee-retry" @click="selectCustomer(selected)">重新读取</el-button>
                </template>
                <el-empty v-else :image-size="72" description="请选择客户" />
            </section>
        </div>
        <template #footer>
            <div class="payee-footer">
                <span>转账前请核对收款人身份；此处不登记付款或核销应付。</span>
                <el-button @click="visible = false">关闭</el-button>
            </div>
        </template>
    </HsxDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Picture, Refresh, Search } from '@element-plus/icons-vue'
import { HsxDialog } from '@/addon/hsx_components/core'
import { getErpCustomerPayee, getErpPayeeCustomers } from '@/addon/hsx_erp/api/payee'
import type { PayeeCustomer, PayeeMethod } from '@/addon/hsx_erp/api/payee'
import ErpCopyText from './ErpCopyText.vue'
import { img } from '@/utils/common'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const searchInput = ref<{ focus: () => void }>()
const keyword = ref('')
const customers = ref<PayeeCustomer[]>([])
const selected = ref<PayeeCustomer | null>(null)
const methods = ref<PayeeMethod[]>([])
const available = ref(true)
const previewing = ref(false)
const searched = ref(false)
const searching = ref(false)
const loading = ref(false)
const searchError = ref('')
const detailError = ref('')
const total = ref(0)
const page = ref(1)
const limit = 8
let searchVersion = 0
let detailVersion = 0

function invalidate() {
    searchVersion++
    detailVersion++
    searching.value = false
    loading.value = false
}

function resetResults() {
    invalidate()
    previewing.value = false
    customers.value = []
    total.value = 0
    page.value = 1
    selected.value = null
    methods.value = []
    searched.value = false
    searchError.value = ''
    detailError.value = ''
}

watch(keyword, resetResults)
watch(visible, async value => {
    resetResults()
    if (value) {
        keyword.value = ''
        await nextTick()
        searchInput.value?.focus()
    }
})

async function search(nextPage = 1) {
    if (!keyword.value.trim()) return
    const version = ++searchVersion
    detailVersion++
    selected.value = null
    methods.value = []
    loading.value = false
    searchError.value = ''
    searched.value = true
    searching.value = true
    page.value = nextPage
    try {
        const result = await getErpPayeeCustomers({ keyword: keyword.value.trim(), page: nextPage, limit })
        if (version !== searchVersion || !visible.value) return
        customers.value = result.data.data || []
        total.value = Number(result.data.total || 0)
    } catch (error: any) {
        if (version !== searchVersion || !visible.value) return
        customers.value = []
        total.value = 0
        searchError.value = error?.msg || '客户查询失败，请稍后重试'
    } finally {
        if (version === searchVersion) searching.value = false
    }
}

async function selectCustomer(customer: PayeeCustomer) {
    const version = ++detailVersion
    selected.value = customer
    methods.value = []
    available.value = true
    detailError.value = ''
    loading.value = true
    try {
        const result = await getErpCustomerPayee(customer.member_id)
        if (version !== detailVersion || !visible.value) return
        selected.value = result.data.customer
        available.value = result.data.available === true
        methods.value = result.data.methods || []
    } catch (error: any) {
        if (version !== detailVersion || !visible.value) return
        detailError.value = error?.msg || '收款资料读取失败，请稍后重试'
    } finally {
        if (version === detailVersion) loading.value = false
    }
}
</script>

<style scoped>
.payee-search { display: flex; gap: 10px; margin-bottom: 18px; }
.payee-workspace { display: grid; grid-template-columns: minmax(350px, 1fr) minmax(300px, 1fr); gap: 22px; min-height: 310px; }
.payee-results, .payee-details { min-width: 0; }
.payee-results :deep(.el-table__row) { cursor: pointer; }
.payee-results :deep(.el-pagination) { justify-content: center; margin-top: 14px; }
.payee-section-title { display: flex; justify-content: space-between; padding: 0 0 12px; font-weight: 600; }
.payee-section-title span, .payee-secondary { color: var(--el-text-color-secondary); font-size: 12px; font-weight: 400; line-height: 1.6; }
.payee-customer-name { color: var(--el-text-color-primary); font-weight: 500; overflow-wrap: anywhere; }
.payee-details { padding-left: 22px; border-left: 1px solid var(--el-border-color-lighter); }
.payee-selected { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.payee-selected h3 { margin: 0 0 5px; color: var(--el-text-color-primary); font-size: 16px; overflow-wrap: anywhere; }
.payee-selected > div { min-width: 0; }
.payee-selected .el-button { flex: none; }
.payee-method { padding: 16px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.payee-method:last-child { border-bottom: 0; }
.payee-method-heading { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; overflow-wrap: anywhere; }
.payee-method-body { display: flex; align-items: flex-start; gap: 16px; }
.payee-qrcode { flex: none; width: 148px; height: 148px; border: 1px solid var(--el-border-color-lighter); border-radius: 4px; background: #fff; cursor: zoom-in; }
.payee-image-error { display: flex; height: 100%; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #73767a; font-size: 12px; }
.payee-account { min-width: 0; padding-top: 4px; font-size: 14px; line-height: 1.8; }
.payee-no-code { margin-top: 8px; }
.payee-retry { margin-top: 12px; }
.payee-footer { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 18px; }
.payee-footer > span { color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; }
.payee-footer .el-button { flex: none; }
@media (max-width: 900px) {
    .payee-workspace { grid-template-columns: minmax(0, 1fr); }
    .payee-details { padding: 18px 0 0; border-left: 0; border-top: 1px solid var(--el-border-color-lighter); }
}
</style>
