import{d as e,r as a,o as t,s,e as l,f as r,w as o,j as i,K as n,h as p,A as u,B as d,M as m,C as g,m as x,F as c,k as f,l as b,G as _}from"./index-ee2590bd.js";import{_ as h}from"./u-parse.cd4b5886.js";import{_ as j}from"./u-loading-page.7764425a.js";import{b as y}from"./article.7c006983.js";import{u as v}from"./useShare.72a88f93.js";import"./_plugin-vue_export-helper.1b428a4d.js";import"./u-loading-icon.fa1220a3.js";import"./u-transition.b582b4be.js";import"./wechat.450f97ff.js";const S=e({__name:"detail",setup(e){const{setShare:S,onShareAppMessage:w,onShareTimeline:T}=v();w(),T();let k=a([]),A=a(!0),M={h2:"margin-bottom: 15px;",p:"margin-bottom: 10px;line-height: 1.5;",img:"margin: 10px 0;"};return t((e=>{A.value=!0,y(e.id).then((e=>{k.value=e.data,A.value=!1;let a={title:k.value.title,desc:k.value.intro,url:k.value.image};s({title:k.value.title}),S({wechat:{...a},weapp:{...a}})}))})),(e,a)=>{const t=x,s=c,y=f(b("u-parse"),h),v=f(b("u-loading-page"),j);return l(),r(t,{class:"bg-white"},{default:o((()=>[i(A)?g("",!0):(l(),n(m,{key:0},[p(t,{class:"border-solid border-t-0 border-l-0 border-r-0 border-b-[1px] border-gray-200 p-[10px]"},{default:o((()=>[p(t,{class:"text-[16px]"},{default:o((()=>[u(d(i(k).title),1)])),_:1}),p(t,{class:"flex align-center justify-between text-[12px] text-gray-400 mt-[15px]"},{default:o((()=>[p(s,null,{default:o((()=>[u(d(i(k).create_time),1)])),_:1})])),_:1})])),_:1}),p(t,{class:"mx-[10px] my-[10px] bg-gray-100 p-[8px] text-[14px] rounded-[5px] leading-[1.3]"},{default:o((()=>[u(d(i(_)("abstract"))+"："+d(i(k).summary),1)])),_:1}),p(t,{class:"px-[10px] pd-[10px]"},{default:o((()=>[p(y,{content:i(k).content,tagStyle:i(M)},null,8,["content","tagStyle"])])),_:1})],64)),p(v,{"bg-color":"rgb(248,248,248)",loading:i(A),fontSize:"16",color:"#333",loadingText:i(_)("loadingText")},null,8,["loading","loadingText"])])),_:1})}}});export{S as default};
