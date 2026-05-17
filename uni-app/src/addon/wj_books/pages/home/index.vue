<template>
  <view class="book-recycle-container">
    <!-- 头部区域替换为图片 -->
    <view class="header-image-container" v-if="cartItems.length === 0">
      <image :src="img('addon/wj_books/home/banana1.jpg')" mode="widthFix" class="header-image"></image>
    </view>
    
    <!-- 当回收车有数据时显示订单页面风格的顶部背景 -->
    <view class="order-header" v-if="cartItems.length > 0">
      <text class="page-title">{{ systemConfig.platform_name || $t('pages.home.index.title') }}</text>
      <view class="header-decoration"></view>
    </view>

    <!-- 回收流程 - 当回收车有数据时隐藏 -->
    <view class="process-section" v-if="cartItems.length === 0">
      <view class="section-title">
        <text>回收流程</text>
        <text class="more" @click="showProcessDetail">查看详情 ></text>
      </view>
      <view class="process-steps">
        <view class="step-item">
          <view class="step-icon">
            <u-icon name="scan" color="#4CAF50" size="50rpx"></u-icon>
          </view>
          <text class="step-num">1</text>
          <text class="step-text">扫码回收</text>
        </view>
        <view class="step-arrow">
          <u-icon name="arrow-right" color="#DDDDDD" size="40rpx"></u-icon>
        </view>
        <view class="step-item">
          <view class="step-icon">
            <u-icon name="car" color="#4CAF50" size="50rpx"></u-icon>
          </view>
          <text class="step-num">2</text>
          <text class="step-text">免费上门</text>
        </view>
        <view class="step-arrow">
          <u-icon name="arrow-right" color="#DDDDDD" size="40rpx"></u-icon>
        </view>
        <view class="step-item">
          <view class="step-icon">
            <u-icon name="rmb-circle" color="#4CAF50" size="50rpx"></u-icon>
          </view>
          <text class="step-num">3</text>
          <text class="step-text">审核拿钱</text>
        </view>
      </view>
    </view>

    <!-- 回收车区域 -->
    <view class="recycle-cart-section" v-if="cartItems.length > 0">
      <view class="cart-header">
        <view class="cart-title">上门收旧书</view>
        <view class="free-pickup-tag">免费上门取件</view>
      </view>


      
      <!-- 回收车列表 -->
      <view class="cart-book-list">
        <view class="cart-book-item" v-for="(item, index) in cartItems" :key="index">
          <view class="item-select" @click.stop.prevent="toggleSelect(item)">
            <template v-if="item.selected">
              <u-icon name="checkmark-circle-fill" color="#4CAF50" size="40rpx"></u-icon>
            </template>
            <template v-else>
              <view class="custom-circle"></view>
            </template>
          </view>
          <view class="item-image">
            <image :src="item.img ? img(item.img) : img('addon/wj_books/home/default-book.jpg')" mode="aspectFit" class="book-image"></image>
          </view>
          <view class="item-info">
            <view class="item-title">{{ item.title || '未知书名' }}</view>
            <view class="item-price">预估：<text class="price-highlight">¥{{ item.recycle_price || '0.00' }}</text></view>
          </view>
          <view class="item-quantity">
            <view class="quantity-control">
              <view class="quantity-btn minus" @click="updateQuantity(index, -1)">-</view>
              <view class="quantity-num">{{ item.quantity || 1 }}</view>
              <view class="quantity-btn plus" @click="updateQuantity(index, 1)">+</view>
            </view>
          </view>
          <view class="item-delete" @click="removeItem(item.id, item.scan_id)">
            <u-icon name="close" color="#999" size="36rpx"></u-icon>
          </view>
        </view>
      </view>
      
      <!-- 不支持回收的书籍 -->
      <view class="unsupported-cart-section" v-if="unsupportedBooks.length > 0">
        <view class="unsupported-cart-title">以下图书暂不支持回收</view>
        <view class="unsupported-cart-list">
          <view class="unsupported-cart-item" v-for="(item, index) in unsupportedBooks" :key="index">
            <view class="item-image">
              <image :src="item.img ? img(item.img) : img('addon/wj_books/home/default-book.jpg')" mode="aspectFit" class="book-image"></image>
            </view>
            <view class="item-info">
              <view class="item-title">{{ item.title || '未知书名' }}</view>
            </view>
            <view class="item-delete" @click="removeItem(item.id, item.scan_id)">
              <u-icon name="close" color="#999" size="36rpx"></u-icon>
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 快速操作区 -->
    <view class="quick-actions" v-if="cartItems.length === 0">
      <view class="action-button scan-button" @click="goToScan">
        <u-icon name="scan" color="#FFFFFF" size="50rpx"></u-icon>
        <text>立即扫码回收</text>
      </view>
      <view class="action-button manual-button" @click="showIsbnInput">
        <u-icon name="list" color="#FFFFFF" size="40rpx"></u-icon>
        <text>手动输入ISBN码</text>
      </view>
    </view>

    <!-- 不支持回收的书籍说明 -->
    <view class="not-support-section" v-if="cartItems.length === 0">
      <view class="section-title not-support-title">
        <text>以下书籍不支持回收</text>
      </view>
      <view class="not-support-header">
        <image :src="img('addon/wj_books/home/32111.jpg')" mode="widthFix" class="not-support-banner"></image>
      </view>
    </view>

    <!-- 为什么选择我们 - 当回收车有数据时隐藏 -->
    <view class="why-us-section" v-if="cartItems.length === 0">
      <view class="section-title">
        <text>为什么选择我们</text>
      </view>
      <view class="why-us-grid">
        <view class="why-us-item" v-for="(item, index) in whyUs" :key="index">
          <view class="why-us-icon">
            <u-icon :name="item.icon" color="#67C23A" size="40rpx"></u-icon>
          </view>
          <text class="why-us-name">{{item.name}}</text>
        </view>
      </view>
    </view>

    <!-- 常见问题 - 当回收车有数据时隐藏 -->
    <view class="faq-section" v-if="cartItems.length === 0">
      <view class="section-title">
        <text>常见问题</text>
      </view>
      <view class="faq-list">
        <view class="faq-item" v-for="(item, index) in faqs" :key="index" @click="toggleFaq(index)">
          <view class="faq-question">
            <text>{{item.question}}</text>
            <u-icon :name="item.open ? 'arrow-up' : 'arrow-down'" color="#999" size="30rpx"></u-icon>
          </view>
          <view class="faq-answer" v-if="item.open">
            <text>{{item.answer}}</text>
          </view>
        </view>
      </view>
    </view>

    <!-- ISBN输入弹窗 -->
    <view class="isbn-modal" v-if="showIsbnPopup" @click="closeIsbnInput">
      <view class="isbn-popup" @click.stop>
        <view class="popup-header">
          <text class="popup-title">手动输入ISBN码</text>
        </view>
        <view class="popup-content">
          <input v-model="isbnInput" placeholder="请输入13位ISBN码" maxlength="13" type="number" class="isbn-input" />
          <text class="isbn-tip">ISBN码一般印在书籍封底或版权页</text>
          <view class="isbn-example">
            <image :src="img('addon/wj_books/home/isbn.jpg')" mode="widthFix" class="isbn-image"></image>
            <text class="example-text">ISBN码示例图</text>
          </view>
        </view>
        <view class="popup-footer">
          <view class="popup-btn cancel-btn" @click="closeIsbnInput">取消</view>
          <view class="popup-btn confirm-btn" @click="confirmIsbn">确认</view>
        </view>
      </view>
    </view>
    
    <!-- 操作按钮 - 当回收车有数据时显示 -->
    <view class="action-buttons-container" v-if="cartItems.length > 0">
      <view class="action-buttons">
        <view class="manual-input-button" @click="showIsbnInput">
          <u-icon name="edit-pen" color="#333333" size="24rpx"></u-icon>
          <text>手动输入</text>
        </view>
        <view class="scan-more-button" @click="goToScan">
          <u-icon name="camera" color="#FFFFFF" size="24rpx"></u-icon>
          <text>换一本扫</text>
        </view>
      </view>
    </view>
    
    <!-- 底部结算栏 - 当回收车有数据时显示 -->
    <view class="bottom-bar-container" v-if="cartItems.length > 0">
      <view class="checkout-row">
        <view class="select-all-container">
          <view class="checkbox-wrapper" @click.stop.prevent="toggleSelectAll">
            <template v-if="isAllSelected">
              <u-icon name="checkmark-circle-fill" color="#4CAF50" size="36rpx"></u-icon>
            </template>
            <template v-else>
              <view class="custom-circle"></view>
            </template>
          </view>
          <text class="select-all-text" @click.stop.prevent="()=>{}">全选</text>
        </view>
        <view class="selected-info">已选{{ selectedCount }}件</view>
      </view>
      
      <view class="price-row">
        <text class="price-label">预计回收价格</text>
        <text class="price-value">¥{{ selectedTotalPrice }}</text>
      </view>
      
      <view class="recycle-button-wrap">
        <view class="recycle-button" :class="{'active-button': canSubmitRecycle}" @click="submitRecycle">
          {{ canSubmitRecycle ? '下一步' : `满${minBookCount}本可回收` }}
        </view>
      </view>
    </view>
      <!-- 底部导航 - 当回收车没有数据时显示 -->
    <tabbar v-if="cartItems.length === 0" />

    <!-- 回收流程详情弹窗 -->
    <view class="process-detail-modal" v-if="showProcessDetailPopup" @click="closeProcessDetail">
      <view class="process-detail-popup" @click.stop>
        <view class="popup-header">
          <text class="popup-title">回收流程详情</text>
          <view class="close-btn" @click="closeProcessDetail">
            <u-icon name="close" color="#999" size="30rpx"></u-icon>
          </view>
        </view>
        
        <view class="popup-content">
          <!-- 步骤1 -->
          <view class="detail-step">
            <view class="detail-step-header">
              <view class="detail-step-number">1</view>
              <view class="detail-step-title">扫码回收</view>
            </view>
            <view class="detail-step-content">
              <text>通过小程序扫描书籍ISBN条形码或手动输入ISBN码，系统自动识别书籍信息并评估回收价格。最低回收数量为{{minBookCount}}本。</text>
            </view>
          </view>
          
          <!-- 步骤2 -->
          <view class="detail-step">
            <view class="detail-step-header">
              <view class="detail-step-number">2</view>
              <view class="detail-step-title">免费上门</view>
            </view>
            <view class="detail-step-content">
              <text>提交回收申请后，选择合适的上门取件时间，我们的专业物流人员会按约定时间上门取件，全程免运费。</text>
            </view>
          </view>
          
          <!-- 步骤3 -->
          <view class="detail-step">
            <view class="detail-step-header">
              <view class="detail-step-number">3</view>
              <view class="detail-step-title">审核拿钱</view>
            </view>
            <view class="detail-step-content">
              <text>平台收到书籍后进行专业质检，根据书籍实际品相评估最终回收价格，审核通过后将立即打款至您的账户。</text>
            </view>
          </view>
        </view>
        
        <view class="popup-footer">
          <view class="popup-btn" @click="closeProcessDetail">立即开始回收</view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import { img } from '@/utils/common';
