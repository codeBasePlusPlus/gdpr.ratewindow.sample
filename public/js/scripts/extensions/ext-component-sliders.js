(()=>{function e(e,n,i){return(n=function(e){var n=function(e,n){if("object"!==t(e)||null===e)return e;var i=e[Symbol.toPrimitive];if(void 0!==i){var r=i.call(e,n||"default");if("object"!==t(r))return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===n?String:Number)(e)}(e,"string");return"symbol"===t(n)?n:String(n)}(n))in e?Object.defineProperty(e,n,{value:i,enumerable:!0,configurable:!0,writable:!0}):e[n]=i,e}function t(e){return t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},t(e)}$((function(){"use strict";var n,i="ltr";"rtl"==$("html").data("textdirection")&&(i="rtl");var r=document.getElementById("slider-handles"),o=document.getElementById("slider-snap"),l=document.getElementById("tap"),d=document.getElementById("drag"),a=document.getElementById("drag-fixed"),c=document.getElementById("hover"),u=document.getElementById("hover-val"),m=document.getElementById("combined"),s=document.getElementById("pips-range");void 0!==t(r)&&null!==r&&noUiSlider.create(r,{start:[4e3,8e3],direction:i,range:{min:[2e3],max:[1e4]}}),void 0!==t(o)&&null!==o&&noUiSlider.create(o,{start:[0,500],direction:i,snap:!0,connect:!0,range:{min:0,"10%":50,"20%":100,"30%":150,"40%":500,"50%":800,max:1e3}}),void 0!==t(l)&&null!==l&&noUiSlider.create(l,{start:[20,40],direction:i,behaviour:"tap",connect:!0,range:{min:10,max:50}}),void 0!==t(d)&&null!==d&&noUiSlider.create(d,{start:[40,60],direction:i,behaviour:"drag",connect:!0,range:{min:20,max:80}}),void 0!==t(a)&&null!==a&&noUiSlider.create(a,{start:[40,60],direction:i,behaviour:"drag-fixed",connect:!0,range:{min:20,max:80}}),void 0!==t(c)&&null!==c&&(noUiSlider.create(c,{start:20,direction:i,behaviour:"hover-snap",range:{min:0,max:100}}),c.noUiSlider.on("hover",(function(e){u.innerHTML=e}))),void 0!==t(m)&&null!==m&&noUiSlider.create(m,{start:[40,60],direction:i,behaviour:"drag-tap",connect:!0,range:{min:20,max:80}}),void 0!==t(s)&&null!==s&&noUiSlider.create(s,{start:10,step:10,range:{min:0,max:100},tooltips:!0,direction:i,pips:{mode:"steps",stepped:!0,density:5}});var v=document.getElementById("default-color-slider"),g=document.getElementById("secondary-color-slider"),p=document.getElementById("success-color-slider"),y=document.getElementById("info-color-slider"),S=document.getElementById("warning-color-slider"),f=document.getElementById("danger-color-slider"),h=(e(n={start:[40,60],connect:!0,behaviour:"drag"},"connect",!0),e(n,"step",10),e(n,"tooltips",!0),e(n,"range",{min:0,max:100}),e(n,"pips",{mode:"steps",stepped:!0,density:5}),e(n,"direction",i),n);void 0!==t(v)&&null!==v&&noUiSlider.create(v,h),void 0!==t(g)&&null!==g&&noUiSlider.create(g,h),void 0!==t(p)&&null!==p&&noUiSlider.create(p,h),void 0!==t(y)&&null!==y&&noUiSlider.create(y,h),void 0!==t(S)&&null!==S&&noUiSlider.create(S,h),void 0!==t(f)&&null!==f&&noUiSlider.create(f,h);var b=document.getElementById("slider-vertical"),E=document.getElementById("connect-upper"),U=document.getElementById("slider-tooltips"),x=document.getElementById("vertical-limit");void 0!==t(b)&&null!==b&&(b.style.height="200px",noUiSlider.create(b,{start:20,direction:i,orientation:"vertical",range:{min:0,max:100}})),void 0!==t(E)&&null!==E&&(E.style.height="200px",noUiSlider.create(E,{start:30,direction:i,orientation:"vertical",connect:"upper",range:{min:0,max:100}})),void 0!==t(U)&&null!==U&&(U.style.height="200px",noUiSlider.create(U,{start:[20,80],direction:i,orientation:"vertical",tooltips:[wNumb({decimals:1}),wNumb({decimals:1})],range:{min:0,max:100}})),void 0!==t(x)&&null!==x&&(x.style.height="200px",noUiSlider.create(x,{start:[40,60],direction:i,orientation:"vertical",limit:40,behaviour:"drag",connect:!0,range:{min:0,max:100}}));var B=document.getElementById("slider-select"),I=document.getElementById("slider-with-input"),w=document.getElementById("slider-input-number");if(void 0!==t(I)&&null!==I&&(noUiSlider.create(I,{start:[10,30],direction:i,connect:!0,range:{min:-20,max:40}}),I.noUiSlider.on("update",(function(e,t){var n=e[t];t?w.value=n:B.value=Math.round(n)}))),void 0!==t(I)&&null!==I){for(var j=-20;j<=40;j++){var L=document.createElement("option");L.text=j,L.value=j,B.appendChild(L)}B.addEventListener("change",(function(){I.noUiSlider.set([this.value,null])}))}void 0!==t(w)&&null!==w&&w.addEventListener("change",(function(){I.noUiSlider.set([null,this.value])}))}))})();