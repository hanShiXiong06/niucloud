<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { useDark, useToggle } from '@vueuse/core'
import { getInstalledAddonList } from '@/app/api/addon'
import useSystemStore from '@/stores/modules/system'
import { setThemeColor } from '@/utils/common'
import heroBackground from '../../assets/images/hsx-enterprise-hero.png'
import {
    HsxBadge,
    HsxActionBar,
    HsxButton,
    HsxCheckbox,
    HsxCascader,
    HsxChartCard,
    HsxDatePicker,
    HsxDialog,
    HsxDetail,
    HsxDrawer,
    HsxExport,
    HsxEntityPicker,
    HsxImport,
    HsxIcon,
    HsxInput,
    HsxGrid,
    HsxGridItem,
    HsxList,
    HsxMotion,
    HsxNoticeBubble,
    HsxOverflow,
    HsxPagination,
    HsxProgress,
    HsxProductList,
    HsxSearchInput,
    HsxSelect,
    HsxStack,
    HsxStatCard,
    HsxSwitch,
    HsxTable,
    HsxTag,
    HsxText,
    HsxTimeline,
    HsxTitle,
    HsxTreeTablePicker,
    HsxUpload,
    ProDialogForm,
    ProTable,
    QueryForm,
    createHsxCache,
    createBarChartOption,
    createDonutChartOption,
    createLineChartOption,
    createSparklineOption,
    useFeedback,
    type AnyRecord,
    type HsxActionItem,
    type HsxCascaderOption,
    type HsxCascaderValue,
    type HsxDetailItem,
    type HsxTableColumn,
    type HsxTimelineItem,
    type HsxUploadItem,
    type ProFormField,
    type ProTableColumn,
    type SelectOption
} from '../../index'

type CenterSection = 'overview' | 'basic' | 'visual' | 'pro' | 'business' | 'mobile' | 'api'

const props = withDefaults(defineProps<{ section?: CenterSection }>(), { section: 'overview' })

interface DemoRow extends AnyRecord {
    id: number
    model: string
    grade: string
    price: number
    status: number
}

interface TreeDemoRow extends AnyRecord {
    id: number
    parent_id: number
    hierarchy: string
    name: string
    category: string
    enabled: boolean
    time: string
}

const version = ref('0.1.0')
const dialogVisible = ref(false)
const drawerVisible = ref(false)
const formVisible = ref(false)
const editData = ref<AnyRecord>({})
const keyword = ref('')
const amount = ref('')
const selectedModel = ref<string | number | boolean | null>(null)
const selectedModelPath = ref<HsxCascaderValue>([])
const quickSearch = ref('')
const demoPage = ref(1)
const allowPricingField = ref(true)
const mobilePreviewUrl = ref('')
const singleDate = ref<any>('')
const dateRange = ref<any>([])
const uploadFiles = ref<HsxUploadItem[]>([])
const queryModel = ref<AnyRecord>({})
const codeVisible = ref(false)
const activeCodeName = ref('')
const progressValue = ref(68)
const layoutColumns = ref(5)
const layoutBackground = ref<'plain' | 'gradient' | 'image'>('gradient')
const checkedChannels = ref<Array<string | number | boolean>>(['public'])
const autoPublish = ref(true)
const motionVisible = ref(true)
const noticeBubbleVisible = ref(false)
const pickerVisible = ref(false)
const pickerActiveTab = ref<string | number>('organization')
const pickerSelectedUsers = ref<AnyRecord[]>([])
const businessPickerVisible = ref(false)
const businessSelectedUsers = ref<AnyRecord[]>([])
const businessProductLayout = ref<'grid' | 'list'>('grid')
const systemStore = useSystemStore()
const isDark = useDark()
const toggleDark = useToggle(isDark)
const demoCache = createHsxCache({ namespace: 'component-demo', version: '0.1.0', storage: 'memory', defaultTtl: 60_000 })
const feedback = useFeedback()

const darkMode = computed({
    get: () => isDark.value,
    set: (value: boolean) => {
        toggleDark(value)
        systemStore.setTheme('dark', value)
        setThemeColor(systemStore.theme, value ? 'dark' : 'light')
    }
})

const heroStyle = computed(() => ({ backgroundImage: `linear-gradient(100deg, rgba(4, 12, 34, .97) 0%, rgba(8, 26, 67, .82) 48%, rgba(6, 31, 76, .2) 100%), url(${heroBackground})` }))
const layoutColumnDefinition = computed(() => ({
    xs: 1,
    sm: Math.min(2, layoutColumns.value),
    md: Math.min(3, layoutColumns.value),
    lg: Math.min(4, layoutColumns.value),
    xl: layoutColumns.value
}))
const layoutSurface = computed(() => {
    if (layoutBackground.value === 'image') return {
        backgroundImage: heroBackground,
        overlay: 'linear-gradient(120deg, rgba(4, 12, 34, .92), rgba(12, 53, 116, .64))',
        color: '#fff'
    }
    if (layoutBackground.value === 'gradient') return {
        gradient: 'radial-gradient(circle at 88% 12%, rgba(59, 130, 246, .20), transparent 34%), linear-gradient(135deg, var(--hsx-bg-surface), var(--hsx-bg-muted))'
    }
    return { backgroundColor: 'var(--hsx-bg-muted)' }
})

function layoutItemSurface(index: number) {
    if (layoutBackground.value === 'image' && index === 0) return {
        backgroundImage: heroBackground,
        overlay: 'linear-gradient(135deg, rgba(5, 16, 42, .92), rgba(22, 79, 158, .58))',
        color: '#fff',
        border: '1px solid rgba(255,255,255,.22)'
    }
    if (layoutBackground.value === 'gradient') {
        const gradients = [
            'linear-gradient(135deg, rgba(59,130,246,.16), rgba(6,182,212,.08))',
            'linear-gradient(135deg, rgba(16,185,129,.14), rgba(255,255,255,.02))',
            'linear-gradient(135deg, rgba(249,115,22,.14), rgba(255,255,255,.02))',
            'linear-gradient(135deg, rgba(124,58,237,.14), rgba(255,255,255,.02))'
        ]
        return { gradient: gradients[index % gradients.length] }
    }
    return {}
}

const statusOptions = [
    { label: '待审核', value: 0 },
    { label: '已上架', value: 1 },
    { label: '已成交', value: 2 }
]

const phoneOptions: SelectOption[] = [
    { label: 'iPhone 16 Pro Max 256GB', value: 'iphone-16-pm-256' },
    { label: 'iPhone 15 Pro 256GB', value: 'iphone-15-pro-256' },
    { label: 'Mate 70 Pro 512GB', value: 'mate-70-pro-512' },
    { label: 'Xiaomi 15 Ultra 512GB', value: 'mi-15-ultra-512' }
]

const sourceRows = ref<DemoRow[]>([
    { id: 1, model: 'iPhone 16 Pro Max 256GB', grade: 'A', price: 6000, status: 1 },
    { id: 2, model: 'iPhone 15 Pro 256GB', grade: 'B+', price: 4650, status: 0 },
    { id: 3, model: 'Mate 70 Pro 512GB', grade: 'A', price: 5890, status: 2 }
])

const treeRows = ref<TreeDemoRow[]>([
    { id: 1, parent_id: 0, hierarchy: '杭州主理人货盘', name: 'Apple 热门机型计划', category: '公共货盘', enabled: true, time: '2026-08 至 2026-09' },
    { id: 11, parent_id: 1, hierarchy: 'iPhone 16 系列', name: 'iPhone 16 Pro Max 256GB', category: 'A 级设备', enabled: true, time: '2026-08-06 15:20' },
    { id: 12, parent_id: 1, hierarchy: 'iPhone 15 系列', name: 'iPhone 15 Pro 256GB', category: 'B+ 级设备', enabled: false, time: '2026-08-06 14:58' },
    { id: 2, parent_id: 0, hierarchy: '宁波主理人货盘', name: '华为热门机型计划', category: '公共货盘', enabled: true, time: '2026-08 至 2026-10' },
    { id: 21, parent_id: 2, hierarchy: 'Mate 70 系列', name: 'Mate 70 Pro 512GB', category: 'A 级设备', enabled: true, time: '2026-08-06 13:36' }
])

const treeColumns: HsxTableColumn<TreeDemoRow>[] = [
    { type: 'selection', width: 48 },
    { type: 'index', label: '序号', width: 70 },
    { prop: 'hierarchy', label: '货盘层级', minWidth: 190 },
    { prop: 'name', label: '设备 / 计划名称', minWidth: 230 },
    { prop: 'category', label: '分类', width: 130 },
    { prop: 'enabled', label: '允许销售', width: 110, align: 'center', slot: 'enabled' },
    { prop: 'time', label: '计划 / 更新时间', minWidth: 180 },
    { prop: 'actions', label: '操作', width: 90, fixed: 'right', slot: 'tree-actions' }
]

const pickerTreeData = [
    {
        id: 'all',
        label: '全国主理人网络',
        children: [
            { id: 'east', label: '华东大区', children: [{ id: 'hangzhou', label: '杭州运营中心' }, { id: 'ningbo', label: '宁波运营中心' }] },
            { id: 'south', label: '华南大区', children: [{ id: 'guangzhou', label: '广州运营中心' }, { id: 'shenzhen', label: '深圳运营中心' }] },
            { id: 'central', label: '华中大区', children: [{ id: 'zhengzhou', label: '郑州运营中心' }] }
        ]
    }
]

const pickerUserRows = [
    ['张三', 'zhangsan', '杭州运营中心', 'hangzhou', '13800138001'],
    ['李明', 'liming', '杭州运营中心', 'hangzhou', '13800138002'],
    ['王芳', 'wangfang', '宁波运营中心', 'ningbo', '13800138003'],
    ['赵磊', 'zhaolei', '广州运营中心', 'guangzhou', '13800138004'],
    ['陈晨', 'chenchen', '深圳运营中心', 'shenzhen', '13800138005'],
    ['刘洋', 'liuyang', '郑州运营中心', 'zhengzhou', '13800138006'],
    ['周琳', 'zhoulin', '杭州运营中心', 'hangzhou', '13800138007'],
    ['吴越', 'wuyue', '宁波运营中心', 'ningbo', '13800138008'],
    ['郑凯', 'zhengkai', '广州运营中心', 'guangzhou', '13800138009'],
    ['孙晓', 'sunxiao', '深圳运营中心', 'shenzhen', '13800138010'],
    ['何静', 'hejing', '郑州运营中心', 'zhengzhou', '13800138011'],
    ['高远', 'gaoyuan', '杭州运营中心', 'hangzhou', '13800138012']
].map(([name, account, department, orgId, mobile], index) => ({ id: index + 1, name, account, department, orgId, mobile }))

