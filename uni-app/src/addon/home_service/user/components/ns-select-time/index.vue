<template>
	<u-popup :show="show"  mode="bottom" :round="10" closeable @close="show = false">
		<view class="box h-[728rpx]">
			<view class="title px-[30rpx] box-border text-center text-[28rpx] font-bold h-[90rpx] leading-[90rpx] border-0 border-solid border-[#f7f4f4] border-b-[2rpx]">
				<text>请选择上门时间</text>
			</view>
			<view class="body flex h-[calc(100%-90rpx)] box-border" v-if="dateArr.length">
				<!-- 左侧日期选择 -->
				<scroll-view :scroll-y="true" class="left bg-[#f8f8f8] shrink-0  w-[280rpx]" scroll-with-animation  :scroll-into-view="'id' + (dateActive ? dateActive - 1 : 0)">
					<template v-for="(item,index) in dateArr" :key="index">
						<view class="date-box flex px-[30rpx] justify-center py-[16rpx] box-border text-[24rpx] items-center"   :id="'id' + index" @click="selectDateEvent(index,item)" :class="{ 'bg-[#fff]': index == dateActive }" v-if="item.children.length > 0 ">
							<view class="text-[24rpx] leading-[58rpx]">{{item.md}}</view>
							<view class="text-[24rpx] leading-[58rpx]">({{item.week}})</view>
						</view>
					</template>
				</scroll-view>
				<!-- 右侧时间选择 -->
				<scroll-view :scroll-y="true" class="right w-[calc(100%-230rpx)] px-[30rpx] box-border" scroll-with-animation  :scroll-into-view="'id' + (timeActive ? timeActive - 1 : 0)">
					<!-- 立即下单选项（只在今天显示） -->
					<view v-if="dateActive === 0 && dateArr[0] && dateArr[0].mdTime === nowDate" 
						class=" h-[72rpx] flex  border-0 border-solid border-b-[2rpx] border-[#eee] justify-between items-center" 
						@click="selectImmediateTime" 
						:class="{'text-[var(--primary-color)]': timeActive === -1}" 
						id="id-immediate">
						<view class="text-[24rpx]" :class="{'text-[var(--primary-color)]': timeActive === -1}">
							<text>立即下单</text>
						</view>
						<text v-if="timeActive === -1" class="nc-iconfont nc-icon-duihaoV6mm mr-[30rpx] text-[38rpx]"></text>
					</view>
					<!-- 时间选项 -->
					<template v-for="(el,key) in dateArr[dateActive].children" :key="key"  >
						<view class=" h-[72rpx] flex  border-0 border-solid border-b-[2rpx] border-[#eee] justify-between items-center" v-if="!el.disable"  @click="selectTimeEvent(key,el)" :class="{'text-[var(--primary-color)]':key == timeActive}" :id="'id' + key">
							<view class="text-[24rpx]" :class="{'text-[var(--primary-color)]':key == timeActive}">
								<text>{{el.begin}}-{{el.end}}</text>
							</view>
							<text v-if="key == timeActive" class="nc-iconfont nc-icon-duihaoV6mm mr-[30rpx] text-[38rpx]"></text>
						</view>
					</template>
				</scroll-view>

			</view>
		</view>
	</u-popup>
</template>

<script >
import {initData,initTime,currentTime,timestampTransition,timeTransition} from './date.js'
import { cloneDeep } from 'lodash-es'
export default {
		name: 'times',
		model: {
			prop: "showPop",
			event: "change"
		},
		props: {
			rules: {
				type: Object,
				default () {
					return {}
				}
			},
			isQuantum: {
				type: Boolean,
				default: false
			}
		},
		data() {
			return {
				orderDateTime: '', // 选中时间
				orderDateStamp: '', // 选中时间年-月-日
				dateArr: [], //日期数据
				timeArr: [], //时间数据
				nowDate: "", // 当前日期(月,日)
				dateActive: 0, //选中的日期索引
				timeActive: 0, //选中的时间索引
				selectDate: "", //选择的日期
				show:false,
			}
		},
		created() {
			this.nowDate = currentTime().md
			this.initOnload()
		},
		methods: {
			initOnload() {
				let dateObj = initData() // 日期栏初始化
				this.timeArr = initTime(timestampTransition(this.rules.start),timestampTransition(this.rules.end), Number(this.rules.interval)/60, this.isQuantum) //时间选项初始化
				let  time = Math.floor(timeTransition(currentTime().time)) + (parseFloat(this.rules.advance)*60*60) //当前时间加时间间隔
				this.dateArr = []
				dateObj.forEach((item, index) =>{
					if(this.rules.week.indexOf(item.dayNum) != -1){
						item.children = cloneDeep(this.timeArr)
						item.children.forEach((el,key)=>{
							if (item.mdTime == this.nowDate && time > `${timeTransition(el.begin+':00')}`) {
								delete item.children[key];
							}
						})
						item.children = item.children.filter(item => item !== '');

						this.dateArr.push(item)
					}

				})

				let flag = true
				this.dateArr.forEach((item,index) =>{
					item.children.forEach((el,key)=>{
						if(!el.disable && flag ){
							flag = false
							this.timeActive = key
							this.dateActive = index
							this.selectDate = this.dateArr[index].mdTime
							this.orderDateStamp = this.dateArr[index].date
							this.orderDateTime = `${this.selectDate} ${item.children[this.timeActive].begin}-${item.children[this.timeActive].end}`
							this.$emit('change', this.orderDateTime) //默认选中的值
							this.$emit('getStamp', this.orderDateStamp)
						}
					})
				})
			},
			// 日期选择事件
			selectDateEvent(index, item) {
				this.dateActive = index
				this.selectDate = item.mdTime
				this.orderDateStamp = item.date
				// 如果是今天且有立即下单选项，默认选中第一个时间段；否则选中第一个可用时间段
				if (index === 0 && item.mdTime === this.nowDate) {
					// 今天：可以选择立即下单，但默认选中第一个时间段
					this.timeActive = 0
					if (item.children.length > 0 && item.children[0]) {
						this.orderDateTime = `${this.selectDate} ${item.children[this.timeActive].begin}-${item.children[this.timeActive].end}`
					}
				} else {
					// 其他日期：选中第一个可用时间段
					this.timeActive = 0
					if (item.children.length > 0 && item.children[0]) {
						this.orderDateTime = `${this.selectDate} ${item.children[this.timeActive].begin}-${item.children[this.timeActive].end}`
					}
				}
				this.$emit('change', this.orderDateTime)
				this.$emit('getStamp', this.orderDateStamp)
			},
			// 立即下单选择事件
			selectImmediateTime() {
				this.timeActive = -1
				this.orderDateTime = '立即下单'
				this.selectDate = this.dateArr[0].mdTime
				// 使用当前日期作为时间戳
				const now = currentTime()
				this.orderDateStamp = now.date
				this.show = false
				this.$emit('change', this.orderDateTime)
				// 传递当前日期的时间戳（秒级）
				this.$emit('getStamp', new Date(now.date + ' ' + now.time).getTime() / 1000)
			},
			// 时间选择事件
			selectTimeEvent(index, item) {
				this.handleSelectQuantum(index, item)
				this.show = false
				this.$emit('change', this.orderDateTime)
			},
			handleSelectQuantum(index, item) {
				this.timeActive = index
				this.orderDateTime = `${this.selectDate} ${item.begin}-${item.end}`
			}
		}
	}
</script>
