<template>
    <view class="bg-[#333] min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 z-100 bg-[#333] py-[14rpx] px-[20rpx]">
            <view class="flex items-center h-[60rpx]">
                <text class="nc-iconfont nc-icon-guanbiV6xx2 text-[38rpx] text-[#fff]" @click="backToPrevious"></text>
                <view class="flex-1 flex items-center justify-center text-[#fff]" >
                    <view class="flex items-center px-[20rpx] py-[4rpx] leading-[46rpx] bg-[rgba(255,255,255,0.3)] rounded-[28rpx]" @click="typePopup = !typePopup">
                        <text>{{ cateName }}</text>
                        <text v-if="typePopup == false" class="nc-iconfont nc-icon-xiangxiaV6mm text-[#b2b2b2] ml-[10rpx]"></text>
                        <text v-else class="nc-iconfont nc-icon-xiangshangV6mm text-[#b2b2b2] ml-[10rpx]"></text>
                    </view>
                </view>
            </view>
        </view>
        <view class="type-class">
            <u-popup :show="typePopup" mode="top" @close="typePopup = false" zIndex="20">
                <view @touchmove.prevent.stop class="py-[22rpx] bg-[#333] rounded-b-[30rpx]">
                    <scroll-view :scroll-y="true" class="max-h-[40vh]">
                        <view class="leading-[80rpx] text-[26rpx] text-[#fff] px-[50rpx] border-b" :class="{'!text-primary font-500' : cateId == ''}" @click="handleSearch()">全部</view>
                        <view class="leading-[80rpx] text-[26rpx] text-[#fff] px-[50rpx] border-b" :class="{'!text-primary font-500' : cateId == item.id}" v-for="(item,index) in attachmentCategoryList" @click="handleSearch(item)">{{ item.name }}</view>
                    </scroll-view>
                </view>
            </u-popup>
        </view>
        <mescroll-body ref="mescrollRef" top="88rpx" bottom="50px"  @init="mescrollInit" :down="{ use: false }"  @up="getAlbumList">
            <view>
                <view class="grid grid-cols-3">
                    <view v-if="attrType == 'image'">
                        <view class="flex-center w-[250rpx] h-[250rpx]  text-center text-[var(--text-color-light9)] bg-[#f2f2f2]" @click="chooseCustomImage" v-if="is_cropper">
                            <view>
                                <view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx] mb-[10rpx]"></view>
                                <view class="text-[26rpx]">选择照片</view>
                            </view>
                        </view>
                        <u-upload @afterRead="afterRead" :maxCount="number" :multiple="true" v-else>
                            <view class="flex-center w-[250rpx] h-[250rpx]  text-center text-[var(--text-color-light9)] bg-[#f2f2f2]">
                                <view>
                                    <view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx] mb-[10rpx]"></view>
                                    <view class="text-[26rpx]">选择照片</view>
                                </view>
                            </view>
                        </u-upload>
                        
                    </view>
                    <view v-if="attrType == 'video'">
                        <up-upload @afterRead="afterReadVideo" :maxCount="number" :multiple="true" accept="video">
                            <view class="flex-center w-[250rpx] h-[250rpx]  text-center text-[var(--text-color-light9)] bg-[#f2f2f2]">
                                <view>
                                    <view class="nc-iconfont nc-icon-a-shipinV6xx-28-1 text-[50rpx] mb-[10rpx]"></view>
                                    <view class="text-[24rpx]">添加视频</view>
                                </view>
                            </view>
                        </up-upload>
                    </view>
                    <view class="relative overflow-hidden"  v-for="(item, index) in list" :key="index">
                        <image v-if="attrType == 'image'" class="w-[250rpx] h-[250rpx] align-middle" mode="aspectFill" :src="img(item.thumb)" @error="item.thumb='static/resource/images/diy/shop_default.jpg'"  @click="previewImg(item.thumb)"></image>
                        <view v-else-if="attrType == 'video'" class="w-[250rpx] h-[250rpx] relative">
                            <video  class="w-[250rpx] h-[250rpx] align-middle" :src="img(item.thumb)" @click="previewVideo(item.thumb)"  objectFit="cover" :controls="false" :show-fullscreen-btn="false" :show-center-play-btn="false" :show-play-btn="false" :enable-progress-gesture="false" @error="handleError($event, item) "></video>
                            <text v-if="item.error" class="absolute bottom-[14rpx] left-[6rpx] text-[#fff] text-[22rpx]">该视频源已失效</text>
                        </view>
                        <view class="absolute top-[10rpx] right-[10rpx] z-10 leading-none flex-center w-[56rpx] h-[56rpx]" @click.stop="checkImg(item.url)" v-if="checked">
                            <text class="w-[40rpx] h-[40rpx] rounded-full bg-primary text-[#fff] text-center leading-[40rpx]" v-if="isSelected(item.url)">{{ getImgIndex(item.url) }}</text>
                            <text class="nc-iconfont nc-icon-duihaoV6xx text-[#fff] text-[40rpx]" v-if="!isSelected(item.url)"></text>
                        </view>
                    </view>
                </view>
            </view>
        </mescroll-body>
        <view class="w-full footer" v-if="checked">
            <view class="py-[var(--top-m)] px-[var(--sidebar-m)] w-full fixed bottom-0 left-0 right-0 z-20 box-border bg-[#333]" v-if="selectedImg.length">
                <scroll-view :scroll-x="true" class="w-full h-[100rpx] mb-[20rpx]">
                    <view class="flex flex-nowrap">
                        <view class="relative flex-shrink-0 overflow-hidden mr-[20rpx]" v-for="(item, index) in selectedImg" :key="index">
                            <image  v-if="attrType == 'image'" class="w-[100rpx] h-[100rpx] rounded-[8rpx] align-middle" mode="aspectFill" :src="img(item)" @error="item='static/resource/images/diy/shop_default.jpg'"  @click="previewImg(item)"></image>
                             <video v-else-if="attrType == 'video'" class="w-[100rpx] h-[100rpx] rounded-[8rpx] align-middle" :src="img(item)" @click="previewVideo(item)"  objectFit="cover" :controls="false" :show-fullscreen-btn="false" :show-center-play-btn="false" :show-play-btn="false" :enable-progress-gesture="false" ></video>
                            <text class="absolute top-[6rpx] right-[6rpx] z-10 nc-iconfont nc-icon-cuohaoV6xx text-[#fff] leading-none" @click="deleteSelect(index)"></text>
                        </view>
                    </view>
                </scroll-view>
                <view class="flex justify-between">
                    <view>
                        <text class="text-[#fff]" v-if="attrType == 'image'" @click="selectPreviewImg">预览</text>
                    </view>
                    <button hover-class="none" class="list-grey-solid-btn bg-primary text-[#fff] m-0" @click="save">确定</button>
                </view>
            </view>
        </view>
        <u-popup :show="videoShow" @close="videoShow = false" zIndex="999" round="0">
            <view class="h-[100vh] w-[100vw] relative" @touchmove.prevent.stop>
                <text class="fixed top-[20rpx] left-[20rpx]  nc-iconfont nc-icon-guanbiV6xx z-1000 text-[#fff] text-[40rpx]" @click="handleVideo"></text>
                <video class="w-full h-full" ref="videoRef" :src="img(curvideo)" controls autoplay></video>
            </view>
        </u-popup>
        <up-cropper ref="customCropperRef" @confirm="onCustomConfirm" class="cropper-wrap" />
    </view>
