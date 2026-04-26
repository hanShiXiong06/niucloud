<template>
  <view class="order-container">
    <!-- 顶部背景 -->
    <view class="order-header">
      <text class="page-title">订单确认</text>
      <view class="header-decoration"></view>
    </view>

    <!-- 主内容区 -->
    <view class="order-content">
      <!-- 地址卡片 -->
      <view class="content-card address-card" @click="selectAddress">
        <view class="card-left">
          <view class="location-icon">
            <u-icon name="map-fill" color="#FFFFFF" size="40rpx"></u-icon>
          </view>
        </view>
        <view class="card-main">
          <view v-if="address">
            <view class="address-contact">
              <text class="contact-name">{{address.name}}</text>
              <text class="contact-phone">{{address.mobile}}</text>
              <view class="default-tag" v-if="address.is_default">默认</view>
        </view>
        <view class="address-detail">
          <text>{{address.full_address}}</text>
        </view>
      </view>
      <view class="no-address" v-else>
            <text>添加回收地址</text>
          </view>
      </view>
        <view class="card-right">
        <u-icon name="arrow-right" color="#CCCCCC" size="40rpx"></u-icon>
      </view>
    </view>

    <!-- 预约时间 -->
      <view class="content-card">
      <view class="card-title">
          <u-icon name="calendar" color="#4CAF50" size="32rpx"></u-icon>
          <text>预约时间</text>
      </view>
      <view class="time-selection">
        <!-- 日期选择部分 -->
        <view class="date-tabs">
          <view 
            class="date-tab" 
            v-for="(date, index) in availableDates" 
            :key="index"
            :class="{'active-tab': selectedDateIndex === index}"
            @click="selectDate(index)"
            hover-class="date-tab-hover"
            hover-stay-time="100"
          >
            <text class="date-day">{{date.day}}</text>
            <text class="date-week">{{date.week}}</text>
          </view>
        </view>
        
        <!-- 时间段选择部分 -->
        <view class="time-slots-container">
          <view 
            class="time-slot" 
            v-for="(slot, index) in filteredTimeSlots" 
            :key="index"
            :class="{
              'active-slot': isSameTimeSlot(slot, selectedTimeSlot), 
              'disabled-slot': slot.disabled
            }"
            @click="selectTimeSlot(slot)"
            :hover-class="slot.disabled ? '' : 'time-slot-hover'"
            hover-stay-time="100"
          >
            <text>{{slot.time}}</text>
          </view>
        </view>
        
        <!-- 已选择的时间显示 -->
        <view class="selected-time" v-if="selectedTime">
          <u-icon name="checkmark-circle" color="#4CAF50" size="28rpx"></u-icon>
          <text>已选择: {{selectedTime}}</text>
        </view>
      </view>
    </view>

    <!-- 回收书籍列表 -->
      <view class="content-card">
      <view class="card-title">
          <u-icon name="file-text" color="#4CAF50" size="32rpx"></u-icon>
        <text>回收书籍</text>
          <text class="book-count">{{books.length}}种 / {{totalQuantity}}本</text>
      </view>
        
        <scroll-view class="book-list-scroll" scroll-y="true">
          <view class="book-list">
        <view class="book-item" v-for="(item, index) in books" :key="index">
              <view v-if="item.img" class="book-cover">
                <image :src="img(item.img)" mode="aspectFill"></image>
              </view>
              <view v-else class="book-cover default-cover">
                <text>未知图片</text>
              </view>
          <view class="book-info">
                <text class="book-title">{{item.title || '未知书名'}}</text>
                <text class="book-author">{{item.author || '未知作者'}}</text>
                <view class="book-quantity">
                  <text>数量：{{item.quantity || 1}}本</text>
                </view>
                <view class="book-price">
                  <text>预估回收价</text>
                  <text class="price-value">¥{{((item.recycle_price || 0) * (item.quantity || 1)).toFixed(2)}}</text>
                </view>
          </view>
        </view>
      </view>
        </scroll-view>
        
        <view class="price-summary">
          <view class="summary-row">
            <text>总数量</text>
            <text class="summary-quantity">{{totalQuantity}}本</text>
          </view>
          <view class="summary-row">
            <text>预估回收总价</text>
            <text class="summary-price">¥{{totalPrice}}</text>
          </view>
      </view>
    </view>

    <!-- 备注信息 -->
      <view class="content-card">
      <view class="card-title">
          <u-icon name="edit-pen" color="#4CAF50" size="32rpx"></u-icon>
        <text>备注信息</text>
      </view>
      <view class="remark-input">
          <u-input v-model="remark" type="textarea" placeholder="如有特殊要求，请在此备注" 
                  maxlength="100" height="160" border="none"></u-input>
        <text class="remark-count">{{remark.length}}/100</text>
      </view>
    </view>

    <!-- 回收须知 -->
      <view class="content-card notice-card">
      <view class="card-title">
          <u-icon name="info-circle" color="#4CAF50" size="32rpx"></u-icon>
        <text>回收须知</text>
      </view>
      <view class="notice-list">
        <view class="notice-item">
            <view class="notice-dot"></view>
          <text>请提前整理好书籍，以便快递员上门取件</text>
        </view>
        <view class="notice-item">
            <view class="notice-dot"></view>
          <text>请确保书籍是正版，且非缺页、水渍等影响阅读的书籍。</text>
        </view>
        <view class="notice-item">
            <view class="notice-dot"></view>
          <text>平台收货审核完成后，款项将立即到账</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部提交栏 -->
    <view class="order-footer safe-area-inset-bottom">
      <view class="price-area">
        <text class="price-label">预估总价</text>
        <text class="price-value">¥{{totalPrice}}</text>
      </view>
      <view class="submit-btn" :class="{'active-btn': canSubmit}" @click="submitOrder">
        <text>{{ canSubmit ? '提交订单' : `至少需要${minBookCount}本书` }}</text>
      </view>
    </view>
  </view>
