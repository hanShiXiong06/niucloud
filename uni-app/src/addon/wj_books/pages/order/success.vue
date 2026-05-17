<template>
  <view class="success-container">
    <!-- 顶部背景 -->
    <view class="success-header">
      <text class="page-title">提交成功</text>
      <view class="header-decoration"></view>
    </view>

    <!-- 加载提示 -->
    <view class="loading-container" v-if="loading">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>

    <!-- 主内容区 -->
    <view class="success-content" v-else>
      <!-- 成功图标 -->
      <view class="success-icon-wrap">
        <view class="success-icon">
          <u-icon name="checkmark" color="#FFFFFF" size="80rpx"></u-icon>
        </view>
        <text class="success-title">订单提交成功</text>
        <text class="success-desc">快递员将尽快与您联系上门取件</text>
      </view>

      <!-- 订单信息卡片 -->
      <view class="content-card order-info-card">
        <view class="card-title">
          <u-icon name="file-text" color="#4CAF50" size="32rpx"></u-icon>
          <text>订单信息</text>
        </view>
        
        <view class="order-info-list">
          <view class="info-item">
            <text class="info-label">订单编号</text>
            <text class="info-value">{{orderInfo.order_no}}</text>
          </view>
          <view class="info-item">
            <text class="info-label">提交时间</text>
            <text class="info-value">{{orderInfo.create_time}}</text>
          </view>
          <view class="info-item">
            <text class="info-label">预约上门</text>
            <text class="info-value">{{orderInfo.pickup_time}}</text>
          </view>
          <view class="info-item">
            <text class="info-label">书籍数量</text>
            <text class="info-value">{{orderInfo.book_count}}本</text>
          </view>
          <view class="info-item">
            <text class="info-label">预估金额</text>
            <text class="info-value price-highlight">¥{{orderInfo.total_amount}}</text>
          </view>
        </view>
      </view>

      <!-- 回收地址卡片 -->
      <view class="content-card">
        <view class="card-title">
          <u-icon name="map-fill" color="#4CAF50" size="32rpx"></u-icon>
          <text>回收地址</text>
        </view>
        
        <view class="address-info">
          <view class="address-contact">
            <text class="contact-name">{{address.name}}</text>
            <text class="contact-phone">{{address.mobile}}</text>
          </view>
          <view class="address-detail">
            <text>{{address.address}}</text>
          </view>
        </view>
      </view>

      <!-- 回收流程卡片 -->
      <view class="content-card">
        <view class="card-title">
          <u-icon name="calendar" color="#4CAF50" size="32rpx"></u-icon>
          <text>回收流程</text>
        </view>
        
        <view class="process-steps">
          <view class="process-step" :class="{'process-active': true}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">提交订单</text>
              <text class="step-time">{{orderInfo.create_time}}</text>
            </view>
          </view>
          <view class="process-step" :class="{'process-active': true}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">等待上门</text>
              <text class="step-time">预计{{orderInfo.pickup_time}}</text>
            </view>
          </view>
          <view class="process-step">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">完成取件</text>
              <text class="step-time">待完成</text>
            </view>
          </view>
          <view class="process-step">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">平台审核</text>
              <text class="step-time">待完成</text>
            </view>
          </view>
          <view class="process-step">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">回收完成</text>
              <text class="step-time">待完成</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 温馨提示 -->
      <view class="content-card notice-card">
        <view class="card-title">
          <u-icon name="info-circle" color="#4CAF50" size="32rpx"></u-icon>
          <text>温馨提示</text>
        </view>
        <view class="notice-list">
          <view class="notice-item">
            <view class="notice-dot"></view>
            <text>快递员上门前会提前电话联系您，请保持电话畅通</text>
          </view>
          <view class="notice-item">
            <view class="notice-dot"></view>
            <text>如需修改预约时间，可前往"我的订单"中进行修改</text>
          </view>
          <view class="notice-item">
            <view class="notice-dot"></view>
            <text>回收价格将根据书籍实际情况，由平台最终审核确定</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部按钮 -->
    <view class="button-group safe-area-inset-bottom">
      <view class="order-btn secondary-btn" @click="goToOrder">
        <text>查看订单</text>
      </view>
      <view class="order-btn primary-btn" @click="goToHome">
        <text>回到首页</text>
      </view>
    </view>
  </view>
</template>

<script>
import { redirect, img } from '@/utils/common'
import { getOrderDetail } from '@/addon/wj_books/api/wj_books_order'
import { getAddressList } from '@/app/api/member'

