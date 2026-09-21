<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center gap-6">
                <div>
                    <span class="text-page-title">商城资料运营</span>
                    <div class="mt-1 text-sm text-gray-400">承接 ERP 拍照定价交接，分别跟进商品上架与资料完善</div>
                </div>
            </div>

            <el-alert class="mt-3" type="info" :closable="true" show-icon :title="policyText" />

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="table.searchParam">
                    <el-form-item label="建品状态">
                        <el-select v-model="table.searchParam.status" placeholder="全部" clearable class="w-[140px]">
                            <el-option label="待建品" :value="0" />
                            <el-option label="已建品" :value="1" />
                            <el-option label="已忽略" :value="2" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="资料进度">
                        <el-select v-model="table.searchParam.material_status" placeholder="全部" clearable class="w-[140px]">
                            <el-option label="待完善" value="pending" /><el-option label="处理中" value="processing" /><el-option label="已完成" value="completed" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="设备搜索">
                        <el-input v-model.trim="table.searchParam.keyword" placeholder="型号 / IMEI" clearable @keyup.enter="loadList()" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">查询</el-button>
                        <el-button @click="resetSearch">重置</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="table.data" size="large" v-loading="table.loading">
                    <template #empty>
                        <span>{{ !table.loading ? '暂无货源' : '' }}</span>
                    </template>
                    <el-table-column label="图片" width="80">
                        <template #default="{ row }">
                            <el-image v-if="firstImage(row)" :src="img(firstImage(row))" fit="cover"
                                      class="w-[48px] h-[48px] rounded" :preview-src-list="imageList(row)" :preview-teleported="true" />
                            <span v-else class="text-gray-300">无图</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="型号/配置" min-width="180">
                        <template #default="{ row }">
                            <div class="font-medium">{{ row.model_name || '—' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ [row.brand_name, row.memory, row.color, row.condition_grade].filter(Boolean).join(' / ') || '—' }}
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="imei" label="IMEI" min-width="130" :show-overflow-tooltip="true" />
                    <el-table-column label="价格(销售/同行/成本)" min-width="170">
                        <template #default="{ row }">
                            <div class="text-danger font-medium">¥{{ row.sale_price }}</div>
                            <div class="text-xs text-gray-500">同行 ¥{{ row.peer_price }} · 成本 ¥{{ row.cost_price }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="商城状态" min-width="150">
                        <template #default="{ row }">
                            <el-tag :type="row.listing_state === 'saleable' ? 'success' : 'info'" size="small">{{ row.listing_state_name }}</el-tag>
                            <div v-if="row.material_block_reason" class="text-xs text-orange-500 mt-1">{{ row.material_block_reason }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="资料待办" min-width="175">
                        <template #default="{ row }">
                            <template v-if="row.material_task?.status !== 'none'">
                                <el-tag :type="row.material_task?.status === 'completed' ? 'success' : 'warning'" size="small">{{ row.material_task?.status_name }}</el-tag>
                                <el-tooltip v-if="row.material_unknown_fields?.length" :content="'未记录：' + row.material_unknown_fields.join('、')" placement="top">
                                    <span class="ml-2 text-xs text-gray-400">{{ row.material_unknown_fields.length }} 项未记录</span>
                                </el-tooltip>
                                <div v-if="row.material_task?.operator_name" class="text-xs text-gray-500 mt-1">{{ row.material_task.operator_name }} · {{ row.material_task.status === 'completed' ? '已核对' : '已保存' }}</div>
                            </template>
                            <span v-else class="text-xs text-gray-400">未启用分岗待办</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" width="210" align="right">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="showDetail(row)">详情</el-button>
                            <el-button v-if="row.status === 0 && policy.can_phone_shop_operate === 1" type="success" link @click="openBuild(row)" v-permission="'phone_shop_intake_build'">{{ row.material_task?.status === 'none' ? '完善并上架' : '对应分类并上架' }}</el-button>
                            <el-button v-if="row.status === 1 && row.goods_id && row.material_task?.status !== 'none'" type="primary" link @click="buildDialog?.open(row, 'material')" v-permission="'phone_shop_intake_material_edit'">{{ row.material_task?.status === 'completed' ? '查看完善记录' : '完善资料' }}</el-button>
                            <el-button v-if="row.status === 0" type="info" link @click="markStatus(row, 2)">忽略</el-button>
                            <el-button v-if="row.status === 1 && row.goods_id" type="primary" link @click="goGoods(row)">查看商品</el-button>
                            <el-button v-if="row.status === 2" type="primary" link @click="markStatus(row, 0)">恢复</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit"
                                   layout="total, sizes, prev, pager, next, jumper" :total="table.total"
                                   @size-change="loadList()" @current-change="loadList" />
                </div>
            </div>
        </el-card>

        <!-- 货源详情 -->
        <el-dialog v-model="detail.visible" title="货源详情" width="640px">
            <el-descriptions :column="2" border v-if="detail.data">
                <el-descriptions-item label="型号">{{ detail.data.model_name || '—' }}</el-descriptions-item>
                <el-descriptions-item label="品牌">{{ detail.data.brand_name || '—' }}</el-descriptions-item>
                <el-descriptions-item label="内存">{{ detail.data.memory || '—' }}</el-descriptions-item>
                <el-descriptions-item label="颜色">{{ detail.data.color || '—' }}</el-descriptions-item>
                <el-descriptions-item label="成色">{{ detail.data.condition_grade || '—' }}</el-descriptions-item>
                <el-descriptions-item label="IMEI">{{ detail.data.imei || '—' }}</el-descriptions-item>
                <el-descriptions-item label="ERP资产ID">{{ detail.data.erp_asset_id }}</el-descriptions-item>
                <el-descriptions-item label="回收设备ID">{{ detail.data.device_id }}</el-descriptions-item>
                <el-descriptions-item label="销售价">¥{{ detail.data.sale_price }}</el-descriptions-item>
                <el-descriptions-item label="同行价">¥{{ detail.data.peer_price }}</el-descriptions-item>
                <el-descriptions-item label="成本价">¥{{ detail.data.cost_price }}</el-descriptions-item>
                <el-descriptions-item label="状态">{{ detail.data.status_name }}</el-descriptions-item>
            </el-descriptions>
            <div v-if="detail.data && imageList(detail.data).length" class="mt-3">
                <div class="text-sm text-gray-500 mb-1">设备图片</div>
                <div class="flex flex-wrap gap-2">
                    <el-image v-for="(u, i) in imageList(detail.data)" :key="i" :src="img(u)" fit="cover"
                              class="w-[72px] h-[72px] rounded" :preview-src-list="imageList(detail.data)" :initial-index="i" :preview-teleported="true" />
                </div>
            </div>
            <el-collapse v-if="detail.data && qcText(detail.data)" class="mt-3">
                <el-collapse-item title="原始质检备查" name="raw-qc"><pre class="text-xs bg-gray-50 p-2 rounded whitespace-pre-wrap">{{ qcText(detail.data) }}</pre></el-collapse-item>
            </el-collapse>
        </el-dialog>

        <IntakeBuildDialog ref="buildDialog" @published="loadList(table.page)" @saved="loadList(table.page)" />
    </div>
</template>

<script lang="ts" setup>
import { reactive, computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { img } from '@/utils/common'
import { getDeviceIntakePages, getDeviceIntakeInfo, setDeviceIntakeStatus, getDeviceIntakeMaterialPolicy } from '@/addon/phone_shop/api/device_intake'
import IntakeBuildDialog from './components/IntakeBuildDialog.vue'
const buildDialog = ref<InstanceType<typeof IntakeBuildDialog>>()
const openBuild = (row: any) => buildDialog.value?.open(row)

const router = useRouter()
// 未加载到 ERP 策略前默认禁用商城侧操作，避免接口返回前短暂出现越权按钮。
const policy = reactive({ owner: 'erp', owner_label: 'ERP 库存人员', can_phone_shop_operate: 0, basic_first: 0, enabled: 1 })
const policyText = computed(() => policy.enabled === 0 ? '商城渠道已关闭，不能新建并上架。已有资料待办仍可处理，不改变商品的上下架状态。' : policy.basic_first === 1
    ? '先上架，运营后补：分类已对应的设备拍照定价交接后即可购买。资料待办独立处理，不会改变商品价格、库存和交易状态。'
    : policy.can_phone_shop_operate === 1 ? '当前由商城运营核对分类、规格并上架，不会覆盖 ERP 主资料。' : '当前由 ERP 完善并直接上架。已有资料待办仍可继续处理。')
getDeviceIntakeMaterialPolicy().then((res: any) => Object.assign(policy, res?.data || {})).catch(() => {})

const table = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [] as any[],
    searchParam: {
        status: '' as number | string,
        material_status: '',
        keyword: ''
    }
})

const loadList = (page: number = 1) => {
    table.loading = true
    table.page = page
    getDeviceIntakePages({
        page: table.page,
        limit: table.limit,
        ...table.searchParam
    }).then((res: any) => {
        table.loading = false
        table.data = res.data.data
        table.total = res.data.total
    }).catch(() => {
        table.loading = false
    })
}
loadList()

const resetSearch = () => {
    table.searchParam = { status: '', material_status: '', keyword: '' }
    loadList()
}

const parseArr = (v: any): string[] => {
    if (!v) return []
    if (Array.isArray(v)) return v
    try { const p = JSON.parse(v); return Array.isArray(p) ? p : [] } catch { return [] }
}
const imageList = (row: any): string[] => parseArr(row.images)
const firstImage = (row: any): string => imageList(row)[0] || ''

const qcText = (row: any): string => {
    const q = row.qc_info
    if (!q) return ''
    if (typeof q === 'string') return q
    try { return JSON.stringify(q, null, 2) } catch { return String(q) }
}


const detail = reactive({ visible: false, data: null as any })
const showDetail = (row: any) => {
    getDeviceIntakeInfo(row.intake_id).then((res: any) => {
        detail.data = res.data
        detail.visible = true
    })
}

const markStatus = (row: any, status: number) => {
    setDeviceIntakeStatus({ intake_id: row.intake_id, status }).then(() => loadList(table.page))
}


const goGoods = (row: any) => {
    router.push('/phone_shop/goods/list')
}
</script>