</template>

<script>
import { getAddressList } from '@/app/api/member'
import { getCartList } from '@/addon/wj_books/api/wj_books_cart'
import { createOrder } from '@/addon/wj_books/api/wj_books_order'
import { getConfig } from '@/addon/wj_books/api/wj_books_info'
import { redirect, img } from '@/utils/common'

export default {
  data() {
    return {
      address: null,
      selectedTime: '',
      selectedDateIndex: 0,
      selectedTimeSlot: null,
      remark: '',
      books: [],
      availableDates: [],
      availableTimeSlots: [
        { time: '09:00-11:00', disabled: false },
        { time: '11:00-13:00', disabled: false },
        { time: '13:00-15:00', disabled: false },
        { time: '15:00-17:00', disabled: false },
        { time: '17:00-19:00', disabled: false }
      ],
      loading: false,
      minBookCount: 8 // 默认最低回收数量，将从配置中获取
    }
  },
  computed: {
    // 计算总价格
    totalPrice() {
      return this.books
        .reduce((sum, item) => sum + parseFloat(item.recycle_price || 0) * (item.quantity || 1), 0)
        .toFixed(2);
    },
    // 计算总数量
    totalQuantity() {
      return this.books
        .reduce((sum, item) => sum + (parseInt(item.quantity) || 1), 0);
    },
    canSubmit() {
      return this.totalQuantity >= this.minBookCount;
    },
    filteredTimeSlots() {
      const now = new Date();
      const currentHour = now.getHours();
      const currentMinute = now.getMinutes();
      const isToday = this.selectedDateIndex === 0;
      
      // 创建一个新数组以避免修改原始数据
      return this.availableTimeSlots.map(slot => {
        // 创建新对象而不是修改原始对象
        const newSlot = { ...slot };
        
        const [startTime, endTime] = slot.time.split('-');
        const [startHour, startMinute] = startTime.split(':').map(Number);
        
        // 如果是今天，检查时间是否已过期
        if (isToday) {
          // 如果当前小时已经超过了开始时间，或者当前小时等于开始时间但分钟已过
          if (currentHour > startHour || (currentHour === startHour && currentMinute > startMinute)) {
            newSlot.disabled = true;
          }
        }
        
        return newSlot;
      });
    }
  },
  onLoad() {
    // 加载默认地址和回收车中的书籍
    this.loadDefaultAddress();
    this.loadCartItems();
    this.initAvailableDates();
    // 加载系统配置
    this.loadSystemConfig();
  },
  methods: {
    img,
    // 加载系统配置
    async loadSystemConfig() {
      try {
        const res = await getConfig();
        
        if (res.code === 1 && res.data) {
          // 设置最低回收数量
          this.minBookCount = res.data.min_book_count || 8;
          console.log('系统配置:', res.data);
        } else {
          console.error('获取系统配置失败', res.msg);
        }
      } catch (error) {
        console.error('获取系统配置失败', error);
      }
    },
    // 加载默认地址
    loadDefaultAddress() {
      this.loading = true;
      getAddressList({}).then(res => {
        this.loading = false;
        if (res.code === 1 && res.data && res.data.length > 0) {
          // 查找默认地址
          const defaultAddress = res.data.find(item => item.is_default === 1);
          if (defaultAddress) {
            this.address = defaultAddress;
          } else if (res.data.length > 0) {
            // 如果没有默认地址，使用第一个地址
            this.address = res.data[0];
          }
        }
      }).catch(() => {
        this.loading = false;
      });
    },
    // 加载回收车中的书籍
    loadCartItems() {
      this.loading = true;
      getCartList().then(res => {
        this.loading = false;
        if (res.code === 1 && res.data) {
          // 只获取可回收的图书
          this.books = (res.data.list || []).filter(item => item.can_recycle !== 0);
        }
      }).catch(() => {
        this.loading = false;
      });
    },
    selectAddress() {
      // 跳转到地址选择页面
      uni.setStorage({
        key: 'selectAddressCallback',
        data: {
          back: '/addon/wj_books/pages/order/index'
        },
        success: () => {
          uni.navigateTo({
            url: '/addon/wj_books/pages/address/index'
          });
        }
      });
    },
    selectDate(index) {
      // 轻微振动提供触觉反馈
      uni.vibrateShort();
      
      this.selectedDateIndex = index;
      this.selectedTimeSlot = null;
      this.selectedTime = '';
    },
    selectTimeSlot(slot) {
      if (slot.disabled) {
        // 振动提示不可选
        uni.vibrateShort({
          success: function () {
            // 振动成功
          }
        });
        uni.showToast({
          title: '该时间段已过期，请选择其他时间',
          icon: 'none'
        });
        return;
      }
      
      // 选择时轻微振动，提供触觉反馈
      uni.vibrateShort();
      
      // 存储选中的时间段，确保是深拷贝
      this.selectedTimeSlot = JSON.parse(JSON.stringify(slot));
      
      // 立即更新选择的时间（为显示目的仍然使用"今天"/"明天"的相对日期）
      const date = this.availableDates[this.selectedDateIndex].day;
      this.selectedTime = `${date} ${this.selectedTimeSlot.time}`;
    },
    // 将相对日期转换为具体日期格式的方法
    getFormattedDate() {
      if (!this.selectedTime) return '';
      
      // 获取选中的日期对象
      const selectedDate = this.availableDates[this.selectedDateIndex].date;
      
      // 格式化日期为"YYYY-MM-DD"
      const year = selectedDate.getFullYear();
      const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
      const day = String(selectedDate.getDate()).padStart(2, '0');
      const formattedDate = `${year}-${month}-${day}`;
      
      // 拼接时间段
      return `${formattedDate} ${this.selectedTimeSlot.time}`;
    },
    submitOrder() {
      // 表单验证
      if (!this.address) {
        uni.showToast({
          title: '请选择上门回收地址',
          icon: 'none'
        });
        return;
      }
      
      if (!this.selectedTime) {
        uni.showToast({
          title: '请选择预约上门时间',
          icon: 'none'
        });
        return;
      }
      
      // 检查最低数量要求
      if (this.totalQuantity < this.minBookCount) {
        uni.showToast({
          title: `至少需要${this.minBookCount}本书籍才能回收`,
          icon: 'none'
        });
        return;
      }
      
      // 显示加载提示
      uni.showLoading({
        title: '提交中...'
      });
      
      // 获取格式化后的日期时间
      const formattedPickupTime = this.getFormattedDate();
      
      // 准备订单数据
      const orderData = {
        address_id: this.address.id,
        pickup_time: formattedPickupTime, // 使用格式化后的日期时间
        remark: this.remark,
        book_list: this.books.map(book => ({
          book_id: book.book_id,
          isbn: book.isbn,
          quantity: book.quantity
        }))
      };
      
      // 调用创建订单接口
      createOrder(orderData).then(res => {
        uni.hideLoading();
        
        if (res.code === 1) {
          // 下单成功，跳转到订单详情页
          uni.showToast({
            title: '下单成功',
            icon: 'success'
          });
          
          // 延迟跳转，让用户看到成功提示
          setTimeout(() => {
            uni.navigateTo({
              url: '/addon/wj_books/pages/order/success?order_id=' + res.data.order_id
            });
          }, 1500);
        } else {
          // 下单失败
          uni.showToast({
            title: res.msg || '下单失败，请重试',
            icon: 'none'
          });
        }
      }).catch(err => {
        uni.hideLoading();
        uni.showToast({
          title: '网络错误，请重试',
          icon: 'none'
        });
        console.error('下单失败:', err);
      });
    },
    initAvailableDates() {
      const days = ['日', '一', '二', '三', '四', '五', '六'];
      const now = new Date();
      const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
      
      this.availableDates = [
        {
          day: '今天',
          week: `周${days[now.getDay()]}`,
          date: now
        },
        {
          day: '明天',
          week: `周${days[tomorrow.getDay()]}`,
          date: tomorrow
        }
      ];
    },
    isSameTimeSlot(slot, selectedSlot) {
      if (!selectedSlot) return false;
      return slot.time === selectedSlot.time;
    }
  },
  // 监听页面显示，用于接收地址选择后的回调
  onShow() {
    // 检查是否有地址选择回调
    const addressCallback = uni.getStorageSync('selectAddressCallback');
    if (addressCallback && addressCallback.address_id) {
      // 获取选中的地址详情
      getAddressList({}).then(res => {
        if (res.code === 1 && res.data) {
          const selectedAddress = res.data.find(item => item.id === addressCallback.address_id);
          if (selectedAddress) {
            this.address = selectedAddress;
          }
        }
        // 清除回调数据
        uni.removeStorageSync('selectAddressCallback');
      });
    }
  }
}
</script>

