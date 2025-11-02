# 上门家政服务系统

<div align="center">

![版本](https://img.shields.io/badge/版本-1.0.1-blue.svg)
![ThinkPHP](https://img.shields.io/badge/ThinkPHP-8.0-green.svg)
![Vue](https://img.shields.io/badge/Vue-3.0-brightgreen.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)
![UniApp](https://img.shields.io/badge/UniApp-Vue3-orange.svg)
![License](https://img.shields.io/badge/License-MIT-yellow.svg)

基于Niucloud框架开发的专业上门家政服务系统，支持预约上门、家电维修、家政服务、校园跑腿等多种业务场景。

[功能特性](#功能特性) • [快速开始](#快速开始) • [项目结构](#项目结构) • [开发文档](#开发文档) • [更新日志](#更新日志)

</div>

---

## 📖 项目简介

上门家政服务系统是一款基于Niucloud SAAS框架开发的综合性O2O服务平台，适用于家政服务、家电维修、上门保洁、校园跑腿等多种上门服务场景。系统采用前后端分离架构，支持多端应用（用户端、技师端、门店端），具备完整的订单流程、营销工具、数据统计等功能模块。

### 适用场景

- 🏠 **家政服务**: 保洁、月嫂、育儿嫂、护工等上门服务
- 🔧 **家电维修**: 空调、冰箱、洗衣机、电视等家电维修
- 🧹 **保洁清洗**: 日常保洁、深度清洁、开荒保洁等
- 🏃 **校园跑腿**: 快递代取、外卖代购、文件代送等跑腿服务
- 💅 **美容美甲**: 上门美容、美甲、化妆等服务
- 🐕 **宠物服务**: 宠物美容、宠物训练、上门喂养等

---

## ✨ 功能特性

### 核心功能

#### 📦 商品管理
- 服务商品分类管理（支持多级分类）
- 商品SKU规格管理
- 城市策略价格设置
- 会员等级折扣
- 商品上下架管理
- 虚拟销量设置
- 服务保障配置

#### 📝 订单管理
- 订单全生命周期管理
- 订单状态流转（待支付、待分配、服务中、已完成等）
- 多种派单方式（手动派单、自动派单、抢单）
- 订单跟进记录
- 订单标签管理
- 订单异常处理
- 订单自动关闭
- **跑腿业务支持**（多包裹、取件码、独立价格）

#### 💳 次卡系统
- 次卡套餐管理
- 按次数消费
- 有效期管理（年、月、永久）
- 次卡订单管理
- 次卡使用记录
- 次卡到期提醒

#### 🎫 优惠券系统
- 优惠券创建与发放
- 多种优惠类型（满减券、折扣券）
- 券适用商品设置
- 券领取与使用记录
- 定时发放
- 过期自动处理

#### 👷 技师管理
- 技师信息管理
- 技师认证审核
- 服务范围设置
- 接单设置（自动接单、抢单）
- 技师评价
- 技师排行榜
- 休息时间管理
- 技师账户与提现

#### 🏪 门店管理
- 门店入驻申请
- 门店信息管理
- 门店认证审核
- 服务范围设置
- 门店技师管理
- 门店订单管理
- 门店账户与提现
- 门店数据统计

#### ⭐ 评价系统
- 订单评价（服务评价、技师评价）
- 评价审核
- 评价展示管理
- 评价自动通过
- 好评率统计

#### 💰 退款系统
- 退款申请与审核
- 退款原因管理
- 退款流水记录
- 自动退款处理
- 退款统计

#### 📊 数据统计
- 订单统计（按日/周/月）
- 营收统计
- 技师业绩统计
- 门店业绩统计
- 商品销售统计
- 用户增长统计
- 小时级统计

#### 💬 帮助反馈
- 帮助中心
- 常见问题
- 意见反馈
- 反馈处理

#### 🔔 消息通知
- 订单消息通知
- 微信模板消息
- 短信通知
- 站内消息

#### 🎨 装修系统
- 首页装修（DIY）
- 自定义组件
- 页面模板
- 底部导航配置
- 海报生成

---

## 🛠 技术栈

### 后端技术
- **核心框架**: PHP 8.0+ / ThinkPHP 8.0
- **数据库**: MySQL 8.0+
- **缓存**: Redis
- **队列**: Workerman
- **架构**: RESTful API / 多租户SAAS架构

### 前端技术
- **管理后台**: Vue 3 + TypeScript + Vite + Element Plus + Pinia
- **移动端**: UniApp + Vue 3 + TypeScript + uView UI
- **多端支持**: H5 / 微信小程序 / 支付宝小程序 / 抖音小程序

### 开发工具
- **API文档**: 自动生成
- **代码生成器**: 内置代码生成工具
- **云编译**: 一键云打包
- **版本管理**: Git

---

## 📁 项目结构

```
home_service/
├── admin/                          # 管理后台前端（Vue3）
│   ├── views/                      # 页面视图
│   ├── components/                 # 公共组件
│   └── api/                        # API接口
│
├── uni-app/                        # 移动端（UniApp）
│   ├── user/                       # 用户端
│   │   ├── pages/                  # 页面
│   │   ├── components/             # 组件
│   │   └── api/                    # API
│   ├── technician/                 # 技师端
│   └── store/                      # 门店端
│
├── app/                            # 后端应用
│   ├── adminapi/                   # 管理后台API
│   │   ├── controller/             # 控制器
│   │   └── route/                  # 路由
│   │
│   ├── api/                        # 前台API
│   │   ├── controller/             # 控制器
│   │   └── route/                  # 路由
│   │
│   ├── model/                      # 数据模型
│   │   ├── order/                  # 订单模型
│   │   ├── goods/                  # 商品模型
│   │   ├── card/                   # 次卡模型
│   │   ├── coupon/                 # 优惠券模型
│   │   ├── technician/             # 技师模型
│   │   └── store/                  # 门店模型
│   │
│   ├── service/                    # 业务服务层
│   │   ├── admin/                  # 管理端服务
│   │   ├── api/                    # 前台服务
│   │   └── core/                   # 核心服务
│   │
│   ├── dict/                       # 字典配置
│   │   ├── order/                  # 订单字典
│   │   ├── goods/                  # 商品字典
│   │   ├── diy/                    # 装修配置
│   │   └── notice/                 # 通知配置
│   │
│   ├── job/                        # 定时任务
│   │   ├── order/                  # 订单任务
│   │   ├── card/                   # 次卡任务
│   │   └── coupon/                 # 优惠券任务
│   │
│   ├── listener/                   # 事件监听
│   │   ├── order/                  # 订单事件
│   │   ├── pay/                    # 支付事件
│   │   └── notice/                 # 通知事件
│   │
│   ├── validate/                   # 数据验证
│   └── lang/                       # 多语言
│
├── sql/                            # 数据库文件
│   ├── install.sql                 # 安装脚本
│   └── upgrade.sql                 # 升级脚本
│
├── docs/                           # 文档目录
│   └── ERRAND_CONFIG_EXAMPLE.md    # 跑腿配置示例
│
├── resource/                       # 资源文件
│
├── Addon.php                       # 插件主类
├── info.json                       # 插件信息
├── ERRAND_BUSINESS_GUIDE.md        # 跑腿业务指南
├── errand_business_migration.sql   # 跑腿业务迁移脚本
└── README.md                       # 本文档
```

---

## 🚀 快速开始

### 环境要求

- PHP >= 8.0
- MySQL >= 8.0
- Redis >= 5.0
- Composer >= 2.0
- Node.js >= 16.0
- Nginx / Apache

### 安装步骤

#### 1. 克隆项目

```bash
git clone [项目地址]
cd niucloud
```

#### 2. 安装Niucloud框架

参考Niucloud官方文档完成基础框架安装：
- 官网地址: https://www.niucloud.com
- 文档地址: https://www.niucloud.com/doc

#### 3. 安装插件

```bash
# 方式一：通过后台插件市场安装
登录管理后台 -> 应用中心 -> 搜索"上门家政" -> 安装

# 方式二：手动安装
将 home_service 目录复制到 niucloud/addon/ 目录下
登录管理后台 -> 应用中心 -> 本地安装 -> 选择 home_service
```

#### 4. 配置数据库

插件安装时会自动执行SQL脚本，创建所需数据表。

如需手动导入：
```bash
# 导入基础表结构
mysql -u用户名 -p密码 数据库名 < sql/install.sql

# 如需跑腿业务功能，额外执行
mysql -u用户名 -p密码 数据库名 < errand_business_migration.sql
```

#### 5. 配置Redis和队列

```bash
# config/queue.php 中配置Redis连接
'default' => 'redis',

# 启动队列服务
php think queue:work
```

#### 6. 编译前端

```bash
# 管理后台
cd admin
npm install
npm run build

# 移动端
cd uni-app
npm install
npm run build:h5
```

#### 7. 配置站点

登录管理后台 -> 站点设置 -> 创建站点 -> 选择"上门家政"应用

---

## 📚 开发文档

### 业务文档

- [跑腿业务集成指南](./ERRAND_BUSINESS_GUIDE.md) - 详细说明如何使用和开发跑腿业务功能
- [跑腿配置示例](./docs/ERRAND_CONFIG_EXAMPLE.md) - 跑腿业务配置示例

### 官方文档

- [Niucloud开发手册](https://www.niucloud.com/doc)
- [API接口文档](https://api.niucloud.com/apidoc.html)
- [二次开发视频教程](https://www.niucloud.com/doc)

### 常用功能开发

#### 添加新的服务类型

1. 在管理后台添加服务分类
2. 创建服务商品和SKU
3. 配置城市策略价格（可选）
4. 设置会员折扣（可选）
5. 上架商品

#### 自定义订单流程

```php
// app/service/core/order/CoreOrderStatusService.php
// 修改订单状态流转逻辑
```

#### 添加自定义通知

```php
// app/dict/notice/notice.php
// 添加通知类型配置

// app/listener/notice/
// 创建通知监听器
```

#### 扩展DIY组件

```vue
<!-- uni-app/user/components/ -->
<!-- 创建自定义组件 -->

<!-- app/dict/diy/components.php -->
<!-- 注册组件配置 -->
```

---

## 🔌 API接口

### 用户端主要接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/home_service/goods/list` | GET | 获取服务商品列表 |
| `/api/home_service/goods/detail` | GET | 获取商品详情 |
| `/api/home_service/order/calculate` | POST | 订单金额计算 |
| `/api/home_service/order/create` | POST | 创建订单 |
| `/api/home_service/order/list` | GET | 我的订单列表 |
| `/api/home_service/order/detail` | GET | 订单详情 |
| `/api/home_service/order/cancel` | POST | 取消订单 |
| `/api/home_service/order/refund` | POST | 申请退款 |
| `/api/home_service/coupon/list` | GET | 优惠券列表 |
| `/api/home_service/card/list` | GET | 次卡列表 |

### 技师端主要接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/home_service/technician/order/list` | GET | 技师订单列表 |
| `/api/home_service/technician/order/grab` | POST | 抢单 |
| `/api/home_service/technician/order/accept` | POST | 接受订单 |
| `/api/home_service/technician/order/refuse` | POST | 拒绝订单 |
| `/api/home_service/technician/order/start` | POST | 开始服务 |
| `/api/home_service/technician/order/finish` | POST | 完成服务 |

### 门店端主要接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/home_service/store/order/list` | GET | 门店订单列表 |
| `/api/home_service/store/order/dispatch` | POST | 派单给技师 |
| `/api/home_service/store/technician/list` | GET | 门店技师列表 |
| `/api/home_service/store/stat` | GET | 门店统计数据 |

完整API文档请参考：[API接口文档](https://api.niucloud.com/apidoc.html)

---

## 🗄️ 数据库设计

### 主要数据表

| 表名 | 说明 |
|------|------|
| `home_service_goods` | 服务商品表 |
| `home_service_goods_sku` | 商品SKU表 |
| `home_service_goods_category` | 商品分类表 |
| `home_service_order` | 订单主表 |
| `home_service_order_item` | 订单项表 |
| `home_service_card` | 次卡表 |
| `home_service_card_order` | 次卡订单表 |
| `home_service_coupon` | 优惠券表 |
| `home_service_coupon_member` | 用户优惠券表 |
| `home_service_technician` | 技师表 |
| `home_service_store` | 门店表 |
| `home_service_order_evaluate` | 订单评价表 |
| `home_service_order_refund` | 退款单表 |

### 订单状态流转

```
待支付(0) -> 待分配(1) -> 待服务(2) -> 服务中(3) -> 已完成(4)
    ↓           ↓           ↓           ↓
  已关闭      已取消      已取消      已取消
```

---

## ⚙️ 配置说明

### 基础配置

登录管理后台 -> 上门家政 -> 设置

- **订单设置**: 自动关闭时间、超时提醒等
- **派单设置**: 派单方式（手动/自动/抢单）
- **评价设置**: 评价审核、自动通过时间
- **退款设置**: 自动退款、退款审核
- **预约设置**: 预约时间段配置
- **账户设置**: 提现规则、手续费比例

### 城市策略配置

支持为不同城市设置不同的服务价格：

1. 进入商品管理 -> 选择商品 -> 城市策略
2. 添加城市并设置价格
3. 用户下单时自动匹配城市价格

### 会员折扣配置

支持会员等级享受不同折扣：

1. 商品编辑 -> 会员折扣
2. 选择折扣方式（会员折扣/指定会员价）
3. 设置各等级折扣比例或价格

---

## 🎯 业务场景

### 标准服务流程

```
用户浏览服务 -> 选择服务商品 -> 填写服务地址 -> 选择预约时间
-> 使用优惠券 -> 支付下单 -> 系统分配技师 -> 技师接单
-> 技师上门服务 -> 服务完成 -> 用户评价
```

### 跑腿业务流程

```
用户选择跑腿服务 -> 填写包裹信息（服务类型、取件码、价格）
-> 选择收货地址 -> 选择取件时间 -> 支付下单
-> 骑手接单 -> 骑手根据取件码取件 -> 送达用户指定地址
-> 服务完成 -> 用户评价
```

**特点**：
- 支持多包裹订单
- 每个包裹有独立的取件码和价格
- 需要收货地址（配送目的地）
- 完全兼容优惠券等优惠功能

详细说明请查看：[跑腿业务集成指南](./ERRAND_BUSINESS_GUIDE.md)

### 次卡使用流程

```
用户购买次卡 -> 支付成功 -> 获得次卡
-> 下单时选择次卡支付 -> 扣减次数
-> 次卡用完或过期
```

---

## ❓ 常见问题

### 安装问题

**Q: 安装插件后没有菜单？**
A: 检查是否已创建站点并绑定应用，刷新浏览器缓存。

**Q: 数据表创建失败？**
A: 检查数据库权限，手动执行SQL脚本，查看日志文件。

### 使用问题

**Q: 订单无法自动派单？**
A: 检查队列是否正常运行，派单设置是否正确，技师是否已认证。

**Q: 优惠券不能使用？**
A: 检查优惠券是否在有效期内，是否满足使用条件，商品是否在适用范围。

**Q: 跑腿业务需要地址吗？**
A: 需要！跑腿业务需要收货地址（骑手配送目的地），取件码用于取件识别。

**Q: 如何添加更多跑腿服务类型？**
A: 在商品管理中添加新的SKU即可（如"顺丰|小件"、"中通|大件"等）。

### 开发问题

**Q: 如何修改订单流程？**
A: 修改 `app/service/core/order/CoreOrderStatusService.php` 中的状态流转逻辑。

**Q: 如何添加自定义字段？**
A: 在数据表中添加字段，修改对应的模型和服务类，更新验证规则。

**Q: 如何集成第三方服务？**
A: 在 `app/service/core/` 中创建服务类，使用监听器在适当时机调用。

---

## 📝 更新日志

### v1.0.1 (2024-10-27)
- ✨ 新增校园跑腿功能
  - 支持多包裹订单
  - 每个包裹独立取件码和价格
  - 完全兼容优惠系统
- 🐛 修复周日时间筛选逻辑问题
- 🐛 修复员工列表获取问题
- 🔧 优化订单时间字段管理
- 🔧 优化订单详情页空状态显示

### v1.0.0 (2024-10-24)
- 🎉 首次发布
- ✨ 完整的订单管理系统
- ✨ 次卡和优惠券功能
- ✨ 技师和门店管理
- ✨ 评价和退款系统
- ✨ 数据统计功能
- ✨ 多端支持（用户/技师/门店）

---

## 🤝 贡献指南

我们欢迎所有形式的贡献，包括但不限于：

- 🐛 提交Bug报告
- 💡 提出新功能建议
- 📖 改进文档
- 🔧 提交代码修复
- 🌍 提供多语言翻译

### 提交流程

1. Fork 本项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

---

## 📄 开源协议

本项目基于 [MIT License](https://opensource.org/licenses/MIT) 开源协议。

### 使用须知

1. ✅ 允许用于个人学习、毕业设计、教学案例、公益事业、商业使用
2. ✅ 本框架应用源代码所有权和著作权归niucloud官方所有
3. ❌ 禁止修改框架代码并再次发布框架衍生版
4. ⚠️ 商用请仔细审查代码和漏洞，产生的后果责任自负
5. 📢 基于本框架开发的应用，必须明确声明是基于niucloud-admin框架开发

---

## 📞 联系我们

- 官方网站: https://www.niucloud.com
- 开发文档: https://www.niucloud.com/doc
- API文档: https://api.niucloud.com/apidoc.html
- 论坛社区: https://niucloud.com/bbs
- 服务市场: https://www.niucloud.com

### 技术支持

- 加入企业微信群技术交流
- 查看官方文档和视频教程
- 在论坛发帖求助
- 提交GitHub Issue

---

## 🙏 致谢

感谢以下开源项目：

- [Niucloud](https://www.niucloud.com) - 基础框架
- [ThinkPHP](https://www.thinkphp.cn/) - PHP框架
- [Vue.js](https://vuejs.org/) - 前端框架
- [Element Plus](https://element-plus.org/) - UI组件库
- [UniApp](https://uniapp.dcloud.io/) - 跨端开发框架
- [uView UI](https://www.uviewui.com/) - UniApp UI组件库

---

## 📊 项目统计

![代码行数](https://img.shields.io/badge/代码行数-50000+-blue)
![文件数](https://img.shields.io/badge/文件数-500+-green)
![数据表](https://img.shields.io/badge/数据表-30+-orange)

---

<div align="center">

**如果这个项目对你有帮助，请给我们一个 ⭐️ Star！**

Made with ❤️ by Niucloud Team

Copyright © 2015-2025 Niucloud. All rights reserved.

</div>