import { queryBookInfo, getConfig } from '@/addon/wj_books/api/wj_books_info';
import { addToCart, getCartList, removeFromCart, submitRecycle, updateCartQuantity } from '@/addon/wj_books/api/wj_books_cart';
import { getToken, redirect } from '@/utils/common';
import { useLogin } from '@/hooks/useLogin';

export default {
  data() {
    return {
      whyUs: [
        { name: '免费上门', icon: 'car' },
        { name: '高价回收', icon: 'rmb-circle' },
        { name: '专业质检', icon: 'checkmark' },
        { name: '到账更快', icon: 'clock' }
      ],
      minBookCount: 5, // 默认最低回收数量，将从配置中获取
      systemConfig: {}, // 存储系统配置
      faqs: [
        { 
          question: '怎么预约回收？', 
          answer: '根据提示扫码回收、填写回收地址、期望上门时间，并提交回收订单即可，提交成功后可在订单页中查询。',
          open: false
        },
        { 
          question: '回收需要支付运费吗？', 
          answer: '不需要，我们提供免费上门取件服务，您无需支付任何费用。',
          open: false
        },
        { 
          question: '有回收门槛要求吗？', 
          answer: '为了提高回收效率，建议您一次性回收10本以上的书籍。单本价值较高的图书也可单独回收。',
          open: false
        },
        { 
          question: '验收不通过的书籍会如何处理？', 
          answer: '验收不通过的书籍，您可以选择我们免费代为环保处理，或者支付邮费退回。',
          open: false
        },
        { 
          question: '验收通过书籍支持寄回吗？', 
          answer: '验收通过的书籍，根据用户协议已转让给平台，不支持寄回。',
          open: false
        }
      ],
      showIsbnPopup: false,
      isbnInput: '',
      showBookModal: false,
      currentBook: {},
      currentIsbn: '',
      cartItems: [],
      unsupportedBooks: [],
      totalPrice: '0.00',
      showProcessDetailPopup: false
    }
  },
  computed: {
    // 是否可以提交回收
    canSubmitRecycle() {
      return this.selectedCount >= this.minBookCount;
    },
    // 计算总价
    calculatedTotalPrice() {
      let total = 0;
      this.cartItems.forEach(item => {
        const price = parseFloat(item.recycle_price || 0);
        const quantity = parseInt(item.quantity || 1);
        total += price * quantity;
      });
      return total.toFixed(2);
    },
    // 计算选中的总价
    selectedTotalPrice() {
      let total = 0;
      this.cartItems.forEach(item => {
        if (item.selected) {
          const price = parseFloat(item.recycle_price || 0);
          const quantity = parseInt(item.quantity || 1);
          total += price * quantity;
        }
      });
      return total.toFixed(2);
    },
    // 是否全选
    isAllSelected() {
      return this.cartItems.length > 0 && this.cartItems.every(item => item.selected);
    },
    // 选中的数量
    selectedCount() {
      return this.cartItems.filter(item => item.selected).length;
    }
  },
  onLoad() {
    // 页面加载时获取系统配置
    this.loadSystemConfig();
  },
  onShow() {
    // 页面显示时加载回收车数据
    if (getToken()) {
      this.loadCartData();
    }
  },
  // 添加created钩子，设置动态页面标题
  created() {
    // 设置小程序/APP标题为语言包默认值
    uni.setNavigationBarTitle({
      title: this.$t('pages.home.index.title')
    });
  },
  methods: {
    img,
    // 加载系统配置
    async loadSystemConfig() {
      try {
        const res = await getConfig();
        
        if (res.code === 1 && res.data) {
          this.systemConfig = res.data;
          // 设置最低回收数量
          this.minBookCount = res.data.min_book_count || 5;
          
          // 如果获取到了平台名称，动态更新页面标题
          if (res.data.platform_name) {
            uni.setNavigationBarTitle({
              title: res.data.platform_name
            });
          }
          
          console.log('系统配置:', this.systemConfig);
        } else {
          console.error('获取系统配置失败', res.msg);
        }
      } catch (error) {
        console.error('获取系统配置失败', error);
      }
    },
    toggleFaq(index) {
      this.faqs[index].open = !this.faqs[index].open;
    },

    // 检查用户是否登录
    checkLogin() {
      if (!getToken()) {
        // 设置登录后返回的页面
        const login = useLogin();
        login.setLoginBack({ 
          url: '/addon/wj_books/pages/home/index',
          param: {}
        });
        uni.showToast({
          title: '请先登录',
          icon: 'none',
          success: () => {
            setTimeout(() => {
              // 跳转到登录页面
              redirect({ url: '/app/pages/auth/index', mode: 'redirectTo' });
            }, 1500);
          }
        });
        return false;
      }
      return true;
    },
    // 加载回收车数据
    async loadCartData() {
      uni.showLoading({
        title: '加载中...'
      });
      
      try {
        const res = await getCartList();
        
        uni.hideLoading();
        
        if (res.code === 1 && res.data) {
          // 直接使用返回的列表数据
          const allItems = res.data.list || [];
          
          // 区分可回收和不可回收的图书
          this.cartItems = allItems.filter(item => item.can_recycle !== 0);
          this.unsupportedBooks = allItems.filter(item => item.can_recycle === 0);
          
          // 初始化数量和选中状态
          this.cartItems.forEach(item => {
            if (!item.quantity) {
              this.$set(item, 'quantity', 1);
            }
            // 默认选中所有项
            this.$set(item, 'selected', true);
          });
          
          // 使用返回的总价或计算总价
          this.totalPrice = this.calculatedTotalPrice;
          
          console.log('回收车数据:', this.cartItems);
          console.log('不可回收图书:', this.unsupportedBooks);
        } else {
          console.error('获取回收车列表失败', res.msg);
        }
      } catch (error) {
        uni.hideLoading();
        console.error('获取回收车列表失败', error);
      }
    },
    
    // 切换选中状态
    toggleSelect(item) {
      this.$set(item, 'selected', !item.selected);
    },
    
    // 全选/取消全选
    toggleSelectAll() {
      const newState = !this.isAllSelected;
      this.cartItems.forEach(item => {
        this.$set(item, 'selected', newState);
      });
    },
    
    // 更新回收车中图书数量（调用API，静默执行）
    async updateQuantity(index, change) {
      // 获取当前项
      const item = this.cartItems[index];
      if (!item) return;
      
      // 当前数量
      const currentQuantity = item.quantity || 1;
      
      // 计算新数量（最小为1）
      const newQuantity = Math.max(1, currentQuantity + change);
      
      // 如果数量没有变化，直接返回
      if (newQuantity === currentQuantity) return;
      
      // 先更新本地数量，提供即时反馈
      this.$set(item, 'quantity', newQuantity);
      // 更新总价
      this.totalPrice = this.calculatedTotalPrice;
      
      try {
        // 静默调用API更新数量
        const res = await updateCartQuantity({
          cart_id: item.id,
          quantity: newQuantity
        });
        
        if (res.code !== 1) {
          // 只有在失败时才显示提示
          console.error('更新数量失败', res.msg);
          // 回滚本地数量
          this.$set(item, 'quantity', currentQuantity);
          // 重新计算总价
          this.totalPrice = this.calculatedTotalPrice;
        }
      } catch (error) {
        console.error('更新数量失败', error);
        // 回滚本地数量
        this.$set(item, 'quantity', currentQuantity);
        // 重新计算总价
        this.totalPrice = this.calculatedTotalPrice;
      }
    },
    
    goToScan() {
      // 先检查登录状态
      if (!this.checkLogin()) return;
      
      // 使用微信小程序官方的扫码接口
      // #ifdef MP-WEIXIN
      wx.scanCode({
        // 只扫描条形码
        scanType: ['barCode'],
        // 扫码成功后的回调
        success: (res) => {
          console.log('扫码结果：', res);
          // 从扫码结果中获取ISBN码
          if (res.result) {
            let isbn = res.result;
            
            // 尝试提取ISBN码
            // 对于非EAN_13的条码，尝试判断是否可能是ISBN
            if (this.isValidIsbn(isbn)) {
              // 移除可能的连字符和空格
              isbn = isbn.replace(/[-\s]/g, '');
              // 直接处理扫描结果
              this.handleScanResult(isbn, 1); // 1代表扫码方式
            } else {
              uni.showModal({
                title: '非ISBN码',
                content: '您扫描的不是有效的ISBN码，请扫描图书背面或版权页的条形码',
                showCancel: false,
                confirmText: '我知道了',
                confirmColor: '#4CAF50'
              });
            }
          } else {
            uni.showToast({
              title: '未获取到有效码，请重新扫描',
              icon: 'none',
              duration: 2000
            });
          }
        },
        // 扫码失败后的回调
        fail: (err) => {
          console.log('扫码失败：', err);
          // 用户主动取消不提示
          if (err.errMsg && err.errMsg.indexOf('cancel') === -1) {
            uni.showToast({
              title: '扫码失败，请重试',
              icon: 'none',
              duration: 2000
            });
          }
        }
      });
      // #endif
      
      // 在非微信小程序环境下提示用户
      // #ifndef MP-WEIXIN
      // 添加自定义样式
      let pageStyle = document.createElement('style');
      pageStyle.innerText = '.uni-modal__btn.uni-modal__btn_primary { color: #4CAF50 !important; }';
      document.head.appendChild(pageStyle);
      
      uni.showModal({
        title: '提示',
        content: '书籍扫码回收功能仅支持微信小程序，请前往微信小程序使用该功能',
        confirmText: '我知道了',
        showCancel: false,
        confirmColor: '#4CAF50',
        success: (res) => {
          if (res.confirm) {
            console.log('用户点击确定');
            // 清理添加的样式
            document.head.removeChild(pageStyle);
          }
        }
      });
      // #endif
    },
    // 处理扫码结果的方法 - 优化为直接添加到回收车
    async handleScanResult(isbn, scanType) {
      // 标记是否需要隐藏加载提示
      let needHideLoading = true;
      
      try {
        // 校验ISBN是否符合规范
        if (!this.isValidIsbn(isbn)) {
          uni.showToast({
            title: '请扫描有效的ISBN码',
            icon: 'none',
            duration: 2000
          });
          return;
        }
        
        // 展示加载中
        uni.showLoading({
          title: '查询中...',
          mask: true
        });
        
        // 调用API查询书籍信息
        const res = await queryBookInfo({
          isbn,
          scan_type: parseInt(scanType)
        });
        
        // 尝试隐藏加载提示
        try {
          uni.hideLoading();
          needHideLoading = false;
        } catch (loadingErr) {
          console.error('hideLoading失败', loadingErr);
        }
        
        if (res.code === 1) { // 接口成功返回code为1
          const data = res.data;
          
          // 如果API请求失败
          if (data.code === -1) {
            uni.showToast({
              title: data.msg || '查询图书信息失败',
              icon: 'none',
              duration: 2000
            });
            return;
          }
          
          const bookInfo = data.book_info;
          
          if (!bookInfo || !bookInfo.title) {
            uni.showToast({
              title: '未找到图书信息',
              icon: 'none',
              duration: 2000
            });
            return;
          }
          
          // 检查图书是否可回收
          if (bookInfo.can_recycle === 0) {
            uni.showModal({
              title: '无法回收',
              content: '抱歉，此书暂不支持回收',
              showCancel: false,
              confirmText: '我知道了',
              confirmColor: '#4CAF50'
            });
            return;
          }
          
          // 查询成功后直接添加到回收车，不再弹窗确认
          await this.directAddToCart(isbn);
          
        } else {
          uni.showToast({
            title: res.msg || '查询图书信息失败',
            icon: 'none',
            duration: 2000
          });
        }
      } catch (error) {
        console.error('查询图书信息失败', error);
        
        // 尝试隐藏加载提示
        if (needHideLoading) {
          try {
            uni.hideLoading();
          } catch (loadingErr) {
            console.error('hideLoading失败', loadingErr);
          }
        }
        
        uni.showToast({
          title: '查询图书信息失败，请重试',
          icon: 'none',
          duration: 2000
        });
      }
    },
    
    // 校验ISBN码是否有效
    isValidIsbn(isbn) {
      if (!isbn) return false;
      
      // 移除可能的连字符和空格
      isbn = isbn.replace(/[-\s]/g, '');
      
      // ISBN-10: 10位数字，最后一位可能是X
      if (isbn.length === 10) {
        return /^[0-9]{9}[0-9X]$/.test(isbn);
      }
      
      // ISBN-13: 13位数字
      if (isbn.length === 13) {
        return /^[0-9]{13}$/.test(isbn);
      }
      
      return false;
    },
    
    // 直接添加到回收车
    async directAddToCart(isbn) {
      let needHideLoading = true;
      
      try {
        uni.showLoading({
          title: '添加中...',
          mask: true
        });
        
        const res = await addToCart({
          isbn: isbn,
          quantity: 1,
          status: 'added_to_cart' // 添加状态：已加入回收车
        });
        
        // 尝试隐藏加载提示
        try {
          uni.hideLoading();
          needHideLoading = false;
        } catch (loadingErr) {
          console.error('hideLoading失败', loadingErr);
        }
        
        if (res.code === 1) {
          if (res.data && res.data.code === -1) {
            uni.showToast({
              title: res.data.msg || '添加到回收车失败',
              icon: 'none',
              duration: 2000
            });
            return;
          }
          
          // 判断书籍是否可以回收
          if (res.data && res.data.book_info && res.data.book_info.can_recycle === 0) {
            uni.showModal({
              title: '无法回收',
              content: '抱歉，此书暂不支持回收',
              showCancel: false,
              confirmText: '我知道了',
              confirmColor: '#4CAF50'
            });
          } else {
            uni.showToast({
              title: '已添加到回收车',
              icon: 'success',
              duration: 1500
            });
          }
          
          // 重新加载回收车数据
          this.loadCartData();
        } else {
          uni.showToast({
            title: res.msg || '添加到回收车失败',
            icon: 'none',
            duration: 2000
          });
        }
      } catch (error) {
        console.error('添加到回收车失败', error);
        
        // 尝试隐藏加载提示
        if (needHideLoading) {
          try {
            uni.hideLoading();
          } catch (loadingErr) {
            console.error('hideLoading失败', loadingErr);
          }
        }
        
        uni.showToast({
          title: '添加到回收车失败，请重试',
          icon: 'none',
          duration: 2000
        });
      }
    },
    
    // 从回收车移除图书
    async removeItem(id, scan_id) {
      if (!id) {
        console.error('移除图书时ID为空');
        return;
      }
      
      uni.showLoading({
        title: '移除中...'
      });
      
      try {
        const res = await removeFromCart({
          cart_id: id,
          scan_id: scan_id, // 传递扫描ID
          status: 0 // 更新状态为仅扫描(0)
        });
        
        uni.hideLoading();
        
        if (res.code === 1) {
          uni.showToast({
            title: '移除成功',
            icon: 'success'
          });
          
          // 重新加载回收车数据
          this.loadCartData();
        } else {
          uni.showToast({
            title: res.msg || '移除失败',
            icon: 'none'
          });
          }
      } catch (error) {
        uni.hideLoading();
        console.error('移除图书失败', error);
        uni.showToast({
          title: '移除失败，请重试',
          icon: 'none'
        });
      }
    },
    
    // 提交回收订单/跳转到订单页面
    submitRecycle() {
      // 获取选中的项目
      const selectedItems = this.cartItems.filter(item => item.selected);
      
      if (selectedItems.length < this.minBookCount) {
        uni.showToast({
          title: `至少需要选择${this.minBookCount}本书才能回收`,
          icon: 'none'
        });
        return;
      }
      
      // 直接跳转到订单页面，不提交任何参数
      uni.navigateTo({
        url: '/addon/wj_books/pages/order/index'
      });
    },
    
    goToCart() {
      uni.navigateTo({
        url: '/addon/wj_books/pages/cart/index'
      });
    },
    goToOrder() {
      uni.navigateTo({
        url: '/addon/wj_books/pages/order/index'
      });
    },
    showIsbnInput() {
      // 先检查登录状态
      if (!this.checkLogin()) return;
      
      this.showIsbnPopup = true;
    },
    closeIsbnInput() {
      this.showIsbnPopup = false;
      // 不要在这里清空isbnInput，避免影响确认操作
    },
    confirmIsbn() {
      if (!this.isbnInput || (this.isbnInput.length !== 13 && this.isbnInput.length !== 10)) {
        uni.showToast({
          title: 'ISBN码应为10位或13位数字',
          icon: 'none'
        });
        return;
      }
      
      // 保存ISBN码，防止关闭弹窗时清空
      const isbn = this.isbnInput;
      console.log('手动输入ISBN:', isbn); // 添加日志便于调试
      
      // 关闭弹窗并处理手动输入的ISBN
      this.closeIsbnInput();
      this.handleScanResult(isbn, 2); // 2代表手动输入方式
      
      // 清空输入框，便于下次输入
      this.isbnInput = '';
    },
    showBookInfoModal(bookInfo) {
      this.currentBook = bookInfo;
      this.showBookModal = true;
    },
    closeBookInfoModal() {
      this.showBookModal = false;
      this.currentBook = {};
    },
    addCurrentBookToCart() {
      this.addBookToCart(this.currentIsbn);
    },
    showProcessDetail() {
      this.showProcessDetailPopup = true;
    },
    closeProcessDetail() {
      this.showProcessDetailPopup = false;
    }
  }
}
</script>