export default {
  data() {
    return {
      orderId: 0,
      orderInfo: {
        order_no: '',
        create_time: '',
        pickup_time: '',
        book_count: 0,
        total_amount: '0.00',
        status: 1 // 1: 待上门, 2: 已取件, 3: 审核中, 4: 已完成, 5: 已取消
      },
      address: {
        name: '',
        mobile: '',
        address: ''
      },
      loading: true
    }
  },
  
  onLoad(options) {
    if (options.order_id || options.id) {
      this.orderId = parseInt(options.order_id || options.id) || (options.order_id || options.id);
      this.loadOrderDetail();
    } else {
      uni.showToast({
        title: '订单信息缺失',
        icon: 'none'
      });
      setTimeout(() => {
        uni.navigateBack();
      }, 1500);
    }
  },
  methods: {
    img,
    // 加载订单详情
    async loadOrderDetail() {
      this.loading = true;
      
      try {
        // 显示加载中
        uni.showLoading({
          title: '加载中...'
        });
        
        // 获取订单信息
        const res = await getOrderDetail({ id: this.orderId });
        
        if (res.code === 1 && res.data) {
          // 处理返回的订单数据
          this.orderInfo = res.data.order_info;
          
          // 加载地址信息
          if (res.data.address_info) {
            this.address = {
              name: res.data.address_info.name,
              mobile: this.maskMobile(res.data.address_info.mobile),
              address: res.data.address_info.full_address
            };
          } else {
            await this.loadAddressInfo(this.orderInfo.address_id);
          }
        } else {
          uni.showToast({
            title: res.msg || '获取订单信息失败',
            icon: 'none'
          });
        }
      } catch (error) {
        console.error('获取订单详情错误', error);
        uni.showToast({
          title: '获取订单信息失败，请重试',
          icon: 'none'
        });
      } finally {
        this.loading = false;
        uni.hideLoading();
      }
    },
    
    // 加载地址信息
    async loadAddressInfo(addressId) {
      try {
        const res = await getAddressList({});
        
        if (res.code === 1 && res.data && res.data.length > 0) {
          // 查找对应的地址
          const matchedAddress = res.data.find(item => item.id === addressId);
          
          if (matchedAddress) {
            this.address = {
              name: matchedAddress.name,
              mobile: this.maskMobile(matchedAddress.mobile),
              address: matchedAddress.full_address
            };
          }
        }
      } catch (error) {
        console.error('获取地址信息错误', error);
      }
    },
    
    // 手机号码脱敏
    maskMobile(mobile) {
      if (!mobile || mobile.length < 11) return mobile;
      return mobile.substring(0, 3) + '****' + mobile.substring(7);
    },
    
    goToOrder() {
      uni.navigateTo({
        url: '/addon/wj_books/pages/order/detail?id=' + this.orderId
      });
    },
    
    goToHome() {
      uni.reLaunch({
        url: '/addon/wj_books/pages/home/index'
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

.success-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  padding-bottom: 150rpx;
}

/* 顶部背景区域 */
.success-header {
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

/* 成功图标区域 */
.success-content {
  flex: 1;
  margin-top: -80rpx;
  padding: 0 30rpx 30rpx;
  position: relative;
  z-index: 20;
}

.success-icon-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 30rpx;
}

.success-icon {
  width: 120rpx;
  height: 120rpx;
  border-radius: 60rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 20rpx;
  box-shadow: 0 6rpx 16rpx rgba(76, 175, 80, 0.2);
}

.success-title {
  font-size: 36rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 12rpx;
}

.success-desc {
  font-size: 28rpx;
  color: #666666;
}

/* 加载状态样式 */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: 100rpx;
  padding: 30rpx;
}

.loading-spinner {
  width: 40rpx;
  height: 40rpx;
  border: 4rpx solid rgba(255, 255, 255, 0.2);
  border-top: 4rpx solid #FFFFFF;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  font-size: 28rpx;
  color: #666666;
  margin-top: 20rpx;
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

/* 订单信息样式 */
.order-info-list {
  display: flex;
  flex-direction: column;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #F5F5F5;
}

.info-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.info-label {
  font-size: 28rpx;
  color: #666666;
}

.info-value {
  font-size: 28rpx;
  color: #333333;
  font-weight: 500;
}

.price-highlight {
  color: #FF6B6B;
  font-weight: 600;
}

/* 地址信息样式 */
.address-info {
  padding: 10rpx 0;
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

.address-detail {
  font-size: 28rpx;
  color: #666666;
  line-height: 1.5;
}

/* 回收流程样式 */
.process-steps {
  position: relative;
}

.process-step {
  display: flex;
  position: relative;
  padding-bottom: 40rpx;
  padding-left: 30rpx;
}

.process-step:last-child {
  padding-bottom: 0;
}

.process-step::before {
  content: '';
  position: absolute;
  left: 10rpx;
  top: 30rpx;
  bottom: 0;
  width: 2rpx;
  background-color: #E0E0E0;
}

.process-step:last-child::before {
  display: none;
}

.process-active .step-dot {
  background-color: #4CAF50;
  border-color: #E8F5E9;
}

.process-active .step-title {
  color: #4CAF50;
}

.step-dot {
  width: 20rpx;
  height: 20rpx;
  border-radius: 50%;
  background-color: #E0E0E0;
  border: 4rpx solid #F5F5F5;
  position: absolute;
  left: 0;
  top: 10rpx;
  z-index: 1;
}

.step-content {
  margin-left: 20rpx;
}

.step-title {
  font-size: 28rpx;
  color: #333333;
  font-weight: 500;
  margin-bottom: 8rpx;
}

.step-time {
  font-size: 24rpx;
  color: #999999;
}

/* 温馨提示样式 */
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

/* 底部按钮 */
.button-group {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  padding: 20rpx 30rpx;
  background-color: #FFFFFF;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);
  z-index: 100;
}

.order-btn {
  flex: 1;
  height: 80rpx;
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 10rpx;
  transition: all 0.3s ease;
}

.secondary-btn {
  background-color: #F0F9EB;
  border: 1rpx solid #4CAF50;
}

.secondary-btn text {
  color: #4CAF50;
  font-size: 28rpx;
  font-weight: 500;
}

.primary-btn {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  box-shadow: 0 6rpx 12rpx rgba(76, 175, 80, 0.2);
}

.primary-btn text {
  color: #FFFFFF;
  font-size: 28rpx;
  font-weight: 500;
}
</style> 