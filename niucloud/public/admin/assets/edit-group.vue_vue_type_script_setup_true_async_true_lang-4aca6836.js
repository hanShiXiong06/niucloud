import{d as q,r as p,w as K,ap as H,R as L,c as Q,e as D,v as N,x as c,g as E,y as i,A as w,B as x,u as s,Q as z}from"./base-04829be5.js";/* empty css                   *//* empty css                   *//* empty css                     *//* empty css                     *//* empty css                *//* empty css                    *//* empty css                  *//* empty css                 */import{t as a}from"./index-043d021e.js";import{t as J}from"./sys-f9859bed.js";import{j as W,k as X,m as Y}from"./site-be3599ef.js";import{h as Z}from"./storage-1a3ddb14.js";import{E as ee}from"./index-db9b8d96.js";import{a as le,E as oe}from"./index-6bd50bb5.js";import{E as te}from"./index-c3b3b83a.js";import{E as ae}from"./index-eb678249.js";import{E as re}from"./index-f29e4e06.js";import{E as se}from"./index-e9e16697.js";import{E as ne}from"./index-b1557f8a.js";import{v as ue}from"./directive-013f0a4e.js";const ie={class:"flex items-center justify-between w-11/12"},ce={class:"dialog-footer"},Be=q({__name:"edit-group",emits:["complete"],setup(de,{expose:O,emit:R}){const _=p(!1),d=p(!1);let h="";const v=p(!0),f=p([]);J("site").then(l=>{f.value=l.data});const g=p(!1),b=p(!1),m=p(null);K(g,()=>{g.value?m.value.setCheckedNodes(H(f.value)):m.value.setCheckedNodes([])});const S=Z(l=>{t.group_roles=m.value.getCheckedKeys()}),B=()=>{v.value?(k(f.value),v.value=!1):(F(f.value),v.value=!0)},F=l=>{Object.keys(l).forEach(e=>{m.value.store.nodesMap[l[e].menu_key].expanded=!0,l[e].children&&l[e].children.length>0&&k(l[e].children)})},k=l=>{Object.keys(l).forEach(e=>{m.value.store.nodesMap[l[e].menu_key].expanded=!1,l[e].children&&l[e].children.length>0&&k(l[e].children)})},V={group_id:0,group_name:"",group_desc:"",group_roles:[]},t=L({...V}),C=p(),G=Q(()=>({group_name:[{required:!0,message:a("groupNamePlaceholder"),trigger:"blur"}],group_roles:[{required:!0,validator:(l,e,o)=>{e.length?o():o(new Error(a("groupRolesPlaceholder")))},trigger:"blur"}]})),U=async l=>{if(d.value||!l)return;const e=t.group_id?W:X;await l.validate(async o=>{if(o){d.value=!0;const n=Object.assign({},t);n.group_roles=n.group_roles.concat(m.value.getHalfCheckedKeys()),e(n).then(r=>{d.value=!1,_.value=!1,g.value=!1,R("complete")}).catch(()=>{d.value=!1})}})},A=async(l=null)=>{if(d.value=!0,g.value=!1,Object.assign(t,V),h=a("addGroup"),l){h=a("updateGroup");const e=await(await Y(l.group_id)).data;Object.keys(t).forEach(o=>{if(e[o]!=null)if(o=="group_roles"){e.group_roles;var n=[];Object.keys(e.group_roles).forEach(r=>{j(e.group_roles[r],f.value,n)}),t[o]=n}else t[o]=e[o]})}d.value=!1};function j(l,e,o){Object.keys(e).forEach(n=>{let r=e[n];r.menu_key==l?(!r.children||r.children.length==0)&&o.push(r.menu_key):r.children&&r.children.length>0&&j(l,r.children,o)})}return O({showDialog:_,setFormData:A}),(l,e)=>{const o=ee,n=le,r=te,y=ae,P=re,T=se,I=oe,M=ne,$=ue;return D(),N(M,{modelValue:_.value,"onUpdate:modelValue":e[7]||(e[7]=u=>_.value=u),title:s(h),width:"500px","destroy-on-close":!0},{footer:c(()=>[E("span",ce,[i(y,{onClick:e[5]||(e[5]=u=>_.value=!1)},{default:c(()=>[w(x(s(a)("cancel")),1)]),_:1}),i(y,{type:"primary",loading:d.value,onClick:e[6]||(e[6]=u=>U(C.value))},{default:c(()=>[w(x(s(a)("confirm")),1)]),_:1},8,["loading"])])]),default:c(()=>[z((D(),N(I,{model:t,"label-width":"90px",ref_key:"formRef",ref:C,rules:s(G),class:"page-form"},{default:c(()=>[i(n,{label:s(a)("groupName"),prop:"group_name"},{default:c(()=>[i(o,{modelValue:t.group_name,"onUpdate:modelValue":e[0]||(e[0]=u=>t.group_name=u),placeholder:s(a)("groupNamePlaceholder"),clearable:"",disabled:t.uid,class:"input-width",maxlength:"20","show-word-limit":!0},null,8,["modelValue","placeholder","disabled"])]),_:1},8,["label"]),i(n,{label:s(a)("groupDesc"),prop:"group_desc"},{default:c(()=>[i(o,{modelValue:t.group_desc,"onUpdate:modelValue":e[1]||(e[1]=u=>t.group_desc=u),type:"textarea",rows:"4",clearable:"",placeholder:s(a)("groupDescPlaceholder"),class:"input-width",maxlength:"100"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),i(n,{label:s(a)("permission"),prop:"group_roles"},{default:c(()=>[E("div",ie,[E("div",null,[i(r,{modelValue:g.value,"onUpdate:modelValue":e[2]||(e[2]=u=>g.value=u),label:s(a)("selectAll")},null,8,["modelValue","label"]),i(r,{modelValue:b.value,"onUpdate:modelValue":e[3]||(e[3]=u=>b.value=u),label:s(a)("checkStrictly")},null,8,["modelValue","label"])]),i(y,{link:"",type:"primary",onClick:e[4]||(e[4]=u=>B())},{default:c(()=>[w(x(s(a)("foldText")),1)]),_:1})]),i(T,{height:"35vh",class:"w-full"},{default:c(()=>[i(P,{data:f.value,props:{label:"menu_name"},"default-checked-keys":t.group_roles,"check-strictly":b.value,"show-checkbox":"","default-expand-all":"",onCheckChange:s(S),"node-key":"menu_key",ref_key:"treeRef",ref:m},null,8,["data","default-checked-keys","check-strictly","onCheckChange"])]),_:1})]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[$,d.value]])]),_:1},8,["modelValue","title"])}}});export{Be as _};
