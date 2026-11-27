<template>
	<view style="position: relative;">
		<view v-if="type === '2'" class="verify-img-out"
		     :style="{height: (parseInt(setSize.imgHeight) + vSpace) + 'px'}"
		>
			<view class="verify-img-panel" :style="{width: setSize.imgWidth,
                                                   height: setSize.imgHeight,}">
				<img :src="'data:image/png;base64,'+backImgBase" alt="" style="width:100%;height:100%;display:block">
				<view class="verify-refresh" @click="refresh" v-show="showRefresh"><i class="nc-iconfont nc-icon-shuaxinV6xx  icon-refresh"></i>
				</view>
				<text class="verify-tips" v-if="tipWords" :class="passFlag ?'suc-bg':'err-bg'">{{tipWords}}</text>
			</view>
		</view>
		<!-- 公共部分 -->
		<view class="verify-bar-area" :style="{width: setSize.imgWidth,
                                              height: barSize.height,
                                              'line-height':barSize.height}">
			<text class="verify-msg" v-text="text"></text>
			<view class="verify-left-bar"
			     :style="{width: (leftBarWidth!==undefined)?leftBarWidth: barSize.height, height: barSize.height, 'border-color': leftBarBorderColor, transaction: transitionWidth}">
				<text class="verify-msg" v-text="finishText"></text>
				<view class="verify-move-block"
				     @touchstart="start"
				     @mousedown="start"
				     :style="{width: barSize.height, height: barSize.height, 'background-color': moveBlockBackgroundColor, left: moveBlockLeft, transition: transitionLeft}">
					<text :class="['verify-icon nc-iconfont', iconClass]"
					   :style="{color: iconColor}"></text>
					<view v-if="type === '2'" class="verify-sub-block"
					     :style="{'width':Math.floor(parseInt(setSize.imgWidth)*47/310)+ 'px',
                                  'height': setSize.imgHeight,
                                  'top':'-' + (parseInt(setSize.imgHeight) + vSpace) + 'px',
                                  'background-size': setSize.imgWidth + ' ' + setSize.imgHeight,
                                  }">
						<img :src="'data:image/png;base64,'+blockBackImgBase" alt=""
						     style="width:100%;height:100%;display:block;-webkit-user-drag:none;">
					</view>
				</view>
			</view>
		</view>
	</view>
