<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">推广卡片设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="标题">
                    <el-input v-model.trim="diyStore.editComponent.title" placeholder="请输入标题" clearable />
                </el-form-item>
                <el-form-item label="副标题">
                    <el-input v-model.trim="diyStore.editComponent.subtitle" placeholder="请输入副标题" clearable />
                </el-form-item>
                <el-form-item label="按钮文字">
                    <el-input v-model.trim="diyStore.editComponent.btnText" placeholder="按钮文字" clearable maxlength="10" />
                </el-form-item>
                <el-form-item label="背景色">
                    <el-input v-model="diyStore.editComponent.bgColor" placeholder="渐变色或纯色" />
                </el-form-item>
                <el-form-item label="跳转链接">
                    <el-input v-model="diyStore.editComponent.url" placeholder="跳转链接" />
                </el-form-item>
                <el-form-item label="是否显示">
                    <el-switch v-model="diyStore.editComponent.isShow" />
                </el-form-item>
            </el-form>
        </div>
    </div>

    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []

defineExpose({})
</script>

<style lang="scss" scoped></style>
