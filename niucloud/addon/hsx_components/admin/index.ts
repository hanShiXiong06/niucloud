import type { App, Component, Plugin } from 'vue'
import HsxButton from './components/HsxButton/index.vue'
import HsxBlockRenderer from './components/HsxBlockRenderer/index.vue'
import HsxBadge from './components/HsxBadge/index.vue'
import HsxActionBar from './components/HsxActionBar/index.vue'
import HsxCheckbox from './components/HsxCheckbox/index.vue'
import HsxCascader from './components/HsxCascader/index.vue'
import HsxChart from './components/HsxChart/index.vue'
import HsxChartCard from './components/HsxChartCard/index.vue'
import HsxColumnSetting from './components/HsxColumnSetting/index.vue'
import HsxDatePicker from './components/HsxDatePicker/index.vue'
import HsxDateRange from './components/HsxDateRange/index.vue'
import HsxDialog from './components/HsxDialog/index.vue'
import HsxDetail from './components/HsxDetail/index.vue'
import HsxDrawer from './components/HsxDrawer/index.vue'
import HsxNotice from './components/HsxNotice/index.vue'
import HsxFold from './components/HsxFold/index.vue'
import HsxExport from './components/HsxExport/index.vue'
import HsxEntityPicker from './components/HsxEntityPicker/index.vue'
import HsxIcon from './components/HsxIcon/index.vue'
import HsxImport from './components/HsxImport/index.vue'
import HsxInput from './components/HsxInput/index.vue'
import HsxGrid from './components/HsxGrid/index.vue'
import HsxGridItem from './components/HsxGridItem/index.vue'
import HsxList from './components/HsxList/index.vue'
import HsxMarkdownRenderer from './components/HsxMarkdownRenderer/index.vue'
import HsxMotion from './components/HsxMotion/index.vue'
import HsxNoticeBubble from './components/HsxNoticeBubble/index.vue'
import HsxOverflow from './components/HsxOverflow/index.vue'
import HsxPagination from './components/HsxPagination/index.vue'
import HsxPage from './components/HsxPage/index.vue'
import HsxProgress from './components/HsxProgress/index.vue'
import HsxProductCard from './components/HsxProductCard/index.vue'
import HsxProductList from './components/HsxProductList/index.vue'
import HsxSearchInput from './components/HsxSearchInput/index.vue'
import HsxSelect from './components/HsxSelect/index.vue'
import HsxStack from './components/HsxStack/index'
import HsxStatCard from './components/HsxStatCard/index.vue'
import HsxSwitch from './components/HsxSwitch/index.vue'
import HsxTable from './components/HsxTable/index.vue'
import HsxTag from './components/HsxTag/index.vue'
import HsxText from './components/HsxText/index.vue'
import HsxTimeline from './components/HsxTimeline/index.vue'
import HsxTitle from './components/HsxTitle/index.vue'
import HsxTreeTablePicker from './components/HsxTreeTablePicker/index.vue'
import HsxUpload from './components/HsxUpload/index.vue'
import HsxVoucherUpload from './components/HsxVoucherUpload/index.vue'
import ProDialogForm from './components/ProDialogForm/index.vue'
import ProForm from './components/ProForm/index.vue'
import ProTable from './components/ProTable/index.vue'
import QueryForm from './components/QueryForm/index.vue'
import { copyDirective, createPermissionDirective, debounceDirective } from './directives'
import { HSX_FORM_COMPONENTS, HSX_PERMISSION_CHECKER, type PermissionChecker } from './tokens'
import './styles/theme.scss'

export interface HsxComponentsOptions {
    permissionChecker?: PermissionChecker
    formComponents?: Record<string, Component>
}

const components = [
    HsxActionBar,
    HsxBadge,
    HsxBlockRenderer,
    HsxButton,
    HsxCheckbox,
    HsxCascader,
    HsxChart,
    HsxChartCard,
    HsxColumnSetting,
    HsxDatePicker,
    HsxDateRange,
    HsxDialog,
    HsxDetail,
    HsxDrawer,
    HsxNotice,
    HsxFold,
    HsxExport,
    HsxEntityPicker,
    HsxIcon,
    HsxImport,
    HsxInput,
    HsxGrid,
    HsxGridItem,
    HsxList,
    HsxMarkdownRenderer,
    HsxMotion,
    HsxNoticeBubble,
    HsxOverflow,
    HsxPagination,
    HsxPage,
    HsxProgress,
    HsxProductCard,
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
    HsxVoucherUpload,
    ProForm,
    ProTable,
    ProDialogForm,
    QueryForm
]

export const HsxComponents: Plugin = {
    install(app: App, options: HsxComponentsOptions = {}) {
        components.forEach((component) => app.component(component.name!, component))
        app.provide(HSX_PERMISSION_CHECKER, options.permissionChecker || (() => true))
        app.provide(HSX_FORM_COMPONENTS, options.formComponents || {})
        app.directive('hsx-copy', copyDirective)
        app.directive('hsx-debounce', debounceDirective)
        app.directive('hsx-permission', createPermissionDirective(options.permissionChecker))
    }
}

export {
    HsxActionBar,
    HsxBadge,
    HsxBlockRenderer,
    HsxButton,
    HsxCheckbox,
    HsxCascader,
    HsxChart,
    HsxChartCard,
    HsxColumnSetting,
    HsxDatePicker,
    HsxDateRange,
    HsxDialog,
    HsxDetail,
    HsxDrawer,
    HsxNotice,
    HsxFold,
    HsxExport,
    HsxEntityPicker,
    HsxIcon,
    HsxImport,
    HsxInput,
    HsxGrid,
    HsxGridItem,
    HsxList,
    HsxMarkdownRenderer,
    HsxMotion,
    HsxNoticeBubble,
    HsxOverflow,
    HsxPagination,
    HsxPage,
    HsxProgress,
    HsxProductCard,
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
    HsxVoucherUpload,
    ProDialogForm,
    ProForm,
    ProTable,
    QueryForm,
    HSX_PERMISSION_CHECKER,
    HSX_FORM_COMPONENTS
}
export * from './directives'
export * from './charts'
export * from './cache'
export * from './design-tokens'
export * from './hooks'
export * from './components/HsxGrid/types'
export * from './components/HsxTreeTablePicker/types'
export * from './tokens'
export * from './types'
export * from './utils'

export default HsxComponents
