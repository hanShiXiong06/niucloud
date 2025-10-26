<template>
	<u-popup :show.sync="show" mode="bottom" @close="colse()">
		<view class="t-pop" @tap.stop>
			<view class="pop-main">
				<view class="top">
					<view class="top-l">
						<view @click="changeSwp('1')" class="date-c" :class="{'active-tab': sindex=='1'}">
							<text>{{checkyear}}-{{checkmonth<10?'0'+checkmonth:checkmonth}}-{{checkdate<10?'0'+checkdate:checkdate}}</text>
						</view>
						<view @click="changeSwp('2')" class="time-c" :class="{'active-tab': sindex=='2'}">
							<text>{{checkhour<10?'0'+checkhour:checkhour}}:{{checkminute<10?'0'+checkminute:checkminute}}</text>
						</view>
					</view>
					<view class="top-r" @click="onOK()">
						<text>确定</text>
					</view>
				</view>
				<swiper class="swiper" circular :current-item-id="sindex" @change="handleSwiperChange">
					<!-- 日期选择部分（第一步已完成，保持不变） -->
					<swiper-item item-id="1">
						<view class="mid">
							<view class="calendar">
								<view class="ca-top" v-for="item in textList" :key="item">
									{{item}}
								</view>
							</view>
							<scroll-view scroll-y="true" style="height: 460rpx;" @scrolltolower="tolower">
								<view class="calendar" v-for="(item,index) in mList" :key="index">
									<view class="cabg">
										{{item.month}}
									</view>
									<template v-if="item.firstDay > 0">
										<view class="ca-top" v-for="i in item.firstDay" :key="'empty-'+i"></view>
									</template>
									<view class="ca-top" v-for="day in item.days" :key="day">
										<view 
											class="cell" 
											:class="{
												'cell-past': isPastDate(item.year, item.month, day),
												'cell-active': !isPastDate(item.year, item.month, day) && checkyear==item.year&&checkmonth==item.month&&checkdate==day
											}"
											@click="!isPastDate(item.year, item.month, day) && check(item, day)"
										>
											{{day}}
										</view>
									</view>
								</view>
							</scroll-view>
						</view>
					</swiper-item>
					
					<!-- 第二步核心：时间选择部分修改 -->
					<swiper-item item-id="2">
						<view class="time-picker-container">
							<picker-view :indicator-style="indicatorStyle" :value="value" @change="bindChange"
								class="picker-view">
								<!-- 小时选择：今天的过去小时置灰 -->
								<picker-view-column>
									<view 
										class="item" 
										v-for="(item,index) in hours" 
										:key="index"
										:class="{ 'time-past': isToday && item < currentHour }"
									>
										{{formatNumber(item)}}时
									</view>
								</picker-view-column>
								<!-- 分钟选择：今天当前小时的过去分钟置灰 -->
								<picker-view-column>
									<view 
										class="item" 
										v-for="(item,index) in minutes" 
										:key="index"
										:class="{ 'time-past': isToday && checkhour === currentHour && item < currentMinute + delayMin }"
									>
										{{formatNumber(item)}}分
									</view>
								</picker-view-column>
							</picker-view>
						</view>
					</swiper-item>
				</swiper>
			</view>
		</view>
	</u-popup>
</template>

