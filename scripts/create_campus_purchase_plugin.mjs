import fs from "node:fs/promises";
import path from "node:path";

const projectRoot = "/Users/a123/Documents/1-work/niucloud/niucloud-admin";
const pluginKey = "campus_purchase";
const pluginTitle = "校园采购";

const paths = {
  sourceAddon: path.join(projectRoot, "niucloud/addon/shop"),
  targetAddon: path.join(projectRoot, `niucloud/addon/${pluginKey}`),
  sourceUni: path.join(projectRoot, "uni-app/src/addon/shop"),
  targetUni: path.join(projectRoot, `uni-app/src/addon/${pluginKey}`),
  pagesJson: path.join(projectRoot, "uni-app/src/pages.json"),
};

const keepUserPageDirs = new Set(["goods", "member", "order"]);
const removeUserPageDirs = ["coupon", "discount", "evaluate", "point", "refund"];
const removeUserApis = ["coupon.ts", "discount.ts", "evaluate.ts", "point.ts", "refund.ts"];
const goodsFallbackImage = `addon/${pluginKey}/goods_template.png`;

async function exists(file) {
  try {
    await fs.access(file);
    return true;
  } catch {
    return false;
  }
}

async function copyDir(source, target) {
  if (await exists(target)) {
    throw new Error(`Target already exists: ${target}`);
  }
  await fs.cp(source, target, { recursive: true });
}

async function walk(dir) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await walk(full));
    } else {
      files.push(full);
    }
  }
  return files;
}

async function replaceInTextFiles(dir, replacer) {
  const files = await walk(dir);
  const textExts = new Set([
    ".php", ".ts", ".vue", ".json", ".scss", ".css", ".js", ".md", ".sql",
  ]);
  for (const file of files) {
    if (!textExts.has(path.extname(file))) continue;
    const original = await fs.readFile(file, "utf8");
    const next = replacer(original, file);
    if (next !== original) await fs.writeFile(file, next);
  }
}

async function writeIfChanged(file, content) {
  const current = await exists(file) ? await fs.readFile(file, "utf8") : "";
  if (current !== content) await fs.writeFile(file, content);
}

async function replaceInFile(file, replacer) {
  const original = await fs.readFile(file, "utf8");
  const next = replacer(original);
  if (next !== original) await fs.writeFile(file, next);
}

function normalizeCampusVisualRefs(content) {
  return content
    .replaceAll("addon/shop/", `addon/${pluginKey}/`)
    .replaceAll("static/resource/images/diy/shop_default.jpg", goodsFallbackImage);
}

async function getAddonPackageBlock(addon) {
  const file = path.join(projectRoot, `niucloud/addon/${addon}/package/uni-app-pages.php`);
  const uniSourceDir = path.join(projectRoot, `uni-app/src/addon/${addon}`);
  if (!await exists(uniSourceDir)) return "";
  if (!await exists(file)) return "";
  const content = await fs.readFile(file, "utf8");
  const match = content.match(/\/\/ PAGE_BEGIN[\s\S]*?\/\/ PAGE_END/);
  if (!match) return "";
  let block = match[0];
  block = block.replace(/(.*)(\r?\n.*\/\/ PAGE_END.*)/s, (_, head, tail) => {
    return `${head}${head.trimEnd().endsWith(",") ? "" : ","}${tail}`;
  });
  block = block.replaceAll("PAGE_BEGIN", `${addon.toUpperCase()}_PAGE_BEGIN`);
  block = block.replaceAll("PAGE_END", `${addon.toUpperCase()}_PAGE_END`);
  block = block.replaceAll("{{addon_name}}", addon);
  return block;
}

function compactUniPagesPhp() {
  return `<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
                // *********************************** 校园采购 ***********************************
                {
                    "root": "addon/${pluginKey}",
                    "pages": [
                {
                    "path": "pages/index",
                    "style": {
                        // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                        "navigationBarTitleText": "%${pluginKey}.pages.index%"
                    }
                },
                {
                    "path": "pages/member/index",
                    "style": {
                        // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                        "navigationBarTitleText": "%${pluginKey}.pages.member.index%"
                    }
                },
                {
                    "path": "pages/goods/search",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.search%"
                    }
                },
                {
                    "path": "pages/goods/list",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.list%"
                    }
                },
                {
                    "path": "pages/goods/detail",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.detail%",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/goods/cart",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.cart%"
                    }
                },
                {
                    "path": "pages/goods/category",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.category%"
                    }
                },
                {
                    "path": "pages/order/detail",
                    "style": {
                        // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                        "navigationBarTitleText": "%${pluginKey}.pages.order.detail%"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/list",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.order.list%"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/payment",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.order.payment%"
                    },
                    "needLogin": true
                }
                    ]
                },
                // PAGE_END
EOT
];
`;
}

function pagesJsonBlock() {
  return `        // CAMPUS_PURCHASE_PAGE_BEGIN
                // *********************************** 校园采购 ***********************************
                {
                    "root": "addon/${pluginKey}",
                    "pages": [
                {
                    "path": "pages/index",
                    "style": {
                        // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                        "navigationBarTitleText": "%${pluginKey}.pages.index%"
                    }
                },
                {
                    "path": "pages/member/index",
                    "style": {
                        // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                        "navigationBarTitleText": "%${pluginKey}.pages.member.index%"
                    }
                },
                {
                    "path": "pages/goods/search",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.search%"
                    }
                },
                {
                    "path": "pages/goods/list",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.list%"
                    }
                },
                {
                    "path": "pages/goods/detail",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.detail%",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/goods/cart",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.cart%"
                    }
                },
                {
                    "path": "pages/goods/category",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.goods.category%"
                    }
                },
                {
                    "path": "pages/order/detail",
                    "style": {
                        // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                        "navigationBarTitleText": "%${pluginKey}.pages.order.detail%"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/list",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.order.list%"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/payment",
                    "style": {
                        "navigationBarTitleText": "%${pluginKey}.pages.order.payment%"
                    },
                    "needLogin": true
                }
                    ]
                },
        // CAMPUS_PURCHASE_PAGE_END
`;
}

function couponApiStub() {
  return `import request from '@/utils/request'

/**
 * 1.0 用户端先保留 DIY 组件兼容，页面不主动展示优惠券能力。
 */
export function getShopCouponList(params: Record<string, any>) {
    return request.get(\`shop/coupon\`, params)
}

export function getShopCouponInfo(id: number) {
    return request.get(\`shop/coupon/\${ id }\`)
}

export function getShopCouponQrocde(id: number) {
    return request.get(\`shop/coupon/qrcode/\${ id }\`)
}

export function getCoupon(params: Record<string, any>) {
    return request.post(\`shop/coupon\`, params, { showSuccessMessage: true })
}

export function getMyCouponList(params: Record<string, any>) {
    return request.get(\`shop/member/coupon\`, params)
}

export function getShopCouponComponents(params: Record<string, any>) {
    return request.get(\`shop/coupon/components\`, params)
}

export function getMyCouponCount(params: Record<string, any>) {
    return request.get(\`shop/member/coupon/count\`, params)
}

export function getMyCouponType() {
    return request.get(\`shop/coupon_type\`)
}

export function getMyCouponStatusCount() {
    return request.get(\`shop/member/coupon/status_count\`)
}
`;
}

function pointApiStub() {
  return `import request from '@/utils/request'

/**
 * 1.0 用户端先保留 DIY 组件兼容，积分兑换页不注册入口。
 */
export function getExchangePoint() {
    return request.get(\`shop/exchange/point\`)
}

export function getExchangeComponentsList(params : Record<string, any>) {
    return request.get(\`shop/exchange/components\`, params)
}

export function getExchangeGoodsList(params : Record<string, any>) {
    return request.get(\`shop/exchange\`, params)
}

export function getExchangeGoodsDetail(id: any) {
    return request.get(\`shop/exchange/goods/\${id}\`)
}

export function orderCreateCalculate(params: Record<string, any>) {
    return request.get('shop/exchange_order/calculate', params)
}

export function orderCreate(params: Record<string, any>) {
    return request.post('shop/exchange_order/create', params)
}
`;
}

