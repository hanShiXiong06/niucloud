<template>
    <div class="demo-container">
        <h2>用户搜索组件演示</h2>
        
        <div class="demo-section">
            <h3>基础用法</h3>
            <div class="demo-item">
                <label>选择用户：</label>
                <member-select
                    v-model="selectedUserId"
                    placeholder="搜索用户昵称、手机号或用户编号"
                    @change="handleUserChange"
                />
            </div>
            <div class="result">
                <p>选中的用户ID: {{ selectedUserId || '无' }}</p>
                <p v-if="selectedUserInfo">选中的用户信息: {{ JSON.stringify(selectedUserInfo, null, 2) }}</p>
            </div>
        </div>

        <div class="demo-section">
            <h3>带分页的用法</h3>
            <div class="demo-item">
                <label>选择用户（带分页）：</label>
                <member-select
                    v-model="selectedUserId2"
                    placeholder="搜索用户信息"
                    :show-pagination="true"
                    @change="handleUserChange2"
                />
            </div>
            <div class="result">
                <p>选中的用户ID: {{ selectedUserId2 || '无' }}</p>
            </div>
        </div>

        <div class="demo-section">
            <h3>在表单中的用法</h3>
            <el-form :model="form" label-width="120px">
                <el-form-item label="关联用户">
                    <member-select
                        v-model="form.user_id"
                        placeholder="请选择关联用户"
                        @change="handleFormUserChange"
                    />
                </el-form-item>
                <el-form-item label="订单金额">
                    <el-input-number v-model="form.amount" :precision="2" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="submitForm">提交</el-button>
                    <el-button @click="resetForm">重置</el-button>
                </el-form-item>
            </el-form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import MemberSelect from './index.vue'

// 基础用法
const selectedUserId = ref<number | null>(null)
const selectedUserInfo = ref<any>(null)

const handleUserChange = (userId: number | null, userInfo: any) => {
    console.log('用户变化:', userId, userInfo)
    selectedUserInfo.value = userInfo
}

// 带分页用法
const selectedUserId2 = ref<number | null>(null)

const handleUserChange2 = (userId: number | null, userInfo: any) => {
    console.log('用户变化2:', userId, userInfo)
}

// 表单用法
const form = reactive({
    user_id: null as number | null,
    amount: 0
})

const handleFormUserChange = (userId: number | null, userInfo: any) => {
    console.log('表单用户变化:', userId, userInfo)
}

const submitForm = () => {
    console.log('提交表单:', form)
    alert(`表单数据：用户ID=${form.user_id}, 金额=${form.amount}`)
}

const resetForm = () => {
    form.user_id = null
    form.amount = 0
}
</script>

<style scoped>
.demo-container {
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.demo-section {
    margin-bottom: 30px;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 20px;
}

.demo-section h3 {
    margin-top: 0;
    color: #303133;
    border-bottom: 1px solid #ebeef5;
    padding-bottom: 10px;
}

.demo-item {
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.demo-item label {
    min-width: 140px;
    font-weight: 500;
    color: #606266;
}

.result {
    padding: 10px;
    background-color: #f5f7fa;
    border-radius: 4px;
    margin-top: 10px;
}

.result p {
    margin: 5px 0;
    font-size: 14px;
    color: #606266;
}

.result pre {
    background-color: #f0f2f5;
    padding: 8px;
    border-radius: 4px;
    font-size: 12px;
    overflow-x: auto;
}
</style> 