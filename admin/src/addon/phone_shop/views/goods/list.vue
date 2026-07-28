<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
                <div class="flex items-center gap-[10px]">
                    <!-- <el-button v-if="!isMasterSite" :loading="syncLoading" @click="syncMasterGoodsFn">一键同步主站商品</el-button> -->
                    <el-button type="primary" @click="addEvent">{{ t('addGoods') }}</el-button>
                </div>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="goodsTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('goodsName')" prop="goods_name">
                        <el-input v-model.trim="goodsTable.searchParam.goods_name" :placeholder="t('goodsNamePlaceholder')" maxlength="60" />
                    </el-form-item>
                    <el-form-item label="多设备" prop="device_keywords">
                        <el-input v-model.trim="goodsTable.searchParam.device_keywords" type="textarea" :autosize="{ minRows: 1, maxRows: 3 }"
                            placeholder="IMEI/资产ID，空格或换行分隔" clearable class="!w-[220px]" @keyup.enter="loadGoodsList()" />
                    </el-form-item>
                    <el-form-item :label="t('goodsCategory')" prop="goods_category">
                        <!-- <el-cascader v-model="goodsTable.searchParam.goods_category" :options="goodsCategoryOptions" :placeholder="t('goodsCategoryPlaceholder')" clearable :props="{ value: 'value', label: 'label', emitPath:false }"/> -->
                        <el-cascader v-model="goodsTable.searchParam.goods_category" ref="cascader" :options="goodsCategoryOptions"  :placeholder="t('goodsCategoryPlaceholder')" clearable :props="goodsCategoryProps"/>
                    </el-form-item>

                    <el-form-item :label="t('brand')" prop="brand_id">
                        <el-select v-model="goodsTable.searchParam.brand_id" :placeholder="t('brandPlaceholder')" clearable filterable remote reserve-keyword :remote-method="getBrandListFn">
                            <el-option v-for="item in brandOptions" :key="item.brand_id" :label="item.brand_name" :value="item.brand_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('labelIds')" prop="label_ids">
                        <el-select v-model="goodsTable.searchParam.label_ids" :placeholder="t('labelIdsPlaceholder')" clearable>
                            <el-option v-for="item in labelOptions" :key="item.label_id" :label="item.label_name" :value="item.label_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('skuPrice')" prop="sku_price">
                        <div class="region-input">
                            <input type="text" :placeholder="t('startPricePlaceholder')" maxlength="10" v-model.trim="goodsTable.searchParam.start_price" @keyup="filterDigit($event)">
                            <span class="separator">-</span>
                            <input type="text" :placeholder="t('endPricePlaceholder')" maxlength="10" v-model.trim="goodsTable.searchParam.end_price" @keyup="filterDigit($event)">
                        </div>
                    </el-form-item>

                    <el-form-item label="内存" prop="memory_group">
                        <el-select v-model="goodsTable.searchParam.memory_group" placeholder="全部内存" clearable filterable class="!w-[140px]">
                            <el-option v-for="m in memOptions" :key="m" :label="m" :value="m" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="成色" prop="condition_grade">
                        <el-select v-model="goodsTable.searchParam.condition_grade" placeholder="全部成色" clearable filterable class="!w-[140px]">
                            <el-option v-for="g in gradeOptions" :key="g" :label="g" :value="g" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="售卖状态" prop="sale_status">
                        <el-select v-model="goodsTable.searchParam.sale_status" placeholder="全部" clearable class="!w-[140px]">
                            <el-option label="在售" value="available" />
                            <el-option label="锁定" value="locked" />
                            <el-option label="已售" value="sold" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="库龄(天)" prop="stock_age">
                        <el-select v-model="stockAgeRange" placeholder="全部库龄" clearable class="!w-[140px]" @change="onStockAgeChange">
                            <el-option v-for="r in stockAgeOptions" :key="r.value" :label="r.label" :value="r.value" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="showSourceFilter" label="归属" prop="source">
                        <el-select v-model="goodsTable.searchParam.source" placeholder="全部" clearable class="!w-[140px]" @change="loadGoodsList()">
                            <el-option label="全部" value="" />
                            <el-option label="自营" :value="SELF_SOURCE" />
                            <el-option label="代理" :value="AGENT_SOURCE" />
                        </el-select>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadGoodsList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">

                <el-tabs v-model="goodsTable.searchParam.status" class="goods-tabs" @tab-click="tabHandleClick">
                    <el-tab-pane :label="t('statusOn')" name="1"></el-tab-pane>
                    <el-tab-pane :label="t('statusOff')" name="0"></el-tab-pane>
                    <el-tab-pane :label="t('statusAll')" name=""></el-tab-pane>
                </el-tabs>

                <div class="mb-[10px] flex items-center">
                    <el-dropdown class="mr-[20px] !text-primary w-[125px]">
                        <span class="el-dropdown-link">
                            <span>{{ currentSelectMode === 'all' ? t('全选所有页') : t('全选当前页')}}</span>(<span class="text-center inline-block">{{ selectedCount }}</span>)
                            <el-icon>
                                <arrow-down />
                            </el-icon>
                        </span>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item class="select-wrap" :class="{ active: currentSelectMode === 'all' }" @click="selectAllPages">
                                    全选所有页
                                </el-dropdown-item>
                                <el-dropdown-item class="select-wrap"  :class="{ active: currentSelectMode === 'page' }" @click="toggleChange">
                                    全选当前页
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>

                    <!-- <el-checkbox v-model="toggleCheckbox" size="large" class="px-[14px]" @change="toggleChange" :indeterminate="isIndeterminate" /> -->

                    <el-button @click="batchGoodsStatus(1)" size="small" v-if="goodsTable.searchParam.status != '1'">{{ t('batchOnGoods') }}</el-button>
                    <el-button @click="batchGoodsStatus(0)" size="small" v-if="goodsTable.searchParam.status != '0'">{{ t('batchOffGoods') }}</el-button>
                    <el-button @click="batchDeleteGoods" size="small">{{ t('batchDeleteGoods') }}</el-button>
                    <el-button @click="batchSetGoods" size="small">{{ t('batchSetting') }}</el-button>
                </div>

                <el-table :data="goodsTable.data" size="large" v-loading="goodsTable.loading" ref="goodsListTableRef" @sort-change="sortChange" :row-key="row => row.goods_id" :default-selection="defaultSelection" @selection-change="handleSelectionChange">
                    <template #empty>
                        <span>{{ !goodsTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column type="selection" width="55" />

                    <el-table-column prop="goods_id" :label="t('goodsInfo')" min-width="300">
                        <template #default="{ row }">
                            <div class="flex items-center cursor-pointer" @click="previewEvent(row)">
                                <div class="min-w-[70px] h-[70px] flex items-center justify-center">
                                    <el-image v-if="row.goods_cover_thumb_small" class="w-[70px] h-[70px]" :src="img(row.goods_cover_thumb_small)" fit="contain">
                                        <template #error>
                                            <div class="image-slot">
                                                <img class="w-[70px] h-[70px]" src="@/addon/phone_shop/assets/goods_default.png" />
                                            </div>
                                        </template>
                                    </el-image>
                                    <img v-else class="w-[70px] h-[70px]" src="@/addon/phone_shop/assets/goods_default.png" fit="contain" />
                                </div>
                                <div class="ml-2  flex flex-col items-start min-w-0">
                                    <span :title="row.goods_name" class="multi-hidden">
                                        <el-tag v-if="row.is_proxy_goods == 1" type="warning" size="small" effect="dark" class="mr-[4px]">代理</el-tag>{{ row.goods_name }}
                                    </span>
                                    <span v-if="row.sub_title" :title="row.sub_title" class="text-[12px] text-[#94a3b8] ellipsis-1 max-w-[220px]">{{ row.sub_title }}</span>
                                    <span v-if="(row.goodsSku || {}).sku_no" class="text-[11px] text-[#64748b] font-mono" :title="row.goodsSku.sku_no">IMEI: {{ row.goodsSku.sku_no }}</span>
                                    <div class="flex items-center flex-wrap gap-[4px] mt-[2px]">
                                        <span v-if="row.memory_group" class="text-[11px] text-[#64748b] bg-[#f1f5f9] rounded px-[4px]">{{ row.memory_group }}</span>
                                        <span v-if="row.condition_grade" class="text-[11px] text-[#64748b] bg-[#f1f5f9] rounded px-[4px]">{{ row.condition_grade }}</span>
                                        <el-tag v-if="row.sale_status === 'sold'" type="info" size="small" effect="plain">已售</el-tag>
                                        <el-tag v-else-if="row.sale_status === 'locked'" type="warning" size="small" effect="plain">锁定</el-tag>
                                        <el-tag v-else-if="Number((row.goodsSku || row.goods_sku || {}).erp_asset_id) > 0" type="success" size="small" effect="plain">在售</el-tag>
                                    </div>
                                    <span class="px-[4px]  text-[12px] text-[#fff] rounded-[4px] bg-primary leading-[18px]" v-if="row.is_gift == 1">赠品</span>
                                    <div class="flex flex-wrap mt-[4px] gap-[4px]">
                                        <el-tooltip v-for="(item, index) in row.active" :key="index" placement="top">
                                            <template #content>
                                                <div style="white-space: pre-wrap">
                                                    {{item.name.trim() || item.short?.active_name }}
                                                </div>
                                            </template>
                                            <span class="text-[12px] text-white rounded-[4px] px-[4px] leading-[18px]"  @click.stop="activeclick(item)" :style="{ backgroundColor: item.short?.bg_color || '#333' }">
                                                {{ item.short?.name }}
                                            </span>
                                        </el-tooltip>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="price" label="价格" min-width="150" sortable="custom">
                        <template #default="{ row }">
                            <div class="price-cell">
                                <div class="cursor-pointer price-wrap" @click="editPriceEvent(row)">
                                    <span class="price-tag retail">零售</span>
                                    <span class="price-val">￥{{ row.goodsSku.price }}</span>
                                    <el-icon class="icon-wrap ml-[3px] invisible"><EditPen /></el-icon>
                                </div>
                                <div><span class="price-tag member">会员</span><span class="price-val member-val">{{ memberPriceText(row) }}</span></div>
                                <div><span class="price-tag cost">成本</span><span class="price-val text-[#94a3b8]">￥{{ (row.goodsSku || {}).cost_price ?? '—' }}</span></div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="stock" :label="t('stock')" min-width="120" sortable="custom">
                        <template #default="{ row }">
                            <div class="cursor-pointer stock-wrap" @click="editStockEvent(row)">
                                <span>{{ row.stock }}</span>
                                <el-icon class="icon-wrap ml-[5px] invisible">
                                    <EditPen />
                                </el-icon>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sale_num" :label="t('saleNum')" min-width="100" sortable="custom" />
                    <el-table-column prop="status" :label="t('status')" min-width="100">
                        <template #default="{ row }">
                            <div v-if="row.status == 1">{{ t('statusOn') }}</div>
                            <div v-if="row.status == 0">{{ t('statusOff') }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sort" :label="t('sort')" min-width="120" sortable="custom">
                        <template #default="{ row }">
                            <el-input v-model.trim="row.sort" class="w-[70px]" maxlength="8" @blur="sortInputListener(row.sort, row)" />
                        </template>
                    </el-table-column>

                    <el-table-column label="库龄" min-width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="stockAgeType(row.create_time)" size="small" effect="plain">{{ stockAgeDays(row.create_time) }}天</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column prop="create_time" :label="t('createTime')" min-width="110" sortable="custom">
                        <template #default="{ row }">
                            <div class="time-cell">
                                <div>{{ dtPart(row.create_time, 'date') }}</div>
                                <div class="time-hms">{{ dtPart(row.create_time, 'time') }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="update_time" label="更新时间" min-width="110" sortable="custom">
                        <template #default="{ row }">
                            <div class="time-cell">
                                <div>{{ dtPart(row.update_time, 'date') }}</div>
                                <div class="time-hms">{{ dtPart(row.update_time, 'time') }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" align="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="primary" link @click="spreadEvent(row)">{{ t('spreadGoods') }}</el-button>
                            <el-button type="primary" link @click="memberPriceEvent(row)">{{ t('memberPrice') }}</el-button>
                            <el-button type="primary" v-if="row.status == 1" link @click="statusChange(row, 0)">{{ t('statusActionOff') }}</el-button>
                            <el-button type="primary" v-else link @click="statusChange(row, 1)">{{ t('statusActionOn') }}</el-button>
                            <el-button type="primary" link @click="copyEvent(row)">{{ t('copyGoods') }}</el-button>
                            <el-button type="primary" v-if="row.status != 1" link @click="deleteEvent(row.goods_id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <!-- <div class="flex items-center flex-1">
                        <el-checkbox v-model="toggleCheckbox" size="large" class="px-[14px]" @change="toggleChange" :indeterminate="isIndeterminate" />
                        <el-button @click="batchGoodsStatus(1)" size="small">{{ t('batchOnGoods') }}</el-button>
                        <el-button @click="batchGoodsStatus(0)" size="small">{{ t('batchOffGoods') }}</el-button>
                        <el-button @click="batchDeleteGoods" size="small">{{ t('batchDeleteGoods') }}</el-button>
                    </div> -->

                    <el-pagination v-model:current-page="goodsTable.page" v-model:page-size="goodsTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="goodsTable.total"
                        @size-change="loadGoodsList()" @current-change="loadGoodsList" />
                </div>

            </div>

        </el-card>

        <!-- 商品库存编辑弹出框 -->
        <goods-stock-edit-popup ref="goodsStockEditPopupRef" @load="loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)" />

        <!-- 商品价格编辑弹出框 -->
        <goods-price-edit-popup ref="goodsPriceEditPopupRef" @load="loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)" />

        <!-- 商品推广弹出框 -->
        <spread-popup ref="spreadPopupRef" />

        <!-- 会员价弹出框 -->
        <goods-member-price-popup ref="memberPricePopupRef" @load="loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)" />

        <!-- 批量设置弹出框 -->
        <goods-batch-settings-popup ref="goodsBatchSettingPopupRef" @load="loadGoodsListReset" />

    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, nextTick, computed } from 'vue'
import { t } from '@/lang'
import { debounce, img, filterDigit, setTablePageStorage, getTablePageStorage } from '@/utils/common'
import storage from '@/utils/storage'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { cloneDeep } from 'lodash-es'
import goodsMemberPricePopup from '@/addon/phone_shop/views/goods/components/goods-member-price-popup.vue'
import goodsStockEditPopup from '@/addon/phone_shop/views/goods/components/goods-stock-edit-popup.vue'
import goodsPriceEditPopup from '@/addon/phone_shop/views/goods/components/goods-price-edit-popup.vue'
import goodsBatchSettingsPopup from '@/addon/phone_shop/views/goods/components/goods-batch-settings-popup.vue'
import sellDialog from '@/addon/phone_shop/views/goods/components/sell-dialog.vue'
import { getGoodsPageList, getCategoryTree, getGoodsType, getBrandList, getLabelList, editGoodsSort, editGoodsStatus, copyGoods, deleteGoods,editGoodssingleStatus, getMemberLevelNoList } from '@/addon/phone_shop/api/goods'
import { syncAgentGoods } from '@/addon/phone_shop/api/agent'
import { getSpecGroups, getGrades } from '@/addon/phone_shop/api/spec'
import { getMemberLevelAll } from '@/app/api/member'
import spreadPopup from '@/components/spread-popup/index.vue'

const router = useRouter()
const route = useRoute()
const pageName = route.meta.title
const repeat = ref(false)
const SELF_SOURCE = '100024'
const AGENT_SOURCE = '100005'
const routeSource = Array.isArray(route.query.source) ? route.query.source[0] : route.query.source
const currentSiteSource = computed(() => String(storage.get('siteId') || routeSource || ''))
const showSourceFilter = computed(() => currentSiteSource.value === SELF_SOURCE)
const defaultSourceFilter = () => showSourceFilter.value ? SELF_SOURCE : ''

// 库龄区间(天)下拉:value = "min-max"(max 空表示无上限),选中后联动 start/end_stock_age
const stockAgeRange = ref('')
const stockAgeOptions = [
    { label: '7天内', value: '0-7' },
    { label: '8-15天', value: '8-15' },
    { label: '16-30天', value: '16-30' },
    { label: '31-60天', value: '31-60' },
    { label: '60天以上', value: '61-' }
]
const onStockAgeChange = (val: string) => {
    const [min, max] = (val || '').split('-')
    goodsTable.searchParam.start_stock_age = min ?? ''
    goodsTable.searchParam.end_stock_age = max ?? ''
}

// 内存 / 成色 下拉选项(取自规格组、成色字典,与建品/收银台同源)
const memOptions = ref<string[]>([])
const gradeOptions = ref<string[]>([])
const loadMemGradeOptions = async () => {
    try {
        const res: any = await getSpecGroups({ category_id: 0 })
        const groups = res.data || []
        const memGroups = groups.filter((g: any) => String(g.label || '').includes('内存'))
        const useGroups = memGroups.length ? memGroups : groups
        const set = new Set<string>()
        useGroups.forEach((g: any) => (g.items || []).forEach((it: any) => { const v = String(it.item_value ?? '').trim(); if (v) set.add(v) }))
        memOptions.value = Array.from(set).sort()
    } catch (e) { /* */ }
    try {
        const res: any = await getGrades()
        gradeOptions.value = (res.data || []).map((x: any) => String(x.grade_name ?? '').trim()).filter(Boolean)
    } catch (e) { /* */ }
}
loadMemGradeOptions()

const goodsTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        goods_name: '',
        goods_category: [],
        goods_type: '',
        brand_id: '',
        label_ids: '',
        start_sale_num: '',
        end_sale_num: '',
        start_price: '',
        end_price: '',
        status: route.query.status || '1',
        source: defaultSourceFilter(),
        memory_group: '',
        condition_grade: '',
        sale_status: '',
        device_keywords: '',
        start_stock_age: '',
        end_stock_age: '',
        order: '',
        sort: ''
    }
})

// 会员价显示:member_price 是 {level_x: 价} 的 JSON,取代表值(多条取最低)
const memberPriceText = (row: any): string => {
    const raw = (row.goodsSku || {}).member_price
    if (!raw) return '—'
    let obj: any = raw
    try { if (typeof raw === 'string') obj = JSON.parse(raw) } catch (e) { return '—' }
    const vals = Object.values(obj || {}).map((v: any) => Number(v)).filter((n: number) => !isNaN(n) && n > 0)
    if (!vals.length) return '—'
    return '￥' + Math.min(...vals).toFixed(2)
}
// 库龄(天):now - 创建时间
const tsOf = (val: any): number => {
    if (!val) return 0
    if (typeof val === 'number') return val > 1e12 ? Math.floor(val / 1000) : val
    const s = String(val).trim()
    if (/^\d+$/.test(s)) { const n = Number(s); return n > 1e12 ? Math.floor(n / 1000) : n }
    const t = Date.parse(s.replace(/-/g, '/'))
    return isNaN(t) ? 0 : Math.floor(t / 1000)
}
const stockAgeDays = (ct: any): number => {
    const ts = tsOf(ct)
    if (!ts) return 0
    return Math.max(0, Math.floor((Date.now() / 1000 - ts) / 86400))
}
const stockAgeType = (ct: any): string => {
    const d = stockAgeDays(ct)
    return d >= 60 ? 'danger' : (d >= 30 ? 'warning' : 'success')
}
// 时间叠两行:date=日期, time=时分秒
const dtPart = (val: any, which: 'date' | 'time'): string => {
    const ts = tsOf(val)
    if (!ts) return which === 'date' ? '—' : ''
    const d = new Date(ts * 1000)
    const p = (n: number) => String(n).padStart(2, '0')
    if (which === 'date') return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
    return `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

const searchFormRef = ref<FormInstance>()

// 正则表达式
const regExp = {
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/
}

// 商品分类
const goodsCategoryOptions: any = reactive([])
const goodsCategoryProps = {
    checkStrictly: true,
    emitPath: false
}
// 商品类型
const goodsType: any = reactive([])

// 品牌列表下拉框
const brandOptions: any = ref([])

// 标签组列表下拉框
const labelOptions: any = reactive([])

// 初始化数据
const initData = () => {
    // 查询商品分类树结构
    getCategoryTree().then((res) => {
        const data = res.data
        if (data) {
            // 递归构建分类树(最多三级;限深度以杜绝异常数据(pid 自引用/成环)导致的死循环)
            const buildCatNode = (item: any, depth = 1): any => {
                const node: any = { value: item.category_id, label: item.category_name }
                const kids = item.child_list || item.children || []
                if (kids.length && depth < 3) {
                    node.children = [{ value: item.category_id, label: '全部' }, ...kids.map((k: any) => buildCatNode(k, depth + 1))]
                }
                return node
            }
            const goodsCategoryTree: any = [
                { value: '', label: '全部', children: [] },
                ...(Array.isArray(data) ? data : []).map((it: any) => buildCatNode(it))
            ]
            goodsCategoryOptions.splice(0, goodsCategoryOptions.length, ...goodsCategoryTree)
        }
    })

    // 商品类型
    getGoodsType().then((res) => {
        const data = res.data
        if (data) {
            for (const k in data) {
                goodsType.push(data[k])
            }
        }
    })

    // 商品标签
    getLabelList({}).then((res) => {
        const data = res.data
        if (data) {
            labelOptions.push(...data)
        }
    })
}

initData()
// 商品品牌
const getBrandListFn = (query = '') => {
    getBrandList({brand_name: query}).then((res) => {
        brandOptions.value = res.data
    })
}

// 当前选中tab页面
const tabHandleClick = (tab: any, event: Event) => {
    goodsTable.searchParam.status = tab.props.name
    isReset.value = true
    loadGoodsList()
}

// 当前站是否主站(主站隐藏 自营/代理 筛选与一键同步，全部正常展示)
const isMasterSite = ref(true)
// 一键同步主站商品(子站)
const syncLoading = ref(false)
const syncMasterGoodsFn = () => {
    ElMessageBox.confirm('将把主站当前在售商品全量同步到本店(代理展示)，是否继续？', '一键同步主站商品', {
        type: 'warning'
    }).then(() => {
        syncLoading.value = true
        syncAgentGoods().then((res: any) => {
            const d = res.data || {}
            const r = d.refs || {}
            ElMessageBox.alert(
                `商品：${d.count ?? 0} 件\n分类：${r.category ?? 0}　品牌：${r.brand ?? 0}　参数：${r.attr ?? 0}\n规格组：${r.spec_group ?? 0}　规格项：${r.spec_item ?? 0}　等级：${r.grade ?? 0}` +
                (r.error ? `\n错误：${r.error}` : ''),
                '同步完成', { type: 'success', customClass: 'whitespace-pre-line' }
            )
            isReset.value = true
            loadGoodsList()
        }).finally(() => { syncLoading.value = false })
    }).catch(() => {})
}

// 全选所有页时排除的 ID
const excludedIds = ref<number[]>([])
// 是否全选所有页
const isSelectAllPages = ref(false)

// 批量复选框
const toggleCheckbox = ref()
const currentSelectMode = ref<'all' | 'page' | null>(null)

// 复选框中间状态
const isIndeterminate = ref(false)

// 全选当前页
const toggleChange = () => {
    restoringSelection.value = true // 加锁
    if (currentSelectMode.value === 'page') {
        isSelectAllPages.value = false
        currentSelectMode.value = null
        excludedIds.value = []
        multipleSelection.value = []
        goodsListTableRef.value.clearSelection()
    } else {
        isSelectAllPages.value = false
        currentSelectMode.value = 'page'
        excludedIds.value = []
        multipleSelection.value = []
        goodsTable.data.forEach(row => {
            goodsListTableRef.value.toggleRowSelection(row, true)
        })
        multipleSelection.value = [...goodsTable.data]
    }
    nextTick(() => {
        restoringSelection.value = false // 解锁
    })
}

// 全选所有页
const selectAllPages = () => {
    restoringSelection.value = true // 加锁
    if (currentSelectMode.value === 'all') {
        isSelectAllPages.value = false
        currentSelectMode.value = null
        excludedIds.value = []
        multipleSelection.value = []
        goodsListTableRef.value.clearSelection()
    } else {
        // goodsListTableRef.value.clearSelection()
        excludedIds.value = []
        multipleSelection.value = []
        isSelectAllPages.value = true
        currentSelectMode.value = 'all'
        goodsTable.data.forEach(row => {
            goodsListTableRef.value.toggleRowSelection(row, true)
        })
    }
    nextTick(() => {
        restoringSelection.value = false // 解锁
    })
}
const defaultSelection = computed(() => {
    if (isSelectAllPages.value) {
        return goodsTable.data.filter(item => !excludedIds.value.includes(item.goods_id))
    } else {
        return multipleSelection.value
    }
})

const goodsListTableRef = ref()

// 选中数据
const multipleSelection: any = ref([])
const restoringSelection = ref(false)

const handleSelectionChange = (val: any[]) => {
    if (restoringSelection.value) return // 阻止自动恢复触发逻辑

    if (isSelectAllPages.value) {
        const currentPageIds = goodsTable.data.map(item => item.goods_id)
        const selectedIds = val.map(item => item.goods_id)
        const unselected = currentPageIds.filter(id => !selectedIds.includes(id))

        excludedIds.value = Array.from(new Set([...excludedIds.value, ...unselected]))
        excludedIds.value = excludedIds.value.filter(id => !selectedIds.includes(id))
    } else {
        multipleSelection.value = val
    }
}
const selectedCount = computed(() => {
    if (isSelectAllPages.value) {
        return goodsTable.total - excludedIds.value.length
    } else {
        return multipleSelection.value.length
    }
})
const selectedSellableRows = computed(() => (multipleSelection.value || []).filter(canSell))
const selectedSellableCount = computed(() => selectedSellableRows.value.length)

const getBatchPayload = () => {
    if (isSelectAllPages.value) {
        return {
            is_all: 1,
            ids: excludedIds.value,
            where: {
                ...goodsTable.searchParam
            }
        }
    } else {
        return {
            is_all: 0,
            ids: multipleSelection.value.map(item => item.goods_id),
            where: {
                ...goodsTable.searchParam
            }
        }
    }
}

// 商品预览
const previewEvent = (data: any) => {
    const url = router.resolve({
        path: '/preview/wap',
        query: {
            page: `/addon/phone_shop/pages/goods/detail?goods_id=${data.goods_id}`
        }
    })
    window.open(url.href)
}

const activeclick = (data: any) => {
    window.open(data.jump_url, '_blank')
}

// 监听排序
const sortChange = (event: any) => {
    let sort = ''
    if (event.order == 'ascending') {
        sort = 'asc'
    } else if (event.order == 'descending') {
        sort = 'desc'
    }
    if (sort) {
        goodsTable.searchParam.order = event.prop
        goodsTable.searchParam.sort = sort
    }
    loadGoodsList()
}

// 修改商品上下架状态
const statusChange = (row: any, value: any) => {
    if (value) {
        editGoodssingleStatus({
            goods_id: row.goods_id,
            status: value
        }).then((res) => {
            loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
        })
    } else {
        ElMessageBox.confirm(t('statusChangeTips'), t('warning'),
            {
                confirmButtonText: t('confirm'),
                cancelButtonText: t('cancel'),
                type: 'warning'
            }
        ).then(() => {
            editGoodssingleStatus({
                goods_id: row.goods_id,
                status: value
            }).then((res) => {
                loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
            })
        })
    }
}

// 批量设置上下架
const batchGoodsStatus = (status: any) => {
    const isNoneSelected =
        (!isSelectAllPages.value && multipleSelection.value.length === 0) ||
        (isSelectAllPages.value && excludedIds.value.length === goodsTable.total)

    if (isNoneSelected) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedGoodsTips')}`
        })
        return
    }
    const info = getBatchPayload()
    editGoodsStatus({
        is_all: info.is_all,
        goods_ids: info.ids,
        where: info.where,
        status
    }).then((res) => {
        isReset.value = true
        loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
    })
}

