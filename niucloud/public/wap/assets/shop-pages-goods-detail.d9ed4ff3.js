import{A as e,B as t,C as r,i as a,j as l,w as s,D as i,p as o,k as n,m as d,P as u,R as c,Q as p,x,b4 as f,q as _,t as m,G as g,H as v,F as y,W as b,b8 as h,I as w,aL as k,aM as C,d as j,r as S,J as I,c as M,n as $,aI as F,E as N,N as A,bj as B,_ as T,e as L,a7 as U,y as O,o as E,b as D,aP as z,b5 as P}from"./index-c8487b3e.js";import{_ as R}from"./u-loading-icon.92d47c06.js";import{_ as V}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as W}from"./u-avatar.e8082faa.js";import{_ as H}from"./u-icon.e1505caa.js";import{_ as J}from"./u--image.bde6b800.js";import{_ as q}from"./u-parse.b8567232.js";import{_ as G}from"./u-popup.41f7f1c4.js";import{_ as Q}from"./u-loading-page.dfb8f67c.js";import{c as X,d as Y,e as K,f as Z}from"./goods.fc64e3f3.js";import{g as ee,a as te}from"./coupon.b3c2fd21.js";import{u as re,_ as ae}from"./cart.b004a953.js";import{_ as le}from"./u-button.a3ccbdc0.js";import"./u--text.e0f8727c.js";import"./u-image.9b57fa1f.js";import"./u-transition.75acc538.js";import"./u-safe-bottom.f4b61f4d.js";const se=V({name:"u-swiper-indicator",mixins:[t,r,{props:{length:{type:[String,Number],default:e.swiperIndicator.length},current:{type:[String,Number],default:e.swiperIndicator.current},indicatorActiveColor:{type:String,default:e.swiperIndicator.indicatorActiveColor},indicatorInactiveColor:{type:String,default:e.swiperIndicator.indicatorInactiveColor},indicatorMode:{type:String,default:e.swiperIndicator.indicatorMode}}}],data:()=>({lineWidth:22}),computed:{lineStyle(){let e={};return e.width=uni.$u.addUnit(this.lineWidth),e.transform=`translateX(${uni.$u.addUnit(this.current*this.lineWidth)})`,e.backgroundColor=this.indicatorActiveColor,e},dotStyle(){return e=>{let t={};return t.backgroundColor=e===this.current?this.indicatorActiveColor:this.indicatorInactiveColor,t}}}},[["render",function(e,t,r,f,_,m){const g=x;return a(),l(g,{class:"u-swiper-indicator"},{default:s((()=>["line"===e.indicatorMode?(a(),l(g,{key:0,class:i(["u-swiper-indicator__wrapper",[`u-swiper-indicator__wrapper--${e.indicatorMode}`]]),style:o({width:e.$u.addUnit(_.lineWidth*e.length),backgroundColor:e.indicatorInactiveColor})},{default:s((()=>[n(g,{class:"u-swiper-indicator__wrapper--line__bar",style:o([m.lineStyle])},null,8,["style"])])),_:1},8,["class","style"])):d("v-if",!0),"dot"===e.indicatorMode?(a(),l(g,{key:1,class:"u-swiper-indicator__wrapper"},{default:s((()=>[(a(!0),u(c,null,p(e.length,((t,r)=>(a(),l(g,{class:i(["u-swiper-indicator__wrapper__dot",[r===e.current&&"u-swiper-indicator__wrapper__dot--active"]]),key:r,style:o([m.dotStyle(r)])},null,8,["class","style"])))),128))])),_:1})):d("v-if",!0)])),_:1})}],["__scopeId","data-v-176855f5"]]);const ie=V({name:"u-swiper",mixins:[t,r,{props:{list:{type:Array,default:e.swiper.list},indicator:{type:Boolean,default:e.swiper.indicator},indicatorActiveColor:{type:String,default:e.swiper.indicatorActiveColor},indicatorInactiveColor:{type:String,default:e.swiper.indicatorInactiveColor},indicatorStyle:{type:[String,Object],default:e.swiper.indicatorStyle},indicatorMode:{type:String,default:e.swiper.indicatorMode},autoplay:{type:Boolean,default:e.swiper.autoplay},current:{type:[String,Number],default:e.swiper.current},currentItemId:{type:String,default:e.swiper.currentItemId},interval:{type:[String,Number],default:e.swiper.interval},duration:{type:[String,Number],default:e.swiper.duration},circular:{type:Boolean,default:e.swiper.circular},previousMargin:{type:[String,Number],default:e.swiper.previousMargin},nextMargin:{type:[String,Number],default:e.swiper.nextMargin},acceleration:{type:Boolean,default:e.swiper.acceleration},displayMultipleItems:{type:Number,default:e.swiper.displayMultipleItems},easingFunction:{type:String,default:e.swiper.easingFunction},keyName:{type:String,default:e.swiper.keyName},imgMode:{type:String,default:e.swiper.imgMode},height:{type:[String,Number],default:e.swiper.height},bgColor:{type:String,default:e.swiper.bgColor},radius:{type:[String,Number],default:e.swiper.radius},loading:{type:Boolean,default:e.swiper.loading},showTitle:{type:Boolean,default:e.swiper.showTitle}}}],data:()=>({currentIndex:0}),watch:{current(e,t){e!==t&&(this.currentIndex=e)}},computed:{itemStyle(){return e=>{const t={};return this.nextMargin&&this.previousMargin&&(t.borderRadius=uni.$u.addUnit(this.radius),e!==this.currentIndex&&(t.transform="scale(0.92)")),t}}},methods:{getItemType(e){return"string"==typeof e?uni.$u.test.video(this.getSource(e))?"video":"image":"object"==typeof e&&this.keyName?e.type?"image"===e.type?"image":"video"===e.type?"video":"image":uni.$u.test.video(this.getSource(e))?"video":"image":void 0},getSource(e){return"string"==typeof e?e:"object"==typeof e&&this.keyName?e[this.keyName]:(uni.$u.error("请按格式传递列表参数"),"")},change(e){const{current:t}=e.detail;this.pauseVideo(this.currentIndex),this.currentIndex=t,this.$emit("change",e.detail)},pauseVideo(e){const t=this.getSource(this.list[e]);if(uni.$u.test.video(t)){f(`video-${e}`,this).pause()}},getPoster:e=>"object"==typeof e&&e.poster?e.poster:"",clickHandler(e){this.$emit("click",e)}}},[["render",function(e,t,r,i,f,j){const S=_(m("u-loading-icon"),R),I=x,M=b,$=h,F=w,N=k,A=C,B=_(m("u-swiper-indicator"),se);return a(),l(I,{class:"u-swiper",style:o({backgroundColor:e.bgColor,height:e.$u.addUnit(e.height),borderRadius:e.$u.addUnit(e.radius)})},{default:s((()=>[e.loading?(a(),l(I,{key:0,class:"u-swiper__loading"},{default:s((()=>[n(S,{mode:"circle"})])),_:1})):(a(),l(A,{key:1,class:"u-swiper__wrapper",style:o({height:e.$u.addUnit(e.height)}),onChange:j.change,circular:e.circular,interval:e.interval,duration:e.duration,autoplay:e.autoplay,current:e.current,currentItemId:e.currentItemId,previousMargin:e.$u.addUnit(e.previousMargin),nextMargin:e.$u.addUnit(e.nextMargin),acceleration:e.acceleration,displayMultipleItems:e.displayMultipleItems,easingFunction:e.easingFunction},{default:s((()=>[(a(!0),u(c,null,p(e.list,((t,r)=>(a(),l(N,{class:"u-swiper__wrapper__item",key:r},{default:s((()=>[n(I,{class:"u-swiper__wrapper__item__wrapper",style:o([j.itemStyle(r)])},{default:s((()=>[d(" 在nvue中，image图片的宽度默认为屏幕宽度，需要通过flex:1撑开，另外必须设置高度才能显示图片 "),"image"===j.getItemType(t)?(a(),l(M,{key:0,class:"u-swiper__wrapper__item__wrapper__image",src:j.getSource(t),mode:e.imgMode,onClick:e=>j.clickHandler(r),style:o({height:e.$u.addUnit(e.height),borderRadius:e.$u.addUnit(e.radius)})},null,8,["src","mode","onClick","style"])):d("v-if",!0),"video"===j.getItemType(t)?(a(),l($,{key:1,class:"u-swiper__wrapper__item__wrapper__video",id:`video-${r}`,"enable-progress-gesture":!1,src:j.getSource(t),poster:j.getPoster(t),title:e.showTitle&&e.$u.test.object(t)&&t.title?t.title:"",style:o({height:e.$u.addUnit(e.height)}),controls:"",onClick:e=>j.clickHandler(r)},null,8,["id","src","poster","title","style","onClick"])):d("v-if",!0),e.showTitle&&e.$u.test.object(t)&&t.title&&e.$u.test.image(j.getSource(t))?(a(),l(F,{key:2,class:"u-swiper__wrapper__item__wrapper__title u-line-1"},{default:s((()=>[g(v(t.title),1)])),_:2},1024)):d("v-if",!0)])),_:2},1032,["style"])])),_:2},1024)))),128))])),_:1},8,["style","onChange","circular","interval","duration","autoplay","current","currentItemId","previousMargin","nextMargin","acceleration","displayMultipleItems","easingFunction"])),n(I,{class:"u-swiper__indicator",style:o([e.$u.addStyle(e.indicatorStyle)])},{default:s((()=>[y(e.$slots,"indicator",{},(()=>[e.loading||!e.indicator||e.showTitle?d("v-if",!0):(a(),l(B,{key:0,indicatorActiveColor:e.indicatorActiveColor,indicatorInactiveColor:e.indicatorInactiveColor,length:e.list.length,current:f.currentIndex,indicatorMode:e.indicatorMode},null,8,["indicatorActiveColor","indicatorInactiveColor","length","current","indicatorMode"]))]),!0)])),_:3},8,["style"])])),_:3},8,["style"])}],["__scopeId","data-v-bb70be48"]]),oe=V(j({__name:"ns-goods-sku",props:["goodsDetail"],emits:["change"],setup(e,{expose:t,emit:r}){const o=e;let f=S(!1),y=S(null),b=S({skuId:"",name:[]}),h=S(""),k=S(1);const C=I(),j=M((()=>C.info)),E=re();E.getList();const D=M((()=>E.cartList)),z=()=>{f.value=!1},P=M((()=>{let e=JSON.parse(JSON.stringify(o.goodsDetail));return Object.keys(e).length&&(Object.keys(b.value.name).length||(b.value.name=e.sku_spec_format.split(",")),e.goodsSpec.forEach(((e,t)=>{let r=e.spec_values.split(",");e.values=[],r.forEach(((r,a)=>{e.values[a]={},e.values[a].name=r,e.values[a].selected=!1,e.values[a].disabled=!1,b.value.name.forEach(((l,s)=>{s==t&&l==r&&(e.values[a].selected=!0)}))}))})),R(),e.skuList&&Object.keys(e.skuList).length&&e.skuList.forEach(((t,r)=>{t.sku_id==b.value.skuId&&(e.detail=t)}))),e})),R=()=>{o.goodsDetail.skuList.forEach(((e,t)=>{e.sku_spec_format==b.value.name.toString()&&(b.value.skuId=e.sku_id,r("change",e.sku_id))}))},V=()=>{if(!j.value)return A().setLoginBack({url:"/shop/pages/goods/detail",param:{sku_id:P.value.sku_id}}),!1;if("join_cart"==h.value){let e=0,t="";D.value["goods_"+P.value.goods_id]&&D.value["goods_"+P.value.goods_id]["sku_"+P.value.sku_id]&&(e=B(D.value["goods_"+P.value.goods_id]["sku_"+P.value.sku_id].num),t=B(D.value["goods_"+P.value.goods_id]["sku_"+P.value.sku_id].id)),e+=k.value,E.increase({id:t||"",goods_id:P.value.goods_id,sku_id:P.value.sku_id,stock:P.value.stock,sale_price:P.value.sale_price,num:e},0,(()=>{T({title:"加入购物车成功",icon:"none"})}))}else if("buy_now"==h.value){var e={sku_id:P.value.sku_id,num:k.value};uni.setStorage({key:"orderCreateData",data:{sku_data:[e]},success:()=>{L({url:"/shop/pages/order/payment"})}})}z()};return t({open:(e="",t="")=>{h.value=e,f.value=!0,y.value=t}}),(e,t)=>{const r=x,o=_(m("u-icon"),H),y=_(m("u--image"),J),h=w,C=_(m("u-number-box"),ae),j=U,S=_(m("u-button"),le),I=_(m("u-popup"),G);return a(),l(r,{onTouchmove:t[1]||(t[1]=N((()=>{}),["prevent","stop"]))},{default:s((()=>[n(I,{show:$(f),onClose:z,mode:"bottom"},{default:s((()=>[n(r,{class:"rounded-t-[20rpx] overflow-hidden bg-[#fff] p-[32rpx] relative"},{default:s((()=>[n(r,{class:"absolute right-[37rpx] iconfont iconguanbi text-[50rpx]",onClick:z}),n(r,{class:"flex mb-[58rpx]"},{default:s((()=>[n(y,{class:"rounded-[8rpx] overflow-hidden",width:"204rpx",height:"204rpx",src:$(O)($(P).detail.sku_image),model:"aspectFill"},{error:s((()=>[n(o,{name:"photo",color:"#999",size:"50"})])),_:1},8,["src"]),n(r,{class:"flex flex-1 flex-col justify-between ml-[20rpx]"},{default:s((()=>[n(r,{class:"w-[100%]"},{default:s((()=>[n(r,{class:"text-[var(--price-text-color)]"},{default:s((()=>[n(h,{class:"text-[28rpx] font-bold"},{default:s((()=>[g("￥")])),_:1}),n(h,{class:"text-[42rpx] mr-[10rpx] font-bold"},{default:s((()=>[g(v($(P).detail.sale_price),1)])),_:1})])),_:1}),n(r,{class:"text-[24rpx] leading-[32rpx] text-[#666] mt-[12rpx]"},{default:s((()=>[g("库存"+v($(P).detail.stock)+v($(P).goods.unit),1)])),_:1})])),_:1}),$(P).goodsSpec&&$(P).goodsSpec.length?(a(),l(r,{key:0,class:"w-[100%]",style:{"max-height":"calc(204rpx - 126rpx)",overflow:"hidden"}},{default:s((()=>[n(h,{class:"text-[24rpx] leading-[32rpx] text-[#666]"},{default:s((()=>[g("已选规格："+v($(P).detail.sku_spec_format),1)])),_:1})])),_:1})):d("v-if",!0)])),_:1})])),_:1}),n(j,{class:"h-[500rpx]","scroll-y":"true"},{default:s((()=>[(a(!0),u(c,null,p($(P).goodsSpec,((e,t)=>(a(),l(r,{class:"mb-[50rpx]",key:t},{default:s((()=>[n(r,{class:"text-[26rpx] leading-[36rpx] mb-[30rpx]"},{default:s((()=>[g(v(e.spec_name),1)])),_:2},1024),n(r,{class:"flex flex-wrap"},{default:s((()=>[(a(!0),u(c,null,p(e.values,((e,o)=>(a(),l(r,{class:i(["box-border min-w-[96rpx] text-[24rpx] px-[15rpx] text-center h-[52rpx] leading-[52rpx] mr-[20rpx] border-1 border-solid rounded-[8rpx] border-[#888]",{"!border-[var(--primary-color)] text-[var(--primary-color)] bg-[var(--primary-color-light)]":e.selected}]),key:o,onClick:r=>((e,t)=>{b.value.name[t]=e.name,R()})(e,t)},{default:s((()=>[g(v(e.name),1)])),_:2},1032,["class","onClick"])))),128))])),_:2},1024)])),_:2},1024)))),128)),n(r,{class:"flex justify-between"},{default:s((()=>[n(r,{class:"text-[26rpx] leading-[36rpx] mb-[30rpx]"},{default:s((()=>[g("购买数量")])),_:1}),n(C,{min:1,max:$(P).stock,integer:"",step:1,"input-width":"98rpx",modelValue:$(k),"onUpdate:modelValue":t[0]||(t[0]=e=>F(k)?k.value=e:k=e),"input-height":"54rpx"},{minus:s((()=>[n(h,{class:i(["text-[44rpx] iconfont iconjianhao text-[var(--primary-color)]",{"!text-[#c8c9cc]":1===$(k)}])},null,8,["class"])])),input:s((()=>[n(h,{class:"text-[#333] fext-[23rpx] font-500 mx-[16rpx]"},{default:s((()=>[g(v($(k)),1)])),_:1})])),plus:s((()=>[n(h,{class:i(["text-[44rpx] iconfont iconjiahao2fill text-[var(--primary-color)]",{"!text-[#c8c9cc]":$(k)===$(P).stock}])},null,8,["class"])])),_:1},8,["max","modelValue"])])),_:1})])),_:1}),n(S,{class:"!h-[80rpx] !text-[30rpx] !m-0 !mt-[30rpx]",type:"primary",shape:"circle",onClick:V},{default:s((()=>[g("确定")])),_:1})])),_:1})])),_:1},8,["show"])])),_:1})}}}),[["__scopeId","data-v-b7a14ce8"]]),ne=V(j({__name:"detail",setup(e){const t=I(),r=M((()=>t.info)),o=re();let f=M((()=>o.totalNum)),y=S(null),h=S({}),k=S(!1),C=S(!1),j=S(!1);E((e=>{X({goods_id:e.goods_id||"",sku_id:e.sku_id||""}).then((e=>{if("[]"===JSON.stringify(e.data))return T({title:"找不到该商品",icon:"none"}),setTimeout((()=>{L({url:"/shop/pages/index",mode:"reLaunch"})}),600),!1;h.value=e.data,R.value=h.value.goods.is_collect,h.value.delivery_type_list=h.value.goods.delivery_type_list?Object.values(h.value.goods.delivery_type_list).map((e=>e.name)):[],h.value.goods.goods_image=h.value.goods.goods_image_thumb_big,h.value.goods.goods_image.forEach(((e,t)=>{h.value.goods.goods_image[t]=O(e)})),le(),ne()}))})),D((()=>{o.getList()}));const N=e=>{h.value.skuList.forEach(((t,r)=>{t.sku_id==e&&Object.assign(h.value,t)}))},B=e=>{y.value.open(e)};let R=S(0);const V=()=>{if(!r.value)return A().setLoginBack({url:"/shop/pages/goods/detail",param:{sku_id:h.value.sku_id}}),!1;(R.value?K(h.value.goods_id):Z(h.value.goods_id)).then((e=>{R.value=!R.value,R.value?T({title:"收藏成功",icon:"none"}):T({title:"取消收藏",icon:"none"})}))};let ae=S([]);const le=()=>{ee({category_id:h.value.goods.goods_category||"",goods_id:h.value.goods_id||""}).then((e=>{ae.value=e.data.data.map((e=>(-1!=e.sum_count&&e.receive_count===e.sum_count&&(e.btnType="collected"),r.value&&e.is_receive&&e.limit_count===e.member_receive_count?e.btnType="using":e.btnType="collecting",e)))}))},se=S({}),ne=()=>{Y(h.value.goods_id).then((e=>{se.value=e.data}))};return(e,t)=>{const o=_(m("u-swiper"),ie),S=w,I=x,M=_(m("u-avatar"),W),T=_(m("u-icon"),H),E=_(m("u--image"),J),D=U,X=_(m("u-parse"),q),Y=z,K=b,Z=_(m("u-popup"),G),ee=_(m("u-loading-page"),Q);return a(),u(c,null,[Object.keys($(h)).length?(a(),l(I,{key:0,class:"bg-[#f6f6f6] min-h-[100vh] relative"},{default:s((()=>[n(o,{list:$(h).goods.goods_image,autoplay:!1,height:"600rpx"},null,8,["list"]),n(I,{class:"absolute top-[20rpx] right-[40rpx]"},{default:s((()=>[n(I,{class:"w-[60rpx] h-[60rpx] flex items-center justify-center bg-[rgba(0,0,0,.2)] rounded-full",onClick:V},{default:s((()=>[n(S,{class:i(["iconfont",$(R)?"text-[#ff0000]":"text-[#fff]",$(R)?"iconshoucang_shoucang":"icona-shoucang-weishoucang"])},null,8,["class"])])),_:1})])),_:1}),n(I,{class:"mt-[20rpx] bg-white mx-[24rpx] rounded-[16rpx] py-[24rpx] px-[24rpx]"},{default:s((()=>[n(I,{class:"flex items-center"},{default:s((()=>[n(S,{class:"text-[var(--price-text-color)]"},{default:s((()=>[n(S,{class:"text-[28rpx] font-bold price-font"},{default:s((()=>[g("￥")])),_:1}),n(S,{class:"text-[42rpx] mr-[10rpx] font-bold price-font"},{default:s((()=>[g(v($(h).sale_price),1)])),_:1}),n(S,{class:"text-[26rpx] text-[#999] line-through font-500"},{default:s((()=>[n(S,{class:"price-font"},{default:s((()=>[g("￥"+v($(h).market_price),1)])),_:1})])),_:1})])),_:1}),n(I,{class:"text-[24rpx] text-[#666] flex items-center ml-auto"},{default:s((()=>[n(S,null,{default:s((()=>[g("销量")])),_:1}),n(S,{class:"mx-[6rpx]"},{default:s((()=>[g(v($(h).goods.sale_num),1)])),_:1}),n(S,null,{default:s((()=>[g(v($(h).goods.unit),1)])),_:1})])),_:1})])),_:1}),n(I,{class:"mt-[10rpx] font-600 text-[32rpx] max-h-[108rpx] multi-hidden leading-[54rpx]"},{default:s((()=>[g(v($(h).goods.goods_name),1)])),_:1}),$(h).label_info&&$(h).label_info.length?(a(),l(I,{key:0,class:"flex flex-wrap"},{default:s((()=>[(a(!0),u(c,null,p($(h).label_info,(e=>(a(),l(I,{key:e.label_id,class:"mt-[10rpx] text-[#FA6400] leading-[40rpx] text-[22rpx] h-[40rpx] px-[10rpx] border-[2rpx] border-solid border-[#FA6400] rounded-[6rpx] mr-[15rpx] box-border"},{default:s((()=>[g(v(e.label_name),1)])),_:2},1024)))),128))])),_:1})):d("v-if",!0)])),_:1}),n(I,{class:"mt-[20rpx] bg-white mx-[24rpx] rounded-[16rpx]"},{default:s((()=>[$(h).service?(a(),l(I,{key:0,onClick:t[0]||(t[0]=e=>F(C)?C.value=!$(C):C=!$(C)),class:"flex items-center h-[88rpx] border-0 border-b-[2rpx] border-solid border-[#ebebec] px-[20rpx]"},{default:s((()=>[n(S,{class:"text-[#999] text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]"},{default:s((()=>[g("服务")])),_:1}),n(I,{class:"flex-1 text-[#343434] text-sm leading-[42rpx] font-500"},{default:s((()=>[g(v($(h).service[0].service_name),1)])),_:1}),n(S,{class:"iconfont iconxiangyoujiantou text-sm"})])),_:1})):d("v-if",!0),$(h).goodsSpec&&$(h).goodsSpec.length?(a(),l(I,{key:1,onClick:B,class:"flex items-center h-[88rpx] px-[20rpx] border-0 border-b-[2rpx] border-solid border-[#ebebec]"},{default:s((()=>[n(S,{class:"text-[#999] text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]"},{default:s((()=>[g("已选")])),_:1}),n(I,{class:"flex-1 text-[#343434] text-sm leading-[42rpx] font-500"},{default:s((()=>[g(v($(h).sku_spec_format),1)])),_:1}),n(S,{class:"iconfont iconxiangyoujiantou text-sm"})])),_:1})):d("v-if",!0),"real"==$(h).goods.goods_type&&$(h).delivery_type_list&&$(h).delivery_type_list.length?(a(),l(I,{key:2,class:"flex items-center h-[88rpx] border-0 border-b-[2rpx] border-solid border-[#ebebec] px-[20rpx]"},{default:s((()=>[n(S,{class:"text-[#999] text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]"},{default:s((()=>[g("配送")])),_:1}),n(I,{class:"flex-1 flex items-center text-[#343434] text-sm leading-[42rpx] font-500"},{default:s((()=>[(a(!0),u(c,null,p($(h).delivery_type_list,((e,t)=>(a(),u(c,null,[t?(a(),l(S,{key:0,class:"w-[7rpx] h-[7rpx] rounded-[7rpx] mx-[10rpx] bg-[#333]"})):d("v-if",!0),n(S,null,{default:s((()=>[g(v(e),1)])),_:2},1024)],64)))),256))])),_:1}),d(' <text class="iconfont iconxiangyoujiantou text-sm"></text> ')])),_:1})):d("v-if",!0),$(ae).length?(a(),l(I,{key:3,class:"flex items-center h-[88rpx] px-[20rpx]"},{default:s((()=>[n(S,{class:"text-[#999] text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]"},{default:s((()=>[g("领券")])),_:1}),n(I,{class:"flex-1 flex items-center whitespace-nowrap overflow-hidden h-[44rpx] flex-wrap content-between"},{default:s((()=>[(a(!0),u(c,null,p($(ae),((e,t)=>(a(),l(I,{key:t,class:"text-xs rounded-sm border-[2rpx] px-[6rpx] py-[2rpx] border-solid border-[var(--primary-color)] text-[var(--primary-color)] mr-[8rpx] mt-[3rpx]"},{default:s((()=>[g(v(e.title),1)])),_:2},1024)))),128))])),_:1}),n(I,{class:"ml-[8rpx] flex items-center",onClick:t[1]||(t[1]=e=>F(j)?j.value=!0:j=!0)},{default:s((()=>[n(S,{class:"text-xs text-[#737373]"},{default:s((()=>[g("领取")])),_:1}),n(S,{class:"iconfont iconxiangyoujiantou text-sm"})])),_:1})])),_:1})):d("v-if",!0)])),_:1}),n(I,{class:"mt-[20rpx] bg-white mx-[24rpx] rounded-[16rpx] px-[20rpx]"},{default:s((()=>[n(I,{class:"flex items-center justify-between h-[88rpx]"},{default:s((()=>[n(S,{class:"text-[30rpx]"},{default:s((()=>[g("宝贝评价("+v(se.value.count)+")",1)])),_:1}),se.value.count?(a(),l(I,{key:0,class:"flex items-center",onClick:t[2]||(t[2]=e=>($(h).goods_id,void L({url:"/shop/pages/evaluate/list",param:{goods_id:h.value.goods_id}})))},{default:s((()=>[d("  "),n(S,{class:"text-xs text-[#737373]"},{default:s((()=>[g("查看全部")])),_:1}),n(S,{class:"iconfont iconxiangyoujiantou text-xs"})])),_:1})):d("v-if",!0),se.value.count?d("v-if",!0):(a(),l(S,{key:1,class:"text-xs text-[#737373]"},{default:s((()=>[g("暂无评价")])),_:1}))])),_:1}),n(I,null,{default:s((()=>[(a(!0),u(c,null,p(se.value.list,((e,t)=>(a(),l(I,{key:t,class:"mx-[20rpx] pb-[20rpx] border-0 border-b-[2rpx] border-solid border-[#eee] mb-[20rpx]"},{default:s((()=>[n(I,{class:"flex items-center justify-between"},{default:s((()=>[n(I,{class:"flex items-center"},{default:s((()=>[n(M,{class:"mr-[10rpx]",src:$(O)(e.member_head),size:"50rpx",leftIcon:"none"},null,8,["src"]),n(S,{class:"text-sm"},{default:s((()=>[g(v(e.member_name),1)])),_:2},1024)])),_:2},1024),n(S,{class:"text-xs text-[#737373]"},{default:s((()=>[g(v(e.create_time?e.create_time.slice(0,10):""),1)])),_:2},1024)])),_:2},1024),n(I,{class:"text-sm text-[#666] mt-[10rpx] multi-hidden"},{default:s((()=>[g(v(e.content),1)])),_:2},1024),n(D,{"scroll-x":"true",class:"scroll-Y box-border py-[24rpx] bg-white"},{default:s((()=>[n(I,{class:"flex items-center"},{default:s((()=>[(a(!0),u(c,null,p(e.image_small,((e,t)=>(a(),l(E,{key:"item"+t,class:"rounded-[8rpx] overflow-hidden mr-[14rpx] mb-[14rpx]",width:"200rpx",height:"200rpx",src:$(O)(e),model:"aspectFill",onClick:t=>(e=>{if(""===e)return!1;var t=[];t.push(O(e)),P({indicator:"number",loop:!0,urls:t})})(e)},{error:s((()=>[n(T,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src","onClick"])))),128))])),_:2},1024)])),_:2},1024)])),_:2},1024)))),128))])),_:1})])),_:1}),n(I,{class:"px-[10px] pd-[10px] mt-[20rpx] bg-white mx-[24rpx] rounded-[16rpx]"},{default:s((()=>[n(I,{class:"py-[20rpx] text-base"},{default:s((()=>[g("商品详情")])),_:1}),n(X,{content:$(h).goods.goods_desc},null,8,["content"])])),_:1}),d(" tabber "),n(I,{class:"tab-bar-placeholder"}),n(I,{class:"w-[100%] flex justify-between py-[9rpx] px-[27rpx] bg-[#fff] box-border fixed left-0 bottom-0 tab-bar"},{default:s((()=>[n(I,{class:"flex items-center"},{default:s((()=>[n(I,{class:"flex flex-col justify-center items-center mr-[39rpx]",onClick:t[3]||(t[3]=e=>$(L)({url:"/shop/pages/index"}))},{default:s((()=>[n(I,{class:"iconfont iconshouye text-[42rpx] mb-[4rpx]"}),n(S,{class:"text-[18rpx] mt-1"},{default:s((()=>[g("首页")])),_:1})])),_:1}),n(I,{class:"flex flex-col justify-center items-center mr-[39rpx]"},{default:s((()=>[n(I,{class:"iconfont iconkefu1 text-[46rpx]"}),n(S,{class:"text-[18rpx] mt-1"},{default:s((()=>[g("客服")])),_:1})])),_:1}),n(I,{class:"flex flex-col justify-center items-center",onClick:t[4]||(t[4]=e=>$(L)({url:"/shop/pages/goods/cart"}))},{default:s((()=>[n(I,{class:"relative"},{default:s((()=>[n(S,{class:"iconfont icongouwuche text-[46rpx]"}),$(f)?(a(),l(I,{key:0,class:i(["absolute left-[26rpx] top-0 rounded-[25rpx] h-[25rpx] min-w-[25rpx] text-center leading-[25rpx] bg-[#FF4646] text-[#fff] text-[20rpx] font-500 box-border",$(f)>9?"px-[10rpx]":""])},{default:s((()=>[g(v($(f)),1)])),_:1},8,["class"])):d("v-if",!0)])),_:1}),n(S,{class:"text-[18rpx] mt-1"},{default:s((()=>[g("购物车")])),_:1})])),_:1})])),_:1}),n(I,{class:"flex"},{default:s((()=>[n(Y,{class:"!w-[200rpx] !h-[80rpx] text-sm !text-[#fff] !m-0 !mr-[20rpx] leading-[80rpx] rounded-full remove-border",style:{background:"linear-gradient(127deg, #FFB000 0%, #FFA029 100%)"},onClick:t[5]||(t[5]=e=>B("join_cart"))},{default:s((()=>[g(" 加入购物车")])),_:1}),n(Y,{class:"!w-[200rpx] !h-[80rpx] text-sm !text-[#fff] !bg-[#FF4646] !m-0 leading-[80rpx] rounded-full remove-border",onClick:t[6]||(t[6]=e=>B("buy_now"))},{default:s((()=>[g("立即购买")])),_:1})])),_:1})])),_:1}),d(" 服务 "),n(Z,{class:"popup-type",show:$(C),onClose:t[9]||(t[9]=e=>F(C)?C.value=!1:C=!1)},{default:s((()=>[n(I,{class:"min-h-[480rpx] rounded-t-[20rpx] overflow-hidden bg-[#fff]"},{default:s((()=>[n(I,{class:"flex items-center justify-center py-[34rpx] relative"},{default:s((()=>[n(S,{class:"text-[32rpx] leading-[36rpx] font-600"},{default:s((()=>[g("商品服务")])),_:1}),n(I,{class:"absolute right-[37rpx] iconfont iconguanbi text-[50rpx]",onClick:t[7]||(t[7]=e=>F(C)?C.value=!1:C=!1)})])),_:1}),n(D,{class:"h-[520rpx]","scroll-y":"true"},{default:s((()=>[n(I,{class:"pl-[22rpx] pt-[28rpx] pr-[37rpx]"},{default:s((()=>[(a(!0),u(c,null,p($(h).service,((e,t)=>(a(),l(I,{class:"flex mb-[28rpx]"},{default:s((()=>[n(K,{class:"max-w-[34rpx] max-h-[34rpx] mr-[14rpx]",src:$(O)(e.image||"addon/shop/icon_service.png"),mode:"aspectFit"},null,8,["src"]),n(I,{class:"flex-1"},{default:s((()=>[n(I,{class:"text-[26rpx] leading-[36rpx] text-[#222] mb-[4rpx] w-[643rpx]"},{default:s((()=>[g(v(e.service_name),1)])),_:2},1024),n(I,{class:"text-[22rpx] leading-[36rpx] text-[#888] w-[643rpx]"},{default:s((()=>[g(v(e.desc),1)])),_:2},1024)])),_:2},1024)])),_:2},1024)))),256))])),_:1})])),_:1}),n(I,{class:"px-[32rpx] pb-[67rpx] pt-[42rpx]"},{default:s((()=>[n(Y,{class:"!w-[100%] !h-[80rpx] text-[30rpx] !bg-[#FF4646] !m-0 leading-[80rpx] rounded-full text-white",onClick:t[8]||(t[8]=e=>F(C)?C.value=!1:C=!1)},{default:s((()=>[g("确定")])),_:1})])),_:1})])),_:1})])),_:1},8,["show"]),d(" 优惠券 "),n(Z,{class:"popup-type",show:$(j),onClose:t[12]||(t[12]=e=>F(j)?j.value=!1:j=!1)},{default:s((()=>[n(I,{class:"min-h-[480rpx] rounded-t-[20rpx] overflow-hidden bg-[#fff]"},{default:s((()=>[n(I,{class:"flex items-center justify-center py-[34rpx] relative"},{default:s((()=>[n(S,{class:"text-[32rpx] leading-[36rpx] font-600"},{default:s((()=>[g("优惠券")])),_:1}),n(I,{class:"absolute right-[37rpx] iconfont iconguanbi text-[50rpx]",onClick:t[10]||(t[10]=e=>F(j)?j.value=!1:j=!1)})])),_:1}),n(D,{class:"h-[520rpx]","scroll-y":"true"},{default:s((()=>[n(I,{class:"px-[20rpx]"},{default:s((()=>[(a(!0),u(c,null,p($(ae),((e,t)=>(a(),l(I,{class:"mb-[30rpx] flex items-center border-[2rpx] border-solid border-[rgba(0,0,0,.1)] rounded",key:t},{default:s((()=>[n(I,{class:"flex flex-col items-center py-[20rpx] w-[240rpx] border-0 border-r-[2rpx] border-dashed border-[rgba(0,0,0,.1)]"},{default:s((()=>[n(I,{class:"text-xs price-font"},{default:s((()=>[n(S,{class:"text-[28rpx]"},{default:s((()=>[g("￥")])),_:1}),n(S,{class:"text-[48rpx]"},{default:s((()=>[g(v(e.price),1)])),_:2},1024)])),_:2},1024),n(S,{class:"text-xs mt-[12rpx]"},{default:s((()=>[g(v(Number(e.min_condition_money)?"满"+e.min_condition_money+"元可以使用":"无门槛优惠券"),1)])),_:2},1024)])),_:2},1024),n(I,{class:"ml-[20rpx] flex-1 flex flex-col py-[20rpx]"},{default:s((()=>[n(S,{class:"text-xs"},{default:s((()=>[g(v(e.title),1)])),_:2},1024),n(S,{class:"text-xs text-[#ABABAB] mt-[12rpx]"},{default:s((()=>[g(v(1==e.valid_type&&"领取之日起"+e.length+"天内有效"||2==e.valid_type&&"有效期"+e.valid_start_time+"至"+e.valid_end_time),1)])),_:2},1024)])),_:2},1024),"collecting"===e.btnType?(a(),l(S,{key:0,class:"bg-[var(--primary-color)] rounded-2xl text-[#fff] text-xs mr-[20rpx] py-[8rpx] px-[16rpx]",onClick:a=>((e,t)=>{if(!r.value)return A().setLoginBack({url:"/shop/pages/goods/detail",param:{sku_id:h.value.sku_id}}),!1;te({coupon_id:e.id||"",number:1}).then((e=>{ae.value[t].btnType="using"}))})(e,t)},{default:s((()=>[g("领取")])),_:2},1032,["onClick"])):(a(),l(S,{key:1,class:"!bg-[#fff] rounded-2xl text-[#ABABAB] text-xs mr-[20rpx] py-[8rpx] px-[16rpx]"},{default:s((()=>[g(v("collected"===e.btnType?"已领玩":"已领取"),1)])),_:2},1024))])),_:2},1024)))),128))])),_:1})])),_:1}),n(I,{class:"px-[32rpx] pb-[67rpx] pt-[42rpx]"},{default:s((()=>[n(Y,{class:"!w-[100%] !h-[80rpx] text-[30rpx] !bg-[var(--primary-color)] !text-[#fff] !m-0 rounded-full leading-[80rpx]",onClick:t[11]||(t[11]=e=>F(j)?j.value=!1:j=!1)},{default:s((()=>[g("确定")])),_:1})])),_:1})])),_:1})])),_:1},8,["show"])])),_:1})):d("v-if",!0),n(oe,{ref_key:"goodsSkuRef",ref:y,"goods-detail":$(h),onChange:N},null,8,["goods-detail"]),n(ee,{"bg-color":"rgb(248,248,248)",loading:$(k),fontSize:"16",color:"#333"},null,8,["loading"])],64)}}}),[["__scopeId","data-v-97730123"]]);export{ne as default};