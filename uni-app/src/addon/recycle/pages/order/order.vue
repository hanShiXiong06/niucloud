<template>
    <view class="page">
        <!-- 顶部导航栏 -->
        <view class="nav-header">
            <view class="tabs">
                <view v-for="(tab, index) in ['邮寄到店', '自送到店']" :key="index"
                    :class="['tab-item', currentTab === index ? 'active' : '']" @tap="switchTab(index)">
                    {{ tab }}
                </view>
            </view>
            <view class="order-link" @tap="toOrderList">
                <up-icon color="#fff" name="list" size="18"></up-icon>
                <text>我的订单</text>
            </view>
        </view>
       

        <u-form :model="form" :rules="rules" ref="formRef" label-position="left">
            <!-- 出货信息 -->
            <view class="box">
                <view class="title">
                    <up-icon name="info-circle" size="16" color="#3b82f6"></up-icon>
                    <text>出货信息</text>
                  
                   
                    <!-- <view class="scan-btn" @click="scanIMEI">
                        <up-icon name="scan" size="14" color="#3b82f6"></up-icon>
                        <text>添加设备</text>
                    </view> -->
                </view>

                <!-- 手机列表 -->
                <view class="phone-list" v-if="phoneList.length > 0">
                    <view class="phone-item" v-for="(item, index) in phoneList" :key="index">
                        <view class="phone-info">
                            <text class="imei"> <text class="index">{{ index + 1 }} </text> IMEI: {{ item.imei }}</text>
                            <text class="model" v-if="item.initial_price">定价: {{ item.initial_price }}</text>
                        </view>
                        <view class="delete-btn" @click="removePhone(index)">
                            <up-icon name="trash" size="14" color="#ef4444"></up-icon>
                        </view>
                    </view>
                </view>

                <!-- <up-collapse v-model="activeHelp" class="help-collapse"> -->
                <!-- <up-collapse-item title="查看IMEI码说明" name="imei-help"> -->

                <!-- </up-collapse-item> -->
                <!-- </up-collapse> -->

                <up-row customStyle="margin-bottom: 8px">
                    <up-col span="3">
                        <view class="label">数量</view>
                    </up-col>
                    <up-col span="9">
                        <u-number-box v-model="form.count" :min="1" :max="99"
                            @change="handleCountChange"></u-number-box>
                    </up-col>
                </up-row>

                <up-row>
                    <up-col span="3">
                        <view class="label">备注</view>
                    </up-col>
                    <up-col span="9">
                        <view class="input-wrapper">
                            <up-textarea autoHeight v-model="form.comment" placeholder="请输入备注信息"></up-textarea>
                        </view>
                    </up-col>
                </up-row>
            </view>

           

            <!-- 寄件信息 -->
            <view class="box mt-2" v-if="currentTab === 0">
                <view class="title">
                    <up-icon name="car" size="16" color="#3b82f6"></up-icon>
                    <text>{{ currentTab === 0 ? '寄件信息' : '自送信息' }}</text>
                    <text  class="title-action text-[#ef4444]"  v-if="currentTab == 0" >  </text>
                    <!-- <view v-if="currentTab == 1" class="title-action" @click="toAddressPage">
                        <up-icon name="plus" size="14"></up-icon>
                        <text>添加地址</text>
                    </view> -->
                </view>

                <!-- 快递单号 - 仅在邮寄模式显示 -->
                <up-row v-if="currentTab === 0" customStyle="margin-bottom: 8px">
                    <up-col span="3">
                        <view class="label">快递单号</view>
                    </up-col>
                    <up-col span="9">
                        <view class="input-wrapper">
                            <u-form-item prop="express_no">
                                <u-input placeholder="输入或扫描快递单号" border="surround" clearable v-model="form.express_no">
                                    <template #suffix>
                                        <up-icon @click="scanCode" name="scan" size="24"></up-icon>
                                    </template>
                                </u-input>
                            </u-form-item>
                        </view>
                    </up-col>
                </up-row>

                <!-- 联系信息 -->
                <!-- <view class="contact-info" v-if="currentTab === 1">
                    <view class="section-header">
                        <text>联系信息</text>
                        <view class="switch-wrapper">
                            <u-switch size="18" v-model="useDefaultAddress"
                                @change="handleDefaultAddressChange"></u-switch>
                            <text class="switch-label">使用默认地址</text>
                        </view>
                    </view>

                    <up-row customStyle="margin-bottom: 8px">
                        <up-col span="3">
                            <view class="label">联系人</view>
                        </up-col>
                        <up-col span="9">
                            <view class="input-wrapper">
                                <u-form-item prop="name">
                                    <u-input v-model="form.customer_name" placeholder="请输入联系人姓名" clearable />
                                </u-form-item>
                            </view>
                        </up-col>
                    </up-row>

                    <up-row>
                        <up-col span="3">
                            <view class="label">联系电话</view>
                        </up-col>
                        <up-col span="9">
                            <view class="input-wrapper">
                                <u-form-item prop="telphone">
                                    <u-input v-model="form.telphone" placeholder="请输入联系电话" clearable />
                                </u-form-item>
                            </view>
                        </up-col>
                    </up-row>
                </view> -->
            </view>
            <!-- 商家信息 -->
            <view class="box mt-2">
                <view class="title">
                    <up-icon name="bag" size="16" color="#3b82f6"></up-icon>
                    <text>商家信息</text>
                    <view class="copy-btn" @click="copyShopInfo">
                        <up-icon size="16" name="cut" />
                    </view>
                </view>

                <view class="shop-info" v-if="shopInfo">
                    <view class="info-row">
                        <text class="label">商家名称：</text>
                        <text class="value">{{ shopInfo.name }}</text>
                    </view>
                    <view class="info-row">
                        <text class="label">联系电话：</text>
                        <text v-if='shopInfo.mobile' class="value">{{ shopInfo.mobile }}</text>
                    </view>
                    <view class="info-row">
                        <text class="label">商家地址：</text>
                        <text class="value">{{ shopInfo.full_address || shopInfo.address }}</text>
                        <up-icon @click="openLocation" name="map" size="16"></up-icon>
                    </view>
                </view>
            </view>
            <!-- 回收协议 -->
            <view class="box rounded mt-2">
                <view class="agreement-section">
                    <view class="agreement-checkbox flex">
                        <u-checkbox-group>
                            <u-checkbox activeColor="var(--primary-color)" :checked="isAgreeRecycle" shape="shape"
                                size="14" @change="handleRecycleAgree" />
                        </u-checkbox-group>
                        <view class="agreement-text flex">
                            我已阅读并同意
                            <view @click="toAgreementPage">
                                <text class="text-primary">《回收服务协议》</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <!-- 提交按钮 -->
            <view class="submit-section">
                <up-button type="primary" @click="submitOrder" text="确认发货"></up-button>
            </view>
        </u-form>
    </view>

    <!-- IMEI输入弹窗 -->
    <uni-popup ref="popup" type="center" background-color="#fff" :mask-click="true">
        <view class="imei-modal">
            <view class="modal-header">
                <text class="modal-title">添加设备</text>
                <view class="close-btn" @click="closePopup">
                    <up-icon name="close" size="16" color="#64748b"></up-icon>
                </view>
            </view>
            <view class="modal-body">
                <view class="form-group">
                    <view class="input-row">
                        <view v-for="(input, index) in inputList" :key="index" class="input-group">
                            <view class="input-wrapper flex-1">
                                <u-input v-model="input.imei" size="14" placeholder="输入IMEI/SN码" border="surround"
                                    clearable></u-input>
                                <view class="scan-btn" @click="() => handleScan(index)">
                                    <up-icon name="scan" size="16" color="#3b82f6"></up-icon>
                                </view>
                            </view>
                            <view class="input-wrapper" v-if="isPriceEnabled">
                                <u-input v-model="input.initial_price" type="number" size="14" placeholder="输入定价"
                                    border="surround" clearable></u-input>
                            </view>
                            <view class="remove-btn" v-if="index > 0" @click="() => removeInput(index)">
                                <up-icon name="trash" size="16" color="#ef4444"></up-icon>
                            </view>
                        </view>
                    </view>
                    <view class="add-input-btn" @click="addInput">
                        <up-icon name="plus" size="16" color="#3b82f6"></up-icon>
                        <up-text size="14" text="添加输入框"></up-text>
                    </view>
                    <view class="tip-text">
                        <up-icon name="info-circle" size="14" color="#64748b"></up-icon>
                        <text>如果 输入 SN 过长，可以直接只输入后 6 位</text>
                    </view>
                    <view class="tip-text">
                        <up-icon name="info-circle" size="12" color="#64748b"></up-icon>
                        <text>苹果设备在拨号界面输入*#06#可快速获取 SN 条形码</text>
                    </view>

                </view>

                <!-- 已添加的设备列表 -->
                <view class="device-list" v-if="tempDeviceList.length > 0">
                    <view class="list-header">
                        <text>已添加设备</text>
                        <text class="count">({{ tempDeviceList.length }})</text>
                    </view>
                    <view class="device-item" v-for="(item, index) in tempDeviceList" :key="index">
                        <view class="device-info">
                            <text class="imei">IMEI: {{ item.imei }}</text>
                            <text class="price" v-if="item.initial_price">¥{{ item.initial_price }}</text>
                        </view>
                        <view class="delete-btn" @click="removeDevice(index)">
                            <up-icon name="trash" size="16" color="#ef4444"></up-icon>
                        </view>
                    </view>
                </view>
            </view>
            <view class="modal-footer">
                <view class="left">
                    <u-switch size="18" v-model="isPriceEnabled" activeColor="#3b82f6"></u-switch>
                    <text>启用定价</text>
                </view>
                <view class="right">
                    <view class="btn cancel" @click="closePopup">取消</view>
                    <view class="btn confirm" @click="confirmDevices">确定</view>
                </view>
            </view>
        </view>
    </uni-popup>

    <tabbar addon="recycle" />
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { getShopAddressList } from '@/addon/recycle/api/recycle'
import { createOrder, getRealInfo } from '@/addon/recycle/api/order'
import { getPaymentList } from '@/addon/recycle/api/payment'
import { getAddressList } from '@/app/api/member'
import useMemberStore from "@/stores/member";
import { copy, redirect } from '@/utils/common'
import { useSubscribeMessage } from "@/hooks/useSubscribeMessage";
// 引入 onshow
import { onShow } from '@dcloudio/uni-app';