/** ***************** 批量设置-start *************************/
const goodsBatchSettingPopupRef = ref()
const batchSetGoods = () => {
    const isNoneSelected =
    (!isSelectAllPages.value && multipleSelection.value.length === 0) ||
    (isSelectAllPages.value && excludedIds.value.length === goodsTable.total)

    if (isNoneSelected) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedGoodsTips')}`
        })
        return
    }
    const info = getBatchPayload()
    goodsBatchSettingPopupRef.value.show(info)
}

/** ***************** 批量设置-end *************************/

const batchDeleteGoods = () => {
    const isNoneSelected =
    (!isSelectAllPages.value && multipleSelection.value.length === 0) ||
    (isSelectAllPages.value && excludedIds.value.length === goodsTable.total)

    if (isNoneSelected) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedGoodsTips')}`
        })
        return
    }
    ElMessageBox.confirm(t('batchGoodsDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (repeat.value) return
        repeat.value = true
        const info = getBatchPayload()
        deleteGoods({
            is_all: info.is_all,
            goods_ids: info.ids,
            where: info.where
        }).then(() => {
            isReset.value = true
            loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
            repeat.value = false
        }).catch(() => {
            repeat.value = false
        })
    })
}

// 修改排序号
const sortInputListener = debounce((sort, row) => {
    if (isNaN(sort) || !regExp.number.test(sort)) {
        ElMessage({
            type: 'warning',
            message: `${t('sortTips')}`
        })
        return
    }
    if (sort > 99999999) {
        row.sort = 99999999
    }
    editGoodsSort({
        goods_id: row.goods_id,
        sort
    }).then((res) => {
        loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
    })
})