<style lang="scss" scoped>
/* 全局设置 */
page {
  background-color: #f8f8f8;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

.safe-area-inset-bottom {
  padding-bottom: env(safe-area-inset-bottom);
}

.order-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  padding-bottom: 150rpx;
}

/* 顶部背景区域 */
.order-header {
  height: 220rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  position: relative;
  padding-top: env(safe-area-inset-top);
  overflow: hidden;
}


.page-title {
  position: absolute;
  width: 100%;
  text-align: center;
  top: calc(30rpx + env(safe-area-inset-top));
  color: #FFFFFF;
  font-size: 34rpx;
  font-weight: 600;
  z-index: 10;
}

.header-decoration {
  position: absolute;
  width: 600rpx;
  height: 600rpx;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.1);
  right: -200rpx;
  top: -300rpx;
}

/* 主内容区 */
.order-content {
  flex: 1;
  margin-top: -80rpx;
  padding: 0 30rpx 30rpx;
  position: relative;
  z-index: 20;
}

/* 卡片通用样式 */
.content-card {
  background-color: #FFFFFF;
  border-radius: 24rpx;
  padding: 30rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 16rpx rgba(0, 0, 0, 0.04);
}

.card-title {
  display: flex;
  align-items: center;
  margin-bottom: 24rpx;
  font-size: 30rpx;
  color: #333333;
  font-weight: 600;
}