<style lang="scss" scoped>
.book-recycle-container {
  min-height: 100vh;
  background-color: #f6f6f6;
  padding-bottom: 120rpx;
}

/* 头部图片区域 */
.header-image-container {
  width: 100%;
  overflow: hidden;
}

.header-image {
  width: 100%;
  display: block;
}

/* 订单风格顶部背景区域 */
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

/* 回收流程 */
.process-section {
  margin: -120rpx 30rpx 30rpx;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 30rpx;
  position: relative;
  z-index: 1;
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
  font-size: 32rpx;
  font-weight: 500;
}

.more {
  font-size: 24rpx;
  color: #999;
  font-weight: normal;
}

.process-steps {
  display: flex;
  justify-content: space-around;
  align-items: center;
}

.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}

.step-icon {
  width: 100rpx;
  height: 100rpx;
  background-color: #F0F9EB;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 20rpx;
}

.step-num {
  position: absolute;
  top: -10rpx;
  right: -10rpx;
  width: 36rpx;
  height: 36rpx;
  background-color: #4CAF50;
  color: #FFFFFF;
  border-radius: 50%;
  font-size: 24rpx;
  display: flex;
  justify-content: center;
  align-items: center;
}

.step-text {
  font-size: 26rpx;
  color: #333;
}

/* 操作按钮容器 */
.action-buttons-container {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 180rpx;
  z-index: 99;
  padding: 0 30rpx;
}

