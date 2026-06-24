<template>
    <div class="edit-xiaoyuan-user-header">
        <el-form label-width="100px" class="px-4">
            <el-form-item label="背景色(上)">
                <el-color-picker v-model="diyStore.editComponent.bgStartColor" show-alpha />
            </el-form-item>
            <el-form-item label="背景色(下)">
                <el-color-picker v-model="diyStore.editComponent.bgEndColor" show-alpha />
            </el-form-item>
            <el-form-item label="登录提示">
                <el-input v-model="diyStore.editComponent.loginTip" placeholder="点击登录" />
            </el-form-item>
            <el-form-item label="欢迎语">
                <el-input v-model="diyStore.editComponent.welcomeText" placeholder="欢迎使用校园帮" />
            </el-form-item>
            <el-form-item label="显示信誉分">
                <el-switch v-model="diyStore.editComponent.showCredit" />
            </el-form-item>
            <el-form-item label="设置链接">
                <diy-link v-model="diyStore.editComponent.settingsUrl" />
            </el-form-item>
            <el-form-item label="信誉链接">
                <diy-link v-model="diyStore.editComponent.creditUrl" />
            </el-form-item>
        </el-form>
    </div>
</template>

<script setup lang="ts">
import useDiyStore from '@/stores/modules/diy'
const diyStore = useDiyStore()
</script>
