<template>
    <div class="service-card-page">
        <el-card>
            <template #header>
                <div class="card-header">
                    <span>次卡管理</span>
                    <el-button type="primary" :loading="saveLoading" @click="handleSave">保存配置</el-button>
                </div>
            </template>
            <el-alert type="info" :closable="false" class="tip-box">
                配置各类型次卡的售价、次数与上架状态，用户购买后可在对应服务下单时抵扣。
            </el-alert>
            <el-table :data="list" v-loading="loading" border class="card-table">
                <el-table-column prop="card_type" label="类型" width="100">
                    <template #default="{ row }">
                        {{ typeMap[row.card_type] || row.card_type }}
                    </template>
                </el-table-column>
                <el-table-column label="名称" min-width="120">
                    <template #default="{ row }">
                        <el-input v-model="row.name" size="small" maxlength="50" />
                    </template>
                </el-table-column>
                <el-table-column label="副标题" min-width="160">
                    <template #default="{ row }">
                        <el-input v-model="row.subtitle" size="small" maxlength="200" />
                    </template>
                </el-table-column>
                <el-table-column label="售价(元)" width="130">
                    <template #default="{ row }">
                        <el-input-number v-model="row.price" :min="0" :precision="2" size="small" :controls="false" style="width: 100px" />
                    </template>
                </el-table-column>
                <el-table-column label="原价(元)" width="130">
                    <template #default="{ row }">
                        <el-input-number v-model="row.origin_price" :min="0" :precision="2" size="small" :controls="false" style="width: 100px" />
                    </template>
                </el-table-column>
                <el-table-column label="次数" width="110">
                    <template #default="{ row }">
                        <el-input-number v-model="row.times" :min="1" :max="9999" size="small" :controls="false" style="width: 80px" />
                    </template>
                </el-table-column>
                <el-table-column label="排序" width="100">
                    <template #default="{ row }">
                        <el-input-number v-model="row.sort" :min="0" :max="9999" size="small" :controls="false" style="width: 70px" />
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch v-model="row.status" :active-value="1" :inactive-value="0" />
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getServiceCardList, saveServiceCards } from '@/addon/sd_xiaoyuan/api/serviceCard'

const loading = ref(false)
const saveLoading = ref(false)
const list = ref<any[]>([])

const typeMap: Record<string, string> = {
    EXPRESS: '快递卡',
    ERRAND: '跑腿卡',
    PRINT: '打印卡',
    RUNNER: '接单员卡'
}

onMounted(() => {
    loadList()
})

const loadList = async () => {
    loading.value = true
    const res: any = await getServiceCardList()
    loading.value = false
    if (res.code === 1) {
        list.value = (res.data || []).map((item: any) => ({
            ...item,
            price: Number(item.price || 0),
            origin_price: Number(item.origin_price || 0),
            times: Number(item.times || 1),
            sort: Number(item.sort || 0),
            status: Number(item.status ?? 1)
        }))
    }
}

const handleSave = async () => {
    if (!list.value.length) {
        ElMessage.warning('暂无次卡数据')
        return
    }
    for (const row of list.value) {
        if (!row.name) {
            ElMessage.warning('请填写次卡名称')
            return
        }
        if (Number(row.price) <= 0) {
            ElMessage.warning(`${typeMap[row.card_type] || row.card_type}售价须大于0`)
            return
        }
    }
    saveLoading.value = true
    const res: any = await saveServiceCards(list.value)
    saveLoading.value = false
    if (res.code === 1) {
        ElMessage.success('保存成功')
        loadList()
    } else {
        ElMessage.error(res.msg || '保存失败')
    }
}
</script>

<style lang="scss" scoped>
.service-card-page {
    padding: 20px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.tip-box {
    margin-bottom: 16px;
}
.card-table {
    margin-top: 8px;
}
</style>
