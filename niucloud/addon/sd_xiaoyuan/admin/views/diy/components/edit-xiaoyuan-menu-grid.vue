<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">功能菜单设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="每行显示">
                    <el-radio-group v-model="diyStore.editComponent.column">
                        <el-radio :label="4">4个</el-radio>
                        <el-radio :label="5">5个</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            
            <h3 class="mb-[10px] mt-[20px]">菜单项配置</h3>
            <div class="menu-list">
                <div class="menu-item" v-for="(item, idx) in diyStore.editComponent.list" :key="idx">
                    <el-form label-width="80px" class="px-[10px]">
                        <el-form-item label="名称">
                            <el-input v-model="item.name" placeholder="菜单名称" size="small" />
                        </el-form-item>
                        <el-form-item label="图标">
                            <el-input v-model="item.icon" placeholder="图标名称" size="small" />
                        </el-form-item>
                        <el-form-item label="图标颜色">
                            <el-color-picker v-model="item.iconColor" size="small" />
                        </el-form-item>
                        <el-form-item label="链接">
                            <el-input v-model="item.url" placeholder="跳转链接" size="small" />
                        </el-form-item>
                        <el-form-item label="显示">
                            <el-switch v-model="item.isShow" size="small" />
                        </el-form-item>
                    </el-form>
                    <el-divider />
                </div>
            </div>
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

<style lang="scss" scoped>
.menu-list {
    max-height: 400px;
    overflow-y: auto;
}
.menu-item {
    background: #f9f9f9;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
}
</style>
