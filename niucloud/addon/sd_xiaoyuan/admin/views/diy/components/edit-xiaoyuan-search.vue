<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">搜索框设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="搜索提示文字">
                    <el-input v-model.trim="diyStore.editComponent.text" placeholder="请输入搜索提示文字" clearable maxlength="20" show-word-limit />
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
