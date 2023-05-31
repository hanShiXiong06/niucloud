import{c as W,v as x,d as Z,T as G,r as z}from"./event-ff03ec12.js";import{b as K,A as J,d as S,u as Q,av as R,ag as H,E as P,_ as X,W as Y,i as ee,ai as F,aj as te}from"./base-962c0c23.js";import{E as N,b as oe}from"./focus-trap-b8b5a003.js";import{d as j,r as ne,c as C,o as se,b as m,m as T,p as _,L as $,f as h,n as v,u as i,h as A,U as ie,C as w,x as D,g as ae,e as V,F as le,q as O,a0 as I,j as re}from"./runtime-core.esm-bundler-dc7a07d7.js";const q=["success","info","warning","error"],ce=K({customClass:{type:String,default:""},dangerouslyUseHTMLString:{type:Boolean,default:!1},duration:{type:Number,default:4500},icon:{type:J},id:{type:String,default:""},message:{type:S([String,Object]),default:""},offset:{type:Number,default:0},onClick:{type:S(Function),default:()=>{}},onClose:{type:S(Function),required:!0},position:{type:String,values:["top-right","top-left","bottom-right","bottom-left"],default:"top-right"},showClose:{type:Boolean,default:!0},title:{type:String,default:""},type:{type:String,values:[...q,""],default:""},zIndex:{type:Number,default:0}}),ue={destroy:()=>!0},fe=["id"],de=["textContent"],pe={key:0},me=["innerHTML"],ve=j({name:"ElNotification"}),ye=j({...ve,props:ce,emits:ue,setup(e,{expose:s}){const o=e,n=Q("notification"),{Close:u}=R,a=ne(!1);let d;const p=C(()=>{const t=o.type;return t&&H[o.type]?n.m(t):""}),r=C(()=>o.type&&H[o.type]||o.icon),c=C(()=>o.position.endsWith("right")?"right":"left"),l=C(()=>o.position.startsWith("top")?"top":"bottom"),g=C(()=>({[l.value]:`${o.offset}px`,zIndex:o.zIndex}));function f(){o.duration>0&&({stop:d}=Y(()=>{a.value&&b()},o.duration))}function L(){d==null||d()}function b(){a.value=!1}function U({code:t}){t===N.delete||t===N.backspace?L():t===N.esc?a.value&&b():f()}return se(()=>{f(),a.value=!0}),W(document,"keydown",U),s({visible:a,close:b}),(t,E)=>(m(),T(G,{name:i(n).b("fade"),onBeforeLeave:t.onClose,onAfterLeave:E[1]||(E[1]=M=>t.$emit("destroy")),persisted:""},{default:_(()=>[$(h("div",{id:t.id,class:v([i(n).b(),t.customClass,i(c)]),style:A(i(g)),role:"alert",onMouseenter:L,onMouseleave:f,onClick:E[0]||(E[0]=(...M)=>t.onClick&&t.onClick(...M))},[i(r)?(m(),T(i(P),{key:0,class:v([i(n).e("icon"),i(p)])},{default:_(()=>[(m(),T(ie(i(r))))]),_:1},8,["class"])):w("v-if",!0),h("div",{class:v(i(n).e("group"))},[h("h2",{class:v(i(n).e("title")),textContent:D(t.title)},null,10,de),$(h("div",{class:v(i(n).e("content")),style:A(t.title?void 0:{margin:0})},[ae(t.$slots,"default",{},()=>[t.dangerouslyUseHTMLString?(m(),V(le,{key:1},[w(" Caution here, message could've been compromised, never use user's input as message "),h("p",{innerHTML:t.message},null,8,me)],2112)):(m(),V("p",pe,D(t.message),1))])],6),[[x,t.message]]),t.showClose?(m(),T(i(P),{key:0,class:v(i(n).e("closeBtn")),onClick:Z(b,["stop"])},{default:_(()=>[O(i(u))]),_:1},8,["class","onClick"])):w("v-if",!0)],2)],46,fe),[[x,a.value]])]),_:3},8,["name","onBeforeLeave"]))}});var ge=X(ye,[["__file","/home/runner/work/element-plus/element-plus/packages/components/notification/src/notification.vue"]]);const k={"top-left":[],"top-right":[],"bottom-left":[],"bottom-right":[]},B=16;let Ce=1;const y=function(e={},s=null){if(!ee)return{close:()=>{}};(typeof e=="string"||I(e))&&(e={message:e});const o=e.position||"top-right";let n=e.offset||0;k[o].forEach(({vm:g})=>{var f;n+=(((f=g.el)==null?void 0:f.offsetHeight)||0)+B}),n+=B;const{nextZIndex:u}=oe(),a=`notification_${Ce++}`,d=e.onClose,p={zIndex:u(),...e,offset:n,id:a,onClose:()=>{he(a,o,d)}};let r=document.body;F(e.appendTo)?r=e.appendTo:re(e.appendTo)&&(r=document.querySelector(e.appendTo)),F(r)||(r=document.body);const c=document.createElement("div"),l=O(ge,p,I(p.message)?{default:()=>p.message}:null);return l.appContext=s??y._context,l.props.onDestroy=()=>{z(null,c)},z(l,c),k[o].push({vm:l}),r.appendChild(c.firstElementChild),{close:()=>{l.component.exposed.visible.value=!1}}};q.forEach(e=>{y[e]=(s={})=>((typeof s=="string"||I(s))&&(s={message:s}),y({...s,type:e}))});function he(e,s,o){const n=k[s],u=n.findIndex(({vm:c})=>{var l;return((l=c.component)==null?void 0:l.props.id)===e});if(u===-1)return;const{vm:a}=n[u];if(!a)return;o==null||o(a);const d=a.el.offsetHeight,p=s.split("-")[0];n.splice(u,1);const r=n.length;if(!(r<1))for(let c=u;c<r;c++){const{el:l,component:g}=n[c].vm,f=Number.parseInt(l.style[p],10)-d-B;g.props.offset=f}}function be(){for(const e of Object.values(k))e.forEach(({vm:s})=>{s.component.exposed.visible.value=!1})}y.closeAll=be;y._context=null;const Ne=te(y,"$notify");export{Ne as E};