.card-title u-icon {
  margin-right: 12rpx;
}

/* 地址卡片样式 */
.address-card {
  display: flex;
  align-items: center;
  padding: 20rpx;
}

.card-left {
  margin-right: 20rpx;
}

.location-icon {
  width: 70rpx;
  height: 70rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  display: flex;
  justify-content: center;
  align-items: center;
}

.card-main {
  flex: 1;
}

.address-contact {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
}

.contact-name {
  font-size: 32rpx;
  font-weight: 600;
  color: #333333;
  margin-right: 20rpx;
}

.contact-phone {
  font-size: 28rpx;
  color: #666666;
}

.default-tag {
  font-size: 20rpx;
  color: #4CAF50;
  background-color: #F0F9EB;
  padding: 2rpx 12rpx;
  border-radius: 20rpx;
  margin-left: 12rpx;
}

.address-detail {
  font-size: 28rpx;
  color: #666666;
  line-height: 1.5;
}

.no-address {
  font-size: 30rpx;
  color: #4CAF50;
  display: flex;
  align-items: center;
}

.card-right {
  padding: 0 10rpx;
}

/* 时间选择样式 */
.time-selection {
  display: flex;
  flex-direction: column;
}

.date-tabs {
  display: flex;
  justify-content: space-between;
  padding: 20rpx 0;
}