<script>
	export default {
		name: "anyi-datetime",
		props: {
			show: {
				type: Boolean,
				default: false
			},
			delayMin: {
				type: Number,
				default: 0 // 延迟分钟数（如需要默认延后30分钟，可设为30）
			},
			type: {
				type: String,
				default: 'start'
			},
			startTime: {
				type: [Number, String],
				default: ''
			},
			endTime: {
				type: [Number, String],
				default: ''
			},
			defaultTime: {
				type: String,
				default: ''
			}
		},
		data() {
			return {
				textList: ['日', '一', '二', '三', '四', '五', '六'],
				mList: [],
				checkyear: 0,
				checkmonth: 0,
				checkdate: 0,
				checkhour: 0,
				checkminute: 0,
				indicatorStyle: 'height: 100rpx; line-height: 100rpx;',
				value: [0, 0],
				sindex: '1',
				nowYear: 0,
				nowMonth: 0,
				nowDate: 0,
				currentHour: 0, // 当前小时（用于判断过去时间）
				currentMinute: 0, // 当前分钟（用于判断过去时间）
				hours: Array.from({length: 24}, (_, i) => i), // 小时数组
				minutes: Array.from({length: 60}, (_, i) => i) // 分钟数组
			};
		},
		computed: {
			// 判断是否选中今天（核心计算属性）
			isToday() {
				return this.checkyear === this.nowYear && 
					   this.checkmonth === this.nowMonth && 
					   this.checkdate === this.nowDate;
			}
		},
		watch: {
			show(newVal) {
				if (newVal) {
					// 初始化当前时间（精确到时分）
					const now = new Date();
					this.nowYear = now.getFullYear();
					this.nowMonth = now.getMonth() + 1;
					this.nowDate = now.getDate();
					this.currentHour = now.getHours();
					this.currentMinute = now.getMinutes();

					if (this.defaultTime) {
						const defaultDate = new Date(this.defaultTime.replace(/-/g, '/'))
						this.checkyear = defaultDate.getFullYear()
						this.checkmonth = defaultDate.getMonth() + 1
						this.checkdate = defaultDate.getDate()
						this.checkhour = defaultDate.getHours()
						this.checkminute = defaultDate.getMinutes()
					} else {
						// 默认时间设为当前时间+延迟分钟（确保是未来时间）
						const defaultTime = new Date(now.getTime() + this.delayMin * 60 * 1000);
						this.checkyear = defaultTime.getFullYear();
						this.checkmonth = defaultTime.getMonth() + 1;
						this.checkdate = defaultTime.getDate();
						this.checkhour = defaultTime.getHours();
						this.checkminute = defaultTime.getMinutes();
					}

					// 同步时间选择器索引
					this.value = [this.checkhour, this.checkminute];
					
					this.mList = [];
					this.init();
				}
			},
			// 当选中日期变化时，更新时间选择范围
			checkdate() {
				this.updateTimeRange();
			},
			// 当选中小时变化时，更新分钟选择范围
			checkhour() {
				this.updateMinuteRange();
			}
		},
		created() {
			this.init();
		},
		methods: {
			// 第一步已实现：判断是否为过去的日期
			isPastDate(year, month, day) {
				const selectedDate = new Date(year, month - 1, day);
				selectedDate.setHours(0, 0, 0, 0);
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				return selectedDate.getTime() < today.getTime();
			},

			// 新增：更新小时选择范围（今天只显示当前小时及以后）
			updateTimeRange() {
				if (this.isToday) {
					// 今天：只保留当前小时及以后的小时
					this.hours = Array.from({length: 24 - this.currentHour}, (_, i) => this.currentHour + i);
					// 确保选中的小时在有效范围内
					if (!this.hours.includes(this.checkhour)) {
						this.checkhour = this.hours[0] || 0;
					}
				} else {
					// 非今天：显示所有小时
					this.hours = Array.from({length: 24}, (_, i) => i);
				}
				// 同步更新分钟范围
				this.updateMinuteRange();
			},

			// 新增：更新分钟选择范围（今天当前小时只显示当前分钟+延迟后及以后）
			updateMinuteRange() {
				if (this.isToday && this.checkhour === this.currentHour) {
					// 今天且当前小时：只保留当前分钟+延迟后及以后的分钟
					const minValidMinute = this.currentMinute + this.delayMin;
					// 处理分钟溢出（如59+2=61 → 自动调整为0）
					if (minValidMinute >= 60) {
						this.minutes = []; // 清空当前分钟数组（会触发小时自动跳转）
					} else {
						this.minutes = Array.from({length: 60 - minValidMinute}, (_, i) => minValidMinute + i);
					}
					// 确保选中的分钟在有效范围内
					if (!this.minutes.includes(this.checkminute)) {
						this.checkminute = this.minutes[0] || 0;
					}
				} else {
					// 非今天或非当前小时：显示所有分钟
					this.minutes = Array.from({length: 60}, (_, i) => i);
				}
				// 同步时间选择器索引
				this.value = [
					this.hours.indexOf(this.checkhour),
					this.minutes.indexOf(this.checkminute)
				];
			},

			tolower() {
				this.init();
			},
			init() {
				let startyear = this.mList.length ? this.mList[this.mList.length - 1].year : new Date().getFullYear();
				let startmonth = this.mList.length ? this.mList[this.mList.length - 1].month + 1 : new Date().getMonth() + 1;

				for (let i = 0; i < 10; i++) {
					let year = startmonth + i > 12 ? startyear + 1 : startyear;
					let month = startmonth + i > 12 ? startmonth + i - 12 : startmonth + i;

					let daynums = new Date(year, month, 0).getDate();
					let firstDay = new Date(year, month - 1, 1).getDay();

					this.mList.push({
						year: year,
						month: month,
						daynums: daynums,
						firstDay: firstDay,
						days: Array.from({length: daynums}, (_, i) => i + 1)
					});
				}
			},
			check(item, day) {
				if (day > item.daynums) return;
				if (this.isPastDate(item.year, item.month, day)) return;

				this.checkyear = item.year;
				this.checkmonth = item.month;
				this.checkdate = day;
				this.sindex = '2';
				// 切换到时间选择时更新时间范围
				this.updateTimeRange();
			},
			colse() {
				console.log('colse')
				this.$emit('update:show', false);
			},
			bindChange(e) {
				const val = e.detail.value;
				this.checkhour = this.hours[val[0]];
				this.checkminute = this.minutes[val[1]];
				this.value = val;
			},
			changeSwp(i) {
				this.sindex = i;
			},
			onOK() {
				const formattedTime = `${this.checkyear}/${this.formatNumber(this.checkmonth)}/${this.formatNumber(this.checkdate)} ${this.formatNumber(this.checkhour)}:${this.formatNumber(this.checkminute)}`;
				const currentTimestamp = Date.parse(formattedTime);
				
				let obj = {
					timeStr: formattedTime,
					value: currentTimestamp,
					type: this.type
				};

				if (this.type === 'start' && this.endTime) {
					const endTimestamp = typeof this.endTime === 'string' ? Date.parse(this.endTime) : this.endTime;
					if (currentTimestamp > endTimestamp) {
						obj = {
							timeStr: formattedTime,
							value: currentTimestamp,
							type: 'end',
							needSwap: true
						};
					}
				} else if (this.type === 'end' && this.startTime) {
					const startTimestamp = typeof this.startTime === 'string' ? Date.parse(this.startTime) : this.startTime;
					if (currentTimestamp < startTimestamp) {
						obj = {
							timeStr: formattedTime,
							value: currentTimestamp,
							type: 'start',
							needSwap: true
						};
					}
				}

				this.$emit('confirm', obj);
				this.$emit('update:show', false);
			},
			formatNumber(n) {
				return n < 10 ? '0' + n : n;
			},
			handleSwiperChange(e) {
				this.sindex = String(e.detail.currentItemId);
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 第一步：过去日期样式 */
	.cell-past {
		color: #ccc;
		pointer-events: none;
	}

	/* 第二步：过去时间（时分）样式 */
	.time-past {
		color: #ccc;
		pointer-events: none; /* 禁止点击 */
	}

	.t-pop {
		width: 100%;
		display: flex;
		justify-content: center;
		align-items: center;

		.pop-main {
			display: flex;
			flex-direction: column;
			justify-content: space-between;
			align-items: center;
			background-color: #fff;
			border-radius: 24px;
			height: 600rpx;
			width: 100%;
		}
	}

	.swiper {
		height: 600rpx;
		width: 100vw;
	}

	.top {
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		align-items: center;
		width: 100%;
		margin: 20rpx 0;

		.top-l {
			display: flex;
			flex-direction: row;
			margin-left: 30rpx;
		}

		.top-r {
			margin-right: 30rpx;
			color: #0DBDA8;
		}

		.date-c {
			width: 210rpx;
		}

		.time-c {
			// margin-left: 20rpx;
		}
	}

	.calendar {
		display: flex;
		flex-wrap: wrap;
		flex-direction: row;
		align-items: center;
		width: 100vw;
		position: relative;
	}

	.ca-top {
		width: 14.2vw;
		display: flex;
		justify-content: center;
		align-items: center;
		height: 66rpx;
		z-index: 10;
	}

	.ca-top:empty {
		height: 66rpx;
	}

	.cell {
		width: 60rpx;
		height: 60rpx;
		display: flex;
		justify-content: center;
		align-items: center;
		border-radius: 30rpx;
	}

	.cell-active {
		background-color: #0DBDA8;
		color: #fff;
	}

	.cabg {
		display: flex;
		justify-content: center;
		width: 100vw;
		font-size: 180rpx;
		color: rgba(13, 189, 168, 0.1);
		position: absolute;
		z-index: 9;
	}

	.active-tab {
		color: #0DBDA8 !important;
	}

	.time-picker-container {
		height: 500rpx;
		display: flex;
		justify-content: center;
		align-items: center;
	}

	.picker-view {
		width: 100%;
		height: 500rpx;
		text-align: center;
	}

	.item {
		line-height: 100rpx;
		display: flex;
		justify-content: center;
		align-items: center;
		font-size: 32rpx;
	}
</style>