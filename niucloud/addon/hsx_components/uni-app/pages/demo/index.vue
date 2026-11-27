<script setup lang="ts">
import { computed, ref } from 'vue'
import HsxAdaptivePage from '../../components/HsxAdaptivePage/index.vue'
import HsxActionBar from '../../components/HsxActionBar/index.vue'
import HsxBlockRenderer from '../../components/HsxBlockRenderer/index.vue'
import HsxButton from '../../components/HsxButton/index.vue'
import HsxCard from '../../components/HsxCard/index.vue'
import HsxChartCard from '../../components/HsxChartCard/index.vue'
import HsxCheckbox from '../../components/HsxCheckbox/index.vue'
import HsxComponentCatalog from '../../components/HsxComponentCatalog/index.vue'
import HsxFilterDrawer from '../../components/HsxFilterDrawer/index.vue'
import HsxFilterToolbar from '../../components/HsxFilterToolbar/index.vue'
import HsxDetail from '../../components/HsxDetail/index.vue'
import HsxEntityPicker from '../../components/HsxEntityPicker/index.vue'
import HsxIcon from '../../components/HsxIcon/index.vue'
import HsxMotion from '../../components/HsxMotion/index.vue'
import HsxPageHeader from '../../components/HsxPageHeader/index.vue'
import HsxPopup from '../../components/HsxPopup/index.vue'
import HsxProgress from '../../components/HsxProgress/index.vue'
import HsxProductList from '../../components/HsxProductList/index.vue'
import HsxResponsiveGrid from '../../components/HsxResponsiveGrid/index.vue'
import HsxSchemaForm from '../../components/HsxSchemaForm/index.vue'
import HsxSearchBar from '../../components/HsxSearchBar/index.vue'
import HsxSplitPane from '../../components/HsxSplitPane/index.vue'
import HsxSwitch from '../../components/HsxSwitch/index.vue'
import HsxTag from '../../components/HsxTag/index.vue'
import HsxText from '../../components/HsxText/index.vue'
import HsxThemeProvider from '../../components/HsxThemeProvider/index.vue'
import HsxTimeline from '../../components/HsxTimeline/index.vue'
import HsxTitle from '../../components/HsxTitle/index.vue'
import mobileHeroBackground from '../../static/images/hsx-mobile-tech-bg.png'
import {
    createMobileColumnChart,
    createMobileLineChart,
    createMobileRingChart,
    countActiveMobileFilters,
    defineMobileFormSchema,
    useModal,
    type AnyRecord,
    type MobileActionItem,
    type MobileBlockSchema,
    type MobileCascaderOption,
    type MobileComponentCatalogItem,
    type MobileDetailItem,
    type MobileFormField,
    type MobileFilterToolbarItem,
    type MobileSwipeActionOption,
    type MobileTimelineItem
} from '../../index'

const popupVisible = ref(false)
const darkMode = ref(false)
const formData = ref<AnyRecord>({ status: 1, photos: [] })
const modal = useModal()
const standaloneChannels = ref<Array<string | number | boolean>>(['public'])
const standaloneSwitch = ref(true)
const adaptivePane = ref<'primary' | 'secondary'>('primary')
const searchKeyword = ref('')
const filterVisible = ref(false)
const listMode = ref<'list' | 'grid'>('list')
const filterParams = ref<AnyRecord>({ status: 'selling', grades: ['A'] })
const activeFilterCount = computed(() => countActiveMobileFilters(filterParams.value))
const entityPickerVisible = ref(false)
const selectedManagers = ref<AnyRecord[]>([])
const catalogActiveKey = ref('')

