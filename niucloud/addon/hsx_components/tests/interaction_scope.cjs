const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../../../..')
const shared = [
    'components/HsxNotice/index.vue', 'components/HsxFold/index.vue', 'components/HsxSearchPanel/index.vue',
    'components/HsxTitle/index.vue', 'components/HsxPage/index.vue', 'components/HsxDrawer/index.vue',
    'components/HsxDialog/index.vue', 'components/QueryForm/index.vue', 'core.ts', 'index.ts', 'hooks/useFeedback.ts',
    'hooks/index.ts', 'hooks/useSearchLayout.ts'
]
function walk(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const file = path.join(directory, entry.name)
        return entry.isDirectory() ? walk(file) : file.endsWith('.vue') ? [file] : []
    })
}
function relative(addon, folder) {
    const base = path.join(root, 'admin/src/addon', addon)
    return walk(path.join(base, folder)).map(file => path.relative(base, file))
}
module.exports = {
    hsx_components: shared,
    hsx_erp: [...relative('hsx_erp', 'views'), ...relative('hsx_erp', 'components')],
    hsx_recycle: [
        'components/FormDialog.vue', 'components/PageHeader.vue', 'components/PremiumTheme.vue', 'styles/premium-theme.scss',
        ...relative('hsx_recycle', 'views').filter(file => /^views\/(recycle_order|recycle_return_order|consignment_order|device_export|stat\/|stats\/(dashboard|staff_kpi|components\/SectionHeader)|order_config|check\/)/.test(file))
    ]
}
