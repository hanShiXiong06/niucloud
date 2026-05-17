<template>
  <view class="order-list-container">
    <!-- 顶部背景 -->
    <view class="order-header">
      <text class="page-title">我的订单</text>
      <view class="header-decoration"></view>
    </view>

    <!-- 主内容区 -->
    <view class="order-content">
      <!-- 订单状态标签 -->
      <view class="order-tabs">
        <view 
          class="order-tab" 
          v-for="(tab, index) in tabs" 
          :key="index"
          :class="{'active-tab': currentTab === index}"
          @click="changeTab(index)"
        >
          <text>{{ tab.name }}</text>
          <view class="tab-indicator" v-if="currentTab === index"></view>
        </view>
      </view>

      <!-- 订单列表 -->
      <scroll-view class="order-scroll" scroll-y refresher-enabled @refresherrefresh="onRefresh" :refresher-triggered="refreshing">
        <!-- 未登录状态 -->
        <view class="login-state" v-if="!isLogin">
          <view class="empty-placeholder">
            <u-icon name="info-circle" color="#999999" size="80rpx"></u-icon>
          </view>
          <text class="login-text">您尚未登录，请先登录查看订单</text>
          <view class="login-btn" @click="goToLogin">立即登录</view>
        </view>
        
        <!-- 加载中状态 -->
        <view class="loading-state" v-if="isLogin && loading">
          <view class="loading-spinner"></view>
          <text class="loading-text">加载订单数据中...</text>
        </view>
        
        <!-- 空状态 -->
        <view class="empty-state" v-if="isLogin && !loading && orderList.length === 0">
          <view class="empty-placeholder">
            <u-icon name="file-text" color="#999999" size="80rpx"></u-icon>
          </view>
          <text class="empty-text">暂无订单记录</text>
          <view class="empty-btn" @click="goToHome">去回收书籍</view>
        </view>

        <!-- 订单列表 -->
        <view class="order-list" v-if="isLogin && !loading && orderList.length > 0">
          <view class="order-item" v-for="(order, index) in orderList" :key="index" @click="viewOrderDetail(order)">
            <view class="order-header-row">
              <text class="order-number">订单号：{{ order.order_no }}</text>
              <text class="order-status" :class="'status-' + order.status">{{ getStatusText(order.status) }}</text>
            </view>
            
            <view class="order-book-info">
              <view class="book-covers">
                <view class="book-cover" v-if="order.books && order.books.length > 0" v-for="(book, bookIndex) in order.books.slice(0, 3)" :key="bookIndex">
                  <image :src="img(book.img || defaultCover)" mode="aspectFill"></image>
                </view>
                <view class="book-cover" v-if="!order.books || order.books.length === 0">
                  <view class="default-cover">
                    <text>暂无封面</text>
                  </view>
                </view>
                <view class="more-books" v-if="order.books && order.books.length > 3">
                  <text>+{{ order.books.length - 3 }}</text>
                </view>
              </view>
              <view class="book-summary">
                <text class="book-count">共{{ order.book_count }}本书籍</text>
                <text class="book-price">¥{{ order.total_amount }}</text>
              </view>
            </view>
            
            <view class="order-footer-row">
              <text class="order-time">{{ order.create_time }}</text>
              <view class="order-actions">
                <view class="order-action-btn" v-if="order.status === 1" @click.stop="cancelOrder(order)">
                  <text>取消订单</text>
                </view>
                <view class="order-action-btn primary-btn" @click.stop="viewOrderDetail(order)">
                  <text>查看详情</text>
                </view>
              </view>
            </view>
          </view>
        </view>
      </scroll-view>
    </view>
    <!-- 底部导航 -->
    <tabbar />
  </view>
</template>

<script>
import { getOrderList } from '@/addon/wj_books/api/wj_books_order'
import { getToken, redirect, img } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'

