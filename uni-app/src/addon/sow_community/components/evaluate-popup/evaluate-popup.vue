<template>
    <view @touchmove.prevent.stop>
        <u-popup class="evaluate-popup" :show="show" @close="handleClose" zIndex="9999">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title !pb-[30rpx] text-[#333] !text-[28rpx]">{{ config.community_comments_status ? ( comment.total ? `${comment.total}条评论`: '暂无评论') : '暂无评论'  }}</view>
                <scroll-view class="scroll-content h-[45vh] pb-[20rpx]" :style="{height: `${scrollHeight}vh` }" scroll-y="true"  @scrolltolower="handleLoadMore">
                    <view v-if="comment.data.length && config.community_comments_status" class="px-[30rpx]">
                        <view class="flex justify-between mb-[30rpx]" v-for="(item,index) in comment.data" :key="index"  @click="handleReply(item)">
                            <u-avatar v-if="item.member" :src="img(item.member.headimg)" size="44" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')" @click="redirect({url: '/addon/sow_community/pages/member',param: {member_id: item.member_id}})"/>
                            <view class="flex-1 ml-[16rpx]">
                                <view class="text-[24rpx] text-[#666] mb-[12rpx] leading-[34rpx]"  v-if="item.member">{{ item.member.nickname }}</view>
                                <view class="text-[26rpx] leading-[36rpx] mb-[20rpx] text-[#333]">{{ item.comment_content }}</view>
                                <view class="flex-between-center">
                                    <view class="flex items-center">
                                        <text class="text-[22rpx] text-[#999] mr-[20rpx]">{{ item.create_time }}</text>
                                        <text class="text-[22rpx] text-primary mr-[30rpx]">回复</text>
                                        <text class="text-[22rpx] text-[#666]" v-if="userInfo && userInfo.member_id == item.member_id" @click.stop="handleDelete(item.comment_id)">删除</text>
                                    </view>
                                    <view class="flex items-center" @click.stop="likeCommentFn(item)">
                                        <text class="nc-iconfont nc-icon-dianzanV6mm text-primary text-[24rpx] text-[#999] mr-[10rpx]" v-if="item.is_like"></text>
                                        <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] text-[#999] mr-[10rpx]" v-else></text>
                                        <text class="text-[22rpx] text-[#999] min-w-[15rpx] text-center">{{ item.like_num }}</text>
                                    </view>
                                </view>

                                <view class="mt-[20rpx]">
                                    <template v-if="item.child_list && item.child_list.length">
                                        <view>
                                            <view class="flex justify-between mb-[20rpx]" v-for="(subItm,subIndex) in item.child_list" :key="subIndex"  @click.stop="handleReply(subItm)">
                                                <u-avatar :src="img(subItm.member.headimg)" size="27" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  @click="redirect({url: '/addon/sow_community/pages/member',param: {member_id: subItm.member_id}})"/>
                                                <view class="flex-1 ml-[14rpx]">
                                                    <view class="text-[22rpx] text-[#666] mb-[12rpx] flex items-center leading-[34rpx]">
                                                        <text class="using-hidden">{{ subItm.member.nickname }}</text>
                                                        <view  class="ml-[6rpx] flex items-center" v-if="subItm.replyMember">
                                                            <text  class="nc-iconfont nc-icon-a-xiangyouV6mm text-[20rpx] mt-[2rpx] mr-[4rpx]"></text>
                                                            <text  class="text-[#666] using-hidden">{{ subItm.replyMember.nickname }}</text>
                                                        </view>
                                                    </view>
                                                    <view class="text-[26rpx] leading-[36rpx] mb-[20rpx] text-[#333]">{{ subItm.comment_content }}</view>
                                                    <view class="flex-between-center">
                                                        <view class="flex items-center">
                                                            <text class="text-[22rpx] text-[#999] mr-[20rpx]">{{ subItm.create_time }}</text>
                                                            <text class="text-[22rpx] text-primary mr-[30rpx]">回复</text>
                                                            <text class="text-[22rpx] text-[#666]" v-if="userInfo && userInfo.member_id == subItm.member_id" @click.stop="handleDelete(subItm.comment_id)">删除</text>
                                                        </view>
                                                        <view class="flex items-center" @click.stop="likeCommentFn(subItm)">
                                                            <text class="nc-iconfont nc-icon-dianzanV6mm text-primary text-[24rpx] text-[#999] mr-[10rpx]" v-if="subItm.is_like"></text>
                                                            <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] text-[#999] mr-[10rpx]" v-else></text>
                                                            <text class="text-[22rpx] text-[#999] min-w-[15rpx] text-center">{{ subItm.like_num }}</text>
                                                        </view>
                                                    </view>
                                                </view>
                                            </view>
                                        </view>
                                        <view class="flex items-center" v-if="item.secondFlag">
                                            <view class="flex items-center text-[#666] text-[24rpx]" v-if="(item.child_list.length - 1) < item.total" @click.stop="addMoreComment(item)">
                                                <text class="w-[40rpx] h-[2rpx] bg-[#d8d8d8]"></text>
                                                <text class="pl-[16rpx]">展开更多</text>
                                                <text class="nc-iconfont nc-icon-xiaV6xx text-[24rpx]"></text>
                                            </view>
                                            <view class="flex items-center text-[#666] text-[24rpx] ml-[30rpx]" @click="packUp(item)">
                                                <text class="w-[40rpx] h-[2rpx] bg-[#d8d8d8]"></text>
                                                <text class="pl-[16rpx]">收起</text>
                                                <text class="nc-iconfont nc-icon-shangV6xx-1 text-[24rpx]"></text>
                                            </view>
                                        </view>
                                    </template>
                                    <view class="flex items-center text-[#666] text-[24rpx]" v-if="(item.reply_num > 1) && !item.secondFlag"  @click.stop="addSecondComment(item)">
                                        <text class="w-[40rpx] h-[2rpx] bg-[#d8d8d8]"></text>
                                        <text class="pl-[16rpx] pr-[4rpx]">展开{{item.reply_num - 1 }}条回复</text>
                                        <text class="nc-iconfont nc-icon-xiaV6xx text-[24rpx]"></text>
                                    </view>
                                </view>
                            </view>
                        </view>
                    </view>
                    <view  class="empty-page-popup h-full mt-0" v-if="(!comment.data.length && !comment.loading) || !config.community_comments_status">
                        <image class="img" :src="img('/addon/sow_community/default_comment.jpg')" model="aspectFit" />
                        <view class="desc">暂无评论</view>
                    </view>
                </scroll-view>
                <view class="h-[100rpx] px-[30rpx] flex-between-center bg-[#fff] padding-bottom" v-if="config.community_comments_status">
                    <input  type="text" v-model.trim="keywords" :placeholder="Object.values(curComment).length ? `回复：${curComment.member.nickname}` : '快来说点儿什么吧...'"  placeholderClass="text-[var(--text-color-light9)] text-[24rpx] leading-[66rpx]" class="flex-1 h-[64rpx] rounded-[32rpx] bg-[#f5f5f5] pl-[30rpx] text-[26rpx] text-[#333]" confirm-type="search" cursor-spacing="8" :focus="focusInput" @blur="onBlur" @focus="onFocus"  />
                    <view class="w-[112rpx] h-[64rpx] rounded-[32rpx]  flex-center text-[24rpx] text-[#fff] ml-[20rpx]" :class="{'primary-btn-bg': keywords, 'bg-[#999]': !keywords }"  @click="handleSend()">发送</view>
                </view>
            </view>
        </u-popup>
        <tips-popup  ref="commentRef" title="确定删除该条评论吗" @confirm="commentDelete"/>
    </view>
</template>
<script lang="ts" setup>
import { ref, computed, reactive, nextTick } from 'vue';
import { img, deepClone, redirect } from '@/utils/common'
import useMemberStore from '@/stores/member'
import { getCommentList, setComment, setCommentLike, deleteComment } from '@/addon/sow_community/api/content';
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'

const show = ref(false)
const comment =reactive<any>({
    page: 1,
    limit: 10,
    total: 0,
    data: [],
    last_page: 1,
    loading: true,
    params:{
        content_id: '',
        parent_comment_id: '',
    }
})
const curComment = ref<any>({}) //当前评论内容
const focusInput = ref(false); // 控制输入框焦点
const scrollHeight = ref(45)  // 小程序控制输入时的高度
const keywords = ref('')
const config = ref<any>({})
// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

const getCommentListFn = (page: number = 1) =>{
    comment.loading = true
    comment.page = page
    if(comment.page > comment.last_page) return false
    getCommentList({
        page: comment.page,
        limit: comment.limit,
        ...comment.params
    }).then((res: any) => {
        let newArr = res.data.data.map((item: any) => {
            item.secondFlag = false
            item.cur_page = 1
            item.total  = 0
            return item
        });
        if (Number(page) === 1) {
            comment.data = []; //如果是第一页需手动制空列表
		}
        if(res.data.last_page){
            comment.last_page = res.data.last_page
        }else{
            comment.last_page = 1
        }
        comment.data =  comment.data.concat(newArr);
        comment.loading = false
    }).catch(() =>{
        comment.loading = false
    })
}

// 分页请求
const handleLoadMore = () => {
    comment.page++
    getCommentListFn(comment.page)
}
const emit = defineEmits(['confirm'])
// 评论
const handleSend = ()  =>{
    if(keywords.value == ''){
        return false
    }
    const obj = {
		content_id: comment.params.content_id,
		comment_content: keywords.value,
		parent_comment_id: curComment.value.parent_comment_id ? curComment.value.parent_comment_id:  curComment.value.comment_id,
        reply_member_id: curComment.value.parent_comment_id ? curComment.value.member_id : 0,
        level: curComment.value.parent_comment_id ?  curComment.value.level : 0
	};
    setComment(obj).then((res: any) =>{
        keywords.value = ''
		if(!config.value.comment_moderation_status){
			if(Object.keys(curComment.value).length){
			    comment.data.forEach((item: any) => {
			        if(item.comment_id == res.data.parent_comment_id){
			            item.child_list.unshift(res.data)
			        }
			    });
			}else{
			    comment.data.unshift(res.data)
			}
		}
        if(!config.value.comment_moderation_status){
            comment.total++
            let val = {
                count: comment.total,
                content_id: comment.params.content_id
            }
            emit('confirm', val)
        }
    })
}

// 回复
const handleReply = (val: any) => {
    curComment.value = deepClone(val)
    focusInput.value = false; // 重置焦点
	nextTick(() => {
	    focusInput.value = true; // 手动设置输入框获取焦点
	});
}
// 输入框获取焦点
const onFocus = () =>{
    // #ifdef MP-WEIXIN
    scrollHeight.value = 25
    // #endif
}
// 输入框失去焦点
const onBlur = () => {
    focusInput.value = false; // 重置输入框焦点
    // #ifdef MP-WEIXIN
    scrollHeight.value = 45
    // #endif
};


// 评论点赞
const likeCommentFn = (data: any) => {
    data.is_like = !data.is_like
    data.is_like ? data.like_num++ : data.like_num--
    setCommentLike({
        comment_id:  data.comment_id,
        status:  data.is_like ? 1 : 0
    }).then((res: any) => {
    })
}

// 删除评论
const commentRef = ref()
const commentId = ref<any>(0)
const handleDelete = (id: number) =>{
    commentId.value = id
    commentRef.value.open()
}
const commentDelete = () =>{
    deleteComment(commentId.value).then(res =>{
        comment.total --
        let val = {
            count: comment.total,
            content_id: comment.params.content_id
        }
        emit('confirm', val)
        comment.page = 1
        getCommentListFn()
    })
}


// 展示二级评论
const addSecondComment = (data: any) =>{
    let obj: object = {
		page: data.cur_page,
		limit: 20,
        content_id: comment.params.content_id,
        parent_comment_id:data.comment_id,
        first_comment_id:data.first_comment_id
	};
    getCommentList(obj).then((res: any) =>{
        data.cur_page = res.data.current_page
        data.total = res.data.total
        data.secondFlag = true
        comment.data.map((item: any) =>{
            if(item.comment_id == data.comment_id){
                item.child_list =  item.child_list.concat(deepClone(res.data.data))
            }
            return item
        })
    })
}

// 展开更多评论
const addMoreComment = (data: any) =>{
    data.cur_page ++
    let obj: object = {
		page: data.cur_page,
		limit: 20,
        content_id: comment.params.content_id,
        parent_comment_id:data.comment_id,
        first_comment_id:data.first_comment_id
	};
    getCommentList(obj).then((res: any) =>{
        comment.data.map((item: any) =>{
            if(item.comment_id == data.comment_id){
                item.child_list =  item.child_list.concat(deepClone(res.data.data))
            }
            return item
        })
    })
}

// 收起
const packUp = (data: any) =>{
    data.cur_page = 1
    data.total_page = 1
    data.secondFlag = false
    comment.data.map((item: any) =>{
        if(item.comment_id == data.comment_id){
            item.child_list = item.child_list.slice(0, 1)
        }
        return item
    })
}

const open = (data:any) => {
    config.value = deepClone(data)
    comment.params.content_id = data.content_id
    comment.total = data.comment_num
    comment.data = []
    getCommentListFn()
    show.value = true
}

const handleClose = () => {
    curComment.value = {}
    keywords.value = ''
    show.value = false
}

defineExpose({
    open
})
</script>
<style lang="scss" scoped>
::v-deep .evaluate-popup .u-slide-up-enter-to{
    z-index: 9999 !important;
}
.padding-bottom{
    padding-bottom: env(safe-area-inset-bottom);
    padding-bottom: constant(safe-area-inset-bottom);
}
</style>
