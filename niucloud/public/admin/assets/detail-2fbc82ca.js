import{g as k,r as _,m as c,n as x,a1 as D,D as p,E as a,q as t,L as o,u as l,F as i,T as E}from"./base-45eb5090.js";/* empty css                   *//* empty css                     *//* empty css                */import{t as r}from"./index-3694a2b2.js";import{g as F}from"./order-a3bcb05f.js";import{u as N,a as C}from"./vue-router-fcbaaf02.js";import{P as I}from"./index-ad71a852.js";import{a as R,E as T}from"./index-2bfbe5a7.js";import{E as B}from"./index-fc3020f4.js";import{v as M}from"./directive-9f485fe5.js";import"./common-af78c857.js";import"./common-2cf17469.js";import"./el-overlay-616d6124.js";import"./event-4977bef7.js";import"./index-cd1661d3.js";import"./focus-trap-318ae2e0.js";/* empty css                  *//* empty css                 */import"./el-radio-2719e44c.js";import"./storage-4159d1ed.js";import"./index-9670e877.js";import"./index-25c37860.js";import"./index-4ce9333e.js";import"./index-3be486d3.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-tooltip-58212670.js";import"./el-avatar-bc58ad46.js";import"./index-aef37b98.js";import"./_Uint8Array-e584e472.js";import"./_initCloneObject-983ff8c8.js";const S={class:"main-container"},V={class:"panel-title"},q={class:"input-width"},L={class:"input-width"},$={class:"input-width"},O={class:"input-width"},P={class:""},j={class:""},z={class:"input-width"},A={class:"input-width"},G={class:"input-width"},H={class:"input-width"},J={class:"input-width"},K={class:"input-width"},Q={class:"input-width"},U={class:"input-width"},Re=k({__name:"detail",setup(W){I();const f=N(),v=C(),u=parseInt(f.query.order_id),m=_(!0),e=_(null);u?(async(n=0)=>{m.value=!0,e.value=null,await F(n).then(({data:d})=>{e.value=d}).catch(()=>{}),m.value=!1})(u):m.value=!1;const b=_(),h=n=>{v.push(`/member/detail?id=${n}`)};return(n,d)=>{const s=R,w=B,y=T,g=M;return c(),x("div",S,[D((c(),p(y,{model:e.value,"label-width":"150px",ref_key:"formRef",ref:b,class:"page-form"},{default:a(()=>[e.value?(c(),p(w,{key:0,class:"box-card !border-none relative",shadow:"never"},{default:a(()=>[t("h3",V,o(l(r)("orderInfo")),1),i(s,{label:l(r)("orderNo")},{default:a(()=>[t("div",q,o(e.value.order_no),1)]),_:1},8,["label"]),i(s,{label:l(r)("orderMoney")},{default:a(()=>[t("div",L,o(e.value.order_money),1)]),_:1},8,["label"]),i(s,{label:l(r)("orderDiscountMoney")},{default:a(()=>[t("div",$,o(e.value.order_discount_money),1)]),_:1},8,["label"]),i(s,{label:l(r)("member")},{default:a(()=>[t("div",O,[t("div",{class:"flex flex flex-col cursor-pointer",onClick:d[0]||(d[0]=Y=>h(e.value.member_id))},[t("span",P,o(e.value.member.nickname||""),1),t("span",j,o(e.value.member.mobile||""),1)])])]),_:1},8,["label"]),i(s,{label:l(r)("ip")},{default:a(()=>[t("div",z,o(e.value.ip),1)]),_:1},8,["label"]),i(s,{label:l(r)("orderFromName")},{default:a(()=>[t("div",A,o(e.value.order_from_name),1)]),_:1},8,["label"]),i(s,{label:l(r)("orderStatus")},{default:a(()=>[t("div",G,o(e.value.order_status_info.name),1)]),_:1},8,["label"]),i(s,{label:l(r)("payTypeName")},{default:a(()=>[t("div",H,o(e.value.pay_type_name),1)]),_:1},8,["label"]),i(s,{label:l(r)("createTime")},{default:a(()=>[t("div",J,o(e.value.create_time||""),1)]),_:1},8,["label"]),i(s,{label:l(r)("payTime")},{default:a(()=>[t("div",K,o(e.value.pay_time||""),1)]),_:1},8,["label"]),i(s,{label:l(r)("remark")},{default:a(()=>[t("div",Q,o(e.value.remark||""),1)]),_:1},8,["label"]),i(s,{label:l(r)("memberMessage")},{default:a(()=>[t("div",U,o(e.value.member_message||""),1)]),_:1},8,["label"])]),_:1})):E("",!0)]),_:1},8,["model"])),[[g,m.value]])])}}});export{Re as default};