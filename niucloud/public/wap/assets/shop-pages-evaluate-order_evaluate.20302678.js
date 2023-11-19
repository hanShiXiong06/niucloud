import{d as e,c as a,q as l,t,i as r,j as o,w as s,P as u,R as d,Q as n,n as p,D as c,k as i,y as x,E as m,G as f,H as _,_ as v,aj as g,I as h,x as b,r as j,o as w,e as y,aG as V,aI as k}from"./index-98826dc8.js";import{_ as C}from"./u-icon.f3535e52.js";import{_ as z}from"./u--image.6ab34ad7.js";import{s as U,_ as I}from"./evaluate.486b4b82.js";import{_ as A}from"./u-tabbar.6861fbf6.js";import{_ as E}from"./u-loading-page.336cca7c.js";import{g as S}from"./order.f5c4f493.js";import{_ as F}from"./u-upload.d7afaa2b.js";import{_ as G}from"./_plugin-vue_export-helper.1b428a4d.js";import"./u-image.0f87688c.js";import"./u-transition.b9a2700d.js";import"./u-safe-bottom.35e4ae97.js";import"./u-loading-icon.9963a5a3.js";const P=e({__name:"upload-img",props:{modelValue:{type:String||Array},maxCount:{type:Number,default:6}},setup(e){const j=e,w=a({get:()=>j.modelValue,set(e){emit("update:modelValue",e)}}),y=a((()=>j.maxCount)),V=e=>{var a;if((null==(a=w.value)?void 0:a.length)>=y.value)return v({title:`最大上传${y.value}张图片`,icon:"none"}),!1;g({filePath:e.file.url,name:"file"}).then((e=>{w.value.push(e.data.url)})).catch((()=>{}))};return(e,a)=>{const v=l(t("u-icon"),C),g=l(t("u--image"),z),j=h,k=b,U=l(t("u-upload"),F);return r(),o(k,{class:"flex flex-wrap"},{default:s((()=>[(r(!0),u(d,null,n(p(w),((e,a)=>(r(),o(k,{class:c(["mb-[18rpx] relative",(a+1)%3==0?"":"mr-[18rpx]"])},{default:s((()=>[i(g,{class:"rounded-[10rpx] overflow-hidden",width:"204rpx",height:"204rpx",src:p(x)(e||""),model:"aspectFill"},{error:s((()=>[i(v,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src"]),i(j,{class:"iconguanbi iconfont absolute top-0 right-[5rpx] text-[#fff] bg-[#888] rounded-bl-[16rpx]",onClick:m((e=>(e=>{w.value.splice(e,1)})(a)),["stop"])},null,8,["onClick"])])),_:2},1032,["class"])))),256)),i(k,{class:"w-[204rpx] h-[204rpx]"},{default:s((()=>[i(U,{onAfterRead:V,maxCount:p(y)},{default:s((()=>[i(k,{class:"flex items-center justify-center w-[204rpx] h-[204rpx] border-[2rpx] border-dashed border-[#ebebec] text-center text-[#888]"},{default:s((()=>[i(k,null,{default:s((()=>[i(k,{class:"iconfont iconzhaoxiangji text-[50rpx]"}),i(k,{class:"text-[24rpx] mt-[10rpx]"},{default:s((()=>[f(_(p(w).length)+"/"+_(p(y)),1)])),_:1})])),_:1})])),_:1})])),_:1},8,["maxCount"])])),_:1})])),_:1})}}}),R=G(e({__name:"order_evaluate",setup(e){const a=j([]),m=j([]),g=j("2"),F=j(!1),G=j("");w((e=>{G.value=e.order_id,R(G.value)}));const R=e=>{F.value=!0,S(e).then((e=>{if(e.data.is_evaluate)return B(G.value),!1;a.value=e.data.order_goods,e.data.order_goods.forEach((e=>{m.value.push({order_id:e.order_id,order_goods_id:e.order_goods_id,goods_id:e.goods_id,content:"",images:[],scores:5})})),F.value=!1})).catch((()=>{F.value=!1}))},T=()=>{g.value="1"===g.value?"2":"1"},q=()=>{if(m.value.some((e=>""==e.content)))return v({title:"请输入你的评价",icon:"none"}),!1;m.value.forEach((e=>e.is_anonymous=g.value)),F.value=!0,U({evaluate_array:m.value}).then((e=>{F.value=!1,B(G.value)})).catch((()=>{F.value=!1}))},B=e=>{y({url:"/shop/pages/evaluate/order_evaluate_view",param:{order_id:e},mode:"redirectTo"})};return(e,v)=>{const j=l(t("u-icon"),C),w=l(t("u--image"),z),y=b,U=h,S=l(t("u-rate"),I),G=k,R=V,B=l(t("u-tabbar"),A),D=l(t("u-loading-page"),E);return r(),o(y,{class:"bg-[#f8f8f8] min-h-screen"},{default:s((()=>[i(y,{class:"px-[24rpx] py-[20rpx]"},{default:s((()=>[(r(!0),u(d,null,n(a.value,((e,a)=>(r(),o(y,{key:a,class:"bg-white py-[20rpx] px-[24rpx] mb-[20rpx] rounded-[16rpx]"},{default:s((()=>[i(y,{class:"flex mb-[20rpx]"},{default:s((()=>[i(w,{class:"rounded-[10rpx] overflow-hidden",width:"200rpx",height:"200rpx",src:p(x)(e.goods_cover_thumb_small?e.goods_cover_thumb_small:""),model:"aspectFill"},{error:s((()=>[i(j,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src"]),i(y,{class:"flex-1 flex flex-wrap ml-[20rpx]"},{default:s((()=>[i(y,null,{default:s((()=>[i(y,{class:"text-[26rpx] font-500 h-[80rpx] leading-[40rpx] multi-hidden mb-[10rpx]"},{default:s((()=>[f(_(e.goods_name),1)])),_:2},1024),i(y,{class:"w-[404rpx] mt-[12rpx] truncate text-[#888] text-[24rpx] leading-[32rpx] font-500"},{default:s((()=>[f(_(e.sku_name),1)])),_:2},1024)])),_:2},1024),i(y,{class:"mt-auto flex self-end justify-between w-[100%]"},{default:s((()=>[i(y,{class:"flex flex-col"},{default:s((()=>[i(U,{class:"text-[28rpx] text-[var(--price-text-color)] price-font"},{default:s((()=>[f("￥"+_(e.price),1)])),_:2},1024)])),_:2},1024),i(U,{class:"text--[24rpx] text-[#666]"},{default:s((()=>[f("x"+_(e.num),1)])),_:2},1024)])),_:2},1024)])),_:2},1024)])),_:2},1024),i(y,{class:"pt-[20rpx] flex items-center border-0 border-t-[2rpx] border-solid border-[#ebebec]"},{default:s((()=>[i(S,{count:5,modelValue:m.value[a].scores,"onUpdate:modelValue":e=>m.value[a].scores=e,"active-color":"var(--primary-color)",size:"40rpx"},null,8,["modelValue","onUpdate:modelValue"]),i(U,{class:"ml-[60rpx] text-[26rpx] text-[#888]"},{default:s((()=>[f(_(1===m.value[a].scores?"差评":2===m.value[a].scores||3===m.value[a].scores?"中评":"好评"),1)])),_:2},1024)])),_:2},1024),i(G,{class:"!text-[26rpx] mt-[20rpx] w-[100%]",modelValue:m.value[a].content,"onUpdate:modelValue":e=>m.value[a].content=e,placeholder:"请在此处输入你的评价",maxlength:"200"},null,8,["modelValue","onUpdate:modelValue"]),i(y,{class:"text-right text-[24rpx] text-[#888]"},{default:s((()=>[f(_(m.value[a].content.length)+"/200 ",1)])),_:2},1024),i(p(P),{class:"mt-[20rpx]",modelValue:m.value[a].images,"onUpdate:modelValue":e=>m.value[a].images=e,"max-count":6},null,8,["modelValue","onUpdate:modelValue"])])),_:2},1024)))),128))])),_:1}),i(B,{fixed:!0,placeholder:!0,safeAreaInsetBottom:!0},{default:s((()=>[i(y,{class:"flex items-center px-[30rpx] py-[10rpx] box-border justify-between w-[100%]"},{default:s((()=>[i(y,{class:"flex items-center",onClick:T},{default:s((()=>[i(U,{class:c(["iconfont text-color text-[34rpx] mr-[12rpx]",{"iconxuanze1 text-[var(--primary-color)]":"1"===g.value,iconcheckbox_nol:"1"!==g.value}])},null,8,["class"]),i(U,{class:c(["font-500 text-[28rpx]",{"text-[var(--primary-color)]":"1"===g.value,"text-[#676767]":"1"!==g.value}])},{default:s((()=>[f("匿名")])),_:1},8,["class"])])),_:1}),i(R,{class:"!w-[444rpx] !h-[80rpx] text-[32rpx] !m-0 leading-[80rpx] rounded-full text-white bg-[var(--primary-color)] remove-border",onClick:q},{default:s((()=>[f(" 提交")])),_:1})])),_:1})])),_:1}),i(D,{"bg-color":"rgb(248,248,248)",loading:F.value,fontSize:"16",color:"#333"},null,8,["loading"])])),_:1})}}}),[["__scopeId","data-v-e5b19e17"]]);export{R as default};
