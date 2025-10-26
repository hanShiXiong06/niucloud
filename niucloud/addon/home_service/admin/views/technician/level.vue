<template>
	<div class="main-container">
		<el-card class="card !border-none" shadow="never">

			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" @click="addEvent">
					{{ t("addTechnicianLevel") }}
				</el-button>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="labelTable.searchParam"
					@search="getLevelListFn" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-table :data="labelTable.data" size="large" v-loading="labelTable.loading">
					<template #empty>
						<span>{{ !labelTable.loading ? t("emptyData") : "" }}</span>
					</template>
					<!-- <el-table-column :label="t('levelName')" min-width="120" >
                        <template #default="{row}">
                            <span>{{ levelWeightList[row.level_num] }}</span>
                        </template>
                    </el-table-column> -->
					<el-table-column prop="level_id" :label="t('levelID')" min-width="80" />
					<el-table-column prop="level_name" :label="t('levelName')" min-width="120" />
					<el-table-column prop="level_num" :label="t('levelWeight')" min-width="120" />
					<el-table-column prop="order_rate" :label="t('sharedCommission')" min-width="120">
						<template #default="{ row }">
							<span class="text-[#273de3]">{{ row.order_rate }}%</span>
						</template>
					</el-table-column>
					<el-table-column prop="level_num" :label="t('startLevel')" min-width="80">
						<template #default="{ row }" >
							<span v-if="row.level_num == 1">星级一</span>
							<span v-if="row.level_num == 2">星级二</span>
							<span v-if="row.level_num == 3">星级三</span>
							<span v-if="row.level_num == 4">星级四</span>
							<span v-if="row.level_num == 5">星级五</span>
							<span v-if="row.level_num == 6">星级六</span>
							<span v-if="row.level_num == 7">星级七</span>
							<span v-if="row.level_num == 8">星级八</span>
							<span v-if="row.level_num == 9">星级九</span>
							<span v-if="row.level_num == 10">星级十</span>
						</template>
					</el-table-column>
					<el-table-column :label="t('levelConditional')" min-width="150">
						<template #default="{ row ,$index}" >
							<div v-if="$index != 0">
								<div>
									1.累计接单≥{{Number(row.order_num)}}
								</div>
								<div>
									2.业绩≥{{Number(row.achievement)}}
								</div>
							</div>
							
							<!-- <span>
								需师傅总服务时间达到{{Number(row.achievement)}}分钟，且完成有效订单数达到{{Number(row.additional_rate)}}个
							</span> -->
						</template>
					</el-table-column>
					<el-table-column prop="additional_rate" :label="t('levelPeople')" min-width="120">
						<template #default="{ row }">
							<div class="flex items-center">
								<el-icon color="#ccc"><UserFilled /></el-icon>
								<span class="text-[#273de3] pl-[5px]">{{ row.technician_count }}人</span>
							</div>
						</template>
					</el-table-column>
					<el-table-column prop="create_time" :label="t('createTime')" min-width="200" />
					<!-- <el-table-column prop="level_text" :label="t('levelConditional')" min-width="150">
						<template #default="{ row }">
							<div class="flex flex-col">
								<span v-for="(item, index) in row.level_text.list"
									:key="index">{{item}}{{row.level_text.list.length != index+1 ? row.level_text.text : ''}}</span>
							</div>
						</template>
					</el-table-column> -->
					<el-table-column :label="t('operation')" fixed="right" align="right" min-width="120">
						<template #default="{ row }">
							<el-button type="primary" link @click="editEvent(row.level_id)">{{ t("edit") }}</el-button>
							<el-button v-if="!row.is_default && !row.is_builtin_data" type="primary" link
								@click="deleteEvent(row.level_id)">{{ t("delete") }}</el-button>
						</template>
					</el-table-column>
				</el-table>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="labelTable.page" v-model:page-size="labelTable.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="labelTable.total"
						@size-change="getLevelListFn()" @current-change="getLevelListFn" />
				</div>
			</div>
		</el-card>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref } from 'vue'
	import { t } from '@/lang'
	import { ElMessageBox } from 'element-plus'
	import { getLevelList, deleteLevel } from '@/addon/home_service/api/level'
	import { useRoute, useRouter } from 'vue-router'
	import { setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title
	const labelTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		data: [],
		searchParam: {
			level_name:''
		}
	})
	
	// 核心：定义表单元素的配置
	const formFields = [
		{
			prop: 'level_name', // 对应searchParam的key
			label: t('levelSearch'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('levelSearchPlaceholder'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
			}
		},
	];
	const levelWeightList = ['默认等级', '一级', '二级', '三级', '四级', '五级', '六级', '七级', '八级', '九级', '十级']
	const getLevelListFn = (page : number = 1) => {
		labelTable.loading = true
		getLevelList({
			page: labelTable.page,
			limit: labelTable.limit,
			...labelTable.searchParam
		}).then((res : any) => {
			labelTable.data = res.data.data
			labelTable.total = res.data.total
			labelTable.loading = false
			setTablePageStorage(labelTable.page, labelTable.limit, labelTable.searchParam)
		}).catch(() => {
			labelTable.loading = false
		})
	}
	const handleFormReset = () => {
		labelTable.page = 1; // 重置页码（与原逻辑一致）
		getLevelListFn(); // 重置后重新搜索（与原逻辑一致）
	};
	
	getLevelListFn(getTablePageStorage(labelTable.searchParam).page)

	const addEvent = () => {
		router.push('/home_service/technician/level_edit')
	}
	const editEvent = (id : Number) => {
		router.push(`/home_service/technician/level_edit?id=${id}`)
	}
	// 删除等级
	const repeat = ref<boolean>(false)
	const deleteEvent = (id : number) => {
		ElMessageBox.confirm(t('levelDeleteTips'), t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(() => {
			if (repeat.value) return
			repeat.value = true
			deleteLevel(id).then(() => {
				getLevelListFn()
				repeat.value = false
			}).catch(() => {
				repeat.value = false
			})
		})
	}
</script>

<style lang="scss" scoped></style>