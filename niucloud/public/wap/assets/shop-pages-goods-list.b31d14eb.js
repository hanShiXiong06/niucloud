import{d as e,r as a,o as t,aI as l,K as s,i as o,j as r,w as c,k as n,n as i,bh as p,D as u,G as x,P as d,Q as m,R as f,m as _,a9 as v,aa as b,a6 as g,I as h,x as y,q as j,t as k,H as w,y as F,e as C}from"./index-19cdd642.js";import{_ as S}from"./u-popup.9c625195.js";import{_ as I}from"./u-icon.608fbfc2.js";import{_ as M}from"./u--image.925f4ba8.js";import{_ as z}from"./tabbar.b665b011.js";import{a as U,b as D}from"./goods.70b25e7a.js";import{u as R,M as T}from"./useMescroll.566e88d0.js";import{M as V}from"./mescroll-empty.118d7584.js";import{u as q}from"./useShare.7d3ba6d6.js";import{_ as A}from"./_plugin-vue_export-helper.1b428a4d.js";import"./u-transition.862f512b.js";import"./u-safe-bottom.c40e3bad.js";import"./u-image.28b481c5.js";import"./u-badge.23b944d1.js";import"./u-tabbar.41e257d5.js";/* empty css                                                                       */import"./wechat.e4f076c9.js";const E=A(e({__name:"list",setup(e){const{mescrollInit:A,downCallback:E,getMescroll:N}=R(b,v),{setShare:P,onShareAppMessage:Q,onShareTimeline:B}=q();P(),Q(),B();let G=a([]),H=a([]),K=a(""),J=a(""),L=a(null),O=a(!1),W=a(!1),X=a(""),Y=a("asc"),Z=a("asc"),$=a("all"),ee=a(!0);t((async e=>{J.value=e.curr_goods_category||"",X.value=e.goods_name||"",K.value=e.coupon_id||"",await U().then((e=>{G.value.push({category_name:"全部",category_id:""}),G.value=G.value.concat(e.data)}))}));const ae=e=>{O.value=!1;let a={goods_category:J.value,page:e.num,limit:e.size,keyword:X.value,coupon_id:K.value,order:"all"===$.value?"":$.value,sort:"price"==$.value?Y.value:Z.value};D(a).then((a=>{let t=a.data.data;1===Number(e.num)&&(H.value=[]),H.value=H.value.concat(t),e.endSuccess(t.length),O.value=!0})).catch((()=>{O.value=!0,e.endErr()}))},te=e=>{e==$.value&&"price"==e&&(Z.value="asc",Y.value="asc"==Y.value?"desc":"asc"),e==$.value&&"sale_num"==e&&(Y.value="asc",Z.value="asc"==Z.value?"desc":"asc"),$.value=e,"label"==e?(Z.value="asc",Y.value="asc",W.value=!0):(W.value=!1,H.value=[],N().resetUpScroll())},le=()=>{ee.value=!ee.value},se=e=>{C({url:"/shop/pages/goods/detail",param:{goods_id:e},mode:"navigateTo"})};return l((()=>{setTimeout((()=>{N().optUp.textNoMore=s("end")}),500)})),(e,a)=>{const t=g,l=h,s=y,v=j(k("u-popup"),S),b=j(k("u-icon"),I),C=j(k("u--image"),M),U=j(k("tabbar"),z);return o(),r(s,{class:"bg-gray-100 min-h-[100vh]"},{default:c((()=>[n(s,{class:"fixed left-0 right-0 top-0 product-warp bg-[#fff] px-[24rpx]"},{default:c((()=>[n(s,{class:"flex items-center h-[106rpx] box-border py-[24rpx]"},{default:c((()=>[n(s,{class:"bg-[#F5F5F5] flex items-center justify-between h-[66rpx] rounded-[33rpx] flex-1 pl-[20rpx] mr-[40rpx]"},{default:c((()=>[n(t,{class:"uni-input text-sm flex-1",maxlength:"50",modelValue:i(X),"onUpdate:modelValue":a[0]||(a[0]=e=>p(X)?X.value=e:X=e),onConfirm:a[1]||(a[1]=e=>te("all")),placeholder:"请搜索您想要的商品"},null,8,["modelValue"]),n(l,{class:"iconfont iconxiazai17 text-[30rpx] mr-[18rpx]",onClick:a[2]||(a[2]=e=>te("all"))})])),_:1}),n(l,{class:u(["iconfont text-[44rpx]",i(ee)?"iconshangpinliebiao":"iconliebiaoxingshi"]),onClick:le},null,8,["class"])])),_:1}),n(s,{class:"pb-3 pt-1 flex items-center justify-between"},{default:c((()=>[n(l,{class:u(["text-sm",{"text-color":"all"==i($)}]),onClick:a[3]||(a[3]=e=>te("all"))},{default:c((()=>[x("综合")])),_:1},8,["class"]),n(s,{class:u(["flex items-center",[{"text-color":"sale_num"==i($)}]]),onClick:a[4]||(a[4]=e=>te("sale_num"))},{default:c((()=>[n(l,{class:"text-sm mr-[4rpx]"},{default:c((()=>[x("销量")])),_:1}),"asc"==i(Z)?(o(),r(l,{key:0,class:"text-xs iconfont iconjiantoushang font-bold"})):(o(),r(l,{key:1,class:"text-xs iconfont iconxialajiantouxiao"}))])),_:1},8,["class"]),n(s,{class:u(["flex items-center",[{"text-color":"price"==i($)}]]),onClick:a[5]||(a[5]=e=>te("price"))},{default:c((()=>[n(l,{class:"text-sm mr-[4rpx]"},{default:c((()=>[x("价格")])),_:1}),"asc"==i(Y)?(o(),r(l,{key:0,class:"text-xs iconfont iconjiantoushang font-bold"})):(o(),r(l,{key:1,class:"text-xs iconfont iconxialajiantouxiao"}))])),_:1},8,["class"]),n(s,{class:u(["flex items-center",[{"text-color":"label"==i($)}]]),onClick:a[6]||(a[6]=e=>te("label"))},{default:c((()=>[n(l,{class:"text-sm mr-[2rpx]"},{default:c((()=>[x("筛选")])),_:1}),n(l,{class:"iconfont iconshaixuan"})])),_:1},8,["class"])])),_:1})])),_:1}),n(v,{show:i(W),mode:"top",onClose:a[7]||(a[7]=e=>p(W)?W.value=!1:W=!1)},{default:c((()=>[n(s,{class:"text-sm font-bold px-[30rpx] mt-3"},{default:c((()=>[x("全部分类")])),_:1}),n(s,{class:"flex flex-wrap pl-[30rpx] pt-[30rpx]"},{default:c((()=>[(o(!0),d(f,null,m(i(G),((e,a)=>(o(),r(l,{onClick:a=>{return t=e.category_id,J.value=t,H.value=[],N().resetUpScroll(),void(W.value=!1);var t},key:e.category_id,class:u(["px-[26rpx] border-[2rpx] border-solid border-transparent h-[60rpx] mr-[30rpx] mb-[30rpx] flex items-center justify-center bg-[#F4F4F4] rounded-[8rpx] text-xs",{"label-select":i(J)==e.category_id}])},{default:c((()=>[x(w(e.category_name),1)])),_:2},1032,["onClick","class"])))),128))])),_:1})])),_:1},8,["show"]),n(T,{ref_key:"mescrollRef",ref:L,top:"174rpx",bottom:"50px",onInit:i(A),onDown:i(E),onUp:ae},{default:c((()=>[n(s,{class:u(["p-[24rpx] !pb-0",i(ee)?"":"flex justify-between flex-wrap"])},{default:c((()=>[(o(!0),d(f,null,m(i(H),((e,a)=>(o(),d(f,null,[i(ee)?(o(),r(s,{key:0,class:u(["bg-white flex p-[20rpx] rounded-[16rpx]",{"mt-[20rpx]":a}]),onClick:a=>se(e.goods_id)},{default:c((()=>[n(C,{class:"rounded-[10rpx] overflow-hidden",width:"200rpx",height:"200rpx",src:i(F)(e.goods_cover_thumb_small?e.goods_cover_thumb_small:""),model:"aspectFill"},{error:c((()=>[n(b,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src"]),n(s,{class:"flex-1 flex flex-col ml-[20rpx]"},{default:c((()=>[n(s,{class:"text-[26rpx] font-500 h-[80rpx] leading-[40rpx] multi-hidden mb-[10rpx]"},{default:c((()=>[x(w(e.goods_name),1)])),_:2},1024),n(s,{class:"mt-auto flex justify-between items-end"},{default:c((()=>[n(s,{class:"flex flex-col"},{default:c((()=>[n(l,{class:"text-[28rpx] text-[var(--price-text-color)] price-font"},{default:c((()=>[x("￥"+w(e.goodsSku.price),1)])),_:2},1024)])),_:2},1024),n(l,{class:"text--[24rpx] text-[#666]"},{default:c((()=>[x("已售"+w(e.sale_num)+w(e.unit),1)])),_:2},1024)])),_:2},1024)])),_:2},1024)])),_:2},1032,["class","onClick"])):(o(),r(s,{key:1,class:"w-[342rpx] bg-[#fff] box-border rounded-[10rpx] overflow-hidden mt-[20rpx]",onClick:a=>se(e.goods_id)},{default:c((()=>[n(C,{width:"342rpx",height:"342rpx",src:i(F)(e.goods_cover_thumb_small?e.goods_cover_thumb_small:""),model:"aspectFill"},{error:c((()=>[n(b,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src"]),n(s,{class:"pl-[22rpx] pr-[30rpx] mt-[18rpx] h-[80rpx] leading-[40rpx] text-[26rpx] font-500 multi-hidden"},{default:c((()=>[x(w(e.goods_name),1)])),_:2},1024),n(s,{class:"pl-[22rpx] pb-[20rpx] pr-[30rpx] flex justify-between items-end mt-[12rpx]"},{default:c((()=>[n(s,{class:"flex justify-between items-end"},{default:c((()=>[n(l,{class:"text-[28rpx] text-[var(--price-text-color)] price-font"},{default:c((()=>[x("￥"+w(e.goodsSku.price),1)])),_:2},1024),_(' <text class="text-[28rpx] font-bold text-[#FF3223]"\r\n\t\t\t\t\t\t\t\t\tv-if="!parseFloat(item.price) && parseFloat(item.balance)">{{item.balance}}牛币</text>\r\n\t\t\t\t\t\t\t\t<text class="text-[24rpx] ml-[6rpx]"\r\n\t\t\t\t\t\t\t\t\tv-if="parseFloat(item.price) && parseFloat(item.balance) || !parseFloat(item.price) && !parseFloat(item.balance)">{{item.balance}}牛币</text> ')])),_:2},1024),n(l,{class:"text--[24rpx] text-[#666] leading-[31rpx]"},{default:c((()=>[x("已售"+w(e.sale_num)+w(e.unit),1)])),_:2},1024)])),_:2},1024)])),_:2},1032,["onClick"]))],64)))),256))])),_:1},8,["class"]),!i(H).length&&i(O)?(o(),r(V,{key:0})):_("v-if",!0)])),_:1},8,["onInit","onDown"]),n(U)])),_:1})}}}),[["__scopeId","data-v-9a2c8a62"]]);export{E as default};