.action-buttons {
  display: flex;
  justify-content: space-between;
  width: 100%;
}

.manual-input-button {
  width: 48%;
  height: 80rpx;
  background-color: #FFFFFF;
  border-radius: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.1);
  border: 1rpx solid #EEEEEE;
}

.manual-input-button text {
  font-size: 28rpx;
  color: #333;
  margin-left: 10rpx;
}

.scan-more-button {
  width: 48%;
  height: 80rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  border-radius: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2rpx 10rpx rgba(76, 175, 80, 0.2);
}

.scan-more-button text {
  font-size: 28rpx;
  color: #FFFFFF;
  margin-left: 10rpx;
}

/* 不支持回收的书籍 */
.not-support-section {
  margin: 20rpx 30rpx;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding-bottom: 20rpx;
  overflow: hidden;
}

.not-support-header {
  width: 100%;
  margin-bottom: 0;
}

.not-support-banner {
  width: 100%;
  display: block;
}

.not-support-title {
  padding: 0 30rpx;
  margin-top: 20rpx;
}

.not-support-grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  padding: 0 30rpx;
}

.not-support-item {
  width: 22%;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 30rpx;
  position: relative;
}

.not-support-icon {
  width: 80rpx;
  height: 80rpx;
  background-color: #F56C6C;
  border-radius: 20rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 10rpx;
}

