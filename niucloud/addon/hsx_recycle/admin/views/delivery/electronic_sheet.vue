<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-[5px]">
                <span class="text-page-title">电子面单</span>
                <el-button type="primary" @click="addEvent">新增电子面单</el-button>
            </div>
            <div class="text-[13px] text-gray-500 mb-[14px]">每个模板绑定一家快递公司和一个执行服务商（易速/快递100）。发件时选用模板即可出单，打印方式按模板设置。</div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="searchParam">
                    <el-form-item label="模板名称">
                        <el-input v-model.trim="searchParam.template_name" placeholder="请输入模板名称" maxlength="30" clearable />
                    </el-form-item>
                    <el-form-item label="快递公司">
                        <el-select v-model="searchParam.express_company_id" placeholder="全部" clearable class="w-[160px]">
                            <el-option v-for="item in companyList" :key="item.company_id" :label="item.company_name" :value="item.company_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">搜索</el-button>
                        <el-button @click="resetSearch">重置</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <el-table :data="tableData.data" size="large" v-loading="tableData.loading" class="mt-[10px]">
                <template #empty>
                    <span>{{ !tableData.loading ? '暂无数据' : '' }}</span>
                </template>
                <el-table-column label="模板名称" min-width="200" show-overflow-tooltip>
                    <template #default="{ row }">
                        <el-tag v-if="row.is_default" size="small" type="success">默认</el-tag>
                        <span class="ml-[8px]">{{ row.template_name }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="快递公司" min-width="120" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.company?.company_name || '-' }}</template>
                </el-table-column>
                <el-table-column label="服务商" min-width="100">
                    <template #default="{ row }">{{ providerName(row.provider) }}</template>
                </el-table-column>
                <el-table-column label="支付方式" min-width="90">
                    <template #default="{ row }">{{ payTypeName(row.pay_type) }}</template>
                </el-table-column>
                <el-table-column label="打印方式" min-width="100">
                    <template #default="{ row }">{{ channelName(row.print_channel) }}</template>
                </el-table-column>
                <el-table-column label="状态" min-width="80">
                    <template #default="{ row }">{{ row.status ? '启用' : '停用' }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" min-width="160" align="right">
                    <template #default="{ row }">
                        <el-button v-if="!row.is_default" type="primary" link @click="setDefaultEvent(row.id)">设默认</el-button>
                        <el-button type="primary" link @click="editEvent(row)">编辑</el-button>
                        <el-button v-if="!row.is_default" type="primary" link @click="deleteEvent(row.id)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-[16px] flex justify-end">
                <el-pagination v-model:current-page="tableData.page" v-model:page-size="tableData.limit"
                    layout="total, sizes, prev, pager, next, jumper" :total="tableData.total"
                    @size-change="loadList()" @current-change="loadList" />
            </div>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { getExpressSheetPageList, deleteExpressSheet, setDefaultExpressSheet, getExpressSheetPayType } from '@/addon/hsx_recycle/api/delivery'
import { getDeliveryCompanyList } from '@/addon/hsx_recycle/api/delivery'

const router = useRouter()

const searchParam = reactive<Record<string, any>>({ template_name: '', express_company_id: '' })
const tableData = reactive({ page: 1, limit: 10, total: 0, loading: true, data: [] as any[] })
const companyList = ref<any[]>([])
const payTypeMap = ref<Record<string, string>>({})

const providerName = (p: string) => ({ yisu: '易速快递', kuaidi100: '快递100' }[p] || p || '-')
const channelName = (c: string) => ({ browser: '网页打印', cloud: '云打印', lodop: '本地Lodop' }[c] || c || '-')
const payTypeName = (t: number) => payTypeMap.value[String(t)] || '-'

const loadList = (page: number = 1) => {
    tableData.loading = true
    tableData.page = page
    getExpressSheetPageList({ page: tableData.page, limit: tableData.limit, ...searchParam }).then((res: any) => {
        tableData.loading = false
        tableData.data = res.data.data
        tableData.total = res.data.total
    }).catch(() => { tableData.loading = false })
}
loadList()

getDeliveryCompanyList({ electronic_sheet_switch: 1 }).then((res: any) => { companyList.value = res.data || [] })
getExpressSheetPayType().then((res: any) => { payTypeMap.value = res.data || {} })

const resetSearch = () => { searchParam.template_name = ''; searchParam.express_company_id = ''; loadList() }
const addEvent = () => router.push('/delivery/electronic_sheet_edit')
const editEvent = (row: any) => router.push('/delivery/electronic_sheet_edit?id=' + row.id)

const deleteEvent = (id: number) => {
    ElMessageBox.confirm('确定删除该电子面单模板吗？', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' })
        .then(() => deleteExpressSheet(id).then(() => loadList(tableData.page)))
}
const setDefaultEvent = (id: number) => {
    ElMessageBox.confirm('设为默认后，发件未指定模板时将默认使用它。确定吗？', '提示', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' })
        .then(() => setDefaultExpressSheet(id).then(() => loadList(tableData.page)))
}
</script>

<style lang="scss" scoped></style>
