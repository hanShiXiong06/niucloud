<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
        <view class="sidebar-margin  card-template !py-[20rpx] my-[20rpx]">
            <u-form labelPosition="left" :model="formData" :label-style="{'font-size':'28rpx'}"  class="pl-[15rpx]" labelWidth="150rpx" errorType='toast' :rules="rules" ref="baseFormRef">
                <view class="form-wrap image-form">
                    <u-form-item label="商品图片" prop="goods_image" required>
                        <view class="mt-[10rpx] flex-1">
                            <shmily-drag-image
							ref="goodsShmilyDragImgRef"
							v-model:list.sync="formData.goods_image"
							:imageWidth="126"
							:imageHeight="126"
							:number="10"
                            uploadMethod="image"
							:isAWait="isAWait"
						></shmily-drag-image>
                        </view>
                    </u-form-item>
                </view>
                <view class="form-wrap image-form" v-if="spread">
                    <u-form-item label="商品视频" prop="goods_video">
                        <view class="mt-[10rpx]">
                            <view class="flex items-center justify-center w-[110rpx] h-[110rpx] border-[2rpx] border-dashed border-[#ddd] text-center text-[var(--text-color-light9)] rounded-[var(--goods-rounded-small)]" @click="addVideo" v-if="!formData.goods_video">
                                <view>
                                    <view class="nc-iconfont nc-icon-a-shipinV6xx-28-1 text-[50rpx] mb-[10rpx]"></view>
                                    <view class="text-[22rpx] ">添加视频</view>
                                </view>
                            </view>
                            <view class="relative flex items-center justify-center w-[110rpx] h-[110rpx] rounded-[var(--goods-rounded-small)] overflow-hidden" v-else>
                                <view>
                                   <video class="w-[110rpx] h-[110rpx] align-middle" :src="img(formData.goods_video)" @click="videoPreview"  objectFit="cover" :controls="false" :show-fullscreen-btn="false" :show-center-play-btn="false" :show-play-btn="false" :enable-progress-gesture="false" ></video>
                                </view>
                                <view class="absolute top-0 right-0 bg-[#373737] z-100 flex justify-end items-center h-[34rpx] w-[34rpx] rounded-bl-[20rpx]" @click.stop="deleteVideo">
                                    <text class="nc-iconfont nc-icon-guanbiV6xx !text-[24rpx]  text-[#fff]"></text>
                                </view>
                            </view>

                        </view>
                    </u-form-item>
                </view>
                <view class="form-wrap">
                    <u-form-item label="商品名称" prop="goods_name" required>
                        <view class="h-[100rpx] flex-1 mt-[10rpx]">
                            <textarea class="leading-[1.5] h-[100%] w-[100%] text-[26rpx]" v-model.trim="formData.goods_name" :maxlength="60" cols="30" rows="5" placeholder="最多输入60个字符（30个汉字）" placeholder-class="text-[26rpx] !text-[var(--text-color-light9)]"></textarea>
                            <view class="text-[24rpx] text-[#666] text-right">{{ formData.goods_name.length }}/60</view>
                        </view>
                    </u-form-item>
                </view>
            </u-form>
        </view>
        <view class="sidebar-margin  card-template !py-[20rpx] my-[20rpx]">
            <u-form labelPosition="left" :model="formData" :label-style="{'font-size':'28rpx'}"  class="pl-[15rpx]" labelWidth="150rpx" errorType='toast' :rules="rules" ref="basicFormRef">
                <view>
                    <u-form-item label="商品类型" prop="category_id" required>
                        <view class="flex items-center flex-1" @click="openGoodsType">
                            <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.goods_type }" >{{ formData.goods_type_name ?? '请选择商品类型' }}</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
                <view v-if="spread">
                    <u-form-item label="副标题" prop="sub_title">
                        <u-input fontSize="28rpx" v-model.trim="formData.sub_title" border="none"  maxlength="80" placeholder="请输入副标题" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                    </u-form-item>
                </view>
                <view>
                    <u-form-item label="商品类目" prop="goods_mall_category" required>
                        <view class="flex items-center flex-1" @click="showGoodsMallCategory  = true">
                            <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.goods_mall_category.length }" >{{ formData.goods_mall_category_name ? formData.goods_mall_category_name : '请选择商品类目' }}</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
                <view>
                    <u-form-item label="商品分类" prop="goods_category">
                        <view class="flex items-center flex-1" @click="handleGoodsCategory">
                            <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.goods_category.length }" >{{ formData.goods_category_name ? formData.goods_category_name : '请选择商品分类' }}</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
                <template v-if="spread">
                    <view>
                        <u-form-item label="商品品牌">
                            <view class="flex items-center flex-1" @click="showBrandPopup = true">
                                <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.brand_id }" >{{ formData.brand_name ? formData.brand_name : '请选择商品品牌' }}</view>
                                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                            </view>
                        </u-form-item>
                    </view>
                    <view>
                        <u-form-item label="商品标签">
                            <view class="flex items-center flex-1" @click="showLabelPopup = true">
                                <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.label_ids.length }" >{{ formData.label_name.length ? formData.label_name : '请选择商品标签' }}</view>
                                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                            </view>
                        </u-form-item>
                    </view>
                    <view>
                        <u-form-item label="商品服务">
                            <view class="flex items-center flex-1" @click="showServicePopup = true">
                                <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.service_ids.length }" >{{ formData.service_name.length ? formData.service_name : '请选择商品服务' }}</view>
                                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                            </view>
                        </u-form-item>
                    </view>
                </template>
                
                <view class="status-wrap">
                    <u-form-item label="商品状态" prop="status" required>
                        <view class="flex-1 flex justify-end">
                            <u-radio-group v-model="formData.status" placement="row"  iconPlacement="left">
                                <u-radio activeColor="var(--primary-color)" size="32rpx"  :labelSize="'28rpx'" labelColor="#333" label="上架" :name="1"></u-radio>
                                <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'28rpx'" labelColor="#333" label="下架" :name="0"></u-radio>
                            </u-radio-group>
                        </view>
                    </u-form-item>
                </view>
                <template v-if="spread">
                    <view>
                        <u-form-item label="单位" prop="unit">
                            <u-input fontSize="28rpx" v-model.trim="formData.unit" border="none"  maxlength="80" placeholder="请输入单位，默认为：件" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                        </u-form-item>
                    </view>
                    <template v-if="formData.goods_type == 'virtual'">
                        <view class="status-wrap">
                            <u-form-item label="发货设置" prop="virtual_auto_delivery">
                                <view class="flex-1 flex justify-end">
                                    <u-radio-group v-model="formData.virtual_auto_delivery" placement="row"  iconPlacement="left">
                                        <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="自动发货" name="1" :disabled="isDisabledVirtual"></u-radio>
                                        <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="手动发货" name="0" :disabled="isDisabledVirtual"></u-radio>
                                    </u-radio-group>
                                </view>
                            </u-form-item>
                        </view>
                        <view>
                            <u-form-item label="收货设置" prop="virtual_receive_type" >
                                <view class="flex items-center flex-1" @click="showVirtualReceiveTypePicker  = true">
                                    <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.virtual_receive_type }" >{{ formData.virtual_receive_type_name ?? '请选择收货设置' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                            <view class="text-[22rpx] text-[#999] mb-[10rpx]" v-show="formData.virtual_receive_type == 'verify'">当设置为店内核销时，若存在未完成的订单，则无法编辑</view>
                        </view>
                        <view v-if="formData.virtual_receive_type == 'verify'">
                            <u-form-item label="核销有效期" prop="virtual_verify_type" >
                                <view class="flex items-center flex-1" @click="showVirtualVerifyTypePicker  = true">
                                    <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.virtual_verify_type }" >{{ formData.virtual_verify_type_name ?? '请选择核销有效期' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                        </view>
                        <view v-if="formData.virtual_receive_type == 'verify' && formData.virtual_verify_type == 1" prop="virtual_indate_day">
                            <u-form-item label="有效期" prop="virtual_indate_day" required>
                                <u-input fontSize="28rpx" v-model.trim="formData.virtual_indate_day" border="none"  maxlength="5" placeholder="请输入有效期" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                            </u-form-item>
                        </view>
                        <view v-if="formData.virtual_receive_type == 'verify' && formData.virtual_verify_type == 2" >
                            <u-form-item label="有效期" prop="virtual_indate" required>
                                <view class="flex items-center flex-1" @click="virtualIndate  = true">
                                    <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.virtual_indate }">{{ timeStampTurnTime(formData.virtual_indate) ?? '请选择有效期' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                            <u-datetime-picker v-model="formData.virtual_indate" :show="virtualIndate" mode="datetime" :minDate="new Date().valueOf()" @cancel="virtualIndate = false" @confirm="updateVirtualIndate"></u-datetime-picker>
                        </view>
                        <view class="status-wrap">
                            <u-form-item label="核销手机号" prop="virtual_verify_mobile">
                                <view class="flex-1 flex justify-end">
                                    <u-radio-group v-model="formData.virtual_verify_mobile" placement="row"  iconPlacement="left">
                                        <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="开启" name="1"></u-radio>
                                        <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="关闭" name="0"></u-radio>
                                    </u-radio-group>
                                </view>
                            </u-form-item>
                        </view>
                    </template>
                    <view>
                        <u-form-item label="排序" prop="shop_sort">
                            <u-input fontSize="28rpx" v-model.trim="formData.shop_sort" border="none"  maxlength="80" placeholder="请输入排序" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                        </u-form-item>
                    </view>
                </template>
            </u-form>
        </view>
        <view class="sidebar-margin  card-template !py-[20rpx] my-[20rpx]">
            <u-form labelPosition="left" :model="formData" :label-style="{'font-size':'28rpx'}"  class="pl-[15rpx]" labelWidth="180rpx" errorType='toast' :rules="rules" ref="priceStockFormRef">
                <view class="status-wrap">
                    <u-form-item label="规格类型" prop="spec_type" required>
                        <view class="flex-1 flex justify-end">
                            <u-radio-group v-model="formData.spec_type" placement="row"  iconPlacement="left">
                                <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="单规格" name="single"></u-radio>
                                <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="多规格" name="multi"></u-radio>
                            </u-radio-group>
                        </view>
                    </u-form-item>
                </view>
                <template v-if="formData.spec_type === 'single'">
                    <view>
                        <u-form-item label="销售价" prop="price" required>
                            <u-input fontSize="28rpx" v-model.trim="formData.price" border="none"  maxlength="80" placeholder="0.00" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                            <text class="ml-[10rpx] text-[28rpx]">元</text>
                        </u-form-item>
                    </view>
                    <template v-if="spread">
                        <view>
                            <u-form-item label="划线价" prop="market_price">
                                <u-input fontSize="28rpx" v-model.trim="formData.market_price" border="none"  maxlength="80" placeholder="0.00" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                                <text class="ml-[10rpx] text-[28rpx]">元</text>
                            </u-form-item>
                        </view>
                        <view>
                            <u-form-item label="成本价" prop="cost_price">
                                <u-input fontSize="28rpx" v-model.trim="formData.cost_price" border="none"  maxlength="80" placeholder="0.00" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                                <text class="ml-[10rpx] text-[28rpx]">元</text>
                            </u-form-item>
                        </view>
                        <template v-if="formData.goods_type == 'real'">
                            <view>
                                <u-form-item label="重量" prop="weight">
                                    <u-input fontSize="28rpx" v-model.trim="formData.weight" border="none"  maxlength="80" placeholder="0.000" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                                    <text class="ml-[10rpx] text-[28rpx]">kg</text>
                                </u-form-item>
                            </view>
                            <view>
                                <u-form-item label="体积" prop="volume">
                                    <u-input fontSize="28rpx" v-model.trim="formData.volume" border="none"  maxlength="80" placeholder="0.000" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                                    <text class="ml-[10rpx] text-[28rpx]">m³</text>
                                </u-form-item>
                            </view>
                        </template>
                    </template>
                    <view>
                        <u-form-item label="商品库存" prop="stock" required>
                            <u-input fontSize="28rpx" v-model.trim="formData.stock" border="none"  maxlength="80" placeholder="请输入商品库存" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                            <text class="ml-[10rpx] text-[28rpx]">{{ formData.unit ? formData.unit : '件' }}</text>
                        </u-form-item>
                    </view>
                    <view v-if="spread">
                        <u-form-item label="商品编码" prop="sku_no">
                            <u-input fontSize="28rpx" v-model.trim="formData.sku_no" border="none"  maxlength="80" placeholder="请输入商品编码" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                        </u-form-item>
                    </view>
                </template>
                <template v-if="formData.spec_type === 'multi'">
                    <view>
                        <u-form-item label="设置商品规格" labelWidth="180rpx" required>
                            <view class="flex items-center flex-1" @click="redirect({url: '/addon/mall/pages/goods/spec', param: {goods_type: 'real', active_goods_count: activeGoodsCount}})">
                                <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !Object.values(goodsSkuData).length }" v-if="!Object.values(goodsSkuData).length">去设置</view>
                                
                                <view v-else class="ml-auto flex-1 max-w-[420rpx] flex-nowrap flex items-center justify-end overflow-hidden">
                                    <view v-for="(item, key, index) in goodsSkuData" :key="key" class="flex-shrink-0 flex items-center bg-[var(--page-bg-color)] rounded-[10rpx] px-[10rpx] py-[2rpx] mx-[5rpx]">
                                        <up-image class="rounded-[10rpx] overflow-hidden" width="40rpx" height="40rpx" :src="img(item.sku_image)" mode="aspectFill">
                                            <template #error>
                                                <image class="w-[40rpx] h-[40rpx] rounded-[10rpx] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                                            </template>
                                        </up-image>
                                        <text class="ml-[10rpx]">{{ item.spec_name_show }}</text>
                                    </view>
                                    <text class="flex-shrink-0">共{{ Object.values(goodsSkuData).length }}个</text>
                                </view>
                                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                            </view>
                        </u-form-item>
                    </view>
                </template>
                <template v-if="spread">
                    <view>
                        <u-form-item label="会员等级折扣">
                            <view class="flex items-center flex-1" @click="showMemberDiscountPopup = true">
                                <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.member_discount }" >{{ formData.member_discount_name ? formData.member_discount_name : '请选择会员等级折扣' }}</view>
                                <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                            </view>
                        </u-form-item>
                    </view>
                    <view>
                        <u-form-item label="是否限购" prop="is_limit">
                            <view class="flex-1 flex justify-end">
                                <up-switch v-model="formData.is_limit" size="20" :activeValue="1" :inactiveValue="0"></up-switch>
                            </view>
                        </u-form-item>
                    </view>
                    <template v-if="formData.is_limit">
                        <view class="status-wrap">
                            <u-form-item label="限购类型" prop="limit_type">
                                <view class="flex-1 flex justify-end">
                                    <u-radio-group v-model="formData.limit_type" placement="row"  iconPlacement="left">
                                        <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="单次限购" :name="1"></u-radio>
                                        <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="单人限购" :name="2"></u-radio>
                                    </u-radio-group>
                                </view>
                            </u-form-item>
                        </view>
                        <view>
                            <u-form-item label="限购数量" prop="max_buy">
                                <u-input fontSize="28rpx" v-model.trim="formData.max_buy" border="none"  maxlength="80" placeholder="请输入限购数量" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                                <text class="ml-[10rpx] text-[28rpx]">{{ formData.unit ? formData.unit : '件' }}</text>
                            </u-form-item>
                        </view>
                    </template>
                    <view>
                        <u-form-item label="起购数量" prop="min_buy">
                            <u-input fontSize="28rpx" v-model.trim="formData.min_buy" border="none"  maxlength="80" placeholder="请输入起购数量" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                            <text class="ml-[10rpx] text-[28rpx]">{{ formData.unit ? formData.unit : '件' }}</text>
                        </u-form-item>
                    </view>
                </template>
            </u-form>
        </view>
        <view class="sidebar-margin  card-template my-[20rpx] !py-0" v-if="formData.goods_type == 'real'">
            <u-form labelPosition="left" :model="formData" :label-style="{'font-size':'28rpx'}"  class="pl-[15rpx]" labelWidth="180rpx" errorType='toast' :rules="rules" ref="deliveryFormRef">
                <view class="delivery-wrap">
                    <u-form-item label="配送方式" prop="delivery_type" required>
                        <view class="flex items-center flex-1" @click="showDeliveryTypePopup  = true">
                            <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.delivery_type.length }" >{{ formData.delivery_type.length ? formData.delivery_type_name : '请选择配送方式' }}</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
                <view v-show="formData.delivery_type.indexOf('express') != -1">
                    <u-form-item label="是否免邮" prop="is_free_shipping">
                        <view class="flex-1 flex justify-end">
                            <up-switch v-model="formData.is_free_shipping" size="20" :activeValue="1" :inactiveValue="0" activeColor="var(--primary-color)"></up-switch>
                        </view>
                    </u-form-item>
                </view>
                <view class="status-wrap" v-show="formData.is_free_shipping == 0">
                    <u-form-item label="运费设置" prop="fee_type">
                        <view class="flex-1 flex justify-end">
                            <u-radio-group v-model="formData.fee_type" placement="row"  iconPlacement="left">
                                <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="选择模板" name="template"></u-radio>
                                <u-radio activeColor="var(--primary-color)" size="32rpx" :labelSize="'30rpx'" labelColor="#333" label="统一运费" name="fixed"></u-radio>
                            </u-radio-group>
                        </view>
                    </u-form-item>
                </view>
                <view v-show="formData.is_free_shipping == 0 && formData.fee_type == 'fixed'">
                    <u-form-item label="固定运费" prop="delivery_money">
                        <u-input fontSize="28rpx" v-model.trim="formData.delivery_money" border="none"  maxlength="80" placeholder="0.00" placeholderClass="!text-[var(--text-color-light9)] text-[28rpx]" inputAlign="right" />
                        <text class="ml-[10rpx] text-[28rpx]">元</text>
                    </u-form-item>
                </view>
                <view v-show="formData.is_free_shipping == 0 && formData.fee_type == 'template'">
                    <u-form-item label="运费模板">
                        <view class="flex items-center flex-1" @click="showDeliveryTemplatePopup = true">
                            <view class="flex-1 text-right" :class="{'text-[var(--text-color-light9)]': !formData.delivery_template_id }" >{{ formData.delivery_template_name ? formData.delivery_template_name : '请选择运费模板' }}</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
            </u-form>
        </view>
        <view class="sidebar-margin  card-template my-[20rpx] !py-0">
            <u-form labelPosition="left" :model="formData" :label-style="{'font-size':'28rpx'}"  class="pl-[15rpx]" labelWidth="180rpx" errorType='toast' :rules="rules" ref="detailFormRef">
                <view>
                    <u-form-item label="商品详情" prop="goods_desc" required> 
                        <view class="flex items-center flex-1" @click="redirect({url: '/addon/mall/pages/goods/content',mode: 'navigateTo'})">
                            <view class="flex-1 text-right">查看</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
            </u-form>
        </view>
        <view class="my-[20rpx] sidebar-margin flex justify-center">
            <view @click="spread = !spread" class="p-[20rpx] rounded-[16rpx] flex justify-center leading-[1]">
                <text class="mr-[10rpx]">{{ spread ? '收起' : '展开全部-填写更多参数'  }}</text>
                <text class="nc-iconfont font-500" :class="{'nc-icon-xiaV6xx' :!spread , 'nc-icon-shangV6xx-1': spread}" ></text>
            </view>
            
        </view>
        <view class="w-full footer">
            <view class="bg-[#fff] py-[var(--top-m)] px-[var(--sidebar-m)]  w-full fixed bottom-0 left-0 right-0 box-border">
                <view class="text-[24rpx] text-[#999] mb-[20rpx]" v-if="supplyDisabled">注：供货商商品暂不支持编辑</view>
                <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500" @click="save" :disabled="supplyDisabled" :loading="repeat" :class="{'opacity-40': supplyDisabled }">保存</button>
            </view>
        </view>
        <!-- 商品类型 -->
        <u-popup :show="showGoodsTypePicker" @close="showGoodsTypePicker = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">商品类型</view>
                <scroll-view scroll-y="true" class="h-[200rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.goods_type" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in goodsType" :key="index" :label="item.name" :name="item.type"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeGoodsType">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 商品类目 -->
        <up-cascader v-model:show="showGoodsMallCategory" v-model="formData.goods_mall_category" :data="goodsMallCategoryOptions" value-key="category_id" label-key="category_name" children-key="child_list" headerDirection="column" :optionsCols="1" v-if="goodsMallCategoryOptions.length" @confirm="handelGoodsMallCategory"></up-cascader>
        <!-- 商品分类 -->
        <goods-category-popup  ref="goodsCategoryRef" @confirm="confirmGoodsCategory"></goods-category-popup>
        <!-- 商品品牌 -->
        <u-popup :show="showBrandPopup" @close="showBrandPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">商品品牌</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.brand_id" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in brandOptions" :key="index" :label="item.brand_name" :name="item.brand_id"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeBrand">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 商品标签 -->
        <u-popup :show="showLabelPopup" @close="showLabelPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">商品标签</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <up-checkbox-group v-model="formData.label_ids" placement="column" iconPlacement="right">
                        <up-checkbox activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" shape="circle"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in labelOptions" :key="index" :label="item.label_name" :name="item.label_id"></up-checkbox>
                    </up-checkbox-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeLabel">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 商品服务 -->
        <u-popup :show="showServicePopup" @close="showServicePopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">商品服务</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <up-checkbox-group v-model="formData.service_ids" placement="column" iconPlacement="right">
                        <up-checkbox activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" shape="circle"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in serviceOptions" :key="index" :label="item.service_name" :name="item.service_id"></up-checkbox>
                    </up-checkbox-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeService">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 会员折扣等级 -->
        <u-popup :show="showMemberDiscountPopup" @close="showMemberDiscountPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">会员等级折扣</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.member_discount" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="不参与" name=""></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="会员折扣" name="discount"></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="指定会员价" name="fixed_price"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeMemberDiscount">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 配送方式 -->
         <u-popup :show="showDeliveryTypePopup" @close="showDeliveryTypePopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">配送方式</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <up-checkbox-group v-model="formData.delivery_type" placement="column" iconPlacement="right">
                        <up-checkbox activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in deliveryTypeCheckBox" :key="index" :label="item.name" :name="item.key"></up-checkbox>
                    </up-checkbox-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeDeliveryType">确定</button>
                </view>
            </view>
        </u-popup>
         
        <!-- 运费模板 -->
        <u-popup :show="showDeliveryTemplatePopup" @close="showDeliveryTemplatePopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">运费模板</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.delivery_template_id" placement="column" iconPlacement="right" v-if="deliveryTemplateOptions.length">
                       <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in deliveryTemplateOptions" :key="index" :label="item.template_name" :name="item.template_id"></u-radio>
                    </u-radio-group>
                    <view class="empty-page" v-else>
                        <image class="img" :src="img('/static/resource/images/system/empty.png')" mode="aspectFill" />
                        <view class="desc">暂无运费模板</view>
                    </view>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeDeliveryTemplate">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 收货设置 -->
        <u-popup :show="showVirtualReceiveTypePicker" @close="showVirtualReceiveTypePicker = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">收货设置</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.virtual_receive_type" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="自动收货" name="auto" :disabled="isDisabledVirtual"></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="买家确认收货" name="artificial" :disabled="isDisabledVirtual"></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="到店核销" name="verify" :disabled="isDisabledVirtual"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeVirtualReceiveType">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 核销有效期 -->
        <u-popup :show="showVirtualVerifyTypePicker" @close="showVirtualVerifyTypePicker = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">核销有效期</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.virtual_verify_type" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="永久" name="0"></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="购买后几日有效" name="1"></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" label="指定过期日期" name="2"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeVirtualVerifyType">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 视频预览 -->
        <u-popup :show="videoShow" @close="videoShow = false" zIndex="999999">
            <view class="h-[100vh] w-[100vw] relative" @touchmove.prevent.stop>
                <text class="fixed top-[20rpx] left-[20rpx]  nc-iconfont nc-icon-guanbiV6xx z-1000 text-[#fff] text-[40rpx]" @click="handleVideo"></text>
                <video class="w-full h-full" ref="videoRef" :src="img(formData.goods_video)" controls autoplay></video>
            </view>
        </u-popup>
        <loading-page :loading="loading"></loading-page>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, reactive, nextTick } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { redirect, img, deepClone, timeStampTurnTime } from '@/utils/common';
