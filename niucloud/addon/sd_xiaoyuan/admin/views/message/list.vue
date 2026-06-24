<template>
    <div class="message-list-container">
        <!-- 发送消息区域 -->
        <el-card class="send-card">
            <template #header>
                <span>发送系统消息</span>
            </template>
            <el-form :model="sendForm" label-width="100px">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="接收用户ID" required>
                            <el-input v-model="sendForm.member_id" placeholder="请输入用户ID，多个用逗号分隔" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="消息类型">
                            <el-select v-model="sendForm.type" placeholder="请选择消息类型">
                                <el-option v-for="(name, key) in typeList" :key="key" :label="name" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="消息标题" required>
                    <el-input v-model="sendForm.title" placeholder="请输入消息标题" />
                </el-form-item>
                <el-form-item label="消息内容" required>
                    <el-input v-model="sendForm.content" type="textarea" rows="4" placeholder="请输入消息内容" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSend" :loading="sendLoading">发送消息</el-button>
                    <el-button @click="handleClear">清空</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 消息类型说明 -->
        <el-card class="type-card">
            <template #header>
                <span>消息类型说明</span>
            </template>
            <el-table :data="typeData" stripe size="small">
                <el-table-column prop="type" label="类型代码" width="150" />
                <el-table-column prop="name" label="类型名称" width="150" />
                <el-table-column prop="desc" label="说明" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { sendMessage, batchSendMessage, getMessageTypeList } from '@/addon/sd_xiaoyuan/api/message'

const sendLoading = ref(false)
const typeList = ref<Record<string, string>>({})

const sendForm = reactive({
    member_id: '',
    type: 'SYSTEM',
    title: '',
    content: ''
})

const typeData = computed(() => {
    return Object.entries(typeList.value).map(([type, name]) => ({
        type,
        name,
        desc: getTypeDesc(type)
    }))
})

onMounted(() => {
    loadTypeList()
})

const loadTypeList = async () => {
    try {
        const res: any = await getMessageTypeList()
        typeList.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const getTypeDesc = (type: string) => {
    const descMap: Record<string, string> = {
        'SYSTEM': '系统通知消息，用于平台公告、活动通知等',
        'TASK': '跑腿任务相关消息，如任务状态变更、接单通知等',
        'GROUP': '拼单相关消息，如成团通知、拼单状态变更等',
        'SECONDHAND': '二手交易相关消息，如有人想要您的商品等',
        'LOSTFOUND': '失物招领相关消息，如有人联系您等',
        'ORDER': '订单相关消息，如订单状态变更等',
        'WALLET': '钱包相关消息，如收入到账、提现通知等'
    }
    return descMap[type] || '其他消息'
}

const handleSend = async () => {
    if (!sendForm.member_id) {
        ElMessage.warning('请输入接收用户ID')
        return
    }
    if (!sendForm.title) {
        ElMessage.warning('请输入消息标题')
        return
    }
    if (!sendForm.content) {
        ElMessage.warning('请输入消息内容')
        return
    }
    
    sendLoading.value = true
    try {
        const memberIds = sendForm.member_id.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id))
        
        if (memberIds.length === 0) {
            ElMessage.warning('请输入有效的用户ID')
            return
        }
        
        if (memberIds.length === 1) {
            await sendMessage({
                member_id: memberIds[0],
                type: sendForm.type,
                title: sendForm.title,
                content: sendForm.content
            })
        } else {
            await batchSendMessage({
                member_ids: memberIds,
                type: sendForm.type,
                title: sendForm.title,
                content: sendForm.content
            })
        }
        
        ElMessage.success('发送成功')
        handleClear()
    } catch (e: any) {
        ElMessage.error(e.message || '发送失败')
    } finally {
        sendLoading.value = false
    }
}

const handleClear = () => {
    sendForm.member_id = ''
    sendForm.type = 'SYSTEM'
    sendForm.title = ''
    sendForm.content = ''
}
</script>

<style lang="scss" scoped>
.message-list-container {
    padding: 20px;
}

.send-card {
    margin-bottom: 20px;
}

.type-card {
    margin-bottom: 20px;
}
</style>