function goodsDetailVue() {
  return `<template>
\t<view :style="themeColor()">
\t\t<view class="bg-[var(--page-bg-color)] min-h-[100vh] relative" v-if="Object.keys(goodsDetail).length">
\t\t\t<view class="flex items-center fixed left-0 right-0 z-10 bg-transparent detail-head" :class="{'!bg-[#fff]' :detailHeadBgChange}" :style="navbarInnerStyle">
\t\t\t\t<text class="nc-iconfont nc-icon-zuoV6xx" :style="navbarInnerArrowStyle" @click="backToPrevious()"></text>
\t\t\t\t<view class="ml-auto !pt-[12rpx] !pb-[8rpx] p-[10rpx] bg-[rgba(255,255,255,.4)] rounded-full border-[2rpx] border-solid border-transparent box-border nc-iconfont nc-icon-fenxiangV6xx font-bold text-[#303133] text-[36rpx]" :class="{'border-[#d8d8d8]': detailHeadBgChange}" @click="openShareFn"></view>
\t\t\t</view>

\t\t\t<view class="swiper-box">
\t\t\t\t<u-swiper :list="goodsImages" :indicator="goodsImages.length" :indicatorStyle="{'bottom': '70rpx'}" :autoplay="true" height="100vw" radius="0" @click="swiperClick"></u-swiper>
\t\t\t</view>

\t\t\t<view class="bg-[var(--page-bg-color)] rounded-[40rpx] overflow-hidden -mt-[34rpx] relative">
\t\t\t\t<view class="datail-title relative px-[30rpx] pt-[30rpx]">
\t\t\t\t\t<view class="text-[var(--price-text-color)] flex items-baseline mb-[12rpx]">
\t\t\t\t\t\t<text class="text-[32rpx] font-medium price-font">￥</text>
\t\t\t\t\t\t<text class="text-[48rpx] price-font">{{ priceParts[0] }}</text>
\t\t\t\t\t\t<text class="text-[32rpx] mr-[10rpx] price-font">.{{ priceParts[1] }}</text>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="text-[#333] font-medium text-[30rpx] multi-hidden leading-[40rpx]">{{ goodsDetail.goods.goods_name }}</view>
\t\t\t\t\t<view class="flex items-start mt-[24rpx]">
\t\t\t\t\t\t<view class="flex flex-wrap" v-if="goodsDetail.label_info && goodsDetail.label_info.length">
\t\t\t\t\t\t\t<view v-for="item in goodsDetail.label_info" :key="item.label_id" class="tag-item text-[#FA6400] mb-[10rpx] h-[36rpx] leading-[32rpx] text-[20rpx] px-[12rpx] border-[2rpx] border-solid border-[#FA6400] mr-[15rpx] truncate">
\t\t\t\t\t\t\t\t{{ item.label_name }}
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t\t<view class="text-[22rpx] mb-[10rpx] text-[var(--text-color-light9)] flex items-baseline ml-auto">
\t\t\t\t\t\t\t<text class="whitespace-nowrap">累计采购</text>
\t\t\t\t\t\t\t<text class="mx-[2rpx]">{{ goodsDetail.goods.sale_num || 0 }}</text>
\t\t\t\t\t\t\t<text>{{ goodsDetail.goods.unit }}</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="mt-[var(--top-m)] sidebar-margin card-template" v-if="isGoodsPropertyTemp">
\t\t\t\t\t<view @click="servicesDataShow = !servicesDataShow" v-if="goodsDetail.service && goodsDetail.service.length" class="card-template-item">
\t\t\t\t\t\t<text class="text-[#333] text-[26rpx] leading-[30rpx] shrink-0">服务</text>
\t\t\t\t\t\t<view class="text-[#343434] text-[26rpx] leading-[30rpx] truncate ml-auto">{{ goodsDetail.service[0].service_name }}</view>
\t\t\t\t\t\t<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)] ml-[8rpx]"></text>
\t\t\t\t\t</view>
\t\t\t\t\t<view @click="buyFn" v-if="goodsDetail.goodsSpec && goodsDetail.goodsSpec.length" class="card-template-item">
\t\t\t\t\t\t<text class="text-[#333] text-[26rpx] leading-[30rpx] shrink-0 mr-[20rpx]">已选</text>
\t\t\t\t\t\t<view class="ml-auto text-right truncate flex-1 text-[#343434] text-[26rpx] leading-[30rpx]">{{ goodsDetail.sku_spec_format }}</view>
\t\t\t\t\t\t<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)] ml-[8rpx]"></text>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="card-template-item" @click="distributionDataOpen" v-if="goodsDetail.goods.goods_type == 'real' && goodsDetail.delivery_type_list && goodsDetail.delivery_type_list.length">
\t\t\t\t\t\t<text class="text-[#333] text-[26rpx] leading-[30rpx] shrink-0">配送</text>
\t\t\t\t\t\t<view class="ml-auto flex items-center text-[#343434] text-[26rpx] leading-[30rpx]">{{ goodsDetail.delivery_type_list[selectDeliveryType].name }}</view>
\t\t\t\t\t\t<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)] ml-[8rpx]"></text>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="mt-[var(--top-m)] sidebar-margin card-template">
\t\t\t\t\t<view class="flex items-center justify-between min-h-[40rpx] mb-[24rpx]">
\t\t\t\t\t\t<text class="title !mb-[0]">资质溯源({{ traceabilityList.length }})</text>
\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--text-color-light6)]">随批次更新</text>
\t\t\t\t\t</view>
\t\t\t\t\t<view v-for="(item, index) in traceabilityList" :key="index" class="card-template-item justify-between">
\t\t\t\t\t\t<view class="flex flex-col">
\t\t\t\t\t\t\t<text class="text-[28rpx] text-[#333] leading-[38rpx]">{{ item.name }}</text>
\t\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--text-color-light6)] mt-[8rpx]">{{ item.desc }}</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--primary-color)]">有效</text>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="my-[var(--top-m)] goods-sku sidebar-margin card-template" v-if="goodsAttrList.length">
\t\t\t\t\t<view class="title mb-[30rpx]">商品属性</view>
\t\t\t\t\t<view>
\t\t\t\t\t\t<block v-for="(item,index) in goodsAttrList" :key="index">
\t\t\t\t\t\t\t<view v-if="index < 4 || isAttrFormatShow" class="card-template-item">
\t\t\t\t\t\t\t\t<text class="text-[26rpx] leading-[30rpx] w-[160rpx] shrink-0 text-[var(--text-color-light9)]">{{ item.attr_value_name }}</text>
\t\t\t\t\t\t\t\t<view class="text-[#333] box-border value-wid text-[26rpx] leading-[30rpx] truncate pl-[20rpx]">{{ Array.isArray(item.attr_child_value_name) ? item.attr_child_value_name.join(',') : item.attr_child_value_name }}</view>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</block>
\t\t\t\t\t\t<view v-if="goodsAttrList.length > 4" class="flex-center" @click="isAttrFormatShow = !isAttrFormatShow">
\t\t\t\t\t\t\t<text class="text-[24rpx] mr-[10rpx]">{{ !isAttrFormatShow ? '展开' : '收起' }}</text>
\t\t\t\t\t\t\t<text class="nc-iconfont !text-[22rpx]" :class="{'nc-icon-xiaV6xx': !isAttrFormatShow, 'nc-icon-shangV6xx-1': isAttrFormatShow}"></text>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="my-[var(--top-m)] sidebar-margin card-template px-[var(--pad-sidebar-m)]">
\t\t\t\t\t<view class="title">商品详情</view>
\t\t\t\t\t<view class="u-content">
\t\t\t\t\t\t<u-parse :content="goodsDetail.goods.goods_desc" :tagStyle="{img: 'vertical-align: top;', p:'overflow: hidden;word-break:break-word;' }"></u-parse>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="tab-bar-placeholder"></view>
\t\t\t\t<view class="border-[0] border-t-[2rpx] border-solid border-[#f5f5f5] w-[100%] flex justify-between pl-[32rpx] pr-[4rpx] bg-[#fff] box-border fixed left-0 bottom-0 tab-bar z-1 items-center">
\t\t\t\t\t<view class="flex items-center">
\t\t\t\t\t\t<view class="flex flex-col justify-center items-center mr-[38rpx]" @click="redirect({ url: '/addon/campus_purchase/pages/index', mode: 'reLaunch' })">
\t\t\t\t\t\t\t<view class="nc-iconfont nc-icon-shouyeV6xx11 text-[36rpx]"></view>
\t\t\t\t\t\t\t<text class="text-[20rpx] mt-[10rpx]">首页</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t\t<view class="flex flex-col justify-center items-center mr-[38rpx]" @click="redirect({ url: '/addon/campus_purchase/pages/goods/cart' })">
\t\t\t\t\t\t\t<view class="relative">
\t\t\t\t\t\t\t\t<view class="nc-iconfont nc-icon-gouwucheV6xx text-[36rpx]"></view>
\t\t\t\t\t\t\t\t<text v-if="cartTotalNum" class="absolute top-[-10rpx] right-[-18rpx] rounded-full bg-[#ff4646] text-[#fff] text-[20rpx] min-w-[28rpx] h-[28rpx] leading-[28rpx] text-center">{{ cartTotalNum > 99 ? '99+' : cartTotalNum }}</text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t<text class="text-[20rpx] mt-[10rpx]">采购单</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="flex flex-1" v-if="goodsDetail.goods.status == 1">
\t\t\t\t\t\t<button v-if="goodsDetail.goods.goods_type == 'real' || (goodsDetail.goods.goods_type == 'virtual' && goodsDetail.goods.virtual_receive_type != 'verify')" class="flex-1 !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" style="background: linear-gradient(127deg, #FFB000 0%, #FFA029 100%);" @click="buyFn('join_cart')">加入采购单</button>
\t\t\t\t\t\t<button v-if="isShowSingleSku" class="flex-1 !h-[70rpx] font-500 text-[26rpx] !text-[#fff] primary-btn-bg !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" @click="buyFn('buy_now')">立即采购</button>
\t\t\t\t\t\t<button v-else class="flex-1 !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !bg-[#ccc] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border">已售罄</button>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="flex flex-1" v-else>
\t\t\t\t\t\t<button class="w-[100%] !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !bg-[#ccc] !m-0 leading-[70rpx] rounded-full remove-border">该商品已下架</button>
\t\t\t\t\t</view>
\t\t\t\t</view>
\t\t\t</view>

\t\t\t<u-popup class="popup-type" :show="servicesDataShow" @close="servicesDataShow = false">
\t\t\t\t<view class="min-h-[480rpx] popup-common" @touchmove.prevent.stop>
\t\t\t\t\t<view class="title">商品服务</view>
\t\t\t\t\t<scroll-view class="h-[520rpx]" scroll-y="true">
\t\t\t\t\t\t<view class="pl-[22rpx] pb-[28rpx] pr-[37rpx]">
\t\t\t\t\t\t\t<view class="flex mb-[28rpx]" v-for="(item, index) in goodsDetail.service" :key="index">
\t\t\t\t\t\t\t\t<image class="mt-[4rpx] w-[32rpx] h-[32rpx] mr-[14rpx]" :src="img(item.image || 'addon/shop/icon_service.png')" mode="aspectFit" />
\t\t\t\t\t\t\t\t<view class="flex-1">
\t\t\t\t\t\t\t\t\t<view class="text-[30rpx] leading-[36rpx] text-[#333] mb-[8rpx]">{{ item.service_name }}</view>
\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] leading-[36rpx] text-[var(--text-color-light9)]">{{ item.desc }}</view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</scroll-view>
\t\t\t\t</view>
\t\t\t</u-popup>

\t\t\t<u-popup class="popup-type" :show="distributionDataShow" @close="distributionDataShow = false">
\t\t\t\t<view class="min-h-[360rpx] popup-common" @touchmove.prevent.stop>
\t\t\t\t\t<view class="title">配送方式</view>
\t\t\t\t\t<scroll-view class="h-[520rpx]" scroll-y="true">
\t\t\t\t\t\t<view class="px-[var(--popup-sidebar-m)]">
\t\t\t\t\t\t\t<view class="flex mb-[40rpx]" v-for="(item, index) in goodsDetail.delivery_type_list" :key="index" @click="distributionListFn(item,index)">
\t\t\t\t\t\t\t\t<image class="mt-[4rpx] w-[32rpx] h-[32rpx] mr-[14rpx]" :src="img('addon/shop/icon_service.png')" mode="aspectFit" />
\t\t\t\t\t\t\t\t<view class="flex-1">
\t\t\t\t\t\t\t\t\t<view class="text-[30rpx] leading-[36rpx] text-[#333] mb-[8rpx]">{{ item.name }}</view>
\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] leading-[36rpx] text-[var(--text-color-light9)]">{{ item.desc }}</view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</scroll-view>
\t\t\t\t</view>
\t\t\t</u-popup>

\t\t\t<ns-goods-sku ref="goodsSkuRef" :goods-detail="goodsDetail" @change="specSelectFn"></ns-goods-sku>
\t\t\t<share-poster ref="sharePosterRef" posterType="shop_goods" :posterId="goodsDetail.goods.poster_id" :posterParam="posterParam" :copyUrlParam="copyUrlParam" />
\t\t</view>
\t\t<loading-page :loading="loading"></loading-page>
\t</view>
</template>

<script setup lang="ts">
import { ref, computed, getCurrentInstance, nextTick } from 'vue';
import { onLoad, onShow, onUnload, onPageScroll } from '@dcloudio/uni-app';
import { img, redirect, handleOnloadParams, deepClone, goback } from '@/utils/common';
import { getGoodsDetail } from '@/addon/campus_purchase/api/goods';
import nsGoodsSku from '@/addon/campus_purchase/components/ns-goods-sku/ns-goods-sku.vue';
import useCartStore from '@/addon/campus_purchase/stores/cart';
import { useShare } from '@/hooks/useShare';
import sharePoster from '@/components/share-poster/share-poster.vue';

const { setShare } = useShare();
const cartStore = useCartStore();
const cartTotalNum = computed(() => cartStore.totalNum);
const goodsSkuRef: any = ref(null);
const sharePosterRef: any = ref(null);
const goodsDetail: any = ref({});
const goodsImages = ref<string[]>([]);
const goodsAttrList: any = ref([]);
const traceabilityList = ref<Array<{ name: string, desc: string }>>([]);
const isAttrFormatShow = ref(false);
const loading = ref<boolean>(false);
const servicesDataShow = ref<boolean>(false);
const distributionDataShow = ref<boolean>(false);
const selectDeliveryType = ref(0);
const detailHeadBgChange = ref(false);
const sendMessagePath = ref('');
const copyUrlParam = ref('');
let posterParam: any = {};

onLoad((option: any) => {
\t// #ifdef MP-WEIXIN
\toption = handleOnloadParams(option);
\t// #endif

\tloading.value = true;
\tgetGoodsDetail({ goods_id: option.goods_id || '', sku_id: option.sku_id || '' }).then((res: any) => {
\t\tif (!res.data.goods || JSON.stringify(res.data) === '[]') {
\t\t\tgoback({ url:'/addon/campus_purchase/pages/index', title: '找不到该商品', mode: 'reLaunch' });
\t\t\treturn false;
\t\t}
\t\tgoodsDetail.value = deepClone(res.data);
\t\tgoodsDetail.value.delivery_type_list = goodsDetail.value.goods.delivery_type_list ? Object.values(goodsDetail.value.goods.delivery_type_list) : [];
\t\tgoodsImages.value = (goodsDetail.value.goods.goods_image_thumb_big || []).map((item: string) => img(item));
\t\tparseGoodsAttr(res.data);
\t\tbuildTraceabilityInfo();
\t\tsetPageShare();
\t\tcopyUrlFn();
\t\tnextTick(() => setHeaderRect());
\t\tloading.value = false;
\t}).catch(() => {
\t\tloading.value = false;
\t});
});

onShow(() => {
\tuni.removeStorageSync('distributionType');
\tcartStore.getList();
});

const parseGoodsAttr = (data: any) => {
\tgoodsAttrList.value = [];
\tif (!data.goods?.attr_format) return;
\ttry {
\t\tconst attrFormatArr = JSON.parse(data.goods.attr_format);
\t\tattrFormatArr.forEach((item: any) => {
\t\t\tif ((item.attr_child_value_name && !(item.attr_child_value_name instanceof Array)) || ((item.attr_child_value_name instanceof Array) && item.attr_child_value_name.length)) {
\t\t\t\tgoodsAttrList.value.push(item);
\t\t\t}
\t\t});
\t} catch (e) {}
};

const buildTraceabilityInfo = () => {
\tconst supplierName = goodsDetail.value.goods?.supplier_name || goodsDetail.value.goods?.brand_name || '平台供货商';
\ttraceabilityList.value = [
\t\t{ name: '食品经营许可证', desc: supplierName },
\t\t{ name: '生产许可证', desc: goodsDetail.value.goods?.goods_name || '按商品批次归档' },
\t\t{ name: '批次检测报告', desc: '到货验收时同步备案' }
\t];
};

const setPageShare = () => {
\tconst share = {
\t\ttitle: goodsDetail.value.goods.goods_name,
\t\tdesc: goodsDetail.value.goods.sub_title,
\t\turl: goodsDetail.value.goods.goods_cover_thumb_mid
\t};
\tuni.setNavigationBarTitle({ title: goodsDetail.value.goods.goods_name });
\tsetShare({ wechat: { ...share }, weapp: { ...share } });
\tsendMessagePath.value = '/addon/campus_purchase/pages/goods/detail?sku_id=' + goodsDetail.value.sku_id;
};

const priceParts = computed(() => parseFloat(goodsDetail.value.price || '0').toFixed(2).split('.'));

const isShowSingleSku = computed(() => {
\tconst skuList = goodsDetail.value.skuList || [];
\tconst isMultiSpec = skuList.some((item: any) => item.sku_spec_format);
\treturn isMultiSpec || Number(goodsDetail.value.stock || 0) > 0;
});

const isGoodsPropertyTemp = computed(() => {
\treturn !!(goodsDetail.value.service?.length || goodsDetail.value.goodsSpec?.length || (goodsDetail.value.goods?.goods_type == 'real' && goodsDetail.value.delivery_type_list?.length));
});

const specSelectFn = (id: any) => {
\t(goodsDetail.value.skuList || []).forEach((item: any) => {
\t\tif (item.sku_id == id) Object.assign(goodsDetail.value, item);
\t});
};

const buyFn = (type: any) => {
\tgoodsSkuRef.value.open(type);
};

const distributionDataOpen = () => {
\tdistributionDataShow.value = true;
};

const distributionListFn = (data: any,index: any) => {
\tselectDeliveryType.value = index;
\tdistributionDataShow.value = false;
\tuni.setStorageSync('distributionType', data.name);
};

const backToPrevious = () => {
\tif (getCurrentPages().length > 1) {
\t\tuni.navigateBack({ delta: 1 });
\t} else {
\t\tredirect({ url: '/addon/campus_purchase/pages/index', mode: 'reLaunch' });
\t}
};

let systemInfo = uni.getSystemInfoSync();
let platform = systemInfo.platform;
let menuButtonInfo: any = {};
// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
menuButtonInfo = uni.getMenuButtonBoundingClientRect();
// #endif

const navbarInnerStyle = computed(() => {
\tlet style = '';
\t// #ifdef MP
\tlet rightButtonWidth = menuButtonInfo.width ? menuButtonInfo.width * 2 + 'rpx' : '70rpx';
\tstyle += 'height:' + menuButtonInfo.height + 'px;';
\tstyle += 'padding-right:calc(' + rightButtonWidth + ' + 30rpx);';
\tstyle += 'padding-left:calc(' + rightButtonWidth + ' + 30rpx);';
\tstyle += 'padding-top:' + menuButtonInfo.top + 'px;';
\tstyle += 'padding-bottom: 8px;';
\tstyle += platform === 'android' ? 'font-size: 36rpx;' : 'font-size: 32rpx;';
\t// #endif
\t// #ifdef H5
\tstyle += 'height: 100rpx;padding-right: 30rpx;padding-left: 30rpx;font-size: 32rpx;';
\t// #endif
\treturn style;
});

const navbarInnerArrowStyle = computed(() => {
\tlet style = '';
\t// #ifdef MP
\tstyle += 'padding-left: 10rpx;padding-right: 10rpx;position: absolute;left:calc( 100vw - ' + menuButtonInfo.right + 'px);font-size: 26px;';
\t// #endif
\t// #ifdef H5
\tstyle += 'font-size: 26px;';
\t// #endif
\treturn style;
});

const instance = getCurrentInstance();
let swiperHeight = 0;
let detailHead = 0;
const setHeaderRect = () => {
\tsetTimeout(() => {
\t\tconst query = uni.createSelectorQuery().in(instance);
\t\tquery.select('.swiper-box').boundingClientRect((data: any) => { swiperHeight = data ? data.height : 0; }).exec();
\t\tquery.select('.detail-head').boundingClientRect((data: any) => { detailHead = data ? data.height || 0 : 0; }).exec();
\t}, 400);
};

onPageScroll((e) => {
\tif (swiperHeight == 0 || detailHead == 0) return;
\tdetailHeadBgChange.value = e.scrollTop >= swiperHeight - detailHead - 20;
});

const swiperClick = (index: any) => {
\tif (typeof index == 'number') uni.previewImage({ indicator: 'number', current:index, loop: true, urls: goodsImages.value });
};

const copyUrlFn = () => {
\tcopyUrlParam.value = '?sku_id=' + goodsDetail.value.sku_id;
};

const openShareFn = () => {
\tposterParam.sku_id = goodsDetail.value.sku_id;
\tsharePosterRef.value.openShare();
};

onUnload(() => {
\t// #ifdef H5 || APP
\tuni.closePreviewImage();
\t// #endif
});
</script>

<style lang="scss" scoped>
.remove-border {
\t&::after {
\t\tborder: none;
\t}
}
.tab-bar-placeholder {
\tpadding-bottom: calc(constant(safe-area-inset-bottom) + 100rpx);
\tpadding-bottom: calc(env(safe-area-inset-bottom) + 100rpx);
}
.tab-bar {
\tpadding-top: 16rpx;
\tpadding-bottom: calc(constant(safe-area-inset-bottom) + 16rpx);
\tpadding-bottom: calc(env(safe-area-inset-bottom) + 16rpx);
}
.datail-title {
\tbackground: linear-gradient(#fff 70%, #F6F6F6);
}
.goods-sku .value-wid {
\twidth: calc(100% - 160rpx);
}
</style>
`;
}