.not-support-name {
  font-size: 24rpx;
  color: #333;
}

/* 为什么选择我们 */
.why-us-section {
  margin: 20rpx 30rpx;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 20rpx 30rpx;
}

.why-us-grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}

.why-us-item {
  width: 47%;
  display: flex;
  align-items: center;
  margin-bottom: 15rpx;
  background-color: #F9F9F9;
  padding: 15rpx;
  border-radius: 10rpx;
  box-sizing: border-box;
}

.why-us-icon {
  width: 60rpx;
  height: 60rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-right: 20rpx;
}

.why-us-name {
  font-size: 26rpx;
  color: #333;
}

/* 常见问题 */
.faq-section {
  margin: 20rpx 30rpx 30rpx;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 20rpx 30rpx;
}

.faq-item {
  border-bottom: 2rpx solid #F0F0F0;
  padding: 15rpx 0;
}

.faq-item:last-child {
  border-bottom: none;
}

.faq-question {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 28rpx;
  color: #333;
}

.faq-answer {
  margin-top: 20rpx;
  font-size: 26rpx;
  color: #666;
  line-height: 1.6;
  padding: 0 20rpx;
}

/* ISBN输入弹窗 */
.isbn-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 999;
  display: flex;
  justify-content: center;
  align-items: center;
}