const componentCatalog: MobileComponentCatalogItem[] = [
    { key: 'HsxButton', label: 'HsxButton', group: '基础视觉', description: '防连点、Loading、震动与按压反馈', icon: 'checkmark-circle', target: 'catalog-feedback' },
    { key: 'HsxCard', label: 'HsxCard', group: '基础视觉', description: '统一卡片、背景和可点击状态', icon: 'grid', target: 'catalog-feedback' },
    { key: 'HsxIcon', label: 'HsxIcon', group: '基础视觉', description: 'uView、字体与图片图标统一入口', icon: 'image', target: 'catalog-search' },
    { key: 'HsxTag', label: 'HsxTag', group: '基础视觉', description: '状态语义和圆点标签', icon: 'info', target: 'catalog-controls' },
    { key: 'HsxText', label: 'HsxText', group: '基础视觉', description: '字号、行数和溢出控制', icon: 'list', target: 'catalog-feedback' },
    { key: 'HsxTitle', label: 'HsxTitle', group: '基础视觉', description: '页面、区块和卡片标题', icon: 'list', target: 'catalog-search' },
    { key: 'HsxMotion', label: 'HsxMotion', group: '基础视觉', description: '克制的进入和状态动画', icon: 'refresh', target: 'catalog-feedback' },
    { key: 'HsxProgress', label: 'HsxProgress', group: '基础视觉', description: '进度与风险语义', icon: 'download', target: 'catalog-feedback' },
    { key: 'HsxAdaptivePage', label: 'HsxAdaptivePage', group: '响应式布局', description: '手机、折叠屏和 iPad 页面容器', icon: 'grid', target: 'catalog-adaptive' },
    { key: 'HsxGrid', label: 'HsxGrid', group: '响应式布局', description: '通用网格布局', icon: 'grid', target: 'catalog-adaptive' },
    { key: 'HsxResponsiveGrid', label: 'HsxResponsiveGrid', group: '响应式布局', description: '按窗口宽度自适应分栏', icon: 'grid', target: 'catalog-adaptive' },
    { key: 'HsxSplitPane', label: 'HsxSplitPane', group: '响应式布局', description: '列表与详情单双栏切换', icon: 'list', target: 'catalog-adaptive' },
    { key: 'HsxStack', label: 'HsxStack', group: '响应式布局', description: '横向与纵向间距布局', icon: 'list', target: 'catalog-adaptive' },
    { key: 'HsxList', label: 'HsxList', group: '响应式布局', description: '统一列表骨架', icon: 'list', target: 'catalog-product' },
    { key: 'HsxOverflow', label: 'HsxOverflow', group: '响应式布局', description: '文本与内容溢出处理', icon: 'more', target: 'catalog-product' },
    { key: 'HsxCheckbox', label: 'HsxCheckbox', group: '表单与选择', description: '响应式多选和数量约束', icon: 'success', target: 'catalog-controls' },
    { key: 'HsxSwitch', label: 'HsxSwitch', group: '表单与选择', description: '统一开关尺寸和语义', icon: 'refresh', target: 'catalog-controls' },
    { key: 'HsxSelect', label: 'HsxSelect', group: '表单与选择', description: '移动端选择器', icon: 'list', target: 'catalog-controls' },
    { key: 'HsxCascader', label: 'HsxCascader', group: '表单与选择', description: '多级、异步和路径回显', icon: 'next', target: 'catalog-controls' },
    { key: 'HsxForm', label: 'HsxForm', group: '表单与选择', description: '统一表单容器', icon: 'edit', target: 'catalog-controls' },
    { key: 'HsxSchemaForm', label: 'HsxSchemaForm', group: '表单与选择', description: 'JSON Schema 动态表单', icon: 'edit', target: 'catalog-controls' },
    { key: 'HsxUpload', label: 'HsxUpload', group: '表单与选择', description: '上传、预览、进度和状态', icon: 'upload', target: 'catalog-controls' },
    { key: 'HsxSearchBar', label: 'HsxSearchBar', group: '检索与筛选', description: '关键词、扫码、筛选和搜索按钮', icon: 'search', target: 'catalog-search' },
    { key: 'HsxPageHeader', label: 'HsxPageHeader', group: '检索与筛选', description: '安全区、自定义头部和搜索', icon: 'back', target: 'catalog-search' },
    { key: 'HsxFilterToolbar', label: 'HsxFilterToolbar', group: '检索与筛选', description: '快捷筛选与数量角标', icon: 'filter', target: 'catalog-search' },
    { key: 'HsxFilterDrawer', label: 'HsxFilterDrawer', group: '检索与筛选', description: '手机底部与宽屏右侧高级筛选', icon: 'filter', target: 'catalog-search' },
    { key: 'HsxPageList', label: 'HsxPageList', group: '数据与业务', description: 'z-paging 分页、刷新和空态', icon: 'list', target: 'catalog-product' },
    { key: 'HsxMediaCard', label: 'HsxMediaCard', group: '数据与业务', description: '图文、状态和价格卡片', icon: 'image', target: 'catalog-product' },
    { key: 'HsxProductList', label: 'HsxProductList', group: '数据与业务', description: '商品分页卡片与左滑操作', icon: 'grid', target: 'catalog-product' },
    { key: 'HsxDetail', label: 'HsxDetail', group: '数据与业务', description: 'Schema 阅读态详情', icon: 'info', target: 'catalog-adaptive' },
    { key: 'HsxTimeline', label: 'HsxTimeline', group: '数据与业务', description: '业务操作流水', icon: 'list', target: 'catalog-controls' },
    { key: 'HsxEntityPicker', label: 'HsxEntityPicker', group: '数据与业务', description: '管理员、用户、门店和供应商选择', icon: 'account', target: 'catalog-business' },
    { key: 'HsxPopup', label: 'HsxPopup', group: '反馈与操作', description: '底部、侧边、居中和全屏弹层', icon: 'more', target: 'catalog-controls' },
    { key: 'HsxActionBar', label: 'HsxActionBar', group: '反馈与操作', description: 'Action Schema 与安全区操作栏', icon: 'success', target: 'catalog-adaptive' },
    { key: 'HsxSwipeActions', label: 'HsxSwipeActions', group: '反馈与操作', description: '左滑快捷操作', icon: 'next', target: 'catalog-product' },
    { key: 'HsxEmpty', label: 'HsxEmpty', group: '反馈与操作', description: '空数据和错误重试', icon: 'info', target: 'catalog-product' },
    { key: 'HsxChart', label: 'HsxChart', group: '图表与低代码', description: '多端图表统一渲染', icon: 'grid', target: 'catalog-charts' },
    { key: 'HsxChartCard', label: 'HsxChartCard', group: '图表与低代码', description: '标题、趋势和图表卡片', icon: 'grid', target: 'catalog-charts' },
    { key: 'HsxChartFallback', label: 'HsxChartFallback', group: '图表与低代码', description: '小程序 Canvas 不可用时降级', icon: 'warning', target: 'catalog-charts' },
    { key: 'HsxBlockRenderer', label: 'HsxBlockRenderer', group: '图表与低代码', description: '白名单 Schema 区块渲染', icon: 'grid', target: 'catalog-lowcode' },
    { key: 'HsxDiyRenderer', label: 'HsxDiyRenderer', group: '图表与低代码', description: '兼容框架低代码协议', icon: 'grid', target: 'catalog-lowcode' }
]

