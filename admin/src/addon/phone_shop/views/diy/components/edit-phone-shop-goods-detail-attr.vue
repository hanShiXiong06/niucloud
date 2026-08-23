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
                    <el-input v-model.trim="diyStore.editComponent.title" maxlength="10" placeholder="本机参数" />
                </el-form-item>
                <el-form-item label="排版风格">
                    <el-radio-group v-model="diyStore.editComponent.layoutStyle">
                        <el-radio-button label="list">列表</el-radio-button>
                        <el-radio-button label="grid">宫格</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="预览数量">
                    <el-slider v-model="diyStore.editComponent.previewCount" show-input size="small" class="ml-[10px] diy-nav-slider" :min="2" :max="8" />
                </el-form-item>
                <el-form-item label="默认展开">
                    <el-switch v-model="diyStore.editComponent.defaultExpand" />
                </el-form-item>
                <div class="text-[12px] text-[#999] px-[10px] leading-[20px]">参数较多时默认只展示摘要，客户可自行展开，避免详情页信息过载。</div>
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
    title: '本机参数', layoutStyle: 'list', previewCount: 4, defaultExpand: false
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
