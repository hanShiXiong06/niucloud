<template>
	<div style="position: relative">
		<div class="verify-img-out">
			<div class="verify-img-panel" :style="{'width': setSize.imgWidth,
                                                   'height': setSize.imgHeight,
                                                   'background-size' : setSize.imgWidth + ' '+ setSize.imgHeight,
                                                   'margin-bottom': vSpace + 'px'}"
			>
				<div class="verify-refresh" style="z-index:3" @click="refresh" v-show="showRefresh">
					<i class="iconfont icon-refresh"></i>
				</div>
				<img :src="'data:image/png;base64,'+pointBackImgBase"
				     ref="canvas"
				     alt="" style="width:100%;height:100%;display:block"
				     @click="bindingClick?canvasClick($event):undefined">

				<div v-for="(tempPoint, index) in tempPoints" :key="index" class="point-area"
				     :style="{
                        'background-color':'#1abd6c',
                        color:'#fff',
                        'z-index':9999,
                        width:'20px',
                        height:'20px',
                        'text-align':'center',
                        'line-height':'20px',
                        'border-radius': '50%',
                        position:'absolute',
                        top:parseInt(tempPoint.y-10) + 'px',
                        left:parseInt(tempPoint.x-10) + 'px'
                     }">
					{{index + 1}}
				</div>
			</div>
		</div>
		<!-- 'height': this.barSize.height, -->
		<div class="verify-bar-area"
		     :style="{'width': setSize.imgWidth,
                      'color': this.barAreaColor,
                      'border-color': this.barAreaBorderColor,
                      'line-height':this.barSize.height}">
			<span class="verify-msg">{{text}}</span>
		</div>
	</div>