function jumpToComponent(item: MobileComponentCatalogItem) {
    catalogActiveKey.value = item.key
    if (!item.target) return
    setTimeout(() => {
        const query = uni.createSelectorQuery()
        query.select(`#${item.target}`).boundingClientRect()
        query.selectViewport().scrollOffset()
        query.exec((result: any[]) => {
            const rect = result?.[0]
            const viewport = result?.[1]
            if (!rect || !viewport) return
            uni.pageScrollTo({ scrollTop: Math.max(0, Number(rect.top) + Number(viewport.scrollTop) - 12), duration: 280 })
        })
    }, 140)
}

const filterToolbarItems = computed<MobileFilterToolbarItem[]>(() => [
    { label: '分类', value: 'category', icon: 'uview grid', active: Boolean(filterParams.value.category_ids?.length), count: filterParams.value.category_ids?.length || 0 },
    { label: '内存', value: 'memory', active: Boolean(filterParams.value.memories?.length), count: filterParams.value.memories?.length || 0 },
    { label: '成色', value: 'grade', active: Boolean(filterParams.value.grades?.length), count: filterParams.value.grades?.length || 0 },
    { label: '状态', value: 'status', active: Boolean(filterParams.value.status), count: filterParams.value.status ? 1 : 0 },
    { label: '更多', value: 'more', active: activeFilterCount.value > 0, count: activeFilterCount.value }
])

const filterSchema: MobileFormField[] = defineMobileFormSchema([
    {
        prop: 'status',
        label: '设备状态',
        component: 'select',
        placeholder: '全部状态',
        options: [
            { label: '在售', value: 'selling' },
            { label: '待审核', value: 'review' },
            { label: '售后中', value: 'after_sale' }
        ]
    },
    {
        prop: 'grades',
        label: '成色',
        component: 'checkbox',
        defaultValue: [],
        options: [
            { label: 'A 级', value: 'A' },
            { label: 'B+ 级', value: 'B+' },
            { label: 'B 级', value: 'B' }
        ],
        props: { columns: { compact: 3, medium: 3 } }
    },
    { prop: 'seller', label: '卖家', component: 'input', placeholder: '店名、手机号或商家编号', props: { clearable: true } },
    { prop: 'imei', label: 'IMEI / SN', component: 'input', placeholder: '输入或扫码识别设备', props: { clearable: true, maxlength: 80 } },
    { prop: 'created_at', label: '提交日期', component: 'date', placeholder: '选择日期' }
])

function openFilter() {
    filterVisible.value = true
}

function handleSearch(value: string) {
    uni.showToast({ title: value ? `检索：${value}` : '查看全部设备', icon: 'none' })
}

function handleFilterConfirm(value: AnyRecord) {
    uni.showToast({ title: `已应用 ${countActiveMobileFilters(value)} 个条件`, icon: 'none' })
}

const adaptiveDevices = [
    { id: 1, model: 'iPhone 16 Pro Max 256GB', grade: 'A 级', price: 6280, seller: '城西数码', accuracy: '98.6%', status: '竞价中' },
    { id: 2, model: 'iPhone 15 Pro 256GB', grade: 'A- 级', price: 4690, seller: '远航通讯', accuracy: '97.2%', status: '待上架' },
    { id: 3, model: 'Mate 70 Pro 512GB', grade: 'B+ 级', price: 5180, seller: '华南优品', accuracy: '99.1%', status: '竞价中' }
]
const selectedAdaptiveDevice = ref(adaptiveDevices[0])

const mobileDetailSchema: MobileDetailItem[] = [
    { prop: 'price', label: '卖家到手价', type: 'money' },
    { prop: 'accuracy', label: '描述准确率' },
    { prop: 'seller', label: '所属卖家' },
    { prop: 'grade', label: '设备成色' },
    { prop: 'status', label: '销售状态', type: 'tag', tone: 'success' }
]