// 新增 - 当前选中的标签页
const currentTab = ref(0);

// 新增 - 退货方式


const address = ref('');
const form = ref({
    count: 1,
    express_no: '',
    customer_name: '测试',
    customer_phone: '13122220000',
    telphone: useMemberStore()?.info?.mobile || '',
    comment: '',
    delivery_type: (currentTab.value + 1), // 1-寄件 2-收件

});


// 校验规则
const rules = {
    express_no: {
        required: (rule, value, callback) => {
            if (form.value.delivery_type === '1' && !value) {
                callback(new Error('快递单号不能空'));
            } else {
                callback();
            }
        },
        trigger: 'blur'
    },

};

const formRef = ref(null);

// 开关状态
const useDefaultAddress = ref(false);

// 切换标签页
const switchTab = (index) => {

    currentTab.value = index;
    form.value.delivery_type = index === 0 ? 1 : 2;
    // 切换到自送时清空快递单号
    if (index === 1) {
        form.value.express_no = '';
    }
};

// 跳转到订单列表
const toOrderList = () => {
    uni.navigateTo({
        url: '/addon/recycle/pages/order/list'
    });
};

// 获取默认地址
const loadDefaultAddress = async () => {
    try {
        const res = await getAddressList({});
        if (res.code === 1 && res.data) {
            const defaultAddress = res.data.find(item => item.is_default === 1);
            if (defaultAddress) {
                form.value.customer_name = defaultAddress.name;
                form.value.telphone = defaultAddress.mobile;
            }
        }
    } catch (error) {
        uni.showToast({
            title: '获取默认地址失败',
            icon: 'none'
        });
    }
};