const businessProducts: AnyRecord[] = [
    { id: 101, title: 'iPhone 16 Pro Max 256GB', subtitle: 'A 级 · 黑色 · 国行', description: '质检报告完整，描述准确率 99.1%', price: 6280, originalPrice: 6499, status: '竞价中' },
    { id: 102, title: 'Mate 70 Pro 512GB', subtitle: 'B+ 级 · 曜石黑', description: '杭州主理人货盘，支持就近复检', price: 5180, originalPrice: 5399, status: '在售' },
    { id: 103, title: 'iPhone 15 Pro 256GB', subtitle: 'A- 级 · 原色钛金属', description: '卖家信用优秀，可享 80% 预付款', price: 4690, originalPrice: 4880, status: '待审核' },
    { id: 104, title: 'Xiaomi 15 Ultra 512GB', subtitle: 'A 级 · 白色', description: '全国公共货盘，自然曝光与私域分享', price: 4580, originalPrice: 4799, status: '在售' },
    { id: 105, title: '荣耀 Magic7 Pro 512GB', subtitle: 'B+ 级 · 灰色', description: '已上传设备图片、IMEI 与质检报告', price: 3590, originalPrice: 3799, status: '竞价中' }
]

const businessDetailSchema: HsxDetailItem[] = [
    { prop: 'title', label: '设备名称', span: 2, copyable: true },
    { prop: 'status', label: '销售状态', type: 'tag', tone: 'success' },
    { prop: 'price', label: '当前售价', type: 'money' },
    { prop: 'seller', label: '所属卖家' },
    { prop: 'manager', label: '负责主理人' },
    { prop: 'mobile', label: '联系电话', copyable: true, sensitive: true },
    { prop: 'channel', label: '销售渠道', span: 2 },
    { prop: 'description', label: '设备说明', span: 3, emptyText: '暂无说明' }
]

const businessDetailData = {
    title: 'iPhone 16 Pro Max 256GB', status: '竞价中', price: 6280, seller: '城西数码', manager: '杭州主理人 · 张三',
    mobile: '13800138001', channel: '全国公共货盘 + 张三私域货盘', description: '卖家保留货权和实物，成交后由平台加密调度快递上门取件。'
}

const businessActions: HsxActionItem[] = [
    { key: 'view', label: '查看详情', icon: 'element View', type: 'primary', plain: true, action: () => ElMessage.success('打开详情') },
    { key: 'edit', label: '编辑资料', icon: 'element Edit', action: () => ElMessage.success('进入编辑') },
    { key: 'share', label: '分享货盘', icon: 'element Share', action: () => ElMessage.success('已生成主理人分享入口') },
    { key: 'audit', label: '审核通过', icon: 'element CircleCheck', type: 'success', action: () => ElMessage.success('审核通过') },
    { key: 'remove', label: '下架', icon: 'element Delete', type: 'danger', confirm: '确认将这台设备从所有销售渠道下架吗？', action: () => ElMessage.success('已下架') }
]

const pickerColumns: HsxTableColumn[] = [
    { prop: 'name', label: '姓名', minWidth: 120 },
    { prop: 'account', label: '账号', minWidth: 140 },
    { prop: 'department', label: '所属组织', minWidth: 180 },
    { prop: 'mobile', label: '手机号', minWidth: 150 }
]

const pickerQuerySchema: ProFormField[] = [
    { prop: 'account', component: 'input', placeholder: '请输入账号' },
    { prop: 'name', component: 'input', placeholder: '请输入姓名' }
]

const timelineItems: HsxTimelineItem[] = [
    { id: 1, actor: '平台运营', time: '2026-08-06 15:42', tag: { label: '审核通过', tone: 'success' }, details: [{ label: '设备', value: 'iPhone 16 Pro Max 256GB' }, { label: '建议售价', value: '¥6,280' }] },
    { id: 2, actor: '杭州主理人·张三', time: '2026-08-06 15:18', tag: { label: '完成定价', tone: 'primary' }, details: [{ label: '最低到手价', value: '¥6,000（买家不可见）' }, { label: '销售渠道', value: '全国公共货盘' }] },
    { id: 3, actor: '卖家·城西数码', time: '2026-08-06 14:51', tag: { label: '提交设备', tone: 'warning' }, content: '已上传设备照片、质检报告及 IMEI 信息。' }
]

const foundationItems = [
    { id: 1, title: '待审核设备', description: '3 台设备等待平台完成描述与质检资料检查', value: '3' },
    { id: 2, title: '今日成交', description: '全国公共货盘已完成 28 笔 B 端成交', value: '28' },
    { id: 3, title: '售后处理中', description: '平均解决时长 7.2 小时，低于平台预警线', value: '2' }
]

const overflowLabels = ['iPhone 16 Pro Max', 'Mate 70 Pro', 'Xiaomi 15 Ultra', 'Galaxy S26 Ultra', 'OPPO Find X9', 'vivo X300 Pro']

const turnoverSparkline = createSparklineOption({ data: [42, 58, 51, 76, 68, 92, 105], area: true })
const inventorySparkline = createSparklineOption({ data: [76, 88, 71, 96, 84, 112, 98], type: 'bar', color: '#06b6d4' })
const lineChartOption = createLineChartOption({
    labels: ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
    series: [
        { name: '成交额', data: [12.6, 18.2, 16.8, 25.4, 23.1, 31.8, 36.2], color: '#2563eb' },
        { name: '卖家到手', data: [10.8, 15.9, 14.4, 22.1, 20.3, 27.6, 31.4], color: '#22c55e' }
    ],
    area: true,
    unit: '万'
})
const barChartOption = createBarChartOption({
    labels: ['Apple', '华为', '小米', '荣耀', 'OPPO', 'vivo'],
    series: [{ name: '在售设备', data: [128, 86, 72, 51, 46, 42], color: '#5b8cff' }],
    showLegend: false,
    unit: '台'
})
const donutChartOption = createDonutChartOption({
    data: [
        { name: 'A 级', value: 46, color: '#2563eb' },
        { name: 'B+ 级', value: 31, color: '#22c55e' },
        { name: 'B 级', value: 23, color: '#06b6d4' }
    ],
    centerText: '328',
    centerSubtext: '在售设备'
})

const columns: ProTableColumn<DemoRow>[] = [
    { type: 'selection', width: 48 },
    { type: 'index', label: '序号', width: 70 },
    { prop: 'model', label: '机型', minWidth: 220, search: true },
    { prop: 'grade', label: '成色', width: 90, search: true },
    { prop: 'price', label: '卖家到手价', width: 130, formatter: (_, value) => `¥${value}` },
    { prop: 'status', label: '状态', width: 100, search: { component: 'select', options: statusOptions }, options: statusOptions },
    { prop: 'actions', label: '操作', width: 150, fixed: 'right', slot: 'actions' }
]

const formSchema: ProFormField[] = [
    { prop: 'model', label: '机型', component: 'input', rules: { required: true, message: '请输入机型' } },
    { prop: 'grade', label: '成色', component: 'select', options: [{ label: 'A', value: 'A' }, { label: 'B+', value: 'B+' }, { label: 'B', value: 'B' }] },
    { prop: 'price', label: '卖家到手价', component: 'input-number', props: { min: 0, controlsPosition: 'right' } },
    { prop: 'status', label: '状态', component: 'radio', options: statusOptions, defaultValue: 0 },
    {
        prop: 'tradeMode',
        label: '销售方式',
        component: 'radio',
        defaultValue: 'consign',
        options: [
            { label: '平台代卖', value: 'consign' },
            { label: '主理人买断', value: 'buyout' }
        ]
    },
    {
        prop: 'minimumPrice',
        label: '最低到手价',
        component: 'input-number',
        visible: (model) => model.tradeMode === 'consign',
        required: (model) => model.tradeMode === 'consign',
        requiredMessage: '请设置卖家最低到手价',
        props: (model) => ({ min: Number(model.price || 0), controlsPosition: 'right' }),
        tip: (model) => `不对买家展示，当前不能低于卖家报价 ¥${Number(model.price || 0)}`
    },
    {
        prop: 'manager',
        label: '负责主理人',
        component: 'select',
        optionsDependencies: ['tradeMode'],
        optionsLoader: async ({ dependencies }) => {
            await wait(450)
            return dependencies.tradeMode === 'buyout'
                ? [{ label: '李四（买断操盘）', value: 2 }]
                : [{ label: '张三（杭州货盘）', value: 1 }, { label: '王五（宁波货盘）', value: 3 }]
        },
        required: true,
        tip: '切换销售方式后会自动重新加载可选主理人'
    },
    {
        prop: 'channels',
        label: '销售渠道',
        component: 'checkbox',
        defaultValue: ['public'],
        options: [
            { label: '全国公共货盘', value: 'public' },
            { label: '主理人私域', value: 'manager' },
            { label: '代理人 C 端', value: 'agent' }
        ],
        props: { min: 1 },
        tip: 'Checkbox 已进入 Schema，可配置最少和最多选择数量'
    },
    {
        prop: 'autoPublish',
        label: '审核后自动上架',
        component: 'switch',
        defaultValue: true,
        props: { activeText: '开启', inactiveText: '关闭' }
    },
    {
        prop: 'internalMargin',
        label: '内部目标毛利',
        component: 'input-number',
        permission: 'pricing.internal',
        props: { min: 0, controlsPosition: 'right' },
        tip: '这是权限字段；关闭演示开关后立即隐藏'
    }
]

const querySchema: ProFormField[] = [
    { prop: 'keyword', label: '关键词', component: 'input', placeholder: '机型 / IMEI / 卖家' },
    { prop: 'status', label: '状态', component: 'select', options: statusOptions },
    { prop: 'manager', label: '主理人', component: 'input' },
    { prop: 'region', label: '地区', component: 'input' },
    { prop: 'grade', label: '成色', component: 'select', options: [{ label: 'A', value: 'A' }, { label: 'B+', value: 'B+' }] },
    { prop: 'imei', label: 'IMEI', component: 'input' }
]

const exportColumns = [
    { prop: 'model', label: '机型' },
    { prop: 'grade', label: '成色' },
    { prop: 'price', label: '卖家到手价' },
    { prop: 'status', label: '状态', formatter: (_: AnyRecord, value: number) => statusOptions.find((item) => item.value === value)?.label || value }
]

