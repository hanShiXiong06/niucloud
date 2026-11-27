const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../../../../')
const addon = path.join(root, 'niucloud/addon/hsx_recycle')
const source = path.join(root, 'site-uniapp/src/addon/hsx_recycle')
const jsonc = require(path.join(root, 'site-uniapp/node_modules/jsonc-parser'))
const read = file => fs.readFileSync(file, 'utf8')
let assertions = 0
const check = (condition, message) => { assertions++; assert.ok(condition, message) }

const errors = []
const pages = jsonc.parse(read(path.join(root, 'site-uniapp/src/pages.json')), errors, { allowTrailingComma: true })
check(errors.length === 0, '移动端pages.json可解析')
const packages = (pages.subPackages || pages.subpackages || []).filter(item => item.root === 'addon/hsx_recycle')
check(packages.length === 1, '回收管理分包唯一注册')
const quotePages = packages[0].pages.filter(item => item.path === 'pages/quote/index')
check(quotePages.length === 1, '报价页唯一注册')
check(quotePages[0].needLogin === true, '报价管理页必须登录')
check(quotePages[0].style.navigationBarTitleText === '报价单', '报价页标题正确')
const fragment = JSON.parse(read(path.join(addon, 'site-uniapp/pages.json')))
check(fragment.root === 'addon/hsx_recycle', '插件移动安装片段分包正确')
check(fragment.pages.length === 1 && fragment.pages[0].path === 'pages/quote/index' && fragment.pages[0].needLogin === true, '插件安装片段包含登录保护报价页')

const menu = read(path.join(addon, 'app/dict/adminapp/app.php'))
const entry = menu.match(/\[\s*'name'\s*=>\s*'报价单'([\s\S]*?)\]/)?.[0] || ''
check(entry.includes("'key' => 'hsx_recycle_quote'"), '移动入口具有独立shortcut key')
check(entry.includes("'menu_key' => 'recycle_category_list'"), '移动入口沿用已有查看权限')
check(entry.includes("'page' => '/addon/hsx_recycle/pages/quote/index'"), '移动入口指向报价页')
const home = read(path.join(source, 'pages/index.vue'))
check(home.includes('getApp()') && home.includes('hsx_recycle_quote') && home.includes('visibleModules'), '工作台卡片消费框架现有入口权限')

for (const file of ['pages/index.vue', 'pages/quote/index.vue', 'api/quote.ts', 'utils/quote.ts', 'utils/preview.ts']) {
    const normalize = value => value.replace(/\r\n/g, '\n').trimEnd()
    check(normalize(read(path.join(source, file))) === normalize(read(path.join(addon, 'site-uniapp', file))), `移动源码与插件镜像一致(忽略换行符/文件末尾空行): ${file}`)
}

const api = read(path.join(source, 'api/quote.ts'))
check(api.includes("request.get('recycle/recycle_category/quote-options'"), '能力从管理API获取')
check(api.includes('request.put(`recycle/recycle_category/${categoryId}/quote-image`'), '仅调用报价图更新接口')
check(api.includes('expected_images: string'), '更新携带旧图片做并发保护')
check(api.includes('recycle/recycle_category/${categoryId}/quote_history'), '更新记录使用已有历史接口')
const page = read(path.join(source, 'pages/quote/index.vue'))
check(page.includes("from '@/app/api/system'") && page.includes('uploadImage({ filePath: local, name: \'file\', timeout: 120000 })'), '使用框架上传路由和请求超时，不自建存储')
check(page.includes("sizeType: ['original']") && page.includes('withQuoteUploadTimeout'), '长图使用原图并有外层超时兜底')
check(page.includes('selected.value.site_id === siteId.value') && page.includes('canEdit.value'), '前端受能力和本站归属限制')
check(page.includes('确认更新报价') && page.includes('新报价 · 尚未发布'), '上传草稿与发布确认分离')
check(page.includes('不改变分类显示和查看权限'), '不将报价图更新误说成权限调整')

const adminRoutes = read(path.join(addon, 'app/adminapi/route/route.php'))
check(adminRoutes.includes("Route::get('recycle_category/quote-options'"), '能力仅注册在管理API')
check(adminRoutes.includes("Route::put('recycle_category/:id/quote-image'"), '更新仅注册在管理API')
const consumerRoutes = read(path.join(addon, 'app/api/route/route.php'))
check(!consumerRoutes.includes('recycle_category/quote-options') && !consumerRoutes.includes('quote-image'), '没有向普通客户暴露上传更新路由')
const service = read(path.join(addon, 'app/service/admin/category/RecycleCategoryService.php'))
check(service.includes('$this->assertQuoteAccess(false)') && service.includes('$this->assertQuoteAccess(true)'), '后端分别校验查看和编辑权限')
check(service.includes('expected_images') && service.includes('snapshot('), '后端并发检查及现有历史快照在位')
console.log(`PASS mobile quote registration/integration: ${assertions} assertions`)