const mobileDetailActions: MobileActionItem[] = [
    { key: 'quote', label: '立即出价', icon: 'rmb-circle', tone: 'primary', action: () => uni.showToast({ title: '进入出价', icon: 'none' }) },
    { key: 'share', label: '分享货盘', icon: 'share', tone: 'default', plain: true, action: () => uni.showToast({ title: '生成分享入口', icon: 'none' }) }
]

const managerRows = [
    { id: 1, name: '张三', account: 'zhangsan', department: '杭州运营中心', status: '在线' },
    { id: 2, name: '李明', account: 'liming', department: '宁波运营中心', status: '在线' },
    { id: 3, name: '王芳', account: 'wangfang', department: '广州运营中心', status: '忙碌' },
    { id: 4, name: '赵磊', account: 'zhaolei', department: '深圳运营中心', status: '在线' }
]

function selectAdaptiveDevice(device: typeof adaptiveDevices[number]) {
    selectedAdaptiveDevice.value = device
    adaptivePane.value = 'secondary'
}

const chartMode = computed(() => darkMode.value ? 'dark' : 'light')
const dealTrendChart = computed(() => createMobileLineChart(
    ['一', '二', '三', '四', '五', '六', '日'],
    [{ name: '成交设备', data: [18, 24, 20, 31, 28, 42, 46] }],
    { color: ['#3c9cff'], extra: { line: { type: 'curve', width: 3 } } },
    chartMode.value
))
const quoteTrendChart = computed(() => createMobileColumnChart(
    ['16PM', '15PM', 'Mate70', 'Pura70'],
    [{ name: '有效报价', data: [32, 27, 19, 16] }],
    { color: ['#10b981'], xAxis: { rotateLabel: true } },
    chartMode.value
))
const sourceRingChart = computed(() => createMobileRingChart([
    { name: '主理人圈子', value: 48 },
    { name: '公共货盘', value: 34 },
    { name: '代理分销', value: 18 }
], {}, chartMode.value))

const timelineItems: MobileTimelineItem[] = [
    { id: 1, actor: '平台运营', time: '15:42', tag: { label: '审核通过', tone: 'success' }, details: [{ label: '建议售价', value: '¥6,280' }] },
    { id: 2, actor: '主理人·张三', time: '15:18', tag: { label: '完成定价', tone: 'primary' }, details: [{ label: '销售渠道', value: '全国公共货盘' }] },
    { id: 3, actor: '卖家·城西数码', time: '14:51', tag: { label: '提交设备', tone: 'warning' }, content: '照片、质检报告和 IMEI 已上传。' }
]

const lowCodeBlocks: MobileBlockSchema[] = [
    { type: 'title', props: { title: '可信交易能力', subtitle: '由白名单 Schema 安全组合', size: 'card' } },
    { type: 'text', text: '描述准确率、退货率与售后时长共同形成卖家信用画像。', props: { tone: 'secondary', size: 'caption', lines: 2 } },
    { type: 'progress', props: { percentage: 86, label: '资料完整度', description: '图片与质检报告', autoTone: true } }
]

const mobileModelPaths: MobileCascaderOption[][] = [
    [
        { label: 'Apple', value: 'apple' },
        { label: 'iPhone 16 系列', value: 'iphone-16' },
        { label: 'iPhone 16 Pro Max 256GB', value: 'iphone-16-pm-256', leaf: true }
    ],
    [
        { label: 'Apple', value: 'apple' },
        { label: 'iPhone 15 系列', value: 'iphone-15' },
        { label: 'iPhone 15 Pro 256GB', value: 'iphone-15-pro-256', leaf: true }
    ],
    [
        { label: '华为', value: 'huawei' },
        { label: 'Mate 70 系列', value: 'mate-70' },
        { label: 'Mate 70 Pro 512GB', value: 'mate-70-pro-512', leaf: true }
    ]
]

async function fetchMobileModelOptions(parent: MobileCascaderOption | null) {
    await new Promise((resolve) => setTimeout(resolve, 260))
    if (!parent) {
        return [
            { label: 'Apple', value: 'apple' },
            { label: '华为', value: 'huawei' }
        ]
    }
    const nextLevel = mobileModelPaths
        .filter((path) => path.some((item) => item.value === parent.value))
        .map((path) => path[path.findIndex((item) => item.value === parent.value) + 1])
        .filter(Boolean)
    return Array.from(new Map(nextLevel.map((item) => [item.value, item])).values())
}

async function resolveMobileModelPath(values: Array<string | number>) {
    const target = String(values[values.length - 1] || '')
    return mobileModelPaths.find((path) => String(path[path.length - 1].value) === target) || []
}

