/* empty css             *//* empty css                   *//* empty css                  */import"./el-overlay-f7f710bd.js";/* empty css                  *//* empty css                     *//* empty css                 */import{E as j,b as C}from"./el-radio-c9a1047c.js";import{t as o}from"./index-5f4ce139.js";import{a as L}from"./message-aca08575.js";import{a as N,E as I}from"./index-624573cc.js";import{E as M}from"./index-95693143.js";import{E as O}from"./index-4862d1b3.js";import{E as P}from"./index-4683bff4.js";import{v as S}from"./directive-a07a10ed.js";import{d as $,r as v,M as q,c as G,b as c,m as V,p as a,f,q as s,v as p,x as m,u as l,L as K,e as E,F as T,t as z}from"./runtime-core.esm-bundler-7c3fd514.js";const A={class:"input-width"},H={class:"input-width"},J={class:"input-width"},Q={class:"dialog-footer"},_e=$({__name:"message-wechat",emits:["complete"],setup(W,{expose:y,emit:D}){const d=v(!1),n=v(!0),h={status:0,key:"",name:"",title:"",type:"",content:[],first:"",remark:"",temp_key:"",wechat_first:"",wechat_remark:""},e=q({...h}),w=v(),x=G(()=>({})),F=async i=>{n.value||!i||await i.validate(async t=>{t&&(n.value=!0,L(e).then(b=>{n.value=!1,d.value=!1,D("complete")}).catch(()=>{n.value=!1,d.value=!1}))})};return y({showDialog:d,setFormData:async(i=null)=>{n.value=!0,Object.assign(e,h),i&&(Object.keys(e).forEach(t=>{i[t]!=null&&(e[t]=i[t]),i.wechat_json[t]!=null&&(e[t]=i.wechat_json[t])}),e.wechat_first||(e.wechat_first=e.first),e.wechat_remark||(e.wechat_remark=e.remark)),n.value=!1}}),(i,t)=>{const _=j,b=C,u=N,g=M,B=I,k=O,R=P,U=S;return c(),V(R,{modelValue:d.value,"onUpdate:modelValue":t[5]||(t[5]=r=>d.value=r),title:l(o)("messageSetting"),width:"550px","destroy-on-close":!0},{footer:a(()=>[f("span",Q,[s(k,{onClick:t[3]||(t[3]=r=>d.value=!1)},{default:a(()=>[p(m(l(o)("cancel")),1)]),_:1}),s(k,{type:"primary",loading:n.value,onClick:t[4]||(t[4]=r=>F(w.value))},{default:a(()=>[p(m(l(o)("confirm")),1)]),_:1},8,["loading"])])]),default:a(()=>[K((c(),V(B,{model:e,"label-width":"110px",ref_key:"formRef",ref:w,rules:l(x),class:"page-form"},{default:a(()=>[s(u,{label:l(o)("status")},{default:a(()=>[s(b,{modelValue:e.status,"onUpdate:modelValue":t[0]||(t[0]=r=>e.status=r)},{default:a(()=>[s(_,{label:1},{default:a(()=>[p(m(l(o)("startUsing")),1)]),_:1}),s(_,{label:0},{default:a(()=>[p(m(l(o)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),s(u,{label:l(o)("name")},{default:a(()=>[f("div",A,m(e.name),1)]),_:1},8,["label"]),s(u,{label:l(o)("tempKey")},{default:a(()=>[f("div",H,m(e.temp_key),1)]),_:1},8,["label"]),s(u,{label:l(o)("first"),prop:"first"},{default:a(()=>[s(g,{modelValue:e.wechat_first,"onUpdate:modelValue":t[1]||(t[1]=r=>e.wechat_first=r),placeholder:l(o)("firstPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),s(u,{label:l(o)("content")},{default:a(()=>[f("div",J,[(c(!0),E(T,null,z(e.content,(r,Y)=>(c(),E("div",null,m(r[0])+"："+m(r[1]),1))),256))])]),_:1},8,["label"]),s(u,{label:l(o)("remark"),prop:"remark"},{default:a(()=>[s(g,{modelValue:e.wechat_remark,"onUpdate:modelValue":t[2]||(t[2]=r=>e.wechat_remark=r),placeholder:l(o)("remarkPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[U,n.value]])]),_:1},8,["modelValue","title"])}}});export{_e as _};
