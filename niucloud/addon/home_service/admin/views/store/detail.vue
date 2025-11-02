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
							<div class="text-[20px] font-bold">
								{{ detailInfo.store_name}}
							</div>
							<div>
								<el-button type="primary" @click="editEvent(detailInfo)">{{ t('edit') }}</el-button>
							</div>
						</div>
					</el-card>
					<div class="flex">
						<el-row>
							<!-- 会员昵称 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.member">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('memberNickName') }}</span>
								<span class="text-[14px]">{{ detailInfo.member.nickname }}</span>
							</el-col>

							<!-- 会员手机号 -->
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

							<!-- 地址 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.full_address">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('address') }}</span>
								<span class="text-[14px]">{{ detailInfo.full_address }}</span>
							</el-col>

							<!-- 证件号码 -->
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('证件号码') }}</span>
								<span class="text-[14px]">{{ detailInfo.id_number }}</span>
							</el-col>

							<!-- 所属店铺 -->
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

							<!-- 证件/许可证图片区域（占满一行，span=24） -->
							<el-col :span="24" class="mt-[15px]">
								<div class="flex w-[100%]">
									<!-- 身份证图片 -->
									<div class="flex mt-[15px] w-[33%] break-all leading-[21px]">
										<span
											class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('idCardImage') }}</span>
										<div class="flex items-center justify-between">
											<div class="relative mr-[20px]">
												<el-image style="width: 40px; height: 30px"
													:src="img(detailInfo.id_card_front)" fit="contain"
													:preview-src-list="[img(detailInfo.id_card_front)]">
													<template #error>
														<div class="flex justify-center items-center w-full h-[30px]">
															<img class="max-w-[40px]"
																src="@/app/assets/images/goods_default.png" alt=""
																object-fit="contain">
														</div>
													</template>
												</el-image>
												<div
													class="bg-black opacity-50 absolute bottom-0 left-0 w-[40px] flex z-10 justify-center items-center text-[10px] text-[#fff] box-border">
													正
												</div>
											</div>
											<div class="relative">
												<el-image style="width: 40px; height: 30px"
													:src="img(detailInfo.id_card_back)" fit="contain"
													:preview-src-list="[img(detailInfo.id_card_back)]">
													<template #error>
														<div class="flex justify-center items-center w-full h-[30px]">
															<img class="max-w-[40px]"
																src="@/app/assets/images/goods_default.png" alt=""
																object-fit="contain">
														</div>
													</template>
												</el-image>
												<div
													class="bg-black opacity-50 absolute bottom-0 left-0 w-[40px] z-10 flex justify-center items-center text-[10px] text-[#fff] box-border">
													反
												</div>
											</div>
										</div>
									</div>

									<!-- 许可证图片 -->
									<div class="flex mt-[15px] w-[33%] break-all leading-[21px]"
										v-if="detailInfo.license_img">
										<span
											class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('licenseImg') }}</span>
										<span class="text-[14px] text-[#666666] pr-[20px]"
											v-for="(item, index) in detailInfo.license_img.split(',')" :key="index">
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
							<div class="member-info w-[550px]">
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
					<el-tab-pane :label="t('tenchnicanList')" name="technician">
						<div class="mb-[15px]">
							<DynamicCollapseForm :fields="formTechnicianFields" :search-param="technicianTable.searchParam"
								@search="getTechnicianListFn" @reset="handleTechnicianFormReset" ref="collapseFormRef" />
						</div>
						<el-table :data="technicianTable.data" size="large" v-loading="technicianTable.loading">
							<template #empty>
								<span>{{ !technicianTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column :show-overflow-tooltip="true" :label="t('tecnicanInfo')" min-width="200" align="left">
								<template #default="{ row }">
									<div class="flex items-center cursor-pointer " v-if="row.technician">
										<el-image style="width: 60px; height: 60px" class="mr-[10px] rounded-[50%] w-[50%]"
											:src="img(row.technician.headimg)" fit="contain" :preview-src-list="[img(row.technician.headimg)]">
											<template #error>
												<div class="flex justify-center items-center w-full h-[60px]"><img
														class="max-w-[60px]" src="@/app/assets/images/member_head.png" alt=""
														object-fit="contain"></div>
											</template>
										</el-image>
										<div class="flex flex-col w-[50%]">
											<span
												class="overflow-hidden text-ellipsis line-clamp-1">{{ row.technician.real_name || '' }}</span>
										</div>
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('memberMobile')" min-width="120"
								align="left">
								<template #default="{ row }">
									<div v-if="row.technician">{{row.technician.mobile}}</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('orderNums')" min-width="100"
								align="left">
								<template #default="{ row }">
									<div v-if="row.technician">
										{{row.technician.order_num}}
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('commsionNumMoney')" min-width="100"
								align="left">
								<template #default="{ row } ">
									<div class="flex items-center" v-if="row.technician">
										<span class="pr-[10px] flex items-center">￥{{row.technician.achievement}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('point')" min-width="200"
								align="left">
								<template #default="{ row }">
									<div v-if="row.technician">
										<el-rate v-model="row.technician.evaluate_avg_scores" allow-half disabled   />
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('commsionRate')" min-width="100"
								align="left">
								<template #default="{ row }">
									<div v-if="row.technician">
										<span class="pr-[10px] flex items-center text-[#273de3]">{{row.order_rate}}%</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('statusName')" min-width="100"
								align="left">
								<template #default="{ row,$index }">
									<div class="flex items-center" v-if="row.technician">
										<el-tag
											:type="row.technician.status == 0 ? 'danger': row.technician.status == 1 ? 'success' :''">{{ row.technician.status_name}}</el-tag>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('operation')" fixed="right" min-width="150" align="right">
								<template #default="{ row }">
									<el-button type="primary" link
										@click="detailTechnicianEvent(row)">详情</el-button>
									<el-button type="primary" link
										@click="openRatioDialog(row)">设置比例</el-button>
								</template>
							</el-table-column>
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="technicianTable.page" v-model:page-size="technicianTable.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="technicianTable.total"
								@size-change="getOrderListFn" @current-change="getOrderListFn" />
						</div>
					</el-tab-pane>
					
					
					
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
							<el-table-column :show-overflow-tooltip="true" :label="t('technican')" min-width="200"
								align="left">
								<template #default="{ row }">
									<div v-if="row.technician">
										{{row.technician.real_name}}
									</div>
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
							<el-table-column :show-overflow-tooltip="true" :label="t('commsionNum')" min-width="250"
								align="left">
								<template #default="{ row } ">
									<div class="flex items-center">
										<span class="pr-[10px] flex items-center">师傅<span class="text-[12px]">￥</span> {{row.technician_commission}}</span>
										<div class="w-[2px] h-[10px] bg-[#111] "></div>
										<span class="pl-[10px] flex items-center ">门店<span class="text-[12px]">￥</span> {{row.store_commission}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column prop="create_time" :label="t('yuyueTIme')" min-width="200" />
							<el-table-column prop="status" :label="t('status')" min-width="120">
								<template #default="{ row }">
									<el-tag v-if="row.order_status_info"
										:type="row.order_status == 'wait_service' ? 'danger': row.order_status == 'finish' ? 'success' :''">{{ row.order_status_info.name}}</el-tag>
								</template>
							</el-table-column>
							<el-table-column :label="t('operation')" fixed="right" min-width="80" align="right">
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
							<el-table-column prop="related_id" :label="t('orderNo')" min-width="250" />
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
						<div class="w-[50%] m-[20px]">
							<div class="flex justify-between w-[100%] store-rate">
								<el-rate v-model="detailInfo.evaluate_avg_scores" allow-half disabled  size="default" show-score class="mb-[30px]"  />
							</div>
							<div class="text-[13px] text-[#999999]">
								项目 + 师傅合并评星（1-5分）
							</div>
							<div class="text-[13px] text-[#999999]">
								评星规则：1-2分（差），3分（一般），4-5分（好）
							</div>
						</div>
						
						<div class="flex items-center my-[20px] mx-[5px]">
							<div
								class="w-[25%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] p-6 mr-10 bg-white rounded-lg">
								<div class="flex items-center">
									<span class="">{{t('likeRaio')}}</span>
								</div>
								<div class="text-[28px] text-[#6cdf15] font-bold">
									{{Tchevalstats.positive_rating}}
								</div>
							</div>
							<div
								class="w-[25%] p-[40px] shadow-[0_0_6px_1px_rgba(0,0,0,0.1)] mr-10 p-6 bg-white rounded-lg">
								<div class="flex">
									<span class="">{{t('allEvalute')}}</span>
								</div>
								<div class="text-[28px] font-bold">
									{{Tchevalstats.total_evaluate_count}}
								</div>
							</div>
							
							<div class="">
								<el-button type="primary"
									@click="detailEvent(row,2)">{{ t('查看全部评价列表') }}</el-button>
							</div>
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
							<el-table-column prop="status" :label="t('complaintType')" min-width="120">
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
							<el-table-column :label="t('operation')" fixed="right" min-width="80" align="right">
								<template #default="{ row }">
									<el-button type="primary" link
										@click="detailorderEvent(row)">详情</el-button>
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
				</el-tabs>
			</div>
		</el-card>

	</div>
	<!-- 设置比例弹窗 -->		
	<el-dialog
		v-model="ratioDialogVisible"
		:title="t('setRatio')"
		width="400px"
		:before-close="closeRatioDialog"
	>
		<el-form
			ref="ratioFormRef"
			:model="ratioForm"
			label-width="120px"
		>
			<el-form-item
				label="分佣比例"
				prop="service_ratio"
				:rules="[
					{ required: true, message: t('ratioRequired'), trigger: 'blur' },
					{ 
						validator: (rule: any, value: any, callback: any) => {
							const num = parseFloat(value)
							if (isNaN(num) || num < 1 || num > 100) {
								callback(new Error(t('ratioRange')))
							} else {
								callback()
							}
						},
						trigger: 'blur' 
					}
				]"
			>
				<el-input
					v-model="ratioForm.service_ratio"
					placeholder="请输入1-100之间的数字"
					style="width: 180px;"
				>
					<template #append>%</template>
				</el-input>
			</el-form-item>
		</el-form>
		<template #footer>
			<span class="dialog-footer">
				<el-button @click="closeRatioDialog">{{ t('cancel') }}</el-button>
				<el-button type="primary" @click="handleRatioSubmit">{{ t('confirm') }}</el-button>
			</span>
		</template>
	</el-dialog>
</template>
<script lang="ts" setup>
	import { reactive, ref, Ref, onMounted } from 'vue'
	import { t } from '@/lang'
	import { img, setTablePageStorage, getTablePageStorage, timeStampTurnTime } from '@/utils/common'
	import { useRoute, useRouter } from 'vue-router'
	import { getstoreList, deletestore, setTechnicanRatio,getstoreDetail, getstoreDetailorder, getstoreDetailAccount, getStoreAccount,getstoreDetailtechnician, getTchevalstats, getStoreRefund, getTechrefundstats } from '@/addon/home_service/api/store'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { ElMessageBox, ElMessage } from 'element-plus'
	const route = useRoute()
	const router = useRouter()
	const id : number = parseInt(route.query.id as string) || 0
	const pageName = route.meta.title
	const name : string = route.query.name || ''
	const activeName = ref('technician')
	const detailorderEvent = (info:any) =>{
		router.push(`/home_service/order/detail?order_id=${info.order_id}`)
	}
	const StoreTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		data: [],
	})
	
	const technicianTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			real_name: '',
		},
		data: [],
	})
	
	const orderTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			order_no: '',
			member_search: '',
			technician_name: '',
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

	const complaintTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		searchParam: {
			join_create_time: '',
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
			prop: 'technician_name', // 对应searchParam的key
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
	const formTechnicianFields = [
		{
			prop: 'real_name', // 对应searchParam的key
			label: t('realName'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('realNamePlaceholder'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
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

		getStoreRefund({
			page: complaintTable.page,
			limit: complaintTable.limit,
			store_id:id,
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
		getTechrefundstats({ store_id: id }).then((res) => {
			refunedTchevalstats.value = res.data
		})
	}
	getRefunedTchevalstatsFn()
	const Tchevalstats = ref({})
	const getTchevalstatsFn = () => {
		getTchevalstats({ store_id: id }).then((res) => {
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

		getstoreDetailorder({
			page: orderTable.page,
			limit: orderTable.limit,
			store_id: id,
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
	 * 获取师傅列表
	 */
	const getTechnicianListFn = (page : number = 1) => {
		technicianTable.loading = true
		technicianTable.page = page
		getstoreDetailtechnician({
			page: technicianTable.page,
			limit: technicianTable.limit,
			store_id: id,
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
	getTechnicianListFn(getTablePageStorage(technicianTable.searchParam).page)
	
	
	
	/**
	 * 获取账单流水列表
	 */
	const getTransactionHistoryTableListFn = (page : number = 1) => {
		transactionHistoryTable.loading = true
		transactionHistoryTable.page = page

		getstoreDetailAccount({
			page: transactionHistoryTable.page,
			limit: transactionHistoryTable.limit,
			store_id: id,
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

	const handleTransactionHistoryTableFormReset = () => {
		orderTable.page = 1; // 重置页码（与原逻辑一致）
		getTransactionHistoryTableListFn(); // 重置后重新搜索（与原逻辑一致）
	};
	
	const handleTechnicianFormReset = () => {
		technicianTable.page = 1; // 重置页码（与原逻辑一致）
		getTechnicianListFn()
	};
	
	const handleFormReset = () => {
		orderTable.page = 1; // 重置页码（与原逻辑一致）
		getOrderListFn(); // 重置后重新搜索（与原逻辑一致）
	};
	const detailInfo = ref({})
	const loading = ref(false)
	const getstoreDetailFn = async () => {
		loading.value = true
		const data = await (await getstoreDetail(id)).data
		loading.value = false
		detailInfo.value = data
	}
	getstoreDetailFn()
	const back = () => {
		history.back()
	}
	/**
	 * 编辑
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/Store/edit?id=' + data.store_id)
	}
	/**
	 * 获取师傅列表
	 */
	const getStoreFn = (page : number = 1) => {
		StoreTable.loading = true
		StoreTable.page = page

		getstoreList({
			page: StoreTable.page,
			limit: StoreTable.limit,
			...StoreTable.searchParam
		}).then(res => {
			StoreTable.loading = false
			StoreTable.data = res.data.data
			StoreTable.total = res.data.total
			setTablePageStorage(StoreTable.page, StoreTable.limit, StoreTable.searchParam)
		}).catch(() => {
			StoreTable.loading = false
		})
	}
	getStoreFn(getTablePageStorage(StoreTable.searchParam).page)
	
	const detailTechnicianEvent = (data:any) =>{
	console.log(data)
	router.push('/home_service/technician/detail?id=' + data.technician_id)
}

// 设置比例相关
const ratioDialogVisible = ref(false)
const currentTechnician = ref<any>(null)
const ratioForm = ref({
	service_ratio: ''
})
const ratioFormRef = ref()

const openRatioDialog = (row: any) => {
	currentTechnician.value = row
	ratioForm.value.service_ratio = row.order_rate || ''
	ratioDialogVisible.value = true
}

const closeRatioDialog = () => {
	ratioDialogVisible.value = false
	// 重置表单
	if (ratioFormRef.value) {
		ratioFormRef.value.resetFields()
	}
}

const handleRatioSubmit = () => {
	ratioFormRef.value.validate((valid: boolean) => {
		if (valid) {
			const params = {
				store_id: id,
				technician_id: currentTechnician.value.technician.id,
				order_rate: parseFloat(ratioForm.value.service_ratio)
			}
			setTechnicanRatio(params).then(() => {
				ElMessage.success(t('setSuccess'))
				closeRatioDialog()
				// 刷新表格数据
				getTechnicianListFn(technicianTable.page)
			}).catch(() => {
				ElMessage.error(t('setFailed'))
			})
		}
	})
}
const detailEvent = (row,type)=>{
	console.log(type)
	console.log(id)
	if(type == 1){
		router.push(`/home_service/order/detail?order_id=${row.order_id}`)
	}else	if(type == 2){
		router.push(`/home_service/order/evaluate?store_id=${id}`)
	}

}
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
	::v-deep .store-rate .el-rate__icon {
	  font-size: 34px !important; /* 默认约 16px，按需调整，越大星星越大 */
	  margin-right: 8px !important; /* 可选：调整星星间距，避免挤在一起 */
	}
	
	/* 2. 可选：放大评分数字（如果需要同步放大分数） */
	::v-deep .store-rate .el-rate__text {
	  font-size: 34px !important; /* 默认约 14px，与星星尺寸匹配 */
	  margin-left: 8px !important; /* 调整分数与星星的间距 */
	}
</style>