// 处理默认地址切换
const handleDefaultAddressChange = async (value) => {
    if (value) {
        await loadDefaultAddress();
    } else {
        form.value.customer_name = '';
        form.value.telphone = '';
    }
};

const fetchAddress = async () => {
    try {
        const res = await getShopAddressList();
        address.value = res.data.data[0].full_address;
        shopInfo.value.name = res.data.data[0].contact_name;
        shopInfo.value.mobile = res.data.data[0].mobile;
        shopInfo.value.full_address = res.data.data[0].full_address;
        shopInfo.value.address = res.data.data[0].address;
        shopInfo.value.lat = res.data.data[0].lat;
        shopInfo.value.lng = res.data.data[0].lng;
    } catch (error) {
        uni.showToast({
            title: '无邮寄地址!请系商家',
            icon: 'none'
        });
        console.error("获取地址失败", error);
    }
};

const scanCode = () => {
    uni.scanCode({
        onlyFromCamera: true,
        success: res => {
            if (res.errMsg === 'scanCode:ok') form.value.express_no = res.result;
            else uni.showToast({ title: res.errorMsg, icon: 'none' });
        }
    });
};

// 手机列表
const phoneList = ref<Array<{ imei: string, initial_price?: string }>>([]);

// IMEI 输入相关
const popup = ref(null)
const isPriceEnabled = ref(false)
const tempDeviceList = ref<Array<{ imei: string, initial_price?: string }>>([])
const inputList = ref<Array<{ imei: string, initial_price: string }>>([
    { imei: '', initial_price: '' }
])