const componentGroups = [
    {
        title: '平台基础组件',
        count: 33,
        tone: 'blue',
        items: ['HsxButton', 'HsxCheckbox', 'HsxSwitch', 'HsxInput', 'HsxText', 'HsxTitle', 'HsxGrid', 'HsxStack(TSX)', 'HsxList', 'HsxOverflow', 'HsxProgress', 'HsxMotion', 'HsxSearchInput', 'HsxSelect', 'HsxCascader', 'HsxDatePicker', 'HsxDateRange', 'HsxUpload', 'HsxIcon', 'HsxDialog', 'HsxDrawer', 'HsxTable', 'HsxTag', 'HsxTimeline', 'HsxChart', 'HsxChartCard', 'HsxStatCard', 'HsxBadge', 'HsxNoticeBubble', 'HsxPagination', 'HsxColumnSetting', 'HsxImport', 'HsxExport'],
        desc: '统一交互规则，减少每个业务插件重复处理加载、防连点、权限和弹窗。'
    },
    {
        title: '平台 Pro 组件',
        count: 5,
        tone: 'violet',
        items: ['ProTable', 'ProForm', 'ProDialogForm', 'QueryForm', 'HsxTreeTablePicker'],
        desc: '由 JSON Schema 生成搜索、表格、分页和表单，是快速 CRUD 的核心。'
    },
    {
        title: '通用业务组件',
        count: 5,
        tone: 'green',
        items: ['HsxEntityPicker', 'HsxProductCard', 'HsxProductList', 'HsxDetail', 'HsxActionBar'],
        desc: '组合已有基础能力，直接搭建人员选择、商品列表、详情阅读态与统一操作区。'
    },
    {
        title: '用户移动端',
        count: 23,
        tone: 'orange',
        items: ['HsxButton', 'HsxCheckbox', 'HsxSwitch', 'HsxTag', 'HsxTimeline', 'HsxCascader', 'HsxUpload', 'HsxIcon', 'HsxPopup', 'HsxForm', 'HsxPageList', 'HsxCard', 'HsxEmpty', 'HsxText', 'HsxTitle', 'HsxGrid', 'HsxStack(TSX)', 'HsxList', 'HsxOverflow', 'HsxProgress', 'HsxMotion', 'HsxBlockRenderer', 'HsxThemeProvider'],
        desc: '基于 UniApp/uview-plus，兼容 H5、小程序和 App 的移动业务页面。'
    }
]

const apiRows = [
    { component: 'HsxEntityPicker', input: 'request、treeData、fieldMap、multiple、columns', output: 'confirm、clear、update:modelValue', value: '基于树表选择器形成管理员、用户、门店、主理人等统一选择入口' },
    { component: 'HsxProductList / Card', input: 'items、fieldMap、actions、layout、columns', output: 'item-click、action、插槽', value: '商品卡片、1–5 列响应式布局、列表模式、骨架和空态统一' },
    { component: 'HsxDetail', input: 'data、schema、columns、permission、formatter', output: 'copy、click、字段插槽', value: '详情阅读态、金额、状态、图片、复制、脱敏和权限统一' },
    { component: 'HsxActionBar', input: 'actions、context、permission、confirm、maxVisible', output: 'action、error、动作插槽', value: 'PC 与移动端统一配置操作，内置权限、防重复、确认和更多菜单' },
    { component: 'HsxChart / useChart', input: 'option、height、loading、empty、renderer', output: 'ready、click、finished、实例方法', value: 'ECharts 初始化、暗黑模式、响应式缩放、空态和销毁统一' },
    { component: 'HsxChartCard / HsxStatCard', input: 'option、title、value、trend、icon', output: 'chart-click、插槽', value: '驾驶舱大图表、指标卡和迷你图表统一' },
    { component: 'useFeedback', input: '文案或 Message/Notification 配置', output: 'light、notice、confirm、alert', value: '轻提示、重通知和风险确认统一，并隔离底层 UI 库' },
    { component: 'HsxBadge / HsxNoticeBubble', input: 'value、dot、title、trigger、placement', output: 'show、hide、update:modelValue', value: '红点、数量角标和就地气泡说明统一' },
    { component: 'HsxDrawer', input: 'modelValue、size、direction、beforeClose', output: 'open、close、confirm、cancel、全部插槽', value: '抽屉生命周期、属性、事件和插槽统一透传' },
    { component: 'HsxCheckbox / HsxSwitch', input: 'options、min/max、beforeChange、confirm、action', output: 'update:modelValue、change、error', value: '表单选择、异步开关和防重复操作统一' },
    { component: 'HsxTable', input: 'columns、data、autoTree、selectionMode', output: 'select、selection-change、expand-change、插槽', value: '普通表格、父子级、级联选择、展开子表和行内编辑' },
    { component: 'HsxTreeTablePicker', input: 'treeData、columns、request、querySchema、multiple', output: 'confirm、selection-change、tree-change、load', value: '左树、右侧查询表格、跨页选择、分页与确认弹窗一次组合' },
    { component: 'createHsxCache', input: 'namespace、version、defaultTtl、storage', output: 'get/set/remove/remember/clear', value: '命名空间、过期、版本失效和并发请求合并' },
    { component: 'HsxTag / HsxTimeline', input: 'tone、items、reverse、compact', output: '语义事件 / 内容插槽', value: '统一业务状态语义与操作流水展示' },
    { component: 'HsxText / HsxTitle', input: 'tone、size、lines、ellipsis、level', output: '插槽 / 原生属性', value: '统一排版层级、长文本截断和溢出提示' },
    { component: 'HsxGrid / HsxStack', input: 'columns、minItemWidth、direction、gap、wrap', output: '布局插槽', value: '响应式网格与 TSX 强类型弹性布局' },
    { component: 'HsxList / HsxOverflow', input: 'items、loading、direction、fade', output: 'item-click、插槽', value: '加载骨架、空态、键盘操作和溢出滚动' },
    { component: 'HsxProgress / HsxMotion', input: 'percentage、tone、preset、duration', output: '状态展示', value: '统一进度语义与尊重减少动画系统设置' },
    { component: 'HsxButton', input: 'action、loading、debounce、permission', output: 'click、error', value: '异步自动 loading、防重复提交、按钮权限' },
    { component: 'HsxInput', input: 'modelValue、numeric、decimalPlaces', output: 'update:modelValue、search、change', value: '数字约束、失焦格式化、回车搜索' },
    { component: 'HsxSelect', input: 'options、fetchOptions、resolveValues', output: 'change、load', value: '远程搜索、分页加载、选项缓存回显' },
    { component: 'HsxCascader', input: 'fetchOptions、searchOptions、resolvePaths', output: 'change、load、search、error', value: '任意层级、异步加载、远程搜索和编辑回显' },
    { component: 'HsxSearchInput', input: 'modelValue、autoSearch、debounce', output: 'search、clear', value: '回车、按钮、清空和防抖搜索统一' },
    { component: 'HsxPagination', input: 'currentPage、pageSize、total', output: 'change、current-change、size-change', value: '统一分页参数和交互' },
    { component: 'HsxColumnSetting', input: 'modelValue、storageKey、allowFixed', output: 'change、reset', value: '显示隐藏、左右固定、排序和本地记忆' },
    { component: 'HsxImport', input: 'importer、accept、maxSizeMb', output: 'submit、success、error', value: '模板、文件校验、上传状态统一' },
    { component: 'HsxExport', input: 'data、columns、exporter、format', output: 'start、success、error', value: '本地 CSV/JSON 与后端 Excel 导出统一' },
    { component: 'HsxDialog', input: 'modelValue、fullscreen、draggable', output: 'confirm、cancel、close', value: '统一弹窗、拖拽、全屏、插槽扩展' },
    { component: 'HsxDatePicker', input: 'type、normalizeRangeBoundary、maxSpanDays', output: 'change、raw-change、exceed', value: '单时间/区间统一，区间自动补齐 00:00:00—23:59:59' },
    { component: 'HsxDateRange', input: '同 HsxDatePicker 区间能力', output: 'change、exceed', value: '兼容已有业务的区间入口' },
    { component: 'HsxUpload', input: 'uploader、previewSize/Width/Height、shape', output: 'success、error、progress', value: '上传状态、校验、可控预览尺寸和后端适配统一' },
    { component: 'HsxIcon', input: 'name、component、src、size、color', output: 'click', value: '统一 Element、字体图标、图片和业务组件图标' },
    { component: 'QueryForm', input: 'schema、collapseCount、initialValues', output: 'search、reset、collapse-change', value: '查询条件配置化、折叠和重置统一' },
    { component: 'ProTable', input: 'columns、request、responseAdapter', output: 'load、search、selection-change', value: '搜索表单、分页、表格由 Schema 一次生成' },
    { component: 'ProForm', input: 'visible、required、permission、optionsLoader、components', output: 'change、options-load、options-error', value: '动态联动、字段权限、异步选项和业务组件注册' },
    { component: 'ProDialogForm', input: 'schema、submit、formData', output: 'success、error、close', value: '新增编辑共用、数据回显、提交状态管理' },
    { component: 'HsxPageList（移动端）', input: 'request、pageSize、fixed、height', output: 'load、error', value: '基于 z-paging 的刷新、分页、空态和异常重试' }
]

