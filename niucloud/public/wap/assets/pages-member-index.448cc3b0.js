import{d as a,r as e,u as o,a as l,c as t,o as r,b as s,s as p,e as g,g as i,f as n,U as d,h as u,i as m,w as b,j as c,k as _,v as f,n as v,l as j,m as x,p as h,q as y,t as S}from"./index-5bb08350.js";import{_ as k}from"./u-loading-page.f2a7c1df.js";import{_ as I}from"./index.1d16afd8.js";import{_ as w}from"./_plugin-vue_export-helper.1b428a4d.js";import"./u-loading-icon.2e89024b.js";import"./u-transition.021c4c1c.js";import"./u-icon.461524ba.js";import"./tabbar.f7ee8f1f.js";import"./u-image.9265f18d.js";import"./u-safe-bottom.6578a4de.js";import"./article.1722ca18.js";import"./app-link.vue_vue_type_script_setup_true_lang.b08a40da.js";import"./u-avatar.0ea3924d.js";const B=w(a({__name:"index",setup(a){const w=e(!0),B=o(),C=e(0),D=l({global:{},value:[]}),E=t((()=>"decorate"==B.mode?B:D));return r((a=>{B.mode=a.mode||"","decorate"==B.mode&&(w.value=!1)})),s((()=>{C.value++,p()})),g((()=>{"decorate"==B.mode?B.init():i({name:"DIY_MEMBER_INDEX"}).then((a=>{if(a.data.value){let e=a.data;D.mode=e.mode;let o=JSON.parse(a.data.value);D.global=o.global,D.value=o.value,D.value.forEach(((a,e)=>{a.pageStyle="",a.pageBgColor&&(a.pageStyle+="background-color:"+a.pageBgColor+";"),a.margin&&(a.pageStyle+="padding-top:"+2*a.margin.top+"rpx;",a.pageStyle+="padding-bottom:"+2*a.margin.bottom+"rpx;",a.pageStyle+="padding-right:"+2*a.margin.both+"rpx;",a.pageStyle+="padding-left:"+2*a.margin.both+"rpx;")})),n({title:D.global.title})}w.value=!1})),d().getMemberInfo()})),(a,e)=>{const o=x(h("u-loading-page"),k),l=x(h("diy-group"),I),t=y;return u(),m(t,null,{default:b((()=>[c(o,{loading:w.value,loadingText:"","bg-color":"#f7f7f7"},null,8,["loading"]),_(c(t,null,{default:b((()=>[c(t,{class:"diy-template-wrap bg-index",style:v({backgroundColor:j(E).global.pageBgColor,minHeight:"calc(100vh - 50px)",backgroundImage:j(E).global.bgUrl?"url("+j(S)(j(E).global.bgUrl)+")":""})},{default:b((()=>[c(l,{data:j(E),pullDownRefresh:C.value},null,8,["data","pullDownRefresh"])])),_:1},8,["style"])])),_:1},512),[[f,!w.value]])])),_:1})}}}),[["__scopeId","data-v-a012177a"]]);export{B as default};