.isbn-popup {
  width: 80%;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 30rpx;
  box-shadow: 0 0 20rpx rgba(0, 0, 0, 0.1);
}

.popup-header {
  text-align: center;
  margin-bottom: 30rpx;
}

.popup-title {
  font-size: 32rpx;
  font-weight: 500;
}

.popup-content {
  margin-bottom: 30rpx;
}

.isbn-input {
  width: 100%;
  height: 80rpx;
  background-color: #F5F5F5;
  border-radius: 10rpx;
  padding: 0 20rpx;
  font-size: 28rpx;
  box-sizing: border-box;
}

.isbn-tip {
  font-size: 24rpx;
  color: #999;
  margin-top: 10rpx;
  display: block;
}

/* 添加ISBN示例图片样式 */
.isbn-example {
  margin-top: 20rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.isbn-image {
  width: 100%;
  max-width: 400rpx;
  border-radius: 10rpx;
  border: 1px solid #eee;
}

.example-text {
  font-size: 24rpx;
  color: #999;
  margin-top: 10rpx;
}

.popup-footer {
  display: flex;
  justify-content: space-between;
}

.popup-btn {
  width: 45%;
  height: 80rpx;
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 28rpx;
}

.cancel-btn {
  background-color: #F5F5F5;
  color: #666;
}

.confirm-btn {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  color: #FFFFFF;
}

/* 回收车区域样式 */
.recycle-cart-section {
  margin: -450rpx 25rpx 25rpx; /* 当头部为图片时的margin */
  margin-top: -80rpx; /* 当头部为订单风格时的margin */
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 0 0 20rpx 0;
  box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.05);
  overflow: hidden;
  position: relative;
  z-index: 1;
}