.date-tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 75rpx;
  padding: 10rpx 0;
  border-radius: 12rpx;
  background-color: #F8F8F8;
  margin: 0 10rpx;
  transition: all 0.2s ease;
  border: 2rpx solid transparent;
  box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.05);
}

.date-tab-hover {
  transform: scale(0.97);
  opacity: 0.9;
  background-color: #E8F5E9;
}

.date-tab:first-child {
  margin-left: 0;
}

.date-tab:last-child {
  margin-right: 0;
}

.active-tab {
  background-color: #E8F5E9;
  border: 2rpx solid #4CAF50;
  box-shadow: 0 2rpx 8rpx rgba(76, 175, 80, 0.15);
}

.date-day {
  font-size: 28rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 4rpx;
}

.date-week {
  font-size: 22rpx;
  color: #999999;
}

.active-tab .date-day {
  color: #4CAF50;
}

.active-tab .date-week {
  color: #4CAF50;
}

.time-slots-container {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20rpx;
  padding: 20rpx 0;
}

.time-slot {
  height: 80rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  border-radius: 12rpx;
  background-color: #F8F8F8;
  font-size: 26rpx;
  color: #333333;
  border: 2rpx solid transparent;
  transition: all 0.2s ease;
  box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.05);
}

.time-slot-hover {
  transform: scale(0.97);
  background-color: #E8F5E9;
}

.active-slot {
  background-color: #4CAF50;
  color: #FFFFFF;
  font-weight: 600;
  border: 2rpx solid #3D9140;
  box-shadow: 0 2rpx 8rpx rgba(76, 175, 80, 0.25);
}

.disabled-slot {
  background-color: #F5F5F5;
  color: #CCCCCC;
  opacity: 0.6;
}

.selected-time {
  padding: 20rpx 0 0;
  font-size: 28rpx;
  color: #666666;
  border-top: 1px solid #F0F0F0;
  margin-top: 10rpx;
  display: flex;
  align-items: center;
}

