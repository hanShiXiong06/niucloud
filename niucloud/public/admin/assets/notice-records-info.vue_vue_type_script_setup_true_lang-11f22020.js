import{d as V,r as p,R as B,c as F,e as m,v as h,x as s,g as n,y as i,A as C,B as o,u as t,Q as N,f,H as u}from"./base-04829be5.js";/* empty css                   *//* empty css                   *//* empty css                  *//* empty css                     */import{t as a}from"./index-043d021e.js";import{a as R,E as T}from"./index-6bd50bb5.js";import{E as j}from"./index-eb678249.js";import{E as I}from"./index-b1557f8a.js";import{v as O}from"./directive-013f0a4e.js";const A={class:"input-width"},H={key:0},K={key:1},L={key:2},Q={class:"input-width"},S={class:"input-width"},U={class:"input-width"},$={class:"input-width"},q={class:"dialog-footer"},oe=V({__name:"notice-records-info",emits:["complete"],setup(z,{expose:b,emit:G}){const c=p(!1),_=p(!0),v={create_time:0,message_data:"",message_key:"",message_type:"",name:"",nickname:"",receiver:""},e=B({...v}),y=p(),w=F(()=>({}));return b({showDialog:c,setFormData:async(d=null)=>{_.value=!0,Object.assign(e,v),d&&Object.keys(e).forEach(l=>{d[l]!=null&&(e[l]=d[l])}),_.value=!1}}),(d,l)=>{const r=R,k=T,D=j,E=I,x=O;return m(),h(E,{modelValue:c.value,"onUpdate:modelValue":l[1]||(l[1]=g=>c.value=g),title:t(a)("messageInfo"),width:"550px","destroy-on-close":!0},{footer:s(()=>[n("span",q,[i(D,{type:"primary",onClick:l[0]||(l[0]=g=>c.value=!1)},{default:s(()=>[C(o(t(a)("confirm")),1)]),_:1})])]),default:s(()=>[N((m(),h(k,{model:e,"label-width":"110px",ref_key:"formRef",ref:y,rules:t(w),class:"page-form"},{default:s(()=>[i(r,{label:t(a)("messageKey")},{default:s(()=>[n("div",A,o(e.name),1)]),_:1},8,["label"]),i(r,{label:t(a)("messageType")},{default:s(()=>[e.message_type=="sms"?(m(),f("div",H,o(t(a)("sms")),1)):u("",!0),e.message_type=="wechat"?(m(),f("div",K,o(t(a)("wechat")),1)):u("",!0),e.message_type=="weapp"?(m(),f("div",L,o(t(a)("weapp")),1)):u("",!0)]),_:1},8,["label"]),i(r,{label:t(a)("messageData")},{default:s(()=>[n("div",Q,o(e.message_data),1)]),_:1},8,["label"]),i(r,{label:t(a)("nickname")},{default:s(()=>[n("div",S,o(e.nickname),1)]),_:1},8,["label"]),i(r,{label:t(a)("receiver")},{default:s(()=>[n("div",U,o(e.receiver),1)]),_:1},8,["label"]),i(r,{label:t(a)("createTime")},{default:s(()=>[n("div",$,o(e.create_time),1)]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[x,_.value]])]),_:1},8,["modelValue","title"])}}});export{oe as _};