import{b1 as o,b2 as t,T as e,aw as n,e as s,f as i,D as l,n as r,C as p,a0 as c,b3 as a,af as h,ag as d,ah as u,ax as w,b4 as g,ac as f,w as m,h as y,A as S,B as D,z as T,i as x,v as U,m as b}from"./index-5ccb7aca.js";import{_ as v}from"./_plugin-vue_export-helper.1b428a4d.js";function B(o,t){let e=this;e.version="1.3.7",e.options=o||{},e.isScrollBody=t||!1,e.isDownScrolling=!1,e.isUpScrolling=!1;let n=e.options.down&&e.options.down.callback;e.initDownScroll(),e.initUpScroll(),setTimeout((function(){(e.optDown.use||e.optDown.native)&&e.optDown.auto&&n&&(e.optDown.autoShowLoading?e.triggerDownScroll():e.optDown.callback&&e.optDown.callback(e)),e.isUpAutoLoad||setTimeout((function(){e.optUp.use&&e.optUp.auto&&!e.isUpAutoLoad&&e.triggerUpScroll()}),100)}),30)}B.prototype.extendDownScroll=function(o){B.extend(o,{use:!0,auto:!0,native:!1,autoShowLoading:!1,isLock:!1,offset:80,startTop:100,inOffsetRate:1,outOffsetRate:.2,bottomOffset:20,minAngle:45,textInOffset:"下拉刷新",textOutOffset:"释放更新",textLoading:"加载中 ...",textSuccess:"加载成功",textErr:"加载失败",beforeEndDelay:0,bgColor:"transparent",textColor:"gray",inited:null,inOffset:null,outOffset:null,onMoving:null,beforeLoading:null,showLoading:null,afterLoading:null,beforeEndDownScroll:null,endDownScroll:null,afterEndDownScroll:null,callback:function(o){o.resetUpScroll()}})},B.prototype.extendUpScroll=function(o){B.extend(o,{use:!0,auto:!0,isLock:!1,isBoth:!0,callback:null,page:{num:0,size:10,time:null},noMoreSize:5,offset:150,textLoading:"加载中 ...",textNoMore:"-- END --",bgColor:"transparent",textColor:"gray",inited:null,showLoading:null,showNoMore:null,hideUpScroll:null,errDistance:60,toTop:{src:null,offset:1e3,duration:300,btnClick:null,onShow:null,zIndex:9990,left:null,right:20,bottom:120,safearea:!1,width:72,radius:"50%"},empty:{use:!0,icon:null,tip:"~ 暂无相关数据 ~",btnText:"",btnClick:null,onShow:null,fixed:!1,top:"100rpx",zIndex:99},onScroll:!1})},B.extend=function(o,t){if(!o)return t;for(let e in t)if(null==o[e]){let n=t[e];o[e]=null!=n&&"object"==typeof n?B.extend({},n):n}else"object"==typeof o[e]&&B.extend(o[e],t[e]);return o},B.prototype.hasColor=function(o){if(!o)return!1;let t=o.toLowerCase();return"#fff"!=t&&"#ffffff"!=t&&"transparent"!=t&&"white"!=t},B.prototype.initDownScroll=function(){let o=this;o.optDown=o.options.down||{},!o.optDown.textColor&&o.hasColor(o.optDown.bgColor)&&(o.optDown.textColor="#fff"),o.extendDownScroll(o.optDown),o.isScrollBody&&o.optDown.native?o.optDown.use=!1:o.optDown.native=!1,o.downHight=0,o.optDown.use&&o.optDown.inited&&setTimeout((function(){o.optDown.inited(o)}),0)},B.prototype.touchstartEvent=function(o){this.optDown.use&&(this.startPoint=this.getPoint(o),this.startTop=this.getScrollTop(),this.startAngle=0,this.lastPoint=this.startPoint,this.maxTouchmoveY=this.getBodyHeight()-this.optDown.bottomOffset,this.inTouchend=!1)},B.prototype.touchmoveEvent=function(o){if(!this.optDown.use)return;let t=this,e=t.getScrollTop(),n=t.getPoint(o);if(n.y-t.startPoint.y>0&&(t.isScrollBody&&e<=0||!t.isScrollBody&&(e<=0||e<=t.optDown.startTop&&e===t.startTop))&&!t.inTouchend&&!t.isDownScrolling&&!t.optDown.isLock&&(!t.isUpScrolling||t.isUpScrolling&&t.optUp.isBoth)){if(t.startAngle||(t.startAngle=t.getAngle(t.lastPoint,n)),t.startAngle<t.optDown.minAngle)return;if(t.maxTouchmoveY>0&&n.y>=t.maxTouchmoveY)return t.inTouchend=!0,void t.touchendEvent();t.preventDefault(o);let e=n.y-t.lastPoint.y;t.downHight<t.optDown.offset?(1!==t.movetype&&(t.movetype=1,t.isDownEndSuccess=null,t.optDown.inOffset&&t.optDown.inOffset(t),t.isMoveDown=!0),t.downHight+=e*t.optDown.inOffsetRate):(2!==t.movetype&&(t.movetype=2,t.optDown.outOffset&&t.optDown.outOffset(t),t.isMoveDown=!0),t.downHight+=e>0?e*t.optDown.outOffsetRate:e),t.downHight=Math.round(t.downHight);let s=t.downHight/t.optDown.offset;t.optDown.onMoving&&t.optDown.onMoving(t,s,t.downHight)}t.lastPoint=n},B.prototype.touchendEvent=function(o){if(this.optDown.use)if(this.isMoveDown)this.downHight>=this.optDown.offset?this.triggerDownScroll():(this.downHight=0,this.endDownScrollCall(this)),this.movetype=0,this.isMoveDown=!1;else if(!this.isScrollBody&&this.getScrollTop()===this.startTop){if(this.getPoint(o).y-this.startPoint.y<0){this.getAngle(this.getPoint(o),this.startPoint)>80&&this.triggerUpScroll(!0)}}},B.prototype.getPoint=function(o){return o?o.touches&&o.touches[0]?{x:o.touches[0].pageX,y:o.touches[0].pageY}:o.changedTouches&&o.changedTouches[0]?{x:o.changedTouches[0].pageX,y:o.changedTouches[0].pageY}:{x:o.clientX,y:o.clientY}:{x:0,y:0}},B.prototype.getAngle=function(o,t){let e=Math.abs(o.x-t.x),n=Math.abs(o.y-t.y),s=Math.sqrt(e*e+n*n),i=0;return 0!==s&&(i=Math.asin(n/s)/Math.PI*180),i},B.prototype.triggerDownScroll=function(){this.optDown.beforeLoading&&this.optDown.beforeLoading(this)||(this.showDownScroll(),!this.optDown.native&&this.optDown.callback&&this.optDown.callback(this))},B.prototype.showDownScroll=function(){this.isDownScrolling=!0,this.optDown.native?(o(),this.showDownLoadingCall(0)):(this.downHight=this.optDown.offset,this.showDownLoadingCall(this.downHight))},B.prototype.showDownLoadingCall=function(o){this.optDown.showLoading&&this.optDown.showLoading(this,o),this.optDown.afterLoading&&this.optDown.afterLoading(this,o)},B.prototype.onPullDownRefresh=function(){this.isDownScrolling=!0,this.showDownLoadingCall(0),this.optDown.callback&&this.optDown.callback(this)},B.prototype.endDownScroll=function(){if(this.optDown.native)return this.isDownScrolling=!1,this.endDownScrollCall(this),void t();let o=this,e=function(){o.downHight=0,o.isDownScrolling=!1,o.endDownScrollCall(o),o.isScrollBody||(o.setScrollHeight(0),o.scrollTo(0,0))},n=0;o.optDown.beforeEndDownScroll&&(n=o.optDown.beforeEndDownScroll(o),null==o.isDownEndSuccess&&(n=0)),"number"==typeof n&&n>0?setTimeout(e,n):e()},B.prototype.endDownScrollCall=function(){this.optDown.endDownScroll&&this.optDown.endDownScroll(this),this.optDown.afterEndDownScroll&&this.optDown.afterEndDownScroll(this)},B.prototype.lockDownScroll=function(o){null==o&&(o=!0),this.optDown.isLock=o},B.prototype.lockUpScroll=function(o){null==o&&(o=!0),this.optUp.isLock=o},B.prototype.initUpScroll=function(){let o=this;o.optUp=o.options.up||{use:!1},!o.optUp.textColor&&o.hasColor(o.optUp.bgColor)&&(o.optUp.textColor="#fff"),o.extendUpScroll(o.optUp),!1!==o.optUp.use&&(o.optUp.hasNext=!0,o.startNum=o.optUp.page.num+1,o.optUp.inited&&setTimeout((function(){o.optUp.inited(o)}),0))},B.prototype.onReachBottom=function(){this.isScrollBody&&!this.isUpScrolling&&!this.optUp.isLock&&this.optUp.hasNext&&this.triggerUpScroll()},B.prototype.onPageScroll=function(o){this.isScrollBody&&(this.setScrollTop(o.scrollTop),o.scrollTop>=this.optUp.toTop.offset?this.showTopBtn():this.hideTopBtn())},B.prototype.scroll=function(o,t){this.setScrollTop(o.scrollTop),this.setScrollHeight(o.scrollHeight),null==this.preScrollY&&(this.preScrollY=0),this.isScrollUp=o.scrollTop-this.preScrollY>0,this.preScrollY=o.scrollTop,this.isScrollUp&&this.triggerUpScroll(!0),o.scrollTop>=this.optUp.toTop.offset?this.showTopBtn():this.hideTopBtn(),this.optUp.onScroll&&t&&t()},B.prototype.triggerUpScroll=function(o){if(!this.isUpScrolling&&this.optUp.use&&this.optUp.callback){if(!0===o){let o=!1;if(!this.optUp.hasNext||this.optUp.isLock||this.isDownScrolling||this.getScrollBottom()<=this.optUp.offset&&(o=!0),!1===o)return}this.showUpScroll(),this.optUp.page.num++,this.isUpAutoLoad=!0,this.num=this.optUp.page.num,this.size=this.optUp.page.size,this.time=this.optUp.page.time,this.optUp.callback(this)}},B.prototype.showUpScroll=function(){this.isUpScrolling=!0,this.optUp.showLoading&&this.optUp.showLoading(this)},B.prototype.showNoMore=function(){this.optUp.hasNext=!1,this.optUp.showNoMore&&this.optUp.showNoMore(this)},B.prototype.hideUpScroll=function(){this.optUp.hideUpScroll&&this.optUp.hideUpScroll(this)},B.prototype.endUpScroll=function(o){null!=o&&(o?this.showNoMore():this.hideUpScroll()),this.isUpScrolling=!1},B.prototype.resetUpScroll=function(o){if(this.optUp&&this.optUp.use){let t=this.optUp.page;this.prePageNum=t.num,this.prePageTime=t.time,t.num=this.startNum,t.time=null,this.isDownScrolling||!1===o||(null==o?(this.removeEmpty(),this.showUpScroll()):this.showDownScroll()),this.isUpAutoLoad=!0,this.num=t.num,this.size=t.size,this.time=t.time,this.optUp.callback&&this.optUp.callback(this)}},B.prototype.setPageNum=function(o){this.optUp.page.num=o-1},B.prototype.setPageSize=function(o){this.optUp.page.size=o},B.prototype.endByPage=function(o,t,e){let n;this.optUp.use&&null!=t&&(n=this.optUp.page.num<t),this.endSuccess(o,n,e)},B.prototype.endBySize=function(o,t,e){let n;if(this.optUp.use&&null!=t){n=(this.optUp.page.num-1)*this.optUp.page.size+o<t}this.endSuccess(o,n,e)},B.prototype.endSuccess=function(o,t,e){let n=this;if(n.isDownScrolling&&(n.isDownEndSuccess=!0,n.endDownScroll()),n.optUp.use){let s;if(null!=o){let i=n.optUp.page.num,l=n.optUp.page.size;if(1===i&&e&&(n.optUp.page.time=e),o<l||!1===t)if(n.optUp.hasNext=!1,0===o&&1===i)s=!1,n.showEmpty();else{s=!((i-1)*l+o<n.optUp.noMoreSize),n.removeEmpty()}else s=!1,n.optUp.hasNext=!0,n.removeEmpty()}n.endUpScroll(s)}},B.prototype.endErr=function(o){if(this.isDownScrolling){this.isDownEndSuccess=!1;let o=this.optUp.page;o&&this.prePageNum&&(o.num=this.prePageNum,o.time=this.prePageTime),this.endDownScroll()}this.isUpScrolling&&(this.optUp.page.num--,this.endUpScroll(!1),this.isScrollBody&&0!==o&&(o||(o=this.optUp.errDistance),this.scrollTo(this.getScrollTop()-o,0)))},B.prototype.showEmpty=function(){this.optUp.empty.use&&this.optUp.empty.onShow&&this.optUp.empty.onShow(!0)},B.prototype.removeEmpty=function(){this.optUp.empty.use&&this.optUp.empty.onShow&&this.optUp.empty.onShow(!1)},B.prototype.showTopBtn=function(){this.topBtnShow||(this.topBtnShow=!0,this.optUp.toTop.onShow&&this.optUp.toTop.onShow(!0))},B.prototype.hideTopBtn=function(){this.topBtnShow&&(this.topBtnShow=!1,this.optUp.toTop.onShow&&this.optUp.toTop.onShow(!1))},B.prototype.getScrollTop=function(){return this.scrollTop||0},B.prototype.setScrollTop=function(o){this.scrollTop=o},B.prototype.scrollTo=function(o,t){this.myScrollTo&&this.myScrollTo(o,t)},B.prototype.resetScrollTo=function(o){this.myScrollTo=o},B.prototype.getScrollBottom=function(){return this.getScrollHeight()-this.getClientHeight()-this.getScrollTop()},B.prototype.getStep=function(o,t,e,n,s){let i=t-o;if(0===n||0===i)return void(e&&e(t));let l=(n=n||300)/(s=s||30),r=i/l,p=0,c=setInterval((function(){p<l-1?(o+=r,e&&e(o,c),p++):(e&&e(t,c),clearInterval(c))}),s)},B.prototype.getClientHeight=function(o){let t=this.clientHeight||0;return 0===t&&!0!==o&&(t=this.getBodyHeight()),t},B.prototype.setClientHeight=function(o){this.clientHeight=o},B.prototype.getScrollHeight=function(){return this.scrollHeight||0},B.prototype.setScrollHeight=function(o){this.scrollHeight=o},B.prototype.getBodyHeight=function(){return this.bodyHeight||0},B.prototype.setBodyHeight=function(o){this.bodyHeight=o},B.prototype.preventDefault=function(o){o&&o.cancelable&&!o.defaultPrevented&&o.preventDefault()};const L={down:{offset:80,native:!1},up:{offset:150,toTop:{src:"https://www.mescroll.com/img/mescroll-totop.png",offset:1e3,right:20,bottom:120,width:72},empty:{use:!0,icon:"https://www.mescroll.com/img/mescroll-empty.png"}},i18n:{zh:{down:{textInOffset:"下拉刷新",textOutOffset:"释放更新",textLoading:"加载中 ...",textSuccess:"加载成功",textErr:"加载失败"},up:{textLoading:"加载中 ...",textNoMore:"-- END --",empty:{tip:"~ 空空如也 ~"}}},en:{down:{textInOffset:"drop down refresh",textOutOffset:"release updates",textLoading:"loading ...",textSuccess:"loaded successfully",textErr:"loading failed"},up:{textLoading:"loading ...",textNoMore:"-- END --",empty:{tip:"~ absolutely empty ~"}}}}},H={def:"zh",getType(){return e("mescroll-i18n")||this.def},setType(o){n("mescroll-i18n",o)}};const P=v({props:{option:Object,value:!1},computed:{mOption(){return this.option||{}},left(){return this.mOption.left?this.addUnit(this.mOption.left):"auto"},right(){return this.mOption.left?"auto":this.addUnit(this.mOption.right)}},methods:{addUnit:o=>o?"number"==typeof o?o+"rpx":o:0,toTopClick(){this.$emit("input",!1),this.$emit("click")}}},[["render",function(o,t,e,n,a,h){const d=c;return h.mOption.src?(s(),i(d,{key:0,class:l(["mescroll-totop",[e.value?"mescroll-totop-in":"mescroll-totop-out",{"mescroll-totop-safearea":h.mOption.safearea}]]),style:r({"z-index":h.mOption.zIndex,left:h.left,right:h.right,bottom:h.addUnit(h.mOption.bottom),width:h.addUnit(h.mOption.width),"border-radius":h.addUnit(h.mOption.radius)}),src:h.mOption.src,mode:"widthFix",onClick:h.toTopClick},null,8,["class","style","src","onClick"])):p("",!0)}],["__scopeId","data-v-c0bf933e"]]),O={data:()=>({wxsProp:{optDown:{},scrollTop:0,bodyHeight:0,isDownScrolling:!1,isUpScrolling:!1,isScrollBody:!0,isUpBoth:!0,t:0},callProp:{callType:"",t:0}}),methods:{wxsCall(o){"setWxsProp"===o.type?this.wxsProp={optDown:this.mescroll.optDown,scrollTop:this.mescroll.getScrollTop(),bodyHeight:this.mescroll.getBodyHeight(),isDownScrolling:this.mescroll.isDownScrolling,isUpScrolling:this.mescroll.isUpScrolling,isUpBoth:this.mescroll.optUp.isBoth,isScrollBody:this.mescroll.isScrollBody,t:Date.now()}:"setLoadType"===o.type?(this.downLoadType=o.downLoadType,this.$set(this.mescroll,"downLoadType",this.downLoadType),this.$set(this.mescroll,"isDownEndSuccess",null)):"triggerDownScroll"===o.type?this.mescroll.triggerDownScroll():"endDownScroll"===o.type?this.mescroll.endDownScroll():"triggerUpScroll"===o.type&&this.mescroll.triggerUpScroll(!0)}},mounted(){this.mescroll.optDown.afterLoading=()=>{this.callProp={callType:"showLoading",t:Date.now()}},this.mescroll.optDown.afterEndDownScroll=()=>{this.callProp={callType:"endDownScroll",t:Date.now()};let o=300+(this.mescroll.optDown.beforeEndDelay||0);setTimeout((()=>{4!==this.downLoadType&&0!==this.downLoadType||(this.callProp={callType:"clearTransform",t:Date.now()}),this.$set(this.mescroll,"downLoadType",this.downLoadType)}),o)},this.wxsCall({type:"setWxsProp"})}};var k={};function C(o,t){if(k.isMoveDown)k.downHight>=k.optDown.offset?(k.downHight=k.optDown.offset,k.callMethod(t,{type:"triggerDownScroll"})):(k.downHight=0,k.callMethod(t,{type:"endDownScroll"})),k.movetype=0,k.isMoveDown=!1;else if(!k.isScrollBody&&k.getScrollTop()===k.startTop){if(k.getPoint(o).y-k.startPoint.y<0)k.getAngle(k.getPoint(o),k.startPoint)>80&&k.callMethod(t,{type:"triggerUpScroll"})}k.callMethod(t,{type:"setWxsProp"})}k.onMoving=function(o,t,e){o.requestAnimationFrame((function(){o.selectComponent(".mescroll-wxs-content").setStyle({"will-change":"transform",transform:"translateY("+e+"px)",transition:""});var n=o.selectComponent(".mescroll-wxs-progress");n&&n.setStyle({transform:"rotate("+360*t+"deg)"})}))},k.showLoading=function(o){k.downHight=k.optDown.offset,o.requestAnimationFrame((function(){o.selectComponent(".mescroll-wxs-content").setStyle({"will-change":"auto",transform:"translateY("+k.downHight+"px)",transition:"transform 300ms"})}))},k.endDownScroll=function(o){k.downHight=0,k.isDownScrolling=!1,o.requestAnimationFrame((function(){o.selectComponent(".mescroll-wxs-content").setStyle({"will-change":"auto",transform:"translateY(0)",transition:"transform 300ms"})}))},k.clearTransform=function(o){o.requestAnimationFrame((function(){o.selectComponent(".mescroll-wxs-content").setStyle({"will-change":"",transform:"",transition:""})}))},k.disabled=function(){return!k.optDown||!k.optDown.use||k.optDown.native},k.getPoint=function(o){return o?o.touches&&o.touches[0]?{x:o.touches[0].pageX,y:o.touches[0].pageY}:o.changedTouches&&o.changedTouches[0]?{x:o.changedTouches[0].pageX,y:o.changedTouches[0].pageY}:{x:o.clientX,y:o.clientY}:{x:0,y:0}},k.getAngle=function(o,t){var e=Math.abs(o.x-t.x),n=Math.abs(o.y-t.y),s=Math.sqrt(e*e+n*n),i=0;return 0!==s&&(i=Math.asin(n/s)/Math.PI*180),i},k.getScrollTop=function(){return k.scrollTop||0},k.getBodyHeight=function(){return k.bodyHeight||0},k.callMethod=function(o,t){o&&o.callMethod("wxsCall",t)};const E={propObserver:function(o){k.optDown=o.optDown,k.scrollTop=o.scrollTop,k.bodyHeight=o.bodyHeight,k.isDownScrolling=o.isDownScrolling,k.isUpScrolling=o.isUpScrolling,k.isUpBoth=o.isUpBoth,k.isScrollBody=o.isScrollBody,k.startTop=o.scrollTop},callObserver:function(o,t,e){k.disabled()||o.callType&&("showLoading"===o.callType?k.showLoading(e):"endDownScroll"===o.callType?k.endDownScroll(e):"clearTransform"===o.callType&&k.clearTransform(e))},touchstartEvent:function(o,t){k.downHight=0,k.startPoint=k.getPoint(o),k.startTop=k.getScrollTop(),k.startAngle=0,k.lastPoint=k.startPoint,k.maxTouchmoveY=k.getBodyHeight()-k.optDown.bottomOffset,k.inTouchend=!1,k.callMethod(t,{type:"setWxsProp"})},touchmoveEvent:function(o,t){var e=!0;if(k.disabled())return e;var n=k.getScrollTop(),s=k.getPoint(o);if(s.y-k.startPoint.y>0&&(k.isScrollBody&&n<=0||!k.isScrollBody&&(n<=0||n<=k.optDown.startTop&&n===k.startTop))&&!k.inTouchend&&!k.isDownScrolling&&!k.optDown.isLock&&(!k.isUpScrolling||k.isUpScrolling&&k.isUpBoth)){if(k.startAngle||(k.startAngle=k.getAngle(k.lastPoint,s)),k.startAngle<k.optDown.minAngle)return e;if(k.maxTouchmoveY>0&&s.y>=k.maxTouchmoveY)return k.inTouchend=!0,C(o,t),e;e=!1;var i=s.y-k.lastPoint.y;k.downHight<k.optDown.offset?(1!==k.movetype&&(k.movetype=1,k.callMethod(t,{type:"setLoadType",downLoadType:1}),k.isMoveDown=!0),k.downHight+=i*k.optDown.inOffsetRate):(2!==k.movetype&&(k.movetype=2,k.callMethod(t,{type:"setLoadType",downLoadType:2}),k.isMoveDown=!0),k.downHight+=i>0?i*k.optDown.outOffsetRate:i),k.downHight=Math.round(k.downHight);var l=k.downHight/k.optDown.offset;k.onMoving(t,l,k.downHight)}return k.lastPoint=s,e},touchendEvent:C},M=o=>{o.$wxs||(o.$wxs=[]),o.$wxs.push("wxsBiz"),o.mixins||(o.mixins=[]),o.mixins.push({beforeCreate(){this.wxsBiz=E}})};var N={};function z(o){N.optDown=o.optDown,N.scrollTop=o.scrollTop,N.isDownScrolling=o.isDownScrolling,N.isUpScrolling=o.isUpScrolling,N.isUpBoth=o.isUpBoth}window&&!window.$mescrollRenderInit&&(window.$mescrollRenderInit=!0,window.addEventListener("touchstart",(function(o){N.disabled()||(N.startPoint=N.getPoint(o))}),{passive:!0}),window.addEventListener("touchmove",(function(o){if(!N.disabled()&&(!(N.getScrollTop()>0)&&N.getPoint(o).y-N.startPoint.y>0&&!N.isDownScrolling&&!N.optDown.isLock&&(!N.isUpScrolling||N.isUpScrolling&&N.isUpBoth))){for(var t=o.target,e=!1;t&&t.tagName&&"UNI-PAGE-BODY"!==t.tagName&&"BODY"!=t.tagName;){var n=t.classList;if(n&&n.contains("mescroll-render-touch")){e=!0;break}t=t.parentNode}e&&o.cancelable&&!o.defaultPrevented&&o.preventDefault()}}),{passive:!1})),N.getScrollTop=function(){return N.scrollTop||0},N.disabled=function(){return!N.optDown||!N.optDown.use||N.optDown.native},N.getPoint=function(o){return o?o.touches&&o.touches[0]?{x:o.touches[0].pageX,y:o.touches[0].pageY}:o.changedTouches&&o.changedTouches[0]?{x:o.changedTouches[0].pageX,y:o.changedTouches[0].pageY}:{x:o.clientX,y:o.clientY}:{x:0,y:0}};const A={mixins:[{data:()=>({propObserver:z})}]},Y=o=>{o.$renderjs||(o.$renderjs=[]),o.$renderjs.push("renderBiz"),o.mixins||(o.mixins=[]),o.mixins.push({beforeCreate(){this.renderBiz=this},mounted(){this.$ownerInstance=this.$gcd(this,!0)}}),o.mixins.push(A)},R={name:"mescroll-body",mixins:[O],components:{MescrollTop:P},props:{down:Object,up:Object,i18n:Object,top:[String,Number],topbar:[Boolean,String],bottom:[String,Number],safearea:Boolean,height:[String,Number],bottombar:{type:Boolean,default:!0},sticky:Boolean},data:()=>({mescroll:{optDown:{},optUp:{}},downHight:0,downRate:0,downLoadType:0,upLoadType:0,isShowEmpty:!1,isShowToTop:!1,windowHeight:0,windowBottom:0,statusBarHeight:0}),computed:{minHeight(){return this.toPx(this.height||"100%")+"px"},numTop(){return this.toPx(this.top)},padTop(){return this.numTop+"px"},numBottom(){return this.toPx(this.bottom)},padBottom(){return this.numBottom+"px"},isDownReset(){return 3===this.downLoadType||4===this.downLoadType},transition(){return this.isDownReset?"transform 300ms":""},translateY(){return this.downHight>0?"translateY("+this.downHight+"px)":""},isDownLoading(){return 3===this.downLoadType},downRotate(){return"rotate("+360*this.downRate+"deg)"},downText(){if(!this.mescroll)return"";switch(this.downLoadType){case 1:default:return this.mescroll.optDown.textInOffset;case 2:return this.mescroll.optDown.textOutOffset;case 3:return this.mescroll.optDown.textLoading;case 4:return this.mescroll.isDownEndSuccess?this.mescroll.optDown.textSuccess:0==this.mescroll.isDownEndSuccess?this.mescroll.optDown.textErr:this.mescroll.optDown.textInOffset}}},methods:{toPx(o){if("string"==typeof o)if(-1!==o.indexOf("px"))if(-1!==o.indexOf("rpx"))o=o.replace("rpx","");else{if(-1===o.indexOf("upx"))return Number(o.replace("px",""));o=o.replace("upx","")}else if(-1!==o.indexOf("%")){let t=Number(o.replace("%",""))/100;return this.windowHeight*t}return o?a(Number(o)):0},emptyClick(){this.$emit("emptyclick",this.mescroll)},toTopClick(){this.mescroll.scrollTo(0,this.mescroll.optUp.toTop.duration),this.$emit("topclick",this.mescroll)}},created(){let o=this,t={down:{inOffset(){o.downLoadType=1},outOffset(){o.downLoadType=2},onMoving(t,e,n){o.downHight=n,o.downRate=e},showLoading(t,e){o.downLoadType=3,o.downHight=e},beforeEndDownScroll:t=>(o.downLoadType=4,t.optDown.beforeEndDelay),endDownScroll(){o.downLoadType=4,o.downHight=0,o.downResetTimer&&(clearTimeout(o.downResetTimer),o.downResetTimer=null),o.downResetTimer=setTimeout((()=>{4===o.downLoadType&&(o.downLoadType=0)}),300)},callback:function(t){o.$emit("down",t)}},up:{showLoading(){o.upLoadType=1},showNoMore(){o.upLoadType=2},hideUpScroll(t){o.upLoadType=t.optUp.hasNext?0:3},empty:{onShow(t){o.isShowEmpty=t}},toTop:{onShow(t){o.isShowToTop=t}},callback:function(t){o.$emit("up",t)}}},e=H.getType(),n={type:e};B.extend(n,o.i18n),B.extend(n,L.i18n),B.extend(t,n[e]),B.extend(t,{down:L.down,up:L.up});let s=JSON.parse(JSON.stringify({down:o.down,up:o.up}));B.extend(s,t),o.mescroll=new B(s,!0),o.mescroll.i18n=n,o.$emit("init",o.mescroll);const i=h();i.windowHeight&&(o.windowHeight=i.windowHeight),i.windowBottom&&(o.windowBottom=i.windowBottom),i.statusBarHeight&&(o.statusBarHeight=i.statusBarHeight),o.mescroll.setBodyHeight(i.windowHeight),o.mescroll.resetScrollTo(((t,e)=>{"string"==typeof t?setTimeout((()=>{let n;-1==t.indexOf("#")&&-1==t.indexOf(".")?n="#"+t:(n=t,-1!=t.indexOf(">>>")&&(n=t.split(">>>")[1].trim())),d().select(n).boundingClientRect((function(t){if(t){let n=t.top;n+=o.mescroll.getScrollTop(),u({scrollTop:n,duration:e})}else console.error(n+" does not exist")})).exec()}),30):u({scrollTop:t,duration:e})})),o.up&&o.up.toTop&&null!=o.up.toTop.safearea||(o.mescroll.optUp.toTop.safearea=o.safearea),w("setMescrollGlobalOption",(t=>{if(!t)return;let e=t.i18n?t.i18n.type:null;if(e&&o.mescroll.i18n.type!=e&&(o.mescroll.i18n.type=e,H.setType(e),B.extend(t,o.mescroll.i18n[e])),t.down){let e=B.extend({},t.down);o.mescroll.optDown=B.extend(e,o.mescroll.optDown)}if(t.up){let e=B.extend({},t.up);o.mescroll.optUp=B.extend(e,o.mescroll.optUp)}}))},destroyed(){g("setMescrollGlobalOption")}};M(R),Y(R);const $=v(R,[["render",function(o,t,e,n,c,a){const h=b,d=f("mescroll-empty"),u=f("mescroll-top");return s(),i(h,{class:l(["mescroll-body mescroll-render-touch",{"mescorll-sticky":e.sticky}]),style:r({minHeight:a.minHeight,"padding-top":a.padTop,"padding-bottom":a.padBottom}),onTouchstart:o.wxsBiz.touchstartEvent,onTouchmove:o.wxsBiz.touchmoveEvent,onTouchend:o.wxsBiz.touchendEvent,onTouchcancel:o.wxsBiz.touchendEvent,"change:prop":o.wxsBiz.propObserver,prop:o.wxsProp},{default:m((()=>[e.topbar&&c.statusBarHeight?(s(),i(h,{key:0,class:"mescroll-topbar",style:r({height:c.statusBarHeight+"px",background:e.topbar})},null,8,["style"])):p("",!0),y(h,{class:"mescroll-body-content mescroll-wxs-content",style:r({transform:a.translateY,transition:a.transition}),"change:prop":o.wxsBiz.callObserver,prop:o.callProp},{default:m((()=>[c.mescroll.optDown.use?(s(),i(h,{key:0,class:"mescroll-downwarp",style:r({background:c.mescroll.optDown.bgColor,color:c.mescroll.optDown.textColor})},{default:m((()=>[y(h,{class:"downwarp-content"},{default:m((()=>[y(h,{class:l(["downwarp-progress mescroll-wxs-progress",{"mescroll-rotate":a.isDownLoading}]),style:r({"border-color":c.mescroll.optDown.textColor,transform:a.downRotate})},null,8,["class","style"]),y(h,{class:"downwarp-tip"},{default:m((()=>[S(D(a.downText),1)])),_:1})])),_:1})])),_:1},8,["style"])):p("",!0),T(o.$slots,"default",{},void 0,!0),c.isShowEmpty?(s(),i(d,{key:1,option:c.mescroll.optUp.empty,onEmptyclick:a.emptyClick},null,8,["option","onEmptyclick"])):p("",!0),c.mescroll.optUp.use&&!a.isDownLoading&&3!==c.upLoadType?(s(),i(h,{key:2,class:"mescroll-upwarp",style:r({background:c.mescroll.optUp.bgColor,color:c.mescroll.optUp.textColor})},{default:m((()=>[x(y(h,null,{default:m((()=>[y(h,{class:"upwarp-progress mescroll-rotate",style:r({"border-color":c.mescroll.optUp.textColor})},null,8,["style"]),y(h,{class:"upwarp-tip"},{default:m((()=>[S(D(c.mescroll.optUp.textLoading),1)])),_:1})])),_:1},512),[[U,1===c.upLoadType]]),2===c.upLoadType?(s(),i(h,{key:0,class:"upwarp-nodata"},{default:m((()=>[S(D(c.mescroll.optUp.textNoMore),1)])),_:1})):p("",!0)])),_:1},8,["style"])):p("",!0)])),_:3},8,["style","change:prop","prop"]),e.bottombar&&c.windowBottom>0?(s(),i(h,{key:1,class:"mescroll-bottombar",style:r({height:c.windowBottom+"px"})},null,8,["style"])):p("",!0),e.safearea?(s(),i(h,{key:2,class:"mescroll-safearea"})):p("",!0),y(u,{modelValue:c.isShowToTop,"onUpdate:modelValue":t[0]||(t[0]=o=>c.isShowToTop=o),option:c.mescroll.optUp.toTop,onClick:a.toTopClick},null,8,["modelValue","option","onClick"]),y(h,{"change:prop":o.renderBiz.propObserver,prop:o.wxsProp},null,8,["change:prop","prop"])])),_:3},8,["class","style","onTouchstart","onTouchmove","onTouchend","onTouchcancel","change:prop","prop"])}],["__scopeId","data-v-0e2ab920"]]);function I(o,t,e){let n=null;return e&&e((()=>{n&&n.onPullDownRefresh()})),o&&o((o=>{n&&n.onPageScroll(o)})),t&&t((()=>{n&&n.onReachBottom()})),{getMescroll:()=>n,mescrollInit:o=>{n=o},downCallback:()=>{n.optUp.use?n.resetUpScroll():setTimeout((()=>{n.endSuccess()}),500)},upCallback:()=>{setTimeout((()=>{n.endErr()}),500)}}}export{L as G,$ as M,H as m,I as u};