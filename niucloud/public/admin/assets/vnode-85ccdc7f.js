import{G as u,a8 as o,F as t,b1 as S,a2 as s,am as A}from"./base-04829be5.js";var N=(e=>(e[e.TEXT=1]="TEXT",e[e.CLASS=2]="CLASS",e[e.STYLE=4]="STYLE",e[e.PROPS=8]="PROPS",e[e.FULL_PROPS=16]="FULL_PROPS",e[e.HYDRATE_EVENTS=32]="HYDRATE_EVENTS",e[e.STABLE_FRAGMENT=64]="STABLE_FRAGMENT",e[e.KEYED_FRAGMENT=128]="KEYED_FRAGMENT",e[e.UNKEYED_FRAGMENT=256]="UNKEYED_FRAGMENT",e[e.NEED_PATCH=512]="NEED_PATCH",e[e.DYNAMIC_SLOTS=1024]="DYNAMIC_SLOTS",e[e.HOISTED=-1]="HOISTED",e[e.BAIL=-2]="BAIL",e))(N||{});function f(e){return o(e)&&e.type===t}function i(e){return o(e)&&e.type===S}function _(e){return o(e)&&!f(e)&&!i(e)}const L=e=>{if(!o(e))return{};const T=e.props||{},E=(o(e.type)?e.type.props:void 0)||{},r={};return Object.keys(E).forEach(n=>{s(E[n],"default")&&(r[n]=E[n].default)}),Object.keys(T).forEach(n=>{r[A(n)]=T[n]}),r},D=e=>{if(!u(e)||e.length>1)throw new Error("expect to receive a single Vue element child");return e[0]},p=e=>{const T=u(e)?e:[e],E=[];return T.forEach(r=>{var n;u(r)?E.push(...p(r)):o(r)&&u(r.children)?E.push(...p(r.children)):(E.push(r),o(r)&&((n=r.component)!=null&&n.subTree)&&E.push(...p(r.component.subTree)))}),E};export{N as P,_ as a,D as e,p as f,L as g,f as i};
