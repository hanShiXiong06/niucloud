<template>
	<div class="main-container" v-loading="loading">
		<el-card class="card !border-none mb-[15px]" shadow="never">
			<el-page-header content="订单详情" :icon="ArrowLeft" @back="back" />
		</el-card>
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex pr-[40px]  pt-[60px] bg-[#fcfcfc] mb-[30px] rounded-[15[x]]">
				<div class="text-[25px] w-[280px] pl-[30px] text-center font-bold">
					{{detailInfo.goodsCategory?.category_name}}
				</div>
				<div class="flex-1">
					<el-steps :active="activeStep" direction="horizontal" class="custom-steps">
						<el-step v-for="(step, index) in steps" :key="index" :title="step.title" :icon="step.icon">
							<!-- 最后一步不需要显示中间内容 -->
							<template #description v-if="index < steps.length - 1">
								<div class="step-middle-content">
									<div class="time-cost">{{ step.timeCost }}</div>
									<el-tag effect="dark" type="success" class="mt-[5px]" v-if="step.status">{{ step.status }}</el-tag>
									<div class="absolute top-[-20px]  !text-[12px] font-bold !text-[#160be4]" :class=" steps.length == 6 ? 'left-[-80%]' : (steps.length == 7 ? 'left-[-62%]' : 'left-[-50%]')">{{timeStampTurnTime(step.time_value)}}</div>
								</div>
							</template>
						</el-step>
					</el-steps>
				</div>
			</div>
			<div class="flex justify-between">
				<div class="flex items-center">
					<div class="text-[15px] w-[280px]">
						{{t('orderNo')}}:
						<span class="font-bold">{{detailInfo.order_no}}</span>
					</div>
					<div class="">
						<el-tag
							:type="{'in_service': 'warning', 'finish': 'success', 'close': 'info','wait_service' : 'primary'}[detailInfo.order_status] || 'danger'"
							v-if="detailInfo.order_status_info">{{detailInfo.order_status_info.name}}</el-tag>
					</div>
				</div>

				<div class="relative z-index-999" >
					<el-button type="primary"
						@click="orderlabel(detailInfo)">{{ t('orderLabel') }}</el-button>
					<el-button type="primary" @click="handlePopupOrderAction(detailInfo,item.key)"
						v-for="(item,index) in detailInfo.order_status_info?.action"
						:key="index">{{ item.name }}</el-button>
					<!-- <el-button type="primary" link @click="handleOrderAction(row)">{{ t('resetPOrder') }}</el-button> -->
				</div>
			</div>
			<el-divider></el-divider>
			<el-tabs :tab-position="tabPosition" v-model="activeStatus" >
				<el-tab-pane :label="item.label" v-for="(item,index) in detailStatusList" :name="item.value"
					:key="index">
					<div v-if="item.value == 'orderInfo'" class="px-[20px]">
						<el-divider content-position="left">{{ t('orderInfoMessage') }}<span
								class="text-[13px] text-[#666]"
								v-if="detailInfo.time_reminder?.text">（{{detailInfo.time_reminder?.text}}）</span></el-divider>
						<el-row>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('orderNo') }}</span>
								<span class="text-[14px]">{{ detailInfo.order_no }}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('workOrderStatus') }}</span>
								<el-tag
									:type="{'in_service': 'warning', 'finish': 'success', 'close': 'info','wait_service' : 'primary'}[detailInfo.order_status] || 'danger'"
									v-if="detailInfo.order_status_info">{{detailInfo.order_status_info.name}}</el-tag>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('technicianName') }}</span>
								<span class="text-[14px]">{{ detailInfo.technician?.real_name }}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('orderFromName') }}</span>
								<span class="text-[14px]">{{ detailInfo.order_from_name }}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('storeName') }}</span>
								<span class="text-[14px]">{{ detailInfo.store?.store_name || '---'}}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('dispatchOrder') }}</span>
								<span class="text-[14px]">{{ detailInfo.dispatch_time }}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('reservationTime') }}</span>
								<span class="text-[14px]">{{ detailInfo.reserve_service_time }}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('createTime') }}</span>
								<span class="text-[14px]">{{ detailInfo.create_time }}</span>
							</el-col>
						</el-row>
						<div class="my-[35px]">
							<el-divider content-position="left">{{ t('customerInfo') }}</el-divider>
						</div>
						<el-row>
							<el-col :span="4" class="mt-[15px] break-all leading-[21px]">
								<div class="flex items-center">
									<el-icon color="#999">
										<Avatar />
									</el-icon><span class="text-[14px] pl-[5px]">{{detailInfo.taker_name}}</span>
								</div>
							</el-col>
							<el-col :span="6" class="mt-[15px] break-all leading-[21px]">
								<div class="flex items-center">
									<el-icon color="#999">
										<PhoneFilled />
									</el-icon><span
										class="text-[14px] pl-[5px] pr-[3px]">{{detailInfo.taker_mobile}}</span><el-icon
										@click="copyEvent(detailInfo.taker_mobile)" class="cursor-pointer">
										<CopyDocument />
									</el-icon>
								</div>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<div class="flex items-center">
									<el-icon color="#999">
										<MapLocation />
									</el-icon><span
										class="text-[14px] pl-[5px]">{{detailInfo.taker_full_address || '---'}}</span>
								</div>
							</el-col>
						</el-row>
						<div class="my-[35px]">
							<el-divider content-position="left">{{ t('serviceItem') }}</el-divider>
						</div>
						<el-table :data="detailInfo.service_items" border>
							<template #empty>
								<span>{{ !detailInfo.service_items ? t('emptyData') : '' }}</span>
							</template>
							<el-table-column prop="item_name" :label="t('projectName')" width="180" align="left" />
							<el-table-column :label="t('sellingPrice')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.price}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('technicianCommission')" min-width="120">
								<template #default="{ row }">
									<div class="text-[#e6a23c]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.technician_commission}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('storeCommission')" min-width="120">
								<template #default="{ row }">
									<div class="text-[#273de3]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.store_commission}}</span>
									</div>
								</template>
							</el-table-column>

              <el-table-column :label="t('calculationRatio')" min-width="120">
                <template #default="{ row }">
                  <div class="text-[#9a02e9]">
                    <span class="font-bold">{{row.order_itme_commission_ratio}}</span>
                    <span class="text-[12px]">%</span>
                  </div>
                </template>
              </el-table-column>

							<el-table-column prop="num" :label="t('salesVolume')" width="180" />
							<el-table-column :label="t('allTotal')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.item_money}}</span>
									</div>
								</template>
							</el-table-column>
						</el-table>

						<template v-if="detailInfo.additional_items?.length">
							<div class="my-[35px]">
								<el-divider content-position="left">{{ t('addGoods') }}</el-divider>
							</div>
							<el-table :data="detailInfo.additional_items" border>
								<template #empty>
									<span>{{ !detailInfo.additional_items ? t('emptyData') : '' }}</span>
								</template>
								<el-table-column prop="item_name" :label="t('projectName')" width="180" align="left" />
								<el-table-column :label="t('sellingPrice')" min-width="120" align="center">
									<template #default="{ row }">
										<div class="">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.price}}</span>
										</div>
									</template>
								</el-table-column>
								<el-table-column :label="t('technicianCommission')" min-width="120">
									<template #default="{ row }">
										<div class="text-[#e6a23c]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.technician_commission}}</span>
										</div>
									</template>
								</el-table-column>
								<el-table-column :label="t('storeCommission')" min-width="120">
									<template #default="{ row }">
										<div class="text-[#273de3]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.store_commission}}</span>
										</div>
									</template>
								</el-table-column>

                <el-table-column :label="t('calculationRatio')" min-width="120">
                  <template #default="{ row }">
                    <div class="text-[#9a02e9]">
                      <span class="font-bold">{{row.order_itme_commission_ratio}}</span>
                      <span class="text-[12px]">%</span>
                    </div>
                  </template>
                </el-table-column>

								<el-table-column prop="num" :label="t('salesVolume')" width="180" />
								<el-table-column :label="t('allTotal')" min-width="120" align="center">
									<template #default="{ row }">
										<div class="text-[#ff0000]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.item_money}}</span>
										</div>
									</template>
								</el-table-column>
							</el-table>
						</template>
					</div>


					<div v-if="item.value == 'moneyInfo'" class="px-[20px]">
						<div class="mb-[35px]">
							<el-divider content-position="left">{{ t('serviceItem') }}</el-divider>
						</div>
						<el-table :data="detailInfo.service_items" border>
							<template #empty>
								<span>{{ !detailInfo.service_items ? t('emptyData') : '' }}</span>
							</template>
							<el-table-column prop="item_name" :label="t('projectName')" width="180" align="left" />
							<el-table-column :label="t('sellingPrice')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.price}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('technicianCommission')" min-width="120">
								<template #default="{ row }">
									<div class="text-[#e6a23c]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.technician_commission}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('storeCommission')" min-width="120">
								<template #default="{ row }">
									<div class="text-[#273de3]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.store_commission}}</span>
									</div>
								</template>
							</el-table-column>


              <el-table-column :label="t('calculationRatio')" min-width="120">
                <template #default="{ row }">
                  <div class="text-[#9a02e9]">
                    <span class="font-bold">{{row.order_itme_commission_ratio}}</span>
                    <span class="text-[12px]">%</span>
                  </div>
                </template>
              </el-table-column>



							<el-table-column prop="num" :label="t('salesVolume')" width="180" />
							<el-table-column :label="t('allTotal')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.item_money}}</span>
									</div>
								</template>
							</el-table-column>
						</el-table>

						<template v-if="detailInfo.additional_items?.length">
							<div class="my-[35px]">
								<el-divider content-position="left">{{ t('addGoods') }}</el-divider>
							</div>
							<el-table :data="detailInfo.additional_items" border>
								<template #empty>
									<span>{{ !detailInfo.additional_items ? t('emptyData') : '' }}</span>
								</template>
								<el-table-column prop="item_name" :label="t('projectName')" width="180" align="left" />
								<el-table-column :label="t('sellingPrice')" min-width="120" align="center">
									<template #default="{ row }">
										<div class="">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.price}}</span>
										</div>
									</template>
								</el-table-column>
								<el-table-column :label="t('technicianCommission')" min-width="120">
									<template #default="{ row }">
										<div class="text-[#e6a23c]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.technician_commission}}</span>
										</div>
									</template>
								</el-table-column>
								<el-table-column :label="t('storeCommission')" min-width="120">
									<template #default="{ row }">
										<div class="text-[#273de3]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.store_commission}}</span>
										</div>
									</template>
								</el-table-column>

                <el-table-column :label="t('calculationRatio')" min-width="120">
                  <template #default="{ row }">
                    <div class="text-[#9a02e9]">
                      <span class="font-bold">{{row.order_itme_commission_ratio}}</span>
                      <span class="text-[12px]">%</span>
                    </div>
                  </template>
                </el-table-column>

								<el-table-column prop="num" :label="t('salesVolume')" width="180" />
								<el-table-column :label="t('allTotal')" min-width="120" align="center">
									<template #default="{ row }">
										<div class="text-[#ff0000]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{row.item_money}}</span>
										</div>
									</template>
								</el-table-column>
							</el-table>
						</template>
						<div class="mt-[35px]">
							<el-divider content-position="left">{{ t('orderInfoMessage') }}<span
									class="text-[13px] text-[#666]" v-if="detailInfo.time_reminder">{{detailInfo.time_reminder?.text}}</span></el-divider>
						</div>

						<el-row>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('orderAmount') }}</span>
								<span class="text-[12px]">￥</span>
								<span class="">{{detailInfo.order_money}}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('remainingPaymentAmount') }}</span>
									<span class="text-[12px]">￥</span>
									<span class="">{{detailInfo.remaining_pay_money}}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('paymentAmount') }}</span>
									<span class="text-[12px]">￥</span>
									<span class="">{{detailInfo.pay_money}}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('technicianCommission') }}</span>
								<span class="text-[12px]">￥</span>
								<span class="">{{(Number(detailInfo.technician_commission) + Number(detailInfo.technician_additional_commission)).toFixed(2)}}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('storeCommission') }}</span>
								<span class="text-[12px]">￥</span>
							<span class="">{{(Number(detailInfo.store_commission) + Number(detailInfo.store_additional_commission)).toFixed(2)}}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('settlementStatus') }}</span>
								<span class="text-[14px]">{{ detailInfo.settlement_name }}</span>
							</el-col>
							<el-col :span="8" class="mt-[15px] break-all leading-[21px]" v-if="detailInfo.finish_time">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('settlementTime') }}</span>
								<span class="text-[14px]">{{ detailInfo.finish_time }}</span>
							</el-col>
						</el-row>

						<div class="my-[35px]">
							<el-divider content-position="left">{{ t('payHistory') }}</el-divider>
						</div>

						<el-table :data="detailInfo.pay_list" border>
							<template #empty>
								<span>{{ !detailInfo.pay_list ? t('emptyData') : '' }}</span>
							</template>
							<el-table-column :label="t('payOutNo')" min-width="120" align="left">
								<template #default="{ row }">
									<div class="">
										<span class="font-bold">{{row.out_trade_no}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('payType')" min-width="120">
								<template #default="{ row }">
									<div class="">
										<span class="">{{row.type_name}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('payMoney')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.money}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column prop="pay_time" :label="t('payTime')" min-width="200" align="center" />
						</el-table>
					</div>
					<div v-if="item.value == 'orderLog'" class="px-[20px]">
						<div class="mb-[35px]">
							<el-divider content-position="left">{{ t('stepsImage') }}</el-divider>
						</div>
						<el-timeline style="max-width: 600px">
							<el-timeline-item v-for="(item, index) in timelineItems" :key="index"
								:timestamp="item.timestamp" placement="top" :index="index + 1">
								<template #dot>
									<div
										class="bg-[#273de3] text-[#fff] w-[20px] h-[20px] flex items-center ml-[-5px] justify-center rounded-[50%]">
										{{ index + 1 }}
									</div>
								</template>
								<el-card>
									<h4>{{ item.title }}</h4>
									<p class="text-[#999] text-[14px]">操作人：{{ item.content }}</p>
								</el-card>
							</el-timeline-item>
						</el-timeline>
					</div>
					<div v-if="item.value == 'serviceList'" class="px-[20px]">
						<div class="mb-[55px]">
							<el-divider content-position="left">{{ t('servicesDoct') }}</el-divider>
						</div>
						<el-card class="max-w-[730px] mb-[30px]" v-if="detailInfo.take_photos">
							<template #header>
								<div class="flex items-center">
									<el-icon class="mr-[10px]" color="#273de3">
										<CircleCheckFilled />
									</el-icon><span>{{t('cardMessage')}}</span>
								</div>
							</template>
							<div class="flex">
								<div class="mr-[15px] text-[13px]">{{t('cardMessage')}}</div>
								<div class="flex-1 grap grap-6">
									<el-image v-for="(item,index) in detailInfo.take_photos.split(',')" :key="index" class="mr-[15px]"
										style="width: 100px; height: 100px" :zoom-rate="1.2" :max-scale="7"
										:src="img(item)"
										:min-scale="0.2" :preview-src-list="[img(detailInfo.take_photos.split(',')[index])]" show-progress
										:initial-index="4" fit="cover" />
								</div>
							</div>
							<template #footer>
								<div class="flex justify-between">
									<span class="text-[14px] ">
										{{t('cardName')}}：{{detailInfo.technician?.real_name}}
									</span>
									<span class="text-[13px] text-[#666]">
										{{t('cardTime')}}：{{detailInfo.take_photos_time}}
									</span>
								</div>
							</template>
						</el-card>

						<el-card class="max-w-[730px] mb-[30px]" v-if="detailInfo.service_finish_time">
							<template #header>
								<div class="flex items-center">
									<el-icon class="mr-[10px]" color="#273de3">
										<CircleCheckFilled />
									</el-icon><span>{{t('cardMessage')}}</span>
								</div>
							</template>
							<div class="flex">
								<div class="mr-[15px] text-[13px]">{{t('cardMessage')}}</div>
								<div class="flex-1 grap grap-6">
									<el-image v-for="(item,index) in detailInfo.check_photos.split(',')" :key="index" class="mr-[15px]"
										style="width: 100px; height: 100px" :src="img(item)" :zoom-rate="1.2" :max-scale="7"
										:min-scale="0.2" :preview-src-list="[img(detailInfo.check_photos.split(',')[index])]" show-progress
										:initial-index="4" fit="cover" />
								</div>
							</div>
							<template #footer>
								<div class="flex justify-between">
									<span class="text-[14px] ">
										{{t('cardName')}}：{{detailInfo.technician?.real_name}}
									</span>
									<span class="text-[13px] text-[#666]">
										{{t('cardTime')}}：{{detailInfo.finish_time}}
									</span>
								</div>
							</template>
						</el-card>
					</div>
					<div v-if="item.value == 'evaluate'" class="px-[20px]">
						<div class="mb-[35px]">
							<el-divider content-position="left">{{ t('evaluteList') }}</el-divider>
						</div>
						<el-descriptions direction="vertical" border style="margin-top: 20px">
							<el-descriptions-item :rowspan="2" :width="180" :label="t('photo')" align="center">
								<el-image style="width: 50px; height: 50px"
									src="https://cube.elemecdn.com/0/88/03b0d39583f48206768a7534e55bcpng.png" />
							</el-descriptions-item>
							<el-descriptions-item :label="t('nickname')">{{detailInfo.evaluate?.member_name}}</el-descriptions-item>
							<el-descriptions-item :label="t('evaluteTime')">{{detailInfo.evaluate?.create_time}}</el-descriptions-item>
							<el-descriptions-item :label="t('evaluteStart')">
								<el-rate v-model="evaluateStart" disabled show-score text-color="#ff9900"
									score-template="{value} 星" />
							</el-descriptions-item>
							<el-descriptions-item :label="t('evaluteContent')">
								<div class="mb-[5px] text-[15px]">
									{{detailInfo.evaluate?.content}}
								</div>
								<div class="flex-1 grap grap-6">
									<el-image v-for="(item,index) in detailInfo.evaluate?.images" :key="index" class="mr-[15px]" v-if="item"
										style="width: 60px; height: 60px" :src="img(item)" :zoom-rate="1.2"
										:max-scale="7" :min-scale="0.2" :preview-src-list="[img(detailInfo.evaluate?.images[index])]"
										show-progress :initial-index="4" fit="cover" />
								</div>
							</el-descriptions-item>
						</el-descriptions>
						<el-divider border-style="dashed" />
					</div>


					<div v-if="item.value == 'invoiceInfo'" class="px-[20px] invoice-box">
						<div class="mb-[35px]">
							<el-divider content-position="left">{{ t('invoiceInfo') }}</el-divider>
						</div>
						<div v-if="detailInfo.invoice_info">
							<el-card class="mb-[20px]">
								<template #header>
									<div class="flex justify-between items-center">
										<span>{{ detailInfo.invoice_info.invoice_number ? t('applicationNumber') + ': ' + detailInfo.invoice_info.invoice_number : t('invoiceInfo') }}({{detailInfo.invoice_info.header_type_name}})</span>
										<div class="flex">
											<el-tag :type="detailInfo.invoice_info.status == '1' ? 'success' : 'warning'" size="small">
												{{ detailInfo.invoice_info.status_name}}
											</el-tag>
											<el-button type="primary" class="ml-[10px]" link v-if="detailInfo.invoice_info?.invoice_voucher"
												@click="uploadPdf(img(detailInfo.invoice_info.invoice_voucher))">下载</el-button>
										</div>

									</div>
								</template>
								<el-descriptions :column="2" border>
									<!-- 个人类型发票 -->
									<template v-if="detailInfo.invoice_info.header_type === 'individual'">
										<el-descriptions-item :label="t('customerName')">
											{{ detailInfo.invoice_info.nickname || '-' }}
										</el-descriptions-item>
										<el-descriptions-item :label="t('taxpayerId')">
											{{ detailInfo.invoice_info.tax_number || '-' }}
										</el-descriptions-item>
									</template>
									<!-- 企业类型发票 -->
									<template v-else>
										<el-descriptions-item :label="t('headerName')">
											{{ detailInfo.invoice_info.header_name || '-' }}
										</el-descriptions-item>
										<el-descriptions-item :label="t('taxpayerId')">
											{{ detailInfo.invoice_info.tax_number || '-' }}
										</el-descriptions-item>
										<el-descriptions-item :label="t('telephone')">
											{{ detailInfo.invoice_info.telephone || '-' }}
										</el-descriptions-item>
										<el-descriptions-item :label="t('address')">
											{{ detailInfo.invoice_info.address || '-' }}
										</el-descriptions-item>
										<el-descriptions-item :label="t('bank_name')">
											{{ detailInfo.invoice_info.bank_name || '-' }}
										</el-descriptions-item>
										<el-descriptions-item :label="t('bank_card_number')">
											{{ detailInfo.invoice_info.bank_card_number || '-' }}
										</el-descriptions-item>
									</template>
									<!-- 公共字段 -->
									<el-descriptions-item :label="t('invoiceType')">
										{{ detailInfo.invoice_info.type_name || '-' }}
									</el-descriptions-item>
									<el-descriptions-item :label="t('orderMoney')">
										<div class="text-[#ff674c]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{detailInfo.invoice_info?.order_money || '0.00'}}</span>
										</div>
									</el-descriptions-item>
									<el-descriptions-item :label="t('invoiceAmount')">
										<div class="text-[#ff674c]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{detailInfo.invoice_info?.money || '0.00'}}</span>
										</div>
									</el-descriptions-item>
									<el-descriptions-item :label="t('email')">
										{{ detailInfo.invoice_info.email || '-' }}
									</el-descriptions-item>
									<el-descriptions-item :label="t('applicationTime')">
										{{ detailInfo.invoice_info.create_time || '-' }}
									</el-descriptions-item>
									<el-descriptions-item :label="t('invoiceTime')">
										{{ detailInfo.invoice_info.invoice_time || t('notInvoiced') }}
									</el-descriptions-item>
									<!-- 发票内容 -->
									<el-descriptions-item :label="t('invoiceContent')" :span="2">
										<div v-if="detailInfo.invoice_info.content_name && detailInfo.invoice_info.content_name.split(',').length" class="flex flex-wrap">
											<el-tag v-for="(item, idx) in detailInfo.invoice_info.content_name.split(',')" :key="idx" size="small" class="mr-[10px] mb-[5px]">
												{{ item }}
											</el-tag>
										</div>
										<span v-else>-</span>
									</el-descriptions-item>
								</el-descriptions>
							</el-card>
						</div>
						<div v-else class="text-center py-[20px] text-[#999]">
							<el-empty description="暂无发票信息" />
						</div>
					</div>

					<div v-if="item.value == 'follow' && detailInfo.orderfollow" class="px-[20px]">
						<div class="mb-[35px]">
							<el-divider content-position="left">{{ t('datanotes') }}</el-divider>
						</div>
						<div class="text-[15px] font-bold mb-[10px]">
							解决状态
						</div>
						<div class="flex items-center mb-[15px]">
							<el-icon :color="detailInfo.orderfollow?.fee_situation == 'consistent' ? '#4de120' : '#ff0000'">
								<CircleCheck />
							</el-icon>
							<span class="text-[13px] pl-[5px]" :class="detailInfo.orderfollow?.fee_situation == 'consistent' ? 'text-[#4de120]' : ' text-[#ff0000]'">{{detailInfo.orderfollow?.result_feedback_name}}</span>
						</div>
						<div class="text-[15px] font-bold mb-[5px]">
							收费情况
						</div>
						<div class="flex items-center mb-[15px]">
							<el-icon :color="detailInfo.orderfollow?.result_feedback == 'resolved' ? '#4de120' : '#ff0000'">
								<CircleCheck />
							</el-icon>
							<span class="text-[13px] pl-[5px]" :class="detailInfo.orderfollow?.result_feedback == 'resolved' ? 'text-[#4de120]' : ' text-[#ff0000]'">{{detailInfo.orderfollow?.fee_situation_name}}</span>
						</div>
						<div class="text-[15px] font-bold mb-[5px]">
							满意程度
						</div>
						<div class="flex items-center mb-[15px]">
							<el-rate v-model="satisfactionScore" disabled text-color="#ff9900" />
						</div>
						<div class="text-[15px] font-bold mb-[5px]">
							回访概述
						</div>
						<div class="bg-[#f6f6f6] mb-[15px] p-[10px] text-[14px]">
							{{detailInfo.orderfollow?.follow_summary}}
						</div>
						<div class="text-[15px] font-bold mb-[5px] ">
							客户建议
						</div>
						<div class="bg-[#f6f6f6] mb-[15px] p-[10px] text-[14px]">
							{{detailInfo.orderfollow?.suggestion_content}}
						</div>
						<div class="flex justify-between">
							<div class="flex items-center">
								<el-icon>
									<Avatar />
								</el-icon>
								<span class="text-[13px] ml-[5px]">回访人：{{detailInfo.orderfollow?.sysUser.username}}</span>
							</div>
							<div class="flex items-center">
								<el-icon>
									<Clock />
								</el-icon>
								<span class="text-[13px] ml-[5px]">回访时间：{{detailInfo.orderfollow?.follow_time}}</span>
							</div>
						</div>
					</div>
					<div v-if="item.value == 'refund'" class="px-[20px]">
						<div class="mb-[35px]">
							<el-divider content-position="left">{{ t('refunedDetail') }}</el-divider>
						</div>
						<div class="flex items-center font-bold mb-[15px]"><el-icon>
								<User />
							</el-icon><span class="text-[15px] ml-[5px] font-bold">{{t('applyInfo')}}</span></div>
						<el-row class="bg-[#f6f6f6] p-[15px]">
							<el-col :span="8" class=" break-all leading-[21px]">
								<span class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('name') }}</span>
								<span class="text-[14px]">{{ detailInfo.refund_info?.member?.nickname }}</span>
							</el-col>
							<el-col :span="8" class=" break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('mobile') }}</span>
								<span class="text-[14px]">{{ detailInfo.refund_info?.member?.mobile }}</span>
							</el-col>
							<el-col :span="8" class="break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('memberID') }}</span>
								<span class="text-[14px]">{{ detailInfo.refund_info?.member?.member_id }}</span>
							</el-col>
						</el-row>
						<div class="flex items-center font-bold mt-[20px]  mb-[15px]"><el-icon>
								<Avatar />
							</el-icon><span class="text-[15px] ml-[5px] font-bold">{{t('technicanInfo')}}</span></div>
						<el-row class="bg-[#f6f6f6] p-[15px]">
							<el-col :span="8" class=" break-all leading-[21px]">
								<span class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('name') }}</span>
								<span class="text-[14px]">{{ detailInfo.technician?.real_name}}</span>
							</el-col>
							<el-col :span="8" class="break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('mobile') }}</span>
								<span class="text-[14px]">{{ detailInfo.technician?.mobile }}</span>
							</el-col>
							<el-col :span="8" class=" break-all leading-[21px]">
								<span
									class="text-[14px] text-[#999999] flex-shrink-0 mr-[20px]">{{ t('workNumber') }}</span>
								<span class="text-[14px]">{{ detailInfo.technician?.id  }}</span>
							</el-col>
						</el-row>

						<el-row :gutter="24" class="mt-[20px]">
							<el-col :span="8">
								<div class="bg-[#f6f6f6] p-[15px]">
									<div class="flex items-center text-[14px]">
										<el-icon>
											<Clock />
										</el-icon>
										<span class="text-[14px] ml-[5px]">申请时间</span>
									</div>
									<div class="text-[14px]">
										{{detailInfo.refund_info?.create_time}}
									</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="bg-[#f6f6f6] p-[15px]">
									<div class="flex items-center text-[14px]">
										<el-icon>
											<Money />
										</el-icon>
										<span class="text-[14px] ml-[5px]">订单金额</span>
									</div>
									<div class="text-[14px]">
										<div class="text-[#273de3]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{detailInfo.refund_info?.orderMain?.order_money}}</span>
										</div>
									</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="bg-[#f6f6f6] p-[15px]">
									<div class="flex items-center text-[14px]">
										<el-icon>
											<DocumentCopy />
										</el-icon>
										<span class="text-[14px] ml-[5px]">最大退款金额</span>
									</div>
									<div class="text-[14px]">
										<div class="text-[#ff674c]">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{detailInfo.refund_info?.apply_money}}</span>
										</div>
									</div>
								</div>
							</el-col>
						</el-row>
						<div class="flex items-center font-bold mt-[20px]  mb-[15px]"><el-icon>
								<Notebook />
							</el-icon><span class="text-[15px] ml-[5px] font-bold">申请理由</span></div>
						<div class="bg-[#f6f6f6] mb-[15px] p-[10px] text-[14px]">
							{{detailInfo.refund_info?.reason_name}}
						</div>
						<div class="flex items-center font-bold mt-[20px]  mb-[15px]"><el-icon>
								<Tickets />
							</el-icon><span class="text-[15px] ml-[5px] font-bold">申请备注</span></div>
						<div class="bg-[#f6f6f6] mb-[15px] p-[10px] text-[14px]">
							{{detailInfo.refund_info?.remark}}
						</div>
						<div class="flex items-center font-bold mt-[20px]  mb-[15px]" v-if="detailInfo.refund_info?.voucher">
              <el-icon>
								<Picture />
							</el-icon><span class="text-[15px] ml-[5px] font-bold">申请图片</span>
            </div>
						<div class="flex-1 grap grap-6" v-if="detailInfo.refund_info?.voucher">
							<el-image v-for="(item,index) in detailInfo.refund_info?.voucher.split(',')" :key="index" class="mr-[15px]"
								style="width: 100px; height: 100px" :src="img(item)" :zoom-rate="1.2" :max-scale="7"
								:min-scale="0.2" :preview-src-list="[img(detailInfo.refund_info?.voucher.split(',')[index])]" show-progress :initial-index="4"
								fit="cover" />
						</div>

						<div class="flex items-center font-bold mt-[20px]  mb-[15px]"  v-if="detailInfo.refund_info?.money && detailInfo.refund_info.money !== '0.00'">
              <el-icon>
								<Coin />
							</el-icon><span class="text-[15px] ml-[5px] font-bold">退款金额</span>
            </div>
						<div class="flex-1 grap grap-6"  v-if="detailInfo.refund_info?.money && detailInfo.refund_info.money !== '0.00'">
							<div class="flex">
								<el-form :model="formData" label-width="85px" ref="formRef" :rules="formRules"
									class="page-form">
									<el-form-item :label="t('退款金额')">
										<div>
											<el-input v-model.trim="formData.money" maxlength="6" clearable
												class="input-width !w-[150px]" max="100" @keyup="filterDigit($event)">
												<template #append>元</template>
											</el-input>
										</div>
									</el-form-item>
								</el-form>
							</div>
						</div>

					</div>
				</el-tab-pane>
			</el-tabs>
		</el-card>

		<div class="fixed-footer-wrap " v-if="detailInfo?.refund_status == 'wait_refund' && activeStatus == 'refund'">
			<div class="fixed-footer">
				<el-button type="primary" @click="handlePass(detailInfo.refund_info)">{{ t('success') }}</el-button>
				<el-button @click="handleReject(detailInfo.refund_info)">{{ t('fail') }}</el-button>
			</div>
		</div>


		<selectOrderlabel :visible="tagDialogVisible" :tags="tagList" @close="tagDialogVisible = false"
			@confirm="handleTagConfirm" />
		<selectTechnician :visible="technicianDialogVisible" :tags="tagList" @close="tagDialogVisible = false"
			@confirm="handleTagConfirm" />
		<component v-for="dialog in dialogs" :key="dialog.key" :is="dialog.component" :visible="dialog.visible"
			@close="() => setDialogVisible(dialog.key, false)"
			@confirm="(data) => handlePopupOrderConfirm(dialog.key, data)"
			:other-prop="dialog.props"
			/>

		<!-- 回访对话框 -->
		<returnvisit
			v-for="dialog in dialogs.filter(item => item.key === 'action_follow')"
			:key="dialog.key"
			v-model:visible="dialog.visible"
			:initial-data="dialog.props.initialData"
			@confirm="handleDialogConfirm"
			@cancel="() => setDialogVisible('action_follow', false)"
		></returnvisit>


		<el-dialog title="确认退款" v-model="passDialogVisible" width="30%">
			<el-form :model="refundForm" :rules="refundRules" ref="refundFormRef">
				<el-form-item label="退款金额" prop="money">
					<el-input v-model="refundForm.money" placeholder="请输入退款金额" class="input-width !w-[214px]"></el-input>
				</el-form-item>
			</el-form>
			<template #footer>
				<el-button @click="passDialogVisible = false">取消</el-button>
				<el-button type="primary" @click="confirmPass">确认</el-button>
			</template>
		</el-dialog>

		<!-- 拒绝退款的弹窗 -->
		<el-dialog title="拒绝退款" v-model="rejectDialogVisible" width="30%">
			<el-form :model="rejectForm" :rules="rejectRules" ref="rejectFormRef">
				<el-form-item label="拒绝理由" prop="refuse_reason">
					<el-input type="textarea" v-model="rejectForm.refuse_reason" class="input-width !w-[214px]" placeholder="请输入拒绝理由"></el-input>
				</el-form-item>
			</el-form>
			<template #footer>
				<el-button @click="rejectDialogVisible = false">取消</el-button>
				<el-button type="primary" @click="confirmReject">确认</el-button>
			</template>
		</el-dialog>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed, watch } from 'vue'
	import { t } from '@/lang'
	import { getOrderDetail, getOrderList, setOrderLabel, deleteOrder, cuiOrder, setOrderDispatch, transferOrder, setorderfollow } from '@/addon/home_service/api/order'
	import { img, setTablePageStorage, getTablePageStorage, filterDigit, filterNumber ,timeStampTurnTime} from '@/utils/common'
	import OrderMethods from '@/addon/home_service/views/order/js/orderMethods';
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import selectOrderlabel from '@/addon/home_service/views/order/components/select-orderlabel.vue';
	import selectTechnician from '@/addon/home_service/views/order/components/select-technician.vue';
	import { getRefundList, getRefundType, setRefundadoptStatus, setRefundSuccessStatus } from '@/addon/home_service/api/refund'
	import { AnyObject } from '@/types/global'
	import { useClipboard } from '@vueuse/core'
	import returnvisit from '@/addon/home_service/views/order/components/returnvisit.vue';
	const { copy, isSupported, copied } = useClipboard()
	const activeStatus = ref('orderInfo')
	const tabPosition = ref<TabsInstance['tabPosition']>('left')
	const route = useRoute()
	const passDialogVisible = ref(false)
	const rejectDialogVisible = ref(false)
	const router = useRouter()
	const id : number = parseInt(route.query.order_id as string) || 0
	const pageStatus : string = route.query.status || 'orderInfo'
	activeStatus.value = pageStatus
	const pageName = route.meta.title
	const detailInfo = ref({})
	const formData = ref({
		money: ''
	})

	// 退款表单数据
	const refundForm = reactive({
		money: ''
	})
	// 拒绝表单数据
	const rejectForm = reactive({
		refuse_reason: ''
	})
	// 表单验证规则
	const refundRules = {
		money: [
			{ required: true, message: '请输入退款金额', trigger: 'blur' }
		]
	}

	const rejectRules = {
		refuse_reason: [
			{ required: true, message: '请输入拒绝理由', trigger: 'blur' }
		]
	}
	const uploadPdf = (url:any) =>{
		window.open(url)
	}
	// 表单引用
	const refundFormRef = ref(null)
	const rejectFormRef = ref(null)
	// 标签相关
	const tagDialogVisible = ref(false);
	const currentTags = ref([]); // 当前标签列表
	let tagSelectCallback : (id : number | string) => void; // 存储标签选择后的回调
	const currentRow = ref(null)
	// 回访对话框相关变量已移至dialogs数组配置中
	// 处理通过操作
	const handlePass = (row) => {
		currentRow.value = row
		refundForm.money = row.apply_money // 默认填充申请金额
		passDialogVisible.value = true
	}

	// 确认通过
	const confirmPass = () => {
		refundFormRef.value.validate((valid) => {
			if (valid) {
				// 调用通过接口
				setRefundSuccessStatus({
					...currentRow.value,
					money: refundForm.money
				}).then(() => {
					passDialogVisible.value = false
					refundFormRef.value.resetFields()
					getOrderDetailFn()
				})
			}
		})
	}

	// 处理拒绝操作
	const handleReject = (row) => {
		currentRow.value = row
		rejectDialogVisible.value = true
	}

	// 确认拒绝
	const confirmReject = () => {
		rejectFormRef.value.validate((valid) => {
			if (valid) {
				// 调用拒绝接口
				setRefundadoptStatus({
					...currentRow.value,
					refuse_reason: rejectForm.refuse_reason
				}).then(() => {
					rejectDialogVisible.value = false
					rejectFormRef.value.resetFields()
					getOrderDetailFn()
				})
			}
		})
	}
	// 确认回访
	const handleDialogConfirm = (formData) => {
		formData.order_id = detailInfo.value.order_id
	  setorderfollow(formData).then(() => {
	    setDialogVisible('action_follow', false);
	    getOrderDetailFn();
	  });
	};

	// 打开回访对话框
	const openFollowDialog = () => {
	  // 找到对应的弹框配置
	  const targetDialog = dialogs.find(dialog => dialog.key === 'action_follow');
	  if (targetDialog) {
	    targetDialog.props.initialData.order_id = detailInfo.value.order_id;
	    setDialogVisible('action_follow', true);
	  }
	};

	// 师傅相关
	const technicianDialogVisible = ref(false)

	// 定义弹框配置：key（唯一标识）、组件、显示状态、额外参数
	const dialogs = reactive([
		{
			key: 'action_dispatch', // 唯一标识，与工具类中的key对应
			component: selectTechnician,
			visible: false,
			props: { title: '待派单处理' } // 组件需要的额外参数
		},
		{
			key: 'action_transfer', // 唯一标识，重新派单
			component: selectTechnician,
			visible: false,
			props: {
				title: '重新派单处理',
				initialTechnicianId: '' // 初始选中的师傅ID
			} // 组件需要的额外参数
		},
		{
			key: 'action_follow', // 唯一标识，回访订单
			component: returnvisit,
			visible: false,
			props: {
				title: '订单回访',
				initialData: {}
			} // 组件需要的额外参数
		}
	])
	const formRules = computed(() => {
		return {
			money: [{ required: true, message: t('moneyPlaceholder'), trigger: 'blur' }],
		}
	})
	const timelineItems = ref([])
	const evaluteList = ref([])
	/**
	 * 复制
	 */
	const copyEvent = (text : string) => {
		if (!isSupported.value) {
			ElMessage({
				message: t('notSupportCopy'),
				type: 'warning'
			})
			return
		}
		copy(text)
	}

	// 详情跳转
	const detailEvent = (info : any) => {
		router.push(`/home_service/order/detail?order_id=${info.order_id}`)
	}

	// 单个订单设置标签
	const orderlabel = (data : any) => {
		currentTags.value = data;
		currentTags.value.isBatch = false;
		tagDialogVisible.value = true;
	};

	// 处理用户选择标签后的确认
	const handleTagSelectConfirm = (selectedTagId : number | string) => {
		if (tagSelectCallback && typeof tagSelectCallback === 'function') {
			tagSelectCallback(selectedTagId); // 调用orderMethods中定义的回调
		}
	};

	// 隐藏标签对话框
	const hideTagDialog = () => {
		tagDialogVisible.value = false;
	};

	// 处理标签选择确认
	const handleTagConfirm = (data : any) => {
		// 判断是批量设置还是单个设置
		let orderIds = '';
		if (currentTags.value.isBatch) {
			// 批量设置：拼接选中的订单ID
			orderIds = currentTags.value.map(item => item.order_id).join(',');
		} else {
			// 单个设置：使用单个订单ID
			orderIds = currentTags.value.order_id;
		}

		let params = {
			label_id: data,
			order_ids: orderIds
		}
		setOrderLabel(params).then((res) => {
			// 关闭标签选择对话框
			tagDialogVisible.value = false;
			// 刷新订单详情
			getOrderDetailFn();
		})
	}

	// 处理订单弹窗操作确认
	const handlePopupOrderConfirm = (data:any,key:any) =>{
		console.log(data,key)
		if(data == 'action_dispatch' || data == 'action_transfer'){
			let params = {
				order_id:currentTags.value.order_id,
				technician_id:key
			}
			// 根据dialogKey决定调用哪个接口
			if(data == 'action_dispatch'){
				console.log(params,'哈哈哈')
				// 派单接口
				setOrderDispatch(params).then((res)=>{
					getOrderDetailFn()
				})
			}else if(data == 'action_transfer'){
				// 转单接口
				transferOrder(params).then((res)=>{
					getOrderDetailFn()
				})
			}
		}
	}

	// 处理订单按钮点击
	const handlePopupOrderAction = (data : any, key : string) => {
 		currentTags.value = data
		var order = data
		console.log(key)
		if(key == 'action_delete'){
			// 检查订单状态是否为关闭状态
			if (order.order_status_info?.status !== 'close') {
				ElMessage({
					message: t('onlyCloseOrderCanDelete'),
					type: 'warning',
				})
				return;
			}
			ElMessageBox.confirm(
				t('confirmDeleteOrder'),
				t('confirm'),
				{
					confirmButtonText: t('confirm'),
					cancelButtonText: t('cancel'),
					type: 'warning',
				}
			).then(() => {
				// 单个订单删除
							deleteOrder({ order_ids: order.order_id }).then(() => {
								back()
								// 刷新订单详情
								getOrderDetailFn();
							});
			}).catch(() => {
			// 用户取消删除
			});
		}else{
			OrderMethods.orderClickFunction(
				data,
				key,
				() => getOrderDetailFn(), // 刷新详情的回调
				// 新增：弹框触发回调（接收工具类传递的弹框key）
				(dialogKey : string) => {
					// 如果是重新派单，设置初始师傅ID
					if(dialogKey == 'action_dispatch'){
						const targetDialog = dialogs.find(d => d.key === dialogKey);
						if(data.store_id>0){
							targetDialog.props.store_id = data.store_id;
						}
						targetDialog.props.category_id = data.category_id;
						targetDialog.props.order_id = data.order_id;
						targetDialog.props.initialTechnicianId = data.technician_id;
					}else if (dialogKey === 'action_transfer' && data.technician_id) {
						const targetDialog = dialogs.find(d => d.key === dialogKey);
						if (targetDialog) {
							// console.log(data,'data')
							// targetDialog.props.initialTechnicianId = data.tecshnician_id;
								if(data.store_id>0){
							targetDialog.props.store_id = data.store_id;
						}
						targetDialog.props.category_id = data.category_id;
						targetDialog.props.order_id = data.order_id;
						targetDialog.props.initialTechnicianId = data.technician_id;
						}

					}
					setDialogVisible(dialogKey, true) // 显示对应的弹框
				}
			)
		}
	}

	// 根据 dialogKey 设置弹框显示/隐藏
	const setDialogVisible = (dialogKey: string, visible: boolean) => {
		// 找到对应 key 的弹框配置
		const targetDialog = dialogs.find(dialog => dialog.key === dialogKey);
		if (targetDialog) {
			targetDialog.visible = visible; // 修改弹框的 visible 状态
		} else {
			console.warn(`未找到 key 为 ${dialogKey} 的弹框配置`);
		}
	}

	watch(copied, () => {
		if (copied.value) {
			ElMessage({
				message: t('copySuccess'),
				type: 'success'
			})
		}
	})
	const evaluateStart = ref(0)
	const satisfactionScore = ref(0)
	const loading = ref(true)
	const steps = ref([])
	// 发票数据（假数据）
	const invoiceData = ref([
		{
			status: 'invoiced',
			invoice_number: 'FP202311220001',
		header_type: 'individual',
		nickname: '张三',
		tax_number: '123456789012345678',
		type: 'electron_regular_invoice',
		type_name: '电子普通发票',
		order_money: '1280.00',
		money: '1280.00',
		email: 'zhangsan@example.com',
		create_time: '2023-11-22 14:30:00',
		invoice_time: '2023-11-23 10:15:00',
		content_name: '家电维修,上门服务费'
		},
		{
			status: 'toInvoice',
			header_type: 'company',
			header_name: '科技有限公司',
		tax_number: '91110105MA00AA0A0A',
		telephone: '010-12345678',
		address: '北京市朝阳区某某大厦A座1001室',
		bank_name: '中国工商银行北京分行',
		bank_card_number: '6222020200012345678',
		type: 'electron_special_invoice',
		type_name: '电子专用发票',
		order_money: '2580.00',
		money: '2580.00',
		email: 'finance@company.com',
		create_time: '2023-11-25 09:45:00',
		invoice_time: '',
		content_name: '设备安装,配件费,技术服务费'
		}
	])


  const getOrderDetailFn = async () => {
    steps.value = [];
    timelineItems.value = []; // 清空时间线数据，避免累积
    loading.value = true;
    try {
      const data = await (await getOrderDetail(id)).data;

      // 处理流程步骤
      if (data.core_process) {
        Object.keys(data.core_process).forEach((item) => {
          const processItem = data.core_process[item];
          steps.value.push({
            title: processItem.text,
            icon: processItem.icon,
            timeCost: processItem.time_diff || '',
            status: processItem.time_diff ? '正常完成' : '',
            time_value: processItem.time_value
          });
        });

        // 计算当前激活的步骤
        let activeStepCount = 1;
        steps.value.forEach(item => {
          if (item.timeCost) activeStepCount++;
        });
        activeStep.value = activeStepCount;

        // 处理时间线数据
        data.order_log.forEach(item => {
          timelineItems.value.push({
            timestamp: item.action_time,
            type: 'card',
            title: item.action || '',
            content: item.nick_name
          });
        });
      }

      // 更新详情数据
      detailInfo.value = data;
      evaluateStart.value = detailInfo.value.evaluate?.scores || 0;
      satisfactionScore.value = detailInfo.value.orderfollow?.satisfaction_score || 0;

      // 处理退款金额
      if (detailInfo.value.refund_info) {
        const refundInfo = detailInfo.value.refund_info;
        formData.value.money = refundInfo.money === '0.00'
            ? refundInfo.apply_money
            : refundInfo.money;
      }

      // 【关键优化】重置标签列表，避免重复添加
      detailStatusList.value = [
        { label: '订单信息', value: 'orderInfo' },
        { label: '收费清单', value: 'moneyInfo' },
        { label: '订单流程', value: 'orderLog' }
      ];

      // 按需添加其他标签（保持原有判断逻辑）
      if (detailInfo.value.take_photos) {
        detailStatusList.value.push({
          label: '服务档案',
          value: 'serviceList'
        });
      }
      if (detailInfo.value.evaluate) {
        detailStatusList.value.push({
          label: '订单评价',
          value: 'evaluate'
        });
      }
      if (detailInfo.value.orderfollow) {
        detailStatusList.value.push({
          label: '回访日志',
          value: 'follow'
        });
      }
      if (detailInfo.value.refund_info?.create_time) {
        detailStatusList.value.push({
          label: '订单售后',
          value: 'refund'
        });
      }
      if (detailInfo.value.invoice_info?.id) {
        detailStatusList.value.push({
          label: '发票信息',
          value: 'invoiceInfo'
        });
      }

      console.log('当前标签列表:', detailStatusList.value);
    } catch (error) {
      console.error('获取订单详情失败:', error);
    } finally {
      loading.value = false;
    }
  };

	getOrderDetailFn()
	const activeStep = ref(4)

	const detailStatusList = ref([
		{
			label: '订单信息',
			value: 'orderInfo'
		},
		{
			label: '收费清单',
			value: 'moneyInfo'
		},
		{
			label: '订单流程',
			value: 'orderLog'
		}
	])
	// 处理订单按钮点击
	const handleOrderAction = (order : any) => {
		OrderMethods.orderClickFunction(
			order,
			order.order_status,
			() => getOrderDetailFn(), // 刷新详情的回调
			(dialogKey) => {
				if (dialogKey === 'action_follow') {
					openFollowDialog();
				}
			}
		);
	};
	const back = () => {
		history.back()
	}