const codeExamples: Record<string, string> = {
    HsxEntityPicker: `<HsxEntityPicker\n  v-model="selectedUsers"\n  v-model:visible="visible"\n  :tree-data="organizationTree"\n  :request="getUserList"\n  multiple\n/>`,
    HsxProductList: `<HsxProductList\n  :items="products"\n  :columns="{ xs: 1, sm: 2, md: 3, lg: 4, xl: 5 }"\n  :actions="productActions"\n  @item-click="openDetail"\n/>`,
    HsxDetail: `<HsxDetail :data="detail" :schema="detailSchema" :columns="3" />`,
    HsxActionBar: `<HsxActionBar :actions="actions" :context="row" @action="trackAction" />`,
    HsxChart: `const option = createLineChartOption({\n  labels: ['周一', '周二'],\n  series: [{ name: '成交额', data: [12, 18] }],\n  area: true\n})\n\n<HsxChartCard title="成交趋势" :option="option" />\n<HsxStatCard title="今日成交" :value="128" :chart-option="sparkline" />`,
    useFeedback: `const feedback = useFeedback()\n\nfeedback.success('保存成功') // 轻提示\nfeedback.noticeWarning({ title: '行情变动', message: '建议重新定价' }) // 重通知\nconst confirmed = await feedback.confirm({ message: '确定清退该卖家吗？', type: 'error' })`,
    HsxDrawer: `<HsxDrawer v-model="visible" title="设备详情" size="520px" show-footer @confirm="save">\n  <template #default="{ close }">业务内容...</template>\n  <template #footer="{ close, confirm }">自定义动作区</template>\n</HsxDrawer>`,
    HsxFormControls: `<HsxCheckbox v-model="channels" :options="channelOptions" :min="1" />\n<HsxSwitch v-model="enabled" :confirm="confirmChange" :action="saveState" />\n\n// ProForm Schema 直接使用\n{ prop: 'channels', component: 'checkbox', options: channelOptions }\n{ prop: 'enabled', component: 'switch', props: { activeText: '开启' } }`,
    HsxTable: `<HsxTable\n  :columns="columns"\n  :data="flatRows"\n  auto-tree\n  selection-mode="cascade"\n  default-expand-all\n>\n  <template #status="{ row }"><HsxTag :tone="row.tone" :text="row.status" /></template>\n  <template #expand="{ row }"><HsxTable :data="row.devices" :columns="deviceColumns" /></template>\n</HsxTable>`,
    HsxTreeTablePicker: `<HsxTreeTablePicker\n  v-model:visible="visible"\n  v-model="selectedUsers"\n  title="选择责任人"\n  :tree-data="organizationTree"\n  :query-schema="[{ prop: 'account', component: 'input' }, { prop: 'name', component: 'input' }]"\n  :columns="userColumns"\n  :request="getUserList"\n  multiple\n  @confirm="saveSelection"\n/>`,
    HsxTimeline: `<HsxTimeline :items="records" />\n<HsxTag text="审核通过" tone="success" dot />`,
    HsxCache: `const cache = createHsxCache({ namespace: 'goods', version: '1', defaultTtl: 60_000 })\ncache.set('filters', form)\nconst filters = cache.get('filters', {})\nconst detail = await cache.remember('detail:1001', () => getDetail(1001))`,
    HsxFoundation: `<HsxTitle title="货盘概览" subtitle="今日设备流转数据" />\n<HsxText :text="description" :lines="2" :max-width="420" />\n<HsxGrid :columns="{ xs: 1, md: 2, lg: 4 }" :gap="16">...</HsxGrid>\n<HsxProgress :percentage="68" label="资料完整度" auto-tone />`,
    HsxGrid: `<HsxGrid\n  :columns="{ xs: 1, sm: 2, md: 3, lg: 4, xl: 5 }"\n  :gap="16"\n  gradient="linear-gradient(135deg, #f8fbff, #eef4ff)"\n  :padding="18" :radius="18"\n>\n  <HsxGridItem v-for="item in 8" :key="item" :span="item === 1 ? { xs: 1, lg: 2 } : 1">\n    卡片内容\n  </HsxGridItem>\n  <HsxGridItem full>跨满整行的内容</HsxGridItem>\n</HsxGrid>`,
    HsxStack: `// HsxStack 本体使用 TSX 实现，Template 与 TSX 页面均可调用\nconst Toolbar = () => (\n  <HsxStack gap={8} justify="end" wrap>\n    <HsxButton>取消</HsxButton>\n    <HsxButton type="primary" action={save}>保存</HsxButton>\n  </HsxStack>\n)`,
    HsxButton: `<HsxButton type="primary" :action="save">保存</HsxButton>`,
    HsxInput: `<HsxInput v-model="price" numeric :decimal-places="2" @search="query" />`,
    HsxSearchInput: `<HsxSearchInput v-model="keyword" auto-search @search="query" />`,
    HsxSelect: `<HsxSelect v-model="modelId" :fetch-options="fetchModels" :resolve-values="resolveModels" />`,
    HsxCascader: `<HsxCascader v-model="modelPath" :fetch-options="loadChildren" :search-options="searchPaths" />`,
    HsxIcon: `<HsxIcon name="element Search" :size="20" color="var(--el-color-primary)" />\n<HsxIcon name="iconfont iconshangpin" :size="20" />`,
    HsxDialog: `<HsxDialog v-model="visible" title="设备信息" draggable fullscreen>...</HsxDialog>`,
    HsxPagination: `<HsxPagination v-model:current-page="page" v-model:page-size="limit" :total="total" />`,
    HsxImport: `<HsxImport :importer="importDevices" />\n<HsxExport :data="rows" :columns="columns" filename="设备货盘" />`,
    HsxDatePicker: `<HsxDatePicker v-model="createdAt" type="datetime" />\n<HsxDatePicker v-model="range" type="daterange" />\n<HsxDatePicker v-model="range" type="daterange" full-width />\n<!-- 默认宽度：日期 180px、日期时间 220px、区间 360px -->\n<!-- range 输出：00:00:00 至 23:59:59 -->`,
    HsxUpload: `<HsxUpload\n  v-model="files"\n  :uploader="uploadFile"\n  :preview-size="88"\n  shape="square"\n/>`,
    QueryForm: `<QueryForm v-model="query" :schema="querySchema" @search="loadList" />`,
    ProTable: `<ProTable :columns="columns" :request="getGoodsList" row-key="id" />`
}

const activeCode = computed(() => codeExamples[activeCodeName.value] || '')

const codeExample = computed(() => {
    if (props.section === 'mobile') {
        return `<HsxPageList :request="getGoods" :page-size="20">\n  <template #item="{ item }">\n    <HsxCard :title="item.model" />\n  </template>\n</HsxPageList>`
    }
    return `<ProTable\n  :columns="columns"\n  :request="getGoodsList"\n  row-key="id"\n>\n  <template #actions="{ row }">\n    <el-button @click="edit(row)">编辑</el-button>\n  </template>\n</ProTable>`
})

function showCode(name: string) {
    activeCodeName.value = name
    codeVisible.value = true
}

async function replayMotion() {
    motionVisible.value = false
    await nextTick()
    motionVisible.value = true
}

async function copyActiveCode() {
    await navigator.clipboard.writeText(activeCode.value)
    ElMessage.success('代码已复制')
}

async function requestRows(params: AnyRecord) {
    await wait(250)
    const filtered = sourceRows.value.filter((row) => {
        return (!params.model || row.model.includes(params.model))
            && (!params.grade || row.grade.includes(params.grade))
            && (params.status === undefined || row.status === params.status)
    })
    const start = (params.page - 1) * params.limit
    return { data: { list: filtered.slice(start, start + params.limit), total: filtered.length } }
}

async function requestPickerUsers(params: AnyRecord) {
    await wait(180)
    const childOrgMap: Record<string, string[]> = {
        all: ['hangzhou', 'ningbo', 'guangzhou', 'shenzhen', 'zhengzhou'],
        east: ['hangzhou', 'ningbo'],
        south: ['guangzhou', 'shenzhen'],
        central: ['zhengzhou']
    }
    const orgIds = childOrgMap[params.tree_id] || (params.tree_id ? [params.tree_id] : childOrgMap.all)
    const filtered = pickerUserRows.filter((row) => orgIds.includes(row.orgId)
        && (!params.account || row.account.includes(params.account))
        && (!params.name || row.name.includes(params.name)))
    const start = (params.page - 1) * params.limit
    return { list: filtered.slice(start, start + params.limit), total: filtered.length }
}

function confirmPicker(value: AnyRecord[] | AnyRecord | null) {
    const rows = Array.isArray(value) ? value : value ? [value] : []
    ElMessage.success(`已选择 ${rows.length} 位责任人`)
}

async function fetchPhoneOptions(search: string, page: number, limit: number) {
    await wait(300)
    const result = phoneOptions.filter((item) => String(item.label).toLowerCase().includes(search.toLowerCase()))
    const start = (page - 1) * limit
    return { list: result.slice(start, start + limit), total: result.length }
}

const modelPaths: HsxCascaderOption[][] = [
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
    ],
    [
        { label: '小米', value: 'xiaomi' },
        { label: '小米 15 系列', value: 'mi-15' },
        { label: 'Xiaomi 15 Ultra 512GB', value: 'mi-15-ultra-512', leaf: true }
    ]
]

async function fetchModelOptions(parent: HsxCascaderOption | null) {
    await wait(350)
    if (!parent) {
        return [
            { label: 'Apple', value: 'apple' },
            { label: '华为', value: 'huawei' },
            { label: '小米', value: 'xiaomi' }
        ]
    }
    const nextLevel = modelPaths
        .filter((path) => path.some((item) => item.value === parent.value))
        .map((path) => path[path.findIndex((item) => item.value === parent.value) + 1])
        .filter(Boolean)
    return Array.from(new Map(nextLevel.map((item) => [item.value, item])).values())
}

async function searchModelOptions(keyword: string) {
    await wait(300)
    const normalized = keyword.trim().toLowerCase()
    return modelPaths.filter((path) => path.some((item) => String(item.label).toLowerCase().includes(normalized)))
}

async function resolveModelPaths(value: HsxCascaderValue) {
    await wait(180)
    const values = new Set((Array.isArray(value) ? value.flat() : [value]).map(String))
    return modelPaths.filter((path) => values.has(String(path[path.length - 1].value)))
}

function handleModelChange(value: HsxCascaderValue) {
    const labels = Array.isArray(value) ? value.flat().join(' / ') : String(value || '-')
    ElMessage.success(`已选择：${labels}`)
}

function openCreate() {
    editData.value = { status: 0 }
    formVisible.value = true
}

function openEdit(row: DemoRow) {
    editData.value = { ...row }
    formVisible.value = true
}

function checkDemoPermission(permission?: string | string[]) {
    if (!permission) return true
    const permissions = Array.isArray(permission) ? permission : [permission]
    return !permissions.includes('pricing.internal') || allowPricingField.value
}

async function submitForm(data: AnyRecord) {
    await wait(500)
    if (data.id) {
        const index = sourceRows.value.findIndex((item) => item.id === data.id)
        if (index >= 0) sourceRows.value[index] = { ...sourceRows.value[index], ...data } as DemoRow
    } else {
        sourceRows.value.push({ id: Date.now(), ...data } as DemoRow)
    }
    ElMessage.success('演示数据已保存')
}

async function runAsyncAction() {
    await wait(900)
    ElMessage.success('异步任务完成，loading 已自动关闭')
}

async function saveAutoPublish(next: boolean) {
    await wait(500)
    ElMessage.success(next ? '已开启审核后自动上架' : '已关闭自动上架')
}

function updateTreeEnabled(row: TreeDemoRow, enabled: boolean) {
    const source = treeRows.value.find((item) => item.id === row.id)
    if (source) source.enabled = enabled
}

async function runCacheDemo() {
    const value = await demoCache.remember('market-price:iphone-16-pm', async () => {
        await wait(350)
        return { price: 6280, updatedAt: new Date().toLocaleTimeString() }
    })
    ElMessage.success(`缓存命中：建议售价 ¥${value.price}，更新时间 ${value.updatedAt}`)
}

async function runImport(file: File) {
    await wait(650)
    ElMessage.success(`已完成演示导入：${file.name}`)
    return { imported: 1 }
}

async function runUpload(file: File, context: { onProgress: (percentage: number) => void }) {
    context.onProgress(25)
    await wait(220)
    context.onProgress(70)
    await wait(220)
    context.onProgress(100)
    return { name: file.name, url: URL.createObjectURL(file) }
}

function runQuery(value: AnyRecord) {
    ElMessage.success(`查询条件：${JSON.stringify(value)}`)
}

async function runDangerConfirm() {
    const confirmed = await feedback.confirm({
        title: '清退卖家',
        message: '该操作会限制卖家继续发布设备，确认继续吗？',
        confirmText: '确认清退',
        type: 'error'
    })
    if (confirmed) feedback.success('已完成演示确认')
}

function removeRow(row: DemoRow) {
    sourceRows.value = sourceRows.value.filter((item) => item.id !== row.id)
    ElMessage.success('已删除本地演示数据')
}

function openMobilePreview() {
    window.open(mobilePreviewUrl.value, '_blank')
}

function wait(milliseconds: number) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds))
}

onMounted(() => {
    mobilePreviewUrl.value = `${window.location.origin}/wap/addon/hsx_components/pages/demo/index`
    getInstalledAddonList().then(({ data }) => {
        version.value = data?.hsx_components?.version || '0.1.0'
    }).catch(() => undefined)
})
</script>

