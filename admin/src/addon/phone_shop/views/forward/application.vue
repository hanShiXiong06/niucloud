<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex items-center justify-between">
                    <div><div class="text-[18px] font-medium">同行转发申请</div><div class="text-[13px] text-gray-400 mt-[5px]">审核通过后自动设置为指定会员等级，并通知客户</div></div>
                    <el-button @click="loadPage">刷新</el-button>
                </div>
            </template>
            <el-tabs v-model="search.status" @tab-change="loadPage(1)">
                <el-tab-pane label="全部" name="" /><el-tab-pane label="待审核" name="pending" />
                <el-tab-pane label="已通过" name="approved" /><el-tab-pane label="已拒绝" name="rejected" />
            </el-tabs>
            <div class="flex items-center gap-[10px] mb-[16px]">
                <el-input v-model="search.keyword" clearable class="!w-[300px]" placeholder="会员姓名、手机号或会员号" @keyup.enter="loadPage(1)" />
                <el-button type="primary" @click="loadPage(1)">查询</el-button><el-button @click="reset">重置</el-button>
            </div>
            <el-table v-loading="loading" :data="list" size="large">
                <el-table-column label="申请会员" min-width="190">
                    <template #default="{ row }"><div class="font-medium">{{ memberName(row) }}</div><div class="text-[13px] text-gray-400 mt-[4px]">{{ row.member?.mobile || '-' }} · {{ row.member?.member_no || '-' }}</div></template>
                </el-table-column>
                <el-table-column prop="target_level_name" label="申请身份" min-width="130" />
                <el-table-column label="指定负责人" min-width="130"><template #default="{ row }">{{ row.reviewer_name || '-' }}</template></el-table-column>
                <el-table-column label="状态" width="110"><template #default="{ row }"><el-tag :type="tagType(row.status)">{{ row.status_name }}</el-tag></template></el-table-column>
                <el-table-column label="提交时间" width="180"><template #default="{ row }">{{ timeText(row.create_time) }}</template></el-table-column>
                <el-table-column label="审核信息" min-width="200"><template #default="{ row }"><span v-if="row.status !== 'pending'">{{ row.reviewed_name || '-' }} {{ timeText(row.reviewed_at) }}</span><span v-else class="text-gray-400">等待处理</span></template></el-table-column>
                <el-table-column label="操作" fixed="right" width="190"><template #default="{ row }"><el-button link type="primary" @click="openDetail(row.application_id)">查看资料</el-button><template v-if="row.status === 'pending'"><el-button link type="success" @click="review(row, 'approve')">通过</el-button><el-button link type="danger" @click="review(row, 'reject')">拒绝</el-button></template></template></el-table-column>
            </el-table>
            <div class="flex justify-end mt-[16px]"><el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, prev, pager, next" :total="page.total" @current-change="loadPage" /></div>
        </el-card>
        <el-drawer v-model="drawer" title="同行身份申请资料" size="560px">
            <div v-loading="detailLoading">
                <el-descriptions v-if="detail.application_id" :column="1" border class="mb-[18px]">
                    <el-descriptions-item label="申请会员">{{ memberName(detail) }}（{{ detail.member?.mobile || '-' }}）</el-descriptions-item>
                    <el-descriptions-item label="目标身份">{{ detail.target_level_name }}</el-descriptions-item>
                    <el-descriptions-item label="审核负责人">{{ detail.reviewer_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ detail.status_name }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.review_reason" label="审核说明">{{ detail.review_reason }}</el-descriptions-item>
                </el-descriptions>
                <div class="text-[15px] font-medium mb-[10px]">客户提交资料</div>
                <el-empty v-if="!fields.length" description="未读取到表单字段" />
                <div v-else class="rounded-[8px] border border-solid border-gray-200 overflow-hidden">
                    <div v-for="item in fields" :key="item.id" class="grid grid-cols-[130px_1fr] border-0 border-b border-solid border-gray-100 last:border-b-0">
                        <div class="bg-gray-50 px-[14px] py-[12px] text-gray-500">{{ item.field_name }}</div><div class="px-[14px] py-[12px] break-all">{{ fieldValue(item) }}</div>
                    </div>
                </div>
            </div>
        </el-drawer>
    </div>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { getForwardApplicationInfo, getForwardApplicationPage, reviewForwardApplication } from '@/addon/phone_shop/api/forward'
const route = useRoute(); const loading = ref(false); const list = ref<any[]>([]); const drawer = ref(false); const detailLoading = ref(false); const detail = ref<any>({})
const search = reactive({ status: '', keyword: '' }); const page = reactive({ page: 1, limit: 10, total: 0 })
const memberName = (row:any) => row.member?.nickname || row.member?.username || row.member?.mobile || `会员#${row.member_id}`
const tagType = (status:string) => status === 'approved' ? 'success' : status === 'rejected' ? 'danger' : 'warning'
const timeText = (value:any) => { if (!value) return '-'; if (typeof value === 'string' && value.includes('-')) return value; return new Date(Number(value) * 1000).toLocaleString() }
const fields = computed(() => Object.values(detail.value.form_record?.recordsFieldList || {}))
const fieldValue = (item:any) => { const value = item.render_value ?? item.handle_field_value ?? item.field_value ?? '-'; return typeof value === 'object' ? JSON.stringify(value) : String(value || '-') }
const loadPage = async (toPage?:number) => { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getForwardApplicationPage({ ...search, page: page.page, limit: page.limit }); list.value = res.data?.data || res.data?.list || []; page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
const reset = () => { search.keyword = ''; search.status = ''; loadPage(1) }
const openDetail = async (id:number) => { drawer.value = true; detailLoading.value = true; try { detail.value = (await getForwardApplicationInfo(id)).data || {} } finally { detailLoading.value = false } }
const review = async (row:any, action:string) => {
    const rejected = action === 'reject'
    try {
        const result:any = await ElMessageBox.prompt(
            rejected ? '请填写未通过原因，客户将收到该说明' : '可填写审核说明（选填）',
            rejected ? '拒绝申请' : '通过申请',
            {
                inputValidator: value => !rejected || !!String(value || '').trim() || '请填写未通过原因',
                confirmButtonText: rejected ? '确认拒绝' : '确认通过'
            }
        )
        await reviewForwardApplication(row.application_id, { action, reason: result.value || '' })
        await loadPage()
        if (drawer.value && detail.value.application_id === row.application_id) await openDetail(row.application_id)
    } catch (error:any) {
        // 用户主动关闭审核弹窗属于正常交互，不应继续抛出业务错误。
        if (error !== 'cancel' && error !== 'close') throw error
    }
}
onMounted(async () => { if (route.query.status) search.status = String(route.query.status); await loadPage(); if (route.query.application_id) openDetail(Number(route.query.application_id)) })
</script>
