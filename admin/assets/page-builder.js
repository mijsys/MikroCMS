/* ===================================================================
   MikroCMS Page Builder — v1.1.7.4
   Tile-based canvas builder: palette → canvas → inspector
   =================================================================== */
(function () {
	'use strict';

	// ── DOM refs ──────────────────────────────────────────────────────────────
	var canvas      = document.getElementById('cmsCanvas');
	var inspector   = document.getElementById('cmsInspector');
	var hiddenInput = document.getElementById('builderDataInputV2');

	if (!canvas || !hiddenInput) { return; }

	// ── State ─────────────────────────────────────────────────────────────────
	var state = {
		blocks:       [],
		selectedId:   null,
		draggingId:   null,
		history:      [],
		historyIndex: -1
	};

	// ── Helpers ───────────────────────────────────────────────────────────────
	function uid() {
		return 'b' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
	}

	function esc(v) {
		return String(v == null ? '' : v)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	// ── Block defaults ────────────────────────────────────────────────────────
	var TYPE_NAMES = {
		heading: 'Nagłówek', text: 'Tekst', image: 'Obraz',
		button: 'Przycisk', hero: 'Hero Banner', divider: 'Separator',
		spacer: 'Odstęp', html: 'HTML'
	};

	function defaults(type) {
		var common = {
			id: uid(), type: type || 'text',
			display: 'block', order: 0,
			link_url: '', link_target: '_self',
			padding_y: '20', padding_x: '0',
			bg_color: '', width: '100', align: 'left',
			float_x: '0', float_y: '0'
		};
		var byType = {
			heading: { heading_text: 'Nagłówek strony', heading_level: 'h2', heading_color: '#111111', heading_size: '' },
			text:    { text_content: 'Wpisz treść tutaj...', text_size: '16', text_color: '#333333', text_bold: '0', text_italic: '0' },
			image:   { image_src: '', image_alt: '', image_width: '100', image_border_radius: '0', image_link: '' },
			button:  { btn_label: 'Kliknij mnie', btn_url: '#', btn_target: '_self', btn_style: 'primary', btn_size: 'md', btn_full_width: '0' },
			hero:    { hero_title: 'Mocny nagłówek', hero_subtitle: 'Krótki opis wartości.', hero_bg_color: '#1a2942', hero_bg_image: '', hero_text_color: '#ffffff', hero_btn_text: 'Dowiedz się więcej', hero_btn_url: '#', hero_min_height: '400', hero_text_align: 'center' },
			divider: { div_color: '#e2e8f0', div_thickness: '1', div_style: 'solid', div_width: '100' },
			spacer:  { spacer_height: '40' },
			html:    { html_content: '<p>Wstaw kod HTML</p>' }
		};
		return Object.assign({}, common, byType[type] || {});
	}

	function normalize(raw) {
		if (!raw || typeof raw !== 'object') { return defaults('text'); }
		var type = String(raw.type || 'text');
		var validTypes = ['heading', 'text', 'image', 'button', 'hero', 'divider', 'spacer', 'html'];
		if (validTypes.indexOf(type) < 0) {
			type = { container: 'text', gallery: 'text', plugin_slot: 'text' }[type] || 'text';
		}
		var block = defaults(type);
		Object.keys(block).forEach(function (k) {
			if (raw[k] !== undefined && raw[k] !== null) { block[k] = raw[k]; }
		});
		block.type = type;
		if (!block.id) { block.id = uid(); }
		if (type === 'image'   && !block.image_src    && raw.image_url) { block.image_src    = raw.image_url; }
		if (type === 'heading' && !block.heading_text  && raw.title)    { block.heading_text  = raw.title; }
		if (type === 'hero'    && !block.hero_title    && raw.title)    { block.hero_title    = raw.title; }
		return block;
	}

	// ── History ───────────────────────────────────────────────────────────────
	function snapshot() { return JSON.stringify(state.blocks); }

	function pushHistory() {
		var s = snapshot();
		if (state.historyIndex >= 0 && state.history[state.historyIndex] === s) { return; }
		state.history = state.history.slice(0, state.historyIndex + 1);
		state.history.push(s);
		if (state.history.length > 60) { state.history.shift(); }
		state.historyIndex = state.history.length - 1;
		updateUndoRedo();
	}

	function restoreHistory(idx) {
		var parsed;
		try { parsed = JSON.parse(state.history[idx]); } catch (e) { parsed = []; }
		state.blocks = Array.isArray(parsed) ? parsed.map(normalize) : [];
		state.selectedId = null;
		state.historyIndex = idx;
		updateUndoRedo();
		rerender();
	}

	function updateUndoRedo() {
		var u = document.getElementById('bldUndo');
		var r = document.getElementById('bldRedo');
		if (u) { u.disabled = state.historyIndex <= 0; }
		if (r) { r.disabled = state.historyIndex >= state.history.length - 1; }
	}

	// ── Block CRUD ────────────────────────────────────────────────────────────
	function addBlock(type) {
		var block = defaults(type);
		block.order = state.blocks.length;
		state.blocks.push(block);
		state.selectedId = block.id;
		pushHistory();
		rerender();
		setTimeout(function () {
			var el = canvas.querySelector('[data-block-id="' + block.id + '"]');
			if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
		}, 60);
	}

	function removeBlock(id) {
		state.blocks = state.blocks.filter(function (b) { return b.id !== id; });
		if (state.selectedId === id) { state.selectedId = null; }
		reindex();
		pushHistory();
		rerender();
	}

	function updateBlock(id, updates) {
		state.blocks = state.blocks.map(function (b) {
			return b.id === id ? Object.assign({}, b, updates) : b;
		});
		saveHidden();
		rerenderBlock(id);
	}

	function updateBlockAndHistory(id, updates) { updateBlock(id, updates); pushHistory(); }

	function moveBlock(id, dir) {
		var idx = state.blocks.findIndex(function (b) { return b.id === id; });
		if (idx < 0) { return; }
		var ni = idx + dir;
		if (ni < 0 || ni >= state.blocks.length) { return; }
		var arr = state.blocks.slice();
		var tmp = arr[idx]; arr[idx] = arr[ni]; arr[ni] = tmp;
		state.blocks = arr;
		reindex(); pushHistory(); rerender();
	}

	function duplicateBlock(id) {
		var src = state.blocks.find(function (b) { return b.id === id; });
		if (!src) { return; }
		var clone = Object.assign({}, src, { id: uid() });
		var idx = state.blocks.findIndex(function (b) { return b.id === id; });
		state.blocks.splice(idx + 1, 0, clone);
		reindex(); pushHistory();
		state.selectedId = clone.id;
		rerender();
	}

	function reindex() { state.blocks.forEach(function (b, i) { b.order = i; }); }

	// ── Canvas preview HTML per block ────────────────────────────────────────
	function blockPreviewHtml(b) {
		var align = b.align || 'left';
		var inner = '';

		if (b.type === 'heading') {
			var lvl  = (['h1','h2','h3','h4','h5','h6'].indexOf(b.heading_level||'h2')>=0 ? b.heading_level : 'h2');
			var szMap= {h1:32,h2:26,h3:21,h4:18,h5:16,h6:14};
			var sz   = b.heading_size ? parseInt(b.heading_size,10) : (szMap[lvl]||26);
			inner = '<'+lvl+' style="font-size:'+sz+'px;color:'+esc(b.heading_color||'#111')+';text-align:'+align+';margin:0;line-height:1.25;font-weight:800">'+esc(b.heading_text||'Nagłówek')+'</'+lvl+'>';
		}
		else if (b.type === 'text') {
			var s='font-size:'+esc(b.text_size||'16')+'px;color:'+esc(b.text_color||'#333')+';text-align:'+align+';margin:0;line-height:1.6;white-space:pre-wrap;word-break:break-word';
			if(b.text_bold==='1'){s+=';font-weight:700';}
			if(b.text_italic==='1'){s+=';font-style:italic';}
			inner='<p style="'+s+'">'+esc(b.text_content||'').replace(/\n/g,'<br>')+'</p>';
		}
		else if (b.type === 'image') {
			if (b.image_src) {
				var iw=Math.max(10,Math.min(100,parseInt(b.image_width||'100',10)));
				var ims='width:'+iw+'%;max-width:100%;border-radius:'+esc(b.image_border_radius||'0')+'px;display:block';
				if(align==='center'){ims+=';margin:0 auto';}else if(align==='right'){ims+=';margin-left:auto;margin-right:0';}
				var imgEl='<img src="'+esc(b.image_src)+'" alt="'+esc(b.image_alt||'')+'" style="'+ims+'">';
				inner= b.image_link ? '<a href="'+esc(b.image_link)+'" target="_blank" rel="noopener">'+imgEl+'</a>' : imgEl;
			} else {
				inner='<div style="background:#f0f5ff;border:2px dashed #c7d8f7;border-radius:8px;height:160px;display:flex;align-items:center;justify-content:center;color:#6b8fd4;font-size:13px;font-weight:700;gap:8px">🖼 Brak obrazu — dodaj URL lub wgraj plik</div>';
			}
		}
		else if (b.type === 'button') {
			var bgM={primary:'#2563eb',secondary:'#475569',outline:'transparent',danger:'#dc2626',success:'#16a34a'};
			var txM={primary:'#fff',secondary:'#fff',outline:'#2563eb',danger:'#fff',success:'#fff'};
			var bdM={primary:'#2563eb',secondary:'#475569',outline:'#2563eb',danger:'#dc2626',success:'#16a34a'};
			var szM={sm:'8px 18px;font-size:13px',md:'11px 28px;font-size:15px',lg:'15px 40px;font-size:18px'};
			var st=b.btn_style||'primary'; var si=b.btn_size||'md';
			var bst='padding:'+(szM[si]||szM.md)+';background:'+(bgM[st]||bgM.primary)+';color:'+(txM[st]||txM.primary)+';border:2px solid '+(bdM[st]||bdM.primary)+';border-radius:8px;font-weight:700;text-decoration:none;display:inline-block;cursor:pointer';
			if(b.btn_full_width==='1'){bst+=';display:block;text-align:center;width:100%;box-sizing:border-box';}
			var aw=align==='center'?'text-align:center':(align==='right'?'text-align:right':'');
			inner='<div'+(aw?' style="'+aw+'"':'')+'>​<span style="'+bst+'">'+esc(b.btn_label||'Przycisk')+'</span></div>';
		}
		else if (b.type === 'hero') {
			var mh=Math.max(100,Math.min(1200,parseInt(b.hero_min_height||'400',10)));
			var hs='min-height:'+mh+'px;background-color:'+esc(b.hero_bg_color||'#1a2942')+';text-align:'+esc(b.hero_text_align||'center')+';display:flex;align-items:center;justify-content:center;flex-direction:column;padding:40px 24px;box-sizing:border-box';
			if(b.hero_bg_image){hs+=';background-image:url('+esc(b.hero_bg_image)+');background-size:cover;background-position:center';}
			var tc=esc(b.hero_text_color||'#fff');
			inner='<div style="'+hs+'">'
				+'<h2 style="color:'+tc+';font-size:clamp(1.4rem,4vw,2.4rem);font-weight:900;margin:0 0 12px;line-height:1.2;max-width:700px">'+esc(b.hero_title||'Hero')+'</h2>'
				+(b.hero_subtitle?'<p style="color:'+tc+';opacity:.85;font-size:1.05rem;margin:0 0 20px;max-width:560px;line-height:1.55">'+esc(b.hero_subtitle)+'</p>':'')
				+(b.hero_btn_text?'<span style="display:inline-block;padding:11px 28px;background:#2563eb;color:#fff;border-radius:8px;font-weight:700;font-size:15px">'+esc(b.hero_btn_text)+'</span>':'')
				+'</div>';
		}
		else if (b.type === 'divider') {
			var dw=Math.max(10,Math.min(100,parseInt(b.div_width||'100',10)));
			inner='<hr style="border:none;border-top:'+esc(b.div_thickness||'1')+'px '+esc(b.div_style||'solid')+' '+esc(b.div_color||'#e2e8f0')+';width:'+dw+'%;margin:0 auto">';
		}
		else if (b.type === 'spacer') {
			var sh=Math.max(4,parseInt(b.spacer_height||'40',10));
			inner='<div style="height:'+sh+'px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:11px;font-weight:700;letter-spacing:.05em;border:1px dashed #dde4ef;border-radius:4px">Odstęp '+sh+'px</div>';
		}
		else if (b.type === 'html') {
			inner='<div style="font-size:12px;color:#888;padding:8px 10px;background:#fafafa;border:1px dashed #ddd;border-radius:6px">'+(b.html_content?b.html_content.slice(0,300):'<em>Pusty blok HTML</em>')+'</div>';
		}

		var padY = Math.max(0, parseInt(b.padding_y||'20',10));
		var padX = Math.max(0, parseInt(b.padding_x||'0',10));
		var ws = 'padding:'+padY+'px '+padX+'px';
		if (b.bg_color) { ws += ';background:'+b.bg_color; }
		return { inner: inner, wrapStyle: ws };
	}

	// ── Build canvas-block DOM element ────────────────────────────────────────
	function buildBlockEl(block) {
		var preview = blockPreviewHtml(block);
		var el = document.createElement('div');
		el.className = 'canvas-block'
			+ (block.id === state.selectedId ? ' is-selected' : '')
			+ (block.display === 'float' ? ' canvas-block-float' : '');
		el.setAttribute('data-block-id', block.id);

		if (block.display === 'float') {
			el.style.left = esc(block.float_x || '0') + '%';
			el.style.top  = esc(block.float_y || '0') + 'px';
		}

		var content = document.createElement('div');
		content.className = 'canvas-block-content';
		content.setAttribute('style', preview.wrapStyle);
		content.innerHTML = preview.inner;
		el.appendChild(content);

		var toolbar = document.createElement('div');
		toolbar.className = 'canvas-block-toolbar';
		toolbar.innerHTML =
			'<span class="cbt-type">'+esc(TYPE_NAMES[block.type]||block.type)+'</span>'
			+'<button class="cbt-btn" data-action="move-up"   title="Do góry">↑</button>'
			+'<button class="cbt-btn" data-action="move-down" title="W dół">↓</button>'
			+'<button class="cbt-btn" data-action="duplicate" title="Duplikuj">⧉</button>'
			+'<button class="cbt-btn cbt-danger" data-action="delete" title="Usuń">✕</button>';
		el.appendChild(toolbar);

		if (block.display !== 'float') {
			var handle = document.createElement('div');
			handle.className = 'canvas-block-drag-handle';
			handle.innerHTML = '⠿';
			handle.title = 'Przeciągnij, aby zmienić kolejność';
			el.appendChild(handle);
		}
		return el;
	}

	// ── Bind events on a single canvas block ──────────────────────────────────
	function bindBlockEl(el, block) {
		el.addEventListener('click', function (e) {
			if (e.target.closest('.canvas-block-toolbar') || e.target.closest('.canvas-block-drag-handle')) { return; }
			e.stopPropagation();
			selectBlock(block.id);
		});

		el.querySelectorAll('[data-action]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				var action = btn.getAttribute('data-action');
				if (action === 'move-up')   { moveBlock(block.id, -1); return; }
				if (action === 'move-down') { moveBlock(block.id,  1); return; }
				if (action === 'duplicate') { duplicateBlock(block.id); return; }
				if (action === 'delete'   ) {
					if (window.confirm('Usunąć element "' + (TYPE_NAMES[block.type]||block.type) + '"?')) { removeBlock(block.id); }
				}
			});
		});

		if (block.display !== 'float') {
			var handle = el.querySelector('.canvas-block-drag-handle');
			if (handle) {
				handle.addEventListener('mousedown', function () { el.draggable = true; });

				el.addEventListener('dragstart', function (e) {
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData('text/plain', block.id);
					setTimeout(function () { el.classList.add('is-dragsource'); }, 0);
					state.draggingId = block.id;
				});

				el.addEventListener('dragend', function () {
					el.draggable = false;
					el.classList.remove('is-dragsource');
					canvas.querySelectorAll('.drop-before,.drop-after').forEach(function (b) {
						b.classList.remove('drop-before', 'drop-after');
					});
					state.draggingId = null;
				});

				el.addEventListener('dragover', function (e) {
					if (!state.draggingId || state.draggingId === block.id) { return; }
					e.preventDefault();
					var rect  = el.getBoundingClientRect();
					var after = e.clientY > rect.top + rect.height / 2;
					canvas.querySelectorAll('.canvas-block').forEach(function (b) {
						b.classList.remove('drop-before', 'drop-after');
					});
					el.classList.add(after ? 'drop-after' : 'drop-before');
				});

				el.addEventListener('dragleave', function () {
					el.classList.remove('drop-before', 'drop-after');
				});

				el.addEventListener('drop', function (e) {
					e.preventDefault();
					el.classList.remove('drop-before', 'drop-after');
					if (!state.draggingId || state.draggingId === block.id) { return; }
					var rect      = el.getBoundingClientRect();
					var after     = e.clientY > rect.top + rect.height / 2;
					var fromId    = state.draggingId;
					var fromIdx   = state.blocks.findIndex(function (b) { return b.id === fromId; });
					var toIdx     = state.blocks.findIndex(function (b) { return b.id === block.id; });
					if (fromIdx < 0 || toIdx < 0) { return; }
					var arr = state.blocks.slice();
					var mv  = arr.splice(fromIdx, 1)[0];
					var ni  = arr.findIndex(function (b) { return b.id === block.id; });
					arr.splice(after ? ni + 1 : ni, 0, mv);
					state.blocks = arr;
					reindex(); pushHistory(); rerender();
				});
			}
		} else {
			// Float: drag to reposition with mouse
			var isPos = false, mx0, my0, elx0, ely0;
			el.addEventListener('mousedown', function (e) {
				if (e.target.closest('.canvas-block-toolbar')) { return; }
				isPos = true; mx0 = e.clientX; my0 = e.clientY;
				var r = el.getBoundingClientRect(); var cr = canvas.getBoundingClientRect();
				elx0 = r.left - cr.left;
				ely0 = r.top  - cr.top + canvas.scrollTop;
				e.preventDefault();

				function onMove(ev) {
					if (!isPos) { return; }
					var nx = elx0 + ev.clientX - mx0;
					var ny = ely0 + ev.clientY - my0;
					el.style.left = Math.max(0, Math.min(90, Math.round((nx / canvas.offsetWidth) * 100))) + '%';
					el.style.top  = Math.max(0, Math.round(ny)) + 'px';
				}
				function onUp(ev) {
					if (!isPos) { return; }
					isPos = false;
					document.removeEventListener('mousemove', onMove);
					document.removeEventListener('mouseup', onUp);
					var nx = elx0 + ev.clientX - mx0;
					var ny = ely0 + ev.clientY - my0;
					updateBlockAndHistory(block.id, {
						float_x: String(Math.max(0, Math.min(90, Math.round((nx / canvas.offsetWidth) * 100)))),
						float_y: String(Math.max(0, Math.round(ny)))
					});
				}
				document.addEventListener('mousemove', onMove);
				document.addEventListener('mouseup', onUp);
			});
		}
	}

	// ── Select block ──────────────────────────────────────────────────────────
	function selectBlock(id) {
		state.selectedId = id;
		canvas.querySelectorAll('.canvas-block').forEach(function (el) {
			el.classList.toggle('is-selected', el.getAttribute('data-block-id') === id);
		});
		renderInspector();
	}

	// ── Re-render single block in place ───────────────────────────────────────
	function rerenderBlock(id) {
		var existing = canvas.querySelector('[data-block-id="' + id + '"]');
		if (!existing) { return; }
		var block = state.blocks.find(function (b) { return b.id === id; });
		if (!block) { return; }
		var newEl = buildBlockEl(block);
		bindBlockEl(newEl, block);
		existing.parentNode.replaceChild(newEl, existing);
	}

	// ── Full canvas re-render ─────────────────────────────────────────────────
	function rerender() {
		var sc = canvas.scrollTop;
		canvas.innerHTML = '';

		if (state.blocks.length === 0) {
			var empty = document.createElement('div');
			empty.className = 'canvas-empty';
			empty.innerHTML = '<div class="canvas-empty-icon">✦</div>'
				+ '<p class="canvas-empty-title">Canvas jest pusty</p>'
				+ '<p class="canvas-empty-hint">Kliknij kafelek w panelu po lewej,<br>aby dodać element do strony</p>';
			canvas.appendChild(empty);
		} else {
			state.blocks.filter(function (b) { return b.display !== 'float'; }).forEach(function (block) {
				var el = buildBlockEl(block); bindBlockEl(el, block); canvas.appendChild(el);
			});
			state.blocks.filter(function (b) { return b.display === 'float'; }).forEach(function (block) {
				var el = buildBlockEl(block); bindBlockEl(el, block); canvas.appendChild(el);
			});
		}

		canvas.scrollTop = sc;
		saveHidden();
		renderInspector();
		updateUndoRedo();
	}

	// ── Inspector helpers ─────────────────────────────────────────────────────
	function inpText(name, label, value, ph) {
		return '<div class="insp-field"><label class="insp-label">'+esc(label)+'</label>'
			+'<input type="text" class="insp-input" data-field="'+esc(name)+'" value="'+esc(value)+'"'+(ph?' placeholder="'+esc(ph)+'"':'')+'>'
			+'</div>';
	}
	function inpTextarea(name, label, value, rows, mono) {
		return '<div class="insp-field"><label class="insp-label">'+esc(label)+'</label>'
			+'<textarea class="insp-input'+(mono?' mono':'')+'" data-field="'+esc(name)+'" rows="'+(rows||4)+'">'+esc(value)+'</textarea>'
			+'</div>';
	}
	function inpColor(name, label, value) {
		return '<div class="insp-field"><label class="insp-label">'+esc(label)+'</label>'
			+'<div class="insp-color-row">'
			+'<input type="color" class="insp-color-picker" data-field="'+esc(name)+'" value="'+esc(value||'#000000')+'">'
			+'<input type="text" class="insp-input insp-color-text" data-color-for="'+esc(name)+'" value="'+esc(value||'')+'" placeholder="#000000">'
			+'</div></div>';
	}
	function inpColorOrEmpty(name, label, value) {
		return '<div class="insp-field"><label class="insp-label">'+esc(label)+'</label>'
			+'<div class="insp-color-row">'
			+'<input type="color" class="insp-color-picker" data-field="'+esc(name)+'" value="'+esc(value||'#ffffff')+'">'
			+'<input type="text" class="insp-input insp-color-text" data-color-for="'+esc(name)+'" value="'+esc(value)+'" placeholder="Puste = bez tła">'
			+'</div></div>';
	}
	function inpSelect(name, label, value, options) {
		var opts = options.map(function (o) {
			return '<option value="'+esc(o[0])+'"'+(o[0]===value?' selected':'')+'>'+esc(o[1])+'</option>';
		}).join('');
		return '<div class="insp-field"><label class="insp-label">'+esc(label)+'</label>'
			+'<select class="insp-input" data-field="'+esc(name)+'">'+opts+'</select></div>';
	}
	function inpCheck(name, label, checked) {
		return '<div class="insp-field insp-field-check"><label class="insp-check-label">'
			+'<input type="checkbox" data-field="'+esc(name)+'"'+(checked?' checked':'')+'>'+esc(label)+'</label></div>';
	}
	function inpAlign(current) {
		return '<div class="insp-field"><label class="insp-label">Wyrównanie</label>'
			+'<div class="insp-align-btns">'
			+['left','center','right'].map(function (a) {
				var icon={left:'⬤≡≡',center:'≡⬤≡',right:'≡≡⬤'}[a];
				return '<button type="button" class="insp-align-btn'+(current===a?' active':'')+'" data-field="align" data-align-val="'+a+'" title="'+a+'">'+icon+'</button>';
			}).join('')
			+'</div></div>';
	}
	function inpRow(fields) { return '<div class="insp-row">'+fields.join('')+'</div>'; }
	function inpUpload(id, label, field, blockId) {
		return '<div class="insp-upload-wrap">'
			+'<label class="btn secondary insp-upload-btn" for="'+esc(id)+'">⬆ '+esc(label)+'</label>'
			+'<input type="file" id="'+esc(id)+'" accept="image/*" style="display:none" data-upload-field="'+esc(field)+'">'
			+'</div>';
	}

	// ── Inspector render ──────────────────────────────────────────────────────
	function renderInspector() {
		if (!inspector) { return; }
		if (!state.selectedId) {
			inspector.innerHTML = '<div class="insp-empty"><div class="insp-empty-icon">←</div><p>Wybierz element na canvas,<br>aby edytować właściwości</p></div>';
			return;
		}
		var block = state.blocks.find(function (b) { return b.id === state.selectedId; });
		if (!block) {
			inspector.innerHTML = '<div class="insp-empty"><p>Element nie znaleziony</p></div>';
			return;
		}

		var html = '<div class="insp-topbar">'
			+'<span class="insp-type-badge">'+esc(TYPE_NAMES[block.type]||block.type)+'</span>'
			+'<button class="btn danger" id="inspDel" style="font-size:12px;padding:6px 12px">Usuń</button>'
			+'</div>';

		html += '<div class="insp-section"><div class="insp-section-title">Zawartość</div>';

		if (block.type === 'heading') {
			html += inpText('heading_text','Treść nagłówka',block.heading_text||'');
			html += inpRow([inpSelect('heading_level','Poziom',block.heading_level||'h2',[['h1','H1 – największy'],['h2','H2'],['h3','H3'],['h4','H4'],['h5','H5'],['h6','H6 – najmniejszy']]),inpText('heading_size','Rozmiar px (puste=auto)',block.heading_size||'')]);
			html += inpRow([inpColor('heading_color','Kolor',block.heading_color||'#111111'),inpAlign(block.align||'left')]);
		}
		else if (block.type === 'text') {
			html += inpTextarea('text_content','Treść',block.text_content||'',6);
			html += inpRow([inpText('text_size','Rozmiar (px)',block.text_size||'16'),inpColor('text_color','Kolor',block.text_color||'#333333')]);
			html += inpRow([inpCheck('text_bold','Pogrubienie',block.text_bold==='1'),inpCheck('text_italic','Kursywa',block.text_italic==='1')]);
			html += inpAlign(block.align||'left');
		}
		else if (block.type === 'image') {
			if (block.image_src) { html += '<img src="'+esc(block.image_src)+'" alt="" style="max-width:100%;border-radius:6px;margin-bottom:8px;display:block;border:1px solid #e2e8f0">'; }
			html += inpText('image_src','URL obrazu',block.image_src||'','https://...');
			html += inpUpload('imgUp_'+esc(block.id),'Wgraj z dysku','image_src', block.id);
			html += inpText('image_alt','Alt tekst (SEO)',block.image_alt||'');
			html += inpRow([inpText('image_width','Szerokość (%)',block.image_width||'100'),inpText('image_border_radius','Zaokrąglenie (px)',block.image_border_radius||'0')]);
			html += inpText('image_link','Link po kliknięciu (URL)',block.image_link||'','https://...');
			html += inpAlign(block.align||'left');
		}
		else if (block.type === 'button') {
			html += inpText('btn_label','Tekst przycisku',block.btn_label||'');
			html += inpText('btn_url','Link (URL)',block.btn_url||'','https://...');
			html += inpRow([inpSelect('btn_style','Styl',block.btn_style||'primary',[['primary','Niebieski'],['secondary','Szary'],['outline','Kontur'],['danger','Czerwony'],['success','Zielony']]),inpSelect('btn_size','Rozmiar',block.btn_size||'md',[['sm','Mały'],['md','Średni'],['lg','Duży']])]);
			html += inpRow([inpSelect('btn_target','Otwórz w',block.btn_target||'_self',[['_self','Tej karcie'],['_blank','Nowej karcie']]),inpCheck('btn_full_width','Pełna szerokość',block.btn_full_width==='1')]);
			html += inpAlign(block.align||'left');
		}
		else if (block.type === 'hero') {
			html += inpText('hero_title','Tytuł główny',block.hero_title||'');
			html += inpTextarea('hero_subtitle','Podtytuł',block.hero_subtitle||'',3);
			html += inpRow([inpColor('hero_bg_color','Kolor tła',block.hero_bg_color||'#1a2942'),inpColor('hero_text_color','Kolor tekstu',block.hero_text_color||'#ffffff')]);
			html += inpText('hero_bg_image','Obraz tła (URL)',block.hero_bg_image||'','https://...');
			html += inpUpload('heroBgUp_'+esc(block.id),'Wgraj obraz tła','hero_bg_image',block.id);
			html += inpRow([inpText('hero_btn_text','Tekst przycisku CTA',block.hero_btn_text||''),inpText('hero_btn_url','URL przycisku',block.hero_btn_url||'#','#')]);
			html += inpRow([inpText('hero_min_height','Min. wysokość (px)',block.hero_min_height||'400'),inpSelect('hero_text_align','Wyrównanie tekstu',block.hero_text_align||'center',[['left','Lewo'],['center','Środek'],['right','Prawo']])]);
		}
		else if (block.type === 'divider') {
			html += inpRow([inpColor('div_color','Kolor linii',block.div_color||'#e2e8f0'),inpText('div_thickness','Grubość (px)',block.div_thickness||'1')]);
			html += inpRow([inpSelect('div_style','Styl',block.div_style||'solid',[['solid','Pełna'],['dashed','Przerywana'],['dotted','Kropkowana']]),inpText('div_width','Szerokość (%)',block.div_width||'100')]);
		}
		else if (block.type === 'spacer') {
			html += inpText('spacer_height','Wysokość odstępu (px)',block.spacer_height||'40');
		}
		else if (block.type === 'html') {
			html += inpTextarea('html_content','Kod HTML',block.html_content||'',10,true);
			html += '<div class="insp-warn">⚠ Kod HTML renderowany bez filtrowania. Używaj tylko z zaufaną treścią.</div>';
		}

		html += '</div>';

		html += '<details class="insp-details"><summary>Układ i pozycja</summary>';
		html += inpSelect('display','Typ wyświetlania',block.display||'block',[['block','Blokowy (w przepływie strony)'],['float','Pływający (nakładka)']]);
		if (block.display === 'float') {
			html += inpRow([inpText('float_x','Pozycja X (%)',block.float_x||'0'),inpText('float_y','Pozycja Y (px)',block.float_y||'0')]);
		} else {
			html += inpText('width','Szerokość bloku (%)',block.width||'100');
		}
		html += inpRow([inpText('padding_y','Odstęp góra/dół (px)',block.padding_y||'20'),inpText('padding_x','Odstęp lewo/prawo (px)',block.padding_x||'0')]);
		html += inpColorOrEmpty('bg_color','Kolor tła bloku',block.bg_color||'');
		html += '</details>';

		html += '<details class="insp-details"><summary>Kliknięcie – link na cały blok</summary>';
		html += inpText('link_url','URL po kliknięciu',block.link_url||'','https://... (opcjonalne)');
		html += inpSelect('link_target','Otwórz w',block.link_target||'_self',[['_self','Tej samej karcie'],['_blank','Nowej karcie']]);
		html += '</details>';

		inspector.innerHTML = html;
		bindInspectorEvents(block);
	}

	// ── Inspector event binding ───────────────────────────────────────────────
	function bindInspectorEvents(block) {
		var delBtn = document.getElementById('inspDel');
		if (delBtn) {
			delBtn.addEventListener('click', function () {
				if (window.confirm('Usunąć element "' + (TYPE_NAMES[block.type]||block.type) + '"?')) { removeBlock(block.id); }
			});
		}

		inspector.querySelectorAll('[data-field]').forEach(function (el) {
			var fn = el.getAttribute('data-field');
			if (!fn) { return; }
			var isCheck = el.type === 'checkbox';

			el.addEventListener('input', function () {
				if (isCheck) { return; }
				var upd = {}; upd[fn] = el.value;
				updateBlock(block.id, upd);
				if (el.type === 'color') {
					var txt = inspector.querySelector('[data-color-for="'+fn+'"]');
					if (txt) { txt.value = el.value; }
				}
			});

			el.addEventListener('change', function () {
				var val = isCheck ? (el.checked ? '1' : '0') : el.value;
				var upd = {}; upd[fn] = val;
				updateBlockAndHistory(block.id, upd);
				var rerenderFields = ['display','heading_level'];
				if (rerenderFields.indexOf(fn) >= 0) { renderInspector(); }
			});

			if (el.getAttribute('data-align-val')) {
				el.addEventListener('click', function () {
					var val = el.getAttribute('data-align-val');
					inspector.querySelectorAll('[data-align-val]').forEach(function (b) {
						b.classList.toggle('active', b.getAttribute('data-align-val') === val);
					});
					updateBlockAndHistory(block.id, { align: val });
				});
			}
		});

		inspector.querySelectorAll('[data-color-for]').forEach(function (el) {
			el.addEventListener('input', function () {
				var forField = el.getAttribute('data-color-for');
				var picker   = inspector.querySelector('[data-field="'+forField+'"]');
				if (picker && /^#[0-9a-fA-F]{6}$/.test(el.value)) {
					picker.value = el.value;
					var upd = {}; upd[forField] = el.value;
					updateBlock(block.id, upd);
				}
			});
			el.addEventListener('change', function () {
				var forField = el.getAttribute('data-color-for');
				var upd = {}; upd[forField] = el.value;
				updateBlockAndHistory(block.id, upd);
			});
		});

		inspector.querySelectorAll('input[type="file"]').forEach(function (input) {
			input.addEventListener('change', function () {
				var file = input.files && input.files[0];
				if (!file) { return; }
				uploadImage(file, block.id, input.getAttribute('data-upload-field') || 'image_src');
			});
		});
	}

	// ── Image upload ──────────────────────────────────────────────────────────
	function uploadImage(file, blockId, fieldName) {
		var csrf = document.querySelector('#pageEditorForm [name="csrf_token"]');
		var fd   = new FormData();
		fd.append('image', file);
		fd.append('csrf_token', csrf ? csrf.value : '');

		var prog = document.createElement('div');
		prog.className = 'insp-upload-progress';
		prog.textContent = 'Przesyłanie…';
		if (inspector) { inspector.appendChild(prog); }

		var xhr = new XMLHttpRequest();
		xhr.open('POST', window.CMS_UPLOAD_URL || 'upload.php');

		xhr.upload.addEventListener('progress', function (e) {
			if (e.lengthComputable) {
				prog.textContent = 'Przesyłanie… ' + Math.round((e.loaded / e.total) * 100) + '%';
			}
		});
		xhr.onload = function () {
			if (prog.parentNode) { prog.parentNode.removeChild(prog); }
			if (xhr.status >= 200 && xhr.status < 300) {
				var resp; try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {}; }
				if (resp.url) {
					var upd = {}; upd[fieldName] = resp.url;
					updateBlockAndHistory(blockId, upd);
					state.selectedId = blockId;
					renderInspector();
				} else { window.alert('Błąd: ' + (resp.error || 'Nieznany błąd')); }
			} else { window.alert('Błąd przesyłania (HTTP ' + xhr.status + ')'); }
		};
		xhr.onerror = function () {
			if (prog.parentNode) { prog.parentNode.removeChild(prog); }
			window.alert('Błąd połączenia podczas przesyłania');
		};
		xhr.send(fd);
	}

	// ── Save hidden ───────────────────────────────────────────────────────────
	function saveHidden() { if (hiddenInput) { hiddenInput.value = JSON.stringify(state.blocks); } }

	// ── Global canvas click (deselect) ────────────────────────────────────────
	canvas.addEventListener('click', function (e) {
		if (!e.target.closest('.canvas-block')) {
			state.selectedId = null;
			canvas.querySelectorAll('.canvas-block').forEach(function (el) { el.classList.remove('is-selected'); });
			renderInspector();
		}
	});

	// ── Palette tiles ─────────────────────────────────────────────────────────
	document.querySelectorAll('[data-palette-add]').forEach(function (btn) {
		btn.addEventListener('click', function () { addBlock(btn.getAttribute('data-palette-add') || 'text'); });
	});

	// ── Breakpoint switcher ───────────────────────────────────────────────────
	document.querySelectorAll('[data-canvas-bp]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll('[data-canvas-bp]').forEach(function (b) { b.classList.remove('active'); });
			btn.classList.add('active');
			canvas.className = 'bld-canvas bld-canvas-' + (btn.getAttribute('data-canvas-bp') || 'desktop');
		});
	});

	// ── Undo / Redo ───────────────────────────────────────────────────────────
	var undoBtn = document.getElementById('bldUndo');
	var redoBtn = document.getElementById('bldRedo');
	if (undoBtn) { undoBtn.addEventListener('click', function () { if (state.historyIndex > 0) { restoreHistory(state.historyIndex - 1); } }); }
	if (redoBtn) { redoBtn.addEventListener('click', function () { if (state.historyIndex < state.history.length - 1) { restoreHistory(state.historyIndex + 1); } }); }
	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey||e.metaKey) && !e.shiftKey && e.key==='z') { e.preventDefault(); if (state.historyIndex > 0) { restoreHistory(state.historyIndex - 1); } }
		if (((e.ctrlKey||e.metaKey) && e.shiftKey && e.key==='z') || ((e.ctrlKey||e.metaKey) && e.key==='y')) { e.preventDefault(); if (state.historyIndex < state.history.length - 1) { restoreHistory(state.historyIndex + 1); } }
	});

	// ── Builder window open/close ─────────────────────────────────────────────
	var bldShell    = document.getElementById('builderWindowShell');
	var bldBackdrop = document.getElementById('builderWindowBackdrop');
	var openBtn     = document.getElementById('openBuilderWindowBtn');
	var closeBtn    = document.getElementById('closeBuilderWindowBtn');

	function openBld()  { if(bldShell){bldShell.classList.add('open');}    if(bldBackdrop){bldBackdrop.classList.add('open');}    document.body.classList.add('builder-window-open'); }
	function closeBld() { if(bldShell){bldShell.classList.remove('open');} if(bldBackdrop){bldBackdrop.classList.remove('open');} document.body.classList.remove('builder-window-open'); }

	if (openBtn)    { openBtn.addEventListener('click', openBld); }
	if (closeBtn)   { closeBtn.addEventListener('click', closeBld); }
	if (bldBackdrop){ bldBackdrop.addEventListener('click', closeBld); }
	document.addEventListener('keydown', function (e) { if (e.key==='Escape' && bldShell && bldShell.classList.contains('open')) { closeBld(); } });

	// ── Form submit sync ──────────────────────────────────────────────────────
	var form = document.getElementById('pageEditorForm');
	if (form) { form.addEventListener('submit', saveHidden); }

	// ── Init ──────────────────────────────────────────────────────────────────
	state.blocks = (Array.isArray(window.CMS_BUILDER_BLOCKS) ? window.CMS_BUILDER_BLOCKS : []).map(normalize);
	rerender();
	pushHistory();

	window.cmsBuilderLoad = function (blocks) {
		state.blocks = Array.isArray(blocks) ? blocks.map(normalize) : [];
		state.selectedId = null;
		state.history = []; state.historyIndex = -1;
		rerender(); pushHistory();
	};

}());

