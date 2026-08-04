<template>
    <div class="main-container category-config-page">
        <div class="page-heading">
            <div>
                <div class="text-page-title">{{ pageName }}</div>
                <div class="page-heading__desc">配置用户端分类页的层级、搜索和商品操作，保存后立即生效。</div>
            </div>
            <el-button @click="getCategoryConfigFn">刷新配置</el-button>
        </div>
        <el-tabs class="category-tabs" model-value="/phone_shop/goods/category/config" @tab-change="handleClick">
            <el-tab-pane :label="t('tabGoodsCategory')" name="/phone_shop/goods/category" />
            <el-tab-pane :label="t('tabGoodsCategoryConfig')" name="/phone_shop/goods/category/config" />
        </el-tabs>
        <el-form v-if="Object.keys(formData).length" :model="formData" label-width="118" ref="formRef" :rules="rules" class="page-form" v-loading="loading">
            <div class="config-layout">
                <div class="config-main">
                    <section class="config-card">
                        <div class="section-heading">
                            <span class="section-index">01</span>
                            <div>
                                <h3>选择分类结构</h3>
                                <p>按照现有商品分类层级选择。二手机目录推荐使用三级分类。</p>
                            </div>
                        </div>
                        <div class="level-options">
                            <button v-for="item in levelOptions" :key="item.value" type="button" class="level-option" :class="{ active: Number(formData.level) === item.value }" @click="changeLevel(item.value)">
                                <span class="level-option__title">{{ item.title }}</span>
                                <span class="level-option__desc">{{ item.desc }}</span>
                                <span v-if="Number(formData.level) === item.value" class="level-option__check">✓</span>
                            </button>
                        </div>
                        <div class="template-options">
                            <button v-for="(item,index) in config['level_'+formData.level]" :key="index" type="button" class="template-option" :class="{ active: formData.template === item.template }" @click="levelChange(item.template)">
                                <img :src="img(item.preview)" />
                                <span>{{ templateName(item.template, index) }}</span>
                                <i v-if="formData.template === item.template">当前使用</i>
                            </button>
                        </div>
                    </section>

                    <section class="config-card">
                        <div class="section-heading">
                            <span class="section-index">02</span>
                            <div>
                                <h3>{{ t('pageSettings') }}</h3>
                                <p>控制页面名称、搜索入口与二手机信息展示。</p>
                            </div>
                        </div>
                        <el-form-item :label="t('pageTitle')" prop="page_title">
                    <el-input v-model.trim="formData.page_title" clearable :placeholder="t('pageTitlePlaceholder')" class="input-width" maxlength="10" show-word-limit />
                </el-form-item>
                <el-form-item :label="t('searchControl')">
                    <el-radio-group class="mx-[10px]" v-model="formData.search.control">
                        <el-radio :label="1">{{ t('open') }}</el-radio>
                        <el-radio :label="0">{{ t('close') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="formData.search.control" :label="t('searchTitle')" prop="search.title">
                    <el-input v-model.trim="formData.search.title" clearable :placeholder="t('searchTitlePlaceholder')" class="input-width" maxlength="12" show-word-limit />
                </el-form-item>
                <el-form-item v-if="Number(formData.level) === 3" label="成色/质检">
                    <el-switch v-model="formData.show_quality" :active-value="1" :inactive-value="0" />
                    <span class="text-[12px] text-[#999] ml-[10px]">开启后，商品图片左上角显示成色；存在质检异常时右上角同时提醒。</span>
                </el-form-item>
                <el-form-item v-if="Number(formData.level) === 3" label="仓库切换">
                    <el-switch v-model="formData.warehouse_switch" :active-value="1" :inactive-value="0" />
                    <span class="text-[12px] text-[#999] ml-[10px]">代理子站:开启后分类页顶部显示「本地仓/代理仓」切换(默认关,关=显示全部)</span>
                </el-form-item>
                    </section>

                    <section class="config-card">
                        <div class="section-heading">
                            <span class="section-index">03</span>
                            <div>
                                <h3>商品操作</h3>
                                <p>决定分类页商品卡片是否显示操作按钮，以及点击后的动作。</p>
                            </div>
                        </div>
                        <div class="action-options">
                            <button v-for="item in actionOptions" :key="item.value" type="button" class="action-option" :class="{ active: actionMode === item.value }" @click="actionMode = item.value">
                                <span class="action-option__icon nc-iconfont" :class="item.icon"></span>
                                <span class="action-option__content">
                                    <b>{{ item.title }}</b>
                                    <small>{{ item.desc }}</small>
                                </span>
                                <span v-if="actionMode === item.value" class="action-option__check">✓</span>
                            </button>
                        </div>

                        <div v-if="actionMode !== 'hidden'" class="action-content">
                            <div class="action-content__title">
                                <div>
                                    <b>按钮内容与样式</b>
                                    <p>只配置当前动作需要的内容，其他设置不会干扰用户端。</p>
                                </div>
                                <el-tag effect="plain">{{ currentActionName }}</el-tag>
                            </div>
                            <el-form-item v-if="actionMode !== 'cart'" label="按钮文字" prop="cart.text">
                                <el-input v-model.trim="formData.cart.text" clearable :placeholder="actionMode === 'download' ? '如：一键转发' : '如：查看'" class="input-width" maxlength="6" show-word-limit />
                            </el-form-item>
                            <el-form-item :label="t('cartStyle')" class="carStyle">
                                <div class="action-style-options">
                                    <button type="button" class="action-style-option" :class="{ active: formData.cart.style === 'style-1' }" @click="carStyleClick(1)">
                                        <span class="action-style-button">{{ formData.cart.text || currentActionName }}</span>
                                        <small>文字按钮</small>
                                    </button>
                                    <button v-if="actionMode === 'cart'" type="button" class="action-style-option" :class="{ active: formData.cart.style === 'style-3' }" @click="carStyleClick(3)">
                                        <span class="text-color nc-iconfont nc-icon-gouwucheV6xx6 !text-[24px]"></span>
                                        <small>线性图标</small>
                                    </button>
                                    <button v-if="actionMode === 'cart'" type="button" class="action-style-option" :class="{ active: formData.cart.style === 'style-4' }" @click="carStyleClick(4)">
                                        <span class="action-style-circle"><i class="nc-iconfont nc-icon-gouwucheV6xx6"></i></span>
                                        <small>圆形图标</small>
                                    </button>
                                </div>
                            </el-form-item>
                            <div v-if="actionMode === 'download'" class="action-tip">下载商品图片并复制商品信息；首次使用时由用户设置转发加价规则。</div>
                            <div v-else-if="actionMode === 'detail'" class="action-tip">商品卡片仍可整体点击，右侧按钮用于给用户一个更明确的查看提示。</div>
                        </div>
                        <div v-else class="action-disabled-tip">商品卡片不显示独立操作按钮，用户仍可点击卡片进入商品详情。</div>
                    </section>
                </div>

                <aside class="config-preview">
                    <div class="preview-card">
                        <div class="preview-head">
                            <div>
                                <h3>页面预览</h3>
                                <p>预览用于确认布局结构，实际内容以商品数据为准。</p>
                            </div>
                            <span>{{ currentLevelName }}</span>
                        </div>
                        <div class="phone-frame">
                            <div class="phone-speaker"></div>
                            <img :src="img(currentPreview)" />
                        </div>
                        <div class="preview-summary">
                            <div><span>搜索栏</span><b>{{ formData.search?.control ? '显示' : '隐藏' }}</b></div>
                            <div><span>商品操作</span><b>{{ currentActionName }}</b></div>
                            <div v-if="Number(formData.level) === 3"><span>质检摘要</span><b>{{ formData.show_quality ? '显示' : '隐藏' }}</b></div>
                        </div>
                    </div>
                </aside>
            </div>
        </el-form>
        <div class="fixed-footer-wrap">
            <div class="fixed-footer">
                <el-button type="primary" @click="onSave(formRef)">{{ t('save') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from 'vue'
import { t } from '@/lang'
import { getCategoryConfig, setCategoryConfig } from '@/addon/phone_shop/api/goods'
import { img } from '@/utils/common'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const pageName = route.meta.title
interface formDataType {
    level: string|number
    template: string
    page_title: string
    search: {
        title: string
        control: number|string
    }
    sort: string
    cart: {
        control: number
        style: string
        text: string
        event: string
    }
    show_quality: number
    warehouse_switch: number
}
const formData = ref<formDataType|any>({})
const levelOptions = [
    { value: 1, title: '一级分类', desc: '分类平铺，适合品类较少' },
    { value: 2, title: '二级分类', desc: '左右分栏，适合常规商城' },
    { value: 3, title: '三级分类', desc: '品牌、系列、型号分层展示' }
]
const loading = ref(false)
const rules = computed(() => {
    return {
        page_title: [
            { required: true, message: t('pageTitlePlaceholder'), trigger: 'blur' }
        ],
        'search.title': formData.value.search?.control ? [
            { required: true, message: t('searchTitlePlaceholder'), trigger: 'blur' }
        ] : [],
        sort: [
            { required: true, message: t('sortPlaceholder'), trigger: 'change' }
        ],
        'cart.text': formData.value.cart?.control && (formData.value.cart?.style === 'style-1' || formData.value.cart?.event === 'download') ? [
            { required: true, message: t('cartTextPlaceholder'), trigger: 'blur' }
        ] : []
    }
})

const actionOptions = [
    { value: 'hidden', title: '不显示操作', desc: '卡片点击进入详情，不额外显示按钮', icon: 'nc-icon-guanbiV6xx' },
    { value: 'detail', title: '查看商品', desc: '显示查看按钮，引导用户进入详情', icon: 'nc-icon-chakanV6xx' },
    { value: 'cart', title: '加入购物车', desc: '直接加购并显示底部购物车', icon: 'nc-icon-gouwucheV6xx6' },
    { value: 'download', title: '下载转发', desc: '下载图片并复制商品介绍', icon: 'nc-icon-fenxiangV6xx' }
]
const actionMode = computed({
    get: () => Number(formData.value.cart?.control) === 1 ? (formData.value.cart?.event || 'detail') : 'hidden',
    set: (value: string) => {
        if (!formData.value.cart) return
        formData.value.cart.control = value === 'hidden' ? 0 : 1
        if (value !== 'hidden') formData.value.cart.event = value
        if (value === 'download' && !formData.value.cart.text) formData.value.cart.text = '一键转发'
        if (value === 'detail' && !formData.value.cart.text) formData.value.cart.text = '查看'
        if (value !== 'cart') formData.value.cart.style = 'style-1'
    }
})
const currentActionName = computed(() => actionOptions.find(item => item.value === actionMode.value)?.title || '')

interface configType {
    level_1: {
        template: string
        preview: string
    }[]
    level_2: {
        template: string
        preview: string
    }[]
}
const config = reactive<configType|any>({
    level_1: [
        {
            template: 'style-1',
            preview: 'addon/phone_shop/category_style1_1.png'
        }
    ],
    level_2: [
        {
            template: 'style-1',
            preview: 'addon/phone_shop/category_style2_1.png'
        },
        {
            template: 'style-2',
            preview: 'addon/phone_shop/category_style2_2.png'
        }
    ],
    level_3: [
        {
            template: 'style-1',
            preview: 'addon/phone_shop/category_style2_1.png'
        }
    ]
})
const getCategoryConfigFn = () => {
    loading.value = true
    getCategoryConfig().then(res => {
        formData.value = res.data
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}
getCategoryConfigFn()
const levelChange = (value: any) => {
    formData.value.template = value
}
const changeLevel = (value: number) => {
    formData.value.level = value
    formData.value.template = 'style-1'
}
const templateName = (template: string, index: number) => template === 'style-1' ? '经典布局' : `布局 ${index + 1}`
const currentLevelName = computed(() => levelOptions.find(item => item.value === Number(formData.value.level))?.title || '')
const currentPreview = computed(() => {
    const templates = config[`level_${formData.value.level}`] || []
    return templates.find((item: any) => item.template === formData.value.template)?.preview || templates[0]?.preview || ''
})
const carStyleClick = (value: any) => {
    formData.value.cart.style = 'style-' + value
}
const formRef = ref()
const onSave = async (formEl: any) => {
    await formEl.validate(async (valid:any) => {
        if (valid) {
            loading.value = true
            setCategoryConfig(formData.value).then(res => {
                getCategoryConfigFn()
            }).catch(() => {
                loading.value = false
            })
        }
    })
}

const router = useRouter()

const handleClick = (path: string) => {
    router.push({ path })
}
</script>

<style lang="scss" scoped>
.category-config-page {
    min-height: calc(100vh - 80px);
    padding: 22px 24px 92px;
    background: #f5f7fa;
    box-sizing: border-box;
}

.page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1280px;
    margin: 0 auto 10px;
}

.page-heading__desc {
    margin-top: 6px;
    color: #8a93a3;
    font-size: 13px;
}

.category-tabs {
    max-width: 1280px;
    margin: 0 auto 18px;
    padding: 0 18px;
    border: 1px solid #e9edf3;
    border-radius: 10px;
    background: #fff;
}

.category-tabs :deep(.el-tabs__header) { margin: 0; }
.category-tabs :deep(.el-tabs__nav-wrap::after) { display: none; }

.config-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 350px;
    gap: 18px;
    max-width: 1280px;
    margin: 0 auto;
    align-items: start;
}

.config-main { display: grid; gap: 16px; }

.config-card,
.preview-card {
    border: 1px solid #e8edf4;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 16px rgba(31, 41, 55, 0.035);
}

.config-card { padding: 22px 24px 8px; }

.section-heading {
    display: flex;
    gap: 12px;
    margin-bottom: 22px;
}

.section-heading h3,
.preview-head h3 { margin: 0; color: #202939; font-size: 16px; line-height: 24px; }
.section-heading p,
.preview-head p { margin: 4px 0 0; color: #8a93a3; font-size: 12px; line-height: 20px; }

.section-index {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    font-size: 12px;
    font-weight: 700;
}

.level-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }

.level-option,
.template-option {
    position: relative;
    border: 1px solid #e3e8ef;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s, background .2s;
}

.level-option { min-height: 82px; padding: 16px 18px; text-align: left; }
.level-option:hover,
.template-option:hover { border-color: var(--el-color-primary-light-5); }
.level-option.active,
.template-option.active { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); box-shadow: 0 0 0 2px var(--el-color-primary-light-8); }
.level-option__title { display: block; color: #273142; font-weight: 600; font-size: 14px; }
.level-option__desc { display: block; margin-top: 8px; color: #8a93a3; font-size: 12px; }
.level-option__check { position: absolute; top: 10px; right: 12px; color: var(--el-color-primary); font-weight: 700; }

.template-options { display: flex; flex-wrap: wrap; gap: 14px; padding: 18px 0 16px; border-top: 1px dashed #e8edf4; }
.template-option { width: 148px; padding: 8px; color: #4f5b6c; }
.template-option img { display: block; width: 100%; height: 158px; object-fit: contain; border-radius: 7px; background: #f7f9fc; }
.template-option span { display: block; margin-top: 8px; text-align: left; font-size: 13px; }
.template-option i { position: absolute; top: 10px; right: 10px; padding: 3px 7px; border-radius: 10px; color: #fff; background: var(--el-color-primary); font-size: 11px; font-style: normal; }

.config-card :deep(.el-form-item) { margin-bottom: 20px; }
.config-card :deep(.el-form-item__label) { color: #485466; }
.input-width { width: 360px; }

.action-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
.action-option { position: relative; display: flex; align-items: center; gap: 12px; min-height: 74px; padding: 14px 16px; border: 1px solid #e3e8ef; border-radius: 10px; background: #fff; text-align: left; cursor: pointer; transition: border-color .2s, background .2s, box-shadow .2s; }
.action-option:hover { border-color: var(--el-color-primary-light-5); }
.action-option.active { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); box-shadow: 0 0 0 2px var(--el-color-primary-light-8); }
.action-option__icon { display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; flex: 0 0 38px; border-radius: 9px; color: var(--el-color-primary); background: #fff; font-size: 20px; }
.action-option__content { display: flex; min-width: 0; flex-direction: column; }
.action-option__content b { color: #273142; font-size: 14px; line-height: 22px; }
.action-option__content small { margin-top: 2px; color: #8a93a3; font-size: 12px; line-height: 18px; }
.action-option__check { position: absolute; top: 9px; right: 11px; color: var(--el-color-primary); font-weight: 700; }
.action-content { padding: 18px 18px 1px; border: 1px solid #e8edf4; border-radius: 10px; background: #f8fafc; }
.action-content__title { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; }
.action-content__title b { color: #273142; font-size: 14px; }
.action-content__title p { margin: 4px 0 0; color: #8a93a3; font-size: 12px; }
.action-style-options { display: flex; flex-wrap: wrap; gap: 12px; }
.action-style-option { display: flex; width: 94px; height: 82px; flex-direction: column; align-items: center; justify-content: center; gap: 8px; border: 1px solid #dfe5ed; border-radius: 9px; background: #fff; color: #667085; cursor: pointer; }
.action-style-option.active { border-color: var(--el-color-primary); box-shadow: 0 0 0 2px var(--el-color-primary-light-8); }
.action-style-option small { font-size: 11px; }
.action-style-button { min-width: 48px; padding: 4px 10px; border-radius: 12px; color: #fff; background: var(--el-color-primary); font-size: 11px; text-align: center; }
.action-style-circle { display: flex; width: 30px; height: 30px; align-items: center; justify-content: center; border-radius: 50%; color: #fff; background: var(--el-color-primary); }
.action-style-circle i { font-size: 18px; }
.action-tip,
.action-disabled-tip { margin: 0 0 18px; padding: 10px 12px; border-radius: 7px; color: #7d8796; background: #f4f6f9; font-size: 12px; line-height: 20px; }
.action-content .action-tip { margin-top: -2px; background: #fff; }

.config-preview { position: sticky; top: 18px; }
.preview-card { padding: 20px; }
.preview-head { display: flex; align-items: flex-start; justify-content: space-between; }
.preview-head > span { padding: 4px 9px; border-radius: 12px; color: var(--el-color-primary); background: var(--el-color-primary-light-9); font-size: 11px; white-space: nowrap; }
.phone-frame { position: relative; width: 224px; height: 430px; margin: 20px auto 18px; padding: 16px 10px 10px; border: 6px solid #202633; border-radius: 28px; background: #fff; overflow: hidden; box-sizing: border-box; }
.phone-frame img { width: 100%; height: 100%; object-fit: contain; }
.phone-speaker { position: absolute; z-index: 2; top: 7px; left: 50%; width: 38px; height: 4px; transform: translateX(-50%); border-radius: 3px; background: #202633; }
.preview-summary { display: grid; gap: 10px; padding-top: 16px; border-top: 1px solid #eef1f5; }
.preview-summary div { display: flex; justify-content: space-between; color: #7d8796; font-size: 12px; }
.preview-summary b { color: #354052; font-weight: 500; }

@media (max-width: 1100px) {
    .config-layout { grid-template-columns: 1fr; }
    .config-preview { position: static; }
    .preview-card { display: none; }
}

@media (max-width: 760px) {
    .category-config-page { padding: 16px 12px 86px; }
    .level-options { grid-template-columns: 1fr; }
    .input-width { width: 100%; }
    .action-options { grid-template-columns: 1fr; }
}

.border-color {
    border-color: var(--el-color-primary);
}

.text-color {
    color: var(--el-color-primary);
}

.bg-color {
    background-color: var(--el-color-primary);
}

.carStyle {
    :deep(.el-form-item__label) {
        height: 50px;
        line-height: 50px;
    }
}
</style>