const schema: MobileFormField[] = defineMobileFormSchema([
    {
        prop: 'model_path',
        label: '机型',
        component: 'cascader',
        placeholder: '逐级选择机型',
        props: {
            fetchOptions: fetchMobileModelOptions,
            resolvePath: resolveMobileModelPath,
            maxLevel: 3
        },
        rules: { required: true, message: '请选择机型' }
    },
    {
        prop: 'grade',
        label: '成色',
        component: 'select',
        options: [
            { label: 'A 级', value: 'A' },
            { label: 'B+ 级', value: 'B+' },
            { label: 'B 级', value: 'B' }
        ]
    },
    { prop: 'price', label: '到手价', component: 'number' },
    {
        prop: 'channels',
        label: '销售渠道',
        component: 'checkbox',
        defaultValue: ['public'],
        options: [
            { label: '全国货盘', value: 'public' },
            { label: '主理人私域', value: 'manager' },
            { label: '代理 C 端', value: 'agent' }
        ],
        props: { min: 1 }
    },
    {
        prop: 'photos',
        label: '设备图片',
        component: 'upload',
        props: {
            uploader: demoUpload,
            maxCount: 3,
            uploadText: '上传'
        },
        tip: '演示直接使用本地临时文件；正式业务传入真实上传接口'
    },
    { prop: 'status', label: '可上架', component: 'switch', defaultValue: 1, props: { activeValue: 1, inactiveValue: 0 } }
])

async function demoUpload(file: any, context: { onProgress: (percentage: number) => void }) {
    context.onProgress(40)
    await new Promise((resolve) => setTimeout(resolve, 260))
    context.onProgress(100)
    return { url: file.url || file.path || file.tempFilePath, name: file.name }
}

const mockRows = Array.from({ length: 12 }, (_, index) => ({
    id: index + 1,
    model: index % 2 ? 'iPhone 15 Pro 256GB' : 'iPhone 16 Pro Max 256GB',
    grade: index % 3 ? 'A' : 'B+',
    price: 4600 + index * 80,
    status: index % 3 === 0 ? '待审核' : '在售',
    description: '质检报告已上传，左滑可快速执行编辑或删除。'
}))

const productSwipeActions: MobileSwipeActionOption[] = [
    { text: '编辑', name: 'edit', icon: 'edit-pen', tone: 'primary' },
    { text: '下架', name: 'offline', icon: 'trash-fill', tone: 'danger' }
]

async function requestRows(params: AnyRecord) {
    await new Promise((resolve) => setTimeout(resolve, 180))
    const start = (params.page - 1) * params.limit
    return {
        data: {
            list: mockRows.slice(start, start + params.limit),
            total: mockRows.length
        }
    }
}

function submit() {
    popupVisible.value = false
    uni.showToast({ title: '演示保存成功', icon: 'success' })
}

function handleSwipeAction(action: MobileSwipeActionOption, item: AnyRecord) {
    uni.showToast({ title: `${action.text}：${item.model}`, icon: 'none' })
}

async function confirmRiskAction() {
    const confirmed = await modal.danger('确认执行高风险操作吗？该演示会触发一次重反馈。')
    if (confirmed) uni.showToast({ title: '已确认', icon: 'success' })
}
</script>

