
(function(){'use strict';
var preview=document.getElementById('custPreview');
if(!preview){return;}
var fontMap={system:'system-ui,-apple-system,sans-serif',inter:"'Inter',sans-serif",lato:"'Lato',sans-serif",montserrat:"'Montserrat',sans-serif",poppins:"'Poppins',sans-serif",roboto:"'Roboto',sans-serif",playfair:"'Playfair Display',serif",merriweather:"'Merriweather',serif"};
function setPreview(key,value){preview.style.setProperty(key,value);}
function applyAll(){
	var form=document.getElementById('themeForm');if(!form){return;}
	var fd=new FormData(form);
	var accent=fd.get('theme_accent')||'#2563eb';
	var text=fd.get('theme_text')||'#0f172a';
	var panel=fd.get('theme_panel')||'#ffffff';
	var muted=fd.get('theme_muted')||'#475569';
	var border=fd.get('theme_border')||'#dbe4f0';
	var radius=(fd.get('theme_radius')||'20')+'px';
	var fontBody=fontMap[fd.get('theme_font_body')]||fontMap['system'];
	var fontHeading=fontMap[fd.get('theme_font_heading')]||fontMap['system'];
	var bgType=fd.get('theme_bg_type')||'gradient';
	var bgColor=fd.get('theme_bg_color')||'#f3f6fb';
	var bgFrom=fd.get('theme_bg_gradient_from')||'#e0eaff';
	var headerStyle=fd.get('theme_header_style')||'glass';
	var headerBg=fd.get('theme_header_bg')||'#ffffff';
	var footerBg=fd.get('theme_footer_bg')||'#ffffff';
	var bg=bgType==='color'?bgColor:bgType==='image'?bgColor:'radial-gradient(ellipse 800px 300px at 100% 0,rgba(37,99,235,.14),transparent 60%),'+bgFrom;
	var hbg=headerStyle==='solid'?headerBg:headerStyle==='transparent'?'transparent':'rgba(255,255,255,.72)';
	setPreview('--prev-bg',bg);
	setPreview('--prev-accent',accent);
	setPreview('--prev-text',text);
	setPreview('--prev-panel',panel);
	setPreview('--prev-muted',muted);
	setPreview('--prev-border',border);
	setPreview('--prev-radius',radius);
	setPreview('--prev-font-body',fontBody);
	setPreview('--prev-font-heading',fontHeading);
	setPreview('--prev-header-bg',hbg);
	setPreview('--prev-footer-bg',footerBg);
}
var form=document.getElementById('themeForm');
if(form){form.addEventListener('input',applyAll);form.addEventListener('change',applyAll);}
// Show/hide bg type rows
var bgTypeSelect=document.getElementById('bgTypeSelect');
if(bgTypeSelect){bgTypeSelect.addEventListener('change',function(){
	var val=this.value;
	document.querySelectorAll('.bg-opt-color').forEach(function(el){el.style.display=val==='color'?'':'none';});
	document.querySelectorAll('.bg-opt-gradient').forEach(function(el){el.style.display=val==='gradient'?'':'none';});
	document.querySelectorAll('.bg-opt-image').forEach(function(el){el.style.display=val==='image'?'':'none';});
});}
// Show/hide header bg row
var headerStyleSel=document.querySelector('[name="theme_header_style"]');
var headerBgRow=document.getElementById('headerBgRow');
if(headerStyleSel&&headerBgRow){headerStyleSel.addEventListener('change',function(){headerBgRow.style.display=this.value==='solid'?'':'none';});}
// Range sliders live labels
document.querySelectorAll('input[type=range]').forEach(function(r){
	var label=document.querySelector('[data-for="'+r.name+'"]');
	if(label){r.addEventListener('input',function(){label.textContent=this.value+'px';});}
});
applyAll();
}());
(function(){'use strict';var list=document.getElementById('builderList');var hidden=document.getElementById('builderDataInput');if(!list||!hidden){return;}var initial=Array.isArray(window.CMS_BUILDER_BLOCKS)?window.CMS_BUILDER_BLOCKS:[];function esc(v){return String(v||'').replace(/[&<>"']/g,function(ch){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch];});}function blockData(type){return{type:type||'text',title:'',text:'',background_color:'#ffffff',background_image:'',background_attachment:'scroll',min_height:'420',align:'left',button_text:'',button_url:'',image_url:'',image_alt:''};}function row(type,data){var block=Object.assign(blockData(type),data||{});var item=document.createElement('div');item.className='builder-item';item.draggable=true;item.innerHTML=''+ '<div class="builder-head">'+ '<div><span class="builder-handle">Przeciagnij</span> <strong>'+esc(block.type.toUpperCase())+'</strong></div>'+ '<button type="button" class="btn danger" data-remove-block>Usun blok</button>'+ '</div>'+ '<div class="builder-fields">'+ '<div><label>Typ bloku</label><select data-field="type"><option value="hero">Hero</option><option value="text">Text</option><option value="image">Image</option></select></div>'+ '<div><label>Wyrownanie</label><select data-field="align"><option value="left">Lewo</option><option value="center">Srodek</option><option value="right">Prawo</option></select></div>'+ '<div><label>Tytul</label><input type="text" data-field="title" value="'+esc(block.title)+'"></div>'+ '<div><label>Minimalna wysokosc</label><input type="number" min="200" max="1200" step="10" data-field="min_height" value="'+esc(block.min_height)+'"></div>'+ '<div class="full"><label>Tekst</label><textarea data-field="text">'+esc(block.text)+'</textarea></div>'+ '<div><label>Kolor tla</label><input type="color" data-field="background_color" value="'+esc(block.background_color)+'"></div>'+ '<div><label>Zachowanie tla</label><select data-field="background_attachment"><option value="scroll">Przewija sie</option><option value="fixed">Nieruchome</option></select></div>'+ '<div class="full"><label>Adres obrazka tla</label><input type="url" data-field="background_image" value="'+esc(block.background_image)+'" placeholder="https://..."></div>'+ '<div><label>Tekst przycisku</label><input type="text" data-field="button_text" value="'+esc(block.button_text)+'"></div>'+ '<div><label>URL przycisku</label><input type="url" data-field="button_url" value="'+esc(block.button_url)+'" placeholder="https://..."></div>'+ '<div><label>Adres obrazka elementu</label><input type="url" data-field="image_url" value="'+esc(block.image_url)+'" placeholder="https://..."></div>'+ '<div><label>ALT obrazka</label><input type="text" data-field="image_alt" value="'+esc(block.image_alt)+'"></div>'+ '</div>';item.querySelector('[data-field="type"]').value=block.type;item.querySelector('[data-field="align"]').value=block.align;item.querySelector('[data-field="background_attachment"]').value=block.background_attachment;bind(item);return item;}function sync(){var payload=[];list.querySelectorAll('.builder-item').forEach(function(item){var data={};item.querySelectorAll('[data-field]').forEach(function(field){data[field.getAttribute('data-field')]=field.value;});payload.push(data);});hidden.value=JSON.stringify(payload);}function bind(item){item.querySelectorAll('[data-field]').forEach(function(field){field.addEventListener('input',sync);field.addEventListener('change',sync);});item.querySelector('[data-remove-block]').addEventListener('click',function(){item.remove();renderEmpty();sync();});item.addEventListener('dragstart',function(){item.classList.add('dragging');});item.addEventListener('dragend',function(){item.classList.remove('dragging');sync();});}function renderEmpty(){var empty=document.getElementById('builderEmpty');if(!empty){return;}empty.style.display=list.querySelector('.builder-item')?'none':'block';}list.addEventListener('dragover',function(e){e.preventDefault();var dragging=list.querySelector('.builder-item.dragging');if(!dragging){return;}var after=[].slice.call(list.querySelectorAll('.builder-item:not(.dragging)')).find(function(el){var rect=el.getBoundingClientRect();return e.clientY<rect.top+rect.height/2;});if(after){list.insertBefore(dragging,after);}else{list.appendChild(dragging);}});document.querySelectorAll('[data-add-block]').forEach(function(btn){btn.addEventListener('click',function(){list.appendChild(row(btn.getAttribute('data-add-block')));renderEmpty();sync();});});if(initial.length){initial.forEach(function(item){list.appendChild(row(item.type||'text',item));});}renderEmpty();sync();var form=document.getElementById('pageEditorForm');if(form){form.addEventListener('submit',sync);}}());

// ── Sidebar toggle ────────────────────────────────────────────────────────────
(function () {
    'use strict';
    var layout = document.querySelector('.layout');
    var sidebar = document.querySelector('.sidebar');
    var btn = document.getElementById('sidebarToggleBtn');
    if (!layout || !sidebar || !btn) { return; }

    function apply(hidden) {
        if (hidden) {
            layout.classList.add('sidebar-hidden');
            sidebar.classList.add('collapsed');
            sidebar.style.overflow = 'hidden';
            btn.title = 'Pokaz panel boczny';
            btn.innerHTML = '&#8250;';
        } else {
            layout.classList.remove('sidebar-hidden');
            sidebar.classList.remove('collapsed');
            setTimeout(function () { sidebar.style.overflow = ''; }, 260);
            btn.title = 'Ukryj panel boczny';
            btn.innerHTML = '&#8249;';
        }
    }

    apply(localStorage.getItem('cms_sidebar_hidden') === '1');

    btn.addEventListener('click', function () {
        var nowHidden = !sidebar.classList.contains('collapsed');
        localStorage.setItem('cms_sidebar_hidden', nowHidden ? '1' : '0');
        apply(nowHidden);
    });
}());

(function(){'use strict';
var placementList=document.getElementById('placementList');
var placementInput=document.getElementById('placementsInput');
if(!placementList||!placementInput){return;}

function sync(){
	var out=[];
	placementList.querySelectorAll('.placement-item').forEach(function(item,idx){
		var enabled=item.querySelector('[data-field="enabled"]');
		if(!enabled||!enabled.checked){return;}
		out.push({
			slug:item.getAttribute('data-slug')||'',
			position:(item.querySelector('[data-field="position"]')||{value:'after_content'}).value,
			sort_order:idx
		});
	});
	placementInput.value=JSON.stringify(out);
}

placementList.querySelectorAll('.placement-item').forEach(function(item){
	item.addEventListener('dragstart',function(){item.classList.add('dragging');});
	item.addEventListener('dragend',function(){item.classList.remove('dragging');sync();});
	item.querySelectorAll('input,select').forEach(function(el){el.addEventListener('change',sync);});
});

placementList.addEventListener('dragover',function(e){
	e.preventDefault();
	var dragging=placementList.querySelector('.placement-item.dragging');
	if(!dragging){return;}
	var after=[].slice.call(placementList.querySelectorAll('.placement-item:not(.dragging)')).find(function(el){
		var rect=el.getBoundingClientRect();
		return e.clientY<rect.top+rect.height/2;
	});
	if(after){placementList.insertBefore(dragging,after);}else{placementList.appendChild(dragging);} 
});

sync();
}());
