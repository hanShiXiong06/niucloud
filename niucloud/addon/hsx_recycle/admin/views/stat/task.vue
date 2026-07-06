<template>
    <PremiumTheme class="recycle-my-task">
        <el-card shadow="never">
            <div class="flex justify-between items-center mb-[12px]">
                <span class="text-page-title">{{ t('我的任务') }}</span>
                <el-button :icon="Refresh" @click="loadAll">{{ t('刷新') }}</el-button>
            </div>

            <!-- 无负责环节 -->
            <EmptyState
                v-if="!loading && stages.length === 0"
                icon="folder"
                title="你当前没有负责的环节"
                description="任务按角色权限自动分发。请联系管理员在「角色管理」为你的角色勾选对应的动作权限（执行质检 / 设备定价 / 确认 / 打款 / 转代卖）。"
            />

            <template v-else>
                <!-- 环节切换 -->
                <el-tabs v-model="activeStage" @tab-change="onStageChange">
                    <el-tab-pane :label="t('全部')" name="" />
                    <el-tab-pane
                        v-for="s in stages"
                        :key="s.stage_key"
                        :label="s.name"
                        :name="s.stage_key"
                    />
                </el-tabs>

                <!-- 搜索 -->
                <div class="flex items-center mb-[12px]">
                    <el-input
                        v-model="keyword"
                        :placeholder="t('IMEI / SN / 型号')"
                        clearable
                        style="width: 260px"
                        @keyup.enter="reload"
                        @clear="reload"
                    >
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button type="primary" class="ml-2" @click="reload">{{ t('搜索') }}</el-button>
                </div>

                <!-- 工单表（FIFO：越靠上越早进入该环节，先处理） -->
                <el-table :data="tableData" v-loading="loading" size="large" row-key="device_id">
                    <template #empty>
                        <EmptyState v-if="!loading" icon="inbox" title="暂无待处理工单" description="该环节当前没有排队的设备。" />
                    </template>

                    <el-table-column type="index" :label="t('队列')" width="64" align="center" />

                    <el-table-column :label="t('设备 / 订单')" min-width="220">
                        <template #default="{ row }">
                            <template v-if="row.is_order">
                                <div class="font-medium">{{ t('订单') }} {{ row.order_no || row.order_id }}</div>
                                <div class="text-gray-400 text-xs">
                                    {{ row.customer_name || '—' }}
                                    <span v-if="row.express_no"> · {{ row.express_company }} {{ row.express_no }}</span>
                                </div>
                            </template>
                            <template v-else>
                                <div class="font-medium">{{ row.model || '—' }}</div>
                                <div class="text-gray-400 text-xs">{{ row.imei || row.sn || '—' }}</div>
                            </template>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('环节')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="stageTagType(row.stage_key)">{{ stageName(row.stage_key) }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('价格 / 台数')" width="120" align="right">
                        <template #default="{ row }">
                            <span v-if="row.is_order">{{ row.device_count || 0 }} {{ t('台') }}</span>
                            <span v-else>¥{{ money(row.final_price || row.initial_price) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('认领人')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.assignee_uid" size="small" :type="row.is_mine ? 'success' : 'info'" effect="plain">
                                {{ row.is_mine ? t('我') : row.assignee_name }}
                            </el-tag>
                            <span v-else class="text-gray-400 text-xs">{{ t('未认领') }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" align="right" width="220">
                        <template #default="{ row }">
                            <el-button v-if="!row.assignee_uid" type="primary" link @click="onClaim(row)">{{ t('认领') }}</el-button>
                            <el-button v-else-if="row.is_mine" type="warning" link @click="onRelease(row)">{{ t('释放') }}</el-button>
                            <el-button type="primary" link @click="onProcess(row)">{{ t('去处理') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-end mt-[16px]">
                    <el-pagination
                        v-model:current-page="pagination.page"
                        v-model:page-size="pagination.limit"
                        :page-sizes="[15, 30, 50]"
                        :total="pagination.total"
                        layout="total, sizes, prev, pager, next"
                        @size-change="reload"
                        @current-change="loadList"
                    />
                </div>
            </template>
        </el-card>
    </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'
import { t } from '@/lang'
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh } from '@element-plus/icons-vue'
import { getMyStages, getTaskList, claimTask, releaseTask } from '../../api/task'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const stages = ref<any[]>([])
const activeStage = ref<string>(typeof route.query.stage === 'string' ? route.query.stage : '')
const keyword = ref('')
const tableData = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 15, total: 0 })

const STAGE_NAME: Record<string, string> = {
    sign: '待签收', check: '质检', price: '定价', confirm: '报价确认', pay: '打款', abnormal: '异常处理'
}
const STAGE_TAG: Record<string, string> = {
    sign: 'info', check: 'primary', price: 'success', confirm: 'warning', pay: '', abnormal: 'danger'
}
const STAGE_ORDER = ['sign', 'check', 'price', 'confirm', 'pay', 'abnormal']
const stageName = (k: string) => STAGE_NAME[k] || k
const stageTagType = (k: string) => STAGE_TAG[k] ?? ''
const money = (v: any) => Number(v || 0).toFixed(2)

const loadMyStages = async () => {
    try {
        const res: any = await getMyStages()
        const keys: string[] = res.data || []
        // 仅保留我有权限的环节，按预设顺序展示
        stages.value = STAGE_ORDER
            .filter((k) => keys.includes(k))
            .map((k) => ({ stage_key: k, name: STAGE_NAME[k] }))
        // activeStage 若不在我的环节里则回退到全部
        if (activeStage.value && !keys.includes(activeStage.value)) activeStage.value = ''
    } catch (e) {
        stages.value = []
    }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getTaskList({
            stage: activeStage.value,
            keyword: keyword.value,
            page: pagination.page,
            limit: pagination.limit
        })
        tableData.value = res.data?.list || []
        pagination.total = res.data?.count || 0
    } catch (e) {
        tableData.value = []
        pagination.total = 0
    } finally {
        loading.value = false
    }
}

const reload = () => {
    pagination.page = 1
    loadList()
}

const onStageChange = () => {
    pagination.page = 1
    loadList()
}

const loadAll = async () => {
    await loadMyStages()
    if (stages.value.length) await loadList()
}

const onClaim = async (row: any) => {
    await claimTask({ device_id: row.device_id, stage_key: row.stage_key })
    loadList()
}

const onRelease = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('确定释放该任务？释放后其他人可认领。'), t('提示'), { type: 'warning' })
    } catch (e) {
        return
    }
    await releaseTask({ device_id: row.device_id, stage_key: row.stage_key })
    loadList()
}

// 去回收订单管理页定位订单/设备，执行真正的签收/质检/定价/确认/打款动作
const onProcess = (row: any) => {
    const query: Record<string, any> = { order_id: row.order_id || '', t: Date.now() }
    if (!row.is_order && row.imei) query.imei = row.imei   // 设备级带 IMEI 精确定位；签收只带订单
    router.push({ path: '/site/recycle_order/list', query })
}

onMounted(() => {
    loadAll()
})
</script>

<style lang="scss" scoped>
.recycle-my-task {
    :deep(.el-tabs__header) { margin-bottom: 12px; }
}
</style>