</template>
<script type="text/babel">
    /**
     * VerifyPoints
     * @description 点选
     * */
    import { resetSize } from './../utils/util'
    import { aesEncrypt } from './../utils/ase'
    import { reqGet, reqCheck } from './../api/index'
    import { onMounted, reactive, ref, nextTick, toRefs, getCurrentInstance } from 'vue'

    export default {
        name: 'VerifyPoints',
        props: {
            // 弹出式pop，固定fixed
            mode: {
                type: String,
                default: 'fixed'
            },
            captchaType: {
                type: String
            },
            // 间隔
            vSpace: {
                type: Number,
                default: 5
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
            const { mode, captchaType, vSpace, imgSize, barSize } = toRefs(props)
            const { proxy } = getCurrentInstance()
            const secretKey = ref('') // 后端返回的ase加密秘钥
            const checkNum = ref(3) // 默认需要点击的字数
            const fontPos = reactive([]) // 选中的坐标信息
            const checkPosArr = reactive([]) // 用户点击的坐标
            const num = ref(1) // 点击的记数
            const pointBackImgBase = ref('') // 后端获取到的背景图片
            const pointTextList = reactive([]) // 后端返回的点击字体顺序
            const backToken = ref('') // 后端返回的token值
            const setSize = reactive({
                imgHeight: 0,
                imgWidth: 0,
                barHeight: 0,
                barWidth: 0
            })
            const tempPoints = reactive([])
            const text = ref('')
            const barAreaColor = ref(undefined)
            const barAreaBorderColor = ref(undefined)
            const showRefresh = ref(true)
            const bindingClick = ref(true)
            const init = () => {
                // 加载页面
                fontPos.splice(0, fontPos.length)
                checkPosArr.splice(0, checkPosArr.length)
                num.value = 1
                getPictrue()
                nextTick(() => {
                    const { imgHeight, imgWidth, barHeight, barWidth } = resetSize(proxy)
                    setSize.imgHeight = imgHeight
                    setSize.imgWidth = imgWidth
                    setSize.barHeight = barHeight
                    setSize.barWidth = barWidth
                    proxy.$parent.$emit('ready', proxy)
                })
            }
            onMounted(() => {
                // 禁止拖拽
                init()
                proxy.$el.onselectstart = function () {
                    return false
                }
            })
            const canvas = ref(null)
            const canvasClick = (e) => {
                checkPosArr.push(getMousePos(canvas, e))
                if (num.value == checkNum.value) {
                    num.value = createPoint(getMousePos(canvas, e))
                    // 按比例转换坐标值
                    const arr = pointTransfrom(checkPosArr, setSize)
                    checkPosArr.length = 0
                    checkPosArr.push(...arr)
                    // 等创建坐标执行完
                    setTimeout(() => {
                        // var flag = this.comparePos(this.fontPos, this.checkPosArr);
                        // 发送后端请求
                        const captchaVerification = secretKey.value ? aesEncrypt(backToken.value + '---' + JSON.stringify(checkPosArr), secretKey.value) : backToken.value + '---' + JSON.stringify(checkPosArr)
                        const data = {
                            captchaType: captchaType.value,
                            'captcha_code': secretKey.value ? aesEncrypt(JSON.stringify(checkPosArr), secretKey.value) : JSON.stringify(checkPosArr),
                            'captcha_key': backToken.value
                        }
                        reqCheck(data).then(res => {
                            if (res.code == 1) {
                                barAreaColor.value = '#4cae4c'
                                barAreaBorderColor.value = '#5cb85c'
                                text.value = '验证成功'
                                bindingClick.value = false
                                if (mode.value == 'pop') {
                                    setTimeout(() => {
                                        proxy.$parent.clickShow = false
                                        refresh()
                                    }, 1500)
                                }
                                proxy.$emit('success', { captchaVerification })
                            } else {
                                proxy.$parent.$emit('error', proxy)
                                barAreaColor.value = '#d9534f'
                                barAreaBorderColor.value = '#d9534f'
                                text.value = '验证失败'
                                setTimeout(() => {
                                    refresh()
                                }, 700)
                            }
                        })
                    }, 400)
                }
                if (num.value < checkNum.value) {
                    num.value = createPoint(getMousePos(canvas, e))
                }
            }
            // 获取坐标
            const getMousePos = function (obj, e) {
                const x = e.offsetX
                const y = e.offsetY
                return { x, y }
            }
            // 创建坐标点
            const createPoint = function (pos) {
                tempPoints.push(Object.assign({}, pos))
                return num.value + 1
            }
            const refresh = function () {
                tempPoints.splice(0, tempPoints.length)
                barAreaColor.value = '#000'
                barAreaBorderColor.value = '#ddd'
                bindingClick.value = true
                fontPos.splice(0, fontPos.length)
                checkPosArr.splice(0, checkPosArr.length)
                num.value = 1
                getPictrue()
                text.value = '验证失败'
                showRefresh.value = true
            }

            // 请求背景图片和验证图片
            function getPictrue() {
                const data = {
                    captchaType: captchaType.value
                }
                reqGet(data).then(res => {
                    if (res.code == 1) {
                        pointBackImgBase.value = res.data.originalImageBase64
                        backToken.value = res.data.token
                        secretKey.value = res.data.secretKey
                        pointTextList.value = res.data.wordList
                        text.value = '请依次点击【' + pointTextList.value.join(',') + '】'
                    } else {
                        text.value = res.msg
                    }
                })
            }

            // 坐标转换函数
            const pointTransfrom = function (pointArr, imgSize) {
                const newPointArr = pointArr.map(p => {
                    const x = Math.round(310 * p.x / parseInt(imgSize.imgWidth))
                    const y = Math.round(155 * p.y / parseInt(imgSize.imgHeight))
                    return { x, y }
                })
                return newPointArr
            }
            return {
                secretKey,
                checkNum,
                fontPos,
                checkPosArr,
                num,
                pointBackImgBase,
                pointTextList,
                backToken,
                setSize,
                tempPoints,
                text,
                barAreaColor,
                barAreaBorderColor,
                showRefresh,
                bindingClick,
                init,
                canvas,
                canvasClick,
                getMousePos,
                createPoint,
                refresh,
                getPictrue,
                pointTransfrom
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
