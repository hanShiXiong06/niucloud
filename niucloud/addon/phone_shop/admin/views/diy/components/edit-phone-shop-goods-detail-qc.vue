<template>
    <!-- 内容 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item :label="t('是否显示')">
                    <el-radio-group v-model="diyStore.editComponent.isShow">
                        <el-radio :label="true">{{ t('显示') }}</el-radio>
                        <el-radio :label="false">{{ t('隐藏') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="标题">
                    <el-input v-model.trim="diyStore.editComponent.title" maxlength="16" placeholder="官方质检报告" />
                </el-form-item>
                <el-form-item label="副标题">
                    <el-input v-model.trim="diyStore.editComponent.subTitle" maxlength="20" placeholder="逐项检测 · 真实成色" />
                </el-form-item>
                <el-form-item label="报告风格">
                    <el-radio-group v-model="diyStore.editComponent.layoutStyle">
                        <el-radio-button label="professional">专业</el-radio-button>
                        <el-radio-button label="simple">简洁</el-radio-button>
                        <el-radio-button label="card">卡片</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="默认展开">
                    <el-radio-group v-model="diyStore.editComponent.defaultExpand">
                        <el-radio-button label="collapsed">收起</el-radio-button>
                        <el-radio-button label="all">展开</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="主题色">
                    <el-color-picker v-model="diyStore.editComponent.themeColor" :predefine="diyStore.predefineColors" />
                </el-form-item>
                <el-form-item label="标题颜色">
                    <el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" />
                </el-form-item>
                <el-form-item label="检测徽标">
                    <el-switch v-model="diyStore.editComponent.showBadge" />
                </el-form-item>
                <el-form-item label="摘要条">
                    <el-switch v-model="diyStore.editComponent.showSummaryBar" />
                </el-form-item>
                <el-form-item label="正常检测项">
                    <el-switch v-model="diyStore.editComponent.showNormalItems" />
                    <span class="text-[12px] text-[#999] ml-[8px]">关闭后完整报告仅展示需关注项</span>
                </el-form-item>
                <div class="text-[12px] text-[#999] px-[10px] leading-[20px]">异常项始终优先展示；背景图、圆角和边距可在「样式」页设置。数据来自商品质检报告。</div>
            </el-form>
        </div>
    </div>

    <!-- 样式 -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <!-- 组件样式 -->
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import useDiyStore from '@/stores/modules/diy'

const diyStore: any = useDiyStore()
diyStore.editComponent.ignore = [] // 忽略公共属性
const defaults: Record<string, any> = {
    layoutStyle: 'professional', defaultExpand: 'collapsed', showNormalItems: true
}
Object.keys(defaults).forEach((key) => {
    if (diyStore.editComponent[key] === undefined) diyStore.editComponent[key] = defaults[key]
})

// 组件验证
diyStore.editComponent.verify = (index: number) => {
    const res = { code: true, message: '' }
    return res
}

defineExpose({})

</script>

<style lang="scss" scoped></style>
