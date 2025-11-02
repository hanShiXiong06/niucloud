<template>
	<div class="main-container">
		<el-card class="card !border-none mb-[15px]" shadow="never">
			<el-page-header :content="pageName" :icon="ArrowLeft" @back="back" />
		</el-card>
		<el-card class="box-card !border-none" shadow="never" v-loading="loading">
			<el-card class="box-card !border-none table-search-wrap " shadow="never">
				<div class="main-container mt-[-15px]">
					<el-card class="box-card !border-none table-search-wrap" shadow="never">
						<div class="flex justify-between">
							<div class="text-[20px] font-bold" v-if="detailInfo.member">
								{{ detailInfo.member.username}}
							</div>
							<div>
								<el-button type="primary" @click="editEvent(detailInfo)">{{ t('edit') }}</el-button>
							</div>
						</div>
					</el-card>
					<div class="flex">
						<!-- 信息列表部分（每行3列，每列span=8） -->
						<el-row>
							<!-- 昵称 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.real_name">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('memberNickName') }}</span>
								<span class="text-[14px]">{{ detailInfo.real_name }}</span>
							</el-col>

							<!-- 手机号 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.mobile">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('memberMobile') }}</span>
								<span class="text-[14px]">{{ detailInfo.mobile }}</span>
							</el-col>

							<!-- 注册时间 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('registerTime') }}</span>
								<span class="text-[14px]">{{ detailInfo.create_time }}</span>
							</el-col>

							<!-- 注册类型 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('registerType') }}</span>
								<span
									class="text-[14px]">{{ detailInfo.source == 'application' ? '外部入驻' : '内部员工' }}</span>
							</el-col>

							<!-- 佣金比例 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('commsionRate') }}</span>
								<span class="text-[14px]"
									v-if="detailInfo.level">{{ detailInfo.level.order_rate }}%</span>
								<span class="text-[14px]" v-else>{{ detailInfo.order_rate }}%</span>
							</el-col>

							<!-- 人员状态 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('peopleStatus') }}</span>
								<span class="text-[14px]">
									<el-tag
										:type="detailInfo.status == 1 ? 'success' : detailInfo.status == -1 ? 'danger' : 'info'">{{ detailInfo.status_name }}</el-tag>
								</span>
							</el-col>

							<!-- 等级 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.level">
								<span class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('level') }}</span>
								<span class="text-[14px]">{{ detailInfo.level.level_name }}</span>
							</el-col>

							<!-- 分类 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]"
								v-if="detailInfo.category_name && detailInfo.category_name.length">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('category') }}</span>
								<span class="text-[14px] pr-[5px]" v-for="(item, index) in detailInfo.category_name"
									:key="index">
									{{ item.category_name }}
								</span>
							</el-col>

							<!-- 店铺 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.store">
								<span class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('shop') }}</span>
								<span class="text-[14px]">{{ detailInfo.store.contact_name }}</span>
							</el-col>

							<!-- 店铺电话 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.store">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('shopMobile') }}</span>
								<span class="text-[14px]">{{ detailInfo.store.mobile }}</span>
							</el-col>

							<!-- 地址 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.full_address">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('address') }}</span>
								<span class="text-[14px]">{{ detailInfo.full_address }}</span>
							</el-col>

							<!-- 身份证+图片列表（占满一行，span=24） -->
							<el-col :span="24" class="mt-[15px]">
								<div class="flex w-[100%]">
									<!-- 身份证图片 -->

									<!-- 其他图片列表 -->
									<div class="flex mt-[15px] w-[33%] break-all leading-[21px]"
										v-if="detailInfo.certificate">
										<span
											class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('imageList') }}</span>
										<span class="text-[14px] text-[#666666] pr-[20px]"
											v-for="(item, index) in detailInfo.certificate.split(',')" :key="index">
											<el-image style="width: 40px; height: 40px" :src="img(item)" fit="contain"
												:preview-src-list="[img(item)]">
												<template #error>
													<div
														class="flex justify-center items-center w-full h-[40px] bg-[#f4f4f4] rounded-[8px]">
														<img class="max-w-[40px]" src="@/app/assets/images/error.png"
															alt="" object-fit="contain">
													</div>
												</template>
											</el-image>
										</span>
									</div>
								</div>
							</el-col>
						</el-row>
						<div>
							<div class="member-info mr-[50px]">
								<div class="flex">
									<div class="text-[14px] min-w-[110px] mt-[15px] text-[#999999] ">{{ t('headimg') }}
									</div>
									<div class="flex items-end text-[14px] w-[150px] mt-[15px]">
										<div class="w-[150px] h-[150px] flex items-center justify-center rounded-[50%]">
											<img class="w-[150px] max-h-[150px] inline-block rounded-[50%]"
												v-if="detailInfo.headimg_mid" :src="img(detailInfo.headimg_mid)" alt="">
											<img class="w-[150px] max-h-[150px] inline-block rounded-full" v-else
												src="@/app/assets/images/member_head.png" alt="">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

			</el-card>
			<div class="mt-[20px]">
				<el-tabs v-model="activeName" class="demo-tabs" @tab-click="handleClick">
					<el-tab-pane :label="t('orderList')" name="order">
						<div class="mb-[15px]">
							<DynamicCollapseForm :fields="formFields" :search-param="orderTable.searchParam"
								@search="getOrderListFn" @reset="handleFormReset" ref="collapseFormRef" />
						</div>
						<el-table :data="orderTable.data" size="large" v-loading="orderTable.loading">
							<template #empty>
								<span>{{ !orderTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column :show-overflow-tooltip="true" :label="t('orderNo')" min-width="250"
								align="left">
								<template #default="{ row }">
									{{row.order_no}}
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('serviceProject')" min-width="200"
								align="left">
								<template #default="{ row }">
									{{row.order_name}}
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('buyer')" min-width="200"
								align="left">
								<template #default="{ row }">
									<div v-if="row.member">
										{{row.member.nickname}}
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('commsionNum')" min-width="200"
								align="left">
								<template #default="{ row }">
									￥{{row.technician_commission}}
								</template>
							</el-table-column>

							<el-table-column :show-overflow-tooltip="true" :label="t('shop')" min-width="120"
								align="left">
								<template #default="{ row }">
									<div class="flex items-center " v-if="row.store">
										<div class="flex flex-wrap">
											<p class="w-[100%] truncate">{{ row.store.store_name }}</p>
										</div>
									</div>
								</template>
							</el-table-column>
							<el-table-column prop="create_time" :label="t('createTime')" min-width="200" />
							<el-table-column prop="status" :label="t('status')" min-width="120">
								<template #default="{ row }">
									<el-tag v-if="row.order_status_info"
										:type="row.order_status == 'wait_service' ? 'danger': row.order_status == 'finish' ? 'success' :''">{{ row.order_status_info.name}}</el-tag>
								</template>
							</el-table-column>
							<el-table-column :label="t('operation')" fixed="right" min-width="120" align="right">
								<template #default="{ row }">
									<el-button type="primary" link
										@click="detailEvent(row,1)">详情</el-button>
								</template>
							</el-table-column>
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="orderTable.page" v-model:page-size="orderTable.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="orderTable.total"
								@size-change="getOrderListFn" @current-change="getOrderListFn" />
						</div>
					</el-tab-pane>



					<el-tab-pane :label="t('money')" name="money">
						<div class="mb-[15px]">
							<DynamicCollapseForm :fields="TransactionHistoryformFields"
								:search-param="transactionHistoryTable.searchParam"
								@search="getTransactionHistoryTableListFn"
								@reset="handleTransactionHistoryTableFormReset" ref="collapseFormRef" />
						</div>
						<el-table :data="transactionHistoryTable.data" size="large"
							v-loading="transactionHistoryTable.loading">
							<template #empty>
								<span>{{ !transactionHistoryTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column prop="related_id" :label="t('orderNo')" min-width="220" />
							<el-table-column prop="status" :label="t('moneyCount')" min-width="120">
								<template #default="{ row }">
									<span class="text-[#ff0000]">￥{{row.account_data}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('type')" min-width="120">
								<template #default="{ row }">
									<span>{{row.from_type_name}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('status')" min-width="120">
								<template #default="{ row }">
									<el-tag
										:type="row.status == 1 ? 'success': row.status == -1 ? 'danger' :'info'">{{row.status_name}}</el-tag>
								</template>
							</el-table-column>

							<el-table-column prop="memo" :label="t('notes')" min-width="300" />
							<el-table-column :label="t('endCalcTome')" min-width="200">
								<template #default="{ row }">
									<span>{{timeStampTurnTime(row.payment_time)}}</span>
								</template>
							</el-table-column>
							<el-table-column prop="create_time" :label="t('createTime')" min-width="200" />
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="transactionHistoryTable.page"
								v-model:page-size="transactionHistoryTable.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="transactionHistoryTable.total"
								@size-change="getTransactionHistoryTableListFn"
								@current-change="getTransactionHistoryTableListFn" />
						</div>
					</el-tab-pane>


					<el-tab-pane :label="t('evaluteData')" name="evalute">

						<div class="flex justify-between my-[20px] mx-[5px]">
							<div
								class="w-[32.5%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 bg-white rounded-lg">
								<div class="flex items-center">
									<span class="iconfont icondianzan text-[#6cdf15] text-[24px] mr-[10px]"></span>
									<span class="">{{t('likeRaio')}}</span>
								</div>
								<div class="text-[28px] text-[#6cdf15] font-bold">
									{{Tchevalstats.positive_rating}}
								</div>
							</div>

							<div
								class="w-[32.5%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 bg-white rounded-lg">
								<div class="flex items-center">
									<el-icon size="20" class=" mr-[10px]">
										<User color="#273de3" />
									</el-icon>
									<span class="">{{t('averagePoint')}}</span>
								</div>
								<div class="text-[28px] text-[#273de3] font-bold">
									{{detailInfo.evaluate_avg_scores || '5.00'}}分
								</div>
							</div>

							<div
								class="w-[32.5%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 bg-white rounded-lg">
								<div class="flex">
									<el-icon size="20" class=" mr-[10px]">
										<ChatLineSquare />
									</el-icon>
									<span class="">{{t('allEvalute')}}</span>
								</div>
								<div class="text-[28px] font-bold">
									{{Tchevalstats.total_evaluate_count}}条
								</div>
							</div>
						</div>

						<div class="mb-[15px]">
							<DynamicCollapseForm :fields="evaluationTableformFields"
								:search-param="evaluationTable.searchParam" @search="getEvaluationListFn"
								@reset="handleEvaluationFormReset" ref="collapseFormRef" />
						</div>
						<el-table :data="evaluationTable.data" size="large" v-loading="evaluationTable.loading">
							<template #empty>
								<span>{{ !evaluationTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column :show-overflow-tooltip="true" :label="t('buyer')" min-width="150"
								align="left">
								<template #default="{ row }">
									{{row.member.nickname}}
								</template>
							</el-table-column>
							<el-table-column prop="status" :label="t('starLevel')" min-width="180">
								<template #default="{ row }">
									<div class="flex">
										<el-rate v-model="row.scores" allow-half disabled show-score
											score-template="{value}星" />
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('evaluteNotes')" min-width="300"
								align="left">
								<template #default="{ row }">
									{{row.content}}
								</template>
							</el-table-column>
							<el-table-column prop="create_time" :label="t('evalutetime')" min-width="200" />
							<el-table-column :label="t('operation')" fixed="right" min-width="120" align="right">
								<template #default="{ row }">
									<el-button type="primary" link
										@click="detailEvent(row,2)">详情</el-button>
								</template>
							</el-table-column>
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="evaluationTable.page"
								v-model:page-size="evaluationTable.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="evaluationTable.total"
								@size-change="getEvaluationListFn" @current-change="getEvaluationListFn" />
						</div>
					</el-tab-pane>

					<el-tab-pane :label="t('refunedData')" name="complaint">

						<div class="flex justify-between my-[20px] mx-[5px]">
							<div
								class="w-[32.5%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 bg-white rounded-lg flex items-center">
								<div class="flex items-center mr-[25px]">
									<el-icon color="#ff0000" size="30">
										<WarnTriangleFilled />
									</el-icon>
								</div>
								<div class="flex flex-col">
									<div class="text-[28px] ">{{refunedTchevalstats.refund_refuse_count}}</div>
									<span class="text-[#999] text-[13px]">{{t('waitPlatformReview')}}</span>
								</div>
							</div>

							<div
								class="w-[32.5%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 bg-white rounded-lg flex items-center">
								<div class="flex items-center mr-[25px]">
									<el-icon color="#e7c30d" size="30">
										<MessageBox />
									</el-icon>
								</div>
								<div class="flex flex-col">
									<div class="text-[28px] ">{{refunedTchevalstats.wait_refund_count}}</div>
									<span class="text-[#999] text-[13px]">{{t('inPlatformReview')}}</span>
								</div>
							</div>

							<div
								class="w-[32.5%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 bg-white rounded-lg flex items-center">
								<div class="flex items-center mr-[25px]">
									<el-icon color="#6cdf15" size="30">
										<CircleCheckFilled />
									</el-icon>
								</div>
								<div class="flex flex-col">
									<div class="text-[28px] ">{{refunedTchevalstats.refund_completed_count}}</div>
									<span class="text-[#999] text-[13px]">{{t('finshPlatformReview')}}</span>
								</div>
							</div>
						</div>

						<div class="mb-[15px]">
							<DynamicCollapseForm :fields="complaintTableformFields"
								:search-param="complaintTable.searchParam" @search="getComplaintListFn"
								@reset="handleComplaintFormReset" ref="collapseFormRef" />
						</div>
						<el-table :data="complaintTable.data" size="large" v-loading="complaintTable.loading">
							<template #empty>
								<span>{{ !complaintTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column prop="refund_no" :label="t('complaintNo')" min-width="250" />
							<el-table-column prop="status" :label="t('complaintName')" min-width="120">
								<template #default="{ row }">
									<div v-if="row.member">
										<div>
											{{row.member.nickname}}
										</div>
										<div class="text-[13px] text-[#999999]">
											{{row.member.mobile}}
										</div>
									</div>
								</template>
							</el-table-column>
							<el-table-column prop="reason_name" :label="t('complaintType')" min-width="120">
								<template #default="{ row }">
									<div>
										{{row.reason_name}}
									</div>
								</template>
							</el-table-column>
							<el-table-column prop="status" :label="t('complaintStatus')" min-width="120">
								<template #default="{ row }">
									<div class="flex">
										<div class="text-[#6cdf15] px-[8px] rounded-[7px]"
											v-if="row.status == 'refund_completed'">
											{{row.status_name}}
										</div>
										<div class=" text-[#e7c30d] px-[8px]  rounded-[7px]"
											v-else-if="row.status == 'wait_refund'">
											{{row.status_name}}
										</div>
										<div class=" text-[#ff0000] px-[8px] rounded-[7px]" v-else>
											{{row.status_name}}
										</div>
									</div>
								</template>
							</el-table-column>
							<!-- <el-table-column :show-overflow-tooltip="true" :label="t('complaintresult')" min-width="200"
								align="left">
								<template #default="{ row }">
									{{}}
								</template>
							</el-table-column> -->
							<el-table-column :show-overflow-tooltip="true" :label="t('notes')" min-width="300"
								align="left">
								<template #default="{ row }">
									{{row.remark}}
								</template>
							</el-table-column>
							<el-table-column prop="create_time" :label="t('complaintTIme')" min-width="200" />
							<el-table-column :label="t('operation')" fixed="right" min-width="120" align="right">
								<template #default="{ row }">
									<el-button type="primary" link
										@click="detailEvent(row,3)">详情</el-button>
								</template>
							</el-table-column>
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="complaintTable.page"
								v-model:page-size="complaintTable.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="complaintTable.total"
								@size-change="getComplaintListFn" @current-change="getComplaintListFn" />
						</div>
					</el-tab-pane>

					<el-tab-pane :label="t('workTimeData')" name="workTime">
						<div class="text-center text-[20px] ">休息日历</div>
						<el-calendar ref="calendarRef" v-model="currentCalendarDate">
							<template #date-cell="{ data }">
								<!-- 新增 @click="handleDayClick(data)" 点击事件 -->
								<div class="custom-calendar-cell" :style="getCellStyle(data)"
									@click="handleDayClick(data)">
									<span class="cell-day" v-if="data.type === 'current-month'">
										{{ data.day.split('-')[2] }}
									</span>
									<template v-if="data.type === 'current-month' && getRestInfo(data.day)">
										<span class="cell-rest-text">
											{{ getRestInfo(data.day).hour_text }} 休息
										</span>
									</template>
								</div>
							</template>
						</el-calendar>
					</el-tab-pane>
				</el-tabs>
			</div>
		</el-card>

		<!-- 新增：休息时间表单弹窗 -->
		<el-dialog v-model="dialogVisible" :title="getDialogTitle()" width="500px" :close-on-click-modal="false"
			@closed="handleDialogClosed">
			<el-form ref="restFormRef" :model="restForm" :rules="formRules" label-width="100px" class="mt-4">
				<!-- 开始时间 -->
				<el-form-item label="开始时间" prop="startTime">
					<el-time-select v-model="restForm.startTime" :placeholder="t('pleaseSelectStartTime')"
						style="width: 240px" value-format="HH:mm" start="00:00" end="23:59" />
				</el-form-item>

				<!-- 结束时间 -->
				<el-form-item label="结束时间" prop="endTime">
					<el-time-select v-model="restForm.endTime" :placeholder="t('pleaseSelectEndTime')"
						style="width: 240px" value-format="HH:mm" start="00:00" end="23:59" />
				</el-form-item>


				<!-- 理由 -->
				<el-form-item label="请假理由" prop="notes">
					<el-select v-model="restForm.notes" placeholder="请选择" style="width: 240px">
						<el-option v-for="item in reasonList" :key="item.value" :label="item.label"
							:value="item.value" />
					</el-select>
				</el-form-item>


				<!-- 备注 -->
				<!-- <el-form-item label="备注">
					<el-input v-model="restForm.notes" type="textarea" :placeholder="t('pleaseEnterRemark')" rows="3"
						style="width: 240px" />
				</el-form-item> -->
			</el-form>

			<!-- 弹窗底部按钮 -->
			<template #footer>
				<el-button @click="dialogVisible = false">
					{{ t('cancel') }}
				</el-button>
				<el-button type="primary" @click="handleSaveRest" :loading="saveLoading">
					{{ t('save') }}
				</el-button>
			</template>
		</el-dialog>
	</div>
</template>
<script lang="ts" setup>
	import { reactive, ref, Ref, onMounted ,watch } from 'vue'
	import { t } from '@/lang'
	import { img, setTablePageStorage, getTablePageStorage, timeStampTurnTime } from '@/utils/common'
	import { useRoute, useRouter } from 'vue-router'
	import { setTechnicianRest, getWorkTimeRecords, getTechnicianList, deleteTechnician, getTechnicianDetail, getTechnicianrestreason, getTechnicianDetailorder, getTechnicianDetailAccount, getTechnicianAccount, getTchevalstats, getTechnicianRefund, getTechrefundstats } from '@/addon/home_service/api/technician'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { ElMessageBox } from 'element-plus'
	const route = useRoute()
	const router = useRouter()
	const id : number = parseInt(route.query.id as string) || 0
	const pageName = route.meta.title
	const name : string = route.query.name || ''
	const activeName = ref('order')
	const technicianTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		data: [],
	})
	const reasonList = ref([])
	const getTechnicianrestreasonFn = () => {
		let params = { technician_id: id }
		getTechnicianrestreason(params).then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				let obj = {
					label: res.data[item],
					value: item
				}
				reasonList.value.push(obj)
			})
		})
	}
	getTechnicianrestreasonFn()
	const orderTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			order_no: '',
			member_search: '',
			order_name: ''
		},
		data: [],
	})
	// const getWorkTimeRecordsFn = () => {
	// 	getWorkTimeRecords().then((res) => {
	// 		console.log(res)
	// 	})
	// }
	// getWorkTimeRecordsFn()
	const transactionHistoryTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			join_create_time: '',
		},
		data: [],
	})
	const evaluationTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			join_create_time: '',
			is_audit: 2,
		},
		data: [],
	})

	const complaintTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			create_time: '',
		},
		data: [],
	})

	// 核心：定义表单元素的配置
	const formFields = [
		{
			prop: 'order_no', // 对应searchParam的key
			label: t('orderNo'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('orderNoPlaceholder'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
			}
		},
		{
			prop: 'member_search', // 对应searchParam的key
			label: t('userName'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('userNamePlaceholder'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
			}
		},
		{
			prop: 'order_name', // 对应searchParam的key
			label: t('goodsName'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('goodsNamePlaceholder'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
			}
		},
	];


	// 核心：定义表单元素的配置
	const TransactionHistoryformFields = [
		{
			prop: 'join_create_time',
			label: t('TransactionHistorycreateTime'),
			component: ElDatePicker,
			placeholder: '', // 日期选择器无需默认占位符（用start/endPlaceholder）
			props: {
				type: 'datetimerange', // 原类型
				valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式
				startPlaceholder: t('startDate'), // 原开始占位符
				endPlaceholder: t('endDate'), // 原结束占位符
			}
		},
	];

	// 核心：定义表单元素的配置
	const evaluationTableformFields = [
		{
			prop: 'join_create_time',
			label: t('createTime'),
			component: ElDatePicker,
			placeholder: '', // 日期选择器无需默认占位符（用start/endPlaceholder）
			props: {
				type: 'datetimerange', // 原类型
				valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式
				startPlaceholder: t('startDate'), // 原开始占位符
				endPlaceholder: t('endDate'), // 原结束占位符
			}
		},
	];


	// 核心：定义表单元素的配置
	const complaintTableformFields = [
		{
			prop: 'join_create_time',
			label: t('TransactionHistorycreateTime'),
			component: ElDatePicker,
			placeholder: '', // 日期选择器无需默认占位符（用start/endPlaceholder）
			props: {
				type: 'datetimerange', // 原类型
				valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式
				startPlaceholder: t('startDate'), // 原开始占位符
				endPlaceholder: t('endDate'), // 原结束占位符
				clearable: true // 可选：增加清空按钮（体验优化）
			}
		},
	];
	/**
	 * 获取投诉列表
	 */
	const getComplaintListFn = (page : number = 1) => {
		complaintTable.loading = true
		complaintTable.page = page

		getTechnicianRefund({
			page: complaintTable.page,
			limit: complaintTable.limit,
			technician_id: id,
			...complaintTable.searchParam
		}).then(res => {
			complaintTable.loading = false
			complaintTable.data = res.data.data
			complaintTable.total = res.data.total
			setTablePageStorage(complaintTable.page, complaintTable.limit, complaintTable.searchParam)
		}).catch(() => {
			complaintTable.loading = false
		})
	}
	getComplaintListFn(getTablePageStorage(complaintTable.searchParam).page)
	const refunedTchevalstats = ref({})
	const getRefunedTchevalstatsFn = () => {
		getTechrefundstats({ technician_id: id }).then((res) => {
			refunedTchevalstats.value = res.data
		})
	}
	getRefunedTchevalstatsFn()
	/**
	 * 获取评价列表
	 */
	const getEvaluationListFn = (page : number = 1) => {
		evaluationTable.loading = true
		evaluationTable.page = page
		getTechnicianAccount({
			page: evaluationTable.page,
			limit: evaluationTable.limit,
			technician_id: id,
			...evaluationTable.searchParam
		}).then(res => {
			evaluationTable.loading = false
			evaluationTable.data = res.data.data
			evaluationTable.total = res.data.total
			setTablePageStorage(evaluationTable.page, evaluationTable.limit, evaluationTable.searchParam)
		}).catch(() => {
			evaluationTable.loading = false
		})
	}
	getEvaluationListFn(getTablePageStorage(evaluationTable.searchParam).page)
	const Tchevalstats = ref({})
	const getTchevalstatsFn = () => {
		getTchevalstats({ technician_id: id }).then((res) => {
			Tchevalstats.value = res.data
		})
	}
	getTchevalstatsFn()
	/**
	 * 获取订单列表
	 */
	const getOrderListFn = (page : number = 1) => {
		orderTable.loading = true
		orderTable.page = page

		getTechnicianDetailorder({
			page: orderTable.page,
			limit: orderTable.limit,
			technician_id: id,
			...orderTable.searchParam
		}).then(res => {
			orderTable.loading = false
			orderTable.data = res.data.data
			orderTable.total = res.data.total
			setTablePageStorage(orderTable.page, orderTable.limit, orderTable.searchParam)
		}).catch(() => {
			orderTable.loading = false
		})
	}
	getOrderListFn(getTablePageStorage(orderTable.searchParam).page)

	/**
	 * 获取账单流水列表
	 */
	const getTransactionHistoryTableListFn = (page : number = 1) => {
		transactionHistoryTable.loading = true
		transactionHistoryTable.page = page

		getTechnicianDetailAccount({
			page: transactionHistoryTable.page,
			limit: transactionHistoryTable.limit,
			technician_id: id,
			...transactionHistoryTable.searchParam
		}).then(res => {
			transactionHistoryTable.loading = false
			transactionHistoryTable.data = res.data.data
			transactionHistoryTable.total = res.data.total
			setTablePageStorage(transactionHistoryTable.page, transactionHistoryTable.limit, transactionHistoryTable.searchParam)
		}).catch(() => {
			transactionHistoryTable.loading = false
		})
	}
	getTransactionHistoryTableListFn(getTablePageStorage(transactionHistoryTable.searchParam).page)


	const handleComplaintFormReset = () => {
		complaintTable.page = 1; // 重置页码（与原逻辑一致）
		getComplaintListFn(); // 重置后重新搜索（与原逻辑一致）
	};

	const handleEvaluationFormReset = () => {
		evaluationTable.page = 1; // 重置页码（与原逻辑一致）
		getEvaluationListFn(); // 重置后重新搜索（与原逻辑一致）
	};
	const handleTransactionHistoryTableFormReset = () => {
		orderTable.page = 1; // 重置页码（与原逻辑一致）
		getTransactionHistoryTableListFn(); // 重置后重新搜索（与原逻辑一致）
	};
	const handleFormReset = () => {
		orderTable.page = 1; // 重置页码（与原逻辑一致）
		getOrderListFn(); // 重置后重新搜索（与原逻辑一致）
	};
	const detailInfo = ref({})
	const loading = ref(false)
	const getTechnicianDetailFn = async () => {
		loading.value = true
		const data = await (await getTechnicianDetail(id)).data
		loading.value = false
		detailInfo.value = data
	}
	getTechnicianDetailFn()
	const back = () => {
		history.back()
	}
	/**
	 * 编辑
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/technician/edit?id=' + data.id)
	}
	/**
	 * 获取师傅列表
	 */
	const getTechnicianFn = (page : number = 1) => {
		technicianTable.loading = true
		technicianTable.page = page

		getTechnicianList({
			page: technicianTable.page,
			limit: technicianTable.limit,
			...technicianTable.searchParam
		}).then(res => {
			technicianTable.loading = false
			technicianTable.data = res.data.data
			technicianTable.total = res.data.total
			setTablePageStorage(technicianTable.page, technicianTable.limit, technicianTable.searchParam)
		}).catch(() => {
			technicianTable.loading = false
		})
	}
	getTechnicianFn(getTablePageStorage(technicianTable.searchParam).page)
	const dialogVisible = ref(false); // 弹窗显示状态
	const currentSelectDate = ref(''); // 当前点击的日历日期（格式：YYYY-MM-DD）
	const saveLoading = ref(false); // 保存按钮加载状态

	// 新增：表单数据模型（开始时间、结束时间、备注）
	const restForm = reactive({
		startTime: '', // 开始时间（对应 hour_array[0]）
		endTime: '',   // 结束时间（对应 hour_array[length-1]）
		notes: '',// 备注（后端若有该字段可直接映射，无则新增）
	});

	// 新增：表单校验规则（开始/结束时间必填）
	const formRules = reactive<FormRules>({
		startTime: [
			{ required: true, message: t('pleaseSelectStartTime'), trigger: 'blur' }
		],
    notes: [
      { required: true, message: t('pleaseEnterRemark'), trigger: 'blur' }
    ],
		endTime: [
			{ required: true, message: t('pleaseSelectEndTime'), trigger: 'blur' },
			// 新增：结束时间必须晚于开始时间
			{
				validator: (rule, value, callback) => {
					if (!restForm.startTime) {
						// 先校验开始时间是否已选择
						callback();
						return;
					}
					if (value && restForm.startTime >= value) {
						callback(new Error(t('endTimeMustBeAfterStartTime')));
					} else {
						callback();
					}
				},
				trigger: 'blur'
			}
		]
	});

	// 新增：表单实例（用于重置/校验）
	const restFormRef = ref<FormInstance | null>(null);
	// 1. 存储后端返回的休息数据（日期 -> 休息信息映射）
	const restDataMap : Ref<Record<string, any>> = ref({});
	// 当前日历显示的月份（根据后端数据调整，示例为2025年9月）
	const calendarRef : Ref<CalendarInstance | null> = ref(null);
	
	// 1. 先声明并初始化 currentCalendarDate
	const currentCalendarDate: Ref<Date> = ref(new Date(2025, 8, 1));
	
	// 2. 再定义依赖 currentCalendarDate 的函数 getWorkTimeRecordsFn
	const getWorkTimeRecordsFn = async () => {
	  const year = currentCalendarDate.value.getFullYear();
	  let month = currentCalendarDate.value.getMonth() + 1; // 月份是 0~11，需 +1 转为 1~12
	  month = month < 10 ? `0${month}` : `${month}`; // 保证月份为两位数（如 “09”）
	  const yearMonth = `${year}-${month}`;
	
	  const params = {
	    technician_id: id,
	    store_id: detailInfo.value.store_id,
	    date: yearMonth,
	  };
	
	  try {
	    const res = await getWorkTimeRecords(params);
	    const restList = res.data;
	    const map: Record<string, any> = {};
	    restList.forEach((item: any) => {
	      map[item.date] = item;
	    });
	    restDataMap.value = map;
	  } catch (err) {
	    console.error('获取休息数据失败：', err);
	  }
	};
	
	// 3. 最后设置 watch 监听 currentCalendarDate（此时变量和函数都已就绪）
	watch(currentCalendarDate, () => {
	  getWorkTimeRecordsFn();
	}, { immediate: true }); // immediate: true → 组件初始化时也会执行一次
	
	// 3. 根据日期获取休息信息（用于判断是否休息及显示hour_text）
	const getRestInfo = (dayStr : string) => {
		return restDataMap.value[dayStr]; // 存在则返回该日期的休息信息
	};

	// 4. 单元格样式（仅休息日期添加特殊背景）
	const getCellStyle = (data : any) => {
		const isRest = data.type === 'current-month' && getRestInfo(data.day);
		return isRest
			? {
				backgroundColor: '#ffebee',
				color: '#b71c1c',
				height: '100%',
				display: 'flex',
				flexDirection: 'column',
				alignItems: 'center',
				justifyContent: 'center'
			}
			: {
				height: '100%',
				display: 'flex',
				flexDirection: 'column',
				alignItems: 'center',
				justifyContent: 'center'
			};
	};
	// 新增：日历单元格点击事件
	const handleDayClick = (data : any) => {
		// 仅处理当前月份的日期（上月/下月日期不触发）
		if (data.type !== 'current-month') return;

		// 1. 记录当前点击的日期
		currentSelectDate.value = data.day;

		// 2. 重置表单和校验状态
		if (restFormRef.value) {
			restFormRef.value.resetFields();
		}
		restForm.notes = ''; // 重置备注

		// 3. 检查当前日期是否有休息数据，有则反向赋值
		const restInfo = getRestInfo(data.day);
		if (restInfo && restInfo.hour_array && restInfo.hour_array.length > 0) {
			// 开始时间 = hour_array 第一项，结束时间 = hour_array 最后一项
			restForm.startTime = restInfo.hour_array[0];
			restForm.endTime = restInfo.hour_array[restInfo.hour_array.length - 1];
			// 备注：若后端返回 remark 字段，直接赋值（这里假设后端有该字段，无则留空）
			restForm.notes = restInfo.notes || '';
		}

		// 4. 打开弹窗
		dialogVisible.value = true;
	};
	// 新增：动态获取弹窗标题
	const getDialogTitle = () => {
		const hasRestData = !!getRestInfo(currentSelectDate.value);
		return hasRestData ? t('editRestTime') : t('addRestTime');
	};

	// 新增：保存休息时间（新增/编辑）
	const handleSaveRest = async () => {
		// 1. 表单校验
		if (!restFormRef.value) return;
		try {
			await restFormRef.value.validate();
		} catch (error) {
			// 校验失败，不执行后续逻辑
			return;
		}

		try {
			// 2. 生成每30分钟间隔的时间点数组（核心修改）
			const hourArray = generateHourArray(restForm.startTime, restForm.endTime);
			if (hourArray.length === 0) {
				ElMessage.error(t('invalidTimeRange'));
				return;
			}

			// 3. 整理提交给后端的参数（按接口要求）
			const submitParams = {
				store_id: detailInfo.value.store_id, // 门店ID（从现有数据获取）
				technician_id: id, // 师傅ID（已有）
				date: currentSelectDate.value, // 选择的日期
				hour: hourArray.join(','), // 转换为字符串（如 "16:30,17:00,17:30"）
				// （接口文档中未要求hour_array，但为了本地显示可保留，若接口不需要可删除）
				hour_array: hourArray,
				notes: restForm.notes
			};

			// 4. 调用接口保存
			saveLoading.value = true;
			await setTechnicianRest(submitParams);

			// 5. 更新本地数据（实时刷新日历）
			restDataMap.value[currentSelectDate.value] = {
				...submitParams,
				hour_text: `${restForm.startTime}--${restForm.endTime}` // 显示用的文本
			};

			ElMessage.success(t('saveSuccess'));
			dialogVisible.value = false;
		} catch (error : any) {
			// 捕获时间生成错误或接口错误
			ElMessage.error(error.message || t('saveFailed'));
			console.error('保存失败：', error);
		} finally {
			saveLoading.value = false;
		}
	};

	const handleDialogClosed = () => {
		if (restFormRef.value) {
			restFormRef.value.resetFields();
		}
		restForm.notes = ''; // 重置备注
		currentSelectDate.value = ''; // 清空当前选择日期
	};
	// 页面加载时获取数据
	onMounted(() => {
		getWorkTimeRecordsFn();
	});

	// 新增：根据开始时间和结束时间，生成每30分钟一个的时间点数组
	const generateHourArray = (startTime : string, endTime : string) : string[] => {
		const hourArray : string[] = [];
		// 解析开始时间和结束时间为Date对象（用同一天的日期作为载体）
		const [startHour, startMinute] = startTime.split(':').map(Number);
		const [endHour, endMinute] = endTime.split(':').map(Number);

		const start = new Date();
		start.setHours(startHour, startMinute, 0, 0);

		const end = new Date();
		end.setHours(endHour, endMinute, 0, 0);

		// 若开始时间晚于结束时间，提示错误（表单校验应提前阻止）
		if (start > end) {
			throw new Error(t('endTimeMustBeAfterStartTime'));
		}

		// 循环生成每30分钟的时间点
		let current = new Date(start);
		while (current <= end) {
			// 格式化为 HH:mm
			const hour = current.getHours().toString().padStart(2, '0');
			const minute = current.getMinutes().toString().padStart(2, '0');
			hourArray.push(`${hour}:${minute}`);

			// 增加30分钟
			current.setMinutes(current.getMinutes() + 30);
		}

		return hourArray;
	};
	const detailEvent =  (row,ev) => { 
		console.log(row)
		console.log(ev)
		var start = ''
		if(ev == 1){
			start ='orderInfo'
		}else if(ev == 2){
			start ='evaluate'
		}else{
			start ='refund'
		}
		// if(ev == 1 || ev == 2){
			router.push('/home_service/order/detail?order_id=' + row.order_id+'&status='+start)
		// }
 	};
</script>
<style scoped>
	.border-raius-style {
		border-radius: 15px 15px 15px 0
	}

	/* 调整单元格样式以容纳更多内容 */
	::v-deep (.el-calendar-table__cell) {
		padding: 0 !important;
		height: 100px !important;
		/* 增加高度，确保显示完整 */
		border: none !important;
	}

	.custom-calendar-cell {
		width: 100%;
		height: 100%;
		padding: 8px 0;
	}

	.cell-day {
		font-size: 16px;
		font-weight: 500;
		margin-bottom: 4px;
	}

	/* 休息信息样式（显示hour_text + 休息） */
	.cell-rest-text {
		font-size: 12px;
		color: #ff5252;
		text-align: center;
		line-height: 1.4;
		/* 优化多行显示 */
	}

	/* 鼠标悬停效果 */
	::v-deep (.el-calendar-table tr:hover .el-calendar-table__cell) {
		background-color: #fff8f8 !important;
	}
</style>