function orderPaymentVue() {
  return `<template>
\t<view :style="themeColor()">
\t\t<view class="bg-[var(--page-bg-color)] min-h-[100vh]" v-if="orderData">
\t\t\t<view class="pt-[30rpx] sidebar-margin payment-bottom">
\t\t\t\t<view class="mb-[var(--top-m)] rounded-[var(--rounded-big)] bg-white" v-if="orderData.basic.has_goods_types.includes('real') && delivery_type_list.length">
\t\t\t\t\t<view class="rounded-tl-[var(--rounded-big)] rounded-tr-[var(--rounded-big)] head-tab flex items-center w-full bg-[#F1F1F1]" v-if="delivery_type_list.length > 1">
\t\t\t\t\t\t<view v-for="(item, index) in delivery_type_list" :key="index" class="head-tab-item flex-1 relative" :class="{'active': index === activeIndex}">
\t\t\t\t\t\t\t<view class="h-[74rpx] relative z-10 text-center leading-[74rpx] text-[28rpx]" @click="switchDeliveryType(item.key, index)">{{ item.name }}</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="min-h-[140rpx] flex items-center px-[30rpx]">
\t\t\t\t\t\t<view class="w-full" v-if="['express', 'local_delivery'].includes(createData.delivery.delivery_type)" @click="toSelectAddress">
\t\t\t\t\t\t\t<view v-if="!$u.test.isEmpty(orderData.delivery.take_address)" class="pt-[20rpx] pb-[30rpx] flex items-center">
\t\t\t\t\t\t\t\t<image class="w-[60rpx] h-[60rpx] mr-[20rpx] flex-shrink-0" :src="img('addon/shop/payment/position_01.png')" mode="aspectFit"></image>
\t\t\t\t\t\t\t\t<view class="flex flex-col overflow-hidden">
\t\t\t\t\t\t\t\t\t<text class="text-[26rpx] text-[var(--text-color-light9)] mt-[16rpx] truncate max-w-[536rpx]">{{ orderData.delivery.take_address.full_address.split(orderData.delivery.take_address.address)[0] }}</text>
\t\t\t\t\t\t\t\t\t<text class="font-500 text-[30rpx] mt-[14rpx] text-[#333] truncate max-w-[536rpx]">{{ orderData.delivery.take_address.address }}</text>
\t\t\t\t\t\t\t\t\t<view class="flex items-center text-[26rpx] text-[var(--text-color-light6)] mt-[16rpx]">
\t\t\t\t\t\t\t\t\t\t<text class="mr-[16rpx]">{{ orderData.delivery.take_address.name }}</text>
\t\t\t\t\t\t\t\t\t\t<text>{{ mobileHide(orderData.delivery.take_address.mobile) }}</text>
\t\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t<text class="ml-auto nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light9)]"></text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t<view v-else class="flex items-center">
\t\t\t\t\t\t\t\t<image class="w-[26rpx] h-[30rpx] mr-[10rpx]" :src="img('addon/shop/payment/position_02.png')" mode="aspectFit"></image>
\t\t\t\t\t\t\t\t<text class="text-[28rpx]">添加收货地址</text>
\t\t\t\t\t\t\t\t<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light9)] ml-auto"></text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>

\t\t\t\t\t\t<view class="flex items-center w-full" v-if="createData.delivery.delivery_type == 'store'" @click="storeRef.open()">
\t\t\t\t\t\t\t<view v-if="!$u.test.isEmpty(orderData.delivery.take_store)" class="pt-[40rpx] pb-[30rpx] w-full flex items-center">
\t\t\t\t\t\t\t\t<view class="flex flex-col">
\t\t\t\t\t\t\t\t\t<view class="text-[30rpx] font-500 text-[#303133] mb-[20rpx]">{{ orderData.delivery.take_store.store_name }}</view>
\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] text-[var(--text-color-light6)] mb-[20rpx] leading-[1.4] flex"><text class="flex-shrink-0">门店地址：</text><text class="max-w-[490rpx]">{{ orderData.delivery.take_store.full_address }}</text></view>
\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] text-[var(--text-color-light6)] mb-[20rpx]"><text>联系电话：</text><text>{{ orderData.delivery.take_store.store_mobile }}</text></view>
\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] text-[var(--text-color-light6)]"><text>营业时间：</text><text>{{ orderData.delivery.take_store.trade_time }}</text></view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t<text class="ml-auto nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light9)]"></text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t<view v-else class="flex items-center w-full">
\t\t\t\t\t\t\t\t<image class="w-[26rpx] h-[30rpx] mr-[10rpx]" :src="img('addon/shop/payment/position_02.png')" mode="aspectFit"></image>
\t\t\t\t\t\t\t\t<text class="text-[28rpx]">请选择自提点</text>
\t\t\t\t\t\t\t\t<text class="ml-auto nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light9)]"></text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view v-if="orderData.basic.has_goods_types.includes('real') && !delivery_type_list.length" class="mb-[var(--top-m)] card-template h-[100rpx] flex items-center">
\t\t\t\t\t<p class="text-[28rpx] text-[var(--primary-color)]">商家尚未配置配送方式</p>
\t\t\t\t</view>

\t\t\t\t<view class="mb-[var(--top-m)] card-template">
\t\t\t\t\t<view class="mb-[30rpx]">
\t\t\t\t\t\t<view class="flex" v-for="(item, key, index) in orderData.goods_data" :key="index" :class="{'pb-[40rpx]': (index + 1) != Object.keys(orderData.goods_data).length}">
\t\t\t\t\t\t\t<u--image radius="var(--goods-rounded-big)" width="180rpx" height="180rpx" :src="img(item.sku_image)" model="aspectFill">
\t\t\t\t\t\t\t\t<template #error><image class="w-[180rpx] h-[180rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image></template>
\t\t\t\t\t\t\t</u--image>
\t\t\t\t\t\t\t<view class="flex flex-1 w-0 flex-col justify-between ml-[20rpx] py-[6rpx]">
\t\t\t\t\t\t\t\t<view class="line-normal">
\t\t\t\t\t\t\t\t\t<view class="truncate text-[#303133] text-[28rpx] leading-[32rpx]">{{ item.goods.goods_name }}</view>
\t\t\t\t\t\t\t\t\t<view class="mt-[14rpx] flex" v-if="item.sku_name"><text class="truncate text-[24rpx] text-[var(--text-color-light9)] leading-[28rpx]">{{ item.sku_name }}</text></view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t<view class="flex justify-between items-baseline">
\t\t\t\t\t\t\t\t\t<view class="text-[var(--price-text-color)] flex items-baseline price-font">
\t\t\t\t\t\t\t\t\t\t<text class="text-[24rpx] font-500 mr-[4rpx]">￥</text>
\t\t\t\t\t\t\t\t\t\t<text class="text-[40rpx] font-500">{{ parseFloat(item.price).toFixed(2).split('.')[0] }}</text>
\t\t\t\t\t\t\t\t\t\t<text class="text-[24rpx] font-500">.{{ parseFloat(item.price).toFixed(2).split('.')[1] }}</text>
\t\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t\t<view class="font-400 text-[28rpx] text-[#303133]">x{{ item.num }}</view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>

\t\t\t\t\t<view class="bg-white flex items-center leading-[30rpx]">
\t\t\t\t\t\t<view class="text-[28rpx] w-[150rpx] text-[#303133]">采购备注</view>
\t\t\t\t\t\t<view class="flex-1 text-[#303133]">
\t\t\t\t\t\t\t<input type="text" v-model="createData.member_remark" class="text-right text-[#333] text-[28rpx]" maxlength="50" placeholder="请输入验收或配送备注" placeholder-class="text-[var(--text-color-light9)] text-[28rpx]">
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="card-template">
\t\t\t\t\t<view class="title">采购金额</view>
\t\t\t\t\t<view class="card-template-item">
\t\t\t\t\t\t<view class="text-[28rpx] w-[150rpx] leading-[30rpx] text-[#303133]">商品金额</view>
\t\t\t\t\t\t<view class="flex-1 w-0 text-right price-font text-[#333] text-[32rpx]">￥{{ parseFloat(orderData.basic.goods_money) }}</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="card-template-item" v-if="orderData.basic.delivery_money">
\t\t\t\t\t\t<view class="text-[28rpx] w-[150rpx] leading-[30rpx] text-[#303133]">配送费用</view>
\t\t\t\t\t\t<view class="flex-1 w-0 text-right price-font text-[#333] text-[32rpx]">￥{{ parseFloat(orderData.basic.delivery_money) }}</view>
\t\t\t\t\t</view>
\t\t\t\t</view>
\t\t\t</view>

\t\t\t<u-tabbar :fixed="true" :placeholder="true" :safeAreaInsetBottom="true" zIndex="10">
\t\t\t\t<view class="flex-1 flex items-center justify-between pl-[30rpx] pr-[20rpx]">
\t\t\t\t\t<view class="flex items-baseline">
\t\t\t\t\t\t<text class="text-[26rpx] text-[#333] leading-[32rpx]">合计：</text>
\t\t\t\t\t\t<view class="inline-block">
\t\t\t\t\t\t\t<text class="text-[26rpx] font-500 text-[var(--price-text-color)] price-font leading-[30rpx]">￥</text>
\t\t\t\t\t\t\t<text class="text-[44rpx] font-500 text-[var(--price-text-color)] price-font leading-[46rpx]">{{ parseFloat(orderData.basic.order_money).toFixed(2).split('.')[0] }}</text>
\t\t\t\t\t\t\t<text class="text-[26rpx] font-500 text-[var(--price-text-color)] price-font leading-[46rpx]">.{{ parseFloat(orderData.basic.order_money).toFixed(2).split('.')[1] }}</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<button class="w-[196rpx] h-[70rpx] font-500 text-[26rpx] leading-[70rpx] !text-[#fff] m-0 rounded-full primary-btn-bg remove-border" hover-class="none" @click="create">提交采购单</button>
\t\t\t\t</view>
\t\t\t</u-tabbar>

\t\t\t<select-store ref="storeRef" @confirm="confirmSelectStore" />
\t\t\t<address-list ref="addressRef" @confirm="confirmAddress" />
\t\t</view>
\t</view>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { orderCreateCalculate, orderCreate } from '@/addon/campus_purchase/api/order';
import { redirect, img, mobileHide } from '@/utils/common';
import selectStore from './components/select-store/select-store';
import addressList from './components/address-list/address-list';
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage';

const createData: any = ref({
\torder_key: '',
\tmember_remark: '',
\tdiscount: {},
\tinvoice: {},
\tdelivery: { delivery_type: '' },
\textend_data: {}
});

const orderData: any = ref(null);
const storeRef = ref();
const addressRef = ref();
const createLoading = ref(false);
const activeIndex = ref(0);
const delivery_type_list = ref<any[]>([]);
uni.getStorageSync('orderCreateData') && Object.assign(createData.value, uni.getStorageSync('orderCreateData'));

const selectAddress = uni.getStorageSync('selectAddressCallback');
if (selectAddress) {
\tcreateData.value.order_key = '';
\tcreateData.value.delivery.delivery_type = selectAddress.delivery;
\tcreateData.value.delivery.take_address_id = selectAddress.address_id;
\tuni.removeStorage({ key: 'selectAddressCallback' });
}

const switchDeliveryType = (type: string, index: number) => {
\tif (createData.value.delivery.delivery_type != type) {
\t\tactiveIndex.value = index;
\t\tcreateData.value.order_key = '';
\t\tcreateData.value.delivery.delivery_type = type;
\t\tcreateData.value.delivery.take_address_id = 0;
\t\tcalculate();
\t}
};

const calculate = () => {
\torderCreateCalculate(createData.value).then(({ data }) => {
\t\torderData.value = data;
\t\tcreateData.value.order_key = data.order_key;
\t\tif (orderData.value.delivery.delivery_type_list) delivery_type_list.value = Object.values(orderData.value.delivery.delivery_type_list);
\t\tif (selectAddress) activeIndex.value = delivery_type_list.value.findIndex((el: any) => el.key === orderData.value.delivery.delivery_type);
\t\t!createData.value.delivery.delivery_type && data.delivery.delivery_type && (createData.value.delivery.delivery_type = data.delivery.delivery_type);
\t}).catch();
};

calculate();

watch(() => delivery_type_list.value.length, () => {
\tif (delivery_type_list.value.length && uni.getStorageSync('distributionType')) {
\t\tdelivery_type_list.value.forEach((item: any,index) => {
\t\t\tif (item.name == uni.getStorageSync('distributionType')) {
\t\t\t\tactiveIndex.value = index;
\t\t\t\tswitchDeliveryType(item.key, index);
\t\t\t}
\t\t});
\t\tuni.removeStorage({ key: 'distributionType' });
\t}
});

const create = () => {
\tif (!verify() || createLoading.value) return;
\tcreateLoading.value = true;
\tuseSubscribeMessage().request('shop_order_delivery');
\torderCreate(createData.value).then(({ data }) => {
\t\tredirect({ url: '/addon/campus_purchase/pages/order/detail', param: { order_id: data.order_id }, mode: 'redirectTo' });
\t}).catch(() => {
\t\tcreateLoading.value = false;
\t});
};

const verify = () => {
\tconst data = createData.value;
\tif (orderData.value.basic.has_goods_types.includes('real')) {
\t\tif (['express', 'local_delivery'].includes(data.delivery.delivery_type) && !orderData.value.delivery.take_address) {
\t\t\tuni.showToast({ title: '请选择收货地址', icon: 'none' });
\t\t\treturn false;
\t\t}
\t\tif (data.delivery.delivery_type == 'store' && !data.delivery.take_store_id) {
\t\t\tuni.showToast({ title: '请选择自提点', icon: 'none' });
\t\t\treturn false;
\t\t}
\t}
\treturn true;
};

const toSelectAddress = () => {
\tlet data: any = {};
\tdata.delivery = createData.value.delivery.delivery_type;
\tdata.type = createData.value.delivery.delivery_type == 'local_delivery' ? 'location_address' : 'address';
\tdata.id = orderData.value.delivery.take_address?.id || 0;
\taddressRef.value.open(data);
};

const confirmSelectStore = (store: any) => {
\tcreateData.value.delivery.take_store_id = ((store && store.store_id) ? store.store_id: 0);
\tcalculate();
};

const confirmAddress = (data:any) => {
\tcreateData.value.order_key = '';
\tcreateData.value.delivery.delivery_type = data.delivery;
\tcreateData.value.delivery.take_address_id = data.address_id;
\tcalculate();
};
</script>

<style lang="scss" scoped>
.head-tab .head-tab-item.active view {
\tfont-weight: bold;
\tcolor: var(--primary-color);
}
.line-normal {
\tline-height: normal;
}
</style>
`;
}

