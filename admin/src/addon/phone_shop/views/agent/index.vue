<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex items-center justify-between">
                <span class="text-page-title">站点代理（多站铺货 / 上下架联动）</span>
                <el-tag :type="isMasterSite ? 'success' : 'info'" effect="dark">
                    当前站：{{ isMasterSite ? '主站' : '子站' }}（主站ID {{ masterSiteId }}）
                </el-tag>
            </div>

            <el-alert class="mt-[12px]" type="info" :closable="false" show-icon>
                <template #title>
                    <span v-if="isMasterSite">主站建品并上架时，会自动铺货到下方启用的代理子站；主站上/下架、卖出会联动子站同步。</span>
                    <span v-else>你的店铺代理了主站商品（纯展示，不在本店下单）。可设置代理加价、是否同步主站分类。</span>
                </template>
            </el-alert>

            <!-- 主站：维护代理子站 -->
            <div v-if="isMasterSite" class="mt-[16px]">
                <el-button type="primary" @click="openAdd">添加代理子站</el-button>
            </div>

            <el-table :data="tableData" v-loading="loading" class="mt-[16px]" size="large">
                <template #empty>
                    <span>{{ !loading ? '暂无代理关系' : '' }}</span>
                </template>
                <el-table-column label="主站" min-width="160">
                    <template #default="{ row }">{{ row.master_site_name || '-' }}（{{ row.master_site_id }}）</template>
                </el-table-column>
                <el-table-column label="代理子站" min-width="160">
                    <template #default="{ row }">{{ row.agent_site_name || '-' }}（{{ row.agent_site_id }}）</template>
                </el-table-column>
                <el-table-column label="子站加价" min-width="120">
                    <template #default="{ row }">
                        <span class="text-[#f56c6c]">+{{ Number(row.markup_value || 0).toFixed(2) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="订阅分类同步" min-width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.subscribe_category == 1 ? 'success' : 'info'" size="small">
                            {{ row.subscribe_category == 1 ? '已订阅' : '未订阅' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="状态" min-width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status == 1 ? 'success' : 'danger'" size="small">
                            {{ row.status == 1 ? '启用' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" min-width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openEdit(row)">编辑</el-button>
                        <el-button v-if="isMasterSite" type="primary" link @click="remove(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-[16px] flex justify-end">
                <el-pagination v-model:current-page="page" v-model:page-size="limit" layout="total, prev, pager, next"
                    :total="total" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 新增/编辑弹窗 -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="460px" @close="resetForm">
            <el-form :model="form" label-width="110px">
                <el-form-item v-if="!form.id" label="代理子站ID" required>
                    <el-input-number v-model="form.agent_site_id" :min="1" controls-position="right" class="!w-[200px]" />
                    <div class="text-[12px] text-[#94a3b8] mt-[4px]">填写要铺货到的子站站点ID</div>
                </el-form-item>
                <el-form-item label="子站加价">
                    <el-input-number v-model="form.markup_value" :min="0" :precision="2" :step="50" controls-position="right" class="!w-[200px]" />
                    <div class="text-[12px] text-[#94a3b8] mt-[4px]">子站售价 = 主站零售价 + 加价（默认0，同价）</div>
                </el-form-item>
                <el-form-item label="订阅分类同步">
                    <el-switch v-model="form.subscribe_category" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item v-if="form.id && isMasterSite" label="状态">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saveLoading" @click="submit">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAgentList, addAgent, editAgent, deleteAgent, getAgentMasterConfig } from '@/addon/phone_shop/api/agent'

const loading = ref(false)
const tableData = ref<any[]>([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const isMasterSite = ref(false)
const masterSiteId = ref(0)

const dialogVisible = ref(false)
const dialogTitle = ref('添加代理子站')
const saveLoading = ref(false)
const form = reactive<any>({ id: 0, agent_site_id: undefined, markup_value: 0, subscribe_category: 1, status: 1 })

const loadConfig = async () => {
    const res: any = await getAgentMasterConfig()
    masterSiteId.value = Number(res.data.master_site_id || 0)
    isMasterSite.value = Number(res.data.is_master_site) === 1
}

const loadList = (p = page.value) => {
    loading.value = true
    page.value = p
    getAgentList({ page: page.value, limit: limit.value }).then((res: any) => {
        loading.value = false
        tableData.value = res.data.data || []
        total.value = res.data.total || 0
    }).catch(() => { loading.value = false })
}

const resetForm = () => {
    form.id = 0
    form.agent_site_id = undefined
    form.markup_value = 0
    form.subscribe_category = 1
    form.status = 1
}

const openAdd = () => {
    resetForm()
    dialogTitle.value = '添加代理子站'
    dialogVisible.value = true
}

const openEdit = (row: any) => {
    form.id = row.id
    form.agent_site_id = row.agent_site_id
    form.markup_value = Number(row.markup_value || 0)
    form.subscribe_category = Number(row.subscribe_category)
    form.status = Number(row.status)
    dialogTitle.value = '编辑代理关系'
    dialogVisible.value = true
}

const submit = () => {
    if (!form.id && (!form.agent_site_id || form.agent_site_id <= 0)) {
        ElMessage.warning('请填写代理子站ID')
        return
    }
    saveLoading.value = true
    const done = () => { saveLoading.value = false; dialogVisible.value = false; loadList() }
    const fail = () => { saveLoading.value = false }
    if (form.id) {
        editAgent(form.id, {
            markup_value: form.markup_value,
            subscribe_category: form.subscribe_category,
            status: form.status,
        }).then(() => { ElMessage.success('已保存'); done() }).catch(fail)
    } else {
        addAgent({
            agent_site_id: form.agent_site_id,
            markup_value: form.markup_value,
            subscribe_category: form.subscribe_category,
        }).then(() => { ElMessage.success('已添加'); done() }).catch(fail)
    }
}

const remove = (row: any) => {
    ElMessageBox.confirm('确定删除该代理关系吗？', '提示', { type: 'warning' }).then(() => {
        deleteAgent(row.id).then(() => { ElMessage.success('已删除'); loadList() })
    }).catch(() => {})
}

onMounted(async () => {
    await loadConfig()
    loadList()
})
</script>
