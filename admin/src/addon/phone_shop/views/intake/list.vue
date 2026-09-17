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
                            <el-button v-if="row.status === 1 && row.goods_id && row.material_task?.status !== 'none'" type="primary" link @click="materialDrawer?.open(row.intake_id)" v-permission="'phone_shop_intake_material_edit'">{{ row.material_task?.status === 'completed' ? '查看完善记录' : '完善资料' }}</el-button>
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
        <IntakeMaterialDrawer ref="materialDrawer" @saved="loadList(table.page)" />

        <!-- 建品上架 -->
        <el-dialog v-model="build.visible" title="整理商城资料并上架" width="720px" :close-on-click-modal="false">
            <el-form :model="build.form" label-width="100px" v-loading="build.submitting || build.prefilling">
                <el-alert type="success" :closable="false" show-icon class="mb-3"
                          :title="build.basicFirst ? '图片、价格和质检已带入。确认分类后立即上架可购买，细节可在资料待办中继续补充。' : '图片、销售价格和质检报告已由 ERP 带入；请核对分类、规格与展示标签，发布后会回写上架关联，不覆盖 ERP 主资料。'" />
                <el-form-item label="标题" required>
                    <el-input v-model="build.form.goods_name" placeholder="商品标题（自动：品牌 型号 内存 颜色）" />
                </el-form-item>
                <el-form-item label="副标题">
                    <el-input v-model="build.form.sub_title" placeholder="卖点副标题（自动：成色 · 一机一检 · 七天质保）" />
                </el-form-item>
                <el-form-item label="品牌">
                    <el-select v-model="build.form.brand_id" placeholder="选择品牌" clearable filterable class="w-full">
                        <el-option v-for="b in brandOptions" :key="b.brand_id" :label="b.brand_name" :value="b.brand_id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="商品分类" required>
                    <el-cascader v-model="build.form.goods_category" :options="categoryOptions" :props="categoryProps"
                                 clearable filterable class="w-full" placeholder="选择分类（支持三级）" @change="onBuildCategoryChange" />
                </el-form-item>
                <el-form-item v-if="!build.basicFirst" label="服务标签">
                    <el-select v-model="build.form.service_ids" placeholder="服务保障（自动按默认带入，可改）" multiple clearable filterable class="w-full">
                        <el-option v-for="s in serviceOptions" :key="s.service_id" :label="s.service_name" :value="s.service_id" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="!build.basicFirst" label="标签">
                    <el-select v-model="build.form.label_ids" placeholder="选择标签" multiple clearable filterable class="w-full">
                        <el-option v-for="l in labelOptions" :key="l.label_id" :label="l.label_name" :value="l.label_id" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="!build.basicFirst" :label="specLabel">
                    <el-select v-model="build.form.memory" :placeholder="`选择${specLabel}（按分类配好的规格，也可手填）`" filterable allow-create default-first-option clearable class="w-full">
                        <el-option v-for="v in specItems" :key="v" :label="v" :value="v" />
                    </el-select>
                    <div v-if="build.form.goods_category.length && !specItems.length" class="text-xs text-gray-400">该分类未配规格，去「商品 → 规格管理」给它配，或直接手填。</div>
                </el-form-item>
                <el-form-item v-if="!build.basicFirst" label="成色">
                    <el-select v-model="build.form.condition_grade" placeholder="选择成色（可手填）" filterable allow-create default-first-option clearable class="w-full">
                        <el-option v-for="g in gradeOptions" :key="g.grade_id" :label="g.grade_name" :value="g.grade_name" />
                    </el-select>
                </el-form-item>
                <el-form-item label="发货方式" required>
                    <el-checkbox-group v-model="build.form.delivery_type">
                        <el-checkbox v-for="d in deliveryOptions" :key="d.value" :label="d.value">{{ d.label }}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item label="价格">
                    <div class="price-grid">
                        <div class="price-cell">
                            <span class="price-lbl">销售价</span>
                            <TierPriceInput v-model="build.form.price" v-model:base-price="build.form.pricing_base_price" :disabled="build.basicFirst" @policy="value => autoTierPricing = Number(value.enabled) === 1" />
                        </div>
                        <div class="price-cell">
                            <span class="price-lbl">划线价</span>
                            <el-input-number v-model="build.form.market_price" :min="0" :precision="2" :controls="false" :disabled="build.basicFirst" class="price-num" />
                        </div>
                        <div class="price-cell">
                            <span class="price-lbl">成本价</span>
                            <el-input-number v-model="build.form.cost_price" :min="0" :precision="2" :controls="false" :disabled="build.basicFirst" class="price-num" />
                        </div>
                        <div v-if="!autoTierPricing" class="price-cell">
                            <span class="price-lbl">同行价</span>
                            <el-input-number v-model="build.form.peer_price" :min="0" :precision="2" :controls="false" class="price-num" disabled />
                            <span class="price-hint">来自货源 · 推送为同行会员价</span>
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="质检报告">
                    <div class="w-full">
                        <CheckResultPanel
                            v-if="build.check && ((build.check.result_items && build.check.result_items.length) || (build.check.summary_fields && build.check.summary_fields.length))"
                            :summary-fields="build.check.summary_fields"
                            :severity-summary="build.check.severity_summary"
                            :abnormal-items="build.check.abnormal_items"
                            :items="build.check.result_items"
                        />
                        <el-text v-else type="info" size="small">该货源暂无可展示的结构化质检报告</el-text>
                    </div>
                </el-form-item>
                <el-form-item v-if="!build.basicFirst" label="商品详情文案（可选）">
                    <el-input v-model="build.form.goods_desc" type="textarea" :rows="3" placeholder="填写卖点、售后或购买说明；质检内容由上方质检报告独立展示" />
                </el-form-item>
                <el-form-item v-if="build.imageCount === 0">
                    <el-alert type="warning" :closable="false" show-icon title="该货源暂无图片，请回 ERP 上传图片并重新交接后再上架。" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="build.visible = false">取消</el-button>
                <el-button type="primary" :loading="build.submitting" @click="submitBuild">确认发布商城</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { reactive, computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { img } from '@/utils/common'
import { ElMessage } from 'element-plus'
import { getDeviceIntakePages, getDeviceIntakeInfo, setDeviceIntakeStatus, buildDeviceIntake, previewDeviceIntake, getDeviceIntakeMaterialPolicy } from '@/addon/phone_shop/api/device_intake'
import { getBrandList, getCategoryTree, getLabelList } from '@/addon/phone_shop/api/goods'
import { getSpecOptionsByCategory, getGrades } from '@/addon/phone_shop/api/spec'
import CheckResultPanel from '@/addon/phone_shop/components/CheckResultPanel.vue'
import IntakeMaterialDrawer from './components/IntakeMaterialDrawer.vue'
import TierPriceInput from '@/addon/phone_shop/views/goods/components/TierPriceInput.vue'
const autoTierPricing = ref(false)

const router = useRouter()
// 未加载到 ERP 策略前默认禁用商城侧操作，避免接口返回前短暂出现越权按钮。
const policy = reactive({ owner: 'erp', owner_label: 'ERP 库存人员', can_phone_shop_operate: 0, basic_first: 0, enabled: 1 })
const policyText = computed(() => policy.enabled === 0 ? '商城渠道已关闭，不能新建并上架。已有资料待办仍可处理，不改变商品的上下架状态。' : policy.basic_first === 1
    ? '先上架，运营后补：分类已对应的设备拍照定价交接后即可购买。资料待办独立处理，不会改变商品价格、库存和交易状态。'
    : policy.can_phone_shop_operate === 1 ? '当前由商城运营核对分类、规格并上架，不会覆盖 ERP 主资料。' : '当前由 ERP 完善并直接上架。已有资料待办仍可继续处理。')
getDeviceIntakeMaterialPolicy().then((res: any) => Object.assign(policy, res?.data || {})).catch(() => {})
const materialDrawer = ref<InstanceType<typeof IntakeMaterialDrawer>>()

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

// —— 建品表单选项 ——
const brandOptions = reactive<any[]>([])
const labelOptions = reactive<any[]>([])
const categoryOptions = reactive<any[]>([])
const categoryProps = { value: 'category_id', label: 'category_name', children: 'child_list', checkStrictly: true, emitPath: true }

const loadOptions = () => {
    getGrades().then((res: any) => { gradeOptions.splice(0, gradeOptions.length, ...(res.data || [])) })
    getBrandList({}).then((res: any) => { brandOptions.splice(0, brandOptions.length, ...(res.data || [])) })
    getLabelList({}).then((res: any) => { labelOptions.splice(0, labelOptions.length, ...(res.data || [])) })
    getCategoryTree().then((res: any) => { categoryOptions.splice(0, categoryOptions.length, ...(res.data || [])) })
}
loadOptions()

// —— 建品上架 ——
// 发货方式可选项（默认快递；可扩展到店自提等）
const deliveryOptions = [
    { value: 'express', label: '快递发货' },
    { value: 'local', label: '同城配送' },
    { value: 'store', label: '到店自提' }
]
const serviceOptions = reactive<any[]>([]) // 服务标签可选项（站点服务保障）
const allDelivery = deliveryOptions.map(d => d.value)

// 规格(内存/表盘尺寸)与成色：按分类配好的下拉，可手填
const specGroups = reactive<any[]>([])
const gradeOptions = reactive<any[]>([])
const specLabel = computed(() => (specGroups[0]?.label) || '内存')
const specItems = computed(() => (specGroups[0]?.items || []).map((x: any) => x.item_value))
const loadSpecOptions = (categoryPath: any[]) => {
    const ids = (categoryPath || []).map((x: any) => Number(x)).filter(Boolean)
    if (!ids.length) { specGroups.splice(0); return }
    getSpecOptionsByCategory({ category_id: ids[ids.length - 1], 'category_path[]': ids }).then((res: any) => {
        specGroups.splice(0, specGroups.length, ...((res.data?.spec_groups) || []))
        gradeOptions.splice(0, gradeOptions.length, ...((res.data?.grades) || []))
    })
}
const onBuildCategoryChange = (path: any) => loadSpecOptions(path || [])

const build = reactive({
    visible: false,
    submitting: false,
    prefilling: false,
    imageCount: 0,
    basicFirst: false,
    qcReport: { title: '', items: [] as any[], text: '', enabled: true },
    check: {} as any, // 结构化质检(异常优先/折叠),由预览接口的 check 字段提供
    form: {
        intake_id: 0,
        goods_name: '',
        sub_title: '',
        brand_id: '' as number | string,
        goods_category: [] as any[],
        label_ids: [] as any[],
        service_ids: [] as any[],
        memory: '',
        condition_grade: '',
        delivery_type: [...allDelivery] as string[],
        price: 0,
        market_price: 0,
        cost_price: 0,
        peer_price: 0,
        pricing_base_price: 0,
        goods_desc: ''
    }
})

const openBuild = (row: any) => {
    // 先用本地信息即时铺底，避免空窗
    const name = [row.model_name, row.memory, row.condition_grade].filter(Boolean).join(' ')
    build.imageCount = imageList(row).length
    build.basicFirst = !!row.material_task && row.material_task.status !== 'none'
    build.qcReport = { title: '', items: [], text: '', enabled: true }
    build.check = {}
    build.form = {
        intake_id: row.intake_id,
        goods_name: name,
        sub_title: '',
        brand_id: '',
        goods_category: [],
        label_ids: [],
        service_ids: [],
        memory: row.memory || '',
        condition_grade: row.condition_grade || '',
        delivery_type: [...allDelivery],
        price: Number(row.sale_price) || 0,
        market_price: 0,
        cost_price: Number(row.cost_price) || 0,
        peer_price: Number(row.peer_price) || 0,
        pricing_base_price: Number(row.peer_price) || 0,
        goods_desc: ''
    }
    specGroups.splice(0) // 清空上一台的规格选项，按本台分类重新取
    build.visible = true
    // 调清洗映射引擎预填 6 字段（标题/副标题/内存分类/服务标签/质检报告/发货方式）
    build.prefilling = true
    previewDeviceIntake({ intake_id: row.intake_id }).then((res: any) => {
        const m = res.data || {}
        serviceOptions.splice(0, serviceOptions.length, ...(m.service_options || []))
        build.qcReport = m.qc_report || build.qcReport
        build.check = m.check || {}
        build.form.goods_name = m.goods_name || build.form.goods_name
        build.form.sub_title = m.sub_title || ''
        if (Array.isArray(m.goods_category) && m.goods_category.length) {
            build.form.goods_category = m.goods_category.map((id: any) => Number(id)).filter(Boolean)
            loadSpecOptions(build.form.goods_category)
        }
        build.form.memory = m.memory_group || build.form.memory
        build.form.condition_grade = m.condition_grade || build.form.condition_grade
        build.form.service_ids = m.service_ids || []
        build.form.label_ids = m.label_ids || []
        build.form.delivery_type = (m.delivery_type && m.delivery_type.length) ? m.delivery_type : ['express']
        build.form.price = Number(m.price) || build.form.price
        build.form.peer_price = Number(m.peer_price) || build.form.peer_price
        // 商品详情只承载运营文案；质检报告通过 qc_report 独立保存和渲染。
        build.form.goods_desc = ''
    }).finally(() => { build.prefilling = false })
}

const submitBuild = () => {
    if (!build.form.goods_name) return ElMessage.warning('请填写商品名称')
    if (!build.form.goods_category || build.form.goods_category.length === 0) return ElMessage.warning('请选择商品分类')
    if (!build.form.delivery_type || build.form.delivery_type.length === 0) return ElMessage.warning('请选择发货方式')
    if (Number(build.form.price) <= 0) return ElMessage.warning('销售价必须大于 0')
    if (!build.imageCount) return ElMessage.warning('请先回 ERP 补充商品图片并重新交接')
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

<style lang="scss" scoped>
/* 价格区:2 列网格,标签在左、输入加宽,避免换行;同行价只读展示 */
.price-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 20px;
    width: 100%;
}
.price-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}
.price-lbl {
    flex: none;
    width: 48px;
    font-size: 13px;
    color: var(--el-text-color-regular);
}
.price-num {
    width: 160px;
}
.price-hint {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
}
.qc-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px 12px;
    width: 100%;
    max-height: 160px;
    overflow-y: auto;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #eef0f3;
    border-radius: 6px;
}
.qc-cell {
    display: flex;
    align-items: baseline;
    font-size: 12px;
    line-height: 1.6;
}
.qc-k {
    color: #94a3b8;
    flex-shrink: 0;
    margin-right: 6px;
    min-width: 56px;
}
.qc-v {
    color: #334155;
    word-break: break-all;
}
</style>