</template>
<script type="text/babel">
    /**
     * VerifySlide
     * @description 滑块
     * */
    import { aesEncrypt } from './../utils/ase'
    import { resetSize } from './../utils/util'
    import { reqGet, reqCheck } from './../api/index'
    import { computed, onMounted, reactive, ref, watch, nextTick, toRefs, getCurrentInstance } from 'vue'
    //  "captchaType":"blockPuzzle",
    export default {
        name: 'VerifySlide',
        props: {
            captchaType: {
                type: String
            },
            type: {
                type: String,
                default: '1'
            },
            // 弹出式pop，固定fixed
            mode: {
                type: String,
                default: 'fixed'
            },
            vSpace: {
                type: Number,
                default: 5
            },
            explain: {
                type: String,
                default: '向右滑动完成验证'
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
                type: Object,
                default() {
                    return {
                        width: '50px',
                        height: '50px'
                    }
                }
            },
            barSize: {
                type: Object,
                default() {
                    return {
                        width: '310px',
                        height: '40px'
                    }
                }
            }
        },
        setup(props, context) {
            const { mode, captchaType, vSpace, imgSize, barSize, type, blockSize, explain } = toRefs(props)
            const { proxy } = getCurrentInstance()
            const secretKey = ref(''), // 后端返回的ase加密秘钥
                passFlag = ref(''), // 是否通过的标识
                backImgBase = ref(''), // 验证码背景图片
                blockBackImgBase = ref(''), // 验证滑块的背景图片
                backToken = ref(''), // 后端返回的唯一token值
                startMoveTime = ref(''), // 移动开始的时间
                endMoveTime = ref(''), // 移动结束的时间
                tipsBackColor = ref(''), // 提示词的背景颜色
                tipWords = ref(''),
                text = ref(''),
                finishText = ref(''),
                setSize = reactive({
                    imgHeight: 0,
                    imgWidth: 0,
                    barHeight: 0,
                    barWidth: 0
                }),
                top = ref(0),
                left = ref(0),
                moveBlockLeft = ref(undefined),
                leftBarWidth = ref(undefined),
                // 移动中样式
                moveBlockBackgroundColor = ref(undefined),
                leftBarBorderColor = ref('#ddd'),
                iconColor = ref(undefined),
                iconClass = ref('nc-icon-youV6xx'),
                status = ref(false), // 鼠标状态
                isEnd = ref(false),		// 是够验证完成
                showRefresh = ref(true),
                transitionLeft = ref(''),
                transitionWidth = ref(''),
                startLeft = ref(0)

            const barArea = computed(() => {
                return proxy.$el.querySelector('.verify-bar-area')
            })

            function init() {
                text.value = explain.value
                getPictrue()
                nextTick(() => {
                    let { imgHeight, imgWidth, barHeight, barWidth } = resetSize(proxy)
                    setSize.imgHeight = imgHeight
                    setSize.imgWidth = imgWidth
                    setSize.barHeight = barHeight
                    setSize.barWidth = barWidth
                    proxy.$parent.$emit('ready', proxy)
                })

                window.removeEventListener('touchmove', function (e) {
                    move(e)
                })
                window.removeEventListener('mousemove', function (e) {
                    move(e)
                })

                // 鼠标松开
                window.removeEventListener('touchend', function () {
                    end()
                })
                window.removeEventListener('mouseup', function () {
                    end()
                })

                window.addEventListener('touchmove', function (e) {
                    move(e)
                })
                window.addEventListener('mousemove', function (e) {
                    move(e)
                })

                // 鼠标松开
                window.addEventListener('touchend', function () {
                    end()
                })
                window.addEventListener('mouseup', function () {
                    end()
                })
            }

            watch(type, () => {
                init()
            })
            onMounted(() => {
                // 禁止拖拽
                init()
                proxy.$el.onselectstart = function () {
                    return false
                }
            })

            // 鼠标按下
            function start(e) {
                e = e || window.event
                if (!e.touches) { // 兼容PC端
                    var x = e.clientX
                } else { // 兼容移动端
                    var x = e.touches[0].pageX
                }
                startLeft.value = Math.floor(x - barArea.value.getBoundingClientRect().left)
                startMoveTime.value = +new Date() // 开始滑动的时间
                if (isEnd.value == false) {
                    text.value = ''
                    moveBlockBackgroundColor.value = '#337ab7'
                    leftBarBorderColor.value = '#337AB7'
                    iconColor.value = '#fff'
                    e.stopPropagation()
                    status.value = true
                }
            }

            // 鼠标移动
            function move(e) {
                e = e || window.event
                if (status.value && isEnd.value == false) {
                    if (!e.touches) { // 兼容PC端
                        var x = e.clientX
                    } else { // 兼容移动端
                        var x = e.touches[0].pageX
                    }
                    var bar_area_left = barArea.value.getBoundingClientRect().left
                    var move_block_left = x - bar_area_left // 小方块相对于父元素的left值
                    if (move_block_left >= barArea.value.offsetWidth - parseInt(parseInt(blockSize.value.width) / 2) - 2) {
                        move_block_left = barArea.value.offsetWidth - parseInt(parseInt(blockSize.value.width) / 2) - 2
                    }
                    if (move_block_left <= 0) {
                        move_block_left = parseInt(parseInt(blockSize.value.width) / 2)
                    }
                    // 拖动后小方块的left值
                    moveBlockLeft.value = ( move_block_left - startLeft.value ) + 'px'
                    leftBarWidth.value = ( move_block_left - startLeft.value ) + 'px'
                }
            }

            // 鼠标松开
            function end() {
                endMoveTime.value = +new Date()
                // 判断是否重合
                if (status.value && isEnd.value == false) {
                    var moveLeftDistance = parseInt(( moveBlockLeft.value || '' ).replace('px', ''))
                    moveLeftDistance = moveLeftDistance * 310 / parseInt(setSize.imgWidth)
                    const data = {
                        captchaType: captchaType.value,
                        'captcha_code': secretKey.value ? aesEncrypt(JSON.stringify({
                            x: moveLeftDistance,
                            y: 5.0
                        }), secretKey.value) : JSON.stringify({ x: moveLeftDistance, y: 5.0 }),
                        'captcha_key': backToken.value
                    }
                    reqCheck(data).then(res => {
                        if (res.code == 1) {
                            moveBlockBackgroundColor.value = '#5cb85c'
                            leftBarBorderColor.value = '#5cb85c'
                            iconColor.value = '#fff'
                            iconClass.value = 'icon-check'
                            showRefresh.value = false
                            isEnd.value = true
                            if (mode.value == 'pop') {
                                setTimeout(() => {
                                    proxy.$parent.clickShow = false
                                    refresh()
                                }, 1500)
                            }
                            passFlag.value = true
                            tipWords.value = `${ ( ( endMoveTime.value - startMoveTime.value ) / 1000 ).toFixed(2) }s验证成功`
                            var captchaVerification = secretKey.value ? aesEncrypt(backToken.value + '---' + JSON.stringify({
                                x: moveLeftDistance,
                                y: 5.0
                            }), secretKey.value) : backToken.value + '---' + JSON.stringify({
                                x: moveLeftDistance,
                                y: 5.0
                            })
                            setTimeout(() => {
                                tipWords.value = ''
                                proxy.$emit('close')
                                proxy.$emit('success', { captchaVerification })
                            }, 1000)
                        } else {
                            moveBlockBackgroundColor.value = '#d9534f'
                            leftBarBorderColor.value = '#d9534f'
                            iconColor.value = '#fff'
                            iconClass.value = 'nc-icon-guanbiV6xx2'
                            passFlag.value = false
                            setTimeout(function () {
                                refresh()
                            }, 1000)
                            proxy.$parent.$emit('error', proxy)
                            tipWords.value = '验证失败'
                            setTimeout(() => {
                                tipWords.value = ''
                            }, 1000)
                        }
                    })
                    status.value = false
                }
            }

            const refresh = () => {
                showRefresh.value = true
                finishText.value = ''

                transitionLeft.value = 'left .3s'
                moveBlockLeft.value = 0

                leftBarWidth.value = undefined
                transitionWidth.value = 'width .3s'

                leftBarBorderColor.value = '#ddd'
                moveBlockBackgroundColor.value = '#fff'
                iconColor.value = '#000'
                iconClass.value = 'nc-icon-youV6xx'
                isEnd.value = false

                getPictrue()
                setTimeout(() => {
                    transitionWidth.value = ''
                    transitionLeft.value = ''
                    text.value = explain.value
                }, 300)
            }

            // 请求背景图片和验证图片
            function getPictrue() {
                const data = {
                    captchaType: captchaType.value
                }
                reqGet(data).then(res => {
                    if (res.code == 1) {
                        backImgBase.value = res.data.originalImageBase64
                        blockBackImgBase.value = res.data.jigsawImageBase64
                        backToken.value = res.data.token
                        secretKey.value = res.data.secretKey
                    } else {
                        tipWords.value = res.msg
                    }
                })
            }

            return {
                secretKey, // 后端返回的ase加密秘钥
                passFlag, // 是否通过的标识
                backImgBase, // 验证码背景图片
                blockBackImgBase, // 验证滑块的背景图片
                backToken, // 后端返回的唯一token值
                startMoveTime, // 移动开始的时间
                endMoveTime, // 移动结束的时间
                tipsBackColor, // 提示词的背景颜色
                tipWords,
                text,
                finishText,
                setSize,
                top,
                left,
                moveBlockLeft,
                leftBarWidth,
                // 移动中样式
                moveBlockBackgroundColor,
                leftBarBorderColor,
                iconColor,
                iconClass,
                status, // 鼠标状态
                isEnd,		// 是够验证完成
                showRefresh,
                transitionLeft,
                transitionWidth,
                barArea,
                refresh,
                start
            }
        }
    }