const isReset = ref(false)
/**
 * 获取商品列表
 */
const loadGoodsList = (page: number = 1) => {
    if (goodsTable.searchParam.start_sale_num && !regExp.digit.test(goodsTable.searchParam.start_sale_num)) {
        ElMessage({
            type: 'warning',
            message: `${t('startSaleNumTips')}`
        })
        return
    }
    if (goodsTable.searchParam.end_sale_num && !regExp.digit.test(goodsTable.searchParam.end_sale_num)) {
        ElMessage({
            type: 'warning',
            message: `${t('endSaleNumTips')}`
        })
        return
    }
    if (Number(goodsTable.searchParam.start_sale_num) > Number(goodsTable.searchParam.end_sale_num)) {
        ElMessage({
            type: 'warning',
            message: `${t('shopSaleNumTips')}`
        })
        return
    }
    if (goodsTable.searchParam.start_price && !regExp.digit.test(goodsTable.searchParam.start_price)) {
        ElMessage({
            type: 'warning',
            message: `${t('startPriceTips')}`
        })
        return
    }
    if (goodsTable.searchParam.end_price && !regExp.digit.test(goodsTable.searchParam.end_price)) {
        ElMessage({
            type: 'warning',
            message: `${t('endPriceTips')}`
        })
        return
    }
    if (Number(goodsTable.searchParam.start_price) > Number(goodsTable.searchParam.end_price)) {
        ElMessage({
            type: 'warning',
            message: `${t('shopPriceTips')}`
        })
        return
    }
    goodsTable.loading = true
    goodsTable.page = page

    const searchData: any = cloneDeep(goodsTable.searchParam)
    if (!showSourceFilter.value || !searchData.source) delete searchData.source

    getGoodsPageList({
        page: goodsTable.page,
        limit: goodsTable.limit,
        ...searchData
    }).then(res => {
        goodsTable.loading = false
        goodsTable.data = res.data.data
        goodsTable.total = res.data.total
        isMasterSite.value = Number(res.data.is_master_site) === 1
        setTablePageStorage(goodsTable.page, goodsTable.limit, searchData)
        if (isReset.value) {
            isSelectAllPages.value = false
            excludedIds.value = []
            currentSelectMode.value = null
            multipleSelection.value = []
        }
        if (isSelectAllPages.value && !isReset.value) {
            restoringSelection.value = true
            nextTick(() => {
                goodsTable.data.forEach(item => {
                    if (!excludedIds.value.includes(item.goods_id)) {
                        goodsListTableRef.value?.toggleRowSelection(item, true)
                    } else {
                        goodsListTableRef.value?.toggleRowSelection(item, false)
                    }
                })

                restoringSelection.value = false
            })
        }
        isReset.value = false
    }).catch(() => {
        isReset.value = false
        goodsTable.loading = false
    })
}

loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)

/**
 * 添加商品
 */
const addEvent = () => {
    router.push('/phone_shop/goods/real_add')
    // let url = router.resolve({
    //     path: '/phone_shop/goods/real_edit',
    // });
    // window.open(url.href);
}

/**
 * 编辑商品
 * @param data
 */

const editEvent = (data: any) => {
    router.push(data.goods_edit_path + '?goods_id=' + data.goods_id)
    // let url = router.resolve({
    //     path: data.goods_edit_path,
    //     query: {goods_id: data.goods_id}
    // });
    // window.open(url.href);
}

const goodsPriceEditPopupRef: any = ref(null)

// 编辑商品价格(同一弹窗内含会员价,一次保存)
const editPriceEvent = (data: any) => {
    goodsPriceEditPopupRef.value.show(data, memberLevel.value)
}

const goodsStockEditPopupRef: any = ref(null)

// 编辑商品库存
const editStockEvent = (data: any) => {
    goodsStockEditPopupRef.value.show(data)
}

// 商品推广
const spreadPopupRef = ref(null)

const spreadEvent = (data: any) => {
    const pagePath = '/addon/phone_shop/pages/goods/detail'
    const paramsArr = [
        { name: 'goods_id', value: data.goods_id },
    ];
    const title = '商品推广'
    const folder = 'goods'
    spreadPopupRef.value?.show(pagePath, paramsArr, title, folder);
}

