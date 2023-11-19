import{_ as t}from"./u-icon.e1505caa.js";import{A as e,B as a,C as i,q as n,t as o,i as s,j as c,w as r,k as l,E as u,P as d,R as h,Q as m,D as v,p as f,m as p,x as _,aJ as g}from"./index-c8487b3e.js";import{_ as I}from"./_plugin-vue_export-helper.1b428a4d.js";const y=I({name:"u-rate",mixins:[a,i,{props:{modelValue:{type:[String,Number],default:e.rate.value},count:{type:[String,Number],default:e.rate.count},disabled:{type:Boolean,default:e.rate.disabled},readonly:{type:Boolean,default:e.rate.readonly},size:{type:[String,Number],default:e.rate.size},inactiveColor:{type:String,default:e.rate.inactiveColor},activeColor:{type:String,default:e.rate.activeColor},gutter:{type:[String,Number],default:e.rate.gutter},minCount:{type:[String,Number],default:e.rate.minCount},allowHalf:{type:Boolean,default:e.rate.allowHalf},activeIcon:{type:String,default:e.rate.activeIcon},inactiveIcon:{type:String,default:e.rate.inactiveIcon},touchable:{type:Boolean,default:e.rate.touchable}}}],data(){return{elId:uni.$u.guid(),elClass:uni.$u.guid(),rateBoxLeft:0,activeIndex:this.modelValue,rateWidth:0,moving:!1}},watch:{modelValue(t){this.activeIndex=t},activeIndex:"emitEvent"},emits:["update:modelValue","change"],methods:{init(){uni.$u.sleep().then((()=>{this.getRateItemRect(),this.getRateIconWrapRect()}))},async getRateItemRect(){await uni.$u.sleep(),this.$uGetRect("#"+this.elId).then((t=>{this.rateBoxLeft=t.left}))},getRateIconWrapRect(){this.$uGetRect("."+this.elClass).then((t=>{this.rateWidth=t.width}))},touchMove(t){if(!this.touchable)return;this.preventEvent(t);const e=t.changedTouches[0].pageX;this.getActiveIndex(e)},touchEnd(t){if(!this.touchable)return;this.preventEvent(t);const e=t.changedTouches[0].pageX;this.getActiveIndex(e)},clickHandler(t,e){if("ios"===uni.$u.os()&&this.moving)return;this.preventEvent(t);let a=0;a=t.changedTouches[0].pageX,this.getActiveIndex(a,!0)},emitEvent(){this.$emit("change",this.activeIndex),this.$emit("update:modelValue",this.activeIndex)},getActiveIndex(t,e=!1){if(this.disabled||this.readonly)return;const a=this.rateWidth*this.count+this.rateBoxLeft,i=t=uni.$u.range(this.rateBoxLeft,a,t)-this.rateBoxLeft;let n;if(this.allowHalf){n=Math.floor(i/this.rateWidth);const t=i%this.rateWidth;t<=this.rateWidth/2&&t>0?n+=.5:t>this.rateWidth/2&&n++}else{n=Math.floor(i/this.rateWidth);const t=i%this.rateWidth;e?t>0&&n++:t>this.rateWidth/2&&n++}this.activeIndex=Math.min(n,this.count),this.activeIndex<this.minCount&&(this.activeIndex=this.minCount),setTimeout((()=>{this.moving=!0}),10),setTimeout((()=>{this.moving=!1}),10)}},mounted(){this.init()}},[["render",function(e,a,i,g,I,y){const x=n(o("u-icon"),t),C=_;return s(),c(C,{class:"u-rate",id:I.elId,ref:"u-rate",style:f([e.$u.addStyle(e.customStyle)])},{default:r((()=>[l(C,{class:"u-rate__content",onTouchmove:u(y.touchMove,["stop"]),onTouchend:u(y.touchEnd,["stop"])},{default:r((()=>[(s(!0),d(h,null,m(Number(e.count),((t,a)=>(s(),c(C,{class:v(["u-rate__content__item",[I.elClass]]),key:a},{default:r((()=>[l(C,{class:"u-rate__content__item__icon-wrap",ref_for:!0,ref:"u-rate__content__item__icon-wrap",onClick:u((t=>y.clickHandler(t,a+1)),["stop"])},{default:r((()=>[l(x,{name:Math.floor(I.activeIndex)>a?e.activeIcon:e.inactiveIcon,color:e.disabled?"#c8c9cc":Math.floor(I.activeIndex)>a?e.activeColor:e.inactiveColor,"custom-style":{padding:`0 ${e.$u.addUnit(e.gutter/2)}`},size:e.size},null,8,["name","color","custom-style","size"])])),_:2},1032,["onClick"]),e.allowHalf?(s(),c(C,{key:0,onClick:u((t=>y.clickHandler(t,a+1)),["stop"]),class:"u-rate__content__item__icon-wrap u-rate__content__item__icon-wrap--half",style:f([{width:e.$u.addUnit(I.rateWidth/2)}]),ref_for:!0,ref:"u-rate__content__item__icon-wrap"},{default:r((()=>[l(x,{name:Math.ceil(I.activeIndex)>a?e.activeIcon:e.inactiveIcon,color:e.disabled?"#c8c9cc":Math.ceil(I.activeIndex)>a?e.activeColor:e.inactiveColor,"custom-style":{padding:`0 ${e.$u.addUnit(e.gutter/2)}`},size:e.size},null,8,["name","color","custom-style","size"])])),_:2},1032,["onClick","style"])):p("v-if",!0)])),_:2},1032,["class"])))),128))])),_:1},8,["onTouchmove","onTouchend"])])),_:1},8,["id","style"])}],["__scopeId","data-v-495ca2bf"]]);function x(t){return g.get("shop/goods/evaluate",t)}function C(t){return g.post("shop/goods/evaluate",t,{showSuccessMessage:!0})}function $(t){return g.get(`shop/order/evaluate/${t}`)}export{y as _,$ as a,x as g,C as s};