.selected-time u-icon {
  margin-right: 8rpx;
}

.selected-time text {
  color: #4CAF50;
  font-weight: 500;
}

/* 书籍列表样式 */
.book-count {
  font-size: 24rpx;
  color: #999999;
  margin-left: auto;
  background-color: #F5F5F5;
  padding: 4rpx 14rpx;
  border-radius: 20rpx;
}

.book-list-scroll {
  height: 500rpx; /* 固定高度，大约显示4本书的高度 */
  margin-bottom: 24rpx;
  position: relative;
  background-color: #FFFFFF;
}

.book-list {
  width: 100%;
}

.book-item {
  display: flex;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #F5F5F5;
}

.book-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.book-cover {
  width: 120rpx;
  height: 170rpx;
  border-radius: 12rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
  margin-right: 24rpx;
  overflow: hidden;
  position: relative;
}

.book-cover image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.default-cover {
  background-color: #f0f0f0;
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
}

.default-cover text {
  font-size: 24rpx;
  color: #999999;
  padding: 0 10rpx;
  line-height: 1.2;
}

.book-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.book-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 8rpx;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.book-author {
  font-size: 24rpx;
  color: #666666;
  margin-bottom: 8rpx;
}

.book-quantity {
  font-size: 24rpx;
  color: #4CAF50;
  margin-bottom: 8rpx;
  font-weight: 500;
}

.book-price {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 24rpx;
  color: #999999;
}

.price-value {
  color: #FF6B6B;
  font-size: 28rpx;
  font-weight: 600;
}

.price-summary {
  display: flex;
  flex-direction: column;
  padding-top: 24rpx;
  border-top: 1rpx solid #F5F5F5;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 28rpx;
  color: #666666;
  margin-bottom: 10rpx;
}

.summary-row:last-child {
  margin-bottom: 0;
}

.summary-quantity {
  font-size: 28rpx;
  font-weight: 600;
  color: #4CAF50;
}

.summary-price {
  color: #FF6B6B;
  font-size: 32rpx;
  font-weight: 600;
}

/* 备注样式 */
.remark-input {
  position: relative;
  background-color: #F8F8F8;
  border-radius: 16rpx;
  padding: 20rpx;
}

.remark-count {
  position: absolute;
  right: 24rpx;
  bottom: 24rpx;
  font-size: 24rpx;
  color: #999999;
}

/* 回收须知样式 */
.notice-card {
  background-color: #FFFFFF;
}

.notice-list {
  display: flex;
  flex-direction: column;
}

.notice-item {
  display: flex;
  margin-bottom: 16rpx;
  padding: 12rpx 16rpx;
  border-radius: 12rpx;
  background-color: #FAFAFA;
  align-items: flex-start;
}

.notice-item:last-child {
  margin-bottom: 0;
}

.notice-dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background-color: #4CAF50;
  margin: 12rpx 16rpx 0 0;
}

.notice-item text {
  font-size: 26rpx;
  color: #666666;
  flex: 1;
  line-height: 1.6;
}

/* 底部提交栏 */
.order-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #FFFFFF;
  height: 120rpx;
  display: flex;
  align-items: center;
  padding: 0 30rpx;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);
  z-index: 50;
}

.price-area {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.price-label {
  font-size: 24rpx;
  color: #999999;
}

.price-value {
  font-size: 36rpx;
  color: #FF6B6B;
  font-weight: 600;
}

.submit-btn {
  width: 240rpx;
  height: 80rpx;
  border-radius: 40rpx;
  background: #CCCCCC;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: all 0.3s ease;
}

.submit-btn text {
  color: #FFFFFF;
  font-size: 28rpx;
  font-weight: 600;
}

.active-btn {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  box-shadow: 0 6rpx 12rpx rgba(76, 175, 80, 0.2);
}

.submit-btn:active {
  transform: scale(0.98);
}
</style> 