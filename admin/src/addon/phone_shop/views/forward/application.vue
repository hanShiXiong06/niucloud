<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex items-center justify-between">
                    <div><div class="text-[18px] font-medium">同行转发申请</div><div class="text-[13px] text-gray-400 mt-[5px]">审核通过后自动设置为指定会员等级，并通知客户</div></div>
                    <el-button @click="loadPage()">刷新</el-button>
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
        <HsxDrawer v-model="drawer" title="同行身份申请资料" subtitle="核对客户填写的信息与资质图片"
            size="md" :close-on-press-escape="!previewing" :before-close="beforeDetailClose" @close="onDetailClose">
            <div v-if="detailLoading" class="application-loading" role="status" aria-live="polite">
                <p>正在加载申请资料…</p><el-skeleton :rows="6" animated />
            </div>
            <el-result v-else-if="detailError" icon="warning" title="申请资料加载失败" :sub-title="detailError">
                <template #extra><el-button type="primary" @click="openDetail(selectedId)">重新加载</el-button></template>
            </el-result>
            <template v-else-if="detail.application_id">
                <section class="application-summary" aria-label="申请概况">
                    <div class="application-summary__heading">
                        <div class="application-member">
                            <strong>{{ memberName(detail) }}</strong>
                            <span>{{ detail.member?.mobile || '未留手机号' }}</span>
                        </div>
                        <el-tag :type="tagType(detail.status)">{{ detail.status_name }}</el-tag>
                    </div>
                    <dl class="application-meta">
                        <div><dt>申请身份</dt><dd>{{ detail.target_level_name || '-' }}</dd></div>
                        <div><dt>审核负责人</dt><dd>{{ detail.reviewer_name || '未指定' }}</dd></div>
                        <div><dt>提交时间</dt><dd>{{ timeText(detail.create_time) }}</dd></div>
                        <div v-if="detail.status !== 'pending'"><dt>审核记录</dt><dd>{{ detail.reviewed_name || '-' }} · {{ timeText(detail.reviewed_at) }}</dd></div>
                    </dl>
                    <div v-if="detail.review_reason" class="application-review-note">
                        <span>审核说明</span><p>{{ detail.review_reason }}</p>
                    </div>
                </section>
                <div class="application-section-heading">
                    <h3>客户提交资料</h3>
                    <span>{{ fields.length }} 项资料<template v-if="imageCount"> · {{ imageCount }} 张图片，点击可放大</template></span>
                </div>
                <el-empty v-if="!fields.length" :image-size="88" description="暂无提交资料，请先核对表单记录" />
                <dl v-else class="application-fields">
                    <div v-for="(item, fieldIndex) in fields" :key="item.key ?? fieldIndex" class="application-field">
                        <dt>{{ item.label }}</dt>
                        <dd>
                            <template v-if="item.isImage">
                                <div v-if="item.images.length" class="application-images">
                                    <div v-for="(url, index) in item.images" :key="url" class="application-image-item">
                                        <el-image :src="url" :alt="item.label + ' ' + (index + 1)" fit="contain"
                                            :preview-src-list="item.images" :initial-index="index" preview-teleported
                                            :z-index="4000" class="application-image" @show="previewing = true" @close="previewing = false">
                                            <template #placeholder><span class="application-image-state">加载中…</span></template>
                                            <template #error><span class="application-image-state is-error">图片加载失败<br />请重新加载资料</span></template>
                                        </el-image>
                                        <span v-if="item.images.length > 1" class="application-image-number">{{ index + 1 }} / {{ item.images.length }}</span>
                                    </div>
                                </div>
                                <span v-else class="application-empty">未上传图片或图片地址无效</span>
                            </template>
                            <span v-else class="application-field-text" :class="{ 'application-empty': item.text === '未填写' }">{{ item.text }}</span>
                        </dd>
                    </div>
                </dl>
            </template>
            <template #footer="{ close }">
                <el-button v-if="detail.application_id && !detailLoading" @click="openDetail(selectedId)">重新加载资料</el-button>
                <el-button type="primary" @click="close">关闭</el-button>
            </template>
        </HsxDrawer>
    </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { HsxDrawer } from '@/addon/hsx_components/core'
