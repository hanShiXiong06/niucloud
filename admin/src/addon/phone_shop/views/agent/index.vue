<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex flex-wrap items-center justify-between gap-[12px]">
                <div>
                    <div class="text-page-title">站点商品自动跟随</div>
                    <div class="mt-[6px] text-[13px] text-[#64748b]">
                        {{ isMasterSite ? '主站按站点关系统一管理从站商品同步' : '站点关系启用后，主站全部商品会自动进入本店' }}
                    </div>
                </div>
                <el-tag :type="isMasterSite ? 'success' : 'warning'" effect="light" size="large">
                    {{ isMasterSite ? '当前为主站' : '当前为从站' }} · 主站 {{ masterSiteId || '-' }}
                </el-tag>
            </div>

            <div class="mt-[16px] rounded-[10px] border border-[#bfdbfe] bg-[#eff6ff] px-[16px] py-[14px] text-[13px] leading-[22px] text-[#1e40af]">
                <div class="font-medium">站点关系就是自动跟随开关</div>
                <div class="mt-[3px]">
                    {{ isMasterSite
                        ? '关系启用后，主站现有商品、以后新增商品、价格资料及上下架状态都会自动同步；停用关系会统一下架该从站副本。'
                        : '无需逐个关注商品。主站上架、下架、改价、售出或恢复时，本店商品会自动保持一致。' }}
                </div>
            </div>
        </el-card>

        <el-card v-if="isMasterSite" class="!border-none mt-[12px]" shadow="never">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[16px] font-medium">从站跟随关系</div>
                    <div class="mt-[5px] text-[12px] text-[#94a3b8]">添加并启用后，系统会开始同步该从站的完整货盘</div>
                </div>
                <el-button type="primary" @click="openAdd">添加从站</el-button>
            </div>
            <el-table v-loading="loading" :data="tableData" class="mt-[14px]" size="large">
                <template #empty><span>{{ !loading ? '暂无从站跟随关系' : '' }}</span></template>
                <el-table-column label="从站" min-width="220">
                    <template #default="{ row }">{{ row.agent_site_name || '-' }}（{{ row.agent_site_id }}）</template>
                </el-table-column>
                <el-table-column label="统一加价" min-width="130">
                    <template #default="{ row }"><span class="text-[#ef4444]">+{{ money(row.markup_value) }}</span></template>
                </el-table-column>
                <el-table-column label="基础资料同步" min-width="130">
                    <template #default="{ row }"><el-tag :type="row.subscribe_category == 1 ? 'success' : 'info'">{{ row.subscribe_category == 1 ? '已开启' : '已关闭' }}</el-tag></template>
                </el-table-column>
                <el-table-column label="自动跟随" min-width="120">
                    <template #default="{ row }"><el-tag :type="row.status == 1 ? 'success' : 'danger'">{{ row.status == 1 ? '运行中' : '已停止' }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" width="310" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDashboard(row.agent_site_id)">数据看板</el-button>
                        <el-button type="success" link @click="openCategoryMappings(row.agent_site_id)">分类映射</el-button>
                        <el-button type="primary" link @click="openEdit(row)">编辑</el-button>
                        <el-button type="danger" link @click="remove(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-[16px] flex justify-end">
                <el-pagination v-model:current-page="page" v-model:page-size="limit" layout="total, prev, pager, next" :total="total" @current-change="loadList" />
            </div>
        </el-card>

        <el-card v-else class="!border-none mt-[12px]" shadow="never">
            <div class="flex flex-wrap items-center justify-between gap-[12px]">
                <div>
                    <div class="text-[16px] font-medium">本站跟随状态</div>
                    <div class="mt-[5px] text-[12px] text-[#94a3b8]">关联成功后不需要再选择商品</div>
                </div>
                <div v-if="tableData[0]" class="flex items-center gap-[8px]">
                    <el-button type="success" plain @click="openCategoryMappings(tableData[0].agent_site_id)">分类映射</el-button>
                    <el-button @click="openEdit(tableData[0])">设置站点加价</el-button>
                    <el-button :loading="syncLoading" :disabled="tableData[0].status != 1" @click="syncAll">
                        {{ syncLoading && syncProgressText ? syncProgressText : '立即全量校准' }}
                    </el-button>
                    <el-button type="danger" plain @click="remove(tableData[0])">取消跟随</el-button>
                </div>
                <el-button v-else type="primary" :loading="saveLoading" @click="followMaster">关注主站并同步商品</el-button>
            </div>

            <div v-if="tableData[0]" class="mt-[16px] grid grid-cols-1 gap-[12px] md:grid-cols-3">
                <div class="summary-item"><span>跟随主站</span><strong>{{ tableData[0].master_site_name || tableData[0].master_site_id }}</strong></div>
                <div class="summary-item"><span>统一加价</span><strong class="!text-[#ef4444]">+{{ money(tableData[0].markup_value) }}</strong></div>
                <div class="summary-item"><span>自动同步</span><strong :class="tableData[0].status == 1 ? '!text-[#16a34a]' : '!text-[#ef4444]'">{{ tableData[0].status == 1 ? '运行中' : '已停止' }}</strong></div>
            </div>
            <div v-if="tableData[0]" class="mt-[14px] flex items-start gap-[10px] rounded-[10px] bg-[#f0fdf4] px-[14px] py-[12px] text-[13px] leading-[22px] text-[#166534]">
                <span class="mt-[2px] h-[8px] w-[8px] shrink-0 rounded-full bg-[#22c55e]"></span>
                <span>系统按站点 ID 自动接收完整货盘。日常新增、修改和上下架为增量实时同步，“全量校准”只用于补偿异常或首次迁移。</span>
            </div>
            <div v-if="syncLoading && syncProgressText" class="mt-[10px] rounded-[8px] bg-[#eff6ff] px-[12px] py-[9px] text-[12px] text-[#1d4ed8]">
                正在校准当前可售货盘：{{ syncProgressText }}。请暂时不要关闭此页面，失败商品不会阻断后续商品。
            </div>
            <AgentDataDashboard v-if="tableData[0]" ref="dashboardRef" class="mt-[16px]" @open-category="openCategoryMappings(tableData[0].agent_site_id)" />
            <el-empty v-else :image-size="90" description="尚未建立站点跟随关系，请联系主站管理员" />
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="460px" @close="resetForm">
            <el-form :model="form" label-width="110px">
                <el-form-item v-if="!form.id" label="从站ID" required>
                    <el-input-number v-model="form.agent_site_id" :min="1" controls-position="right" class="!w-[200px]" />
                    <div class="mt-[4px] text-[12px] text-[#94a3b8]">建立关系后，该站点自动跟随主站全部商品</div>
                </el-form-item>
                <el-form-item label="从站加价">
                    <el-input-number v-model="form.markup_value" :min="0" :precision="2" :step="50" controls-position="right" class="!w-[200px]" />
                    <div class="mt-[4px] text-[12px] text-[#94a3b8]">从站展示价 = 主站售价 + 此加价</div>
                </el-form-item>
                <el-form-item label="同步基础资料"><el-switch v-model="form.subscribe_category" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item v-if="form.id && isMasterSite" label="自动跟随">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saveLoading" @click="submit">确定</el-button>
            </template>
        </el-dialog>
        <CategoryMappingDrawer ref="categoryMappingRef" />
        <el-drawer v-model="dashboardVisible" title="从站货盘数据" size="76%" destroy-on-close>
            <AgentDataDashboard v-if="dashboardVisible" ref="drawerDashboardRef" :agent-site-id="dashboardAgentId" @open-category="openCategoryMappings(dashboardAgentId)" />
        </el-drawer>
    </div>
