<template>
    <div class="content-wrap" v-show="diyStore.editTab === 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">会员身份码</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="标题"><el-input v-model="diyStore.editComponent.title" /></el-form-item>
                <el-form-item label="说明"><el-input v-model="diyStore.editComponent.desc" type="textarea" :rows="3" /></el-form-item>
            </el-form>
        </div>
    </div>
    <div class="style-wrap" v-show="diyStore.editTab === 'style'"><slot name="style"></slot></div>
</template>
<script setup lang="ts">
import useDiyStore from '@/stores/modules/diy'
const diyStore = useDiyStore()
defineExpose({})
</script>