.cart-header {
  display: flex;
  flex-direction: row;
  align-items: center;
  padding: 20rpx 30rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.cart-title {
  font-size: 30rpx;
  font-weight: 600;
  color: #333;
  margin-right: 20rpx;
}

.free-pickup-tag {
  font-size: 22rpx;
  color: #fff;
  background-color: #FF9800;
  padding: 4rpx 12rpx;
  border-radius: 20rpx;
}

.lottery-notice {
  display: flex;
  align-items: center;
  background-color: #FFF9F0;
  margin: 20rpx 30rpx;
  padding: 16rpx 20rpx;
  border-radius: 10rpx;
}

.lottery-notice text {
  font-size: 24rpx;
  color: #FF5722;
  margin: 0 10rpx;
  flex: 1;
}

.cart-book-list {
  padding: 0;
}

.cart-book-item {
  display: flex;
  align-items: center;
  padding: 30rpx;
  border-bottom: 1rpx solid #f0f0f0;
  background-color: #FFFFFF;
}

.cart-book-item:last-child {
  border-bottom: none;
}

.item-select {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40rpx;
  height: 40rpx;
  margin-right: 20rpx;
}

.item-image {
  width: 140rpx;
  height: 180rpx;
  border-radius: 8rpx;
  overflow: hidden;
  margin-right: 20rpx;
  box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
}

.book-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-info {
  flex: 1;
  overflow: hidden;
  padding-right: 20rpx;
}

.item-title {
  font-size: 28rpx;
  font-weight: 500;
  color: #333;
  margin-bottom: 12rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-price {
  font-size: 26rpx;
  color: #666;
}

.price-highlight {
  color: #f56c6c;
  font-weight: 500;
}

.item-quantity {
  margin-right: 20rpx;
  flex-shrink: 0;
}

.quantity-control {
  display: flex;
  align-items: center;
  border: 1rpx solid #ddd;
  border-radius: 6rpx;
  overflow: hidden;
}

.quantity-btn {
  width: 50rpx;
  height: 50rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f5f5f5;
  font-size: 28rpx;
  color: #333;
}

.quantity-num {
  width: 60rpx;
  height: 50rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26rpx;
  color: #333;
  background-color: #fff;
}

.item-delete {
  padding: 10rpx;
  flex-shrink: 0;
}

.unsupported-cart-section {
  margin: 20rpx 30rpx;
  border: 2rpx solid #FF6B6B;
  border-radius: 12rpx;
  padding: 0;
  overflow: hidden;
}

.unsupported-cart-title {
  font-size: 26rpx;
  font-weight: 500;
  color: #FF6B6B;
  padding: 16rpx 20rpx;
  background-color: #FFF0F0;
  border-bottom: 1rpx solid #FFD1D1;
}

.unsupported-cart-list {
  background-color: #FFFFFF;
}

.unsupported-cart-item {
  display: flex;
  align-items: center;
  padding: 20rpx;
  border-bottom: 1rpx solid #FFE0E0;
}

.unsupported-cart-item:last-child {
  border-bottom: none;
}

.unsupported-cart-item .item-image {
  width: 100rpx;
  height: 130rpx;
  margin-right: 20rpx;
  flex-shrink: 0;
}

.unsupported-cart-item .item-info {
  flex: 1;
}

.unsupported-cart-item .item-title {
  font-size: 26rpx;
  color: #333;
  margin-bottom: 6rpx;
}

.unsupported-cart-item .item-edition {
  font-size: 22rpx;
  color: #999;
}

/* 底部容器 */
.bottom-bar-container {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 99;
  background-color: #FFFFFF;
  border-top: 1rpx solid #f5f5f5;
  padding: 20rpx 30rpx;
}

.checkout-row {
  display: flex;
  align-items: center;
}

.select-all-container {
  display: flex;
  align-items: center;
  pointer-events: none; /* 禁用整体点击事件 */
}

.checkbox-wrapper {
  margin-right: 10rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40rpx;
  height: 40rpx;
  pointer-events: auto; /* 恢复复选框的点击事件 */
}

.select-all-text {
  font-size: 28rpx;
  color: #333;
  pointer-events: auto; /* 恢复文本的点击事件，但不执行任何操作 */
  user-select: none; /* 防止文本被选中 */
}

.selected-info {
  font-size: 24rpx;
  color: #999;
  margin-left: 10rpx;
}

.price-row {
  margin-top: 10rpx;
}

.price-label {
  font-size: 24rpx;
  color: #999;
}

.price-value {
  font-size: 36rpx;
  font-weight: bold;
  color: #00C853;
  margin-left: 10rpx;
}

.recycle-button-wrap {
  position: absolute;
  right: 30rpx;
  bottom: 20rpx;
}

.recycle-button {
  width: 220rpx;
  height: 70rpx;
  background-color: #B4E6C9;
  color: #FFFFFF;
  font-size: 28rpx;
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
}

.active-button {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  box-shadow: 0 4rpx 12rpx rgba(76, 175, 80, 0.2);
}

/* 快速操作区 */
.quick-actions {
  margin: 20rpx 30rpx;
  display: flex;
  justify-content: space-between;
}

.action-button {
  width: 48%;
  height: 100rpx;
  border-radius: 20rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #FFFFFF;
  font-size: 28rpx;
  font-weight: 500;
}

.action-button u-icon {
  margin-right: 10rpx;
}

.scan-button {
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
}

.manual-button {
  background: linear-gradient(135deg, #FF9800 0%, #FFEB3B 100%);
}

/* 自定义未选中状态的圆形图标 */
.custom-circle {
  display: inline-block;
  width: 36rpx;
  height: 36rpx;
  border: 2rpx solid #CCCCCC;
  border-radius: 50%;
  box-sizing: border-box;
  vertical-align: middle;
}

/* 自定义uni-modal按钮样式 */
::v-deep .uni-modal__btn.uni-modal__btn_primary {
  color: #4CAF50 !important;
}

::v-deep .wx-modal-btn-primary {
  color: #4CAF50 !important;
}

/* 回收流程详情弹窗 */
.process-detail-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 999;
  display: flex;
  justify-content: center;
  align-items: center;
}

.process-detail-popup {
  width: 85%;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 40rpx 30rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.15);
  position: relative;
}

.popup-header {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 40rpx;
  position: relative;
}

.popup-title {
  font-size: 34rpx;
  font-weight: 600;
  color: #333;
  position: relative;
  padding-bottom: 16rpx;
}

.popup-title::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 40rpx;
  height: 4rpx;
  background-color: #4CAF50;
  border-radius: 2rpx;
}

