import{a as l,b as r}from"./_Uint8Array-6ca580e8.js";import{b$ as t,bT as h}from"./base-d2ce4248.js";var e=t?t.isConcatSpreadable:void 0;function x(n){return h(n)||l(n)||!!(e&&n&&n[e])}function m(n,o,b,a,f){var s=-1,g=n.length;for(b||(b=x),f||(f=[]);++s<g;){var i=n[s];o>0&&b(i)?o>1?m(i,o-1,b,a,f):r(f,i):a||(f[f.length]=i)}return f}function S(n){var o=n==null?0:n.length;return o?m(n,1):[]}export{m as b,S as f};