function orderListVue() {
  return `<template>
\t<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden order-list" :style="themeColor()">
\t\t<view class="fixed left-0 top-0 right-0 z-10" v-if="statusLoading">
\t\t\t<scroll-view :scroll-x="true" class="tab-style-2">
\t\t\t\t<view class="tab-content">
\t\t\t\t\t<view class="tab-items" :class="{ 'class-select': orderState === item.status.toString() }" @click="orderStateFn(item.status)" v-for="(item, index) in orderStateList" :key="index">{{ item.name }}</view>
\t\t\t\t</view>
\t\t\t</scroll-view>
\t\t</view>

\t\t<mescroll-body ref="mescrollRef" top="88rpx" @init="mescrollInit" :down="{ use: false }" @up="getShopOrderFn">
\t\t\t<view class="sidebar-margin pt-[var(--top-m)]" v-if="list.length">
\t\t\t\t<view class="mb-[var(--top-m)] card-template" v-for="(item, index) in list" :key="index">
\t\t\t\t\t<view @click.stop="toLink(item)">
\t\t\t\t\t\t<view class="flex justify-between items-center">
\t\t\t\t\t\t\t<view class="text-[#303133] text-[26rpx] leading-[36rpx]">
\t\t\t\t\t\t\t\t<text>采购单号:</text>
\t\t\t\t\t\t\t\t<text class="ml-[10rpx]">{{ item.order_no }}</text>
\t\t\t\t\t\t\t\t<text class="text-[#303133] text-[26rpx] nc-iconfont nc-icon-fuzhiV6xx1 ml-[11rpx]" @click.stop="copy(item.order_no)"></text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t<view class="text-[#303133] text-[26rpx] leading-[34rpx]" :class="{'text-primary': item.status == 1, '!text-[var(--text-color-light9)]': item.status == 5 || item.status == -1}">{{ item.status_name.name }}</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t\t<view class="flex box-border mt-[20rpx]" v-for="(subitem, subIndex) in item.order_goods" :key="subIndex">
\t\t\t\t\t\t\t<u--image width="150rpx" height="150rpx" :radius="'var(--goods-rounded-big)'" :src="img(subitem.goods_image_thumb_small ? subitem.goods_image_thumb_small : '')" mode="aspectFill">
\t\t\t\t\t\t\t\t<template #error><image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image></template>
\t\t\t\t\t\t\t</u--image>
\t\t\t\t\t\t\t<view class="ml-[20rpx] flex flex-1 flex-col box-border">
\t\t\t\t\t\t\t\t<view class="flex justify-between items-baseline">
\t\t\t\t\t\t\t\t\t<view class="max-w-[322rpx] text-[28rpx] leading-[40rpx] truncate text-[#303133]">{{ subitem.goods_name }}</view>
\t\t\t\t\t\t\t\t\t<view class="text-right leading-[42rpx] ml-[10rpx]">
\t\t\t\t\t\t\t\t\t\t<text class="text-[22rpx] price-font">￥</text>
\t\t\t\t\t\t\t\t\t\t<text class="text-[36rpx] font-500 price-font">{{ parseFloat(subitem.price).toFixed(2).split('.')[0] }}</text>
\t\t\t\t\t\t\t\t\t\t<text class="text-[22rpx] font-500 price-font">.{{ parseFloat(subitem.price).toFixed(2).split('.')[1] }}</text>
\t\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t<view class="flex justify-between items-baseline text-[#303133] mt-[14rpx]">
\t\t\t\t\t\t\t\t\t<view>
\t\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] text-[var(--text-color-light6)] truncate leading-[34rpx] max-w-[369rpx] mb-[10rpx]" v-if="subitem.sku_name">{{ subitem.sku_name }}</view>
\t\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] leading-[34rpx] text-[var(--text-color-light6)]" v-if="item.delivery_type != 'virtual'">配送方式 ： {{ item.delivery_type_name }}</view>
\t\t\t\t\t\t\t\t\t\t<view class="text-[24rpx] leading-[34rpx] text-[var(--text-color-light6)]" v-else>创建时间 ：{{ item.create_time }}</view>
\t\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t\t<text class="text-right text-[26rpx] w-[90rpx] leading-[36rpx]">x{{ subitem.num }}</text>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="flex justify-end items-center mt-[20rpx]">
\t\t\t\t\t\t<view class="flex items-baseline">
\t\t\t\t\t\t\t<view class="text-[22rpx] leading-[30rpx] text-[#303133]">采购金额：</view>
\t\t\t\t\t\t\t<view class="leading-[1] text-[var(--price-text-color)]">
\t\t\t\t\t\t\t\t<text class="text-[22rpx] leading-[26rpx] price-font">￥</text>
\t\t\t\t\t\t\t\t<text class="text-[36rpx] font-500 leading-[40rpx] price-font">{{ parseFloat(item.order_money).toFixed(2).split('.')[0] }}</text>
\t\t\t\t\t\t\t\t<text class="text-[22rpx] font-500 leading-[28rpx] price-font">.{{ parseFloat(item.order_money).toFixed(2).split('.')[1] }}</text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="flex justify-end text-[28rpx] mt-[20rpx] items-center" v-if="item.status == 1 || item.status == 3">
\t\t\t\t\t\t<view class="text-[24rpx] font-500 leading-[52rpx] h-[56rpx] min-w-[150rpx] text-center border-[2rpx] border-solid border-[var(--text-color-light9)] rounded-full text-[var(--text-color-light6)] box-border" v-if="item.status == 1" @click.stop="orderBtnFn(item, 'close')">关闭</view>
\t\t\t\t\t\t<view class="text-[24rpx] font-500 flex-center h-[56rpx] min-w-[150rpx] text-center border-[0] text-[#fff] primary-btn-bg rounded-full ml-[20rpx] box-border" v-if="item.status == 3" @click.stop="orderBtnFn(item, 'finish')">确认收货</view>
\t\t\t\t\t</view>
\t\t\t\t</view>
\t\t\t</view>
\t\t\t<mescroll-empty v-if="!list.length && loading" :option="{tip : '暂无采购记录'}"></mescroll-empty>
\t\t</mescroll-body>
\t</view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { img, redirect, copy } from '@/utils/common';
import { getShopOrderStatus, getShopOrder, orderClose, orderFinish } from '@/addon/campus_purchase/api/order';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import useConfigStore from '@/stores/config';

const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const list = ref<Array<any>>([]);
const loading = ref<boolean>(false);
const statusLoading = ref<boolean>(false);
const orderState = ref('');
const orderStateList: any = ref([]);

onLoad((option: any) => {
\torderState.value = option.status || '';
\tgetShopOrderStatusFn();
});

const getShopOrderFn = (mescroll: any) => {
\tloading.value = false;
\tgetShopOrder({ page: mescroll.num, limit: mescroll.size, status: orderState.value }).then((res: any) => {
\t\tlet newArr = (res.data.data as Array<any>);
\t\tif (mescroll.num == 1) list.value = [];
\t\tlist.value = list.value.concat(newArr);
\t\tmescroll.endSuccess(newArr.length);
\t\tloading.value = true;
\t}).catch(() => {
\t\tloading.value = true;
\t\tmescroll.endErr();
\t});
};

const getShopOrderStatusFn = () => {
\tstatusLoading.value = false;
\torderStateList.value = [{ name: '全部', status: '' }];
\tgetShopOrderStatus().then((res: any) => {
\t\tObject.values(res.data).forEach((item) => orderStateList.value.push(item));
\t\tstatusLoading.value = true;
\t}).catch(() => {
\t\tstatusLoading.value = true;
\t});
};

const orderStateFn = (status: any) => {
\torderState.value = status.toString();
\tlist.value = [];
\tgetMescroll().resetUpScroll();
};

const toLink = (data: any) => {
\tredirect({ url: '/addon/campus_purchase/pages/order/detail', param: { order_id: data.order_id } });
};

const orderBtnFn = (data: any, type = '') => {
\tif (type == 'close') close(data);
\tif (type == 'finish') finish(data);
};

const close = (item: any) => {
\tuni.showModal({
\t\ttitle: '提示',
\t\tcontent: '您确定要关闭该采购单吗？',
\t\tconfirmColor: useConfigStore().themeColor['--primary-color'],
\t\tsuccess: res => {
\t\t\tif (res.confirm) orderClose(item.order_id).then(() => getMescroll().resetUpScroll());
\t\t}
\t});
};

const finish = (item: any) => {
\tuni.showModal({
\t\ttitle: '提示',
\t\tcontent: '您确定货品已收到吗？',
\t\tconfirmColor: useConfigStore().themeColor['--primary-color'],
\t\tsuccess: res => {
\t\t\tif (res.confirm) orderFinish(item.order_id).then(() => getMescroll().resetUpScroll());
\t\t}
\t});
};
</script>

<style>
.order-list .mescroll-body {
\tpadding-bottom: constant(safe-area-inset-bottom) !important;
\tpadding-bottom: env(safe-area-inset-bottom) !important;
}
</style>
`;
}