import { img } from '@/utils/common'
import { getForwardApplicationInfo, getForwardApplicationPage, reviewForwardApplication } from '@/addon/phone_shop/api/forward'
import { displayApplicationField, type ApplicationField } from './application-fields'
const route = useRoute(); const loading = ref(false); const list = ref<any[]>([]); const drawer = ref(false); const detailLoading = ref(false); const detail = ref<any>({})
const search = reactive({ status: '', keyword: '' }); const page = reactive({ page: 1, limit: 10, total: 0 })
const memberName = (row:any) => row.member?.nickname || row.member?.username || row.member?.mobile || `会员#${row.member_id}`
const tagType = (status:string) => status === 'approved' ? 'success' : status === 'rejected' ? 'danger' : 'warning'
const timeText = (value:any) => { if (!value) return '-'; if (typeof value === 'string' && value.includes('-')) return value; return new Date(Number(value) * 1000).toLocaleString() }
const detailError = ref('')
const selectedId = ref(0)
const previewing = ref(false)
let detailRequest = 0
const fields = computed(() => {
    const records: ApplicationField[] = Object.values(detail.value.form_record?.recordsFieldList || {})
    return records.filter(item => item && typeof item === 'object').map(item => {
        const field = displayApplicationField(item)
        return { ...field, images: field.images.map(url => img(url)) }
    })
})
const imageCount = computed(() => fields.value.reduce((count, field) => count + field.images.length, 0))
const loadPage = async (toPage?:number) => { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getForwardApplicationPage({ ...search, page: page.page, limit: page.limit }); list.value = res.data?.data || res.data?.list || []; page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
const reset = () => { search.keyword = ''; search.status = ''; loadPage(1) }
const openDetail = async (id:number) => {
    const requestId = ++detailRequest
    selectedId.value = id
    drawer.value = true
    detail.value = {}
    detailError.value = ''
    previewing.value = false
    detailLoading.value = true
    try {
        const data = (await getForwardApplicationInfo(id)).data
        if (requestId !== detailRequest || !drawer.value) return
        if (!data?.application_id) throw new Error('未读取到申请记录，请刷新列表后重试')
        detail.value = data
    } catch (error:any) {
        if (requestId === detailRequest && drawer.value) {
            detailError.value = error?.msg || error?.message || '暂时无法读取资料，请稍后重试'
        }
    } finally {
        if (requestId === detailRequest) detailLoading.value = false
    }
}
const beforeDetailClose = (done: () => void) => { if (!previewing.value) done() }
const onDetailClose = () => { detailRequest++; previewing.value = false }
onBeforeUnmount(onDetailClose)
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
<style scoped>
.application-loading { padding: 12px 0; color: var(--el-text-color-secondary); }
.application-loading p { margin: 0 0 24px; }
.application-summary { padding: 16px; border: 1px solid var(--el-border-color-light); border-radius: 8px; background: var(--el-fill-color-light); }
.application-summary__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.application-member { display: flex; align-items: baseline; flex-wrap: wrap; min-width: 0; gap: 8px 12px; overflow-wrap: anywhere; }
.application-member strong { font-size: 17px; font-weight: 600; color: var(--el-text-color-primary); }
.application-member span { color: var(--el-text-color-secondary); font-size: 13px; }
.application-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 20px; margin: 16px 0 0; font-size: 13px; }
.application-meta > div { display: flex; align-items: baseline; gap: 10px; min-width: 0; line-height: 20px; }
.application-meta dt { flex: none; color: var(--el-text-color-secondary); }
.application-meta dd { margin: 0; overflow-wrap: anywhere; color: var(--el-text-color-primary); }
.application-review-note { border-top: 1px solid var(--el-border-color-light); margin-top: 12px; padding-top: 12px; font-size: 13px; }
.application-review-note span { color: var(--el-text-color-secondary); }
.application-review-note p { margin: 6px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 22px; }
.application-section-heading { display: flex; justify-content: space-between; align-items: baseline; gap: 8px 16px; flex-wrap: wrap; margin: 22px 0 12px; }
.application-section-heading h3 { margin: 0; font-size: 15px; font-weight: 600; }
.application-section-heading > span { color: var(--el-text-color-secondary); font-size: 12px; }
.application-fields { margin: 0; overflow: hidden; border: 1px solid var(--el-border-color-light); border-radius: 8px; }
.application-field { display: grid; grid-template-columns: 128px minmax(0, 1fr); border-bottom: 1px solid var(--el-border-color-lighter); }
.application-field:last-child { border-bottom: 0; }
.application-field > dt, .application-field > dd { margin: 0; padding: 12px 14px; min-width: 0; font-size: 14px; line-height: 22px; overflow-wrap: anywhere; }
.application-field > dt { background: var(--el-fill-color-lighter); color: var(--el-text-color-secondary); }
.application-field-text { white-space: pre-wrap; overflow-wrap: anywhere; color: var(--el-text-color-primary); }
.application-images { display: flex; flex-wrap: wrap; gap: 10px; }
.application-image-item { position: relative; width: 112px; max-width: 100%; }
.application-image { display: block; width: 100%; height: 96px; box-sizing: border-box; border: 1px solid var(--el-border-color-light); border-radius: 6px; background: var(--el-fill-color-lighter); cursor: zoom-in; }
.application-image-state { display: flex; height: 100%; align-items: center; justify-content: center; padding: 6px; box-sizing: border-box; text-align: center; color: var(--el-text-color-secondary); font-size: 12px; line-height: 18px; }
.application-image-state.is-error { cursor: default; }
.application-image-number { position: absolute; right: 4px; bottom: 4px; padding: 0 5px; border-radius: 3px; background: rgba(0, 0, 0, .56); color: #fff; font-size: 11px; line-height: 18px; pointer-events: none; }
.application-empty { color: var(--el-text-color-placeholder); }
@media (max-width: 640px) {
    .application-meta { grid-template-columns: 1fr; }
    .application-field { grid-template-columns: 100px minmax(0, 1fr); }
    .application-field > dt, .application-field > dd { padding: 10px; }
    .application-image-item { width: 104px; }
}
</style>
