import{d as k,r as u,e as g,v as b,x as e,g as _,y as a,A as m,B as s,u as l,Q as w}from"./base-04829be5.js";/* empty css                   *//* empty css                   *//* empty css                  *//* empty css                     *//* empty css                             */import{t}from"./index-043d021e.js";import{b as B}from"./site-be3599ef.js";import{a as C,E as L}from"./index-20bee9b4.js";import{E as N}from"./index-e9e16697.js";import{E as F}from"./index-eb678249.js";import{E as I}from"./index-b1557f8a.js";import{v as S}from"./directive-013f0a4e.js";const A={class:"break-all"},Q={class:"break-all"},T={class:"dialog-footer"},Y=k({__name:"user-log-detail",setup(U,{expose:v}){const r=u(!1),p=u(!1),o=u([]),h=async()=>{o.value=await(await B(d)).data,p.value=!1};let d=0;return v({showDialog:r,setFormData:async(c=null)=>{p.value=!0,c&&(d=c.id,h())}}),(c,n)=>{const i=C,D=L,E=N,x=F,y=I,V=S;return g(),b(y,{modelValue:r.value,"onUpdate:modelValue":n[1]||(n[1]=f=>r.value=f),title:l(t)("detail"),width:"500px","destroy-on-close":!0},{footer:e(()=>[_("span",T,[a(x,{onClick:n[0]||(n[0]=f=>r.value=!1)},{default:e(()=>[m(s(l(t)("cancel")),1)]),_:1})])]),default:e(()=>[w((g(),b(E,{height:"400px"},{default:e(()=>[a(D,{column:1},{default:e(()=>[a(i,{label:l(t)("username"),"label-align":"right"},{default:e(()=>[m(s(o.value.username),1)]),_:1},8,["label"]),a(i,{label:l(t)("ip"),"label-align":"right"},{default:e(()=>[m(s(o.value.ip),1)]),_:1},8,["label"]),a(i,{label:l(t)("url"),"label-align":"right"},{default:e(()=>[_("span",A,s(o.value.url),1)]),_:1},8,["label"]),a(i,{label:l(t)("type"),"label-align":"right"},{default:e(()=>[m(s(o.value.type),1)]),_:1},8,["label"]),a(i,{label:l(t)("params"),"label-align":"right"},{default:e(()=>[_("span",Q,s(o.value.params),1)]),_:1},8,["label"])]),_:1})]),_:1})),[[V,p.value]])]),_:1},8,["modelValue","title"])}}});export{Y as _};