(()=>{"use strict";function t(e){return t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},t(e)}$((function(){var e,o=$(".task-due-date"),a=$(".sidebar-todo-modal"),s=$("#form-modal-todo"),l=$(".todo-item-favorite"),i=$(".modal-title"),n=$(".add-todo-item"),d=$(".add-task button"),r=$(".update-todo-item"),c=$(".update-btn"),m=$("#task-desc"),p=$("#task-assigned"),h=$("#task-tag"),u=$(".body-content-overlay"),f=$(".menu-toggle"),v=$(".sidebar-toggle"),g=$(".sidebar-left"),w=$(".sidebar-menu-list"),b=$("#todo-search"),k=$(".sort-asc"),C=$(".sort-desc"),y=$(".todo-task-list"),x=$(".todo-task-list-wrapper"),T=$(".list-group-filters"),D=$(".no-results"),S=100,j="rtl"===$("html").attr("data-textdirection"),M="../../../app-assets/";if("laravel"===$("body").attr("data-framework")&&(M=$("body").attr("data-asset-path")),$.app.menu.is_touch_device())w.css("overflow","scroll"),x.css("overflow","scroll");else{if(w.length>0)new PerfectScrollbar(w[0],{theme:"dark"});if(x.length>0)new PerfectScrollbar(x[0],{theme:"dark"})}T.length&&T.find("a").on("click",(function(){T.find("a").hasClass("active")&&T.find("a").removeClass("active"),$(this).addClass("active")}));var q=document.getElementById("todo-task-list");function B(t){return t.id?'<div class="d-flex align-items-center"><img class="d-block rounded-circle me-50" src="'+$(t.element).data("img")+'" height="26" width="26" alt="'+t.text+'"><p class="mb-0">'+t.text+"</p></div>":t.text}if(void 0!==t(q)&&null!==q&&dragula([q],{moves:function(t,e,o){return o.classList.contains("drag-icon")}}),f.length&&f.on("click",(function(t){g.removeClass("show"),u.removeClass("show")})),v.length&&v.on("click",(function(t){t.stopPropagation(),g.toggleClass("show"),u.addClass("show")})),u.length&&u.on("click",(function(t){g.removeClass("show"),u.removeClass("show"),$(a).modal("hide")})),p.length&&(p.wrap('<div class="position-relative"></div>'),p.select2({placeholder:"Unassigned",dropdownParent:p.parent(),templateResult:B,templateSelection:B,escapeMarkup:function(t){return t}})),h.length&&(h.wrap('<div class="position-relative"></div>'),h.select2({placeholder:"Select tag"})),l.length&&$(l).on("click",(function(){$(this).toggleClass("text-warning")})),o.length&&o.flatpickr({dateFormat:"Y-m-d",defaultDate:"today",onReady:function(t,e,o){o.isMobile&&$(o.mobileInput).attr("step",null)}}),m.length)new Quill("#task-desc",{bounds:"#task-desc",modules:{formula:!0,syntax:!0,toolbar:".desc-toolbar"},placeholder:"Write Your Description",theme:"snow"});d.length&&d.on("click",(function(t){n.removeClass("d-none"),c.addClass("d-none"),i.text("Add Task"),g.removeClass("show"),u.removeClass("show"),a.find(".new-todo-item-title").val(""),m.find(".ql-editor")[0].innerHTML=""})),s.length&&(s.validate({ignore:".ql-container *",rules:{todoTitleAdd:{required:!0},"task-assigned":{required:!0},"task-due-date":{required:!0}}}),s.on("submit",(function(t){if(t.preventDefault(),s.valid()){S++;var e=$("#task-assigned").val(),o="",l={"Phill Buffer":M+"images/portrait/small/avatar-s-3.jpg","Chandler Bing":M+"images/portrait/small/avatar-s-1.jpg","Ross Geller":M+"images/portrait/small/avatar-s-4.jpg","Monica Geller":M+"images/portrait/small/avatar-s-6.jpg","Joey Tribbiani":M+"images/portrait/small/avatar-s-2.jpg","Rachel Green":M+"images/portrait/small/avatar-s-11.jpg"},i=$(".sidebar-todo-modal .new-todo-item-title").val(),n=$(".sidebar-todo-modal .task-due-date").val(),d=new Date(n),r=new Intl.DateTimeFormat("en",{month:"short"}).format(d)+" "+new Intl.DateTimeFormat("en",{day:"2-digit"}).format(d),c=$(".task-tag").val(),m={Team:"primary",Low:"success",Medium:"warning",High:"danger",Update:"info"};$.each(c,(function(t,e){o+='<span class="badge rounded-pill badge-light-'+m[e]+' me-50">'+e+"</span>"})),""!=i&&$(y).prepend('<li class="todo-item"><div class="todo-title-wrapper"><div class="todo-title-area">'+feather.icons["more-vertical"].toSvg({class:"drag-icon"})+'<div class="title-wrapper"><div class="form-check"><input type="checkbox" class="form-check-input" id="customCheck'+S+'" /><label class="form-check-label" for="customCheck'+S+'"></label></div><span class="todo-title">'+i+'</span></div></div><div class="todo-item-action"><span class="badge-wrapper me-1">'+o+'</span><small class="text-nowrap text-muted me-1">'+r+'</small><div class="avatar"><img src="'+l[e]+'" alt="'+e+'" height="28" width="28"></div></div></div></li>'),toastr.success("Data Saved","💾 Task Action!",{closeButton:!0,tapToDismiss:!1,rtl:j}),$(a).modal("hide"),u.removeClass("show")}}))),x.on("change",".form-check",(function(t){var e=$(this).find("input");e.prop("checked")?(e.closest(".todo-item").addClass("completed"),toastr.success("Task Completed","Congratulations!! 🎉",{closeButton:!0,tapToDismiss:!1,rtl:j})):e.closest(".todo-item").removeClass("completed")})),x.on("click",".form-check",(function(t){t.stopPropagation()})),$(document).on("click",".todo-task-list-wrapper .todo-item",(function(t){a.modal("show"),n.addClass("d-none"),c.removeClass("d-none"),$(this).hasClass("completed")?i.html('<button type="button" class="btn btn-sm btn-outline-success complete-todo-item waves-effect waves-float waves-light" data-bs-dismiss="modal">Completed</button>'):i.html('<button type="button" class="btn btn-sm btn-outline-secondary complete-todo-item waves-effect waves-float waves-light" data-bs-dismiss="modal">Mark Complete</button>'),h.val("").trigger("change"),$("#task-desc .ql-editor")[0].innerHTML="Chocolate cake topping bonbon jujubes donut sweet wafer. Marzipan gingerbread powder brownie bear claw. Chocolate bonbon sesame snaps jelly caramels oat cake.",e=$(this).find(".todo-title");var o=$(this).find(".todo-title").html();s.find(".new-todo-item-title").val(o)})),r.length&&r.on("click",(function(t){var o=s.valid();if(t.preventDefault(),o){var l=s.find(".new-todo-item-title").val();$(e).text(l),toastr.success("Data Saved","💾 Task Action!",{closeButton:!0,tapToDismiss:!1,rtl:j}),$(a).modal("hide")}})),k.length&&k.on("click",(function(){x.find("li").sort((function(t,e){return $(e).find(".todo-title").text().toUpperCase()<$(t).find(".todo-title").text().toUpperCase()?1:-1})).appendTo(y)})),C.length&&C.on("click",(function(){x.find("li").sort((function(t,e){return $(e).find(".todo-title").text().toUpperCase()>$(t).find(".todo-title").text().toUpperCase()?1:-1})).appendTo(y)})),b.length&&b.on("keyup",(function(){var t=$(this).val().toLowerCase();""!==t?($(".todo-item").filter((function(){$(this).toggle($(this).text().toLowerCase().indexOf(t)>-1)})),0==$(".todo-item:visible").length?$(D).hasClass("show")||$(D).addClass("show"):$(D).removeClass("show")):($(".todo-item").show(),$(D).hasClass("show")&&$(D).removeClass("show"))})),$(window).width()>992&&u.hasClass("show")&&u.removeClass("show")})),$(window).on("resize",(function(){$(window).width()>992&&$(".body-content-overlay").hasClass("show")&&($(".sidebar-left").removeClass("show"),$(".body-content-overlay").removeClass("show"),$(".sidebar-todo-modal").modal("hide"))}))})();