// 添加输入框
const addInput = () => {
    inputList.value.push({ imei: '', initial_price: '' })
}

// 移除输入框
const removeInput = (index: number) => {
    inputList.value.splice(index, 1)
}

// 打开弹窗
const openPopup = () => {
    popup.value.open()
    tempDeviceList.value = []
    isPriceEnabled.value = false
    inputList.value = [{ imei: '', initial_price: '' }]
}

// 关闭弹窗
const closePopup = () => {
    popup.value.close()
    tempDeviceList.value = []
    isPriceEnabled.value = false
    inputList.value = [{ imei: '', initial_price: '' }]
}

// 处理扫码
const handleScan = (index: number) => {
    uni.scanCode({
        onlyFromCamera: true,
        success: (res) => {
            inputList.value[index].imei = res.result;
        },
        fail: () => {
            uni.showToast({
                title: '扫码失败',
                icon: 'none'
            });
        }
    });
}

// 添加设备到临时列表
const addDevices = () => {
    const validInputs = inputList.value.filter(input => input.imei.trim());

    if (validInputs.length === 0) {
        uni.showToast({
            title: '请输入IMEI码',
            icon: 'none'
        });
        return;
    }

    for (const input of validInputs) {
        // 检查是否重复
        if (tempDeviceList.value.some(item => item.imei === input.imei)) {
            uni.showToast({
                title: `IMEI码 ${input.imei} 已存在`,
                icon: 'none'
            });
            continue;
        }

        const device = {
            imei: input.imei,
            initial_price: isPriceEnabled.value ? input.initial_price : undefined
        };

        tempDeviceList.value.push(device);
    }

    // 清空输入
    inputList.value = [{ imei: '', initial_price: '' }];
}

// 确认添加设备
const confirmDevices = () => {
    // 先添加当前输入框中的设备
    addDevices();

    if (tempDeviceList.value.length === 0) {
        uni.showToast({
            title: '请添加至少一个设备',
            icon: 'none'
        });
        return;
    }

    // 将临时列表中的设备添加到主列表
    tempDeviceList.value.forEach(device => {
        phoneList.value.push({
            imei: device.imei,
            initial_price: device.initial_price
        });
    });

    // 更新设备数量
    form.value.count = phoneList.value.length;

    closePopup();
}

// 扫描IMEI
const scanIMEI = () => {
    openPopup();
};

// 删除手机
const removePhone = (index: number) => {
    phoneList.value.splice(index, 1);
    // 更新数量
    form.value.count = phoneList.value.length;
};

// 处理数量变化
const handleCountChange = (value: number) => {
    // 如果手动修改数量小于已扫描的手机数量，删除多余的手机记录
    if (value < phoneList.value.length) {
        phoneList.value = phoneList.value.slice(0, value);
    }
};