export default {
  data() {
    return {
      isLogin: false,
      tabs: [
        { name: '全部', status: 0 },
        { name: '待上门', status: 1 },
        { name: '已取件', status: 2 },
        { name: '审核中', status: 3 },
        { name: '已完成', status: 4 }
      ],
      currentTab: 0,
      refreshing: false,
      loading: false,
      orderList: [],
      defaultCover: '/static/addon/wj_books/images/default_book.png',
      page: 1,
      pageSize: 10,
      hasMore: true,
      totalCount: 0
    }
  },
  
  onLoad() {
    this.checkLoginStatus();
  },
  
  onShow() {
    this.checkLoginStatus();
  },
  
  methods: {
    img,
    // 检查登录状态
    checkLoginStatus() {
      this.isLogin = !!getToken();
      if (this.isLogin) {
        this.loadOrderList();
      }
    },
    
    // 跳转到登录页
    goToLogin() {
      // 设置登录后返回的页面
      const login = useLogin();
      login.setLoginBack({ 
        url: '/addon/wj_books/pages/order/list',
        param: {}
      });
      
      // 跳转到登录页面
      redirect({ url: '/app/pages/auth/index', mode: 'redirectTo' });
    },
    
    // 加载订单列表
    async loadOrderList() {
      if (!this.isLogin) return;
      
      this.loading = true;
      
      try {
        // 显示加载中
        uni.showLoading({
          title: '加载中...'
        });
        
        // 获取订单列表
        const params = {
          page: this.page,
          page_size: this.pageSize
        };
        
        if (this.currentTab > 0) {
          params.status = this.tabs[this.currentTab].status;
        }
        
        console.log('获取订单列表，参数:', params);
        
        const res = await getOrderList(params);
        console.log('订单列表API返回:', res);
        
        if (res.code === 1) {
          // 如果是第一页，直接赋值；否则追加数据
          if (this.page === 1) {
            this.orderList = res.data.list || [];
          } else {
            this.orderList = [...this.orderList, ...(res.data.list || [])];
          }
          
          this.totalCount = res.data.count || 0;
          this.hasMore = this.orderList.length < this.totalCount;
          
          // 处理书籍封面图片
          this.orderList.forEach(order => {
            // 确保book_list字段存在且有值
            if (!order.book_list || !Array.isArray(order.book_list)) {
              order.books = [];
            } else {
              order.books = order.book_list;
            }
            
            // 处理书籍封面
            if (order.books && order.books.length > 0) {
              order.books.forEach(book => {
                if (!book.img) {
                  book.img = this.defaultCover;
                }
              });
            }
          });
        } else {
          uni.showToast({
            title: res.msg || '获取订单列表失败',
            icon: 'none'
          });
        }
      } catch (error) {
        console.error('获取订单列表错误', error);
        uni.showToast({
          title: '获取订单列表失败，请重试',
          icon: 'none'
        });
      } finally {
        this.loading = false;
        this.refreshing = false;
        uni.hideLoading();
      }
    },
    
    goBack() {
      uni.navigateBack();
    },
    
    goToHome() {
      uni.reLaunch({
        url: '/addon/wj_books/pages/home/index'
      });
    },
    
    changeTab(index) {
      this.currentTab = index;
      this.page = 1; // 切换标签时重置页码
      this.loadOrderList(); // 重新加载订单列表
    },
    
    onRefresh() {
      this.refreshing = true;
      this.page = 1; // 刷新时重置页码
      this.loadOrderList();
    },
    
    // 加载更多
    loadMore() {
      if (this.hasMore && !this.loading) {
        this.page++;
        this.loadOrderList();
      }
    },
    
    // 滚动到底部触发加载更多
    onReachBottom() {
      this.loadMore();
    },
    
    viewOrderDetail(order) {
      console.log('查看订单详情:', order);
      uni.navigateTo({
        url: `/addon/wj_books/pages/order/detail?id=${order.id}`
      });
    },
    
    cancelOrder(order) {
      uni.showModal({
        title: '取消订单',
        content: '确定要取消该订单吗？',
        confirmColor: '#4CAF50',
        success: (res) => {
          if (res.confirm) {
            // 调用取消订单API
            uni.showLoading({
              title: '取消中...'
            });
            
            getOrderList({ 
              id: order.id, 
              action: 'cancel',
              reason: '用户主动取消'
            }).then(res => {
              if (res.code === 1) {
                uni.showToast({
                  title: '订单已取消',
                  icon: 'success'
                });
                // 刷新订单列表
                this.page = 1;
                this.loadOrderList();
              } else {
                uni.showToast({
                  title: res.msg || '取消订单失败',
                  icon: 'none'
                });
              }
            }).catch(err => {
              console.error('取消订单错误', err);
              uni.showToast({
                title: '取消订单失败，请重试',
                icon: 'none'
              });
            }).finally(() => {
              uni.hideLoading();
            });
          }
        }
      });
    },
    getStatusText(status) {
      const statusMap = {
        1: '待上门',
        2: '已取件',
        3: '审核中',
        4: '已完成',
        5: '已取消'
      };
      return statusMap[status] || '未知状态';
    }
  },
  
  // 下拉刷新
  onPullDownRefresh() {
    this.page = 1;
    this.loadOrderList().then(() => {
      uni.stopPullDownRefresh();
    });
  },
  
  // 触底加载更多
  onReachBottom() {
    this.loadMore();
  }
}
</script>

<style lang="scss" scoped>
/* 全局设置 */
page {
  background-color: #f8f8f8;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

.order-list-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
}

/* 顶部背景区域 */
.order-header {
  height: 220rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  position: relative;
  padding-top: env(safe-area-inset-top);
  overflow: hidden;
}