/* ── Auto-save draft + page preview ─────────────────────────────────────── */
(function () {
	'use strict';
	var form = document.getElementById('pageEditorForm');
	if (!form) { return; }

	var draftKey      = typeof window.CMS_DRAFT_KEY === 'string'        ? window.CMS_DRAFT_KEY        : null;
	var previewUrl    = typeof window.CMS_PAGE_PREVIEW_URL === 'string' ? window.CMS_PAGE_PREVIEW_URL : '';
	var autosaveBadge = document.getElementById('autosaveBadge');
	var isDirty       = false;

	function snap() {
		var s = {};
		Array.prototype.forEach.call(form.elements, function (el) {
			if (!el.name) { return; }
			s[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
		});
		return JSON.stringify(s);
	}
	function badge(cls, txt) {
		if (!autosaveBadge) { return; }
		autosaveBadge.className = 'autosave-badge' + (cls ? ' ' + cls : '');
		autosaveBadge.textContent = txt;
	}
	function dirty() {
		isDirty = true;
		if (draftKey) { try { localStorage.setItem(draftKey, snap()); } catch (e) {} }
		badge('dirty', '● Niezapisane zmiany');
	}

	if (draftKey) {
		var saved = null;
		try { saved = localStorage.getItem(draftKey); } catch (e) {}
		if (saved) {
			try {
				var s = JSON.parse(saved);
				var skip = { csrf_token:1, action:1, page_id:1, edit_lang:1 };
				Object.keys(s).forEach(function (k) {
					if (skip[k] || k==='builder_data') { return; }
					var el = form.querySelector('[name="'+k+'"]');
					if (!el) { return; }
					if (el.type==='checkbox') { el.checked = s[k]==='1'; } else { el.value = s[k]; }
				});
				if (s.builder_data && typeof window.cmsBuilderLoad === 'function') {
					try { var bl = JSON.parse(s.builder_data); if (Array.isArray(bl)) { window.cmsBuilderLoad(bl); } } catch (e) {}
				}
				badge('dirty', '● Odzyskano szkic — zapisz aby zachować');
				isDirty = true;
			} catch (e) {}
		}
	}

	form.addEventListener('input', dirty);
	form.addEventListener('change', dirty);
	document.addEventListener('cms:builder:change', dirty);
	form.addEventListener('submit', function () {
		if (draftKey) { try { localStorage.removeItem(draftKey); } catch (e) {} }
		isDirty = false; badge('saved', '✓ Zapisano');
	});
	window.addEventListener('beforeunload', function (e) { if (isDirty) { e.preventDefault(); e.returnValue = ''; } });
	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); } }
	});

	var pBtn  = document.getElementById('pagePreviewBtn');
	var pOvl  = document.getElementById('pagePreviewOverlay');
	var pFr   = document.getElementById('pagePreviewFrame');
	var pCl   = document.getElementById('pagePreviewClose');

	function openP()  { if (!previewUrl) { window.alert('Zapisz stronę.'); return; } if(pFr){pFr.src=previewUrl;} if(pOvl){pOvl.classList.add('open');} }
	function closeP() { if(pOvl){pOvl.classList.remove('open');} if(pFr){setTimeout(function(){pFr.src='';},200);} }

	if (pBtn) { pBtn.addEventListener('click', openP); }
	if (pCl)  { pCl.addEventListener('click', closeP); }
	document.addEventListener('keydown', function (e) { if (e.key==='Escape' && pOvl && pOvl.classList.contains('open')) { closeP(); } });
}());