// 提交订单时添加手机列表
const submitOrder = async () => {
    if (!isAgreeRecycle.value) {
        uni.showToast({
            title: '请阅读并同意回收服务协议',
            icon: 'none'
        });
        return;
    }
    // 请求订阅相关消息通知
    await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay');
    form.value.devices = phoneList.value;  // 添加手机列表到表单数据

    const valid = await formRef.value.validate();
    if (valid) {
        createOrder(form.value).then((res: any) => {
            if (res.code === 1) {
                uni.showToast({
                    title: '下单成功',
                    icon: 'success'
                });
                // 清空表单
                formRef.value.resetFields()
                // 跳转到订单列表
                setTimeout(() => {
                    toOrderList();
                }, 1500);
            } else {
                uni.showToast({
                    title: '下单失败,请重试!',
                    icon: 'none'
                });
            }
        });
    } else {
        uni.showToast({
            title: '请完善表单信息',
            icon: 'none'
        });
    }
};

// 跳转到协议页面
const toAgreementPage = () => {
    uni.navigateTo({
        url: '/app/pages/auth/agreement?key=recycle_service'
    });
};

// 检查收款信息
const checkPaymentInfo = async () => {
    try {
        const res = await getPaymentList();
       
        if (res.data.length < 2) {
            // 如果没有收款信息，弹出选择框让用户决定
            uni.showModal({
                title: '提示',
                content: '您尚未输入个人信息，建议至少添加2种收款方式及完善个人信息，以便回收完成后能及时收到款项。',
                confirmText: '立即设置',
                cancelText: '稍后设置',
                success: function(res) {
                    if (res.confirm) {
                        // 用户点击"立即设置"，跳转到收款信息页面
                        uni.navigateTo({
                            url: '/addon/recycle/pages/payment/index'
                        });
                    } else {
                        // 用户点击"稍后设置"，显示提示消息
                        uni.showToast({
                            title: '请记得及时完善收款信息，避免影响回收款项到账',
                            icon: 'none',
                            duration: 3000
                        });
                    }
                }
            });
        }
    } catch (error) {
        console.error('获取收款信息失败：', error);
    }
};

// 打开导航
const openLocation = () => {
    if (!shopInfo.value.lat || !shopInfo.value.lng) {
        uni.showToast({
            title: '无法获取商家位置信息',
            icon: 'none'
        });
        return;
    }

    uni.openLocation({
        latitude: parseFloat(shopInfo.value.lat),
        longitude: parseFloat(shopInfo.value.lng),
        name: shopInfo.value.name || '商家地址',
        address: shopInfo.value.full_address || shopInfo.value.address,
        success: () => {
            console.log('打开导航成功');
        },
        fail: (err) => {
            console.error('打开导航失败：', err);
            uni.showToast({
                title: '打开导航失败',
                icon: 'none'
            });
        }
    });
};

const isAgreeRecycle = ref(false)

// 处理回收协议同意状态
const handleRecycleAgree = () => {
    isAgreeRecycle.value = !isAgreeRecycle.value
}

const shopInfo = ref({
    name: '',
    mobile: '',
    business_hours: '',
    lat: '',
    lng: '',
    full_address: '',
    address: ''
});

const copyShopInfo = () => {
    // name+mobile+full_address
    const text = `${shopInfo.value.name} , ${shopInfo.value.mobile}, ${shopInfo.value.full_address}`;
    copy(text, () => {
        uni.showToast({
            title: '复制成功',
            icon: 'success'
        })
    })
}



// 获取实名信息
// const fetchRealInfo = async () => {
//     const res = await getRealInfo();
//     if (res.code === 1 && res.data) {
//         console.log(res.data);
//         if (res.data.status == 10) {
//             redirect({ url: '/addon/tk_vip/pages/real', type: 'navigateTo' })
//         }
//     }
// }

onShow(async () => {
    // 请求订阅相关消息通知
    await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay');
    // 检查收款信息
    await checkPaymentInfo();
})