import { getGoodsType, getLabelList, addGoods, editGoods, getGoodsInit, getVirtualGoodsInit, addVirtualGoods, editVirtualGoods } from '@/addon/mall/api/goods'
import { getMallCategoryTree } from '@/app/api/base_admin'
import { getBrandList, getServeList, getCategoryTree } from '@/app/api/base_site'
import { getShopDeliveryList, getShippingTemplateList } from '@/app/api/delivery_site'
import goodsCategoryPopup from './components/goods-category-popup.vue'
import ShmilyDragImage from '@/components/shmily-drag-image/shmily-drag-image.vue'

const loading = ref(true)
const repeat = ref(false)
const spread = ref(false)
const formData = reactive<any>({
    goods_id: '',
    goods_type: 'real',
    goods_type_name: '',
    goods_name: '',
    sub_title: '',
    goods_image: [],
    goods_video: '',
    goods_mall_category: [],
    goods_mall_category_name: '',
    goods_category: [],
    goods_category_name: '',
    brand_id: '',
    brand_name: '',
    label_ids: [],
    label_name: [],
    service_ids: [],
    service_name: [],
    status: 1,
    unit: '',
    shop_sort: '',

    spec_type: 'single',
    price: '',
    market_price: '',
    cost_price: '',
    weight: '',
    volume: '',
    stock: '',
    sku_no: '',
    member_discount: '',
    member_discount_name: '不参与',
    is_limit: 0,
    limit_type: 1,
    max_buy: '',
    min_buy: '',
    delivery_type: [],
    delivery_type_name: [],
    is_free_shipping: 1,
    fee_type: 'template',
    delivery_money: '',
    delivery_template_id: '',
    delivery_template_name: '',
    attr_ids: [],
    shop_attr_ids: [],
    goods_desc: '',
    virtual_auto_delivery: '0',
    virtual_receive_type: 'auto',
    virtual_receive_type_name: '自动收货',
    virtual_verify_type: '0',
    virtual_verify_type_name: '永久',
    virtual_indate: '',
    virtual_indate_day: '0', // 几日有效的有效期
    virtual_verify_mobile: '0'
})
const isAWait = ref(false) 
const supplyDisabled = ref(false);

