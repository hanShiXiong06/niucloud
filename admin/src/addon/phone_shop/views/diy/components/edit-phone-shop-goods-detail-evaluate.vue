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
                <el-form-item label="模块标题">
                    <el-input v-model.trim="diyStore.editComponent.title" maxlength="12" placeholder="留空则根据评价范围自动显示" clearable />
                </el-form-item>
                <el-form-item label="排版风格">
                    <el-radio-group v-model="diyStore.editComponent.layoutStyle">
                        <el-radio-button label="standard">标准</el-radio-button>
                        <el-radio-button label="compact">紧凑</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="评价来源">
                    <el-switch v-model="diyStore.editComponent.showSource" />
                    <span class="text-[12px] text-[#999] ml-[8px]">显示同型号评价的分类来源</span>
                </el-form-item>
                <el-form-item label="空状态文案">
                    <el-input v-model.trim="diyStore.editComponent.emptyText" maxlength="16" placeholder="暂无真实成交评价" />
                </el-form-item>
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
    title: '', layoutStyle: 'standard', showSource: true, emptyText: '暂无真实成交评价'
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