</script>
<style lang="scss" scoped> 
/* ---------------------------- */
/*常规验证码*/
.verify-code {
    font-size: 20px;
    text-align: center;
    cursor: pointer;
    margin-bottom: 5px;
    border: 1px solid #ddd;
}

.cerify-code-panel {
    height: 100%;
    overflow: hidden;
}

.verify-code-area {
    float: left;
}

.verify-input-area {
    float: left;
    width: 60%;
    padding-right: 10px;

}

.verify-change-area {
    line-height: 30px;
    float: left;
}

.varify-input-code {
    display: inline-block;
    width: 100%;
    height: 25px;
}

.verify-change-code {
    color: #337AB7;
    cursor: pointer;
}

.verify-btn {
    width: 200px;
    height: 30px;
    background-color: #337AB7;
    color: #FFFFFF;
    border: none;
    margin-top: 10px;
}

/*滑动验证码*/
.verify-bar-area {
    position: relative;
    background: #FFFFFF;
    text-align: center;
    -webkit-box-sizing: content-box;
    -moz-box-sizing: content-box;
    box-sizing: content-box;
    border: 1px solid #ddd;
    -webkit-border-radius: 4px;
}

.verify-bar-area .verify-move-block {
    position: absolute;
    top: 0px;
    left: 0;
    background: #fff;
    cursor: pointer;
    -webkit-box-sizing: content-box;
    -moz-box-sizing: content-box;
    box-sizing: content-box;
    box-shadow: 0 0 2px #888888;
    -webkit-border-radius: 1px;
}

.verify-bar-area .verify-move-block:hover {
    background-color: #337ab7;
    color: #FFFFFF;
}

.verify-bar-area .verify-left-bar {
    position: absolute;
    top: -1px;
    left: -1px;
    background: #f0fff0;
    cursor: pointer;
    -webkit-box-sizing: content-box;
    -moz-box-sizing: content-box;
    box-sizing: content-box;
    border: 1px solid #ddd;
}

.verify-img-panel {
    margin: 0;
    -webkit-box-sizing: content-box;
    -moz-box-sizing: content-box;
    box-sizing: content-box;
    border-top: 1px solid #ddd;
    border-bottom: 1px solid #ddd;
    border-radius: 3px;
    position: relative;
}

.verify-img-panel .verify-refresh {
    width: 25px;
    height: 25px;
    text-align: center;
    padding: 5px;
    cursor: pointer;
    position: absolute;
    top: 0;
    right: 0;
    z-index: 2;
}

.verify-img-panel .icon-refresh {
    font-size: 20px;
    color: #fff;
}

.verify-img-panel .verify-gap {
    background-color: #fff;
    position: relative;
    z-index: 2;
    border: 1px solid #fff;
}

.verify-bar-area .verify-move-block .verify-sub-block {
    position: absolute;
    text-align: center;
    z-index: 3;
    /* border: 1px solid #fff; */
}

.verify-bar-area .verify-move-block .verify-icon {
    font-size: 18px;
}

.verify-bar-area .verify-msg {
    z-index: 3;
}
</style>