</script>
<style lang="scss" scoped>
	.custom-steps {
		position: relative;
		/* 为中间内容预留空间 */
	}

	/* 隐藏Element UI默认的描述文字样式 */
	::v-deep .el-step__description {
		position: static;
		height: auto;
		margin: 0;
	}

	/* 中间内容样式 */
	.step-middle-content {
		position: absolute;
		top: -15px;
		/* 调整垂直位置 */
		left: 65%;
		transform: translateX(-50%);
		/* 水平居中 */
		text-align: center;
		width: 152px;
		/* 固定宽度确保居中效果 */
	}

	.time-cost {
		font-size: 12px;
		color: #666;
		margin-bottom: 15px
	}

	.status {
		font-size: 12px;
		color: #67c23a;
		/* 成功状态的绿色 */
	}

	/* 调整步骤连接线样式 */
	::v-deep .el-steps--horizontal {
		.el-step:not(:last-child)::after {
			top: 14px;
			/* 调整连接线垂直位置 */
			height: 2px;
		}
	}

	/* 调整步骤标题位置 */
	::v-deep .el-step__title {
		margin-top: 10px;
		padding-bottom: 20px;
		/* 为中间内容留出空间 */
	}

	/* 放大标签页标题字体 */
	::v-deep .el-tabs__item {
		/* 可选：如果需要增加标题间距 */
		padding: 25px 80px;
		font-size: 15px;
		margin-bottom:35px !important;
		border:2px solid #f5f5f5 !important;
		margin:0 0 20px 20px !important;
		background:#fcfcfc !important;
	}

	/* 选中状态的标题也保持一致大小（可选） */
	::v-deep .el-tabs__item.is-active {
		/* 可选：可以加粗选中的标题 */
		font-weight: bold;
	}

	::v-deep .el-divider__text {
		font-size: 18px;
		font-weight: bold !important;
	}

	::v-deep .el-step__icon {
		background-color: #fcfcfc;
		/* 透明背景 */
	}
</style>
