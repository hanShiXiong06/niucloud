import{i as a}from"./_isIterateeCall-797defa1.js";import{t as g}from"./debounce-196ce6a6.js";var m=1/0,I=17976931348623157e292;function o(n){if(!n)return n===0?n:0;if(n=g(n),n===m||n===-m){var r=n<0?-1:1;return r*I}return n===n?n:0}var h=Math.ceil,t=Math.max;function x(n,r,i,f){for(var u=-1,e=t(h((r-n)/(i||1)),0),c=Array(e);e--;)c[f?e:++u]=n,n+=i;return c}function M(n){return function(r,i,f){return f&&typeof f!="number"&&a(r,i,f)&&(i=f=void 0),r=o(r),i===void 0?(i=r,r=0):i=o(i),f=f===void 0?r<i?1:-1:o(f),x(r,i,f,n)}}var N=M();const y=N;export{y as r};