// 追加刷新商品sku数据
const appendRefreshGoodsSkuData = reactive<any>({
    // 重量
    weight: {
        value: '',
        regExp: 'special',
        message: '重量(kg)格式输入错误'
    },
    // 体积
    volume: {
        value: '',
        regExp: 'special',
        message: '体积(m³)格式输入错误'
    }
})

const goodsSkuData: any = reactive({}) // 商品SKU规格数据
const goodsSpecFormat: any = reactive([]) // 规格项/规格值

const activeGoodsCount: any = ref(0)

const baseFormRef = ref<any>()
const basicFormRef = ref<any>()
const priceStockFormRef = ref<any>()
const deliveryFormRef = ref<any>()
const goodsArgumentsFormRef = ref<any>()
const detailFormRef = ref<any>()
const goodsShmilyDragImgRef = ref<any>()

onLoad(async (option: any) => {
    formData.goods_id = option.goods_id || ''
    formData.goods_type = option.goods_type || 'real'
    clearStoreage()
    if(formData.goods_id){
        isAWait.value = true;
        if (formData.goods_type == 'real') {
            await getGoodsInitFn()
        } else {
            await getGoodsVirtualInitFn()
        }
    } else {
        isAWait.value = false;
        loading.value = false
    }
    getGoodsTypeFn()
    refreshMallGoodsCategory()
    getCategoryTreeFn()
    refreshGoodsBrand()
    refreshGoodsLabel()
    refreshGoodsService()
    getShopDeliveryListFn()
    refreshDeliveryTemplate()
    
})
onShow(() => {
    refreshData()
})

 // 生成随机数
