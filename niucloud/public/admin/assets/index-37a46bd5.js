import{v as N,T as A}from"./event-10eba222.js";import{b as I,a4 as h,E as k,a8 as V}from"./index-2fcd1254.js";import{k as $,d as B,K as D,b as F,r as M,c as p,e as o,v as l,x as u,Q as P,g as b,n as a,u as e,Z as z,H as n,f as r,h as g,A as C,B as f,F as H,y as K,_ as O,l as Q}from"./base-06478700.js";const Z=["light","dark"],_=I({title:{type:String,default:""},description:{type:String,default:""},type:{type:String,values:$(h),default:"info"},closable:{type:Boolean,default:!0},closeText:{type:String,default:""},showIcon:Boolean,center:Boolean,effect:{type:String,values:Z,default:"light"}}),j={close:i=>i instanceof MouseEvent},q=B({name:"ElAlert"}),G=B({...q,props:_,emits:j,setup(i,{emit:E}){const c=i,{Close:S}=V,d=D(),t=F("alert"),m=M(!0),y=p(()=>h[c.type]),T=p(()=>[t.e("icon"),{[t.is("big")]:!!c.description||!!d.default}]),w=p(()=>({[t.is("bold")]:c.description||d.default})),v=s=>{m.value=!1,E("close",s)};return(s,L)=>(o(),l(A,{name:e(t).b("fade"),persisted:""},{default:u(()=>[P(b("div",{class:a([e(t).b(),e(t).m(s.type),e(t).is("center",s.center),e(t).is(s.effect)]),role:"alert"},[s.showIcon&&e(y)?(o(),l(e(k),{key:0,class:a(e(T))},{default:u(()=>[(o(),l(z(e(y))))]),_:1},8,["class"])):n("v-if",!0),b("div",{class:a(e(t).e("content"))},[s.title||s.$slots.title?(o(),r("span",{key:0,class:a([e(t).e("title"),e(w)])},[g(s.$slots,"title",{},()=>[C(f(s.title),1)])],2)):n("v-if",!0),s.$slots.default||s.description?(o(),r("p",{key:1,class:a(e(t).e("description"))},[g(s.$slots,"default",{},()=>[C(f(s.description),1)])],2)):n("v-if",!0),s.closable?(o(),r(H,{key:2},[s.closeText?(o(),r("div",{key:0,class:a([e(t).e("close-btn"),e(t).is("customed")]),onClick:v},f(s.closeText),3)):(o(),l(e(k),{key:1,class:a(e(t).e("close-btn")),onClick:v},{default:u(()=>[K(e(S))]),_:1},8,["class"]))],64)):n("v-if",!0)],2)],2),[[N,m.value]])]),_:3},8,["name"]))}});var J=O(G,[["__file","/home/runner/work/element-plus/element-plus/packages/components/alert/src/alert.vue"]]);const X=Q(J);export{X as E};