/** ***************** 会员价-start *************************/
// 会员等级
const memberLevel = ref([])
const getMemberLevelAllFn = async () => {
    const res: any = await getMemberLevelAll()
    const levels = res.data ? res.data : []
    // 给每个等级补"站内序号 level_no"(会员价 JSON 统一按 level_no 存取);hsx_erp 未启用则跳过,回退 level_id
    try {
        const noRes: any = await getMemberLevelNoList()
        const noMap: Record<number, number> = {}
        ;(noRes.data || []).forEach((it: any) => { noMap[Number(it.level_id)] = Number(it.level_no) })
        levels.forEach((lv: any) => { if (noMap[Number(lv.level_id)]) lv.level_no = noMap[Number(lv.level_id)] })
    } catch (e) { /* 无 ERP 时按 level_id */ }
    memberLevel.value = levels
}
getMemberLevelAllFn()

const memberPricePopupRef: any = ref(null)
const memberPriceEvent = (data: any) => {
    memberPricePopupRef.value.show(data, memberLevel.value)
}
/** ***************** 会员价-end *************************/

// 复制商品
const copyEvent = (data: any) => {
    ElMessageBox.confirm(t('goodsCopyTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (repeat.value) return
        repeat.value = true

        copyGoods({
            goods_id: data.goods_id
        }).then((res: any) => {
            if (res.code == 1) {
                loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
            }
            repeat.value = false
            ElMessage({
                type: 'success',
                message: '商品复制成功，可以在仓库中商品查询'
            })
        }).catch(() => {
            repeat.value = false
        })
    })
}