const generateRandom = (len: number = 5) => {
    return Number(Math.random().toString().substr(3, len) + Date.now()).toString(36)
}

const orderGoodsCount = ref(0)

const isDisabledVirtual = computed(() => {
    if (formData.virtual_receive_type == 'verify' && orderGoodsCount.value > 0) {
        // 虚拟商品，并且设置为店内核销，若存在订单，则禁用，无法编辑
        return true
    }
    return false
})

const handleGoodsInit = (data: any) => {
    if (formData.goods_id && data.goods_info) {

        // 商品参与营销活动的数量
        activeGoodsCount.value = data.goods_info.active_goods_count;
        if(data.goods_info.supply_id){
            supplyDisabled.value = true;
        }

        // 基础信息
        formData.goods_name = data.goods_info.goods_name
        formData.sub_title = data.goods_info.sub_title
        formData.goods_type = data.goods_info.goods_type
        formData.goods_image = data.goods_info.goods_image.split(',')
        formData.goods_video = data.goods_info.goods_video
        formData.goods_mall_category = data.goods_info.goods_mall_category
        formData.goods_category = data.goods_info.goods_category
        formData.brand_id = data.goods_info.brand_id
        formData.label_ids = data.goods_info.label_ids
        formData.service_ids = data.goods_info.service_ids
        formData.shop_sort = data.goods_info.shop_sort
        // 上下架
        if (['1', '0'].indexOf(data.goods_info.status) != -1) {
            formData.status = Number(data.goods_info.status)
        } else {
            formData.status = 0
        }


        // 价格库存
        formData.spec_type = data.goods_info.spec_type
        formData.stock = data.goods_info.stock      

        if (formData.spec_type == 'single') {
            // 单规格
            const skuInfo = data.goods_info.sku_list[0]
            formData.price = skuInfo.price
            formData.market_price = skuInfo.market_price
            formData.cost_price = skuInfo.cost_price
            formData.sku_no = skuInfo.sku_no
            formData.weight = skuInfo.weight
            formData.volume = skuInfo.volume
        } else if (formData.spec_type == 'multi') {
            Object.assign(goodsSpecFormat, [])
            Object.assign(goodsSkuData, {})
            // 多规格
            const specList = data.goods_info.spec_list
            specList.forEach((item: any) => {
                const values: any = []
                item.spec_values = item.spec_values.split(',')
                item.spec_values.forEach((v: any) => {
                    values.push({
                        id: generateRandom(),
                        spec_value_name: v
                    })
                })
                goodsSpecFormat.push({
                    id: generateRandom(),
                    spec_id: item.spec_id,
                    goods_id: item.goods_id,
                    spec_name: item.spec_name,
                    values
                })
            })
            refreshGoodsSkuData()
            const skuList = data.goods_info.sku_list
            for (let key in goodsSkuData) {
                for (let i = 0; i < skuList.length; i++) {
                    let item = skuList[i];
                    if (goodsSkuData[key].spec_name == item.sku_spec_format.replace(/,/g, ' ')) {
                        goodsSkuData[key].sku_id = item.sku_id;
                        goodsSkuData[key].sku_image = item.sku_image;
                        goodsSkuData[key].price = item.price;
                        goodsSkuData[key].market_price = item.market_price;
                        goodsSkuData[key].cost_price = item.cost_price;
                        goodsSkuData[key].min_price = item.min_price;
                        goodsSkuData[key].max_price = item.max_price;

                        for (let field in appendRefreshGoodsSkuData) {
                            goodsSkuData[key][field] = item[field];
                        }

                        goodsSkuData[key].stock = item.stock;
                        goodsSkuData[key].sku_id = item.sku_id;
                        goodsSkuData[key].sku_no = item.sku_no;
                        goodsSkuData[key].is_default = item.is_default;
                        goodsSkuData[key].is_sell = item.is_sell;
                        break;
                    }
                }
            }
            uni.setStorageSync("editGoodsSpecFormat", JSON.stringify(goodsSpecFormat));
		    uni.setStorageSync("editGoodsSkuData", JSON.stringify(goodsSkuData));
            
        }

        formData.member_discount = data.goods_info.member_discount
        formData.member_discount_name = formData.member_discount === 'discount' ? '会员折扣' : formData.member_discount === 'fixed_price' ? '指定会员价' : '不参与'
        
        formData.unit = data.goods_info.unit
        formData.is_limit = data.goods_info.is_limit
        formData.limit_type = data.goods_info.limit_type
        formData.max_buy = data.goods_info.max_buy
        formData.min_buy = data.goods_info.min_buy

        // 配送设置
        formData.delivery_template_name = data.goods_info.delivery_template_name

        // 商品详情
        formData.goods_desc = data.goods_info.goods_desc
        uni.setStorageSync("editGoodsContent", data.goods_info.goods_desc);

        // 获取已选商品品牌
        brandOptions.splice(0, brandOptions.length, data.goods_info.brand_option)
    }
}

