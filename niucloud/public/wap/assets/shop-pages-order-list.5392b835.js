import{aJ as e,d as a,r,o as t,i as l,j as s,w as o,n as i,k as p,P as u,Q as d,R as n,m as c,a9 as x,aa as f,x as m,a7 as _,q as v,t as g,D as b,G as y,H as h,K as k,y as j,e as w,bm as C,I as S}from"./index-c8487b3e.js";import{_ as z}from"./u-icon.e1505caa.js";import{_ as D}from"./u--image.bde6b800.js";import{_ as I}from"./pay.9e381d65.js";import{c as U,d as M,o as E,a as P}from"./order.e6f765ef.js";import{u as R,M as F}from"./useMescroll.16a36445.js";import{M as K}from"./mescroll-empty.e6398411.js";import{_ as q}from"./_plugin-vue_export-helper.1b428a4d.js";import"./u-image.9b57fa1f.js";import"./u-transition.75acc538.js";import"./u-button.a3ccbdc0.js";import"./u-loading-icon.92d47c06.js";import"./u-popup.41f7f1c4.js";import"./u-safe-bottom.f4b61f4d.js";import"./pay.116f472c.js";import"./wechat.8ecaae3c.js";/* empty css                                                                       */const G=q(a({__name:"list",setup(a){const{mescrollInit:q,downCallback:G,getMescroll:H}=R(f,x);let J=r([]),N=r(!1),O=r(!1),Q=r(""),T=r([]);const V=r("");t((e=>{Q.value=e.status||"",W(),A()}));const W=()=>{e.get("shop/goods/evaluate/config").then((e=>{V.value=e.data}))},Y=e=>{N.value=!1;let a={page:e.num,limit:e.size,status:Q.value};M(a).then((a=>{let r=a.data.data;1==e.num&&(J.value=[]),J.value=J.value.concat(r),e.endSuccess(r.length),N.value=!0})).catch((()=>{N.value=!0,e.endErr()}))},A=()=>{O.value=!1,T.value=[];T.value.push({name:"全部",status:""}),U().then((e=>{Object.values(e.data).forEach(((e,a)=>{T.value.push(e)})),O.value=!0})).catch((()=>{O.value=!0}))},B=e=>{w({url:"/shop/pages/order/detail",param:{order_id:e.order_id}})},L=r(null),X=(e,a="")=>{var r;"pay"==a?null==(r=L.value)||r.open(e.order_type,e.order_id):"close"==a?Z(e):"finish"==a?$(e):"evaluate"==a&&(e.is_evaluate?w({url:"/shop/pages/evaluate/order_evaluate_view",param:{order_id:e.order_id}}):w({url:"/shop/pages/evaluate/order_evaluate",param:{order_id:e.order_id}}))},Z=e=>{C({title:"提示",content:"您确定要关闭该订单吗？",success:a=>{a.confirm&&E(e.order_id).then((e=>{H().resetUpScroll()}))}})},$=e=>{C({title:"提示",content:"您确定物品已收到吗？",success:a=>{a.confirm&&P(e.order_id).then((e=>{H().resetUpScroll()}))}})};return(e,a)=>{const r=m,t=_,x=v(g("u-icon"),z),f=v(g("u--image"),D),w=S,C=v(g("pay"),I);return l(),s(r,{class:"bg-[#f8f8f8] min-h-screen overflow-hidden order-list"},{default:o((()=>[i(O)?(l(),s(r,{key:0,class:"fixed left-0 top-0 right-0 z-10"},{default:o((()=>[p(t,{"scroll-x":"true",class:"scroll-Y box-border px-[24rpx] bg-white"},{default:o((()=>[p(r,{class:"flex whitespace-nowrap justify-around"},{default:o((()=>[(l(!0),u(n,null,d(i(T),((e,a)=>(l(),s(r,{class:b(["text-sm leading-[90rpx]",{"class-select":i(Q)===e.status.toString()}]),onClick:a=>{return r=e.status,Q.value=r.toString(),J.value=[],void H().resetUpScroll();var r}},{default:o((()=>[y(h(e.name),1)])),_:2},1032,["class","onClick"])))),256))])),_:1})])),_:1})])),_:1})):c("v-if",!0),p(F,{ref:"mescrollRef",top:"104rpx",onInit:i(q),onDown:i(G),onUp:Y},{default:o((()=>[p(r,{class:"goods-wrap"},{default:o((()=>[(l(!0),u(n,null,d(i(J),((e,a)=>(l(),s(r,{key:a,class:"mb-[30rpx] bg-[#fff] pb-[24rpx]"},{default:o((()=>[p(r,{onClick:a=>B(e)},{default:o((()=>[p(r,{class:"bg-[#fff] px-[24rpx] py-[24rpx] rounded-[16rpx]"},{default:o((()=>[p(r,{class:"text-[30rpx] text-[#222] flex justify-between"},{default:o((()=>[p(r,{class:""},{default:o((()=>[y(h(i(k)("orderNo"))+"："+h(e.order_no),1)])),_:2},1024),p(r,{class:"text-[var(--primary-color)]"},{default:o((()=>[y(h(e.status_name.name),1)])),_:2},1024)])),_:2},1024)])),_:2},1024),p(r,{class:"px-[24rpx]"},{default:o((()=>[(l(!0),u(n,null,d(e.order_goods,((e,a)=>(l(),s(r,{class:"order-goods-item flex mt-[50rpx]",key:a},{default:o((()=>[p(r,{class:"w-[160rpx] h-[160rpx] flex-2"},{default:o((()=>[p(f,{class:"rounded-[10rpx] overflow-hidden",width:"160rpx",height:"160rpx",src:i(j)(e.goods_image_thumb_small?e.goods_image_thumb_small:""),model:"aspectFill"},{error:o((()=>[p(x,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src"])])),_:2},1024),p(r,{class:"ml-[20rpx] flex flex-1 flex-col justify-between"},{default:o((()=>[p(r,null,{default:o((()=>[p(w,{class:"text-[28rpx] text-item leading-[40rpx]"},{default:o((()=>[y(h(e.goods_name),1)])),_:2},1024),e.sku_name?(l(),s(r,{key:0,class:"flex"},{default:o((()=>[p(w,{class:"block text-[20rpx] text-item mt-[10rpx] text-[#ccc] bg-[#f5f5f5] px-[16rpx] py-[6rpx] rounded-[999rpx]"},{default:o((()=>[y(h(e.sku_name),1)])),_:2},1024)])),_:2},1024)):c("v-if",!0)])),_:2},1024),p(r,{class:"flex justify-between"},{default:o((()=>[p(w,{class:"text-right text-[28rpx] text-[var(--price-text-color)] price-font"},{default:o((()=>[y("￥"+h(e.price),1)])),_:2},1024),p(w,{class:"text-right text-[24rpx]"},{default:o((()=>[y("x"+h(e.num),1)])),_:2},1024)])),_:2},1024)])),_:2},1024)])),_:2},1024)))),128))])),_:2},1024)])),_:2},1032,["onClick"]),p(r,{class:"flex justify-between text-[28rpx] px-[24rpx] mt-[40rpx]"},{default:o((()=>[p(w,{class:"text-[#999]"},{default:o((()=>[y(h(e.create_time),1)])),_:2},1024),p(w,null,{default:o((()=>[y(h(i(k)("actualPayment"))+"：",1),p(w,{class:"text-[var(--price-text-color)] price-font"},{default:o((()=>[y("￥"+h(e.order_money),1)])),_:2},1024)])),_:2},1024)])),_:2},1024),p(r,{class:"mt-[34rpx] flex justify-end z-10 px-[24rpx]"},{default:o((()=>[1==e.status?(l(),s(r,{key:0,class:"inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] border-solid border-[#999] rounded-full",onClick:a=>X(e,"close")},{default:o((()=>[y(h(i(k)("orderClose")),1)])),_:2},1032,["onClick"])):c("v-if",!0),p(r,{class:"inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] border-solid border-[#999] rounded-full ml-[20rpx]",onClick:a=>B(e)},{default:o((()=>[y(h(i(k)("orderDetail")),1)])),_:2},1032,["onClick"]),1==e.status?(l(),s(r,{key:1,class:"inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] border-solid text-[#fff] border-primary bg-primary rounded-full ml-[20rpx]",onClick:a=>X(e,"pay")},{default:o((()=>[y(h(i(k)("topay")),1)])),_:2},1032,["onClick"])):c("v-if",!0),3==e.status?(l(),s(r,{key:2,class:"inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] border-solid text-[#fff] border-primary bg-primary rounded-full ml-[20rpx]",onClick:a=>X(e,"finish")},{default:o((()=>[y(h(i(k)("orderFinish")),1)])),_:2},1032,["onClick"])):c("v-if",!0),5==e.status&&1==V.value.is_evaluate?(l(),s(r,{key:3,class:"inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] border-solid border-[#999] rounded-full ml-[20rpx]",onClick:a=>X(e,"evaluate")},{default:o((()=>[y(h(i(k)("evaluate")),1)])),_:2},1032,["onClick"])):c("v-if",!0)])),_:2},1024)])),_:2},1024)))),128))])),_:1}),!i(J).length&&i(N)?(l(),s(K,{key:0,option:{icon:i(j)("static/resource/images/empty.png")}},null,8,["option"])):c("v-if",!0)])),_:1},8,["onInit","onDown"]),p(C,{ref_key:"payRef",ref:L,onClose:e.payClose},null,8,["onClose"])])),_:1})}}}),[["__scopeId","data-v-56f29aa5"]]);export{G as default};