import{g as C,r as h,a4 as N,j,m as f,D as V,E as l,q as p,F as s,K as _,L as m,u as o,a1 as I,n as E,I as L,J as K}from"./base-45eb5090.js";/* empty css                   */import{E as O}from"./el-overlay-616d6124.js";/* empty css                  *//* empty css                     *//* empty css                 */import{E as P,b as S}from"./el-radio-2719e44c.js";import{t as r}from"./index-3694a2b2.js";import{a as $}from"./notice-fed9c625.js";import{a as q,E as G}from"./index-2bfbe5a7.js";import{E as J}from"./index-4ce9333e.js";import{E as T}from"./index-25c37860.js";import{v as z}from"./directive-9f485fe5.js";const A={class:"input-width"},H={class:"input-width"},M={class:"input-width"},Q={class:"dialog-footer"},ue=C({__name:"notice-wechat",emits:["complete"],setup(W,{expose:D,emit:y}){const u=h(!1),n=h(!0),v={is_wechat:0,key:"",name:"",title:"",type:"",content:[],first:"",remark:"",temp_key:"",wechat_first:"",wechat_remark:""},t=N({...v}),w=h(),x=j(()=>({})),F=async a=>{n.value||!a||await a.validate(async e=>{if(e){n.value=!0;const d=t;d.status=d.is_wechat,$(d).then(b=>{n.value=!1,u.value=!1,y("complete")}).catch(()=>{n.value=!1})}})};return D({showDialog:u,setFormData:async(a=null)=>{n.value=!0,Object.assign(t,v),a&&(Object.keys(t).forEach(e=>{a[e]!=null&&(t[e]=a[e]),a.wechat&&a.wechat[e]!=null&&(t[e]=a.wechat[e])}),a.wechat_first||(t.wechat_first=a.first),a.wechat_remark||(t.wechat_remark=a.remark)),n.value=!1}}),(a,e)=>{const d=P,b=S,c=q,g=J,B=G,k=T,R=O,U=z;return f(),V(R,{modelValue:u.value,"onUpdate:modelValue":e[5]||(e[5]=i=>u.value=i),title:o(r)("noticeSetting"),width:"550px","destroy-on-close":!0},{footer:l(()=>[p("span",Q,[s(k,{onClick:e[3]||(e[3]=i=>u.value=!1)},{default:l(()=>[_(m(o(r)("cancel")),1)]),_:1}),s(k,{type:"primary",loading:n.value,onClick:e[4]||(e[4]=i=>F(w.value))},{default:l(()=>[_(m(o(r)("confirm")),1)]),_:1},8,["loading"])])]),default:l(()=>[I((f(),V(B,{model:t,"label-width":"110px",ref_key:"formRef",ref:w,rules:o(x),class:"page-form"},{default:l(()=>[s(c,{label:o(r)("status")},{default:l(()=>[s(b,{modelValue:t.is_wechat,"onUpdate:modelValue":e[0]||(e[0]=i=>t.is_wechat=i)},{default:l(()=>[s(d,{label:1},{default:l(()=>[_(m(o(r)("startUsing")),1)]),_:1}),s(d,{label:0},{default:l(()=>[_(m(o(r)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),s(c,{label:o(r)("name")},{default:l(()=>[p("div",A,m(t.name),1)]),_:1},8,["label"]),s(c,{label:o(r)("tempKey")},{default:l(()=>[p("div",H,m(t.temp_key),1)]),_:1},8,["label"]),s(c,{label:o(r)("first"),prop:"first"},{default:l(()=>[s(g,{modelValue:t.wechat_first,"onUpdate:modelValue":e[1]||(e[1]=i=>t.wechat_first=i),placeholder:o(r)("firstPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),s(c,{label:o(r)("content")},{default:l(()=>[p("div",M,[(f(!0),E(L,null,K(t.content,(i,Y)=>(f(),E("div",null,m(i[0])+"："+m(i[1]),1))),256))])]),_:1},8,["label"]),s(c,{label:o(r)("remark"),prop:"remark"},{default:l(()=>[s(g,{modelValue:t.wechat_remark,"onUpdate:modelValue":e[2]||(e[2]=i=>t.wechat_remark=i),placeholder:o(r)("remarkPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[U,n.value]])]),_:1},8,["modelValue","title"])}}});export{ue as _};