// 实物
const getGoodsInitFn = async () => {
    loading.value = true
    let data = await (await getGoodsInit({goods_id: formData.goods_id})).data
    handleGoodsInit(data)

    if (formData.goods_id && data.goods_info) {
        // 配送设置
        formData.delivery_type = data.goods_info.delivery_type
        formData.is_free_shipping = data.goods_info.is_free_shipping
        formData.fee_type = data.goods_info.fee_type
        formData.delivery_money = data.goods_info.delivery_money
        formData.delivery_template_id = data.goods_info.delivery_template_id
    }
    loading.value = false
}

// 虚拟
const getGoodsVirtualInitFn = async () => {
    loading.value = true
    let data = await (await getVirtualGoodsInit({goods_id: formData.goods_id})).data
    handleGoodsInit(data)
    if (formData.goods_id && data.goods_info) {
        orderGoodsCount.value = data.goods_info.order_goods_count

        formData.virtual_auto_delivery = String(data.goods_info.virtual_auto_delivery)
        formData.virtual_receive_type = data.goods_info.virtual_receive_type
        if (formData.virtual_receive_type == 'auto') {
            formData.virtual_receive_type_name = '自动收货'
        } else if (formData.virtual_receive_type == 'artificial') {
            formData.virtual_receive_type_name = '买家确认收货'
        } else {
            formData.virtual_receive_type_name = '到店核销'
        }
        formData.virtual_verify_type = String(data.goods_info.virtual_verify_type)
        if (formData.virtual_verify_type == '0') {
            formData.virtual_verify_type_name = '永久'
        }  else if (formData.virtual_verify_type == '1') {
            formData.virtual_verify_type_name = '购买后几日有效'
        }
         else {
            formData.virtual_verify_type_name = '指定过期日期'
        }
        formData.virtual_verify_mobile = String(data.goods_info.virtual_verify_mobile)
        if (data.goods_info.virtual_receive_type == 'verify' && data.goods_info.virtual_verify_type == 2) {
            formData.virtual_indate = data.goods_info.virtual_indate
        } else if (data.goods_info.virtual_receive_type == 'verify' && data.goods_info.virtual_verify_type == 1) {
            formData.virtual_indate_day = data.goods_info.virtual_indate
        }
    }
    loading.value = false
}
// 刷新商品规格数据
const refreshGoodsSkuData = () => {
    const arr = goodsSpecFormat
    const tempGoodsSkuData = deepClone(goodsSkuData)// 记录原始数据，后续用作对比
    let skuData: any = {}
    let tempIndex = 0;
    for (const spec of arr) {
        let item_prop_arr: any = {}
        if (Object.keys(skuData).length > 0) {
            for (const ele_2 in skuData) {
                for (let ele_3 of spec.values) {
                    let sku_spec = deepClone(skuData[ele_2].sku_spec)// 防止对象引用
                    if(ele_3.spec_value_name){
                        sku_spec.push(ele_3)
                        item_prop_arr['sku_' + tempIndex] = {
                            spec_name: `${skuData[ele_2].spec_name} ${ele_3.spec_value_name}`,
                            spec_name_show: `${skuData[ele_2].spec_name}/${ele_3.spec_value_name}`,
                            sku_spec,
                            sku_image: '',
                            price: '',
                            market_price: '',
                            cost_price: '',
                            stock: '',
                            sku_no: '',
                            is_default: 0,
                            is_sell: 0,
                            checked: false,
                        }
                        if(goodsType.value == 'real'){
                            for (let key in appendRefreshGoodsSkuData) {
                                item_prop_arr['sku_' + tempIndex][key] = appendRefreshGoodsSkuData[key].value;
                            }
                        }
                    
                        tempIndex++;
                    }
                    
                }
            }
        } else {
            for (let ele_1 of spec.values) {
                let spec_name = ele_1.spec_value_name
                if(spec_name){
                    item_prop_arr['sku_' + tempIndex] = {
                        spec_name: spec_name,
                        spec_name_show: spec_name,
                        sku_spec: [ele_1],
                        sku_image: '',
                        price: '',
                        market_price: '',
                        cost_price: '',
                        stock: '',
                        sku_no: '',
                        is_default: 0,
                        is_sell: 0,
                        checked: false,
                    }
                    if(goodsType.value == 'real'){
                        for (let key in appendRefreshGoodsSkuData) {
                            item_prop_arr['sku_' + tempIndex][key] = appendRefreshGoodsSkuData[key].value;
                        }
                    }
                    tempIndex++;
                }
            }
        }

        skuData = Object.keys(item_prop_arr).length > 0 ? item_prop_arr : skuData
    }

    // 比对已存在的规格项/值，并且赋值
    for (const tempKey in tempGoodsSkuData) {
        for (const key in skuData) {
            const count = matchSkuSpecCount(tempGoodsSkuData[tempKey].sku_spec, skuData[key].sku_spec)
            if (count === skuData[key].sku_spec.length) {
                // 匹配成功后，要同步最新的规格项名称、规格值集合
                const specName = skuData[key].spec_name
                const skuSpec = skuData[key].sku_spec
                Object.assign(skuData[key], tempGoodsSkuData[tempKey])
                skuData[key].spec_name = specName
                skuData[key].sku_spec = skuSpec
                break
            }
        }
    }

    for (const item in goodsSkuData) {
        delete goodsSkuData[item]
    }

    let firstSpec = ''

    for (const key in skuData) {
        if (firstSpec == '') {
            firstSpec = key
            skuData[key].is_default = 1
        } else {
            skuData[key].is_default = 0
        }
        skuData[key].is_sell = 1
        goodsSkuData[key] = skuData[key]
    }
}
// 匹配规格值
const matchSkuSpecCount = (oVal: any, nVal: any) => {
    let count = 0// 匹配次数，与规格值相等时为匹配成功
    for (let i = 0; i < oVal.length; i++) {
        for (let j = 0; j < nVal.length; j++) {
            if (oVal[i].id === nVal[j].id) {
                count++
                break
            }
        }
    }
    return count
}
const refreshData = () => {
    // 商品图片

    let selectedAlbumImg = uni.getStorageSync('selectedAlbumImg');
    if (selectedAlbumImg) {
        selectedAlbumImg = JSON.parse(selectedAlbumImg);
        formData.goods_image = selectedAlbumImg.list;
        goodsShmilyDragImgRef.value.refresh()
    }
    // 商品视频
    let selectedAlbumVideo = uni.getStorageSync('selectedAlbumVideo');
    if (selectedAlbumVideo) {
        selectedAlbumVideo = JSON.parse(selectedAlbumVideo);
        formData.goods_video = selectedAlbumVideo.list;
    }

    // 规格项
	let goodsSpec = uni.getStorageSync('editGoodsSpecFormat') ? JSON.parse(uni.getStorageSync('editGoodsSpecFormat')) : [];
    goodsSpecFormat.splice(0, goodsSpecFormat.length, ...goodsSpec);

    // 多规格数据
    let skuData =  uni.getStorageSync('editGoodsSkuData') ? JSON.parse(uni.getStorageSync('editGoodsSkuData')) : {};
    for (let key in goodsSkuData){
        delete goodsSkuData[key];
    }
    Object.assign(goodsSkuData, skuData)
    if (uni.getStorageSync('editGoodsContent') != undefined && uni.getStorageSync('editGoodsContent') != '') {
        formData.goods_desc = uni.getStorageSync('editGoodsContent');
    }
}
const clearStoreage = () => {
    // 临时选择的商品图片
    uni.removeStorageSync("selectedAlbumImg");
    // 临时选择的商品视频
    uni.removeStorageSync("selectedAlbumVideo");

    uni.removeStorageSync("editGoodsSkuData");
    uni.removeStorageSync("editGoodsSpecFormat");
    // 商品详情
	uni.removeStorageSync("editGoodsContent");
}
// 商品类型
const showGoodsTypePicker = ref(false)
const goodsType = reactive<any>([])
const getGoodsTypeFn = () => {
    // 商品类型
    getGoodsType().then((res: any) => {
        const data = Object.values(res.data)
        Object.assign(goodsType, data)
        formData.goods_type_name = goodsType.find((item: any) => item.type === formData.goods_type)?.name
    })
}


