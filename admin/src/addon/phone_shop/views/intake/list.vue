<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">待上架货源</span>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-400">中台定价完成的设备会自动进入此列表，销售对照信息录入商品后标记"已建品"</span>
                    <el-button type="danger" plain size="small" @click="syncSchema">同步表结构</el-button>
                    <el-button type="warning" plain size="small" @click="seedTest">造测试数据(临时)</el-button>
                </div>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="table.searchParam">
                    <el-form-item label="状态">
                        <el-select v-model="table.searchParam.status" placeholder="全部" clearable class="w-[140px]">
                            <el-option label="待建品" :value="0" />
                            <el-option label="已建品" :value="1" />
                            <el-option label="已忽略" :value="2" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="型号">
                        <el-input v-model.trim="table.searchParam.model_name" placeholder="型号关键词" clearable />
                    </el-form-item>
                    <el-form-item label="资产ID">
                        <el-input v-model.trim="table.searchParam.erp_asset_id" placeholder="ERP资产ID" clearable />
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
                    <el-table-column prop="erp_asset_id" label="资产ID" width="90" />
                    <el-table-column label="价格(销售/同行/成本)" min-width="170">
                        <template #default="{ row }">
                            <div class="text-danger font-medium">¥{{ row.sale_price }}</div>
                            <div class="text-xs text-gray-500">同行 ¥{{ row.peer_price }} · 成本 ¥{{ row.cost_price }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="statusTag(row.status)" size="small">{{ row.status_name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" width="200" align="right">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="showDetail(row)">详情</el-button>
                            <el-button v-if="row.status === 0" type="success" link @click="openBuild(row)">建品上架</el-button>
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
            <div v-if="detail.data && qcText(detail.data)" class="mt-3">
                <div class="text-sm text-gray-500 mb-1">质检信息</div>
                <pre class="text-xs bg-gray-50 p-2 rounded whitespace-pre-wrap">{{ qcText(detail.data) }}</pre>
            </div>
        </el-dialog>

        <!-- 建品上架 -->
        <el-dialog v-model="build.visible" title="建品上架" width="680px" :close-on-click-modal="false">
            <el-form :model="build.form" label-width="100px" v-loading="build.submitting">
                <el-form-item label="商品名称" required>
                    <el-input v-model="build.form.goods_name" placeholder="商品标题" />
                </el-form-item>
                <el-form-item label="品牌">
                    <el-select v-model="build.form.brand_id" placeholder="选择品牌" clearable filterable class="w-full">
                        <el-option v-for="b in brandOptions" :key="b.brand_id" :label="b.brand_name" :value="b.brand_id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="商品分类" required>
                    <el-cascader v-model="build.form.goods_category" :options="categoryOptions" :props="categoryProps"
                                 clearable filterable class="w-full" placeholder="选择分类（支持三级）" />
                </el-form-item>
                <el-form-item label="标签">
                    <el-select v-model="build.form.label_ids" placeholder="选择标签" multiple clearable filterable class="w-full">
                        <el-option v-for="l in labelOptions" :key="l.label_id" :label="l.label_name" :value="l.label_id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="内存">
                    <el-input v-model="build.form.memory" placeholder="如 256G / 8+256G" />
                </el-form-item>
                <el-form-item label="成色">
                    <el-input v-model="build.form.condition_grade" placeholder="如 99新" />
                </el-form-item>
                <el-form-item label="价格">
                    <div class="flex items-center gap-2">
                        <el-input-number v-model="build.form.price" :min="0" :precision="2" controls-position="right" />
                        <span class="text-xs text-gray-400">销售价</span>
                        <el-input-number v-model="build.form.market_price" :min="0" :precision="2" controls-position="right" />
                        <span class="text-xs text-gray-400">划线价</span>
                        <el-input-number v-model="build.form.cost_price" :min="0" :precision="2" controls-position="right" />
                        <span class="text-xs text-gray-400">成本价</span>
                    </div>
                </el-form-item>
                <el-form-item label="商品详情">
                    <el-input v-model="build.form.goods_desc" type="textarea" :rows="3" placeholder="可填质检摘要/卖点" />
                </el-form-item>
                <el-form-item v-if="build.imageCount === 0">
                    <el-alert type="warning" :closable="false" show-icon title="该货源暂无图片，建出的商品将没有主图，建议补图后再上架（测试数据无图可忽略）" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="build.visible = false">取消</el-button>
                <el-button type="primary" :loading="build.submitting" @click="submitBuild">确认建品上架</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { img } from '@/utils/common'
import { ElMessage } from 'element-plus'
import { getDeviceIntakePages, getDeviceIntakeInfo, setDeviceIntakeStatus, seedTestDeviceIntake, buildDeviceIntake, syncDeviceIntakeSchema } from '@/addon/phone_shop/api/device_intake'
import { getBrandList, getCategoryTree, getLabelList } from '@/addon/phone_shop/api/goods'

const router = useRouter()

const table = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [] as any[],
    searchParam: {
        status: '' as number | string,
        model_name: '',
        erp_asset_id: ''
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
    table.searchParam = { status: '', model_name: '', erp_asset_id: '' }
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

const statusTag = (s: number) => (s === 1 ? 'success' : s === 2 ? 'info' : 'warning')

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

// 手动同步表结构（补缺列）
const syncSchema = () => {
    syncDeviceIntakeSchema().then((res: any) => {
        ElMessage.success('表结构已同步：' + JSON.stringify(res.data))
    })
}

// 【临时】造测试数据
const seedTest = () => {
    seedTestDeviceIntake().then((res: any) => {
        ElMessage.success('已灌入 ' + (res.data?.seeded ?? 0) + ' 条测试货源')
        loadList(1)
    })
}

// —— 建品表单选项 ——
const brandOptions = reactive<any[]>([])
const labelOptions = reactive<any[]>([])
const categoryOptions = reactive<any[]>([])
const categoryProps = { value: 'category_id', label: 'category_name', children: 'child_list', checkStrictly: true, emitPath: true }

const loadOptions = () => {
    getBrandList({}).then((res: any) => { brandOptions.splice(0, brandOptions.length, ...(res.data || [])) })
    getLabelList({}).then((res: any) => { labelOptions.splice(0, labelOptions.length, ...(res.data || [])) })
    getCategoryTree().then((res: any) => { categoryOptions.splice(0, categoryOptions.length, ...(res.data || [])) })
}
loadOptions()

// —— 建品上架 ——
const build = reactive({
    visible: false,
    submitting: false,
    imageCount: 0,
    form: {
        intake_id: 0,
        goods_name: '',
        brand_id: '' as number | string,
        goods_category: [] as any[],
        label_ids: [] as any[],
        memory: '',
        condition_grade: '',
        price: 0,
        market_price: 0,
        cost_price: 0,
        goods_desc: ''
    }
})

const openBuild = (row: any) => {
    const name = [row.model_name, row.memory, row.condition_grade].filter(Boolean).join(' ')
    build.imageCount = imageList(row).length
    build.form = {
        intake_id: row.intake_id,
        goods_name: name,
        brand_id: '',
        goods_category: [],
        label_ids: [],
        memory: row.memory || '',
        condition_grade: row.condition_grade || '',
        price: Number(row.sale_price) || 0,
        market_price: 0,
        cost_price: Number(row.cost_price) || 0,
        goods_desc: qcText(row) || name
    }
    build.visible = true
}

const submitBuild = () => {
    if (!build.form.goods_name) return ElMessage.warning('请填写商品名称')
    if (!build.form.goods_category || build.form.goods_category.length === 0) return ElMessage.warning('请选择商品分类')
    build.submitting = true
    const cat = Array.isArray(build.form.goods_category) ? build.form.goods_category : [build.form.goods_category]
    buildDeviceIntake({ ...build.form, goods_category: cat }).then(() => {
        build.submitting = false
        build.visible = false
        loadList(table.page)
    }).catch(() => { build.submitting = false })
}

const goGoods = (row: any) => {
    router.push('/phone_shop/goods/list')
}
</script>
