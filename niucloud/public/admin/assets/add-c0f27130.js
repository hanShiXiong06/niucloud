/* empty css             *//* empty css                  *//* empty css                */import{a as F,E as G}from"./el-form-item-144bc604.js";/* empty css                       *//* empty css                 */import"./index-596de8a9.js";/* empty css                  *//* empty css                          *//* empty css               *//* empty css                    *//* empty css                 *//* empty css                       */import{v as _}from"./event-3e082a4a.js";import{t as i}from"./index-6b4cbbe4.js";import{u as I,a as j}from"./vue-router-9f815af7.js";import{g as z,a as A}from"./marketing-b4c665bf.js";import H from"./goods-select-popup-b39a0a0b.js";import{E as J}from"./index-5f2625ed.js";import{E as K,b as M}from"./index-6ff31475.js";import{E as O}from"./index-22ce9a15.js";import{E as Q}from"./index-e6e7384d.js";import{E as W}from"./index-c5af06c2.js";import{E as X}from"./index-6f670765.js";import{d as Y,r as f,c as Z,b as ee,e as le,f as n,u as r,x as s,q as l,p as o,v as u,L as v}from"./runtime-core.esm-bundler-c17ab5bc.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./_baseClone-37ba2e68.js";import"./common-a45d855f.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";/* empty css                   *//* empty css                      *//* empty css                  *//* empty css                  *//* empty css                        */import"./el-tooltip-4ed993c7.js";/* empty css                 *//* empty css                        */import"./goods_default-3802d665.js";import"./goods-c51e60b4.js";import"./cloneDeep-028669bf.js";import"./index-b7b91fcb.js";import"./index-5c20ff8f.js";import"./strings-e72e60f7.js";import"./isEqual-ca98cf0c.js";import"./debounce-196ce6a6.js";import"./index-bfecf17f.js";import"./validator-f5e079db.js";import"./index-6191b0b4.js";import"./index-784d7581.js";import"./_isIterateeCall-797defa1.js";import"./index-6701860e.js";import"./index-f6eed9e8.js";import"./position-0d02b322.js";import"./index-cefc0b67.js";import"./directive-d226d53f.js";import"./rand-14326ce1.js";import"./aria-adfa05c5.js";import"./arrays-e667dc24.js";import"./index-43e913f7.js";const te={class:"main-container"},oe={class:"detail-head"},ae=n("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1),ie={class:"ml-[1px]"},re=n("span",{class:"adorn"},"|",-1),ue={class:"right"},de={class:"flex items-center",type:"number",οninput:"value=value.replace(/[^\\d.]/g,'')",style:{"padding-left":"5px"}},ne={class:"flex items-center",style:{"padding-left":"5px"}},me={class:"w-[220px]",style:{"padding-left":"5px"}},pe=n("div",{class:"form-tip"},"开启手动领取后，会员可以直接在优惠券列表以及优惠券推广中直接领取",-1),se={class:"w-[180px]"},_e=n("div",{class:"form-tip"},"最多发放100000张",-1),ve={class:"fixed-footer-wrap"},ce={class:"fixed-footer"},ql=Y({__name:"add",setup(fe){I();const w=j(),b=f(!1),E=f(null),U=new Date,g=new Date;g.setTime(g.getTime()+3600*1e3*2*360);const e=f({title:"",price:"",type:1,limit:2,receive_type:1,remain_count:1e3,threshold:2,limit_count:1,min_condition_money:1,length:30,goods_ids:[],goods_category_ids:[],receive_type_time:2,valid_type:1,receive_time:[U,g],valid_time:g}),V=f(),k=Z(()=>({title:[{required:!0,message:i("titlePlaceholder"),trigger:"blur"}],price:[{required:!0,validator:R,trigger:"blur"}],remain_count:[{required:!0,validator:P,trigger:"blur"}],limit_count:[{required:!0,validator:T,trigger:"blur"}]})),P=(c,t,m)=>{e.value.remain_count!=""&&e.value.remain_count>1e5&&m(new Error(i("remainCountPlaceholder"))),m()},R=(c,t,m)=>{e.value.price!=""&&e.value.price<.01&&m(new Error(i("pricePlaceholder"))),m()},T=(c,t,m)=>{e.value.limit_count!=""&&e.value.limit_count<1&&m(new Error(i("userLimitCountPlaceholder"))),m()};f(!0),f(1);const S={multiple:!0};f([]);const h=f([]),D=()=>{z({}).then(c=>{h.value=c.data.goods_category_tree})},L=async c=>{b.value||!c||await c.validate(async t=>{if(t){b.value=!0;let m=e.value;A(m).then(p=>{b.value=!1,history.back()}).catch(p=>{b.value=!1})}})},q=()=>{history.back()};return(c,t)=>{const m=J,d=F,p=K,y=M,B=O,x=Q,N=G,$=W,C=X;return ee(),le("div",te,[n("div",oe,[n("div",{class:"left",onClick:t[0]||(t[0]=a=>r(w).push("/shop/marketing/coupon/list"))},[ae,n("span",ie,s(r(i)("returnToPreviousPage")),1)]),re,n("span",ue,s(r(i)("addCoupon")),1)]),l($,{class:"box-card !border-none",shadow:"never"},{default:o(()=>[l(N,{model:e.value,"label-width":"120px",ref_key:"formRef",ref:V,rules:r(k),class:"page-form"},{default:o(()=>[l(d,{label:r(i)("title"),prop:"title"},{default:o(()=>[l(m,{modelValue:e.value.title,"onUpdate:modelValue":t[1]||(t[1]=a=>e.value.title=a),clearable:"",placeholder:r(i)("titlePlaceholder"),class:"input-width",maxlength:20},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(d,{label:r(i)("price"),prop:"price"},{default:o(()=>[l(m,{type:"number",οninput:"value=value.replace(/[^\\d.]/g,'')",modelValue:e.value.price,"onUpdate:modelValue":t[2]||(t[2]=a=>e.value.price=a),clearable:"",placeholder:r(i)("pricePlaceholder"),class:"input-width",maxlength:"60"},{append:o(()=>[u("元")]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),l(d,{label:r(i)("type"),prop:"type"},{default:o(()=>[l(y,{modelValue:e.value.type,"onUpdate:modelValue":t[3]||(t[3]=a=>e.value.type=a)},{default:o(()=>[l(p,{label:1},{default:o(()=>[u("通用券")]),_:1}),l(p,{label:2,onClick:D},{default:o(()=>[u("品类券")]),_:1}),l(p,{label:3},{default:o(()=>[u("商品券")]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),v(l(d,null,{default:o(()=>[n("div",null,[l(B,{modelValue:e.value.goods_category_ids,"onUpdate:modelValue":t[4]||(t[4]=a=>e.value.goods_category_ids=a),options:h.value,props:S,placeholder:"请选择商品分类","collapse-tags":"","collapse-tags-tooltip":"",clearable:""},null,8,["modelValue","options"])])]),_:1},512),[[_,e.value.type==2]]),v(l(d,null,{default:o(()=>[n("div",null,[l(d,null,{default:o(()=>[l(H,{ref_key:"goodsSelectPopupRef",ref:E,modelValue:e.value.goods_ids,"onUpdate:modelValue":t[5]||(t[5]=a=>e.value.goods_ids=a),min:"1",max:"99"},null,8,["modelValue"])]),_:1})])]),_:1},512),[[_,e.value.type==3]]),l(d,{label:r(i)("threshold")},{default:o(()=>[l(y,{modelValue:e.value.threshold,"onUpdate:modelValue":t[6]||(t[6]=a=>e.value.threshold=a)},{default:o(()=>[l(p,{label:1},{default:o(()=>[u(s(r(i)("reduction")),1)]),_:1}),l(p,{label:2},{default:o(()=>[u(s(r(i)("noThreshold")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),v(l(d,null,{default:o(()=>[u(" 最低满 "),n("div",de,[l(m,{type:"number",modelValue:e.value.min_condition_money,"onUpdate:modelValue":t[7]||(t[7]=a=>e.value.min_condition_money=a),clearable:"",class:"!w-[100px]"},null,8,["modelValue"])]),u(" 元可用 ")]),_:1},512),[[_,e.value.threshold==1]]),l(d,{label:r(i)("validType")},{default:o(()=>[l(y,{modelValue:e.value.valid_type,"onUpdate:modelValue":t[8]||(t[8]=a=>e.value.valid_type=a)},{default:o(()=>[l(p,{label:1},{default:o(()=>[u(s(r(i)("days")),1)]),_:1}),l(p,{label:2},{default:o(()=>[u(s(r(i)("times")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),v(l(d,null,{default:o(()=>[u(" 领劵后立即生效，有效期 "),n("div",ne,[l(m,{type:"number",modelValue:e.value.length,"onUpdate:modelValue":t[9]||(t[9]=a=>e.value.length=a),clearable:"",class:"!w-[100px]"},null,8,["modelValue"]),u(" 天 ")])]),_:1},512),[[_,e.value.valid_type==1]]),v(l(d,{prop:"valid_time"},{default:o(()=>[u(" 领劵后立即生效，使用时间截止至 "),n("div",me,[l(x,{modelValue:e.value.valid_time,"onUpdate:modelValue":t[10]||(t[10]=a=>e.value.valid_time=a),type:"datetime"},null,8,["modelValue"])])]),_:1},512),[[_,e.value.valid_type==2]]),l(d,{label:r(i)("receiveType")},{default:o(()=>[n("div",null,[l(y,{modelValue:e.value.receive_type,"onUpdate:modelValue":t[11]||(t[11]=a=>e.value.receive_type=a)},{default:o(()=>[l(p,{label:1},{default:o(()=>[u(s(r(i)("user")),1)]),_:1}),l(p,{label:2},{default:o(()=>[u(s(r(i)("grant")),1)]),_:1})]),_:1},8,["modelValue"])]),pe]),_:1},8,["label"]),v(l(d,{label:r(i)("receiveTime")},{default:o(()=>[l(y,{modelValue:e.value.receive_type_time,"onUpdate:modelValue":t[12]||(t[12]=a=>e.value.receive_type_time=a)},{default:o(()=>[l(p,{label:1},{default:o(()=>[u(s(r(i)("limitedTime")),1)]),_:1}),l(p,{label:2},{default:o(()=>[u(s(r(i)("unlimitedTime")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),[[_,e.value.receive_type==1]]),v(l(d,{prop:"receive_time"},{default:o(()=>[n("div",se,[l(x,{modelValue:e.value.receive_time,"onUpdate:modelValue":t[13]||(t[13]=a=>e.value.receive_time=a),type:"datetimerange","range-separator":"至","start-placeholder":"开始日期","end-placeholder":"结束日期"},null,8,["modelValue"])])]),_:1},512),[[_,e.value.receive_type_time==1&&e.value.receive_type==1]]),v(l(d,{label:r(i)("receiveNumber")},{default:o(()=>[l(y,{modelValue:e.value.limit,"onUpdate:modelValue":t[14]||(t[14]=a=>e.value.limit=a)},{default:o(()=>[l(p,{label:1},{default:o(()=>[u(s(r(i)("limit")),1)]),_:1}),l(p,{label:2},{default:o(()=>[u(s(r(i)("unlimited")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),[[_,e.value.receive_type==1]]),v(l(d,{prop:"remain_count"},{default:o(()=>[n("div",null,[l(m,{type:"number",onkeypress:"return( /[\\d]/.test(String.fromCharCode(event.keyCode) ) )",modelValue:e.value.remain_count,"onUpdate:modelValue":t[15]||(t[15]=a=>e.value.remain_count=a),clearable:"",placeholder:r(i)("remainCountPlaceholder"),class:"input-width",min:1,max:1e5,controls:!1},{append:o(()=>[u("张")]),_:1},8,["modelValue","placeholder"])]),_e]),_:1},512),[[_,e.value.limit==1&&e.value.receive_type==1]]),v(l(d,{label:r(i)("userLimitCount"),prop:"limit_count"},{default:o(()=>[l(m,{type:"number",onkeypress:"return( /[\\d]/.test(String.fromCharCode(event.keyCode) ) )",modelValue:e.value.limit_count,"onUpdate:modelValue":t[16]||(t[16]=a=>e.value.limit_count=a),clearable:"",placeholder:r(i)("userLimitCountPlaceholder"),class:"input-width",min:1,max:1e5},{append:o(()=>[u("张")]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),[[_,e.value.receive_type==1]])]),_:1},8,["model","rules"])]),_:1}),n("div",ve,[n("div",ce,[l(C,{type:"primary",onClick:t[17]||(t[17]=a=>L(V.value))},{default:o(()=>[u(s(r(i)("save")),1)]),_:1}),l(C,{onClick:t[18]||(t[18]=a=>q())},{default:o(()=>[u(s(r(i)("cancel")),1)]),_:1})])])])}}});export{ql as default};
