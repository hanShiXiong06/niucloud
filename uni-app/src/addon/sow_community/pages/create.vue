<template>
    <view  class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden"  :style="themeColor()">
        <view class="card-template sidebar-margin top-mar">
            <view class="flex items-center">
                <upload-img v-model="form.content_image" :max-count="9" :multiple="true" :title="t('addPhoto')" v-if="!form.content_video" class="mr-[20rpx]"/>
                <upload-video v-model="form.content_video" :max-count="1"  v-if="!form.content_image"/>
                <upload-img v-model="form.content_cover" :max-count="1" :title="t('addCover')" v-if="form.content_video" />
            </view>
            <view class="pb-[20rpx] border-0 border-solid border-b-[2rpx] border-[#ddd] mt-[50rpx] mb-[32rpx] flex items-center" >
                <input class="text-[30rpx] flex-1 box-border" maxlength="30" type="text" v-model.trim="form.content_title" :placeholder="t('addTitle')" placeholderClass="text-[var(--text-color-light9)] text-[30rpx]" confirm-type="search" >
                <text class="text-[24rpx] text-[#999] ml-[10rpx]">{{form.content_title.length}}/30</text>
            </view>
            <view class="relative mb-[20rpx] pb-[40rpx]">
                <textarea class="!text-[26rpx] px-[2rpx] w-[100%] !text-[#333] !leading-[1.5]" v-model="form.content" :placeholder="t('contentTips')" placeholderClass="text-[26rpx] text-[var(--text-color-light9)]"  maxlength="300"></textarea>
                <view class="absolute right-[20rpx] bottom-0 w-[96rpx] h-[40rpx] leading-[40rpx] text-center  text-[24rpx] text-[#999]">{{form.content.length}}/300</view>
            </view>
            <view>
                <text class="inline-block w-[140rpx] h-[60rpx] bg-[#f2f2f2] rounded-full text-center leading-[60rpx] text-[24rpx] font-500" @click="handleTopic"># {{ t('topic') }}</text>
                <view class="flex flex-wrap mt-[26rpx]" v-if="topicList.length">
                    <view class="flex items-center h-[50rpx] px-[18rpx] rounded-full bg-[#f6f6f6] mr-[20rpx] mb-[20rpx]" v-for="(item,index) in topicList" :key="index">
                        <text class="text-[#666] text-[24rpx] mr-[20rpx]"># {{ item.topic_name }}</text>
                        <text class="nc-iconfont nc-icon-guanbiV6xx2 text-[26rpx]" @click="deleteTopic(item.topic_id, index)"></text>
                    </view>
                </view>
            </view>
        </view>
        <view class="card-template sidebar-margin top-mar flex-between-center" @click="handleTreasure">
            <view class="flex-center">
                <text class="nc-iconfont nc-icon-gouwuV6xx1 text-[34rpx] mr-[20rpx]"></text>
                <text class="text-[28rpx] font-500">{{ t('treasure') }}</text>
            </view>
            <view class="flex items-center justify-end">
                <view class="w-[60rpx] h-[60rpx] mr-[12rpx]" v-for="(item,index) in treasureImg" :key="index">
                    <up-image radius="6rpx" width="60rpx" height="60rpx"  :src="img(item)" model="aspectFill">
                        <template #error>
                            <image class="w-[60rpx] h-[60rpx] rounded-[6rpx] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"/>
                        </template>
                    </up-image>
                </view>
                <text class="nc-iconfont nc-icon-youV6xx text-[32rpx] text-[#666]"></text>
            </view>
        </view>
        <view class="card-template sidebar-margin top-mar flex-between-center" @click="handleCategory">
            <view class="flex-center">
                <text class="nc-iconfont nc-icon-shequfenleiV6xx-1 text-[34rpx] mr-[20rpx]"></text>
                <text class="text-[28rpx] font-500">{{ t('communityCategory') }}</text>
            </view>
            <view class="flex items-center justify-end flex-1">
                <text class="text-[28rpx]">{{ form.category_name }}</text>
                <text class="nc-iconfont nc-icon-youV6xx text-[32rpx] text-[#666]"></text>
            </view>
        </view>
        <view class="tab-bar-placeholder"></view>
        <view class="py-[40rpx] px-[var(--sidebar-m)] bg-[var(--page-bg-color)] tab-bar fixed bottom-0 left-0 right-0 box-border">
            <button hover-class="none" class="primary-btn-bg text-[#fff] h-[80rpx] leading-[80rpx] rounded-[100rpx] text-[26rpx] font-500" @click="create">{{ t('create') }}</button>
        </view>
        <topic-popup  ref="topicRef"  @confirm="topicEvent"/>
        <treasure-popup ref="treasureRef" @confirm="treasureEvent"/>
        <categoryPopup ref="categoryRef" @confirm="categoryEvent"/>
    </view>
    <!-- #ifdef MP-WEIXIN -->
    <!-- 小程序隐私协议 -->
    <wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
    <!-- #endif -->
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { t } from '@/locale'
import { img, redirect, getToken } from '@/utils/common';
import { setContent,editContent, getContentDetail } from '@/addon/sow_community/api/content';
import { onLoad,onShow } from '@dcloudio/uni-app';
import { useLogin } from '@/hooks/useLogin'
import uploadImg from '@/addon/sow_community/components/upload-img/upload-img.vue'
import uploadVideo from '@/addon/sow_community/components/upload-video/upload-video.vue'
import topicPopup from '@/addon/sow_community/components/topic-popup/topic-popup.vue'
import TreasurePopup from '@/addon/sow_community/components/treasure-popup/treasure-popup.vue'
import categoryPopup from '@/addon/sow_community/components/category-popup/category-popup.vue'