// 删除商品
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('goodsDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (repeat.value) return
        repeat.value = true
        deleteGoods({
            goods_ids: id
        }).then(() => {
            loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
            repeat.value = false
        }).catch(() => {
            repeat.value = false
        })
    })
}

// 批量重置全选状态
const loadGoodsListReset = () => {
    console.log('loadGoodsListReset')
    isReset.value = true
    goodsBatchSettingPopupRef.value.showDialog = false
    loadGoodsList(getTablePageStorage(goodsTable.searchParam).page)
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    goodsTable.searchParam.start_price = ''
    goodsTable.searchParam.end_price = ''
    goodsTable.searchParam.start_sale_num = ''
    goodsTable.searchParam.end_sale_num = ''
    stockAgeRange.value = ''
    goodsTable.searchParam.start_stock_age = ''
    goodsTable.searchParam.end_stock_age = ''
    goodsTable.searchParam.source = defaultSourceFilter()
    isReset.value = true
    loadGoodsList()
}
</script>
<style lang="scss">
    .el-cascader-panel .el-radio{
        position:absolute;
        z-index:10;
        padding:0 10px;
        width:132px;
        height:34px;
        line-height:34px;
    }
    .el-cascader-panel .el-radio__input{
        visibility:hidden;
    }
    .el-cascader-panel .el-input-node__postfix{
        top:10px;
    }
</style>
<style lang="scss" scoped>
    /* 单行省略 */
    .ellipsis-1 { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    /* 价格三行:零售/会员/成本 */
    .price-cell { display: flex; flex-direction: column; gap: 2px; font-size: 12px; line-height: 1.5; }
    .price-tag { display: inline-block; width: 30px; text-align: center; font-size: 10px; border-radius: 3px; margin-right: 4px; color: #fff; }
    .price-tag.retail { background: #f56c6c; }
    .price-tag.member { background: #e6a23c; }
    .price-tag.cost { background: #c0c4cc; }
    .price-val { font-weight: 600; color: #303133; }
    .price-val.member-val { color: #e6a23c; }
    /* 时间叠两行 */
    .time-cell { line-height: 1.4; font-size: 12px; }
    .time-cell .time-hms { color: #94a3b8; font-size: 11px; }
    .price-wrap, .stock-wrap {
        &:hover {
            .icon-wrap {
                visibility: visible;
                color: var(--el-color-primary);
            }
        }
    }
    .select-wrap .active {
        font-weight: bold;
        background-color: #f5f7fa;
    }

</style>