function orderDetailVue() {
  return `<template>
\t<view :style="themeColor()">
\t\t<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" v-if="!loading">
\t\t\t<view class="pb-20rpx">
\t\t\t\t<view v-if="detail.status_name" class="pl-[40rpx] pr-[50rpx] bg-linear pb-[100rpx]">
\t\t\t\t\t<!-- #ifdef MP-WEIXIN -->
\t\t\t\t\t<top-tabbar :data="topTabbarData" :scrollBool="topTabarObj.getScrollBool()" />
\t\t\t\t\t<!-- #endif -->
\t\t\t\t\t<view class="flex justify-between items-center pt-[40rpx]">
\t\t\t\t\t\t<view class="text-[#fff] text-[36rpx] font-500 leading-[42rpx]">{{ detail.status_name.name }}</view>
\t\t\t\t\t\t<image v-if="detail.status == 1" class="w-[180rpx] h-[140rpx]" :src="img('addon/shop/detail/payment.png')" mode="aspectFit" />
\t\t\t\t\t\t<image v-if="detail.status == 2" class="w-[180rpx] h-[140rpx]" :src="img('addon/shop/detail/deliver_goods.png')" mode="aspectFit" />
\t\t\t\t\t\t<image v-if="detail.status == 3" class="w-[180rpx] h-[140rpx]" :src="img('addon/shop/detail/receive.png')" mode="aspectFit" />
\t\t\t\t\t\t<image v-if="detail.status == 5" class="w-[180rpx] h-[140rpx]" :src="img('addon/shop/detail/complete.png')" mode="aspectFit" />
\t\t\t\t\t\t<image v-if="detail.status == -1" class="w-[180rpx] h-[140rpx]" :src="img('addon/shop/detail/close.png')" mode="aspectFit" />
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="sidebar-margin mt-[-86rpx] card-template" v-if="detail.delivery_type != 'virtual'">
\t\t\t\t\t<view v-if="detail.delivery_type == 'express' || detail.delivery_type == 'local_delivery'" class="text-[#303133] flex">
\t\t\t\t\t\t<text class="nc-iconfont nc-icon-dizhiguanliV6xx text-[40rpx] pt-[12rpx] mr-[20rpx]"></text>
\t\t\t\t\t\t<view class="flex flex-col">
\t\t\t\t\t\t\t<view class="text-[30rpx] leading-[38rpx] overflow-hidden">
\t\t\t\t\t\t\t\t<text>{{ detail.taker_name }}</text>
\t\t\t\t\t\t\t\t<text class="ml-[15rpx]">{{ detail.taker_mobile }}</text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t<view class="mt-[12rpx] text-[24rpx] text-[var(--text-color-light6)] leading-[26rpx]">{{ detail.taker_full_address }}</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view v-if="detail.delivery_type == 'store'" class="flex items-center">
\t\t\t\t\t\t<u--image class="overflow-hidden" radius="var(--goods-rounded-mid)" width="100rpx" height="100rpx" :src="img(detail.store.store_logo ? detail.store.store_logo : '')" model="aspectFill">
\t\t\t\t\t\t\t<template #error><image class="w-[100rpx] h-[100rpx] rounded-[var(--goods-rounded-mid)] overflow-hidden" :src="img('addon/shop/store_default.png')" mode="aspectFill"></image></template>
\t\t\t\t\t\t</u--image>
\t\t\t\t\t\t<view class="flex flex-col ml-[20rpx]">
\t\t\t\t\t\t\t<text class="text-[30rpx] font-500 text-[#303133] mb-[20rpx]">{{ detail.store.store_name }}</text>
\t\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--text-color-light6)] mb-[14rpx]">{{ detail.store.trade_time }}</text>
\t\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--text-color-light6)] leading-[1.4]">{{ detail.store.full_address }}</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="sidebar-margin card-template" :style="detail.delivery_type == 'virtual' ? 'margin-top: -86rpx' : 'margin-top: 20rpx'">
\t\t\t\t\t<view class="order-goods-item flex justify-between flex-wrap mt-[30rpx]" v-for="(goodsItem, goodsIndex) in detail.order_goods" :key="goodsIndex">
\t\t\t\t\t\t<view class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" @click="goodsEvent(goodsItem.goods_id)">
\t\t\t\t\t\t\t<u--image class="overflow-hidden" radius="var(--goods-rounded-big)" width="150rpx" height="150rpx" :src="img(goodsItem.goods_image_thumb_small ? goodsItem.goods_image_thumb_small : '')" model="aspectFill">
\t\t\t\t\t\t\t\t<template #error><image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image></template>
\t\t\t\t\t\t\t</u--image>
\t\t\t\t\t\t</view>
\t\t\t\t\t\t<view class="ml-[20rpx] flex flex-1 flex-col justify-between">
\t\t\t\t\t\t\t<view>
\t\t\t\t\t\t\t\t<view class="text-[28rpx] max-w-[490rpx] truncate leading-[40rpx] text-[#333]">{{ goodsItem.goods_name }}</view>
\t\t\t\t\t\t\t\t<view class="text-[22rpx] mt-[14rpx] text-[var(--text-color-light9)] truncate max-w-[490rpx] leading-[28rpx]" v-if="goodsItem.sku_name">{{ goodsItem.sku_name }}</view>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t<view class="flex justify-between items-baseline leading-[28rpx] text-[#333]">
\t\t\t\t\t\t\t\t<view class="price-font">
\t\t\t\t\t\t\t\t\t<text class="text-[24rpx]">￥</text>
\t\t\t\t\t\t\t\t\t<text class="text-[40rpx] font-500">{{ parseFloat(goodsItem.price).toFixed(2).split('.')[0] }}</text>
\t\t\t\t\t\t\t\t\t<text class="text-[24rpx] font-500">.{{ parseFloat(goodsItem.price).toFixed(2).split('.')[1] }}</text>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t<text class="text-right text-[26rpx]">x{{ goodsItem.num }}</text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="sidebar-margin mt-[var(--top-m)] card-template">
\t\t\t\t\t<view class="justify-between card-template-item">
\t\t\t\t\t\t<view class="text-[28rpx]">采购单号</view>
\t\t\t\t\t\t<view class="flex items-center text-[28rpx]"><text>{{ detail.order_no }}</text><text class="w-[2rpx] h-[20rpx] bg-[#999] mx-[10rpx]"></text><text class="text-[#EF900A]" @click="copy(detail.order_no)">复制</text></view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="justify-between card-template-item">
\t\t\t\t\t\t<view class="text-[28rpx]">创建时间</view>
\t\t\t\t\t\t<view class="text-[28rpx]">{{ detail.create_time }}</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="card-template-item justify-between">
\t\t\t\t\t\t<view class="text-[28rpx]">配送方式</view>
\t\t\t\t\t\t<view class="text-[28rpx]">{{ detail.delivery_type_name }}</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="sidebar-margin mt-[var(--top-m)] card-template">
\t\t\t\t\t<view class="card-template-item justify-between">
\t\t\t\t\t\t<view class="text-[28rpx]">商品金额</view>
\t\t\t\t\t\t<view class="price-font font-500"><text class="text-[28rpx]">￥{{ parseFloat(detail.goods_money).toFixed(2) }}</text></view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="card-template-item justify-between">
\t\t\t\t\t\t<view class="text-[28rpx]">配送费用</view>
\t\t\t\t\t\t<view class="price-font font-500 text-[28rpx]">￥{{ parseFloat(detail.delivery_money).toFixed(2) }}</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="card-template-item justify-between items-baseline">
\t\t\t\t\t\t<view class="text-[28rpx]">采购金额</view>
\t\t\t\t\t\t<view class="text-[var(--price-text-color)] price-font"><text class="text-[28rpx]">￥{{ parseFloat(detail.order_money).toFixed(2) }}</text></view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="flex z-2 justify-between items-center bg-[#fff] fixed left-0 right-0 bottom-0 min-h-[100rpx] pl-[30rpx] pr-[20rpx] flex-wrap pb-ios">
\t\t\t\t\t<view class="flex">
\t\t\t\t\t\t<view class="flex mr-[34rpx] flex-col justify-center items-center" @click="orderBtnFn('index')">
\t\t\t\t\t\t\t<view class="nc-iconfont nc-icon-shouyeV6xx11 text-[36rpx]"></view>
\t\t\t\t\t\t\t<text class="text-[20rpx] mt-[10rpx]">首页</text>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="flex justify-end">
\t\t\t\t\t\t<view class="min-w-[180rpx] box-border text-[26rpx] h-[70rpx] flex-center border-[2rpx] border-solid border-[#999] rounded-full ml-[20rpx] text-[var(--text-color-light6)]" @click="orderBtnFn('logistics')" v-if="showLogistics(detail)">物流跟踪</view>
\t\t\t\t\t\t<view class="min-w-[180rpx] box-border text-[26rpx] h-[70rpx] flex-center text-center border-[2rpx] border-solid border-[#999] rounded-full ml-[20rpx] text-[var(--text-color-light6)]" v-if="detail.status == 1" @click="orderBtnFn('close')">关闭</view>
\t\t\t\t\t\t<view v-if="detail.status == 3" class="min-w-[180rpx] box-border text-[26rpx] h-[70rpx] flex-center text-center text-[#fff] primary-btn-bg rounded-full ml-[20rpx]" @click="orderBtnFn('finish')">确认收货</view>
\t\t\t\t\t</view>
\t\t\t\t</view>
\t\t\t</view>
\t\t\t<view class="tab-bar-placeholder"></view>
\t\t\t<logistics-tracking ref="materialRef"></logistics-tracking>
\t\t</view>
\t\t<loading-page :loading="loading"></loading-page>
\t</view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { img, redirect, copy, goback } from '@/utils/common';
import { getShopOrderDetail, orderClose, orderFinish } from '@/addon/campus_purchase/api/order';
import logisticsTracking from '@/addon/campus_purchase/pages/order/components/logistics-tracking/logistics-tracking.vue';
import useConfigStore from '@/stores/config';
import { topTabar } from '@/utils/topTabbar';

const topTabarObj = topTabar();
let topTabbarData = topTabarObj.setTopTabbarParam({ title: '采购详情' });
const detail: any = ref({});
const loading = ref<boolean>(true);
const orderId = ref('');
const materialRef: any = ref(null);

onLoad((option: any) => {
\tif (option.order_id) {
\t\torderId.value = option.order_id;
\t\torderDetailFn(orderId.value);
\t} else {
\t\tgoback({ url:'/addon/campus_purchase/pages/order/list', title: '缺少采购单id' });
\t}
});

const orderDetailFn = (id: any) => {
\tloading.value = true;
\tgetShopOrderDetail(id).then((res: any) => {
\t\tdetail.value = res.data;
\t\tloading.value = false;
\t}).catch(() => {
\t\tloading.value = false;
\t});
};

const close = (item : any) => {
\tuni.showModal({
\t\ttitle: '提示',
\t\tcontent: '您确定要关闭该采购单吗？',
\t\tconfirmColor: useConfigStore().themeColor['--primary-color'],
\t\tsuccess: res => {
\t\t\tif (res.confirm) orderClose(item.order_id).then(() => orderDetailFn(item.order_id));
\t\t}
\t});
};

const finish = (item : any) => {
\tuni.showModal({
\t\ttitle: '提示',
\t\tcontent: '您确定货品已收到吗？',
\t\tconfirmColor: useConfigStore().themeColor['--primary-color'],
\t\tsuccess: res => {
\t\t\tif (res.confirm) orderFinish(item.order_id).then(() => orderDetailFn(item.order_id));
\t\t}
\t});
};

const goodsEvent = (id : number) => {
\tredirect({ url: '/addon/campus_purchase/pages/goods/detail', param: { goods_id: id } });
};

const orderBtnFn = (type = '') => {
\tif (type == 'close') close(detail.value);
\telse if (type == 'finish') finish(detail.value);
\telse if (type == 'index') redirect({ url: '/addon/campus_purchase/pages/index', mode: 'reLaunch' });
\telse if (type == 'logistics' && detail.value.order_delivery.length > 0) {
\t\tlet params = { id: detail.value.order_delivery[0].id, mobile: detail.value.taker_mobile };
\t\tlet list : any = [];
\t\tdetail.value.order_delivery.forEach((item : any, index : number) => {
\t\t\titem.name = \`包裹\${index + 1}\`;
\t\t\tlist.push(item);
\t\t});
\t\tmaterialRef.value.open(params);
\t\tmaterialRef.value.packageList = list;
\t}
};

const showLogistics = (data: any) => {
\treturn data.delivery_type == 'express' && data.order_delivery && data.order_delivery.length;
};
</script>

<style lang="scss" scoped>
.bg-linear {
\tbackground: linear-gradient(90deg, var(--primary-color) 0%, var(--primary-color-dark) 100%);
}
.tab-bar-placeholder {
\tpadding-bottom: calc(constant(safe-area-inset-bottom) + 100rpx);
\tpadding-bottom: calc(env(safe-area-inset-bottom) + 100rpx);
}
</style>
`;
}