<template>
    <div class="component-center hsx-theme">
        <section class="hero" :style="heroStyle">
            <div class="hero__content">
                <div class="hero__eyebrow">FRAMEWORK COMPONENT LIBRARY</div>
                <h1>HSX 组件开发中心</h1>
                <p>平台端与用户移动端共用的开发基建。看文档、试交互、复制用法，然后在业务插件中直接引用。</p>
                <div class="hero__meta">
                    <span class="version">v{{ version }}</span>
                    <span>开发版本</span>
                    <span>·</span>
                    <span>Schema 驱动</span>
                    <span>·</span>
                    <span>Vue 3 + UniApp</span>
                </div>
                <div class="hero__theme">
                    <HsxIcon :name="darkMode ? 'element Moon' : 'element Sunny'" :size="18" />
                    <span>暗黑模式</span>
                    <el-switch v-model="darkMode" inline-prompt active-text="开" inactive-text="关" />
                </div>
            </div>
            <div class="hero__visual" aria-hidden="true">
                <div class="visual-card visual-card--back">Mobile</div>
                <div class="visual-card visual-card--front">&lt;ProTable /&gt;</div>
            </div>
        </section>

        <main class="docs-content">
            <template v-if="props.section === 'overview'">
                <div class="group-grid">
                    <article v-for="group in componentGroups" :key="group.title" class="group-card" :class="`group-card--${group.tone}`">
                        <div class="group-card__top">
                            <h3>{{ group.title }}</h3>
                            <strong>{{ group.count }}</strong>
                        </div>
                        <p>{{ group.desc }}</p>
                        <div class="tag-list">
                            <el-tag v-for="item in group.items" :key="item" effect="plain" round>{{ item }}</el-tag>
                        </div>
                    </article>
                </div>

                <section class="doc-card architecture">
                    <div>
                        <span class="section-kicker">依赖边界</span>
                        <h2>三层架构，只允许向下依赖</h2>
                        <p>业务插件只通过组件库公开出口调用能力；组件库不引用任何手机交易业务，后续升级才不会牵一发动全身。</p>
                    </div>
                    <div class="architecture__flow">
                        <div><small>第 3 层</small><b>货盘 / 质检 / 交易 / 资金</b></div>
                        <span>↓</span>
                        <div class="architecture__primary"><small>第 2 层</small><b>HSX 组件库 v{{ version }}</b></div>
                        <span>↓</span>
                        <div><small>第 1 层</small><b>NiuCloud 核心框架</b></div>
                    </div>
                </section>
                <section class="doc-card design-contract">
                    <HsxTitle title="产品级 UI / UX 合同" subtitle="规则写入令牌、组件和测试，不依赖开发者临场发挥" eyebrow="DESIGN SYSTEM" />
                    <HsxGrid :columns="{ xs: 1, md: 2, lg: 4 }" :gap="12">
                        <div><b>8pt 间距体系</b><span>页面只使用受控间距档位</span></div>
                        <div><b>语义排版</b><span>页标题、区块、正文、辅助文案分层</span></div>
                        <div><b>克制动效</b><span>120/200/320ms，并尊重减少动画设置</span></div>
                        <div><b>完整状态</b><span>加载、空态、错误、禁用、溢出均有规则</span></div>
                    </HsxGrid>
                </section>
            </template>

            <template v-else-if="props.section === 'basic'">
                <div class="demo-grid">
                    <section class="doc-card demo-panel demo-panel--wide foundation-demo">
                        <div class="panel-title"><div><h2>排版、布局与反馈规范</h2><p>标题、长文本、响应式网格、列表、溢出、进度和动效使用同一套设计令牌。</p></div><div class="panel-actions"><el-tag type="success">产品基建</el-tag><el-button link type="primary" @click="showCode('HsxFoundation')">查看代码</el-button></div></div>
                        <HsxTitle title="货盘运营概览" subtitle="文字层级、间距和动作区已经标准化" eyebrow="FOUNDATION" size="section">
                            <template #extra><el-button size="small" @click="replayMotion">重播动效</el-button><el-button link type="primary" @click="showCode('HsxStack')">TSX 写法</el-button></template>
                        </HsxTitle>
                        <HsxMotion :show="motionVisible" preset="fade-up" :duration="260">
                            <HsxGrid :columns="{ xs: 1, md: 2, lg: 3 }" :gap="14" class="foundation-grid">
                                <article v-for="item in foundationItems" :key="item.id" class="metric-card">
                                    <HsxStack justify="between" align="start" :gap="16">
                                        <div class="metric-card__content">
                                            <HsxText :text="item.title" tone="primary" weight="600" />
                                            <HsxText :text="item.description" tone="secondary" size="caption" :lines="2" :max-width="280" />
                                        </div>
                                        <strong>{{ item.value }}</strong>
                                    </HsxStack>
                                </article>
                            </HsxGrid>
                        </HsxMotion>
                        <HsxProgress :percentage="progressValue" label="设备资料完整度" description="图片、质检报告与卖家描述" auto-tone />
                        <HsxOverflow direction="x" :fade="true" class="foundation-overflow">
                            <HsxStack :gap="8">
                                <el-tag v-for="label in overflowLabels" :key="label" round effect="plain">{{ label }}</el-tag>
                            </HsxStack>
                        </HsxOverflow>
                        <HsxList :items="foundationItems" hoverable clickable @item-click="ElMessage.success(`打开：${$event.title}`)">
                            <template #default="{ item }"><div class="foundation-list-item"><HsxText :text="item.title" tone="primary" weight="600" /><HsxText :text="item.description" tone="secondary" size="caption" ellipsis /></div></template>
                            <template #action><HsxIcon name="element ArrowRight" /></template>
                        </HsxList>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxButton</h2><p>异步加载、防连点和权限控制内置。</p></div><div class="panel-actions"><el-tag>平台端</el-tag><el-button link type="primary" @click="showCode('HsxButton')">查看代码</el-button></div></div>
                        <div class="demo-row">
                            <HsxButton type="primary" :action="runAsyncAction">点我测试自动 Loading</HsxButton>
                            <HsxButton :permission="'goods.delete'" :permission-checker="() => false" :hide-without-permission="false">无权限状态</HsxButton>
                        </div>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxInput</h2><p>回车搜索与金额格式约束。</p></div><div class="panel-actions"><el-tag>平台端</el-tag><el-button link type="primary" @click="showCode('HsxInput')">查看代码</el-button></div></div>
                        <div class="form-stack">
                            <HsxInput v-model="keyword" clearable placeholder="输入机型后按回车" @search="ElMessage.success(`搜索：${$event || '空'}`)" />
                            <HsxInput v-model="amount" numeric :decimal-places="2" placeholder="只允许输入金额，例如 6200.00">
                                <template #prepend>¥</template>
                            </HsxInput>
                        </div>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxSearchInput</h2><p>按钮、回车、防抖和清空搜索使用同一个事件。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxSearchInput')">查看代码</el-button></div></div>
                        <HsxSearchInput
                            v-model="quickSearch"
                            auto-search
                            placeholder="输入关键词，停顿后自动搜索"
                            @search="ElMessage.success(`搜索：${$event || '全部'}`)"
                        />
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxSelect</h2><p>远程搜索、分页、缓存与编辑回显。</p></div><div class="panel-actions"><el-tag>平台端</el-tag><el-button link type="primary" @click="showCode('HsxSelect')">查看代码</el-button></div></div>
                        <HsxSelect v-model="selectedModel" :fetch-options="fetchPhoneOptions" placeholder="输入 iPhone / Mate 搜索" clearable style="width: 100%" />
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxCascader</h2><p>品牌 → 系列 → 型号，逐级异步加载，也支持直接搜索末级型号。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxCascader')">查看代码</el-button></div></div>
                        <HsxCascader
                            v-model="selectedModelPath"
                            :fetch-options="fetchModelOptions"
                            :search-options="searchModelOptions"
                            :resolve-paths="resolveModelPaths"
                            placeholder="搜索或逐级选择型号"
                            @change="handleModelChange"
                        />
                        <p class="demo-value">当前值：{{ selectedModelPath || '未选择' }}</p>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxIcon</h2><p>统一 Element、框架字体图标、图片和业务组件图标的调用协议。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxIcon')">查看代码</el-button></div></div>
                        <div class="icon-demo-row">
                            <span><HsxIcon name="element Search" :size="22" /><small>Search</small></span>
                            <span><HsxIcon name="element Goods" :size="22" /><small>Goods</small></span>
                            <span><HsxIcon name="element UploadFilled" :size="22" /><small>Upload</small></span>
                            <span><HsxIcon name="element DataAnalysis" :size="22" /><small>Data</small></span>
                            <span><HsxIcon name="element Setting" :size="22" spin /><small>Spin</small></span>
                        </div>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxDialog</h2><p>统一宽度、拖拽、全屏和底部动作。</p></div><div class="panel-actions"><el-tag>平台端</el-tag><el-button link type="primary" @click="showCode('HsxDialog')">查看代码</el-button></div></div>
                        <el-button type="primary" plain @click="dialogVisible = true">打开可拖拽弹窗</el-button>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxDrawer</h2><p>默认、头部、底部插槽和 Element 事件完整透传。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxDrawer')">查看代码</el-button></div></div>
                        <el-button type="primary" plain @click="drawerVisible = true">打开设备详情抽屉</el-button>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxCheckbox / HsxSwitch</h2><p>既可独立使用，也已接入 ProForm Schema。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxFormControls')">查看代码</el-button></div></div>
                        <div class="form-stack">
                            <HsxCheckbox v-model="checkedChannels" :min="1" :options="[{ label: '全国货盘', value: 'public' }, { label: '主理人私域', value: 'manager' }, { label: '代理 C 端', value: 'agent' }]" />
                            <HsxStack align="center" :gap="10"><HsxSwitch v-model="autoPublish" active-text="审核后上架" :action="saveAutoPublish" /><HsxText :text="autoPublish ? '已开启' : '已关闭'" tone="secondary" size="caption" /></HsxStack>
                        </div>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>createHsxCache</h2><p>TTL、版本隔离、命名空间和相同请求合并。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxCache')">查看代码</el-button></div></div>
                        <HsxButton type="primary" plain :action="runCacheDemo">读取行情缓存</HsxButton>
                    </section>

                    <section class="doc-card demo-panel demo-panel--wide">
                        <div class="panel-title"><div><h2>HsxTag / HsxTimeline</h2><p>只统一状态语义和流水骨架，具体业务内容仍由数据或插槽决定。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxTimeline')">查看代码</el-button></div></div>
                        <div class="tag-demo-row"><HsxTag text="待审核" tone="warning" dot /><HsxTag text="已上架" tone="success" dot /><HsxTag text="售后处理中" tone="danger" dot /><HsxTag text="主理人定价" tone="primary" dot /></div>
                        <HsxTimeline :items="timelineItems" compact class="timeline-demo" />
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxPagination</h2><p>统一页码、每页数量和 change 出参。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxPagination')">查看代码</el-button></div></div>
                        <HsxPagination v-model:current-page="demoPage" :page-size="20" :total="186" layout="prev, pager, next" />
                        <p class="demo-value">当前第 {{ demoPage }} 页</p>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxImport / HsxExport</h2><p>统一文件校验、异步状态和下载格式。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('HsxImport')">查看代码</el-button></div></div>
                        <div class="demo-row">
                            <HsxImport :importer="runImport" />
                            <HsxExport :data="sourceRows" :columns="exportColumns" filename="设备货盘演示" type="primary" plain />
                        </div>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxDatePicker</h2><p>单时间与区间统一；区间自动补齐全天边界。</p></div><div class="panel-actions"><el-tag type="success">增强</el-tag><el-button link type="primary" @click="showCode('HsxDatePicker')">查看代码</el-button></div></div>
                        <div class="form-stack">
                            <HsxDatePicker v-model="singleDate" type="datetime" placeholder="选择单个时间" />
                            <HsxDatePicker v-model="dateRange" type="daterange" :max-span-days="90" />
                        </div>
                        <p class="demo-value">区间出参：{{ dateRange?.join(' 至 ') || '未选择' }}</p>
                    </section>

                    <section class="doc-card demo-panel">
                        <div class="panel-title"><div><h2>HsxUpload</h2><p>业务只提供 uploader，进度、校验、预览和状态由组件处理。</p></div><div class="panel-actions"><el-tag type="success">增强</el-tag><el-button link type="primary" @click="showCode('HsxUpload')">查看代码</el-button></div></div>
                        <HsxUpload v-model="uploadFiles" :uploader="runUpload" :limit="3" :max-size-mb="5" :preview-size="88" tip="演示使用浏览器本地预览，不上传服务器" />
                    </section>

                    <section class="doc-card demo-panel demo-panel--wide">
                        <div class="panel-title"><div><h2>QueryForm</h2><p>Schema 生成查询条件，超过一行自动折叠。</p></div><div class="panel-actions"><el-tag type="success">新增</el-tag><el-button link type="primary" @click="showCode('QueryForm')">查看代码</el-button></div></div>
                        <QueryForm v-model="queryModel" :schema="querySchema" :collapse-count="4" @search="runQuery" />
                    </section>
                </div>
            </template>

            <template v-else-if="props.section === 'visual'">
                <section class="visual-intro">
                    <div>
                        <span class="section-kicker">DATA VISUALIZATION & FEEDBACK</span>
                        <h2>驾驶舱图表与全局反馈</h2>
                        <p>业务只提供数据和文案。图表生命周期、暗黑模式、尺寸变化、空状态，以及轻提示、重通知和确认动作都由组件库处理。</p>
                    </div>
                    <div class="capability-tags">
                        <HsxTag text="ECharts 适配层" tone="primary" />
                        <HsxTag text="自动暗黑模式" tone="success" />
                        <HsxTag text="ResizeObserver" tone="info" />
                        <HsxTag text="UI 库可替换" tone="warning" />
                    </div>
                </section>

                <section class="doc-card layout-playground">
                    <div class="panel-title">
                        <div><h2>响应式卡片布局</h2><p>一份配置控制 1–5 列、多行、跨列、整行及纯色、渐变、背景图。</p></div>
                        <div class="panel-actions"><el-tag type="success">可配置</el-tag><el-button link type="primary" @click="showCode('HsxGrid')">查看代码</el-button></div>
                    </div>
                    <div class="layout-playground__toolbar">
                        <span>宽屏列数</span>
                        <el-radio-group v-model="layoutColumns" size="small">
                            <el-radio-button v-for="count in 5" :key="count" :value="count">{{ count }} 列</el-radio-button>
                        </el-radio-group>
                        <span>背景</span>
                        <el-select v-model="layoutBackground" size="small" style="width: 128px">
                            <el-option label="纯色" value="plain" />
                            <el-option label="渐变色" value="gradient" />
                            <el-option label="背景图" value="image" />
                        </el-select>
                    </div>
                    <HsxGrid v-bind="layoutSurface" :columns="layoutColumnDefinition" :gap="14" :padding="18" :radius="18" border="1px solid var(--hsx-border-color)">
                        <HsxGridItem
                            v-for="index in 8"
                            :key="index"
                            v-bind="layoutItemSurface(index - 1)"
                            :span="index === 1 ? { xs: 1, lg: Math.min(2, layoutColumns) } : 1"
                            :min-height="112"
                            shadow
                        >
                            <span class="layout-card__eyebrow">CARD {{ String(index).padStart(2, '0') }}</span>
                            <strong>{{ index === 1 ? '支持响应式跨列' : `第 ${index} 张业务卡片` }}</strong>
                            <small>自动换行形成多行，不限制卡片数量</small>
                        </HsxGridItem>
                        <HsxGridItem full :padding="13" background-color="rgba(255,255,255,.82)" border="1px dashed var(--hsx-border-color)">
                            <span class="layout-card__footer">full：需要时可让公告、汇总或操作区跨满整行</span>
                        </HsxGridItem>
                    </HsxGrid>
                </section>

                <HsxGrid :columns="{ xs: 1, md: 2, lg: 4 }" :gap="14" class="stat-demo-grid">
                    <HsxStatCard title="今日成交额" :value="362800" unit="元" trend="+18.6%" trend-label="较昨日" icon="element TrendCharts" :chart-option="turnoverSparkline" />
                    <HsxStatCard title="公共货盘" :value="328" unit="台" trend="+24" trend-label="今日新增" tone="info" icon="element Box" :chart-option="inventorySparkline" />
                    <HsxStatCard title="平均成交时长" value="5.8" unit="小时" trend="-12.4%" trend-label="效率提升" tone="success" icon="element Timer" />
                    <HsxStatCard title="售后待处理" :value="7" unit="单" trend="2 单超时" trend-tone="danger" tone="warning" icon="element Warning" />
                </HsxGrid>

                <div class="chart-demo-grid">
                    <HsxChartCard title="近 7 日成交趋势" subtitle="成交额与卖家实际到手金额" trend="本周 +18.6%" :option="lineChartOption" :height="286">
                        <template #extra><el-button link type="primary" @click="showCode('HsxChart')">查看代码</el-button></template>
                    </HsxChartCard>
                    <HsxChartCard title="品牌在售结构" subtitle="全国公共货盘实时设备数" :option="barChartOption" :height="286" />
                    <HsxChartCard title="设备等级分布" subtitle="A / B+ / B 级设备占比" :option="donutChartOption" :height="286" />
                </div>

                <section class="doc-card feedback-demo">
                    <div class="panel-title">
                        <div><h2>useFeedback / HsxNoticeBubble</h2><p>轻提示用于操作结果；重通知用于需要阅读的信息；确认框用于不可逆动作；气泡用于就地解释。</p></div>
                        <el-button link type="primary" @click="showCode('useFeedback')">查看代码</el-button>
                    </div>
                    <div class="feedback-demo__actions">
                        <HsxButton type="success" plain @click="feedback.success('设备保存成功')">轻提示 · 成功</HsxButton>
                        <HsxButton type="warning" plain @click="feedback.noticeWarning({ title: '行情发生变化', message: 'iPhone 16 Pro Max 建议售价已下调 ¥80，请重新确认定价。' })">重通知 · 行情变化</HsxButton>
                        <HsxButton type="danger" plain :action="runDangerConfirm">风险确认</HsxButton>
                        <HsxNoticeBubble v-model="noticeBubbleVisible" title="待处理提醒" placement="bottom-start">
                            <template #reference><HsxBadge :value="7"><el-button circle><HsxIcon name="element Bell" :size="18" /></el-button></HsxBadge></template>
                            <p class="bubble-copy">有 7 台设备等待审核，其中 2 台已接近处理时限。</p>
                            <template #actions><el-button size="small" @click="noticeBubbleVisible = false">稍后</el-button><el-button size="small" type="primary" @click="noticeBubbleVisible = false">去处理</el-button></template>
                        </HsxNoticeBubble>
                    </div>
                </section>
            </template>

            <template v-else-if="props.section === 'pro'">
                <section class="schema-capabilities">
                    <div>
                        <span class="section-kicker">SCHEMA ENGINE</span>
                        <h2>一份配置表达真实业务联动</h2>
                        <p>下面的新增/编辑弹窗已接入动态显示、动态必填、动态属性、字段权限和异步选项，不需要在页面重复写判断。</p>
                    </div>
                    <div class="capability-tags">
                        <el-tag>visible 动态显示</el-tag>
                        <el-tag type="success">required 动态必填</el-tag>
                        <el-tag type="warning">permission 字段权限</el-tag>
                        <el-tag type="info">optionsLoader 异步选项</el-tag>
                        <el-tag type="danger">components 业务组件</el-tag>
                    </div>
                </section>
                <section class="doc-card">
                    <div class="panel-title panel-title--table">
                        <div><h2>ProTable + ProDialogForm</h2><p>配置一次字段，同时得到搜索、列表、分页、新增和编辑。</p></div>
                        <div class="schema-actions">
                            <span>显示内部毛利字段</span>
                            <el-switch v-model="allowPricingField" />
                            <HsxButton data-testid="schema-create" type="primary" :action="openCreate">Schema 新增</HsxButton>
                            <el-button link type="primary" @click="showCode('ProTable')">查看代码</el-button>
                        </div>
                    </div>
                    <ProTable :columns="columns" :request="requestRows" row-key="id" column-storage-key="hsx-component-demo">
                        <template #toolbar>
                            <HsxImport :importer="runImport" size="small" />
                            <HsxExport :data="sourceRows" :columns="exportColumns" filename="设备货盘" size="small" />
                        </template>
                        <template #actions="{ row }">
                            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
                            <el-button link type="danger" @click="removeRow(row)">删除</el-button>
                        </template>
                    </ProTable>
                </section>
                <section class="doc-card table-tree-demo">
                    <div class="panel-title panel-title--table">
                        <div><h2>HsxTable · 父子级与级联选择</h2><p>扁平数据自动组树；父级勾选可联动子级。订单—设备场景则用 expand 插槽再嵌一张 HsxTable。</p></div>
                        <div class="panel-actions"><HsxTag text="基础表格" tone="primary" /><el-button link type="primary" @click="showCode('HsxTable')">查看代码</el-button></div>
                    </div>
                    <HsxTable :columns="treeColumns" :data="treeRows" auto-tree selection-mode="cascade" default-expand-all border>
                        <template #enabled="{ row }"><HsxSwitch :model-value="row.enabled" :debounce="0" @update:model-value="updateTreeEnabled(row, $event)" /></template>
                        <template #tree-actions="{ row }"><el-button link type="primary" @click="ElMessage.success(`查看：${row.name}`)">详情</el-button></template>
                    </HsxTable>
                    <div class="table-guide"><HsxTag text="independent" tone="neutral" /><span>独立选择</span><HsxTag text="children" tone="info" /><span>父级联动子级</span><HsxTag text="cascade" tone="success" /><span>父子双向联动</span><HsxTag text="leaf" tone="warning" /><span>只允许选择末级</span></div>
                </section>
                <section class="doc-card table-tree-demo">
                    <div class="panel-title panel-title--table">
                        <div><h2>HsxTreeTablePicker · 树表选择弹窗</h2><p>左侧组织/分类树，右侧 Schema 查询、远程表格、跨页选择和分页，适用于责任人、门店、主理人、商品等业务。</p></div>
                        <div class="panel-actions"><HsxTag text="组合组件" tone="success" /><el-button link type="primary" @click="showCode('HsxTreeTablePicker')">查看代码</el-button></div>
                    </div>
                    <div class="picker-demo__summary">
                        <span>当前已选：{{ pickerSelectedUsers.map((item) => item.name).join('、') || '暂无' }}</span>
                        <HsxButton type="primary" @click="pickerVisible = true">打开责任人选择器</HsxButton>
                    </div>
                </section>
            </template>

            <template v-else-if="props.section === 'business'">
                <section class="schema-capabilities business-intro">
                    <div>
                        <span class="section-kicker">COMPOSABLE BUSINESS COMPONENTS</span>
                        <h2>先复用基础组件，再组合成业务通用能力</h2>
                        <p>这一层不绑定具体接口。管理员、用户、主理人、商品等业务只需要提供字段映射、请求函数和动作配置。</p>
                    </div>
                    <div class="capability-tags">
                        <HsxTag text="PC / UniApp 同协议" tone="primary" />
                        <HsxTag text="属性事件插槽完整" tone="success" />
                        <HsxTag text="响应式与暗黑模式" tone="info" />
                    </div>
                </section>

                <section class="doc-card business-section">
                    <div class="panel-title">
                        <div><h2>HsxEntityPicker · 管理员 / 用户选择</h2><p>底层直接复用 HsxTreeTablePicker、ProForm、HsxTable 和 HsxPagination，业务只提供数据。</p></div>
                        <div class="panel-actions"><el-button link type="primary" @click="showCode('HsxEntityPicker')">查看代码</el-button></div>
                    </div>
                    <div class="business-picker-row">
                        <HsxEntityPicker
                            v-model="businessSelectedUsers"
                            v-model:visible="businessPickerVisible"
                            title="选择业务负责人"
                            :tree-data="pickerTreeData"
                            :columns="pickerColumns"
                            :query-schema="pickerQuerySchema"
                            :request="requestPickerUsers"
                            :page-size="5"
                            multiple
                        />
                        <HsxButton type="primary" @click="businessPickerVisible = true">选择负责人</HsxButton>
                    </div>
                </section>

                <section class="doc-card business-section">
                    <div class="panel-title">
                        <div><h2>HsxProductList · 通用商品列表</h2><p>商品卡片、响应式列数、列表模式、骨架、空态和操作插槽由组件统一处理。</p></div>
                        <div class="panel-actions">
                            <el-radio-group v-model="businessProductLayout" size="small"><el-radio-button value="grid">卡片</el-radio-button><el-radio-button value="list">列表</el-radio-button></el-radio-group>
                            <el-button link type="primary" @click="showCode('HsxProductList')">查看代码</el-button>
                        </div>
                    </div>
                    <HsxProductList :items="businessProducts" :layout="businessProductLayout" :actions="businessActions.slice(0, 3)" @item-click="ElMessage.success(`查看：${$event.title}`)" />
                </section>

                <HsxGrid :columns="{ xs: 1, lg: 2 }" :gap="16">
                    <section class="doc-card business-section">
                        <div class="panel-title"><div><h2>HsxDetail · Schema 详情</h2><p>阅读态、复制、脱敏、金额与状态语义。</p></div><el-button link type="primary" @click="showCode('HsxDetail')">查看代码</el-button></div>
                        <HsxDetail :data="businessDetailData" :schema="businessDetailSchema" :columns="3" />
                    </section>
                    <section class="doc-card business-section">
                        <div class="panel-title"><div><h2>HsxActionBar · 统一操作</h2><p>权限、确认、自动动作与更多菜单采用一份配置。</p></div><el-button link type="primary" @click="showCode('HsxActionBar')">查看代码</el-button></div>
                        <div class="business-action-preview">
                            <HsxActionBar :actions="businessActions" :context="businessDetailData" :max-visible="3" bordered @action="ElMessage.info(`执行：${$event.label}`)" />
                        </div>
                    </section>
                </HsxGrid>
            </template>

            <template v-else-if="props.section === 'mobile'">
                <div class="mobile-layout">
                    <section class="doc-card mobile-copy">
                        <span class="section-kicker">UNIAPP / UVIEW-PLUS</span>
                        <h2>一套组件覆盖 H5、小程序和 App</h2>
                        <p>这里展示的是用户移动端组件，不依赖 site 管理端。手机预览页包含 z-paging 真实分页、卡片、上传、Schema 表单和底部弹层。</p>
                        <div class="mobile-list">
                            <div><b>HsxPageList</b><span>z-paging 刷新、分页、空态、错误重试</span></div>
                            <div><b>HsxPopup</b><span>底部 / 中间 / 全屏模式</span></div>
                            <div><b>HsxForm</b><span>移动端 JSON Schema 表单</span></div>
                            <div><b>HsxButton</b><span>防连点与自动 Loading</span></div>
                            <div><b>HsxUpload</b><span>多文件、进度、预览、失败重试</span></div>
                        </div>
                        <el-button type="primary" @click="openMobilePreview">在新窗口打开移动端演示</el-button>
                        <p class="route-hint">{{ mobilePreviewUrl }}</p>
                    </section>
                    <div class="phone-shell">
                        <div class="phone-shell__speaker"></div>
                        <iframe :src="mobilePreviewUrl" title="HSX 移动端组件预览" />
                    </div>
                </div>
            </template>

            <template v-else>
                <section class="doc-card">
                    <div class="panel-title"><div><h2>核心 API 速查</h2><p>详细类型和完整示例见应用目录中的 COMPONENT_API.md。</p></div><el-tag type="success">持续更新</el-tag></div>
                    <el-table :data="apiRows" stripe>
                        <el-table-column prop="component" label="组件" min-width="170" />
                        <el-table-column prop="input" label="主要入参 Props" min-width="260" />
                        <el-table-column prop="output" label="主要出参 Events" min-width="230" />
                        <el-table-column prop="value" label="解决的问题" min-width="280" />
                    </el-table>
                </section>
                <section class="doc-card code-card">
                    <div class="panel-title"><div><h2>调用示例</h2><p>业务插件从公开出口导入，不访问组件内部目录。</p></div></div>
                    <pre><code>{{ codeExample }}</code></pre>
                </section>
            </template>
        </main>

        <HsxDrawer v-model="drawerVisible" title="设备详情" size="520px" show-footer @confirm="drawerVisible = false">
            <template #default="{ close }">
                <HsxTitle title="iPhone 16 Pro Max 256GB" subtitle="A 级 · 卖家到手价 ¥6,000" size="card">
                    <template #extra><HsxTag text="审核中" tone="warning" dot /></template>
                </HsxTitle>
                <el-descriptions :column="1" border class="drawer-detail">
                    <el-descriptions-item label="所属主理人">杭州 · 张三</el-descriptions-item>
                    <el-descriptions-item label="卖家信用">描述准确率 98.6% · 退货率 1.2%</el-descriptions-item>
                    <el-descriptions-item label="销售方式">全国公共货盘 + 主理人私域</el-descriptions-item>
                </el-descriptions>
                <el-button class="drawer-close-action" @click="close">通过插槽参数关闭</el-button>
            </template>
            <template #footer="{ close, confirm }"><el-button @click="close">取消</el-button><el-button type="primary" @click="confirm">确认并关闭</el-button></template>
        </HsxDrawer>

        <HsxDialog v-model="dialogVisible" title="可拖拽、可全屏弹窗" :show-footer="true" @confirm="dialogVisible = false">
            <el-alert title="这是真实交互，不是静态 PPT" type="success" :closable="false" show-icon />
            <p class="dialog-copy">拖动标题区域可以移动弹窗，右上角按钮可切换全屏。业务内容通过默认插槽进入，底部操作区也能完全自定义。</p>
        </HsxDialog>

        <HsxTreeTablePicker
            v-model:visible="pickerVisible"
            v-model="pickerSelectedUsers"
            v-model:active-tab="pickerActiveTab"
            title="选择整改责任人"
            :tabs="[{ label: '组织架构', value: 'organization' }, { label: '本部门', value: 'department' }]"
            :tree-data="pickerTreeData"
            :columns="pickerColumns"
            :query-schema="pickerQuerySchema"
            :request="requestPickerUsers"
            :page-size="5"
            :page-sizes="[5, 10, 20]"
            tree-node-key="id"
            tree-default-expand-all
            multiple
            @confirm="confirmPicker"
        />

        <HsxDialog v-model="codeVisible" :title="`${activeCodeName} 使用代码`" width="760px" :show-footer="false">
            <div class="code-dialog__toolbar">
                <span>从公开入口 `@/addon/hsx_components` 引入</span>
                <HsxButton type="primary" plain size="small" :action="copyActiveCode">
                    <HsxIcon name="element CopyDocument" :size="15" />
                    复制代码
                </HsxButton>
            </div>
            <pre class="code-dialog__pre"><code>{{ activeCode }}</code></pre>
        </HsxDialog>

        <ProDialogForm
            v-model="formVisible"
            v-model:form-data="editData"
            title="设备信息"
            :schema="formSchema"
            :submit="submitForm"
            :permission-checker="checkDemoPermission"
        />
    </div>
