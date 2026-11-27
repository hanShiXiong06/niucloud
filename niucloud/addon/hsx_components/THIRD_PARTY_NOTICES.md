# 第三方开源软件声明

HSX 组件库在移动端图表适配层中包含以下开源软件：

## qiun-data-charts / uCharts

- 版本：`2.5.0-20230101`
- 来源：<https://gitee.com/uCharts/uCharts>
- 许可证：Apache License 2.0
- 用途：为 H5、App 与各类小程序提供 Canvas 图表渲染。
- 源码位置：`uni-app/vendor/qiun-data-charts`
- 完整许可证：`uni-app/vendor/qiun-data-charts/license.md`

HSX 适配层没有删除原作者版权声明。为兼容当前 UniApp 3.8.7 的 App renderjs 多入口构建限制，
H5 继续使用上游 renderjs，App 通过 `HsxUChartCanvas` 使用同一 uCharts 内核的逻辑层 Canvas 路径；并保留两份配置
隔离副本供 H5 视图层使用。图表算法和对外数据协议未修改。
业务插件应从 `@/addon/hsx_components` 使用
`HsxChart/HsxChartCard`，不要再次复制或修改第三方源码。

## VXE

当前版本尚未内置 VXE 依赖。若后续增加 `HsxExcelTable`，只允许使用 MIT 许可证的
`vxe-table/vxe-pc-ui` 开源部分；区域选取、单元格复制粘贴、查找替换和全键盘操作等
官方企业版插件不在本项目开源依赖范围内。
