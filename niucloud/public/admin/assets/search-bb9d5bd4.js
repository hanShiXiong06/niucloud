/* empty css             *//* empty css                   */import{a as S,E as U}from"./el-form-item-144bc604.js";/* empty css                *//* empty css                 *//* empty css                  *//* empty css                       *//* empty css                 */import{t as s}from"./index-6b4cbbe4.js";import{u as A,a as K}from"./vue-router-9f815af7.js";import{l as N,m as F}from"./delivery-44173086.js";import{E as j,b as L}from"./index-6ff31475.js";import{E as M}from"./index-6f670765.js";import{E as O}from"./index-5f2625ed.js";import{E as Y}from"./index-c5af06c2.js";import{v as $}from"./directive-d226d53f.js";import{d as q,r as V,M as E,b as _,e as u,f as l,u as o,x as p,L as G,m as H,p as i,q as a,v as d,C as f,at as J,au as Q}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as W}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./event-3e082a4a.js";import"./_baseClone-37ba2e68.js";import"./index-596de8a9.js";import"./common-a45d855f.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";/* empty css                  */import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";const g=k=>(J("data-v-14df74fa"),k=k(),Q(),k),X={class:"main-container"},Z={class:"flex ml-[18px] justify-between items-center mt-[20px]"},ee={class:"detail-head !m-0"},te=g(()=>l("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1)),oe={class:"ml-[1px]"},ae=g(()=>l("span",{class:"adorn"},"|",-1)),se={class:"right"},le={key:0,class:"text-[12px] text-[#b2b2b2]"},ie={key:1,class:"text-[12px] text-[#b2b2b2]"},pe={key:2,class:"text-[12px] text-[#b2b2b2]"},re={key:0},de={class:"text-[12px] text-[#b2b2b2]"},ne={class:"text-[12px] text-[#b2b2b2]"},ce={key:1},_e={class:"text-[12px] text-[#b2b2b2]"},me={class:"text-[12px] text-[#b2b2b2]"},ue={class:"fixed-footer-wrap"},fe={class:"fixed-footer"},ke=q({__name:"search",setup(k){const C=A(),P=K(),T=C.meta.title,c=V(!0),e=E({interface_type:1,kdniao_id:"",kdniao_app_key:"",kdniao_is_pay:1,kd100_app_key:"",kd100_customer:""});(async(b=0)=>{const t=await(await N()).data;Object.keys(e).forEach(n=>{t[n]!=null&&(e[n]=t[n])}),c.value=!1})();const h=()=>{window.open("https://www.kdniao.com/","_blank")},x=V(),D=E({}),I=async b=>{c.value||!b||await b.validate(async t=>{t&&(c.value=!0,F(e).then(()=>{c.value=!1}).catch(()=>{c.value=!1}))})};return(b,t)=>{const n=j,w=L,v=M,m=S,y=O,B=Y,R=U,z=$;return _(),u("div",X,[l("div",Z,[l("div",ee,[l("div",{class:"left",onClick:t[0]||(t[0]=r=>o(P).push("/shop/order/delivery"))},[te,l("span",oe,p(o(s)("returnToPreviousPage")),1)]),ae,l("span",se,p(o(T)),1)])]),G((_(),H(R,{model:e,"label-width":"150px",ref_key:"formRef",ref:x,rules:D,class:"page-form"},{default:i(()=>[a(B,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[a(m,{label:o(s)("interfaceType"),prop:"interface_type",class:""},{default:i(()=>[l("div",null,[a(w,{modelValue:e.interface_type,"onUpdate:modelValue":t[1]||(t[1]=r=>e.interface_type=r)},{default:i(()=>[a(n,{label:1,size:"large"},{default:i(()=>[d(p(o(s)("kdn")),1)]),_:1}),a(n,{label:2,size:"large"},{default:i(()=>[d(p(o(s)("kd100")),1)]),_:1})]),_:1},8,["modelValue"]),e.interface_type==1?(_(),u("p",le,[d(p(o(s)("promptTips1-1")),1),a(v,{class:"button-size",type:"primary",link:"",onClick:h},{default:i(()=>[d("https://www.kdniao.com/")]),_:1})])):f("",!0),e.interface_type==1?(_(),u("p",ie,p(o(s)("promptTips1-2")),1)):f("",!0),e.interface_type==2?(_(),u("p",pe,[d(p(o(s)("promptTips2")),1),a(v,{class:"button-size",type:"primary",link:"",onClick:h},{default:i(()=>[d("https://www.kuaidi100.com/")]),_:1})])):f("",!0)])]),_:1},8,["label"]),e.interface_type==1?(_(),u("div",re,[a(m,{label:o(s)("isPayEdition"),prop:"kdn_is_pay",class:"items-center"},{default:i(()=>[a(w,{modelValue:e.kdniao_is_pay,"onUpdate:modelValue":t[2]||(t[2]=r=>e.kdniao_is_pay=r)},{default:i(()=>[a(n,{label:1,size:"large"},{default:i(()=>[d(p(o(s)("free")),1)]),_:1}),a(n,{label:2,size:"large"},{default:i(()=>[d(p(o(s)("pay")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),a(m,{label:"EBusinessID",class:"input-item"},{default:i(()=>[l("div",null,[a(y,{modelValue:e.kdniao_id,"onUpdate:modelValue":t[3]||(t[3]=r=>e.kdniao_id=r),placeholder:o(s)("kdnEBusinessIDPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),l("p",de,p(o(s)("kdnEBusinessIDTips")),1)])]),_:1}),a(m,{label:"APPKEY",class:"input-item"},{default:i(()=>[l("div",null,[a(y,{modelValue:e.kdniao_app_key,"onUpdate:modelValue":t[4]||(t[4]=r=>e.kdniao_app_key=r),clearable:"",placeholder:o(s)("kdnAppKeyPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"]),l("p",ne,p(o(s)("kdnAppKeyTips")),1)])]),_:1})])):f("",!0),e.interface_type==2?(_(),u("div",ce,[a(m,{label:"APPKEY",class:"input-item"},{default:i(()=>[l("div",null,[a(y,{modelValue:e.kd100_app_key,"onUpdate:modelValue":t[5]||(t[5]=r=>e.kd100_app_key=r),clearable:"",placeholder:o(s)("kd100AppKeyPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"]),l("p",_e,p(o(s)("kd100AppKeyTips")),1)])]),_:1}),a(m,{label:"CUSTOMER",class:"input-item"},{default:i(()=>[l("div",null,[a(y,{modelValue:e.kd100_customer,"onUpdate:modelValue":t[6]||(t[6]=r=>e.kd100_customer=r),placeholder:o(s)("kd100CustomerPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),l("p",me,p(o(s)("kd100CustomerTips")),1)])]),_:1})])):f("",!0)]),_:1})]),_:1},8,["model","rules"])),[[z,c.value]]),l("div",ue,[l("div",fe,[a(v,{type:"primary",loading:c.value,onClick:t[7]||(t[7]=r=>I(x.value))},{default:i(()=>[d(p(o(s)("save")),1)]),_:1},8,["loading"])])])])}}});const et=W(ke,[["__scopeId","data-v-14df74fa"]]);export{et as default};