// 商品类目
const showGoodsMallCategory = ref(false)
const goodsMallCategoryOptions = reactive([])
const refreshMallGoodsCategory = () => {
    getMallCategoryTree().then((res: any) => {
        Object.assign(goodsMallCategoryOptions, res.data)
        if(formData.goods_id){
            const nodePath = findNodePath(goodsMallCategoryOptions, formData.goods_mall_category)
           formData.goods_mall_category = nodePath?.map((item: any) => item.category_id)
           formData.goods_mall_category_name = nodePath?.map((item: any) => item.category_name).join('/')
        }
    })
}

const findNodePath = (options: any[], targetId: any, path: any[] = []): any[] | null => {
    for (const option of options) {
        const currentPath = [...path, option]

        if (option.category_id === targetId) {
            return currentPath
        }

        if (option.child_list && option.child_list.length > 0) {
            const result = findNodePath(option.child_list, targetId, currentPath)
            if (result) return result
        }
    }

    return null
}
// 商品分类
const goodsCategoryOptions = reactive<any>([])
const getCategoryTreeFn = () => {
    getCategoryTree().then((res: any) => {
        Object.assign(goodsCategoryOptions, res.data)
        if(formData.goods_id && formData.goods_category.length){
        	let arr: any = [];
            goodsCategoryOptions.forEach((item: any) => {
            	formData.goods_category.forEach((val: any) => {
            		if(item.category_id == val){
            			arr.push(item.category_name)
            		}
            		if(item.child_list){
            			item.child_list.forEach((subItem: any) => {
            				if(subItem.category_id == val){
            					arr.push(subItem.category_name)
            				}
            			})	
            		}
            	})
            })
            formData.goods_category_name = arr.join(',')
        }
    })
}

// 商品品牌
const brandOptions = reactive<any>([])
const showBrandPopup = ref(false)
const refreshGoodsBrand = () => {
    getBrandList({}).then((res: any) => {
        Object.assign(brandOptions, res.data)
        if(formData.goods_id && formData.brand_id){
            formData.brand_name = brandOptions.find((item: any) => item.brand_id == formData.brand_id)?.brand_name
        }
    })
}

// 商品标签
const showLabelPopup = ref(false)
const labelOptions = reactive<any>([])

const refreshGoodsLabel = () => {
    getLabelList({}).then((res: any) => {
        Object.assign(labelOptions, res.data)
        if(formData.goods_id && formData.label_ids){
           let arr = labelOptions.filter((item: any) => formData.label_ids.includes(item.label_id)).map((item: any) => item.label_name)
            formData.label_name = arr.join(',')
        }
    })
}
// 商品服务
const  showServicePopup = ref(false)
const serviceOptions = reactive<any>([])
const refreshGoodsService = () => {
    getServeList({}).then((res: any) => {
        Object.assign(serviceOptions, res.data)
        if(formData.goods_id && formData.service_ids){
            let arr = serviceOptions.filter((item: any) => formData.service_ids.includes(item.service_id)).map((item: any) => item.service_name)
            formData.service_name = arr.join(',')
        }
    })
}
// 会员等级折扣
const showMemberDiscountPopup = ref(false)

// 配送方式
const deliveryTypeCheckBox = reactive<any>([])
const getShopDeliveryListFn = () => {
    getShopDeliveryList().then((res: any) => {
        Object.assign(deliveryTypeCheckBox, res.data)
        if(formData.goods_id && formData.delivery_type){
            let arr = deliveryTypeCheckBox.filter((item: any) => formData.delivery_type.includes(item.key)).map((item: any) => item.name)
            formData.delivery_type_name = arr.join('、')
        }
    })
}
//运费模板
const deliveryTemplateOptions = reactive<any>([])