</template>

<style scoped>
.component-center { min-height: 100%; padding: 20px; color: var(--hsx-text-primary); background: var(--hsx-bg-page); transition: color .25s ease, background .25s ease; }
.hero { position: relative; display: flex; min-height: 248px; overflow: hidden; align-items: center; justify-content: space-between; padding: 36px 48px; border: 1px solid rgba(118, 174, 255, .22); border-radius: 20px; color: #fff; background-color: #071633; background-position: center; background-size: cover; box-shadow: 0 22px 64px rgba(9, 37, 91, .28); }
.hero::after { position: absolute; right: -120px; bottom: -210px; width: 450px; height: 450px; border: 70px solid rgba(255,255,255,.08); border-radius: 50%; content: ''; }
.hero__content { position: relative; z-index: 2; max-width: 690px; }
.hero__eyebrow { margin-bottom: 12px; color: #93c5fd; font-size: 12px; font-weight: 700; letter-spacing: 2.2px; }
.hero h1 { margin: 0 0 12px; font-size: 34px; letter-spacing: -.5px; }
.hero p { max-width: 650px; margin: 0; color: rgba(255,255,255,.82); font-size: 15px; line-height: 1.8; }
.hero__meta { display: flex; align-items: center; gap: 10px; margin-top: 22px; color: #dbeafe; font-size: 13px; }
.hero__theme { display: inline-flex; align-items: center; gap: 9px; margin-top: 18px; padding: 7px 10px; border: 1px solid rgba(255,255,255,.2); border-radius: 999px; color: #e6f0ff; background: rgba(5, 15, 38, .38); font-size: 12px; backdrop-filter: blur(12px); }
.version { padding: 5px 11px; border: 1px solid rgba(255,255,255,.3); border-radius: 99px; color: #fff; background: rgba(255,255,255,.12); font-weight: 700; }
.hero__visual { position: relative; z-index: 2; width: 280px; height: 155px; margin-right: 16px; }
.visual-card { position: absolute; display: flex; width: 210px; height: 112px; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,.28); border-radius: 15px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; box-shadow: 0 20px 50px rgba(5, 15, 45, .25); }
.visual-card--back { top: 0; right: 0; color: #bfdbfe; background: rgba(15, 23, 42, .42); transform: rotate(7deg); }
.visual-card--front { bottom: 0; left: 0; color: #172554; background: rgba(255,255,255,.95); font-weight: 700; transform: rotate(-4deg); }
.docs-content { margin-top: 18px; }
.group-grid, .demo-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.group-card, .doc-card { border: 1px solid var(--hsx-border-color); border-radius: 16px; background: var(--hsx-bg-surface); box-shadow: var(--hsx-shadow-card); transition: border-color .22s ease, box-shadow .22s ease, transform .22s ease; }
.group-card { position: relative; overflow: hidden; padding: 22px; }
.group-card:hover, .demo-panel:hover { border-color: var(--el-color-primary-light-5); box-shadow: var(--hsx-shadow-floating); transform: translateY(-2px); }
.group-card::before { position: absolute; top: 0; right: 0; left: 0; height: 4px; content: ''; background: #2563eb; }
.group-card--violet::before { background: #7c3aed; }
.group-card--orange::before { background: #f97316; }
.group-card--green::before { background: #10b981; }
.group-card__top { display: flex; align-items: center; justify-content: space-between; }
.group-card h3, .doc-card h2 { margin: 0; }
.group-card strong { color: #2563eb; font-size: 32px; line-height: 1; }
.group-card--violet strong { color: #7c3aed; }
.group-card--orange strong { color: #f97316; }
.group-card--green strong { color: #10b981; }
.business-intro { margin-bottom: 16px; }
.business-section { min-width: 0; }
.business-picker-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px; }
.business-action-preview { display: flex; min-height: 180px; align-items: center; justify-content: center; padding: 24px; border-radius: 16px; background: radial-gradient(circle at 80% 10%, color-mix(in srgb, var(--hsx-color-primary) 14%, transparent), transparent 40%), var(--hsx-bg-muted); }
@media (max-width: 720px) { .business-picker-row { grid-template-columns: 1fr; } }
.group-card p, .doc-card p { color: var(--hsx-text-regular); line-height: 1.7; }
.tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.doc-card { padding: 24px; }
.architecture { display: grid; grid-template-columns: 1fr 1fr; gap: 44px; align-items: center; margin-top: 16px; background: linear-gradient(135deg, var(--hsx-bg-surface), var(--hsx-bg-muted)); }
.section-kicker { color: #2563eb; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; }
.architecture h2, .mobile-copy h2 { margin-top: 8px; font-size: 24px; }
.architecture__flow { display: flex; align-items: center; gap: 10px; }
.architecture__flow > div { display: flex; min-height: 82px; flex: 1; flex-direction: column; align-items: center; justify-content: center; padding: 8px; border: 1px solid var(--hsx-border-color); border-radius: 12px; text-align: center; background: var(--hsx-bg-elevated); }
.architecture__flow small { margin-bottom: 7px; color: #98a2b3; }
.architecture__primary { color: #fff !important; border-color: #2563eb !important; background: #2563eb !important; }
.architecture__primary small { color: #bfdbfe; }
.design-contract { margin-top: 16px; }
.design-contract .hsx-grid { margin-top: 20px; }
.design-contract .hsx-grid > div { padding: 16px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-md); background: var(--hsx-bg-muted); }
.design-contract b, .design-contract span { display: block; }
.design-contract b { color: var(--hsx-text-primary); font-size: 14px; }
.design-contract span { margin-top: 5px; color: var(--hsx-text-secondary); font-size: 12px; line-height: 18px; }
.demo-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.demo-panel { min-height: 180px; }
.demo-panel--wide { grid-column: 1 / -1; }
.panel-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
.panel-title h2 { font-size: 19px; }
.panel-title p { margin: 6px 0 0; font-size: 13px; }
.panel-title--table { align-items: center; }
.panel-actions { display: flex; flex: none; align-items: center; gap: 8px; }
.schema-capabilities { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(360px, .75fr); gap: 30px; align-items: center; margin-bottom: 16px; padding: 24px; border: 1px solid var(--hsx-border-color); border-radius: 16px; background: linear-gradient(135deg, var(--hsx-bg-surface), var(--hsx-color-primary-soft)); box-shadow: var(--hsx-shadow-card); }
.visual-intro { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr); gap: 30px; align-items: center; margin-bottom: 16px; padding: 24px; overflow: hidden; border: 1px solid var(--hsx-border-color); border-radius: 16px; background: radial-gradient(circle at 85% 18%, color-mix(in srgb, var(--hsx-color-primary) 16%, transparent), transparent 34%), linear-gradient(135deg, var(--hsx-bg-surface), var(--hsx-bg-muted)); box-shadow: var(--hsx-shadow-card); }
.visual-intro h2 { margin: 6px 0 0; font-size: 22px; }
.visual-intro p { max-width: 760px; margin: 8px 0 0; color: var(--hsx-text-regular); line-height: 1.7; }
.stat-demo-grid { margin-bottom: 16px; }
.layout-playground { margin-bottom: 16px; }
.layout-playground__toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; margin-bottom: 16px; color: var(--hsx-text-regular); font-size: 13px; }
.layout-card__eyebrow, .layout-card__footer { display: block; color: inherit; font-size: 11px; letter-spacing: .08em; opacity: .72; }
.layout-playground .hsx-grid-item strong { display: block; margin-top: 8px; font-size: 15px; }
.layout-playground .hsx-grid-item small { display: block; margin-top: 6px; color: inherit; line-height: 1.5; opacity: .68; }
.layout-card__footer { text-align: center; letter-spacing: 0; }
.chart-demo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.chart-demo-grid > :first-child { grid-column: 1 / -1; }
.feedback-demo { margin-top: 16px; }
.feedback-demo__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.bubble-copy { margin: 0; }
.schema-capabilities h2 { margin: 6px 0 0; font-size: 22px; }
.schema-capabilities p { margin: 8px 0 0; color: var(--hsx-text-regular); line-height: 1.7; }
.capability-tags { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; }
.schema-actions { display: flex; align-items: center; gap: 10px; color: var(--hsx-text-regular); font-size: 13px; }
.demo-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-stack { display: grid; gap: 12px; }
.tag-demo-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; }
.timeline-demo { max-width: 920px; }
.table-tree-demo { margin-top: 16px; }
.table-guide { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 16px; color: var(--hsx-text-secondary); font-size: 12px; }
.table-guide span { margin-right: 8px; }
.picker-demo__summary { display: flex; min-height: 64px; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 18px; border: 1px dashed var(--hsx-border-color); border-radius: 12px; color: var(--hsx-text-regular); background: var(--hsx-bg-muted); }
.drawer-detail { margin-top: 20px; }
.drawer-close-action { margin-top: 16px; }
.foundation-demo { display: grid; gap: 20px; }
.foundation-demo > .panel-title { margin-bottom: 0; }
.foundation-grid { margin-top: -4px; }
.metric-card { padding: 16px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-md); background: linear-gradient(145deg, var(--hsx-bg-surface), var(--hsx-bg-muted)); }
.metric-card__content { display: grid; min-width: 0; gap: 4px; }
.metric-card strong { color: var(--hsx-color-primary); font-size: 26px; font-variant-numeric: tabular-nums; }
.foundation-overflow { padding: 4px 16px; margin: 0 -16px; }
.foundation-list-item { display: grid; min-width: 0; gap: 2px; }
.demo-value { margin: 12px 0 0 !important; color: var(--hsx-text-secondary) !important; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
.icon-demo-row { display: grid; grid-template-columns: repeat(5, minmax(58px, 1fr)); gap: 10px; }
.icon-demo-row > span { display: flex; min-height: 72px; flex-direction: column; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--hsx-border-color); border-radius: 12px; color: var(--el-color-primary); background: var(--hsx-bg-muted); }
.icon-demo-row small { color: var(--hsx-text-secondary); }
.mobile-layout { display: grid; grid-template-columns: minmax(0, 1fr) 390px; gap: 36px; align-items: center; padding: 16px 26px 34px; }
.mobile-copy { padding: 34px; background: linear-gradient(145deg, var(--hsx-bg-surface), var(--hsx-bg-muted)); }
.mobile-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 24px 0; }
.mobile-list > div { padding: 15px; border: 1px solid var(--hsx-border-color); border-radius: 10px; background: var(--hsx-bg-elevated); }
.mobile-list b, .mobile-list span { display: block; }
.mobile-list span { margin-top: 5px; color: #87909d; font-size: 12px; }
.route-hint { overflow: hidden; color: #98a2b3 !important; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.phone-shell { position: relative; width: 340px; height: 650px; padding: 12px; border: 8px solid #17202f; border-radius: 42px; background: #17202f; box-shadow: 0 28px 70px rgba(17, 24, 39, .25); }
.phone-shell__speaker { position: absolute; z-index: 2; top: 16px; left: 50%; width: 90px; height: 22px; border-radius: 0 0 15px 15px; background: #17202f; transform: translateX(-50%); }
.phone-shell iframe { width: 100%; height: 100%; border: 0; border-radius: 28px; background: #f5f6f8; }
.code-card { margin-top: 16px; }
.code-card pre { overflow: auto; margin: 0; padding: 22px; border-radius: 10px; color: var(--hsx-code-color); background: var(--hsx-code-bg); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; line-height: 1.7; }
.code-dialog__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 12px; color: var(--hsx-text-secondary); font-size: 12px; }
.code-dialog__pre { max-height: 58vh; overflow: auto; margin: 0; padding: 22px; border: 1px solid var(--hsx-border-color); border-radius: 12px; color: var(--hsx-code-color); background: var(--hsx-code-bg); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; line-height: 1.7; }
.dialog-copy { margin: 18px 0 4px; }
@media (max-width: 1100px) { .group-grid { grid-template-columns: 1fr; } .architecture, .schema-capabilities, .visual-intro { grid-template-columns: 1fr; } .capability-tags { justify-content: flex-start; } .mobile-layout { grid-template-columns: 1fr; } .phone-shell { margin: auto; } .hero__visual { display: none; } }
@media (max-width: 760px) { .component-center { padding: 10px; } .hero { padding: 28px 22px; } .hero h1 { font-size: 28px; } .hero__meta { flex-wrap: wrap; } .demo-grid, .chart-demo-grid { grid-template-columns: 1fr; } .chart-demo-grid > :first-child { grid-column: auto; } .architecture__flow { flex-direction: column; } .architecture__flow > div { width: 100%; } .mobile-list { grid-template-columns: 1fr; } }
</style>