</template>

<script lang="ts" setup>
import { nextTick, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { addAgent, deleteAgent, editAgent, getAgentList, getAgentMasterConfig, syncAgentGoods, syncAgentGoodsStep } from '@/addon/phone_shop/api/agent'
import CategoryMappingDrawer from './components/CategoryMappingDrawer.vue'
import AgentDataDashboard from './components/AgentDataDashboard.vue'

const loading = ref(false)
const tableData = ref<any[]>([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const isMasterSite = ref(true)
const masterSiteId = ref(0)
const dialogVisible = ref(false)
const dialogTitle = ref('添加从站')
const saveLoading = ref(false)
const syncLoading = ref(false)
const syncProgressText = ref('')
const categoryMappingRef = ref<any>(null)
const dashboardRef = ref<any>(null)
const drawerDashboardRef = ref<any>(null)
const dashboardVisible = ref(false)
const dashboardAgentId = ref(0)
const form = reactive<any>({ id: 0, agent_site_id: undefined, markup_value: 0, subscribe_category: 1, status: 1 })

const money = (value: any) => Number(value || 0).toFixed(2)
const loadConfig = async () => {
    const res: any = await getAgentMasterConfig()
    masterSiteId.value = Number(res.data.master_site_id || 0)
    isMasterSite.value = Number(res.data.is_master_site) === 1
}
const loadList = (p = page.value) => {
    loading.value = true
    page.value = Number(p || 1)
    return getAgentList({ page: page.value, limit: limit.value }).then((res: any) => {
        tableData.value = res.data.data || []
        total.value = Number(res.data.total || 0)
    }).finally(() => { loading.value = false })
}
const resetForm = () => Object.assign(form, { id: 0, agent_site_id: undefined, markup_value: 0, subscribe_category: 1, status: 1 })
const openAdd = () => { resetForm(); dialogTitle.value = '添加从站跟随关系'; dialogVisible.value = true }
const followMaster = () => {
    saveLoading.value = true
    addAgent({}).then(() => {
        ElMessage.success('已关注主站，完整货盘正在自动同步')
        loadList()
    }).finally(() => { saveLoading.value = false })
}
const openEdit = (row: any) => {
    Object.assign(form, { id: row.id, agent_site_id: row.agent_site_id, markup_value: Number(row.markup_value || 0), subscribe_category: Number(row.subscribe_category), status: Number(row.status) })
    dialogTitle.value = '编辑站点跟随关系'
    dialogVisible.value = true
}
const submit = () => {
    if (!form.id && (!form.agent_site_id || form.agent_site_id <= 0)) return ElMessage.warning('请填写从站ID')
    saveLoading.value = true
    const request = form.id ? editAgent(form.id, form) : addAgent(form)
    request.then(() => {
        ElMessage.success(form.id ? '保存成功，系统将按最新关系自动同步' : '关系已建立，主站完整货盘正在自动同步')
        dialogVisible.value = false
        loadList()
    }).finally(() => { saveLoading.value = false })
}
const remove = (row: any) => {
    const message = isMasterSite.value
        ? '删除后，该主站在从站的全部商品都会下架并停止同步，确认继续？'
        : '取消后，本站从主站同步的商品会全部下架，确认继续？'
    ElMessageBox.confirm(message, isMasterSite.value ? '删除站点关系' : '取消关注主站', { type: 'warning' }).then(() => {
        deleteAgent(row.id).then(() => { ElMessage.success(isMasterSite.value ? '关系已删除，相关商品已下架' : '已取消跟随，相关商品已下架'); loadList() })
    }).catch(() => {})
}
const syncAll = async () => {
    syncLoading.value = true
    syncProgressText.value = '准备数据'
    try {
        const start: any = await syncAgentGoods()
        const runId = Number(start.data?.run_id || 0)
        const total = Number(start.data?.total || 0)
        if (!runId) throw new Error('未能创建同步批次')
        let cursor = 0
        let done = false
        let scanned = 0
        let success = 0
        let failed = 0
        while (!done) {
            const step: any = await syncAgentGoodsStep({ run_id: runId, cursor, limit: 25 })
            cursor = Number(step.data?.cursor || cursor)
            done = Number(step.data?.done || 0) === 1
            scanned = Number(step.data?.scanned_count || 0)
            success = Number(step.data?.success_count || 0)
            failed = Number(step.data?.failed_count || 0)
            syncProgressText.value = `${Math.min(scanned, total)}/${total}`
            await dashboardRef.value?.load(false)
        }
        ElMessage.success(`校准完成：扫描 ${scanned} 台，成功 ${success} 台，失败 ${failed} 台`)
    } finally {
        syncLoading.value = false
        syncProgressText.value = ''
        dashboardRef.value?.load()
    }
}
const openCategoryMappings = (agentSiteId: number) => categoryMappingRef.value?.open(Number(agentSiteId || 0))
const openDashboard = async (agentSiteId: number) => {
    dashboardAgentId.value = Number(agentSiteId || 0)
    dashboardVisible.value = true
    await nextTick()
    drawerDashboardRef.value?.load()
}

onMounted(async () => {
    await loadConfig()
    await loadList()
    if (!isMasterSite.value && tableData.value[0]) {
        await nextTick()
        dashboardRef.value?.load()
    }
})
</script>

<style lang="scss" scoped>
.summary-item {
    @apply flex min-h-[76px] flex-col justify-center rounded-[10px] border border-[#e5e7eb] bg-[#fafafa] px-[16px];
    span { @apply text-[12px] text-[#94a3b8]; }
    strong { @apply mt-[7px] text-[16px] font-semibold text-[#1f2937]; }
}
</style>