</template>

<script setup lang="ts">
import { ref, getCurrentInstance, nextTick } from 'vue';
import { img, redirect } from '@/utils/common'
import { getAttachmentList, getAttachmentCategoryList, getAttachmentConfig, uploadImage, uploadImageBase64, uploadVideo } from '@/app/api/system'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const loading = ref<boolean>(false);
const list = ref<any>([]);
const attrType = ref<string>('image');
const cateId = ref<any>('');
const cateName = ref<any>('全部');
const number = ref<number>(9);
const checked = ref<boolean>(false);
const selectedImg = ref<any>([]);
const index = ref<number>(0);
const typePopup = ref<boolean>(false);
const attachmentCategoryList = ref<any>([]);
const is_cropper = ref<boolean>(false);
onLoad((option: any) => {
    attrType.value = option.att_type || 'image';
    number.value = option.number || (option.att_type == 'image' ? 9 : 1);
    checked.value = option.checked || false;
    if(attrType.value == 'image'){
    	let selectedAlbumImg = uni.getStorageSync('selectedAlbumImg')? JSON.parse(uni.getStorageSync('selectedAlbumImg')) : null;
        if(selectedAlbumImg){
            if (selectedAlbumImg.list) {
                selectedImg.value = selectedAlbumImg.list;
            }
            index.value = selectedAlbumImg.index;
        }
    }
    if (attrType.value == 'video') {
        let selectedAlbumVideo = uni.getStorageSync('selectedAlbumVideo')? JSON.parse(uni.getStorageSync('selectedAlbumVideo')) : null;
        if(selectedAlbumVideo){
            if (selectedAlbumVideo.list) {
                selectedImg.value = selectedAlbumVideo.list.split(',');
            }
            index.value = selectedAlbumVideo.index;
        }
    }
    getAttachmentCategoryListFn();
})
const getAttachmentConfigFn = () => {
    getAttachmentConfig().then((res: any) => {
        is_cropper.value = res.data.is_cropper;
    })
}
getAttachmentConfigFn();
const getAttachmentCategoryListFn = () => {
    getAttachmentCategoryList({
        type: attrType.value
    }).then((res: any) => {
        attachmentCategoryList.value = res.data;
    })

}
const getAlbumList = (mescroll: any) => {
	loading.value = false;
    mescroll.size = 30;
	let data = {
        page: mescroll.num,
		limit: mescroll.size,
        att_type: attrType.value,
        cate_id: cateId.value
    }
	getAttachmentList(data).then((res: any) => {
		let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			list.value = []; //如果是第一页需手动制空列表
		}
		list.value = list.value.concat(newArr);
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const handleSearch = (data: any = {}) => {
    cateId.value = data.id || '';
    cateName.value = data.name || '全部';
    typePopup.value = false;
    getMescroll().resetUpScroll();
}

const backToPrevious = () => {
	if(getCurrentPages().length > 1){
		
		uni.navigateBack({
			delta: 1,
		});
	}else{
        redirect({
			url: '/app/pages/index/index',
            mode: 'reLaunch'
		});
	}
}
// 选中图片
const isSelected = (path: any)  =>{
    if (selectedImg.value.indexOf(path) > -1) return true;
    else return false;
}

// 选中图片数码
const getImgIndex = (path: any) => {
    var index = selectedImg.value.indexOf(path);
    if (index > -1) return index + 1;
    else return '';
}

// 选择图片
const checkImg = (path: any) => {
    let index = selectedImg.value.indexOf(path);

    if (index == -1) {
        if (selectedImg.value.length + 1 > number.value) return;
        selectedImg.value.push(path);
    } else {
       selectedImg.value.splice(index, 1);
    }
}
// 删除图片
const deleteSelect = (index: any) => {
    selectedImg.value.splice(index, 1);
}
// 添加图片
const afterRead = (event: any) => {
    event.file.forEach((file: any) => {
        upload({ file })
    })
}

const upload = (event: any) => {
    uploadImage({
        filePath: event.file.url,
        formData:{
            cate_id: cateId.value,
        },
        name: 'file'
    }).then((res: any) => {
        getMescroll().resetUpScroll();
    }).catch(() => {
    })
}

// 添加视频
const afterReadVideo = (event: any) => {
    if (event.file.size > 100 * 1024 * 1024) {
        uni.showToast({ title: '视频大小不能超过100MB', icon: 'none' })
        return false
    }
    event.file.forEach((file: any) => {
        uploadVideoFn({ file })
    })
}

const uploadVideoFn = (event:any) => {
    uni.showLoading({
        title: '上传中...',
        mask: true
    });
    uploadVideo({
        filePath: event.file.url,
        formData:{
            cate_id: cateId.value,
        },
        name: 'file'
    }).then((res: any)=> {
        uni.hideLoading();
        getMescroll().resetUpScroll();
    }).catch(() => {
    })
}
// 图片预览
const previewImg = (path: any) => {
    let index = selectedImg.value.indexOf(path);
    let paths: any = [];
    if (index > -1) {
        selectedImg.value.forEach((item: any) => {
            paths.push(img(item));
        });
        uni.previewImage({
            current: index,
            urls: paths
        })
    }  else {
        paths = [img(path)];
        uni.previewImage({
            current: 0,
            urls: paths
        })
    }
    
}
const selectPreviewImg = () => {
    let paths: any = [];
    selectedImg.value.forEach((item: any) => {
        paths.push(img(item));
    });
    uni.previewImage({
        current: 0,
        urls: paths
    })
}

// 预览视频
const videoShow = ref(false)
const curvideo = ref('')
const previewVideo = (item:any) => {
    if (item === '') return false
    curvideo.value = item
    videoShow.value = true
}

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

const  handleError = (e:any, item:any) => {
    item.error = true;
}

const save = () => {
    if(attrType.value == 'image'){
        let temp = {
            list: selectedImg.value,
            index: index.value
        }
        uni.setStorageSync('selectedAlbumImg', JSON.stringify(temp));	
    }
    if(attrType.value == 'video'){
        let temp = {
            list: selectedImg.value.toString(),
            index: index.value
        }
        uni.setStorageSync('selectedAlbumVideo', JSON.stringify(temp));
    }
    uni.navigateBack({
        delta: 1
    });
}

const instance = getCurrentInstance();
// 图片裁剪
const customCropperRef = ref<any>(null);
const chooseCustomImage = () => {
    customCropperRef.value.chooseImage(0, {
        canChangeSize: true,
        areaWidth: "300rpx", 
        areaHeight: "180rpx"
    })
}
const onCustomConfirm = async (rsp: any) => {
    let url = await toBase64(rsp.path)
    // const dataUrl = `data:image/jpeg;base64,${url}`;
    await uploadImageBase64({
        content: url,
        cate_id: cateId.value,
    }).then((res: any) => {
        getMescroll().resetUpScroll();
    }).catch(() => {
    })
}

const toBase64 = (path: string) => {
    return new Promise((resolve, reject) => {
        // 先判断是否是 Blob URL（仅 H5 端生效）
        // #ifdef H5
        if (path.startsWith('blob:')) {
            const convertBlobToBase64 = async () => {
                try {
                    // 1. 通过 Blob URL 获取 Blob 对象
                    const response = await fetch(path);
                    const blob = await response.blob();
                    
                    // 2. Blob 转 Base64
                    return new Promise<string>((resolveBlob, rejectBlob) => {
                        const reader = new FileReader();
                        reader.readAsDataURL(blob); // 读取为带前缀的 Base64
                        reader.onloadend = () => {
                            // 3. 提取纯 Base64 部分（去掉前缀）
                            const base64Full = reader.result as string;
                            const base64Pure = base64Full.split(',')[1];
                            resolveBlob(base64Pure);
                        };
                        reader.onerror = (err) => rejectBlob(err);
                    });
                } catch (err) {
                    reject(err);
                }
            };

            // 执行 Blob 转 Base64
            convertBlobToBase64().then(resolve).catch(reject);
            return; // 终止后续逻辑
        }
        // #endif

        // 1. 微信小程序平台
        // #ifdef MP-WEIXIN
        uni.getFileSystemManager().readFile({
            filePath: path,
            encoding: 'base64',
            success: (res) => resolve(res.data),
            fail: (err) => reject(err)
        });
        // #endif

        // 2. App 平台（使用 plus.io API）
        // #ifdef APP-PLUS
        plus.io.resolveLocalFileSystemURL(path, (entry) => {
            entry.file((file) => {
                const fileReader = new plus.io.FileReader();
                fileReader.readAsDataURL(file);
                fileReader.onloadend = (evt: any) => {
                    // 提取纯 base64 部分
                    const base64 = evt.target.result.split(',')[1];
                    resolve(base64);
                };
                fileReader.onerror = (err: any) => reject(err);
            }, (err) => reject(err));
        }, (err) => reject(err));
        // #endif
    });
};
</script>

<style lang="scss" scoped>
.footer {
    height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
:deep(.type-class .u-popup .u-transition) {
    top: 86rpx !important;
}
.border-b{
    border-bottom: 1rpx solid #5a5959;
}
</style>