.close-btn {
  position: absolute;
  top: -10rpx;
  right: -10rpx;
  width: 50rpx;
  height: 50rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: #f5f5f5;
  border-radius: 50%;
}

.popup-content {
  margin-bottom: 40rpx;
}

.detail-step {
  margin-bottom: 30rpx;
  padding: 20rpx;
  background-color: #f9f9f9;
  border-radius: 12rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
}

.detail-step:last-child {
  margin-bottom: 0;
}

.detail-step-header {
  display: flex;
  align-items: center;
  margin-bottom: 15rpx;
}

.detail-step-number {
  width: 40rpx;
  height: 40rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #FFFFFF;
  font-size: 24rpx;
  font-weight: 500;
  margin-right: 15rpx;
  box-shadow: 0 2rpx 6rpx rgba(76, 175, 80, 0.2);
}

.detail-step-title {
  font-size: 30rpx;
  font-weight: 600;
  color: #333;
}

.detail-step-content {
  padding-left: 55rpx;
}

.detail-step-content text {
  font-size: 26rpx;
  color: #666;
  line-height: 1.6;
}

.popup-footer {
  display: flex;
  justify-content: center;
  margin-top: 10rpx;
}

.popup-btn {
  width: 260rpx;
  height: 80rpx;
  background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
  color: #FFFFFF;
  font-size: 28rpx;
  font-weight: 500;
  border-radius: 40rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  box-shadow: 0 4rpx 12rpx rgba(76, 175, 80, 0.2);
  transition: all 0.3s;
}

.popup-btn:active {
  transform: scale(0.98);
  box-shadow: 0 2rpx 6rpx rgba(76, 175, 80, 0.2);
}
</style>