onMounted(async () => {
    // await fetchRealInfo()
    fetchAddress();

});
</script>

<style lang="scss" scoped>
.page {
    min-height: 100vh;
    background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
    padding: 12px;
}

.nav-header {
    background: linear-gradient(120deg, #4f46e5, #3b82f6, #0ea5e9);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.15), 0 2px 4px -2px rgba(59, 130, 246, 0.1);
    position: relative;
    overflow: hidden;

    &::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg,
                rgba(255, 255, 255, 0.12) 0%,
                rgba(255, 255, 255, 0.06) 50%,
                rgba(255, 255, 255, 0) 100%);
        pointer-events: none;
    }
}

.tabs {
    display: flex;
    gap: 12px;
    position: relative;
    z-index: 1;

    .tab-item {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 14px;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(4px);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);

        &::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        &:active::before {
            opacity: 1;
        }

        &.active {
            background: #fff;
            color: #4f46e5;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
            border-color: #fff;

            &::after {
                content: '';
                position: absolute;
                bottom: -1px;
                left: 50%;
                transform: translateX(-50%);
                width: 4px;
                height: 4px;
                border-radius: 2px;
                background: #4f46e5;
            }
        }
    }
}

.order-link {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    padding: 6px 12px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(4px);
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
    border: 1px solid rgba(255, 255, 255, 0.1);

    &:active {
        transform: translateY(1px);
        background: rgba(255, 255, 255, 0.18);
    }

    &:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .up-icon {
        opacity: 0.9;
    }

    text {
        font-weight: 500;
    }
}

.box {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    
    /* 根据box用途应用不同的莫兰迪色系 */
    &:nth-of-type(1) {
        /* 出货信息区块 - 使用淡粉莫兰迪色 */
        background: rgb(255, 255, 255);        border-left: 4px solid #D8C1C1;
    }
    
    &:nth-of-type(2) {
        /* 寄件信息区块 - 使用淡蓝莫兰迪色 */
        background: rgb(255, 255, 255);
        border-left: 4px solid #B4C7D6;
    }
    
    &:nth-of-type(3) {
        /* 商家信息区块 - 使用淡绿莫兰迪色 */
        background: rgb(255, 255, 255);
        border-left: 4px solid #B8C8C2;
    }
}

.title {
    display: flex;
    align-items: center;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;

    .up-icon {
        margin-right: 4px;
    }
    
    /* 为不同区块的标题添加莫兰迪色系 */
    .box:nth-of-type(1) & {
        color: #8C7575; /* 出货信息标题颜色 */
    }
    
    .box:nth-of-type(2) & {
        color: #5F758A; /* 寄件信息标题颜色 */
    }
    
    .box:nth-of-type(3) & {
        color: #657A6E; /* 商家信息标题颜色 */
    }
}

.phone-list {
    margin-bottom: 12px;
}

.phone-item {
    background: rgba(241, 237, 237, 0.8);
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid #D1C2C2;
}

.phone-info {
    display: flex;
    flex-direction: column;
    gap: 4px;

    .imei {
        font-size: 14px;
        color: #1f2937;
    }

    .model {
        font-size: 12px;
        color: #6b7280;
    }
}

.empty-tip {
    font-size: 12px;
    color: #6b7280;
    padding: 8px;
    background: #f8fafc;
    border-radius: 6px;
    margin-bottom: 12px;
}

.index {
    color: #4f46e5;
    font-weight: 500;
    margin-right: 4px;

}

.label {
    font-size: 14px;
    color: #374151;
}