<template>
    <HsxThemeProvider :mode="darkMode ? 'dark' : 'light'">
    <HsxAdaptivePage :max-width="1280" :gutter="{ compact: 12, medium: 20, expanded: 24 }">
    <template #default="{ layout }">
    <view class="demo-page">
        <view class="demo-page__header">
            <image
                class="demo-page__header-bg"
                :src="mobileHeroBackground"
                mode="aspectFill"
            />
            <view class="demo-page__intro">
                <view class="demo-page__title-row">
                    <text class="demo-page__title">HSX 移动端组件</text>
                    <text class="demo-page__version">v0.1.0</text>
                </view>
                <text class="demo-page__desc">z-paging、上传、表单、弹层、图标和暗黑主题</text>
            </view>
            <view class="demo-page__actions">
                <view class="demo-page__theme" role="button" aria-label="切换暗黑模式" @click="darkMode = !darkMode">
                    <HsxIcon :name="darkMode ? 'uview setting-fill' : 'uview photo'" :size="18" color="#fff" />
                </view>
                <HsxButton type="primary" size="small" @click="popupVisible = true">新增设备</HsxButton>
            </view>
        </view>

        <HsxComponentCatalog
            :items="componentCatalog"
            :active-key="catalogActiveKey"
            title="HSX 组件目录"
            trigger-text="组件"
            @select="jumpToComponent"
        />

        <HsxCard id="catalog-search" class="demo-page__search-demo" padding="0">
            <HsxPageHeader
                v-model="searchKeyword"
                title="全国货盘"
                subtitle="自然曝光与主理人私域"
                searchable
                :show-back="false"
                search-placeholder="搜索型号、商品、订单号或 IMEI"
                :show-filter="true"
                :filter-count="activeFilterCount"
                @search="handleSearch"
                @filter="openFilter"
            >
                <template #right>
                    <HsxIcon
                        :name="listMode === 'list' ? 'grid' : 'list'"
                        clickable
                        :label="listMode === 'list' ? '切换网格' : '切换列表'"
                        @click="listMode = listMode === 'list' ? 'grid' : 'list'"
                    />
                </template>
                <template #bottom>
                    <HsxFilterToolbar :items="filterToolbarItems" dense @select="openFilter" />
                </template>
            </HsxPageHeader>

            <view class="demo-page__merchant-search">
                <HsxTitle title="管理端抽屉检索" subtitle="关键词常驻，高级条件进入自适应抽屉" size="card" />
                <HsxSearchBar
                    v-model="searchKeyword"
                    placeholder="搜索订单、客户、手机号、IMEI"
                    show-filter
                    show-action
                    :filter-count="activeFilterCount"
                    @search="handleSearch"
                    @filter="openFilter"
                >
                    <template #trailing><HsxIcon name="scan" clickable label="扫码" /></template>
                </HsxSearchBar>
            </view>
        </HsxCard>

        <HsxCard id="catalog-adaptive" class="demo-page__adaptive" padding="20px">
            <view class="demo-page__adaptive-header">
                <HsxTitle title="折叠屏自适应" subtitle="同一份页面，按当前窗口自动切换单页与双栏" size="card" />
                <HsxTag :text="`${layout.widthClass} · ${layout.windowWidth}px`" tone="primary" />
            </view>

            <HsxResponsiveGrid
                :columns="{ compact: 1, medium: 3, expanded: 3 }"
                :gap="{ compact: 8, medium: 12 }"
                class="demo-page__adaptive-metrics"
            >
                <view class="demo-page__metric"><text class="demo-page__metric-label">窗口模式</text><text class="demo-page__metric-value">{{ layout.isLandscape ? '横向' : '纵向' }}</text></view>
                <view class="demo-page__metric"><text class="demo-page__metric-label">当前宽度</text><text class="demo-page__metric-value">{{ layout.windowWidth }} px</text></view>
                <view class="demo-page__metric"><text class="demo-page__metric-label">页面策略</text><text class="demo-page__metric-value">{{ layout.windowWidth >= 840 ? '列表 + 详情' : '单页切换' }}</text></view>
            </HsxResponsiveGrid>

            <HsxSplitPane v-model="adaptivePane" :has-secondary="!!selectedAdaptiveDevice" :split-at="840">
                <template #primary>
                    <view class="demo-page__device-list">
                        <view
                            v-for="device in adaptiveDevices"
                            :key="device.id"
                            class="demo-page__device-row"
                            :class="{ 'demo-page__device-row--active': selectedAdaptiveDevice.id === device.id }"
                            @click="selectAdaptiveDevice(device)"
                        >
                            <view class="demo-page__device-main">
                                <text class="demo-page__device-name">{{ device.model }}</text>
                                <text class="demo-page__device-meta">{{ device.grade }} · {{ device.seller }}</text>
                            </view>
                            <view class="demo-page__device-side">
                                <text class="demo-page__device-price">¥{{ device.price }}</text>
                                <text class="demo-page__device-arrow">›</text>
                            </view>
                        </view>
                    </view>
                </template>

                <template #secondary="{ isSplit, showPrimary }">
                    <view class="demo-page__device-detail">
                        <view v-if="!isSplit" class="demo-page__back"><HsxButton size="small" @click="showPrimary">返回货盘</HsxButton></view>
                        <view class="demo-page__detail-heading">
                            <view>
                                <text class="demo-page__detail-title">{{ selectedAdaptiveDevice.model }}</text>
                                <text class="demo-page__detail-subtitle">机器详情与出价操作区</text>
                            </view>
                            <HsxTag :text="selectedAdaptiveDevice.status" tone="success" dot />
                        </view>
                        <HsxDetail :data="selectedAdaptiveDevice" :schema="mobileDetailSchema" :columns="{ compact: 2, expanded: 3 }" />
                        <view class="demo-page__detail-action"><HsxActionBar :actions="mobileDetailActions" :context="selectedAdaptiveDevice" :safe-area="false" :bordered="false" :gap="8" /></view>
                    </view>
                </template>
            </HsxSplitPane>
        </HsxCard>

        <HsxMotion id="catalog-feedback" preset="fade-up" :duration="260">
            <HsxResponsiveGrid :columns="{ compact: 1, medium: 2 }" :gap="12" class="demo-page__foundation-grid">
                <HsxCard><HsxTitle title="卖家信用" subtitle="三维数据" size="card" /><HsxProgress :percentage="92" tone="success" :show-value="true" /></HsxCard>
                <HsxCard clickable @click="confirmRiskAction"><HsxText text="交互反馈" tone="primary" weight="600" /><HsxText text="点击体验确认弹窗与震动" tone="secondary" size="caption" :lines="2" /></HsxCard>
            </HsxResponsiveGrid>
        </HsxMotion>

        <HsxCard id="catalog-lowcode" class="demo-page__schema-card"><HsxBlockRenderer :schema="lowCodeBlocks" /></HsxCard>

        <HsxResponsiveGrid id="catalog-charts" :columns="{ compact: 1, medium: 2, expanded: 2 }" :gap="16" class="demo-page__charts">
            <HsxChartCard
                :class="{ 'demo-page__chart--wide': layout.windowWidth >= 840 }"
                title="近七日成交趋势"
                subtitle="业务只提供数据，尺寸、主题和交互由组件处理"
                trend="+18.6%"
                trend-tone="success"
                :type="dealTrendChart.type"
                :chart-data="dealTrendChart.chartData"
                :opts="dealTrendChart.opts"
                :mode="chartMode"
            />
            <HsxChartCard
                title="热门机型报价"
                subtitle="柱状图配置预设"
                :type="quoteTrendChart.type"
                :chart-data="quoteTrendChart.chartData"
                :opts="quoteTrendChart.opts"
                :mode="chartMode"
            />
            <HsxChartCard
                title="货源渠道分布"
                subtitle="环形图自动适配明暗主题"
                :type="sourceRingChart.type"
                :chart-data="sourceRingChart.chartData"
                :opts="sourceRingChart.opts"
                :mode="chartMode"
            />
        </HsxResponsiveGrid>

        <HsxResponsiveGrid id="catalog-controls" :columns="{ compact: 1, expanded: 2 }" :gap="16" class="demo-page__utility-grid">
            <HsxCard>
                <HsxTitle title="表单控件与状态语义" subtitle="独立调用与 Schema 使用同一协议" size="card" />
                <view class="demo-page__control"><HsxCheckbox v-model="standaloneChannels" :options="[{ label: '全国货盘', value: 'public' }, { label: '主理人私域', value: 'manager' }]" :columns="{ compact: 2, medium: 2, expanded: 2 }" :min="1" /></view>
                <view class="demo-page__switch"><HsxSwitch v-model="standaloneSwitch" /><HsxText :text="standaloneSwitch ? '允许自动上架' : '需要人工确认'" tone="secondary" size="caption" /></view>
                <view class="demo-page__tags"><HsxTag text="待审核" tone="warning" dot /><HsxTag text="已上架" tone="success" dot /><HsxTag text="售后" tone="danger" dot /></view>
            </HsxCard>

            <HsxCard><HsxTitle title="设备操作流水" subtitle="内容由业务提供，骨架由组件统一" size="card" /><HsxTimeline :items="timelineItems" compact /></HsxCard>
        </HsxResponsiveGrid>

        <HsxCard id="catalog-business" class="demo-page__business-card">
            <HsxTitle title="管理员 / 用户选择" subtitle="同一组件可适配主理人、门店、供应商和普通用户" size="card" />
            <view class="demo-page__selected-users">
                <HsxTag v-for="item in selectedManagers" :key="item.id" :text="item.name" tone="primary" />
                <HsxText v-if="!selectedManagers.length" text="尚未选择负责人" tone="secondary" size="caption" />
            </view>
            <view class="demo-page__business-action"><HsxButton type="primary" block @click="entityPickerVisible = true">选择业务负责人</HsxButton></view>
        </HsxCard>

        <HsxProductList
            id="catalog-product"
            :request="requestRows"
            :page-size="6"
            :columns="{ compact: 1, medium: 2, expanded: 3 }"
            :gap="16"
            :field-map="{ title: 'model', subtitle: 'grade', price: 'price', status: 'status', description: 'description' }"
            :swipe-actions="productSwipeActions"
            height="calc(100vh - 325px)"
            @action="handleSwipeAction"
        />

        <HsxPopup v-model="popupVisible" title="发布设备" height="78vh" adaptive="dialog" :z-index="10100">
            <HsxSchemaForm v-model="formData" :schema="schema" />
            <template #footer>
                <view class="demo-page__save"><HsxButton type="primary" block @click="submit">保存设备</HsxButton></view>
            </template>
        </HsxPopup>

        <HsxFilterDrawer
            v-model:visible="filterVisible"
            v-model="filterParams"
            :schema="filterSchema"
            title="货盘高级筛选"
            description="手机底部弹出，iPad 与折叠屏自动切换右侧抽屉"
            @confirm="handleFilterConfirm"
        />

        <HsxEntityPicker
            v-model="selectedManagers"
            v-model:visible="entityPickerVisible"
            :items="managerRows"
            title="选择业务负责人"
            description="手机底部弹出，宽屏自动切换右侧面板"
            multiple
        />
    </view>
    </template>
    </HsxAdaptivePage>
    </HsxThemeProvider>