.back-btn {
  position: absolute;
  left: 30rpx;
  top: calc(20rpx + env(safe-area-inset-top));
  width: 60rpx;
  height: 60rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10;
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
  margin-top: -40rpx;
  position: relative;
  z-index: 20;
  background-color: #f8f8f8;
  border-radius: 30rpx 30rpx 0 0;
  overflow: hidden;
  padding-bottom: env(safe-area-inset-bottom);
}

/* 订单状态标签 */
.order-tabs {
  display: flex;
  background-color: #FFFFFF;
  padding: 0 20rpx;
  height: 90rpx;
  border-radius: 30rpx 30rpx 0 0;
}

.order-tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  position: relative;
}

.order-tab text {
  font-size: 28rpx;
  color: #333333;
  transition: color 0.3s;
}

.active-tab text {
  color: #4CAF50;
  font-weight: 600;
}

.tab-indicator {
  width: 40rpx;
  height: 6rpx;
  background-color: #4CAF50;
  border-radius: 3rpx;
  position: absolute;
  bottom: 0;
}

/* 订单列表区域 */
.order-scroll {
  height: calc(100vh - 220rpx - 90rpx);
  background-color: #f8f8f8;
}

/* 未登录状态 */
.login-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx 0;
}

.empty-placeholder {
  width: 200rpx;
  height: 200rpx;
  background-color: #F5F5F5;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 30rpx;
}

.login-text {
  font-size: 28rpx;
  color: #999999;
  margin-bottom: 40rpx;
}

.login-btn {
  width: 240rpx;
  height: 80rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #FFFFFF;
  font-size: 28rpx;
  font-weight: 500;
  box-shadow: 0 4rpx 12rpx rgba(76, 175, 80, 0.2);
}

/* 空状态 */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx 0;
}

.empty-text {
  font-size: 28rpx;
  color: #999999;
  margin-bottom: 40rpx;
}

.empty-btn {
  width: 240rpx;
  height: 80rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #FFFFFF;
  font-size: 28rpx;
  font-weight: 500;
  box-shadow: 0 4rpx 12rpx rgba(76, 175, 80, 0.2);
}

/* 订单列表 */
.order-list {
  padding: 20rpx;
}

/* 加载状态样式 */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx 0;
}

.loading-spinner {
  width: 60rpx;
  height: 60rpx;
  border: 4rpx solid rgba(76, 175, 80, 0.3);
  border-radius: 50%;
  border-top-color: #4CAF50;
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

.order-item {
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.03);
}

.order-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #F5F5F5;
}

.order-number {
  font-size: 26rpx;
  color: #666666;
}

.order-status {
  font-size: 26rpx;
  font-weight: 600;
}

.status-1 {
  color: #FF9800; /* 待上门 */
}

.status-2 {
  color: #2196F3; /* 已取件 */
}

.status-3 {
  color: #9C27B0; /* 审核中 */
}

.status-4 {
  color: #4CAF50; /* 已完成 */
}

.status-5 {
  color: #9E9E9E; /* 已取消 */
}

.order-book-info {
  padding: 20rpx 0;
  display: flex;
  align-items: center;
  border-bottom: 1rpx solid #F5F5F5;
}

.book-covers {
  display: flex;
  align-items: center;
}

.book-cover {
  width: 100rpx;
  height: 140rpx;
  border-radius: 10rpx;
  overflow: hidden;
  margin-right: 10rpx;
  box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.1);
}

.book-cover:nth-child(2) {
  margin-left: -60rpx;
  z-index: 2;
}

.book-cover:nth-child(3) {
  margin-left: -60rpx;
  z-index: 1;
}

.book-cover image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.default-cover {
  width: 100%;
  height: 100%;
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

.more-books {
  width: 60rpx;
  height: 140rpx;
  background-color: #F5F5F5;
  border-radius: 0 10rpx 10rpx 0;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-left: -20rpx;
}

.more-books text {
  font-size: 24rpx;
  color: #666666;
}

.book-summary {
  flex: 1;
  margin-left: 20rpx;
  display: flex;
  flex-direction: column;
}

.book-count {
  font-size: 28rpx;
  color: #333333;
  margin-bottom: 10rpx;
}

.book-price {
  font-size: 32rpx;
  color: #FF6B6B;
  font-weight: 600;
}

.order-footer-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 20rpx;
}

.order-time {
  font-size: 24rpx;
  color: #999999;
}

.order-actions {
  display: flex;
}

.order-action-btn {
  height: 60rpx;
  padding: 0 24rpx;
  border-radius: 30rpx;
  border: 1rpx solid #DDDDDD;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-left: 20rpx;
}

.order-action-btn text {
  font-size: 24rpx;
  color: #666666;
}

.primary-btn {
  border: 1rpx solid #4CAF50;
  background-color: #F0F9EB;
}

.primary-btn text {
  color: #4CAF50;
  font-weight: 500;
}
</style> 