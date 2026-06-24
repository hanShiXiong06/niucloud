<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">公告栏设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="公告内容">
                    <el-input v-model.trim="diyStore.editComponent.text" placeholder="请输入公告内容" clearable maxlength="100" show-word-limit />
                </el-form-item>
                <el-form-item label="背景颜色">
                    <el-color-picker v-model="diyStore.editComponent.bgColor" />
                </el-form-item>
                <el-form-item label="文字颜色">
                    <el-color-picker v-model="diyStore.editComponent.textColor" />
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