const refreshDeliveryTemplate = () => {
    const params = ref<any>({
        support_trade: 'mall',
        template_id: 0
    })
    if (formData.delivery_template_id) {
        params.value.template_id = formData.delivery_template_id
    }
    getShippingTemplateList(params.value).then((res: any) => {
        Object.assign(deliveryTemplateOptions, res.data)
    })
}

// 正则表达式
const regExp: any = {
    required: /[\S]+/,
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/,
    special: /^\d{0,10}(.?\d{0,3})$/
}
// 规则
const rules = computed(() => {
    return {
        'goods_name': [
            {
                type: 'string',
                required: true,
                message: '请输入商品名称',
                trigger: ['blur', 'change']
            },
            {
                validator(rule: any, value: any, callback: any) {
                    if (value.length > 60) {
                        callback(new Error('商品名称不能超过60个字'))
                    } else {
                        callback()
                    }
                }
            }
        ],
        'sub_title':[
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (value.length > 80) {
                        callback(new Error('商品副标题不能超过80个字'))
                    } else {
                        callback()
                    }
                }
            }
        ],
        'goods_image': [
            {
                type:'array',
                required: true,
                message: '请上传商品主图',
                trigger: ['blur', 'change']
            }
        ],
        'goods_mall_category': [
            {
                type:'array',
                required: true,
                message: '请选择商品类目',
                trigger: ['blur', 'change']
            }
        ],
        // 'goods_category': [
        //     {
        //         type:'array',
        //         required: true,
        //         message: '请选择商品分类',
        //         trigger: ['blur', 'change']
        //     }
        // ],
        'shop_sort': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (isNaN(value) || !regExp.number.test(value)) {
                        callback(new Error('请输入正确的排序值'))
                    } else {
                        callback()
                    }
                }
            }
        ],
        'price': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'single') {
                        if (value === '') {
                            callback(new Error('请输入销售价'))
                        } else if (isNaN(value) || !regExp.digit.test(value)) {
                            callback(new Error('销售价格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('销售价不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'market_price': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'single') {
                        if (isNaN(value) || !regExp.digit.test(value)) {
                            callback(new Error('划线价格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('划线价不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'cost_price': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'single') {
                        if (isNaN(value) || !regExp.digit.test(value)) {
                            callback(new Error('成本价格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('成本价不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'stock': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'single') {
                        if (value === '') {
                            callback(new Error('请输入库存'))
                        } else if (isNaN(value) || !regExp.number.test(value)) {
                            callback(new Error('库存格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('库存不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'spec_type': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'multi') {
                        if (Object.keys(goodsSkuData).length == 0) {
                            callback(new Error('请编辑规格信息'))
                        }
                    }
                    callback()
                }
            }
        ],
        'weight': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'single' && formData.goods_type== 'real'  && value) {
                        if (isNaN(value) || !regExp.special.test(value)) {
                            callback(new Error('重量(kg)格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('重量(kg)不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'volume': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.spec_type == 'single' && value) {
                        if (isNaN(value) || !regExp.special.test(value)) {
                            callback(new Error('体积(m³)格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('体积(m³)不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'max_buy': [
            {
                trigger: ['blur', 'change'],
                validator: (rule: any, value: any, callback: any) => {
                    if (value === '') {
                        callback(new Error('请输入限购数量'))
                    } else if (isNaN(value) || !regExp.number.test(value)) {
                        callback(new Error('限购数量格式输入错误'))
                    } else if (value < 1) {
                        callback(new Error('限购数量不能小于1'))
                    } else {
                        callback()
                    }
                }
            }
        ],
        'min_buy': [
            {
            	trigger: ['blur', 'change'],
                validator: (rule: any, value: any, callback: any) => {
                    if (isNaN(value) || !regExp.number.test(value)) {
                        callback(new Error('起购数量格式输入错误'))
                    } else if (value < 0) {
                        callback(new Error('起购数量不能小于0'))
                    } else if (formData.is_limit == 1 && value > Number(formData.max_buy)) {
                        callback(new Error('起购数量不能大于限购数量'))
                    } else {
                        callback()
                    }
                }
            }
        ],
        'goods_desc': [
            {
                required: true,
                trigger: ['blur', 'change'],
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.goods_desc === '') {
                        callback(new Error('请填写商品详情'))
                    } else if (formData.goods_desc.length < 5 || formData.goods_desc.length > 50000) {
                        callback(new Error('商品描述字符数应在5～50000之间'))
                        return false
                    } else {
                        callback()
                    }
                }
            }
        ],
        'delivery_type': [
            {
                type:'array',
                required: true,
                message: '请选择配送方式',
                trigger: ['blur', 'change']
            }
        ],
        'delivery_money': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.delivery_type.indexOf('express') != -1 && formData.is_free_shipping == 0 && formData.fee_type == 'fixed') {
                        if (formData.delivery_template_id.length == 0 && value === '') {
                            callback(new Error('请输入固定运费'))
                        } else if (isNaN(value) || !regExp.digit.test(value)) {
                            callback(new Error('固定运费格式输入错误'))
                        } else if (value < 0) {
                            callback(new Error('固定运费不能小于0'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'delivery_template_id':[
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.delivery_type.indexOf('express') != -1 && formData.is_free_shipping == 0 && formData.fee_type == 'template') {
                        if (formData.delivery_money.length == 0 && value === '') {
                            callback(new Error('请选择运费模板'))
                        } else {
                            callback()
                        }
                    } else {
                        callback()
                    }
                }
            }
        ],
        'virtual_indate_day': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.virtual_receive_type =='verify' && formData.virtual_verify_type == 1 && value == '') {
                    	callback(new Error('请输入有效期'))
                    }
                    if (formData.virtual_receive_type == 'verify' && formData.virtual_verify_type == 1 && value < 1) {
                        callback(new Error('核销有效期不能小于1天'))
                    }
                    callback()
                }
            }
        ],
        'virtual_indate': [
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (formData.virtual_receive_type =='verify' && formData.virtual_verify_type == 2 && value == '') {
                        callback(new Error('请输入有效期'))
                    }
                    if (formData.virtual_receive_type == 'verify' && formData.virtual_verify_type == 2) {
                        const date = new Date(value)
                        const time = date.getTime() * 1000
                        const date1 = new Date()
                        const time1 = date1.getTime()
                        if (time <= time1) { callback(new Error('核销有效期不能小于等于当前时间')) }
                    }
                    callback()
                }
            }
        ]
    }
})
const verify = (callback: any) => {
    let formRef = [
        {
            key: 'base',
            verify: false,
            ref: baseFormRef.value
 
        },
        {
            key: 'basic',
            verify: false,
            ref: basicFormRef.value
        },
        {
            key: 'price_stock',
            verify: false,
            ref: priceStockFormRef.value
        },
        {
            key: 'detail',
            verify: false,
            ref: detailFormRef.value
        }
    ];
    let obj = {
        key: 'delivery',
        verify: false,
        ref: deliveryFormRef.value
    }
    if (formData.goods_type == 'real') {
        formRef.push(obj)
    }

    formRef.forEach((el: any, index) => {
        el.ref.validate().then((valid: any) => {
            el.verify = valid
        })
    })

    setTimeout(() => {
        let verify = true
        // 检测验证，并且定位tab页面
        for (let i = 0; i < formRef.length; i++) {
            if (formRef[i].verify == false) {
                verify = false
                break
            }
        }
        if (verify && callback) callback()
    }, 10)
}

// 保存数据
const save = () => {
    verify(() => {

        if (repeat.value) return
        repeat.value = true
        let api = null
        if(formData.goods_id) {
            api = formData.goods_type == 'real' ? editGoods : editVirtualGoods
        } else {
            api = formData.goods_type == 'real' ? addGoods : addVirtualGoods
        }
        const data = deepClone(formData)

        if (data.spec_type == 'multi') {
            data.stock = 0
            for (const k in goodsSkuData) {
                if (goodsSkuData[k].stock) data.stock += parseInt(goodsSkuData[k].stock)
            }
        }
        // 收货设置
        if (data.virtual_receive_type == 'verify' && data.virtual_verify_type == 2) {
            const date = new Date(data.virtual_indate)
            data.virtual_indate = Math.floor(date.getTime())
        }
        if (data.virtual_receive_type == 'verify' && data.virtual_verify_type == 1) {
            data.virtual_indate = data.virtual_indate_day
        }
        if (data.virtual_receive_type == 'verify' && data.virtual_verify_type == 0) {
            data.virtual_indate = 0
        }

        const goodsCategory: any = []
       data.goods_category.forEach((item: any) => {
            if (typeof item == 'object') {
                item.forEach((second: any) => {
                    if (goodsCategory.indexOf(second) == -1) {
                        goodsCategory.push(second)
                    }
                })
            } else {
                if (goodsCategory.indexOf(item) == -1) {
                    goodsCategory.push(item)
                }
            }
        })
        for (let i = 0; i < goodsSpecFormat.length; i++) {
            const spec = goodsSpecFormat[i]
            if(spec.values.length){
                spec.values = spec.values.filter((item: any) => {
                    return item.spec_value_name !== ''
                })
            }
        }

        data.goods_category = goodsCategory
        if(formData.goods_mall_category && formData.goods_mall_category.length) {
            data.goods_mall_category = formData.goods_mall_category[formData.goods_mall_category.length - 1].toString()
        } else {
            data.goods_mall_category = ''
        }
        data.goods_image = formData.goods_image.join(',')
        data.goods_sku_data = goodsSkuData
        data.goods_spec_format = goodsSpecFormat


        api(data).then((res: any) => {
            repeat.value = false
            clearStoreage();
            redirect({url: '/addon/mall/pages/goods/list'})
        }).catch(() => {
            repeat.value = false
        })
    })
}

// 添加视频
const addVideo = () => { 
    if(formData.goods_video){
        let temp = {
            list: formData.goods_video.toString(),
            index: 0
        }
        uni.setStorageSync('selectedAlbumVideo', JSON.stringify(temp));
    }
    redirect({url: '/addon/mall/pages/goods/album', param: {att_type: 'video', number: 1, checked: true }})
}
// 删除视频
const deleteVideo = () => {
    formData.goods_video = ''
    uni.removeStorageSync('selectedAlbumVideo');
}

const videoShow = ref(false)
// 视频预览
const videoPreview = () => {
    videoShow.value = true
}
// 关闭视频预览
const videoRef = ref()
const handleVideo = () => {
    videoShow.value = false
    try {
        if (videoRef.value && typeof videoRef.value.pause === 'function') {
            videoRef.value.pause()
        }
    } catch (error) {
        console.error('Failed to pause video:', error)
    }
}
// 商品类型
const openGoodsType = () => {
    if(formData.goods_id){
        return false
    }
    showGoodsTypePicker.value = true
}
const  changeGoodsType = () => {
    formData.goods_type_name = goodsType.find((item: any) => item.type === formData.goods_type)?.name
    showGoodsTypePicker.value = false
}
// 商品类目
const handelGoodsMallCategory = (e: any) => {
    let name = ''
    goodsMallCategoryOptions.forEach((item: any) => {
        if(e[0] && item.category_id === e[0]){
            name = item.category_name
            if(e[1] && item.child_list){
                item.child_list.forEach((subItem: any) => {
                    if(subItem.category_id === e[1]){
                        name += '/' + subItem.category_name
                        if(e[2] && subItem.child_list){
                            subItem.child_list.forEach((childItem: any) => {
                                if(childItem.category_id === e[2]){
                                    name += '/' + childItem.category_name
                                }
                            })
                        }
                    }
                })
            }
        }
    })
    formData.goods_mall_category_name = name
}
// 商品分类
const goodsCategoryRef = ref()
const handleGoodsCategory = () => {
    goodsCategoryRef.value.open(formData.goods_category)
}
const confirmGoodsCategory = (data: any) => {
    formData.goods_category = data.category_id
    formData.goods_category_name = data.category_name?.join(',')
}

// 商品品牌
const changeBrand = () => {
    formData.brand_name = brandOptions.find((item: any) => item.brand_id === formData.brand_id)?.brand_name
    showBrandPopup.value = false
}
// 商品标签
const changeLabel = () => {
    formData.label_name = labelOptions.filter((item: any) => formData.label_ids.includes(item.label_id)).map((item: any) => item.label_name)?.join(',')
    showLabelPopup.value = false
}
// 商品服务
const changeService = () => {
    formData.service_name = serviceOptions.filter((item: any) => formData.service_ids.includes(item.service_id)).map((item: any) => item.service_name)?.join(',')
    showServicePopup.value = false
}
// 会员等级折扣
const changeMemberDiscount = () => {
    formData.member_discount_name = formData.member_discount === 'discount' ? '会员折扣' : formData.member_discount === 'fixed_price' ? '指定会员价' : '不参与'
    showMemberDiscountPopup.value = false
}
// 配送方式
const showDeliveryTypePopup = ref(false)
const changeDeliveryType = () => {
    formData.delivery_type_name =  deliveryTypeCheckBox.filter((item: any) => formData.delivery_type.includes(item.key)).map((item: any) => item.name).join('、')
    showDeliveryTypePopup.value = false
}
// 运费模板
const showDeliveryTemplatePopup = ref(false)
const changeDeliveryTemplate = () => {
    formData.delivery_template_name =  deliveryTemplateOptions.find((item: any) => item.template_id === formData.delivery_template_id)?.template_name
    showDeliveryTemplatePopup.value = false
}
// 收货设置
const showVirtualReceiveTypePicker = ref(false)
const changeVirtualReceiveType = () => {
    formData.virtual_receive_type_name = formData.virtual_receive_type === 'auto'? '自动收货' : formData.virtual_receive_type === 'artificial'? '买家确认收货' : '到店核销'
    showVirtualReceiveTypePicker.value = false
}
// 核销有效期
const showVirtualVerifyTypePicker = ref(false)
const changeVirtualVerifyType = () => {
    formData.virtual_verify_type_name = formData.virtual_verify_type === '1'? '购买后几日生效' : formData.virtual_verify_type === '2'? '指定过期日期' : '永久'
    showVirtualVerifyTypePicker.value = false
}
// 有效期
const virtualIndate = ref(false)
const  updateVirtualIndate = (e: any) => {
    formData.virtual_indate = (e.value / 1000)
    virtualIndate.value = false
}

</script>

<style lang="scss" scoped>
.form-wrap :deep(.u-form-item__body){
    flex-direction: column !important;
}
.image-form :deep(.u-form-item__body__left__content__label){
    color: #999 !important;
    font-size: 26rpx !important;
}
.status-wrap :deep(.u-radio-group){
    flex: none!important;
}
.delivery-wrap :deep(.u-checkbox-group){
    justify-content: flex-end;
}
.footer {
    height: calc(120rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(120rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
.empty-page{
    padding-top: 0rpx;
    margin-top: 0rpx;
    height:  350rpx;
    width: auto;
}
</style>