</template>

<style scoped lang="scss">
.demo-page {
    box-sizing: border-box;
}

.demo-page__header {
    position: relative;
    display: flex;
    min-height: 96px;
    box-sizing: border-box;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: var(--hsx-mobile-section-gap, 16px);
    padding: 18px 20px;
    overflow: hidden;
    border: 1px solid rgba(118, 174, 255, .28);
    border-radius: 16px;
    background-color: #081a3b;
    background-image: linear-gradient(90deg, rgba(5, 15, 40, .96), rgba(8, 30, 73, .72));
    box-shadow: 0 10px 25px rgba(7, 29, 71, .24);
}

.demo-page__header-bg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: .32;
}

.demo-page__intro,
.demo-page__actions {
    position: relative;
    z-index: 1;
}

.demo-page__title,
.demo-page__desc {
    display: block;
}

.demo-page__title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.demo-page__version {
    padding: 2px 7px;
    border-radius: 999px;
    color: #dbeafe;
    background: rgba(91, 140, 255, .26);
    font-size: 11px;
    font-weight: 600;
}

.demo-page__title {
    color: #fff;
    font-size: var(--hsx-mobile-font-title, 18px);
    font-weight: 600;
}

.demo-page__desc {
    margin-top: 5px;
    color: rgba(224, 236, 255, .76);
    font-size: var(--hsx-mobile-font-caption, 12px);
}