function diyIndexVue(diyName) {
  return `<template>
\t<view :style="themeColor()">
\t\t<loading-page :loading="diy.getLoading()"></loading-page>
\t\t<view v-show="!diy.getLoading()">
\t\t\t<view class="diy-template-wrap bg-index" :style="diy.pageStyle()">
\t\t\t\t<diy-group ref="diyGroupRef" :data="diy.data" :pullDownRefreshCount="diy.pullDownRefreshCount" />
\t\t\t</view>
\t\t</view>
\t\t<!-- #ifdef MP-WEIXIN -->
\t\t<wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
\t\t<!-- #endif -->
\t</view>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { useDiy } from '@/hooks/useDiy';
import diyGroup from '@/addon/components/diy/group/index.vue';

const diy = useDiy({ name: '${diyName}' });
const diyGroupRef = ref(null);
const wxPrivacyPopupRef:any = ref(null);

diy.onLoad();
diy.onShow(() => {
\tdiyGroupRef.value?.refresh();
\t// #ifdef MP
\tnextTick(() => {
\t\tif (wxPrivacyPopupRef.value) wxPrivacyPopupRef.value.proactive();
\t});
\t// #endif
});
diy.onHide();
diy.onUnload();
diy.onPullDownRefresh();
diy.onPageScroll();
</script>

<style lang="scss" scoped>
\t@import '@/styles/diy.scss';
</style>
<style lang="scss">
.diy-template-wrap {
  /* #ifdef MP */
  .child-diy-template-wrap {
    ::v-deep .diy-group {
      > .draggable-element.top-fixed-diy {
        display: block !important;
      }
    }
  }
  /* #endif */
}
</style>
`;
}

