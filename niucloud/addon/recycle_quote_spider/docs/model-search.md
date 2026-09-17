# 报价型号搜索

## 使用入口

后台页面装修的「爬虫报价组件」分组新增「报价搜索」。拖入页面后可设置搜索提示、报价源、按钮颜色和背景颜色，保存并发布装修页面即可使用。

- 搜索页：`/addon/recycle_quote_spider/pages/price/search`
- 接口：`GET /api/recycle_quote_spider/search`
- 参数：`keyword`、`source_id`（0 为全部）、`page`、`limit`（最多 30，默认 12）。
- 页面与已有报价详情保持相同的登录要求。

## 查询口径

搜索报价单中的型号、品牌及型号关键词，而不只是报价单标题。忽略型号中的空格和大小写；带数字的苹果型号允许省略或输入「苹果 / iPhone」前缀。

同一型号属于不同报价单时分别返回，结果按型号、报价单、行排序。每条显示对应规格、公开的最终价格、报价日期和备注。点击「查看报价单」会自动选中该型号，可在详情页清除型号筛选继续浏览。

只查询当前站点、启用报价源、可见分类及祖先分类、可见报价单和可见报价行。手工导入且 `source_id=0` 的结构化报价也支持检索。纯图片报价没有型号行，不参与型号搜索。

价格只读取 `final_prices`，不下发源价格、手工覆盖配置或完整原始采集数据。日期使用该报价行的更新时间，缺失时使用创建时间，不伪造为搜索当天。

## 最近搜索

本期保存在客户端本机，按站点、会员和报价源隔离，最多 12 条。记录关键词、上次命中数量和搜索时间，支持清空。重新点击记录时查询数据库最新价格，不保存旧报价结果作为当前价格。

这不是后台全站搜索行为统计，也不支持跨设备同步。本次不新增数据库表、字段或索引，不回填历史数据。

## 更新范围

业务代码、装修配置、接口和测试均在 `recycle_quote_spider` 插件内。前端同时更新运行目录和插件安装目录中的镜像文件。

本地已同步两处现有生成机制的组装文件：

1. `uni-app/src/pages.json`：注册搜索页；安装源配置在插件的 `package/uni-app-pages.php`。
2. `uni-app/src/addon/components/diy/group/index.vue`：注册装修组件；安装时由框架扫描插件的 `components/diy` 目录生成。

没有修改框架生成器、公共请求模块、鉴权中间件或数据库结构。部署时需要带上插件后台和两端前端文件，并重新编译前端；仅上传后端 PHP 不会让旧小程序出现新页面。

## 回归

在项目根目录运行：

```sh
node niucloud/addon/recycle_quote_spider/tests/quote_search_frontend_test.cjs
QUOTE_SEARCH_ROLLBACK_TEST=1 php niucloud/addon/recycle_quote_spider/tests/quote_search_database_test.php
node niucloud/addon/recycle_quote_spider/tests/quote_search_browser_test.cjs
```

数据库测试仅允许本机 MySQL、未占用测试站点和 InnoDB 表，写入均在事务中回滚，不触发爬虫同步或外部通知。浏览器测试需要可用的 Playwright 和 Chrome，以及已启动的 H5 服务；可用 `QUOTE_SEARCH_H5_URL` 指定服务地址，默认 `http://127.0.0.1:5187/wap`。该测试使用隔离 API 样本，覆盖搜索、跨报价单结果、分页、历史记录、空结果、重试、跳转及多屏宽布局，不向实际业务接口写入。