.demo-page__card { height: 100%; }
.demo-page__adaptive { margin-bottom: 18px; }
.demo-page__search-demo { margin-bottom: var(--hsx-mobile-section-gap, 16px); overflow: hidden; }
.demo-page__merchant-search { display: flex; gap: 14px; padding: 16px; flex-direction: column; border-top: 1px solid var(--hsx-mobile-border, #e6ebf2); background: var(--hsx-mobile-bg-surface, #fff); }
.demo-page__adaptive-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.demo-page__adaptive-metrics { margin-bottom: 18px; }
.demo-page__metric { display: flex; min-width: 0; padding: 12px; flex-direction: column; border: 1px solid var(--hsx-mobile-border, #e6ebf2); border-radius: 12px; background: var(--hsx-mobile-bg-muted, #f7f8fa); }
.demo-page__metric-label { color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 12px; }
.demo-page__metric-value { margin-top: 5px; overflow: hidden; color: var(--hsx-mobile-text-primary, #202124); font-size: 15px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.demo-page__device-list { display: flex; flex-direction: column; gap: 8px; }
.demo-page__device-row { display: flex; min-height: 72px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 12px; padding: 12px; border: 1px solid transparent; border-radius: 12px; background: var(--hsx-mobile-bg-muted, #f7f8fa); transition: border-color .18s ease, background-color .18s ease; }
.demo-page__device-row--active { border-color: rgba(60, 156, 255, .45); background: rgba(60, 156, 255, .08); }
.demo-page__device-main, .demo-page__device-side { display: flex; min-width: 0; flex-direction: column; }
.demo-page__device-main { flex: 1; }
.demo-page__device-side { flex: none; align-items: flex-end; }
.demo-page__device-name, .demo-page__device-meta, .demo-page__device-price, .demo-page__device-arrow, .demo-page__detail-title, .demo-page__detail-subtitle { display: block; }
.demo-page__device-name { overflow: hidden; color: var(--hsx-mobile-text-primary, #202124); font-size: 14px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.demo-page__device-meta { margin-top: 6px; color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 12px; }
.demo-page__device-price { color: var(--hsx-mobile-price, #ff5a36); font-size: 15px; font-weight: 700; }
.demo-page__device-arrow { margin-top: 2px; color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 20px; line-height: 14px; }
.demo-page__device-detail { min-height: 232px; }
.demo-page__back { display: inline-flex; margin-bottom: 12px; }
.demo-page__detail-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
.demo-page__detail-title { color: var(--hsx-mobile-text-primary, #202124); font-size: 18px; font-weight: 700; }
.demo-page__detail-subtitle { margin-top: 6px; color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 12px; }
.demo-page__detail-field { display: flex; padding: 12px; flex-direction: column; border-radius: 12px; background: var(--hsx-mobile-bg-muted, #f7f8fa); color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 12px; }
.demo-page__detail-value { margin-top: 6px; color: var(--hsx-mobile-text-primary, #202124); font-size: 15px; font-weight: 700; }
.demo-page__detail-action { margin-top: 18px; }
.demo-page__foundation-grid, .demo-page__schema-card, .demo-page__charts, .demo-page__utility-grid, .demo-page__business-card { margin-bottom: var(--hsx-mobile-section-gap, 16px); }
.demo-page__chart--wide { grid-column: 1 / -1; }
.demo-page__control { margin-top: 14px; }
.demo-page__switch { display: flex; align-items: center; gap: 10px; margin-top: 12px; }
.demo-page__tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.demo-page__selected-users { display: flex; min-height: 30px; flex-wrap: wrap; align-items: center; gap: 7px; margin-top: 14px; }
.demo-page__business-action { margin-top: 12px; }

.demo-page__actions { display: flex; flex: none; align-items: center; gap: 8px; }
.demo-page__theme { display: flex; width: 36px; height: 36px; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,.24); border-radius: 50%; background: rgba(5, 15, 38, .38); }

.demo-page__price {
    color: var(--hsx-mobile-price, #ff5a36);
    font-size: var(--hsx-mobile-font-body, 14px);
    font-weight: 600;
}
.demo-page__save { width: 100%; }
@media (max-width: 360px) {
    .demo-page__header { align-items: flex-start; flex-direction: column; }
    .demo-page__actions { width: 100%; justify-content: flex-end; }
}
</style>
