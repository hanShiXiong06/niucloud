import{d as U,r as _,e as r,f as u,g as e,B as k,u as f,y as i,x as j,i as q,Q as x,F as g,z as B,n as z,A as G,aP as H,aQ as J}from"./base-06478700.js";/* empty css                   *//* empty css                  *//* empty css                */import{_ as K}from"./index-981b0207.js";/* empty css                    */import{d as N,v as C}from"./event-10eba222.js";import{t as p}from"./index-81ed253c.js";import{b as X,c as Y}from"./wechat-b2c05bef.js";import{_ as $}from"./menu-form.vue_vue_type_script_setup_true_lang-676bd554.js";import{u as Z,a as ee}from"./vue-router-d09a2c28.js";import{E as te}from"./index-01f6e375.js";import{a as ae}from"./index-b52d0f2a.js";import{a as oe,E as se}from"./index-0d66b73c.js";import{E as le}from"./index-e10fccde.js";import{E as ne}from"./index-c2f001d3.js";import{v as re}from"./directive-cb2d3366.js";import{_ as ue}from"./_plugin-vue_export-helper-c27b6911.js";import"./common-92a35870.js";import"./index-2fcd1254.js";import"./index-adb89d14.js";import"./el-main-9a0960e7.js";import"./index-6b67c4ac.js";import"./el-overlay-42a687c6.js";import"./index-9fe5de95.js";import"./focus-trap-3e826cdc.js";import"./index-f27d6ce0.js";import"./index-818c0ce2.js";import"./el-form-item-314d006d.js";/* empty css                 */import"./el-tooltip-58212670.js";import"./index-2a269c7c.js";import"./index-e4abfaa5.js";import"./index-b68e8463.js";import"./index-9ee9102c.js";/* empty css                 */import"./index-6290cf08.js";import"./validator-6e9db238.js";import"./strings-fe930bc4.js";const T=w=>(H("data-v-b88524b1"),w=w(),J(),w),ie={class:"main-container p-5"},ce={class:"flex justify-between items-center mb-[20px]"},pe={class:"text-[20px]"},me={class:"flex"},de={class:"preview-wrap w-[300px] h-[550px] mr-[16px] bg-overlay rounded-md flex flex-col justify-between"},ve=T(()=>e("div",{class:"head w-full h-[70px]"},null,-1)),_e={class:"menu-list h-[70px] flex border-t border-color"},fe={class:"py-[15px]"},be={class:"flex h-full px-[10px] items-center justify-center border-r border-color"},he={class:"flex-1 flex w-0"},xe=["onClick"],ge={class:"menu-name px-[10px] border-r border-color w-full leading-[40px] text-base truncate text-center"},we=T(()=>e("div",{class:"active-shade"},null,-1)),ye={class:"sub-menu-wrap w-full bg-overlay border border-color rounded"},ke=["onClick"],Be={class:"menu-name w-full text-base truncate text-center"},Ce=T(()=>e("div",{class:"active-shade"},null,-1)),Ee=["onClick"],je={class:"flex-1"},Te={key:1,class:"py-[20px] leading"},Me={class:"fixed-footer-wrap"},Se={class:"fixed-footer"},Ve=U({__name:"menu",setup(w){const D=Z().meta.title,P=ee(),d=_(!0),t=_([]),o=_(0),l=_(-1),m=_(null);let b=_("/website/channel/wechat/menu");const R=a=>{P.push({path:b.value})};X().then(a=>{t.value=a.data,d.value=!1});const F=()=>{t.value.push({name:"菜单名称",type:"view",url:"",appid:"",pagepath:"",sub_button:[]}),M(t.value.length-1)},I=a=>{!t.value[a].sub_button&&(t.value[a].sub_button=[]),t.value[a].sub_button.push({name:"子菜单名称",type:"view",url:"",appid:"",pagepath:""}),S(a,t.value[a].sub_button.length-1)},M=a=>{o.value=a,l.value=-1},S=(a,c)=>{o.value=a,l.value=c},V=()=>{te.confirm(p("deleteMemuTips"),p("warning"),{confirmButtonText:p("confirm"),cancelButtonText:p("cancel"),type:"warning"}).then(()=>{l.value!=-1?(t.value[o.value].sub_button.splice(l.value,1),l.value=t.value[o.value].sub_button.length-1,l.value==-1&&Object.assign(t.value[o.value],{type:"view",url:"",appid:"",pagepath:""})):(t.value.splice(o.value,1),t.value.length&&(o.value=t.value.length-1))})},A=async()=>{if(!m.value||!m.value){ae.error(p("menusEmptyTips"));return}for(let a=0;a<(m==null?void 0:m.value.length);a++){const c=m.value[a];if(!await c.validate()){o.value=c.index,l.value=c.subIndex;break}}d.value||(d.value=!0,Y({button:t.value}).then(()=>{d.value=!1}).catch(()=>{d.value=!1}))};return(a,c)=>{const y=oe,L=se,E=K,Q=le,W=ne,O=re;return r(),u(g,null,[e("div",ie,[e("div",ce,[e("span",pe,k(f(D)),1)]),i(L,{modelValue:f(b),"onUpdate:modelValue":c[0]||(c[0]=n=>q(b)?b.value=n:b=n),class:"demo-tabs",onTabChange:R},{default:j(()=>[i(y,{label:f(p)("wechatAccessFlow"),name:"/website/channel/wechat"},null,8,["label"]),i(y,{label:f(p)("customMenu"),name:"/website/channel/wechat/menu"},null,8,["label"]),i(y,{label:f(p)("wechatTemplate"),name:"/website/channel/wechat/message"},null,8,["label"])]),_:1},8,["modelValue"]),x((r(),u("div",me,[e("div",de,[ve,e("div",_e,[e("div",fe,[e("div",be,[i(E,{name:"iconfont-iconjianpan",size:"20px",color:"#b1b2b3"})])]),e("div",he,[(r(!0),u(g,null,B(t.value,(n,s)=>(r(),u("div",{class:z(["menu-item py-[15px] flex items-center justify-center cursor-pointer",{"size-1":t.value.length==1,"size-2-3":t.value.length>1,active:s==o.value,curr:s==o.value&&l.value==-1}]),key:s,onClick:h=>M(s)},[e("div",ge,k(n.name),1),we,e("div",ye,[(r(!0),u(g,null,B(n.sub_button,(h,v)=>(r(),u("div",{class:z(["menu-item h-[50px] p-[10px] border-b border-color flex items-center justify-center cursor-pointer",{curr:v==l.value}]),key:v,onClick:N(Ne=>S(s,v),["stop"])},[e("div",Be,k(h.name),1),Ce],10,ke))),128)),x(e("div",{class:"add-menu flex items-center justify-center flex-1 cursor-pointer menu-item h-[50px]",onClick:N(h=>I(s),["stop"])},[i(E,{name:"element-Plus"})],8,Ee),[[C,!n.sub_button||n.sub_button.length<5]])])],10,xe))),128)),x(e("div",{class:"add-menu flex items-center justify-center flex-1 cursor-pointer menu-item",onClick:F},[i(E,{name:"element-Plus"})],512),[[C,t.value.length<3]])])])]),e("div",je,[i(Q,{class:"box-card !border-none h-auto",shadow:"never"},{default:j(()=>[t.value.length?(r(!0),u(g,{key:0},B(t.value,(n,s)=>(r(),u("div",{key:s},[x(e("div",null,[i($,{data:n,onDelete:V,index:s,ref_for:!0,ref_key:"formRef",ref:m},null,8,["data","index"])],512),[[C,s==o.value&&l.value==-1]]),(r(!0),u(g,null,B(n.sub_button,(h,v)=>(r(),u("div",{key:v},[x(e("div",null,[i($,{data:h,onDelete:V,index:s,"sub-index":v,ref_for:!0,ref_key:"formRef",ref:m},null,8,["data","index","sub-index"])],512),[[C,s==o.value&&v==l.value]])]))),128))]))),128)):(r(),u("div",Te,"尚未添加自定义菜单，点击左侧添加菜单为公众号创建菜单栏。"))]),_:1})])])),[[O,d.value]])]),e("div",Me,[e("div",Se,[i(W,{type:"primary",loading:d.value,onClick:c[1]||(c[1]=n=>A())},{default:j(()=>[G(k(f(p)("save")),1)]),_:1},8,["loading"])])])],64)}}});const xt=ue(Ve,[["__scopeId","data-v-b88524b1"]]);export{xt as default};