.input-wrapper {
    width: 100%;
    overflow: hidden;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.switch-wrapper {
    display: flex;
    align-items: center;
    gap: 4px;

    .switch-label {
        font-size: 12px;
        color: #6b7280;
    }
}

.info-row {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;

    .label {
        color: #6b7280;
        width: 80px;
    }

    .value {
        color: #1f2937;
        flex: 1;
    }

    .up-icon {
        margin-left: 8px;
        color: #3b82f6;
    }
}

.scan-btn {
    margin-right: 18px;
}

.scan-btn,
.copy-btn {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
}

.title-action {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;

}

.help-collapse {
    margin: 8px 0 12px;
    border-radius: 6px;
    overflow: hidden;
    background: #f8fafc;

    :deep(.up-collapse-item__title) {
        font-size: 13px;
        color: #6b7280;
        padding: 8px 12px;
    }
}

.help-content {
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;

    text {
        font-size: 13px;
        color: #6b7280;

        &.sub-text {
            font-size: 12px;
            color: #9ca3af;
        }
    }
}

.mt-2 {
    margin-top: 12px;
}

.imei-modal {
    width: 320px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 16px 12px;
        border-bottom: 1px solid #e5e7eb;

        .modal-title {
            font-size: 16px;
            font-weight: 500;
            color: #1f2937;
        }

        .close-btn {
            padding: 4px;
            border-radius: 4px;
            cursor: pointer;

            &:active {
                background: #f1f5f9;
            }
        }
    }

    .modal-body {
        padding: 16px;
        max-height: 60vh;
        overflow-y: auto;

        .form-group {
            margin-bottom: 16px;

            .input-row {
                display: flex;
                flex-direction: column;
                gap: 12px;

                .input-group {
                    display: flex;
                    gap: 8px;
                    align-items: flex-start;

                    .input-wrapper {
                        position: relative;
                        display: flex;
                        align-items: center;
                        flex: 1;

                        .scan-btn {
                            position: absolute;
                            right: 8px;
                            padding: 4px;
                            border-radius: 4px;
                            cursor: pointer;

                            &:active {
                                background: #f1f5f9;
                            }
                        }
                    }

                    .input-wrapper:nth-child(1) {
                        flex: 2;
                    }

                    .remove-btn {
                        margin-top: 8px;
                        padding: 4px;
                        border-radius: 4px;
                        cursor: pointer;

                        &:active {
                            background: #fee2e2;
                        }
                    }
                }
            }

            .add-input-btn {
                display: flex;
                align-items: center;
                gap: 4px;
                margin-top: 8px;
                padding: 6px 12px;
                border-radius: 6px;
                background: #f1f5f9;
                cursor: pointer;

                text {
                    font-size: 14px;
                    color: #3b82f6;
                }

                &:active {
                    background: #e2e8f0;
                }
            }

            .tip-text {
                display: flex;
                align-items: center;
                gap: 4px;
                margin-top: 8px;

                text {
                    font-size: 12px;
                    color: #64748b;
                }
            }
        }

        .device-list {
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;

            .list-header {
                display: flex;
                align-items: center;
                gap: 4px;
                margin-bottom: 8px;
                font-size: 14px;
                color: #1f2937;

                .count {
                    color: #64748b;
                }
            }

            .device-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px;
                background: #f8fafc;
                border-radius: 4px;
                margin-bottom: 8px;

                .device-info {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;

                    .imei {
                        font-size: 14px;
                        color: #1f2937;
                    }

                    .price {
                        font-size: 12px;
                        color: #3b82f6;
                    }
                }

                .delete-btn {
                    padding: 4px;
                    border-radius: 4px;
                    cursor: pointer;

                    &:active {
                        background: #fee2e2;
                    }
                }
            }
        }
    }

    .modal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-top: 1px solid #e5e7eb;

        .left {
            display: flex;
            align-items: center;
            gap: 8px;

            text {
                font-size: 14px;
                color: #64748b;
            }
        }

        .right {
            display: flex;
            gap: 8px;

            .btn {
                padding: 6px 16px;
                border-radius: 6px;
                font-size: 14px;
                cursor: pointer;

                &.cancel {
                    color: #64748b;
                    background: #f1f5f9;

                    &:active {
                        background: #e2e8f0;
                    }
                }

                &.confirm {
                    color: #fff;
                    background: #3b82f6;

                    &:active {
                        background: #2563eb;
                    }
                }
            }
        }
    }
}



/* 修改协议和提交按钮部分 */
.agreement-section {
    
    padding: 8px;
    border-radius: 6px;
}

.submit-section {
    margin-top: 24px;
    
    :deep(.up-button--primary) {
        background: linear-gradient(to right, #8C7575, #5F758A);
        border: none;
    }
}
</style>