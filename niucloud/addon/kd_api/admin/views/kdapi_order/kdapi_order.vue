<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center mb-4">
                <span class="text-lg font-semibold">{{pageName}}</span>
                <div class="flex space-x-4 text-sm">
                    <div class="bg-blue-50 px-3 py-2 rounded-lg">
                        <span class="text-blue-600">总订单数: </span>
                        <span class="font-bold text-blue-800">{{ kdapiOrderTable.total }}</span>
                    </div>
             
                </div>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">

                <el-form :inline="true" :model="kdapiOrderTable.searchParam" ref="searchFormRef" label-width="80px">
                    
                    <el-form-item :label="t('memberId')" prop="member_id">
                        <el-select class="w-[280px]" v-model="kdapiOrderTable.searchParam.member_id" clearable :placeholder="t('memberIdPlaceholder')" filterable>
                           <el-option
                                       v-for="(item, index) in memberIdList"
                                       :key="index"
                                       :label="item['nickname']"
                                       :value="item['member_id']"
                                   />
                        </el-select>
                    </el-form-item>
                    
                    <el-form-item :label="t('orderId')" prop="order_id">
                        <el-input v-model="kdapiOrderTable.searchParam.order_id" :placeholder="t('orderIdPlaceholder')" clearable />
                    </el-form-item>
   
                   
                    <el-form-item :label="t('isJs')" prop="is_js">
                        <el-select v-model="kdapiOrderTable.searchParam.is_js" :placeholder="t('isJsPlaceholder')" clearable>
                            <el-option label="已结算" value="1" />
                            <el-option label="未结算" value="0" />
                        </el-select>
                    </el-form-item>
                    
                    <el-form-item :label="t('sid')" prop="sid">
                        <el-input v-model="kdapiOrderTable.searchParam.sid" :placeholder="t('sidPlaceholder')" clearable />
                    </el-form-item>
                            <el-form-item :label="t('createTime')" prop="create_time">
                        <el-date-picker v-model="kdapiOrderTable.searchParam.create_time" type="datetimerange" format="YYYY-MM-DD hh:mm:ss"
                            :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadKdapiOrderList()" :icon="Search">
                            {{ t('search') }}
                        </el-button>
                        <el-button @click="resetForm(searchFormRef)" :icon="Refresh">
                            {{ t('reset') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="kdapiOrderTable.data" size="large" v-loading="kdapiOrderTable.loading" stripe border>
                    <template #empty>
                        <span>{{ !kdapiOrderTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    
                    <!-- 用户信息 -->
                    <el-table-column :label="t('memberId')" min-width="140" :show-overflow-tooltip="true">
                        <template #default="scope">
                            <div class="flex items-center">
                                <div>
                                    <div class="font-medium">{{ scope.row.member_id_name }}</div>
                                    <div class="text-xs text-gray-400">ID: {{ scope.row.member_id }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    
                    <!-- 订单信息 -->
                    <el-table-column :label="t('orderId')" min-width="160" :show-overflow-tooltip="true">
                        <template #default="scope">
                            <div>
                                <div class="font-mono text-blue-600">{{ scope.row.order_id }}</div>
                                <div class="text-xs text-gray-500 mt-1 font-bold">{{ scope.row.title }}</div>
                            </div>
                        </template>
                    </el-table-column>
                    
                    <!-- 金额信息 -->
                    <el-table-column :label="t('payMoney')" min-width="120" align="right">
                        <template #default="scope">
                            <div class="text-right">
                                <div class="text-lg font-bold text-green-600">¥{{ formatMoney(scope.row.pay_money) }}</div>
                                <div class="text-xs text-gray-500">佣金: ¥{{ formatMoney(scope.row.commission) }}</div>
                            </div>
                        </template>
                    </el-table-column>
                    
                    <!-- 状态信息 -->
                    <el-table-column :label="t('status')" min-width="120" align="center">
                        <template #default="scope">
                            <el-tag :type="getStatusType(scope.row.status)" size="small">
                                {{ scope.row.status_name}}
                            </el-tag>
                        </template>
                    </el-table-column>
                    
                    <!-- 结算状态 -->
                    <el-table-column :label="t('isJs')" min-width="100" align="center">
                        <template #default="scope">
                            <el-tag :type="scope.row.is_js == 1 ? 'success' : 'warning'" size="small">
                                {{ scope.row.is_js == 1 ? '已结算' : '未结算' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    
                    <!-- 推广信息 -->
                    <el-table-column label="推广信息" min-width="140">
                        <template #default="scope">
                            <div class="text-xs">
                                <div>SID: <span class="font-mono">{{ scope.row.sid }}</span></div>
                                <div class="mt-1">PUB: <span class="font-mono">{{ scope.row.pub_id }}</span></div>
                            </div>
                        </template>
                    </el-table-column>
                    
                    <!-- 创建时间 -->
                    <el-table-column label="创建时间" min-width="160" :show-overflow-tooltip="true">
                        <template #default="scope">
                            <div class="text-xs text-gray-600">
                                {{scope.row.create_time }}
                            </div>
                        </template>
                    </el-table-column>
                

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="kdapiOrderTable.page" v-model:page-size="kdapiOrderTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="kdapiOrderTable.total"
                        @size-change="loadKdapiOrderList()" @current-change="loadKdapiOrderList" />
                </div>
            </div>

            <edit ref="editKdapiOrderDialog" @complete="loadKdapiOrderList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getKdapiOrderList, deleteKdapiOrder, getWithMemberList } from '@/addon/kd_api/api/kdapi_order'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import { User, Edit as EditIcon, Delete, View, Search, Refresh } from '@element-plus/icons-vue'
import Edit from '@/addon/kd_api/views/kdapi_order/components/kdapi-order-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let kdapiOrderTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "member_id":"",
      "order_id":"",
      "title":"",
      "status":"",
      "is_js":"",
      "sid":"",
      "pub_id":"",
      "create_time":""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取订单列列表
 */
const loadKdapiOrderList = (page: number = 1) => {
    kdapiOrderTable.loading = true
    kdapiOrderTable.page = page

    getKdapiOrderList({
        page: kdapiOrderTable.page,
        limit: kdapiOrderTable.limit,
         ...kdapiOrderTable.searchParam
    }).then(res => {
        kdapiOrderTable.loading = false
        kdapiOrderTable.data = res.data.data
        kdapiOrderTable.total = res.data.total
    }).catch(() => {
        kdapiOrderTable.loading = false
    })
}
loadKdapiOrderList()

const editKdapiOrderDialog: Record<string, any> | null = ref(null)

/**
 * 添加订单列
 */
const addEvent = () => {
    editKdapiOrderDialog.value.setFormData()
    editKdapiOrderDialog.value.showDialog = true
}

/**
 * 编辑订单列
 * @param data
 */
const editEvent = (data: any) => {
    editKdapiOrderDialog.value.setFormData(data)
    editKdapiOrderDialog.value.showDialog = true
}

/**
 * 删除订单列
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('kdapiOrderDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteKdapiOrder(id).then(() => {
            loadKdapiOrderList()
        }).catch(() => {
        })
    })
}

    
    const memberIdList = ref([])
    const setMemberIdList = async () => {
    memberIdList.value = await (await getWithMemberList({})).data
    }
    setMemberIdList()

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadKdapiOrderList()
}

/**
 * 格式化金额
 */
const formatMoney = (amount: string | number) => {
    if (!amount) return '0.00'
    return parseFloat(amount.toString()).toFixed(2)
}

/**
 * 格式化日期时间
 */
const formatDateTime = (timestamp: string | number) => {
    if (!timestamp) return '-'
    const date = new Date(parseInt(timestamp.toString()) * 1000)
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    })
}

/**
 * 获取状态类型
 */
const getStatusType = (status: string) => {
    const statusMap: Record<string, string> = {
        '0': 'info',     // 待处理
        '1': 'success',  // 成功
        '2': 'warning',  // 处理中
        '3': 'danger',   // 失败
    }
    return statusMap[status] || 'info'
}

/**
 * 获取状态文本
 */
const getStatusText = (status: string) => {
    const statusMap: Record<string, string> = {
        '0': '待处理',
        '1': '成功',
        '2': '处理中',
        '3': '失败',
    }
    return statusMap[status] || '未知'
}

/**
 * 查看订单详情
 */
const viewEvent = (data: any) => {
    // 这里可以添加查看详情的逻辑
    console.log('查看订单详情:', data)
    // 可以打开一个详情弹窗或跳转到详情页面
    ElMessageBox.alert(
        `订单ID: ${data.order_id}<br/>标题: ${data.title}<br/>金额: ¥${formatMoney(data.pay_money)}<br/>佣金: ¥${formatMoney(data.commission)}`,
        '订单详情',
        {
            dangerouslyUseHTMLString: true,
            confirmButtonText: '确定'
        }
    )
}
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
		word-break: break-all;
		text-overflow: ellipsis;
		overflow: hidden;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

/* 表格样式优化 */
:deep(.el-table) {
	.el-table__header {
		th {
			background-color: #fafafa;
			font-weight: 600;
			color: #333;
		}
	}
	
	.el-table__row {
		transition: all 0.2s ease;
		
		&:hover {
			background-color: #f5f7fa;
		}
	}
}

/* 金额样式 */
.money-amount {
	font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
	font-weight: bold;
}

/* 状态标签样式 */
:deep(.el-tag) {
	border-radius: 12px;
	padding: 0 8px;
	font-size: 12px;
}

/* 按钮组样式 */
:deep(.el-button-group) {
	.el-button {
		padding: 4px 8px;
		font-size: 12px;
		
		&:first-child {
			border-top-left-radius: 4px;
			border-bottom-left-radius: 4px;
		}
		
		&:last-child {
			border-top-right-radius: 4px;
			border-bottom-right-radius: 4px;
		}
	}
}

/* 头像样式 */
:deep(.el-avatar) {
	background-color: #409eff;
	flex-shrink: 0;
}
</style>
