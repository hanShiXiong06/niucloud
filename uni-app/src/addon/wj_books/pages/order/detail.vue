<template>
  <view class="detail-container">
    <!-- 顶部背景 -->
    <view class="detail-header">
      <text class="page-title">订单详情</text>
      <view class="header-decoration"></view>
    </view>

    <!-- 加载中状态 -->
    <view class="loading-container" v-if="loading">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载订单信息中...</text>
    </view>

    <!-- 主内容区 -->
    <view class="detail-content" v-if="!loading">
      <!-- 订单状态卡片 -->
      <view class="content-card status-card">
        <view class="status-icon" :class="'status-' + orderInfo.status">
          <u-icon :name="getStatusIcon(orderInfo.status)" color="#FFFFFF" size="50rpx"></u-icon>
        </view>
        <view class="status-info">
          <text class="status-text">{{ getStatusText(orderInfo.status) }}</text>
          <text class="status-desc">{{ getStatusDesc(orderInfo.status) }}</text>
        </view>
      </view>

      <!-- 快递员信息卡片（仅在已取件状态显示） -->
      <view class="content-card courier-card" v-if="(orderInfo.status === 1 || orderInfo.status === 2) && orderInfo.courier">
        <view class="card-title">
          <u-icon name="account-fill" color="#4CAF50" size="32rpx"></u-icon>
          <text>快递员信息</text>
        </view>
        
        <view class="courier-info" v-if="orderInfo.courier">
          <view class="courier-avatar">
            <image :src="getExpressLogo(orderInfo.express_channel_id)" mode="aspectFill"></image>
          </view>
          <view class="courier-detail">
            <view class="courier-row">
              <text class="courier-name">{{ orderInfo.courier.name }}</text>
            </view>
            <text class="courier-phone">{{ orderInfo.courier.mobile }}</text>
            <view class="contact-actions">
              <view class="contact-btn" @click="contactCourier">
                <u-icon name="phone" color="#4CAF50" size="28rpx"></u-icon>
                <text>拨打电话</text>
              </view>
            </view>
          </view>
        </view>
      </view>
      
      <!-- 审核进度卡片（仅在审核中状态显示） -->
      <view class="content-card audit-card" v-if="orderInfo.status === 3">
        <view class="card-title">
          <u-icon name="checkmark-circle" color="#4CAF50" size="32rpx"></u-icon>
          <text>审核进度</text>
        </view>
        
        <view class="audit-progress">
          <view class="audit-tips">
            <text>提示：审核通常在48h内完成，请耐心等待</text>
          </view>
        </view>
      </view>
      
      <!-- 回收结果卡片（仅在已完成状态显示） -->
      <view class="content-card result-card" v-if="orderInfo.status === 4">
        <view class="card-title">
          <u-icon name="checkmark-circle-fill" color="#4CAF50" size="32rpx"></u-icon>
          <text>回收结果</text>
        </view>
        
        <view class="result-info">
          <view class="result-item">
            <text class="result-label">最终回收价</text>
            <text class="result-value final-price">¥{{ orderInfo.final_amount || orderInfo.total_amount }}</text>
          </view>
          <view class="result-item">
            <text class="result-label">实际回收书籍</text>
            <text class="result-value">{{ orderInfo.final_book_count || orderInfo.book_count }}本</text>
          </view>
          <view class="result-item">
            <text class="result-label">结算时间</text>
            <text class="result-value">{{ orderInfo.complete_time }}</text>
          </view>
          <view class="result-item" v-if="orderInfo.rejected_books && orderInfo.rejected_books.length > 0">
            <text class="result-label">不符合回收条件</text>
            <text class="result-value rejected-count">{{ orderInfo.rejected_books.length }}本</text>
          </view>
        </view>
        
        <view class="result-message" v-if="orderInfo.completion_message">
          <text class="message-title">回收说明</text>
          <text class="message-content">{{ orderInfo.completion_message }}</text>
        </view>
        
        <!-- 不符合回收条件的书籍详情 -->
        <view class="rejected-books" v-if="orderInfo.rejected_books && orderInfo.rejected_books.length > 0">
          <view class="rejected-title">不符合回收条件的书籍</view>
          <view class="rejected-book-item" v-for="(book, index) in orderInfo.rejected_books" :key="index">
            <view class="rejected-book-info">
              <text class="rejected-book-title">{{ book.title }}</text>
              <text class="rejected-book-reason">原因：{{ formatRejectReason(book.reason) }}</text>
              <text class="rejected-book-quantity">数量：{{ book.rejected_quantity }}本</text>
            </view>
            <view class="rejected-book-cover">
              <image :src="book.img ? img(book.img) : img('addon/wj_books/express/default_book.png')" mode="aspectFill"></image>
            </view>
          </view>
          
          <!-- 拒收图片展示区域 -->
          <view class="rejected-images-section" v-if="hasRejectedImages">
            <view class="rejected-images-title">拒收原因图片</view>
            <scroll-view scroll-x class="rejected-images-scroll">
              <view class="rejected-images-container">
                <view class="rejected-image-item" 
                      v-for="(image, imgIndex) in allRejectedImages" 
                :key="imgIndex" 
                      @click="previewRejectedImage(allRejectedImages, imgIndex)">
                  <image :src="img(image)" mode="aspectFill"></image>
            </view>
              </view>
            </scroll-view>
          </view>
          
          <!-- 取回申请部分 -->
          <view class="retrieve-section" v-if="canRetrieve && !isExpired">
            <view class="retrieve-info">
              <view class="retrieve-countdown">
                <u-icon name="clock" color="#FF9800" size="28rpx"></u-icon>
                <text>剩余申请取回时间：{{ retrieveCountdown }}</text>
              </view>
              <text class="retrieve-tip">超时后无法申请取回，书籍将由平台处理</text>
            </view>
            <view class="retrieve-btn" @click="showRetrieveModal">申请取回</view>
          </view>
          <view class="retrieve-section" v-else-if="orderInfo.rejected_books && orderInfo.rejected_books.length > 0">
            <view class="retrieve-info">
              <text class="retrieve-tip">
                {{ hasApplied ? '已申请取回，详细进度查询请联系客服。' : '已超过申请期限，无法申请取回' }}
              </text>
            </view>
            <view class="retrieve-btn disabled" @click="showRetrieveStatusInfo">
              {{ hasApplied ? '查看进度' : '无法取回' }}
            </view>
          </view>
        </view>
      </view>

      <!-- 回收流程卡片 -->
      <view class="content-card">
        <view class="card-title">
          <u-icon name="calendar" color="#4CAF50" size="32rpx"></u-icon>
          <text>回收进度</text>
        </view>
        
        <view class="process-steps">
          <view class="process-step" :class="{'process-active': orderInfo.status >= 1, 'process-current': orderInfo.status === 1}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">提交订单</text>
              <text class="step-time">{{ orderInfo.create_time }}</text>
            </view>
          </view>
          <view class="process-step" :class="{'process-active': orderInfo.status >= 1, 'process-current': orderInfo.status === 1}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">等待上门</text>
              <text class="step-time">预计{{ orderInfo.pickup_time }}</text>
            </view>
          </view>
          <view class="process-step" :class="{'process-active': orderInfo.status >= 2, 'process-current': orderInfo.status === 2}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">已取件</text>
              <text class="step-time">{{ orderInfo.status >= 2 ? orderInfo.pickup_actual_time : '待取件' }}</text>
            </view>
          </view>
          <view class="process-step" :class="{'process-active': orderInfo.status >= 3, 'process-current': orderInfo.status === 3}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">平台审核</text>
              <text class="step-time">{{ orderInfo.status >= 3 ? '审核中' : '待审核' }}</text>
            </view>
          </view>
          <view class="process-step" :class="{'process-active': orderInfo.status >= 4, 'process-current': orderInfo.status === 4}">
            <view class="step-dot"></view>
            <view class="step-content">
              <text class="step-title">回收完成</text>
              <text class="step-time">{{ orderInfo.status >= 4 ? orderInfo.complete_time : '待完成' }}</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 回收详情 -->
      <view class="content-card">
        <view class="card-title">
          <u-icon name="file-text" color="#4CAF50" size="32rpx"></u-icon>
          <text>回收详情</text>
        </view>
        
        <view class="order-info-list">
          <view class="info-item">
            <text class="info-label">订单编号</text>
            <text class="info-value">{{ orderInfo.order_no }}</text>
            <view class="copy-btn" @click="copyOrderNo">复制</view>
          </view>
          <view class="info-item">
            <text class="info-label">提交时间</text>
            <text class="info-value">{{ orderInfo.create_time }}</text>
          </view>
          <view class="info-item">
            <text class="info-label">预约上门</text>
            <text class="info-value">{{ orderInfo.pickup_time }}</text>
          </view>
          <view class="info-item" v-if="orderInfo.courier">
            <text class="info-label">快递员信息</text>
            <text class="info-value">{{ orderInfo.courier.name }} {{ orderInfo.courier.mobile }}</text>
          </view>
          <view class="info-item">
            <text class="info-label">书籍数量</text>
            <text class="info-value">{{ orderInfo.book_count }}本</text>
          </view>
          <view class="info-item">
            <text class="info-label">预估金额</text>
            <text class="info-value price-highlight">¥{{ orderInfo.total_amount }}</text>
          </view>
          <view class="info-item" v-if="orderInfo.express_waybill">
            <text class="info-label">物流单号</text>
            <text class="info-value">{{ orderInfo.express_waybill }}</text>
            <view class="copy-btn" @click="copyWaybill">复制</view>
          </view>
          <view class="info-item" v-if="orderInfo.remark">
            <text class="info-label">备注信息</text>
            <text class="info-value">{{ orderInfo.remark }}</text>
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
            <text class="contact-name">{{ address.name }}</text>
            <text class="contact-phone">{{ address.mobile }}</text>
          </view>
          <view class="address-detail">
            <text>{{ address.address }}</text>
          </view>
        </view>
      </view>

      <!-- 书籍列表 -->
      <view class="content-card">
        <view class="card-title">
          <u-icon name="bookmark" color="#4CAF50" size="32rpx"></u-icon>
          <text>书籍清单</text>
          <text class="book-count">共{{ orderInfo.book_count }}本</text>
        </view>
        
        <view class="book-list">
          <view class="book-item" v-for="(book, index) in orderInfo.books" :key="index">
            <view class="book-cover">
              <image :src="book.img ? img(book.img) : img('addon/wj_books/express/default_book.png')" mode="aspectFill"></image>
            </view>
            <view class="book-info">
              <text class="book-title">{{ book.title || '未知书名' }}</text>
              <text class="book-author">{{ book.author || '未知作者' }}</text>
              <view class="book-quantity">
                <text>数量：{{ book.quantity }}本</text>
              </view>
              <view class="book-price">
                <text>预估回收价</text>
                <text class="price-value">¥{{ (book.price * book.quantity).toFixed(2) }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部按钮区域 -->
    <view class="footer-btns safe-area-inset-bottom" v-if="orderInfo.status !== 5">
      <!-- 根据不同状态显示不同按钮 -->
      <block v-if="orderInfo.status === 1">
        <view class="footer-btn secondary-btn" @click="cancelOrder">取消订单</view>
        <!-- #ifdef MP-WEIXIN -->
        <button open-type="contact" class="footer-btn primary-btn wx-contact-btn">联系客服</button>
        <!-- #endif -->
        <!-- #ifndef MP-WEIXIN -->
        <view class="footer-btn primary-btn" @click="contactService">联系客服</view>
        <!-- #endif -->
      </block>
      <block v-if="orderInfo.status === 2">
        <view class="footer-btn secondary-btn" @click="goToHome">返回首页</view>
        <view class="footer-btn primary-btn" @click="trackDelivery">查看物流</view>
      </block>
      <block v-if="orderInfo.status === 3">
        <!-- #ifdef MP-WEIXIN -->
        <button open-type="contact" class="footer-btn secondary-btn wx-contact-btn">联系客服</button>
        <!-- #endif -->
        <!-- #ifndef MP-WEIXIN -->
        <view class="footer-btn secondary-btn" @click="contactService">联系客服</view>
        <!-- #endif -->
        <view class="footer-btn primary-btn" @click="checkAuditProgress">查看审核进度</view>
      </block>
      <block v-if="orderInfo.status === 4">
        <view class="footer-btn secondary-btn" @click="deleteOrder">删除订单</view>
        <!-- #ifdef MP-WEIXIN -->
        <button open-type="contact" class="footer-btn primary-btn wx-contact-btn">联系客服</button>
        <!-- #endif -->
        <!-- #ifndef MP-WEIXIN -->
        <view class="footer-btn primary-btn" @click="contactService">联系客服</view>
        <!-- #endif -->
      </block>
    </view>
    
    <!-- 取消订单后的底部按钮 -->
    <view class="footer-btns safe-area-inset-bottom" v-if="orderInfo.status === 5">
      <view class="footer-btn primary-btn full-width" @click="goToHome">返回首页</view>
    </view>

    <!-- 物流信息弹窗 -->
    <view class="express-popup" v-if="showExpressPopup">
      <view class="express-popup-mask" @click="closeExpressPopup"></view>
      <view class="express-popup-content">
        <!-- 弹窗头部 -->
        <view class="express-popup-header">
          <text class="express-popup-title">物流信息</text>
          <view class="express-popup-close" @click="closeExpressPopup">
            <u-icon name="close" color="#FFFFFF" size="30rpx"></u-icon>
          </view>
        </view>
        
        <!-- 快递信息 -->
        <view class="express-info">
          <view class="express-info-item">
            <text class="express-info-label">运单号</text>
            <text class="express-info-value">{{ orderInfo.express_waybill }}</text>
            <view class="express-copy-btn" @click="copyWaybill">复制</view>
          </view>
          <view class="express-info-item">
            <text class="express-info-label">快递公司</text>
            <text class="express-info-value">{{ orderInfo.express_channel_id || orderInfo.express_channel || '未知' }}</text>
          </view>
          <view class="express-info-item">
            <text class="express-info-label">物流状态</text>
            <text class="express-info-value status">{{ expressData.status || '查询中' }}</text>
          </view>
        </view>
        
        <!-- 物流轨迹 -->
        <view class="express-traces">
          <view class="express-traces-title">物流轨迹</view>
          <scroll-view scroll-y class="express-traces-scroll">
            <view class="express-traces-list" v-if="expressData.traces && expressData.traces.length > 0">
              <view class="express-trace-item" v-for="(trace, index) in expressData.traces" :key="index" :class="{'express-trace-item-first': index === 0}">
                <view class="express-trace-dot"></view>
                <view class="express-trace-line" v-if="index < expressData.traces.length - 1"></view>
                <view class="express-trace-content">
                  <text class="express-trace-status">{{ trace.status }}</text>
                  <text class="express-trace-time">{{ trace.time }}</text>
                </view>
              </view>
            </view>
            <view class="express-no-traces" v-else>
              <text>暂无物流轨迹信息</text>
            </view>
          </scroll-view>
        </view>
      </view>
    </view>

    <!-- 取回申请弹窗 -->
    <view class="retrieve-modal" v-if="showRetrievePopup">
      <view class="retrieve-modal-mask" @click="closeRetrieveModal"></view>
      <view class="retrieve-modal-content">
        <view class="retrieve-modal-header">
          <text class="retrieve-modal-title">申请取回书籍</text>
          <view class="retrieve-modal-close" @click="closeRetrieveModal">
            <u-icon name="close" color="#333333" size="30rpx"></u-icon>
          </view>
        </view>
        
        <view class="retrieve-modal-body">
          <view class="retrieve-modal-tips">
            <text>请确认以下信息：</text>
            <text class="retrieve-modal-subtitle">1. 取回书籍将以到付方式寄回，运费由您承担</text>
            <text class="retrieve-modal-subtitle">2. 取回申请提交后，客服将在24小时内处理</text>
          </view>
          
          <view class="retrieve-modal-books">
            <view class="retrieve-modal-section-title">待取回书籍（全部退回）</view>
            <view class="retrieve-modal-book-count">共{{ orderInfo.rejected_books.filter(book => book.retrieve_status !== 1).length }}本可取回书籍</view>
            <view class="retrieve-modal-book-item" v-for="(book, index) in orderInfo.rejected_books" :key="index">
              <view class="retrieve-modal-book-info">
                <text class="retrieve-modal-book-title">{{ book.title }}</text>
                <text class="retrieve-modal-book-quantity">{{ book.rejected_quantity }}本</text>
                <text class="retrieve-modal-book-status" v-if="book.retrieve_status === 1">(已申请)</text>
              </view>
            </view>
          </view>
          
          <view class="retrieve-modal-address">
            <view class="retrieve-modal-section-title">原路退回（取件地址）</view>
            <view class="retrieve-modal-address-info">
              <view class="retrieve-modal-address-contact">
                <text>{{ address.name }}</text>
                <text>{{ address.mobile }}</text>
              </view>
              <view class="retrieve-modal-address-detail">
                {{ address.address }}
              </view>
            </view>
            <view class="retrieve-address-tip">取回书籍将原路退回至取件地址</view>
          </view>
          
          <view class="retrieve-modal-remark">
            <view class="retrieve-modal-section-title">备注信息</view>
            <textarea class="retrieve-modal-textarea" v-model="retrieveRemark" placeholder="可选填，如有特殊要求请在此说明" />
          </view>
        </view>
        
        <view class="retrieve-modal-footer">
          <view class="retrieve-modal-btn cancel" @click="closeRetrieveModal">取消</view>
          <view class="retrieve-modal-btn confirm" @click="submitRetrieveApply">确认申请</view>
        </view>
      </view>
    </view>

    <!-- 添加联系客服提示弹窗 -->
    <view class="service-dialog" v-if="showServiceDialog">
      <view class="service-dialog-mask" @click="closeServiceDialog"></view>
      <view class="service-dialog-content">
        <view class="service-dialog-title">提示</view>
        <view class="service-dialog-message">请前往微信小程序使用联系客服功能</view>
        <view class="service-dialog-footer">
          <view class="service-dialog-btn" @click="closeServiceDialog">知道了</view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import { getOrderDetail, getExpressInfo, applyRetrieve } from '@/addon/wj_books/api/wj_books_order'
import { getAddressList } from '@/app/api/member'
import { img } from '@/utils/common'

export default {
  data() {
    return {
      orderId: null,
      loading: true,
      // 取回倒计时
      retrieveDeadline: null,
      retrieveCountdownTimer: null,
      retrieveCountdown: '',
      canRetrieve: false,
      hasApplied: false,
      isExpired: false,
      // 订单信息
      orderInfo: {
        id: 0,
        order_no: '',
        create_time: '',
        pickup_time: '',
        pickup_actual_time: '',
        complete_time: '',
        book_count: 0,
        total_amount: '0.00',
        final_amount: '0.00',
        final_book_count: 0,
        status: 1, // 1: 待上门, 2: 已取件, 3: 审核中, 4: 已完成, 5: 已取消
        remark: '',
        rejected_books: [],
        completion_message: '',
        courier: null,
        books: [],
        express_waybill: '',
        express_courier_name: '',
        express_courier_phone: '',
        address_id: 0
      },
      address: {
        name: '',
        mobile: '',
        address: ''
      },
      showExpressPopup: false,
      expressData: {
        status: '',
        traces: []
      },
      // 取回申请相关
      showRetrievePopup: false,
      selectedBooks: [],
      selectedAddress: null,
      retrieveRemark: '',
      addressList: [],
      // 拒收图片相关
      allRejectedImages: [],
      hasRejectedImages: false,
      // 客服相关
      showServiceDialog: false
    }
  },
  computed: {
    // 格式化倒计时
    formatCountdown() {
      const hours = Math.floor(this.countdownTime / 3600);
      const minutes = Math.floor((this.countdownTime % 3600) / 60);
      const seconds = this.countdownTime % 60;
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
  },
  onLoad(options) {
    console.log('订单详情页接收参数:', options);
    
    if (options && (options.id || options.order_id)) {
      this.orderId = parseInt(options.id || options.order_id) || (options.id || options.order_id);
      console.log('准备加载订单ID:', this.orderId);
      this.loadOrderDetail();
    } else {
      console.error('未接收到订单ID参数');
      uni.showToast({
        title: '订单ID不存在',
        icon: 'none'
      });
      setTimeout(() => {
        uni.navigateBack();
      }, 1500);
    }
  },
  methods: {
    img,
    // 格式化拒收原因
    formatRejectReason(reason) {
      const reasonMap = {
        'damaged': '破损',
        'not_match': '信息不符',
        'too_old': '过旧',
        'other': '其他'
      };
      return reasonMap[reason] || reason;
    },
    // 加载订单详情
    async loadOrderDetail() {
      if (!this.orderId) {
        console.error('订单ID为空，无法加载订单详情');
        uni.showToast({
          title: '订单ID不存在',
          icon: 'none'
        });
        setTimeout(() => {
          uni.navigateBack();
        }, 1500);
        return;
      }
      
      this.loading = true;
      
      try {
        // 显示加载中
        uni.showLoading({
          title: '加载中...'
        });
        
        console.log('开始请求订单详情, ID:', this.orderId);
        
        // 获取订单详情
        const res = await getOrderDetail({ id: this.orderId });
        console.log('订单详情API返回:', res);
        
        if (res.code === 1 && res.data) {
          console.log('获取到订单数据:', res.data);
          
          // 处理返回的订单数据
          this.orderInfo = res.data.order_info;
          this.orderInfo.books = res.data.book_list;
          this.orderInfo.rejected_books = res.data.rejected_books || [];
          
          // 处理拒收书籍的状态 - 简化逻辑，只看API返回的can_retrieve值
          if (this.orderInfo.rejected_books && this.orderInfo.rejected_books.length > 0) {
            const orderCanRetrieve = res.data.can_retrieve === true;
            
            console.log('订单可取回状态:', orderCanRetrieve);
            
            // 根据订单级别的can_retrieve值统一设置所有书籍的状态
            this.orderInfo.rejected_books.forEach(book => {
              // 如果订单可取回，则所有书籍都可取回
              book.can_retrieve = orderCanRetrieve ? 1 : 0;
              
              // 确保retrieve_status属性存在，默认为0
              if (book.retrieve_status === undefined) {
                book.retrieve_status = 0;
              }
            });
          }
          
          // 收集所有拒收图片
          this.allRejectedImages = [];
          this.hasRejectedImages = false;
          
          if (this.orderInfo.rejected_books && this.orderInfo.rejected_books.length > 0) {
            this.orderInfo.rejected_books.forEach(book => {
              if (book.audit_images && book.audit_images.length > 0) {
                this.hasRejectedImages = true;
                book.audit_images.forEach(img => {
                  this.allRejectedImages.push(img);
                });
              }
            });
          }
          
          // 设置取回相关状态
          // 保存取回截止时间
          if (res.data.retrieve_deadline) {
            this.retrieveDeadline = new Date(res.data.retrieve_deadline);
          }
          
          // 1. 首先判断API返回的can_retrieve值
          this.canRetrieve = res.data.can_retrieve === true;
          
          // 2. 判断是否已申请
          this.hasApplied = !this.canRetrieve;
          
          // 3. 判断是否已超过申请期限
          if (this.retrieveDeadline) {
            const nowTime = new Date().getTime();
            this.isExpired = nowTime > this.retrieveDeadline.getTime();
          } else {
            this.isExpired = false;
          }
          
                    console.log('取回状态信息 - 可取回:', this.canRetrieve, 
                       '已申请:', this.hasApplied, 
                       '已超期:', this.isExpired,
                       '期限:', res.data.retrieve_deadline);
          
          // 如果是已完成状态且有不合格书籍且可以申请取回且未超期，计算取回期限
          if (this.orderInfo.status === 4 && 
              this.orderInfo.rejected_books && 
              this.orderInfo.rejected_books.length > 0 && 
              res.data.retrieve_deadline && 
              this.canRetrieve && 
              !this.isExpired) {
            this.retrieveDeadline = new Date(res.data.retrieve_deadline);
            this.startRetrieveCountdown();
          }
          
          // 构建快递员信息
          if (this.orderInfo.express_courier_name || this.orderInfo.express_courier_phone) {
            this.orderInfo.courier = {
              name: this.orderInfo.express_courier_name || '快递员',
              mobile: this.maskPhoneNumber(this.orderInfo.express_courier_phone || '')
            };
          }
          
          // 加载地址信息
          if (res.data.address_info) {
            this.address = {
              name: res.data.address_info.name,
              mobile: this.maskPhoneNumber(res.data.address_info.mobile),
              address: res.data.address_info.full_address
            };
          } else {
            await this.loadAddressInfo(this.orderInfo.address_id);
          }
        } else {
          console.error('API返回错误:', res);
          uni.showToast({
            title: res.msg || '获取订单信息失败',
            icon: 'none'
          });
          
          // 如果是订单不存在的错误，返回上一页
          if (res.msg && (res.msg.includes('不存在') || res.msg.includes('未找到'))) {
            setTimeout(() => {
              uni.navigateBack();
            }, 1500);
          }
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
      if (!addressId) return;
      
      try {
        const res = await getAddressList({});
        
        if (res.code === 1 && res.data && res.data.length > 0) {
          // 查找对应的地址
          const matchedAddress = res.data.find(item => item.id === addressId);
          
          if (matchedAddress) {
            this.address = {
              name: matchedAddress.name,
              mobile: this.maskPhoneNumber(matchedAddress.mobile),
              address: matchedAddress.full_address
            };
          }
        }
      } catch (error) {
        console.error('获取地址信息错误', error);
      }
    },
    
    // 手机号码脱敏
    maskPhoneNumber(phone) {
      if (!phone || phone.length < 11) return phone;
      return phone.substring(0, 3) + '****' + phone.substring(7);
    },
    
    // 开始取回倒计时
    startRetrieveCountdown() {
      this.retrieveCountdownTimer = setInterval(() => {
        const now = new Date();
        const diff = this.retrieveDeadline - now;
        
        if (diff <= 0) {
          clearInterval(this.retrieveCountdownTimer);
          this.canRetrieve = false;
          this.retrieveCountdown = '00:00:00';
          return;
        }
        
        // 计算剩余时间
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        // 如果有天数，显示天数
        if (days > 0) {
          this.retrieveCountdown = `${days}天${hours.toString().padStart(2, '0')}小时`;
        } else if (hours > 0) {
          // 如果只有小时，显示小时和分钟
          this.retrieveCountdown = `${hours}小时${minutes.toString().padStart(2, '0')}分钟`;
        } else {
          // 如果只有分钟和秒，显示分钟和秒
          this.retrieveCountdown = `${minutes}分钟${seconds.toString().padStart(2, '0')}秒`;
        }
      }, 1000);
    },
    
    // 显示取回申请弹窗
    showRetrieveModal() {
      // 检查是否已申请
      if (this.hasApplied) {
        this.showRetrieveStatusInfo();
        return;
      }
      
      // 检查是否已过期
      if (this.isExpired) {
        uni.showModal({
          title: '无法申请取回',
          content: '已超过申请期限，无法申请取回书籍。根据平台规则，超过申请期限的不合格书籍将由平台统一处理。',
          showCancel: false,
          confirmText: '我知道了',
          confirmColor: '#4CAF50'
        });
        return;
      }
      
      // 检查是否可以申请
      if (!this.canRetrieve) {
        uni.showToast({
          title: '当前无法申请取回',
          icon: 'none'
        });
        return;
      }
      
      // 检查是否有拒收的书籍
      if (!this.orderInfo.rejected_books || this.orderInfo.rejected_books.length === 0) {
        uni.showToast({
          title: '没有可取回的书籍',
          icon: 'none'
        });
        return;
      }
      
      // 显示弹窗
      this.showRetrievePopup = true;
    },
    
    // 关闭取回申请弹窗
    closeRetrieveModal() {
      this.showRetrievePopup = false;
      this.retrieveRemark = '';
    },
    
    // 切换书籍选择状态
    toggleBookSelection(bookId, event) {
      const checked = event.detail.value.length > 0;
      
      if (checked) {
        if (!this.selectedBooks.includes(bookId)) {
          this.selectedBooks.push(bookId);
        }
      } else {
        const index = this.selectedBooks.indexOf(bookId);
        if (index !== -1) {
          this.selectedBooks.splice(index, 1);
        }
      }
    },
    
    // 加载地址列表
    async loadAddressList() {
      try {
        const res = await getAddressList({});
        
        if (res.code === 1 && res.data) {
          this.addressList = res.data;
          
          // 如果有默认地址，选择默认地址
          const defaultAddress = this.addressList.find(item => item.is_default === 1);
          if (defaultAddress) {
            this.selectedAddress = defaultAddress;
          } else if (this.addressList.length > 0) {
            // 否则选择第一个地址
            this.selectedAddress = this.addressList[0];
          }
        }
      } catch (error) {
        console.error('获取地址列表错误', error);
        uni.showToast({
          title: '获取地址列表失败',
          icon: 'none'
        });
      }
    },
    
    // 选择地址
    selectAddress() {
      uni.navigateTo({
        url: '/pages/member/address/list?is_select=1',
        events: {
          // 监听选择地址页面返回的数据
          selectAddress: (address) => {
            this.selectedAddress = address;
          }
        }
      });
    },
    
    // 提交取回申请
    submitRetrieveApply() {
      // 不再根据can_retrieve属性过滤，而是直接选择所有拒收的书籍
      if (!this.orderInfo.rejected_books || this.orderInfo.rejected_books.length === 0) {
        uni.showToast({
          title: '没有可取回的书籍',
          icon: 'none'
        });
        return;
      }
      
      // 显示加载中
      uni.showLoading({
        title: '提交中...'
      });
      
      // 选择所有未申请过的拒收书籍
      const booksToRetrieve = this.orderInfo.rejected_books.filter(book => book.retrieve_status !== 1);
      const bookIds = booksToRetrieve.map(book => book.id);
      
      console.log('选择取回的书籍:', booksToRetrieve);
      console.log('选择取回的书籍ID:', bookIds);
      
      if (bookIds.length === 0) {
        uni.hideLoading();
        uni.showToast({
          title: '所有书籍已申请取回',
          icon: 'none'
        });
        this.closeRetrieveModal();
        return;
      }
      
      // 构建请求参数（使用原有取件地址）
      const params = {
        order_id: this.orderId,
        book_ids: bookIds,
        address_id: this.orderInfo.address_id, // 使用原有地址ID
        remark: this.retrieveRemark
      };
      
      console.log('提交取回申请参数:', params);
      
      // 调用API提交申请
      applyRetrieve(params).then(res => {
        uni.hideLoading();
        console.log('取回申请API返回:', res);
        
        if (res.code === 1) {
          uni.showToast({
            title: '申请已提交',
            icon: 'success'
          });
          
          // 关闭弹窗
          this.closeRetrieveModal();
          
          // 立即更新本地状态
          this.hasApplied = true;
          this.canRetrieve = false;
          this.orderInfo.retrieve_applied = 1;
          
          // 刷新订单详情
          setTimeout(() => {
            this.loadOrderDetail();
          }, 1500);
        } else {
          uni.showToast({
            title: res.msg || '申请提交失败',
            icon: 'none'
          });
        }
      }).catch(err => {
        console.error('取回申请错误:', err);
        uni.hideLoading();
        uni.showToast({
          title: err.msg || '申请提交失败',
          icon: 'none'
        });
      });
    },
    getStatusIcon(status) {
      const statusIcons = {
        1: 'clock-fill',
        2: 'car-fill',
        3: 'checkmark-circle',
        4: 'checkmark-circle-fill',
        5: 'close-circle-fill'
      };
      return statusIcons[status] || 'info-circle-fill';
    },
    getStatusText(status) {
      const statusTexts = {
        1: '等待上门',
        2: '快递员已取件',
        3: '平台审核中',
        4: '回收完成',
        5: '订单已取消'
      };
      return statusTexts[status] || '未知状态';
    },
    getStatusDesc(status) {
      const statusDescs = {
        1: this.orderInfo.courier ? '快递员已分配，等待揽件' : '快递已下单，正在分配快递员',
        2: '快递员已取件，正在运往平台',
        3: '平台正在审核您的书籍，请耐心等待',
        4: '回收已完成，感谢您的支持',
        5: '订单已取消，您可以重新提交'
      };
      return statusDescs[status] || '';
    },
    copyOrderNo() {
      uni.setClipboardData({
        data: this.orderInfo.order_no,
        success: () => {
          uni.showToast({
            title: '订单号已复制',
            icon: 'none'
          });
        }
      });
    },
    
    // 复制物流单号
    copyWaybill() {
      if (this.orderInfo.express_waybill) {
        uni.setClipboardData({
          data: this.orderInfo.express_waybill,
          success: () => {
            uni.showToast({
              title: '物流单号已复制',
              icon: 'none'
            });
          }
        });
      }
    },
    // 取消订单
    cancelOrder() {
      // #ifdef H5
      // 添加自定义样式
      let pageStyle = document.createElement('style');
      pageStyle.innerText = '.uni-modal__btn.uni-modal__btn_primary { color: #4CAF50 !important; }';
      document.head.appendChild(pageStyle);
      // #endif
      
      uni.showModal({
        title: '取消订单',
        content: '确定要取消该订单吗？',
        confirmColor: '#4CAF50',
        success: (res) => {
          if (res.confirm) {
            // #ifdef H5
            // 清理添加的样式
            document.head.removeChild(pageStyle);
            // #endif
            
            // 调用取消订单API
            uni.showLoading({
              title: '取消中...'
            });
            
            getOrderDetail({ 
              id: this.orderId, 
              action: 'cancel',
              reason: '用户主动取消'
            }).then(res => {
              if (res.code === 1) {
            uni.showToast({
              title: '订单已取消',
                  icon: 'success'
                });
                // 刷新订单详情
                setTimeout(() => {
                  this.loadOrderDetail();
                }, 1500);
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
    contactCourier() {
      const phone = this.orderInfo.express_courier_phone || (this.orderInfo.courier && this.orderInfo.courier.mobile);
      
      if (phone) {
        const cleanPhone = phone.replace(/\*/g, ''); // 去掉可能的脱敏星号
        
        // 直接拨打电话
              uni.makePhoneCall({
          phoneNumber: cleanPhone,
                fail: () => {
                  uni.showToast({
                    title: '拨打电话失败',
                    icon: 'none'
                  });
                }
              });
      } else {
              uni.showToast({
          title: '暂无快递员信息',
                icon: 'none'
              });
      }
    },
    
    // 查看物流信息
    trackDelivery() {
      if (this.orderInfo.express_waybill) {
        // 显示加载中
        uni.showLoading({
          title: '获取物流信息...'
        });
        
        // 调用接口获取最新物流信息
        getExpressInfo({
          waybill: this.orderInfo.express_waybill,
          channel: this.orderInfo.express_channel
        }).then(res => {
          if (res.code === 1) {
            // 获取成功，显示物流详情
            this.expressData = res.data;
            // 显示物流弹窗
            this.showExpressPopup = true;
          } else {
            // 获取失败，显示错误信息
            uni.showModal({
              title: '物流信息',
              content: `运单号：${this.orderInfo.express_waybill}\n` +
                       `快递公司：${this.orderInfo.express_channel_id || this.orderInfo.express_channel || '未知'}\n\n` +
                       `获取物流信息失败：${res.msg || '未知错误'}`,
              confirmText: '复制单号',
              success: (res) => {
                if (res.confirm) {
                  this.copyWaybill();
                }
              }
            });
          }
        }).catch(err => {
          console.error('获取物流信息错误', err);
          // 获取失败，显示基本信息
          uni.showModal({
            title: '物流信息',
            content: `运单号：${this.orderInfo.express_waybill}\n` +
                     `快递公司：${this.orderInfo.express_channel_id || this.orderInfo.express_channel || '未知'}\n\n` +
                     `获取物流信息失败，请稍后重试`,
            confirmText: '复制单号',
            success: (res) => {
              if (res.confirm) {
                this.copyWaybill();
              }
            }
          });
        }).finally(() => {
          uni.hideLoading();
        });
      } else {
        uni.showToast({
          title: '暂无物流信息',
          icon: 'none'
        });
      }
    },
    checkAuditProgress() {
      uni.showToast({
        title: '正在审核中，请耐心等待',
        icon: 'none'
      });
    },
    // 删除订单
    deleteOrder() {
      // #ifdef H5
      // 添加自定义样式
      let pageStyle = document.createElement('style');
      pageStyle.innerText = '.uni-modal__btn.uni-modal__btn_primary { color: #4CAF50 !important; }';
      document.head.appendChild(pageStyle);
      // #endif
      
      uni.showModal({
        title: '删除订单',
        content: '确定要删除该订单吗？删除后将无法恢复',
        confirmColor: '#4CAF50',
        success: (res) => {
          if (res.confirm) {
            // #ifdef H5
            // 清理添加的样式
            document.head.removeChild(pageStyle);
            // #endif
            
            // 调用删除订单API
            uni.showLoading({
              title: '删除中...'
            });
            
            getOrderDetail({ 
              id: this.orderId, 
              action: 'delete'
            }).then(res => {
              if (res.code === 1) {
            uni.showToast({
              title: '订单已删除',
                  icon: 'success'
            });
            setTimeout(() => {
              uni.navigateTo({
                url: '/addon/wj_books/pages/home/index'
              });
            }, 1500);
              } else {
                uni.showToast({
                  title: res.msg || '删除订单失败',
                  icon: 'none'
                });
              }
            }).catch(err => {
              console.error('删除订单错误', err);
              uni.showToast({
                title: '删除订单失败，请重试',
                icon: 'none'
              });
            }).finally(() => {
              uni.hideLoading();
            });
          }
        }
      });
    },
    viewRecycleHistory() {
      uni.navigateTo({
        url: '/addon/wj_books/pages/user/recycle_history'
      });
    },
    // 返回首页
    goToHome() {
      uni.navigateTo({
        url: '/addon/wj_books/pages/home/index'
      });
    },
    // 关闭物流弹窗
    closeExpressPopup() {
      this.showExpressPopup = false;
    },
    // 预览拒收图片
    previewRejectedImage(images, current) {
      // 确保所有图片URL都应用了img()函数处理，添加服务器地址前缀
      const processedUrls = images.map(image => this.img(image));
      
      uni.previewImage({
        urls: processedUrls,
        current: processedUrls[current]
      });
    },
    // 获取快递公司logo
    getExpressLogo(channel) {
      // 首先检查express_channel_id
      if (this.orderInfo && this.orderInfo.express_channel_id) {
        const channelId = this.orderInfo.express_channel_id.toLowerCase();
        
        if (channelId.includes('京东') || channelId.includes('jd')) {
          return this.img('addon/wj_books/express/jd.png');
        } else if (channelId.includes('顺丰') || channelId.includes('sf')) {
          return this.img('addon/wj_books/express/sf.png');
        } else if (channelId.includes('德邦') || channelId.includes('db')) {
          return this.img('addon/wj_books/express/db.png');
        }
      }
      
      // 如果express_channel_id没有匹配，则检查channel参数
      if (channel) {
        channel = channel.toLowerCase();
        if (channel.includes('jd') || channel.includes('京东')) {
          return this.img('addon/wj_books/express/jd.png');
        } else if (channel.includes('sf') || channel.includes('顺丰')) {
          return this.img('addon/wj_books/express/sf.png');
        } else if (channel.includes('db') || channel.includes('德邦')) {
          return this.img('addon/wj_books/express/db.png');
        }
      }
      
      return this.img('addon/wj_books/express/courier_avatar.png');
    },
    // 联系客服
    contactService() {
      this.showServiceDialog = true;
    },
    
    // 关闭客服弹窗
    closeServiceDialog() {
      this.showServiceDialog = false;
    },
    
    // 显示取回申请状态信息
    showRetrieveStatusInfo() {
      if (this.hasApplied) {
        // 已申请状态，显示申请进度
        uni.showModal({
          title: '申请进度',
          content: '您的取回申请已提交，正在处理中。\n\n处理流程：\n1. 客服确认申请（1个工作日内）\n2. 安排物流寄回（1-3个工作日）\n3. 书籍寄出（物流配送时间）\n\n如需了解更多详情，请联系客服。',
          showCancel: false,
          confirmText: '我知道了',
          confirmColor: '#4CAF50'
        });
      } else {
        // 超时状态，提示已超期
        uni.showModal({
          title: '无法申请取回',
          content: '已超过申请期限，无法申请取回书籍。根据平台规则，超过申请期限的不合格书籍将由平台统一处理。',
          showCancel: false,
          confirmText: '我知道了',
          confirmColor: '#4CAF50'
        });
      }
    }
  },
  // 页面销毁时清除定时器
  onUnload() {
    if (this.retrieveCountdownTimer) {
      clearInterval(this.retrieveCountdownTimer);
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

.detail-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  padding-bottom: calc(100rpx + env(safe-area-inset-bottom));
}

/* 顶部背景区域 */
.detail-header {
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
.detail-content {
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

.book-count {
  font-size: 24rpx;
  color: #999999;
  margin-left: auto;
  background-color: #F5F5F5;
  padding: 4rpx 14rpx;
  border-radius: 20rpx;
}

/* 状态卡片样式 */
.status-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}

.status-icon {
  width: 100rpx;
  height: 100rpx;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-right: 24rpx;
}

.status-1 {
  background: linear-gradient(135deg, #FF9800 0%, #FFEB3B 100%); /* 待上门 */
}

.status-2 {
  background: linear-gradient(135deg, #2196F3 0%, #03A9F4 100%); /* 已取件 */
}

.status-3 {
  background: linear-gradient(135deg, #9C27B0 0%, #E040FB 100%); /* 审核中 */
}

.status-4 {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%); /* 已完成 */
}

.status-5 {
  background: linear-gradient(135deg, #9E9E9E 0%, #BDBDBD 100%); /* 已取消 */
}

.status-info {
  flex: 1;
}

.status-text {
  font-size: 34rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 10rpx;
  display: block;
}

.status-desc {
  font-size: 26rpx;
  color: #666666;
  display: block;
}

/* 快递员信息卡片样式 - 优化版 */
.courier-info {
  display: flex;
  align-items: center;
}

.courier-avatar {
  width: 100rpx;
  height: 100rpx;
  border-radius: 50%;
  overflow: hidden;
  margin-right: 24rpx;
  border: 1rpx solid #EFEFEF;
}

.courier-avatar image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.courier-detail {
  flex: 1;
}

.courier-row {
  display: flex;
  align-items: center;
  margin-bottom: 10rpx;
}

.courier-name {
  font-size: 30rpx;
  font-weight: 600;
  color: #333333;
  margin-right: 16rpx;
}

.courier-company {
  font-size: 24rpx;
  color: #4CAF50;
  background-color: rgba(76, 175, 80, 0.1);
  padding: 2rpx 12rpx;
  border-radius: 16rpx;
}

.courier-phone {
  font-size: 26rpx;
  color: #666666;
  margin-bottom: 16rpx;
}

.courier-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16rpx;
}

.status-tag {
  display: flex;
  align-items: center;
  background-color: rgba(76, 175, 80, 0.1);
  padding: 4rpx 12rpx;
  border-radius: 16rpx;
}

.status-tag u-icon {
  margin-right: 6rpx;
}

.status-tag text {
  font-size: 24rpx;
  color: #4CAF50;
}

.pickup-time {
  font-size: 24rpx;
  color: #999999;
}

.contact-actions {
  display: flex;
}

.contact-btn {
  display: flex;
  align-items: center;
  padding: 6rpx 16rpx;
  border: 1rpx solid #4CAF50;
  border-radius: 16rpx;
  margin-right: 16rpx;
}

.contact-btn u-icon {
  margin-right: 6rpx;
}

.contact-btn text {
  font-size: 24rpx;
  color: #4CAF50;
}

/* 审核进度卡片样式 */
.audit-progress {
  padding: 10rpx 0;
}

.audit-tips {
  background-color: #F9F9F9;
  padding: 16rpx;
  border-radius: 12rpx;
}

.audit-tips text {
  font-size: 24rpx;
  color: #666666;
  line-height: 1.5;
}

/* 回收结果卡片样式 */
.result-info {
  display: flex;
  flex-wrap: wrap;
  margin-bottom: 20rpx;
}

.result-item {
  width: 50%;
  padding: 10rpx 0;
}

.result-label {
  font-size: 26rpx;
  color: #666666;
  display: block;
  margin-bottom: 8rpx;
}

.result-value {
  font-size: 28rpx;
  color: #333333;
  font-weight: 500;
  display: block;
}

.final-price {
  color: #FF6B6B;
  font-size: 34rpx;
  font-weight: 600;
}

.rejected-count {
  color: #FF9800;
}

.result-message {
  background-color: #FFF8E1;
  padding: 16rpx;
  border-radius: 12rpx;
  border-left: 4rpx solid #FFC107;
}

.message-title {
  font-size: 26rpx;
  color: #FF9800;
  font-weight: 600;
  margin-bottom: 8rpx;
  display: block;
}

.message-content {
  font-size: 24rpx;
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

.process-current .step-dot {
  box-shadow: 0 0 0 6rpx rgba(76, 175, 80, 0.2);
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

/* 订单信息样式 */
.order-info-list {
  display: flex;
  flex-direction: column;
}

.info-item {
  display: flex;
  align-items: center;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #F5F5F5;
}

.info-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.info-label {
  width: 160rpx;
  font-size: 28rpx;
  color: #666666;
}

.info-value {
  flex: 1;
  font-size: 28rpx;
  color: #333333;
}

.copy-btn {
  font-size: 24rpx;
  color: #4CAF50;
  padding: 4rpx 14rpx;
  border: 1rpx solid #4CAF50;
  border-radius: 20rpx;
  margin-left: 10rpx;
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

/* 书籍列表样式 */
.book-list {
  display: flex;
  flex-direction: column;
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
}

.book-cover image {
  width: 100%;
  height: 100%;
  object-fit: cover;
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

/* 拒收书籍样式优化 */
.rejected-books {
  margin-top: 20rpx;
  padding-top: 20rpx;
  border-top: 1rpx dashed #F0F0F0;
}

.rejected-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 16rpx;
}

.rejected-book-item {
  background-color: #F9F9F9;
  border-radius: 12rpx;
  padding: 16rpx;
  margin-bottom: 16rpx;
  display: flex;
  justify-content: space-between;
}

.rejected-book-info {
  flex: 1;
}

.rejected-book-title {
  font-size: 28rpx;
  font-weight: 500;
  color: #333333;
  display: block;
  margin-bottom: 8rpx;
}

.rejected-book-reason {
  font-size: 24rpx;
  color: #FF9800;
  display: block;
}

.rejected-book-quantity {
  font-size: 24rpx;
  color: #666666;
  display: block;
}

.rejected-book-cover {
  width: 100rpx;
  height: 140rpx;
  border-radius: 8rpx;
  overflow: hidden;
  margin-left: 16rpx;
}

.rejected-book-cover image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* 取回申请部分样式 */
.retrieve-section {
  margin-top: 16rpx;
  padding: 16rpx;
  background-color: #FFF8E1;
  border-radius: 12rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.retrieve-info {
  flex: 1;
}

.retrieve-countdown {
  display: flex;
  align-items: center;
  margin-bottom: 8rpx;
}

.retrieve-countdown u-icon {
  margin-right: 8rpx;
}

.retrieve-countdown text {
  font-size: 26rpx;
  color: #FF9800;
  font-weight: 500;
}

.retrieve-tip {
  font-size: 24rpx;
  color: #999999;
}

.retrieve-btn {
  padding: 12rpx 24rpx;
  background: linear-gradient(135deg, #FF9800 0%, #FFC107 100%);
  color: #FFFFFF;
  font-size: 26rpx;
  font-weight: 500;
  border-radius: 24rpx;
}

.retrieve-btn.disabled {
  background: #CCCCCC;
  color: #FFFFFF;
}

/* 取回申请弹窗样式 */
.retrieve-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.retrieve-modal-mask {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  z-index: 1;
}

.retrieve-modal-content {
  position: relative;
  width: 90%;
  max-width: 650rpx;
  max-height: 80vh;
  background-color: #FFFFFF;
  border-radius: 24rpx;
  overflow: hidden;
  z-index: 2;
  display: flex;
  flex-direction: column;
}

.retrieve-modal-header {
  padding: 30rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1rpx solid #F0F0F0;
}

.retrieve-modal-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #333333;
}

.retrieve-modal-close {
  width: 50rpx;
  height: 50rpx;
  display: flex;
  justify-content: center;
  align-items: center;
}

.retrieve-modal-body {
  flex: 1;
  padding: 30rpx;
  overflow-y: auto;
}

.retrieve-modal-tips {
  margin-bottom: 24rpx;
  padding: 16rpx;
  background-color: #FFF8E1;
  border-radius: 12rpx;
}

.retrieve-modal-tips text {
  font-size: 26rpx;
  color: #666666;
  display: block;
  line-height: 1.5;
}

.retrieve-modal-subtitle {
  font-size: 24rpx;
  color: #999999;
  margin-top: 8rpx;
}

.retrieve-modal-section-title {
  font-size: 28rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 16rpx;
  margin-top: 24rpx;
}

.retrieve-modal-books {
  margin-bottom: 24rpx;
}

.retrieve-modal-book-count {
  font-size: 26rpx;
  color: #FF9800;
  font-weight: 500;
  margin-bottom: 16rpx;
}

.retrieve-modal-book-item {
  padding: 16rpx 0;
  border-bottom: 1rpx solid #F5F5F5;
}

.retrieve-modal-book-info {
  display: flex;
  align-items: center;
}

.retrieve-modal-book-title {
  flex: 1;
  font-size: 28rpx;
  color: #333333;
}

.retrieve-modal-book-quantity {
  font-size: 24rpx;
  color: #999999;
  margin-left: 16rpx;
}

.retrieve-modal-address {
  margin-bottom: 24rpx;
}

.retrieve-modal-address-info {
  background-color: #F9F9F9;
  padding: 16rpx;
  border-radius: 12rpx;
  margin-bottom: 16rpx;
}

.retrieve-modal-address-contact {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8rpx;
}

.retrieve-modal-address-contact text {
  font-size: 28rpx;
  color: #333333;
}

.retrieve-modal-address-detail {
  font-size: 26rpx;
  color: #666666;
  line-height: 1.5;
}

.retrieve-address-tip {
  font-size: 24rpx;
  color: #999999;
  margin-top: 10rpx;
  text-align: center;
  padding: 10rpx;
  background-color: #F8F8F8;
  border-radius: 8rpx;
}

.retrieve-modal-remark {
  margin-bottom: 24rpx;
}

.retrieve-modal-textarea {
  width: 100%;
  height: 160rpx;
  background-color: #F9F9F9;
  border-radius: 12rpx;
  padding: 16rpx;
  font-size: 26rpx;
  color: #333333;
}

.retrieve-modal-footer {
  display: flex;
  padding: 20rpx 30rpx;
  border-top: 1rpx solid #F0F0F0;
}

.retrieve-modal-btn {
  flex: 1;
  height: 80rpx;
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 10rpx;
  font-size: 28rpx;
  font-weight: 500;
}

.retrieve-modal-btn.cancel {
  background-color: #F0F0F0;
  color: #666666;
}

.retrieve-modal-btn.confirm {
  background: linear-gradient(135deg, #FF9800 0%, #FFC107 100%);
  color: #FFFFFF;
}

.retrieve-modal-book-status {
  font-size: 24rpx;
  color: #FF9800;
  margin-left: 16rpx;
}

.retrieve-modal-book-status-available {
  font-size: 24rpx;
  color: #4CAF50;
  margin-left: 16rpx;
}

.retrieve-modal-book-status-unavailable {
  font-size: 24rpx;
  color: #FF9800;
  margin-left: 16rpx;
}

/* 底部按钮 */
.footer-btns {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  background-color: #FFFFFF;
  padding: 20rpx 30rpx;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);
  z-index: 100;
}

.footer-btn {
  flex: 1;
  height: 80rpx;
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 10rpx;
  font-size: 28rpx;
  font-weight: 500;
}

.secondary-btn {
  background-color: #F0F0F0;
  color: #666666;
}

.primary-btn {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  color: #FFFFFF;
  box-shadow: 0 4rpx 12rpx rgba(76, 175, 80, 0.2);
}

.full-width {
  margin: 0;
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

/* 物流弹窗样式 */
.express-popup {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.express-popup-mask {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  z-index: 1;
}

.express-popup-content {
  position: relative;
  width: 90%;
  max-width: 650rpx;
  max-height: 80vh;
  background-color: #FFFFFF;
  border-radius: 24rpx;
  overflow: hidden;
  z-index: 2;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.15);
}

.express-popup-header {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  padding: 30rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.express-popup-title {
  color: #FFFFFF;
  font-size: 34rpx;
  font-weight: 600;
}

.express-popup-close {
  width: 50rpx;
  height: 50rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
}

.express-info {
  padding: 30rpx;
  border-bottom: 1rpx solid #F0F0F0;
}

.express-info-item {
  display: flex;
  align-items: center;
  margin-bottom: 16rpx;
}

.express-info-item:last-child {
  margin-bottom: 0;
}

.express-info-label {
  width: 140rpx;
  font-size: 28rpx;
  color: #666666;
}

.express-info-value {
  flex: 1;
  font-size: 28rpx;
  color: #333333;
}

.express-info-value.status {
  color: #4CAF50;
}

.express-copy-btn {
  padding: 6rpx 16rpx;
  border: 1rpx solid #4CAF50;
  border-radius: 20rpx;
  font-size: 24rpx;
  color: #4CAF50;
  margin-left: 16rpx;
}

.express-traces {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 20rpx 30rpx;
  max-height: 60vh;
}

.express-traces-title {
  font-size: 30rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 20rpx;
}

.express-traces-scroll {
  flex: 1;
  height: 100%;
  max-height: calc(60vh - 80rpx);
}

.express-traces-list {
  padding-bottom: 30rpx;
}

.express-trace-item {
  position: relative;
  padding-left: 30rpx;
  padding-bottom: 30rpx;
}

.express-trace-dot {
  position: absolute;
  left: 0;
  top: 8rpx;
  width: 16rpx;
  height: 16rpx;
  border-radius: 50%;
  background-color: #CCCCCC;
}

.express-trace-item-first .express-trace-dot {
  background-color: #4CAF50;
  width: 20rpx;
  height: 20rpx;
  top: 6rpx;
  left: -2rpx;
  border: 4rpx solid rgba(76, 175, 80, 0.2);
}

.express-trace-line {
  position: absolute;
  left: 8rpx;
  top: 24rpx;
  width: 1rpx;
  bottom: 0;
  background-color: #EEEEEE;
}

.express-trace-content {
  padding-left: 20rpx;
}

.express-trace-status {
  font-size: 28rpx;
  color: #333333;
  display: block;
  margin-bottom: 8rpx;
}

.express-trace-item-first .express-trace-status {
  font-weight: 600;
  color: #000000;
}

.express-trace-time {
  font-size: 24rpx;
  color: #999999;
}

.express-no-traces {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200rpx;
  color: #999999;
  font-size: 28rpx;
}

/* 拒收图片展示区域 */
.rejected-images-section {
  margin-top: 20rpx;
  padding: 16rpx;
  background-color: #F9F9F9;
  border-radius: 12rpx;
}

.rejected-images-title {
  font-size: 26rpx;
  font-weight: 600;
  color: #333333;
  margin-bottom: 16rpx;
}

.rejected-images-scroll {
  width: 100%;
  white-space: nowrap;
}

.rejected-images-container {
  display: inline-flex;
  padding: 6rpx 0;
}

.rejected-image-item {
  width: 160rpx;
  height: 160rpx;
  border-radius: 8rpx;
  margin-right: 12rpx;
  overflow: hidden;
  border: 1rpx solid #EFEFEF;
}

.rejected-image-item image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* 客服提示弹窗样式 */
.service-dialog {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.service-dialog-mask {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  z-index: 1;
}

.service-dialog-content {
  position: relative;
  width: 80%;
  background-color: #FFFFFF;
  border-radius: 12rpx;
  overflow: hidden;
  z-index: 2;
  padding: 30rpx;
}

.service-dialog-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  margin-bottom: 20rpx;
}

.service-dialog-message {
  font-size: 28rpx;
  color: #666666;
  text-align: center;
  padding: 20rpx 0;
}

.service-dialog-footer {
  margin-top: 30rpx;
  display: flex;
  justify-content: center;
}

.service-dialog-btn {
  padding: 12rpx 40rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  color: #FFFFFF;
  font-size: 28rpx;
  border-radius: 40rpx;
}

.wx-contact-btn {
  margin: 0 10rpx;
  padding: 0;
  line-height: 80rpx;
  font-size: 28rpx;
  font-weight: 500;
}

.wx-contact-btn::after {
  border: none;
}

/* 自定义uni-modal按钮样式 */
::v-deep .uni-modal__btn.uni-modal__btn_primary {
  color: #4CAF50 !important;
}

::v-deep .wx-modal-btn-primary {
  color: #4CAF50 !important;
}
</style>

