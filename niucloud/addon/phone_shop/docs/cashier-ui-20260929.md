# 收银台交互调整

## 范围

仅修改 phone_shop 插件的收银台页面及商品查询，不改框架、数据库结构、历史商品资料或 ERP 开单规则。

## 行为

- 价格升降序使用当前买家的会员价，未选买家使用标价。后端先排序再分页，切换买家重新查询。
- 内存排序统一换算容量，无法识别的放最后。优先使用有效内存字段；字段存有非容量值时，仅从标题明确的容量单位补充，不猜测索引的含义。
- 首次查询前测量展示区，按列数和行数请求整屏数量；窗口变化和结算区折叠后自动调整，每页最多 120 件。
- 点击商品卡片信息区或加号选择，再次点击取消；标题、详情按钮打开详情，图片打开多图预览，不改变选择。
- 结算区折叠保留买家、已选商品和金额；列表分页不会清空已选商品。
- 详情使用左右分区，长质检报告仅在详情内部滚动。配色跟随系统主题。

## 验证

- `node niucloud/addon/phone_shop/admin/utils/cashier-layout.test.cjs`：布局计算、图片去重、选择切换、请求序号保护、SFC 和 SCSS 编译通过。
- 后端目录执行 PHP 8.0 的 `php addon/phone_shop/scripts/verify_cashier_sort.php --database`：38 组会员价/内存用例、分页稳定排序和 ORM 命名参数容量筛选通过，仅 SELECT，无业务写入。
- 本地登录站点测试了最新、价格双向、容量双向、128GB 筛选、选择取消、折叠展开、详情和多图预览。
- 1280x800、1024x768、768x1024 布局及暗色模式已人工检查，商品区无整页滚动溢出。
- 会员折扣与固定会员价使用合成数据校验；本地浏览器买家切换已验证，未提交实际开单或收款。

## 更新文件

- `admin/views/cashier/index.vue`
- `admin/utils/cashier-layout.ts`
- `app/adminapi/controller/cashier/Cashier.php`
- `app/service/admin/cashier/CashierService.php`
- `app/service/admin/cashier/CashierGoodsOrder.php`

前端源码已同步到根目录 `admin/src/addon/phone_shop/`，上线时前后端需一起更新。本次未构建、部署或提交代码。仓库现有 `.gitignore` 忽略插件源码目录，打包时注意包含上述后端文件；本次没有调整该规则。
