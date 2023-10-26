import{e as N,i as h,C as A,aA as z,a8 as G,a9 as U,b as V,d as O}from"./index-72686045.js";import{B as d,a0 as a,F as W,aH as g,X as K,ac as j,o as $,a as q,J,i as Q,aL as Z,w as P,az as F,N as v,d as ee,q as oe,g as Y,P as te}from"./runtime-core.esm-bundler-67034826.js";import{u as x}from"./base-0e92f4db.js";import{t as ne}from"./event-a537c4cb.js";import{a as se}from"./focus-trap-83769a43.js";var l=(e=>(e[e.TEXT=1]="TEXT",e[e.CLASS=2]="CLASS",e[e.STYLE=4]="STYLE",e[e.PROPS=8]="PROPS",e[e.FULL_PROPS=16]="FULL_PROPS",e[e.HYDRATE_EVENTS=32]="HYDRATE_EVENTS",e[e.STABLE_FRAGMENT=64]="STABLE_FRAGMENT",e[e.KEYED_FRAGMENT=128]="KEYED_FRAGMENT",e[e.UNKEYED_FRAGMENT=256]="UNKEYED_FRAGMENT",e[e.NEED_PATCH=512]="NEED_PATCH",e[e.DYNAMIC_SLOTS=1024]="DYNAMIC_SLOTS",e[e.HOISTED=-1]="HOISTED",e[e.BAIL=-2]="BAIL",e))(l||{});function re(e){return a(e)&&e.type===W}function ue(e){return a(e)&&e.type===g}function ve(e){return a(e)&&!re(e)&&!ue(e)}const ye=e=>{if(!a(e))return{};const s=e.props||{},t=(a(e.type)?e.type.props:void 0)||{},o={};return Object.keys(t).forEach(n=>{K(t[n],"default")&&(o[n]=t[n].default)}),Object.keys(s).forEach(n=>{o[j(n)]=s[n]}),o},Te=e=>{if(!d(e)||e.length>1)throw new Error("expect to receive a single Vue element child");return e[0]},y=e=>{const s=d(e)?e:[e],t=[];return s.forEach(o=>{var n;d(o)?t.push(...y(o)):a(o)&&d(o.children)?t.push(...y(o.children)):(t.push(o),a(o)&&((n=o.component)!=null&&n.subTree)&&t.push(...y(o.component.subTree)))}),t},Se=(e,s,t)=>{let o={offsetX:0,offsetY:0};const n=i=>{const c=i.clientX,f=i.clientY,{offsetX:p,offsetY:E}=o,m=e.value.getBoundingClientRect(),T=m.left,S=m.top,D=m.width,_=m.height,H=document.documentElement.clientWidth,k=document.documentElement.clientHeight,B=-T+p,I=-S+E,R=H-T-D+p,X=k-S-_+E,M=L=>{const b=Math.min(Math.max(p+L.clientX-c,B),R),w=Math.min(Math.max(E+L.clientY-f,I),X);o={offsetX:b,offsetY:w},e.value.style.transform=`translate(${N(b)}, ${N(w)})`},C=()=>{document.removeEventListener("mousemove",M),document.removeEventListener("mouseup",C)};document.addEventListener("mousemove",M),document.addEventListener("mouseup",C)},u=()=>{s.value&&e.value&&s.value.addEventListener("mousedown",n)},r=()=>{s.value&&e.value&&s.value.removeEventListener("mousedown",n)};$(()=>{q(()=>{t.value?u():r()})}),J(()=>{r()})},Me=e=>{Q(e)||ne("[useLockscreen]","You need to pass a ref param to this function");const s=x("popup"),t=Z(()=>s.bm("parent","hidden"));if(!h||A(document.body,t.value))return;let o=0,n=!1,u="0";const r=()=>{setTimeout(()=>{U(document.body,t.value),n&&(document.body.style.width=u)},200)};P(e,i=>{if(!i){r();return}n=!A(document.body,t.value),n&&(u=document.body.style.width),o=se(s.namespace.value);const c=document.documentElement.clientHeight<document.body.scrollHeight,f=z(document.body,"overflowY");o>0&&(c||f==="scroll")&&n&&(document.body.style.width=`calc(100% - ${o}px)`),G(document.body,t.value)}),F(()=>r())},ae=e=>{if(!e)return{onClick:v,onMousedown:v,onMouseup:v};let s=!1,t=!1;return{onClick:r=>{s&&t&&e(r),s=t=!1},onMousedown:r=>{s=r.target===r.currentTarget},onMouseup:r=>{t=r.target===r.currentTarget}}},ie=V({mask:{type:Boolean,default:!0},customMaskEvent:{type:Boolean,default:!1},overlayClass:{type:O([String,Array,Object])},zIndex:{type:O([String,Number])}}),ce={click:e=>e instanceof MouseEvent};var me=ee({name:"ElOverlay",props:ie,emits:ce,setup(e,{slots:s,emit:t}){const o=x("overlay"),n=c=>{t("click",c)},{onClick:u,onMousedown:r,onMouseup:i}=ae(e.customMaskEvent?void 0:n);return()=>e.mask?oe("div",{class:[o.b(),e.overlayClass],style:{zIndex:e.zIndex},onClick:u,onMousedown:r,onMouseup:i},[Y(s,"default")],l.STYLE|l.CLASS|l.PROPS,["onClick","onMouseup","onMousedown"]):te("div",{class:e.overlayClass,style:{zIndex:e.zIndex,position:"fixed",top:"0px",right:"0px",bottom:"0px",left:"0px"}},[Y(s,"default")])}});const Ce=me;export{Ce as E,l as P,ve as a,Me as b,ae as c,Te as e,y as f,ye as g,re as i,Se as u};