const form = ref<any>({
    content_id: '',
    content_type: 1,
    content_image: '',
    content_video: '',
    content_cover: '',
    content_title: '',
    content: '',
    category_id: '',
    category_name: '',
    topic_ids: [],
    treasure_ids: [],
	content_cover_width:"",
	content_cover_height:""
})

const  type = computed(() => {
    let type = 1
    if(form.value.content_image){
        type = 1
    }
    if(form.value.content_video){
        type = 2
    }
    return type
})

const wxPrivacyPopupRef:any = ref(null)

onLoad((options: any) =>{
    form.value.content_id = options.content_id || ''

    // #ifdef MP
    nextTick(()=>{
        if(wxPrivacyPopupRef.value) wxPrivacyPopupRef.value.proactive();
    })
    // #endif
})
const handleHeight = () => {
    return new Promise((resolve, reject) => {
        const imageArray = form.value.content_image.split(',');
        let imageSrc = img(form.value.content_type == 1 ? imageArray[0] : form.value.content_cover);

        uni.getImageInfo({
            src: imageSrc,
            success: (image) => {
                form.value.content_cover_width = image.width;
                form.value.content_cover_height = image.height;
                resolve();
            },
            fail: (err) => {
                console.error('Failed to get image info:', err);

                uni.showToast({
                    title: '获取图片失败，请检查服务器downloadFile合法域名',
                    icon: 'none'
                })
                reject(err);
            }
        });
    });
};

onShow(() =>{
    if(form.value.content_id){
        getContentDetailFn()
    }
})

const getContentDetailFn =  () => {
    getContentDetail(form.value.content_id).then((res: any) =>{
        Object.keys(form.value).forEach((key: string) => {
            if (res.data[key] != undefined) form.value[key] = res.data[key]
        })
        topicList.value = res.data.topic_list || []
        treasureImg.value =  res.data.treasure_list &&  res.data.treasure_list.map((item:any) => { return item.treasure_image })
    })

}

// 参与话题
const topicRef = ref<any>()
const topicList = ref([])
const handleTopic = () =>{
    topicRef.value.open(form.value.topic_ids)
}

const topicEvent = (data:any) =>{
    topicList.value = data
    form.value.topic_ids = data.map((item:any) => { return item.topic_id })
}

const deleteTopic = (id: any, index:number) =>{
    topicList.value.splice(index,1)
    form.value.topic_ids = form.value.topic_ids.filter((item:any) => { return item !== id })
    console.log(form.value.topic_ids)
}

// 关联商品
const treasureRef = ref<any>()
const treasureImg = ref([])
const handleTreasure = () =>{
    treasureRef.value.open(form.value.treasure_ids)
}
const treasureEvent = (data:any) =>{
    form.value.treasure_ids = data.treasure_id
    treasureImg.value = data.treasure_image
}

// 社区分类
const categoryRef = ref<any>()
const handleCategory = () =>{
    categoryRef.value.open(form.value.category_id)
}
const categoryEvent = (data:any) =>{
    form.value.category_id = data.category_id
    form.value.category_name = data.category_name
}

const createLoading = ref(false)
const create = async () => {
    if(!getToken()){
        useLogin().setLoginBack({ url: '/addon/sow_community/pages/create' })
        return false
    }

    form.value.content_type = type.value;
    if(form.value.content_type === 1 && !form.value.content_image){
        uni.showToast({
            title: '请上传图片',
            icon: 'none'
        })
        return false
    }
    if(form.value.content_type === 2 && !form.value.content_video){
        uni.showToast({
            title: '请上传视频',
            icon: 'none'
        })
        return false
    }
    if(form.value.content_type === 2 && !form.value.content_cover){
        uni.showToast({
            title: '请上传视频封面',
            icon: 'none'
        })
        return false
    }
    if(!form.value.content){
        uni.showToast({
            title: '请输入分享内容',
            icon: 'none'
        })
        return false
    }
    if(!form.value.category_id){
        uni.showToast({
            title: '请选择社区分类',
            icon: 'none'
        })
        return false
    }

    if (createLoading.value) return;
    createLoading.value = true;

    try {
        await handleHeight();
        let api = form.value.content_id ? editContent : setContent;
        api(form.value).then((res: any) => {
            redirect({ url: '/addon/sow_community/pages/index', mode: 'reLaunch' });
        }).catch(() => {
            createLoading.value = false;
        });
    } catch (err) {
        createLoading.value = false;
    }
};
</script>

<style lang="scss" scoped>
.tab-bar-placeholder {
	padding-bottom: calc(constant(safe-area-inset-bottom) + 160rpx);
	padding-bottom: calc(env(safe-area-inset-bottom) + 160rpx);
}
.tab-bar {
	padding-bottom: calc(constant(safe-area-inset-bottom) + 40rpx);
	padding-bottom: calc(env(safe-area-inset-bottom) + 40rpx);
}
</style>