async function cleanupCampusUserSide() {
  const detailFile = path.join(paths.targetUni, "pages/goods/detail.vue");
  const paymentFile = path.join(paths.targetUni, "pages/order/payment.vue");
  const orderListFile = path.join(paths.targetUni, "pages/order/list.vue");
  const orderDetailFile = path.join(paths.targetUni, "pages/order/detail.vue");
  const orderInfoDiyFile = path.join(paths.targetUni, "components/diy/shop-order-info/index.vue");
  const memberInfoDiyFile = path.join(paths.targetUni, "components/diy/shop-member-info/index.vue");

  await fs.rm(path.join(paths.targetUni, "pages/member/my_coupon.vue"), { force: true });
  for (const file of [
    "locale/zh-Hans/pages.point.detail.json",
    "locale/zh-Hans/pages.point.order_list.json",
    "locale/zh-Hans/pages.refund.detail.json",
    "locale/zh-Hans/pages.refund.list.json",
  ]) {
    await fs.rm(path.join(paths.targetUni, file), { force: true });
  }

  if (await exists(detailFile)) {
    await replaceInFile(detailFile, (content) => {
      let next = content
        .replace("import { getGoodsDetail, collect, cancelCollect, getEvaluateList } from '@/addon/campus_purchase/api/goods';", "import { getGoodsDetail, collect, cancelCollect } from '@/addon/campus_purchase/api/goods';")
        .replace("import { getShopCouponList, getCoupon } from '@/addon/campus_purchase/api/coupon';\n", "")
        .replace(/\n\t\t\/\/ 获取优惠券列表\n\t\tgetShopCouponListFn\(\);\n/, "\n")
        .replace(/\n\t\t\/\/ 获取评价\n\t\tgetEvaluateListFn\(\);\n/, "\n\t\tbuildTraceabilityInfo();\n")
        .replace(/<view @click="couponListShow = true" v-if="couponList\.length" class="card-template-item">[\s\S]*?\n\t\t\t\t\t<\/view>\n\t\t\t\t<\/view>/, "</view>")
        .replace(/<view class="mt-\[var\(--top-m\)\] sidebar-margin card-template">\n\t\t\t\t\t<view class="flex items-center justify-between min-h-\[40rpx\]" :class="\{'mb-\[30rpx\]': evaluate && evaluate\.list && evaluate\.list\.length\}">[\s\S]*?\n\t\t\t\t<\/view>\n\n\t\t\t\t<view class="my-\[var\(--top-m\)\] goods-sku/, `<view class="mt-[var(--top-m)] sidebar-margin card-template">
\t\t\t\t\t<view class="flex items-center justify-between min-h-[40rpx]" :class="{'mb-[30rpx]': traceabilityList.length}">
\t\t\t\t\t\t<text class="title !mb-[0]">资质溯源({{ traceabilityList.length }})</text>
\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--text-color-light6)]">随批次更新</text>
\t\t\t\t\t</view>
\t\t\t\t\t<view>
\t\t\t\t\t\t<view :class="{'pb-[24rpx]': index != (traceabilityList.length-1)}" v-for="(item, index) in traceabilityList" :key="index">
\t\t\t\t\t\t\t<view class="flex items-center justify-between">
\t\t\t\t\t\t\t\t<view class="flex flex-col">
\t\t\t\t\t\t\t\t\t<text class="text-[28rpx] text-[#333] leading-[38rpx]">{{ item.name }}</text>
\t\t\t\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--text-color-light6)] mt-[8rpx]">{{ item.desc }}</text>
\t\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t\t\t<text class="text-[24rpx] text-[var(--primary-color)]">有效</text>
\t\t\t\t\t\t\t</view>
\t\t\t\t\t\t</view>
\t\t\t\t\t</view>
\t\t\t\t</view>

\t\t\t\t<view class="my-[var(--top-m)] goods-sku`);

      next = next.replace(/\/\/ 优惠券[\s\S]*?\/\/ 获取评价\nconst evaluate = ref\(\{\n\tcount : 0\n\}\)\n\nconst getEvaluateListFn = \(\) => \{\n\tgetEvaluateList\(goodsDetail\.value\.goods_id\)\.then\(\(res: any\) => \{\n\t\tevaluate\.value = res\.data\n\t\}\)\n\}\n\n\/\/进入评论\nconst toLink = \(\) => \{\n\tredirect\(\{ url: '\/addon\/campus_purchase\/pages\/evaluate\/list', param: \{ goods_id: goodsDetail\.value\.goods_id \} \}\)\n\}\n/, `// 资质溯源
const traceabilityList = ref<Array<{ name: string, desc: string }>>([])

const buildTraceabilityInfo = () => {
\tconst supplierName = goodsDetail.value.goods?.supplier_name || goodsDetail.value.goods?.brand_name || '平台供货商'
\ttraceabilityList.value = [
\t\t{ name: '食品经营许可证', desc: supplierName },
\t\t{ name: '生产许可证', desc: goodsDetail.value.goods?.goods_name || '按商品批次归档' },
\t\t{ name: '批次检测报告', desc: '到货验收时同步备案' }
\t]
}
`);
      next = next.replace(/\n\t\t\t<!-- 优惠券 -->[\s\S]*?\n\t\t\t<\/view>\n\t\t\t<ns-goods-sku/, "\n\t\t\t<ns-goods-sku");
      return next;
    });
  }

  if (await exists(paymentFile)) {
    await replaceInFile(paymentFile, (content) => {
      return content
        .replace(/<view class="mb-\[var\(--top-m\)\] card-template" v-if="couponRef && couponList\.length">[\s\S]*?\n\t\t\t\t<\/view>\n\n/, "")
        .replace(/<view class="card-template-item" v-if="orderData\.basic\.discount_money">[\s\S]*?\n\t\t\t\t\t<\/view>\n/, "")
        .replace("提交订单", "提交采购单")
        .replace(/\n            <!-- 选择优惠券 -->\n            <select-coupon :order-key="createData\.order_key" ref="couponRef" @confirm="confirmSelectCoupon" \/>/, "")
        .replace(/\n            <pay ref="payRef" @close="payClose" \/>/, "")
        .replace("import selectCoupon from './components/select-coupon/select-coupon'\n", "")
        .replace("const couponRef = ref()\n", "")
        .replace("const payRef = ref()\n", "")
        .replace("    discount: {},\n", "")
        .replace("    useSubscribeMessage().request('shop_order_pay,shop_order_delivery')", "    useSubscribeMessage().request('shop_order_delivery')")
        .replace(/    orderCreate\(createData\.value\)\.then\(\(\{ data \}\) => \{\n        orderId = data\.order_id\n        if \(orderData\.value\.basic\.order_money == 0\) \{\n            redirect\(\{ url: '\/addon\/campus_purchase\/pages\/order\/detail', param: \{ order_id: orderId \}, mode: 'redirectTo' \}\)\n        \} else \{\n            payRef\.value\?\.open\(data\.trade_type, data\.order_id, `\/addon\/campus_purchase\/pages\/order\/detail\?order_id=\$\{ data\.order_id \}`\)\n        \}\n    \}\)\.catch\(\(\) => \{\n/, "    orderCreate(createData.value).then(({ data }) => {\n        orderId = data.order_id\n        redirect({ url: '/addon/campus_purchase/pages/order/detail', param: { order_id: orderId }, mode: 'redirectTo' })\n    }).catch(() => {\n")
        .replace(/\n\/\*\*\n \* 支付弹窗关闭\n \*\/\nconst payClose = \(\) => \{\n    redirect\(\{ url: '\/addon\/campus_purchase\/pages\/order\/detail', param: \{ order_id: orderId \}, mode: 'redirectTo' \}\)\n\}\n/, "\n")
        .replace(/\nconst couponList = computed\(\(\) => \{\n    return couponRef\.value\?\.couponList \|\| \[\]\n\}\)\n\n\/\*\*\n \* 选择优惠券\n \*\/\nconst confirmSelectCoupon = \(coupon: any\) => \{\n    createData\.value\.discount\.coupon_id = coupon \? coupon\.id : 0\n    calculate\(\)\n\}\n/, "\n");
    });
  }

  if (await exists(orderListFile)) {
    await replaceInFile(orderListFile, (content) => {
      return content
        .replace(/<view class="flex justify-end text-\[28rpx\] mt-\[20rpx\] items-center" v-if="\((item\.status == 1)\) \|\| \((item\.status == 3)\) \|\| \(item\.status == 5 && evaluateConfig\.is_evaluate == 1\)">/, `<view class="flex justify-end text-[28rpx] mt-[20rpx] items-center" v-if="(item.status == 1) || (item.status == 3)">`)
        .replace(/\n\t\t\t\t\t\t\t<view class="text-\[24rpx\][^\n]*v-if="item\.status == 1" @click\.stop="orderBtnFn\(item, 'pay'\)">\{\{ t\('topay'\) \}\}<\/view>/, "")
        .replace(/\n\t\t\t\t\t\t\t<view class="text-\[24rpx\][\s\S]*?orderBtnFn\(item, 'evaluate'\)">\{\{ item\.is_evaluate == 1 \? t\('selectedEvaluate'\) : t\('evaluate'\) \}\}<\/view>/, "")
        .replace(/\n\t\t<pay ref="payRef" @close="payClose"><\/pay>/, "")
        .replace("import { getEvaluateConfig } from '@/addon/campus_purchase/api/shop';\n", "")
        .replace("const evaluateConfig = ref(\"\")\n", "")
        .replace("\tevaluateEvent()\n", "")
        .replace(/\nconst evaluateEvent = \(\) => \{\n\tgetEvaluateConfig\(\)\.then\(\(data: any\) => \{\n\t\tevaluateConfig\.value = data\.data\n\t\}\)\n\}\n/, "\n")
        .replace(/\n\/\/ 支付\nconst payRef = ref\(null\)\n/, "\n")
        .replace(/\tif \(type == 'pay'\)\n\t\tpayRef\.value\?\.open\(data\.order_type, data\.order_id, `\/addon\/campus_purchase\/pages\/order\/detail\?order_id=\$\{data\.order_id\}`\);\n\telse if \(type == 'close'\) \{/, "\tif (type == 'close') {")
        .replace(/ else if \(type == 'evaluate'\) \{\n\t\tif \(!data\.is_evaluate\) \{\n\t\t\tredirect\(\{ url: '\/addon\/campus_purchase\/pages\/evaluate\/order_evaluate', param: \{ order_id: data\.order_id \} \}\)\n\t\t\} else \{\n\t\t\tredirect\(\{ url: '\/addon\/campus_purchase\/pages\/evaluate\/order_evaluate_view', param: \{ order_id: data\.order_id \} \}\)\n\t\t\}\n\t\}/, "");
    });
  }

  if (await exists(orderDetailFile)) {
    await replaceInFile(orderDetailFile, (content) => {
      return content
        .replace(/<view class="flex justify-end  self-end w-\[100%\] mt-\[30rpx\]" v-if="\((goodsItem\.status != '1')\) \|\| \(goodsItem\.is_enable_refund == 1\)">[\s\S]*?\n\t\t\t\t\t\t<\/view>/, "")
        .replace(/<view class=" card-template-item justify-between">\n\t\t\t\t\t\t<view class="text-\[28rpx\]">\{\{ t\('discountMoney'\) \}\}<\/view>[\s\S]*?\n\t\t\t\t\t<\/view>/, "")
        .replace(/\n\t\t\t\t\t\t<view class="min-w-\[180rpx\][^\n]*v-if="detail\.status == 1" @click="orderBtnFn\('pay'\)">\{\{ t\('topay'\) \}\}<\/view>/, "")
        .replace(/<block v-if="detail\.status == 5">[\s\S]*?\n\t\t\t\t\t\t<\/block>/, "")
        .replace(/\n\t\t\t<pay ref="payRef" @close="payClose"><\/pay>/, "")
        .replace("import { getEvaluateConfig } from '@/addon/campus_purchase/api/shop';\n", "")
        .replace("const evaluateConfig = ref<Object>({});\n", "")
        .replace(/\n\tgetEvaluateConfig\(\)\.then\(\(\{ data \}\) => \{\n\t\tevaluateConfig\.value = data\n\t\}\)\n/, "\n")
        .replace(/const goodsEvent = \(id : number\) => \{\n\t\tif \(detail\.value\.activity_type == 'exchange'\) \{[\s\S]*?\n\t\t\}\n\n\t\}/, `const goodsEvent = (id : number) => {
\t\tredirect({
\t\t\turl: '/addon/campus_purchase/pages/goods/detail',
\t\t\tparam: {
\t\t\t\tgoods_id: id
\t\t\t}
\t\t})
\t}`)
        .replace(/\n\tconst payRef = ref\(null\)/, "")
        .replace(/\t\tif \(type == 'pay'\)\n\t\t\tpayRef\.value\?\.open\(detail\.value\.order_type, detail\.value\.order_id, `\/addon\/campus_purchase\/pages\/order\/detail\?order_id=\$\{detail\.value\.order_id\}`\);\n\t\telse if \(type == 'close'\) \{/, "\t\tif (type == 'close') {")
        .replace(/ else if \(type == 'evaluate'\) \{\n\t\t\tif \(!detail\.value\.is_evaluate\) \{\n\t\t\t\tredirect\(\{ url: '\/addon\/campus_purchase\/pages\/evaluate\/order_evaluate', param: \{ order_id: detail\.value\.order_id \} \}\)\n\t\t\t\} else \{\n\t\t\t\tredirect\(\{ url: '\/addon\/campus_purchase\/pages\/evaluate\/order_evaluate_view', param: \{ order_id: detail\.value\.order_id \} \}\)\n\t\t\t\}\n\t\t\}/, "")
        .replace(/\n\tconst applyRefund = \(orderGoodsId : number\) => \{\n\t\tredirect\(\{\n\t\t\turl: '\/addon\/campus_purchase\/pages\/refund\/apply',\n\t\t\tparam: \{\n\t\t\t\torder_id: detail\.value\.order_id,\n\t\t\t\torder_goods_id: orderGoodsId\n\t\t\t\}\n\t\t\}\)\n\t\}\n/, "\n");
    });
  }

  if (await exists(orderInfoDiyFile)) {
    await replaceInFile(orderInfoDiyFile, (content) => {
      return content
        .replace(/<view class="flex flex-col items-center w-\[20%\] flex-shrink-0" @click="redirect\(\{ url: '\/addon\/campus_purchase\/pages\/order\/list', param: \{ status: 5 \}\}\)">[\s\S]*?\n\t\t\t<\/view>\n\t\t\t<view class="flex flex-col items-center w-\[20%\] flex-shrink-0" @click="redirect\(\{ url: '\/addon\/campus_purchase\/pages\/refund\/list'\}\)">[\s\S]*?\n\t\t\t<\/view>/, "")
        .replaceAll("w-[20%]", "w-[25%]");
    });
  }

  if (await exists(memberInfoDiyFile)) {
    await replaceInFile(memberInfoDiyFile, (content) => {
      return content
        .replace(/<view class="text-center w-\[33\.333%\] flex-shrink-0" @click="redirect\(\{url: '\/app\/pages\/member\/point'\}\)">[\s\S]*?\n\t\t\t\t<\/view>\n\t\t\t\t<view class="text-center w-\[33\.333%\] flex-shrink-0" @click="redirect\(\{ url: '\/addon\/campus_purchase\/pages\/member\/my_coupon' \}\)">[\s\S]*?\n\t\t\t\t<\/view>/, "")
        .replace("w-[33.333%]", "w-[50%]");
    });
  }

  await writeIfChanged(detailFile, goodsDetailVue());
  await writeIfChanged(paymentFile, orderPaymentVue());
  await writeIfChanged(orderListFile, orderListVue());
  await writeIfChanged(orderDetailFile, orderDetailVue());
  await writeIfChanged(path.join(paths.targetUni, "pages/index.vue"), diyIndexVue("DIY_SHOP_INDEX"));
  await writeIfChanged(path.join(paths.targetUni, "pages/member/index.vue"), diyIndexVue("DIY_SHOP_MEMBER_INDEX"));

  await writeIfChanged(path.join(paths.targetUni, "components/diy/goods-coupon/index.vue"), `<template></template>

<script setup lang="ts">
// 校园采购 1.0 不启用优惠券装修组件。
</script>
`);

  await writeIfChanged(path.join(paths.targetUni, "components/diy/shop-exchange-goods/index.vue"), `<template></template>

<script setup lang="ts">
// 校园采购 1.0 不启用积分兑换商品组件。
</script>
`);

  await writeIfChanged(path.join(paths.targetUni, "components/diy/shop-exchange-info/index.vue"), `<template></template>

<script setup lang="ts">
// 校园采购 1.0 不启用积分账户组件。
</script>
`);

  await writeIfChanged(orderInfoDiyFile, `<template>
\t<view :style="warpCss">
\t\t<view class="diy-text relative">
\t\t\t<view class="px-[var(--pad-sidebar-m)] pt-[var(--pad-top-m)] pb-[40rpx] flex items-center justify-between">
\t\t\t\t<view @click="diyStore.toRedirect(diyComponent.link)">
\t\t\t\t\t<view class="max-w-[200rpx] truncate leading-[1] text-[30rpx]" :style="{ fontSize: diyComponent.fontSize * 2 + 'rpx', color: diyComponent.textColor, fontWeight: (diyComponent.fontWeight == 'normal' ? 500 : diyComponent.fontWeight) }">{{ diyComponent.text }}</view>
\t\t\t\t</view>
\t\t\t\t<view class="flex items-center" @click="redirect({ url: '/addon/campus_purchase/pages/order/list'})">
\t\t\t\t\t<text class="max-w-[200rpx] truncate text-[24rpx]" :style="{ color: diyComponent.more.color }">{{ diyComponent.more.text }}</text>
\t\t\t\t\t<text class="nc-iconfont nc-icon-youV6xx text-[24rpx]" :style="{ color: diyComponent.more.color }"></text>
\t\t\t\t</view>
\t\t\t</view>
\t\t</view>
\t\t<view class="pb-[var(--pad-top-m)] px-[var(--pad-sidebar-m)] flex items-center justify-between text-center">
\t\t\t<view class="flex flex-col items-center w-[33.333%] flex-shrink-0" @click="toList(1)">
\t\t\t\t<view class="relative w-[44rpx] h-[44rpx]">
\t\t\t\t\t<image class="w-[44rpx] h-[44rpx]" :src="img('addon/shop/diy/member/order1.png')" />
\t\t\t\t\t<view v-if="orderInfo.wait_pay" class="absolute left-[35rpx] top-[-10rpx] rounded-[28rpx] h-[28rpx] min-w-[28rpx] text-center leading-[30rpx] bg-[#FF4646] text-[#fff] text-[20rpx] font-500 box-border">{{ orderInfo.wait_pay > 99 ? "99+" : orderInfo.wait_pay }}</view>
\t\t\t\t</view>
\t\t\t\t<view class="mt-[20rpx] leading-[1]" :style="{ fontSize: diyComponent.item.fontSize * 2 + 'rpx', color: diyComponent.item.color, fontWeight: diyComponent.item.fontWeight }">待确认</view>
\t\t\t</view>
\t\t\t<view class="flex flex-col items-center w-[33.333%] flex-shrink-0" @click="toList(2)">
\t\t\t\t<view class="relative w-[44rpx] h-[44rpx]">
\t\t\t\t\t<image class="w-[44rpx] h-[44rpx]" :src="img('addon/shop/diy/member/order2.png')" />
\t\t\t\t\t<view v-if="orderInfo.wait_shipping" class="absolute left-[35rpx] top-[-10rpx] rounded-[28rpx] h-[28rpx] min-w-[28rpx] text-center leading-[30rpx] bg-[#FF4646] text-[#fff] text-[20rpx] font-500 box-border">{{ orderInfo.wait_shipping > 99 ? "99+" : orderInfo.wait_shipping }}</view>
\t\t\t\t</view>
\t\t\t\t<view class="mt-[20rpx] leading-[1]" :style="{ fontSize: diyComponent.item.fontSize * 2 + 'rpx', color: diyComponent.item.color, fontWeight: diyComponent.item.fontWeight }">待配送</view>
\t\t\t</view>
\t\t\t<view class="flex flex-col items-center w-[33.333%] flex-shrink-0" @click="toList(3)">
\t\t\t\t<view class="relative w-[44rpx] h-[44rpx]">
\t\t\t\t\t<image class="w-[44rpx] h-[44rpx]" :src="img('addon/shop/diy/member/order3.png')" />
\t\t\t\t\t<view v-if="orderInfo.wait_take" class="absolute left-[35rpx] top-[-10rpx] rounded-[28rpx] h-[28rpx] min-w-[28rpx] text-center leading-[30rpx] bg-[#FF4646] text-[#fff] text-[20rpx] font-500 box-border">{{ orderInfo.wait_take > 99 ? "99+" : orderInfo.wait_take }}</view>
\t\t\t\t</view>
\t\t\t\t<view class="mt-[20rpx] leading-[1]" :style="{ fontSize: diyComponent.item.fontSize * 2 + 'rpx', color: diyComponent.item.color, fontWeight: diyComponent.item.fontWeight }">待收货</view>
\t\t\t</view>
\t\t</view>
\t</view>
</template>

<script lang="ts" setup>
import { ref, computed, watch, onMounted } from 'vue';
import useDiyStore from '@/app/stores/diy';
import { img, redirect } from '@/utils/common';
import { getShopOrderNum } from '@/addon/campus_purchase/api/order';

const props = defineProps(['component', 'index', 'pullDownRefreshCount']);
const diyStore = useDiyStore();
const diyComponent = computed(() => diyStore.mode == 'decorate' ? diyStore.value[props.index] : props.component);
const orderInfo = ref<any>({});

onMounted(() => refresh());
watch(() => diyComponent.value, () => refresh(), { deep: true });

const refresh = () => {
\tif (diyStore.mode == 'decorate') {
\t\torderInfo.value = {};
\t} else {
\t\tgetShopOrderNum().then((res:any) => { orderInfo.value = res.data; });
\t}
};

const warpCss = computed(() => {
\tlet style = 'position:relative;';
\tif (diyComponent.value.componentStartBgColor) {
\t\tif (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += \`background:linear-gradient(\${diyComponent.value.componentGradientAngle},\${diyComponent.value.componentStartBgColor},\${diyComponent.value.componentEndBgColor});\`;
\t\telse style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
\t}
\tif (diyComponent.value.componentBgUrl) {
\t\tstyle += \`background-image:url('\${ img(diyComponent.value.componentBgUrl) }');\`;
\t\tstyle += 'background-size: cover;background-repeat: no-repeat;';
\t}
\tif (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
\tif (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
\tif (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
\tif (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
\treturn style;
});

const toList = (status:any) => {
\tredirect({ url: '/addon/campus_purchase/pages/order/list', param: { status } });
};
</script>
`);

  await writeIfChanged(memberInfoDiyFile, `<template>
\t<view :style="warpCss">
\t\t<view class="px-[30rpx] pt-[30rpx] box-border pb-[30rpx]">
\t\t\t<view v-if="info" class="flex items-center">
\t\t\t\t<u-avatar :src="img(info.headimg)" :size="'110rpx'" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')" @click="clickAvatar" />
\t\t\t\t<view class="ml-[20rpx] flex-1">
\t\t\t\t\t<view class="text-[#ffffff] flex items-baseline flex-wrap" :style="{ color : diyComponent.textColor }">
\t\t\t\t\t\t<view class="text-[32rpx] truncate max-w-[320rpx] font-500 leading-[38rpx]">{{ info.nickname }}</view>
\t\t\t\t\t\t<view class="text-[26rpx] leading-[28rpx] ml-[10rpx]" v-if="info.mobile">{{ info.mobile.replace(info.mobile.substring(3,7), "****") }}</view>
\t\t\t\t\t</view>
\t\t\t\t\t<view class="text-[#666] text-[24rpx] leading-[28rpx] mt-[14rpx]" :style="{ color : diyComponent.uidTextColor }">学校账号：{{ info.member_no }}</view>
\t\t\t\t</view>
\t\t\t\t<text @click="redirect({ url: '/app/pages/setting/index' })" class="nc-iconfont nc-icon-shezhiV6xx1 text-[38rpx] ml-[10rpx]" :style="{ color : diyComponent.textColor }"></text>
\t\t\t</view>
\t\t\t<view v-else class="flex items-center">
\t\t\t\t<u-avatar :src="img('static/resource/images/default_headimg.png')" :size="'100rpx'" @click="toLogin" />
\t\t\t\t<view class="ml-[20rpx] flex-1" @click="toLogin">
\t\t\t\t\t<view class="text-[32rpx] font-500 leading-[38rpx]" :style="{ color : diyComponent.textColor }">{{ t('login') }}/{{ t('register') }}</view>
\t\t\t\t</view>
\t\t\t\t<view @click="redirect({ url: '/app/pages/setting/index' })">
\t\t\t\t\t<text class="nc-iconfont nc-icon-shezhiV6xx1 text-[38rpx] ml-[10rpx]" :style="{ color : diyComponent.textColor }"></text>
\t\t\t\t</view>
\t\t\t</view>
\t\t</view>
\t</view>
</template>

<script lang="ts" setup>
import { computed, watch } from 'vue';
import useMemberStore from '@/stores/member';
import { useLogin } from '@/hooks/useLogin';
import { img, isWeixinBrowser, redirect, urlDeconstruction } from '@/utils/common';
import { t } from '@/locale';
import { wechatSync } from '@/app/api/system';
import useDiyStore from '@/app/stores/diy';
import useConfigStore from '@/stores/config';

const props = defineProps(['component', 'index', 'pullDownRefreshCount','global']);
const configStore = useConfigStore();
const diyStore = useDiyStore();
const memberStore = useMemberStore();

const diyComponent = computed(() => diyStore.mode == 'decorate' ? diyStore.value[props.index] : props.component);

const warpCss = computed(() => {
\tlet style = '';
\tif (diyComponent.value.componentStartBgColor) {
\t\tif (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += \`background:linear-gradient(\${diyComponent.value.componentGradientAngle},\${diyComponent.value.componentStartBgColor},\${diyComponent.value.componentEndBgColor});\`;
\t\telse style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
\t}
\tif (diyComponent.value.bgUrl) {
\t\tstyle += 'background-image:url(' + img(diyComponent.value.bgUrl) + ');';
\t\tstyle += 'background-size: 100%;background-repeat: no-repeat;';
\t}
\tif (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
\tif (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
\tif (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
\tif (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
\treturn style;
});

watch(() => props.pullDownRefreshCount, () => {});

// #ifdef H5
const { query } = urlDeconstruction(location.href);
if (query.code && isWeixinBrowser()) {
\tsetTimeout(() =>{
\t\twechatSync({ code: query.code }).then(() => memberStore.getMemberInfo());
\t}, 1500);
}
// #endif

const info = computed(() => {
\tif (diyStore.mode == 'decorate') {
\t\treturn { headimg: '', nickname: '学校账号', mobile: '155****0549', member_no: 'SCHOOL0001' };
\t}
\treturn memberStore.info;
});

const toLogin = () => {
\tlet normalLogin = !configStore.login.is_username && !configStore.login.is_mobile && !configStore.login.is_bind_mobile;
\tlet authRegisterLogin = !configStore.login.is_auth_register;
\t// #ifdef H5
\tif (isWeixinBrowser()) {
\t\tif (normalLogin && authRegisterLogin) uni.showToast({ title: '商家未开启登录注册', icon: 'none' });
\t\telse if (configStore.login.is_username || configStore.login.is_mobile || configStore.login.is_bind_mobile) useLogin().setLoginBack({ url: '/addon/campus_purchase/pages/member/index' });
\t\telse if (normalLogin && configStore.login.is_auth_register) useLogin().getAuthCode({ scopes: 'snsapi_userinfo' });
\t} else {
\t\tif (normalLogin) uni.showToast({ title: '商家未开启登录注册', icon: 'none' });
\t\telse useLogin().setLoginBack({ url: '/addon/campus_purchase/pages/member/index' });
\t}
\t// #endif
\t// #ifdef MP
\tif (normalLogin && authRegisterLogin) uni.showToast({ title: '商家未开启登录注册', icon: 'none' });
\telse if (configStore.login.is_username || configStore.login.is_mobile || configStore.login.is_bind_mobile) useLogin().setLoginBack({ url: '/addon/campus_purchase/pages/member/index' });
\telse if (normalLogin && configStore.login.is_auth_register) useLogin().getAuthCode();
\t// #endif
};

const clickAvatar = () => {
\t// #ifdef H5
\tif (isWeixinBrowser()) useLogin().getAuthCode({ scopes: 'snsapi_userinfo' });
\telse redirect({ url: '/app/pages/member/personal' });
\t// #endif
};
</script>
`);

  await replaceInTextFiles(paths.targetUni, (content) => normalizeCampusVisualRefs(content));

  await fs.rm(path.join(paths.targetUni, "api/coupon.ts"), { force: true });
  await fs.rm(path.join(paths.targetUni, "api/point.ts"), { force: true });
  await fs.rm(path.join(paths.targetUni, "pages/order/components/select-coupon"), { recursive: true, force: true });

  await fs.rm(path.join(paths.targetAddon, "uni-app"), { recursive: true, force: true });
  await fs.cp(paths.targetUni, path.join(paths.targetAddon, "uni-app"), { recursive: true });

  await fs.writeFile(path.join(paths.targetAddon, "package/uni-app-pages.php"), compactUniPagesPhp());

  let pagesJson = await fs.readFile(paths.pagesJson, "utf8");
  const pageBlocks = [];
  const cmsBlock = await getAddonPackageBlock("cms");
  if (cmsBlock) pageBlocks.push(cmsBlock);
  const campusBlock = await getAddonPackageBlock(pluginKey);
  if (campusBlock) pageBlocks.push(campusBlock);
  if (pagesJson.includes("// {{ PAGE_BEGAIN }}") && pagesJson.includes("// {{ PAGE_END }}")) {
    pagesJson = pagesJson.replace(/(.*\/\/ \{\{ PAGE_BEGAIN \}\})([\s\S]*?)(\/\/ \{\{ PAGE_END \}\}.*)/s, (_, before, _middle, after) => {
      return `${before}\n${pageBlocks.join("\n")}\n${after}`;
    });
  }
  await fs.writeFile(paths.pagesJson, pagesJson);
}

async function main() {
  if (await exists(paths.targetAddon) || await exists(paths.targetUni)) {
    await cleanupCampusUserSide();
    console.log(`Cleaned ${pluginKey}`);
    console.log(paths.targetAddon);
    console.log(paths.targetUni);
    return;
  }

  await copyDir(paths.sourceAddon, paths.targetAddon);
  await copyDir(paths.sourceUni, paths.targetUni);

  await fs.writeFile(path.join(paths.targetAddon, "info.json"), JSON.stringify({
    title: pluginTitle,
    desc: "校园采购前端演示版：学校采购、资质溯源、采购单、采购记录",
    key: pluginKey,
    version: "1.0.0",
    author: "niucloud",
    type: "app",
    support_app: "",
    support_version: "1.6.1",
  }, null, 2) + "\n");

  await replaceInTextFiles(paths.targetAddon, (content) => {
    return normalizeCampusVisualRefs(content)
      .replaceAll("namespace addon\\shop", `namespace addon\\${pluginKey}`)
      .replaceAll("use addon\\shop", `use addon\\${pluginKey}`)
      .replaceAll("'key' => 'shop'", `'key' => '${pluginKey}'`)
      .replaceAll("\"key\": \"shop\"", `"key": "${pluginKey}"`)
      .replaceAll("商城系统", pluginTitle)
      .replaceAll("商城", "校园采购");
  });

  await replaceInTextFiles(paths.targetUni, (content, file) => {
    let next = normalizeCampusVisualRefs(content)
      .replaceAll("@/addon/shop", `@/addon/${pluginKey}`)
      .replaceAll("/addon/shop", `/addon/${pluginKey}`);

    if (!file.endsWith(path.join("api", "goods.ts"))
      && !file.endsWith(path.join("api", "order.ts"))
      && !file.endsWith(path.join("api", "cart.ts"))
      && !file.endsWith(path.join("api", "config.ts"))
      && !file.endsWith(path.join("api", "shop.ts"))) {
      next = next.replaceAll("购物车", "采购单")
        .replaceAll("去结算", "去提交")
        .replaceAll("结算", "提交")
        .replaceAll("立即购买", "立即采购")
        .replaceAll("加入购物车", "加入采购单")
        .replaceAll("订单列表", "采购记录")
        .replaceAll("订单详情", "采购详情")
        .replaceAll("待付款订单", "提交采购单")
        .replaceAll("优惠券", "优惠券")
        .replaceAll("宝贝评价", "检测报告");
    }
    return next;
  });

  for (const dir of removeUserPageDirs) {
    await fs.rm(path.join(paths.targetUni, "pages", dir), { recursive: true, force: true });
    await fs.rm(path.join(paths.targetAddon, "uni-app/pages", dir), { recursive: true, force: true });
  }
  for (const file of removeUserApis) {
    await fs.rm(path.join(paths.targetUni, "api", file), { force: true });
    await fs.rm(path.join(paths.targetAddon, "uni-app/api", file), { force: true });
  }

  const locale = {
    "pages.index": "校园采购",
    "pages.goods.search": "搜索",
    "pages.goods.cart": "采购单",
    "pages.goods.category": "采购分类",
    "pages.goods.detail": "商品详情",
    "pages.goods.list": "商品列表",
    "pages.member.index": "我的",
    "pages.order.list": "采购记录",
    "pages.order.detail": "采购详情",
    "pages.order.payment": "提交采购单",
  };
  await fs.writeFile(path.join(paths.targetUni, "locale/zh-Hans.json"), JSON.stringify(locale, null, 4) + "\n");
  await fs.writeFile(path.join(paths.targetAddon, "uni-app/locale/zh-Hans.json"), JSON.stringify(locale, null, 4) + "\n");

  await fs.writeFile(path.join(paths.targetAddon, "package/uni-app-pages.php"), compactUniPagesPhp());

  let pagesJson = await fs.readFile(paths.pagesJson, "utf8");
  if (!pagesJson.includes("CAMPUS_PURCHASE_PAGE_BEGIN")) {
    pagesJson = pagesJson.replace("        // SHOP_PAGE_BEGIN", pagesJsonBlock() + "        // SHOP_PAGE_BEGIN");
    await fs.writeFile(paths.pagesJson, pagesJson);
  }

  await cleanupCampusUserSide();

  console.log(`Created ${pluginKey}`);
  console.log(paths.targetAddon);
  console.log(paths.targetUni);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
