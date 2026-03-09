<template>
    <div class="message-send-container">
        <el-card class="send-card">
            <template #header>
                <div class="card-header">
                    <span>发送系统消息</span>
                </div>
            </template>
            
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="发送对象" prop="sendType">
                    <el-radio-group v-model="sendType" @change="handleSendTypeChange">
                        <el-radio label="single">指定用户</el-radio>
                        <el-radio label="batch">批量发送</el-radio>
                        <el-radio label="all">全体用户</el-radio>
                    </el-radio-group>
                </el-form-item>
                
                <el-form-item label="选择用户" prop="member_id" v-if="sendType === 'single'">
                    <el-select 
                        v-model="form.member_id" 
                        filterable 
                        remote 
                        reserve-keyword 
                        placeholder="请输入用户昵称或手机号搜索"
                        :remote-method="searchMembers"
                        :loading="loading"
                        style="width: 300px"
                    >
                        <el-option
                            v-for="item in memberOptions"
                            :key="item.member_id"
                            :label="`${item.nickname} (${item.mobile})`"
                            :value="item.member_id"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item label="选择用户" prop="member_ids" v-if="sendType === 'batch'">
                    <el-select 
                        v-model="form.member_ids" 
                        multiple 
                        filterable 
                        remote 
                        reserve-keyword 
                        placeholder="请输入用户昵称或手机号搜索"
                        :remote-method="searchMembers"
                        :loading="loading"
                        style="width: 500px"
                    >
                        <el-option
                            v-for="item in memberOptions"
                            :key="item.member_id"
                            :label="`${item.nickname} (${item.mobile})`"
                            :value="item.member_id"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item label="消息类型" prop="type">
                    <el-select v-model="form.type" placeholder="请选择消息类型">
                        <el-option 
                            v-for="item in messageTypes" 
                            :key="item.value" 
                            :label="item.label" 
                            :value="item.value"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item label="消息标题" prop="title">
                    <el-input 
                        v-model="form.title" 
                        placeholder="请输入消息标题"
                        maxlength="100"
                        show-word-limit
                    />
                </el-form-item>
                
                <el-form-item label="消息内容" prop="content">
                    <el-input 
                        v-model="form.content" 
                        type="textarea" 
                        :rows="6"
                        placeholder="请输入消息内容"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
                
                <el-form-item>
                    <el-button type="primary" @click="handleSubmit" :loading="submitting">
                        {{ sendType === 'all' ? '发送给全体用户' : '发送' }}
                    </el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>
        
        <!-- 发送记录 -->
        <el-card class="history-card" style="margin-top: 20px">
            <template #header>
                <div class="card-header">
                    <span>最近发送记录</span>
                    <el-button type="text" @click="loadHistory">刷新</el-button>
                </div>
            </template>
            
            <el-table :data="historyList" v-loading="historyLoading">
                <el-table-column prop="title" label="标题" width="200" />
                <el-table-column prop="content" label="内容" show-overflow-tooltip />
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="scope">
                        <el-tag size="small">{{ getTypeLabel(scope.row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="发送时间" width="180">
                    <template #default="scope">
                        {{ scope.row.create_time || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100">
                    <template #default="scope">
                        <el-button type="text" @click="handleResend(scope.row)">重新发送</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRouter } from 'vue-router'

const router = useRouter()

// 表单数据
const form = reactive({
    member_id: 0,
    member_ids: [] as number[],
    type: 'SYSTEM',
    title: '',
    content: ''
})

// 表单验证规则
const rules = {
    title: [
        { required: true, message: '请输入消息标题', trigger: 'blur' }
    ],
    content: [
        { required: true, message: '请输入消息内容', trigger: 'blur' }
    ]
}

// 发送类型
const sendType = ref('single')
const submitting = ref(false)
const loading = ref(false)
const memberOptions = ref<MemberOption[]>([])
const messageTypes = ref([
    { label: '系统消息', value: 'SYSTEM' },
    { label: '订单消息', value: 'ORDER' },
    { label: '社区消息', value: 'COMMUNITY' },
    { label: '评论消息', value: 'COMMENT' }
])

// 历史记录
const historyList = ref<any[]>([])
const historyLoading = ref(false)

// 定义用户类型
interface MemberOption {
    member_id: number
    nickname: string
    mobile: string
}

// 搜索用户
const searchMembers = async (query: string) => {
    if (!query) {
        memberOptions.value = []
        return
    }
    
    loading.value = true
    try {
        // 调用搜索用户API
        const res = await fetch(`/adminapi/sd_xiaoyuan/member/search?keyword=${encodeURIComponent(query)}`)
        const data = await res.json()
        if (data.code === 1) {
            memberOptions.value = (data.data || []) as MemberOption[]
        }
    } catch (error) {
        console.error('搜索用户失败:', error)
    } finally {
        loading.value = false
    }
}

// 发送类型改变
const handleSendTypeChange = (type: string) => {
    form.member_id = 0
    form.member_ids = []
}

// 提交发送
const handleSubmit = async () => {
    const formRef = ref()
    
    if (sendType.value === 'all') {
        try {
            await ElMessageBox.confirm('确定要发送给全体用户吗？', '确认发送', {
                type: 'warning'
            })
        } catch {
            return
        }
    }
    
    submitting.value = true
    try {
        let url = '/adminapi/sd_xiaoyuan/message/send'
        let data: any = { ...form }
        
        if (sendType.value === 'batch') {
            url = '/adminapi/sd_xiaoyuan/message/batchSend'
            data.member_ids = data.member_ids.join(',')
        } else if (sendType.value === 'all') {
            // 全体用户发送逻辑
            url = '/adminapi/sd_xiaoyuan/message/sendAll'
        }
        
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        
        const result = await res.json()
        if (result.code === 1) {
            ElMessage.success('发送成功')
            handleReset()
            loadHistory()
        } else {
            ElMessage.error(result.msg || '发送失败')
        }
    } catch (error) {
        console.error('发送失败:', error)
        ElMessage.error('发送失败')
    } finally {
        submitting.value = false
    }
}

// 重置表单
const handleReset = () => {
    form.member_id = 0
    form.member_ids = []
    form.type = 'SYSTEM'
    form.title = ''
    form.content = ''
    sendType.value = 'single'
}

// 加载历史记录
const loadHistory = async () => {
    historyLoading.value = true
    try {
        const res = await fetch('/adminapi/sd_xiaoyuan/message/history')
        const data = await res.json()
        if (data.code === 1) {
            historyList.value = data.data || []
        }
    } catch (error) {
        console.error('加载历史记录失败:', error)
    } finally {
        historyLoading.value = false
    }
}

// 重新发送
const handleResend = (row: any) => {
    form.title = row.title
    form.content = row.content
    form.type = row.type
}

// 获取类型标签
const getTypeLabel = (type: string) => {
    const item = messageTypes.value.find(item => item.value === type)
    return item ? item.label : type
}


onMounted(() => {
    loadHistory()
})
</script>

<style lang="scss" scoped>
.message-send-container {
    padding: 20px;
}

.send-card, .history-card {
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
}

.el-form {
    max-width: 800px;
}
</style>
