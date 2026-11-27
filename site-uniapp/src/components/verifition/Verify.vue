<template>
	<view :class="mode=='pop'?'mask':''" v-show="showBox">
		<view :class="mode=='pop'?'verifybox':''" :style="{'max-width':parseInt(imgSize.width) + 30 +'px'}">
			<view class="verifybox-top" v-if="mode=='pop'">
				请完成安全验证
				<view class="verifybox-close" @click="closeBox">
					<text class="nc-iconfont nc-icon-guanbiV6xx2"></text>
				</view>
			</view>
			<view class="verifybox-bottom" :style="{padding:mode=='pop'?'15px':'0'}">
				<!-- 验证码容器 -->
				<component v-if="componentType"
				           :is="componentType"
				           :captchaType="captchaType"
				           :type="verifyType"
				           :figure="figure"
				           :arith="arith"
				           :mode="mode"
				           :vSpace="vSpace"
				           :explain="explain"
				           :imgSize="imgSize"
				           :blockSize="blockSize"
				           :barSize="barSize"
						    @close="closeBox"
							@success="success"
				           ref="instance"></component>
			</view>
		</view>
	</view>
</template>
<script type="text/babel">
    /**
     * Verify 验证码组件
     * @description 分发验证码使用
     * */
    import VerifySlide from './Verify/VerifySlide.vue'
    import VerifyPoints from './Verify/VerifyPoints.vue'
    import { computed, ref, toRefs, watchEffect } from 'vue'

    export default {
        name: 'Vue2Verify',
        components: {
            VerifySlide,
            VerifyPoints
        },
        props: {
            captchaType: {
                type: String,
                required: true
            },
            figure: {
                type: Number
            },
            arith: {
                type: Number
            },
            mode: {
                type: String,
                default: 'pop'
            },
            vSpace: {
                type: Number
            },
            explain: {
                type: String
            },
            imgSize: {
                type: Object,
                default() {
                    return {
                        width: '310px',
                        height: '155px'
                    }
                }
            },
            blockSize: {
                type: Object
            },
            barSize: {
                type: Object
            }
        },
        setup(props, context) {
            const { captchaType, figure, arith, mode, vSpace, explain, imgSize, blockSize, barSize } = toRefs(props)
            const clickShow = ref(false)
            const verifyType = ref(undefined)
            const componentType = ref(undefined)
            const instance = ref({})

            const showBox = computed(() => {
                if (mode.value == 'pop') {
                    return clickShow.value
                } else {
                    return true
                }
            })
            /**
             * refresh
             * @description 刷新
             * */
            const refresh = () => {
                if (instance.value.refresh) {
                    instance.value.refresh()
                }
            }
            const closeBox = () => {
                clickShow.value = false
                refresh()
            }
            const success = (data) => {
                context.emit('success', data)	
            }
            const show = () => {
                if (mode.value == 'pop') {
                    clickShow.value = true
                }
            }
            watchEffect(() => {
                switch (captchaType.value) {
                    case 'blockPuzzle':
                        verifyType.value = '2'
                        componentType.value = 'VerifySlide'
                        break
                    case 'clickWord':
                        verifyType.value = ''
                        componentType.value = 'VerifyPoints'
                        break
                }
            })

            return {
                clickShow,
                verifyType,
                componentType,
                instance,
                showBox,
                closeBox,
				success,
                show
            }
        }
    }
</script>
<style lang="scss" scoped>
.verifybox {
	position: relative;
	box-sizing: border-box;
	border-radius: 2px;
	border: 1px solid #e4e7eb;
	background-color: #fff;
	box-shadow: 0 0 10px rgba(0, 0, 0, .3);
	left: 50%;
	top: 50%;
	transform: translate(-50%, -50%);
}

.verifybox-top {
	padding: 0 15px;
	height: 50px;
	line-height: 50px;
	text-align: left;
	font-size: 16px;
	color: #45494c;
	border-bottom: 1px solid #e4e7eb;
	box-sizing: border-box;
}

.verifybox-bottom {
	padding: 15px;
	box-sizing: border-box;
}

.verifybox-close {
	position: absolute;
	top: 13px;
	right: 9px;
	width: 24px;
	height: 24px;
	text-align: center;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
}

.mask {
	position: fixed;
	top: 0;
	left: 0;
	z-index: 1001;
	width: 100%;
	height: 100vh;
	background: rgba(0, 0, 0, .3);
	/* display: none; */
	transition: all .5s;
}

.verify-tips {
	position: absolute;
	left: 0px;
	bottom: 0px;
	width: 100%;
	height: 30px;
	line-height: 30px;
	color: #fff;
}

.suc-bg {
	background-color: rgba(92, 184, 92, .5);
	filter: progid:DXImageTransform.Microsoft.gradient(startcolorstr=#7f5CB85C, endcolorstr=#7f5CB85C);
}

.err-bg {
	background-color: rgba(217, 83, 79, .5);
	filter: progid:DXImageTransform.Microsoft.gradient(startcolorstr=#7fD9534F, endcolorstr=#7fD9534F);
}

.tips-enter, .tips-leave-to {
	bottom: -30px;
}

.tips-enter-active, .tips-leave-active {
	transition: bottom .5s;
}

</style>
