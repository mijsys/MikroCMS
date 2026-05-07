(function () {
    'use strict';

    var list = document.getElementById('builderListV2');
    var hidden = document.getElementById('builderDataInputV2');
    var canvas = document.getElementById('builderCanvasGrid');
    var autoLayoutBtn = document.getElementById('builderAutoLayoutBtn');
    var toggleAdvancedBtn = document.getElementById('builderToggleAdvancedBtn');
    var liveContent = document.getElementById('builderLiveContent');
    var liveWrap = liveContent ? liveContent.closest('.builder-live-wrap') : null;
    var liveBreakpoints = document.getElementById('builderLiveBreakpoints');
    var outlineList = document.getElementById('builderOutlineList');
    var fallbackContentField = document.querySelector('#pageEditorForm [name="content"]');
    if (!list || !hidden) {
        return;
    }

    var initial = Array.isArray(window.CMS_BUILDER_BLOCKS) ? window.CMS_BUILDER_BLOCKS : [];
    var historyStack = [];
    var historyIndex = -1;
    var isApplyingHistory = false;
    var selectedIndex = -1;
    var layoutAnchorIndex = -1;
    var advancedMode = localStorage.getItem('cms_builder_advanced_mode') === '1';

    function setAdvancedMode(nextMode, skipSync) {
        advancedMode = !!nextMode;
        localStorage.setItem('cms_builder_advanced_mode', advancedMode ? '1' : '0');
        document.body.classList.toggle('builder-mode-advanced', advancedMode);
        if (toggleAdvancedBtn) {
            toggleAdvancedBtn.setAttribute('aria-pressed', advancedMode ? 'true' : 'false');
            toggleAdvancedBtn.textContent = advancedMode ? 'Tryb prosty' : 'Tryb zaawansowany';
        }
        if (!skipSync) {
            sync();
        }
    }

    function esc(v) {
        return String(v || '').replace(/[&<>"']/g, function (ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[ch];
        });
    }

    function defaults(type) {
        return {
            type: type || 'text',
            title: '',
            text: '',
            background_color: '#ffffff',
            background_image: '',
            background_attachment: 'scroll',
            min_height: '420',
            align: 'left',
            button_text: '',
            button_url: '',
            image_url: '',
            image_alt: '',
            gallery_urls: '',
            container_columns: '2',
            container_items_json: '[\n  {"title":"Karta 1","text":"Opis elementu"},\n  {"title":"Karta 2","text":"Opis elementu"}\n]',
            plugin_slug: '',
            section_theme: 'default',
            typography_scale: 'md',
            layout_x: '0',
            layout_y: '0',
            layout_w: '12',
            layout_h: '2'
        };
    }

    function makeItem(type, data) {
        var block = Object.assign(defaults(type), data || {});
        var item = document.createElement('div');
        item.className = 'builder-item';
        item.draggable = true;

        item.innerHTML = '' +
            '<div class="builder-head">' +
                '<div><span class="builder-handle">Przeciagnij</span> <strong>' + esc(String(block.type).toUpperCase()) + '</strong></div>' +
                    '<div style="display:inline-flex;gap:8px;align-items:center">' +
                        '<button type="button" class="btn secondary" data-duplicate-block>Duplikuj</button>' +
                        '<button type="button" class="btn danger" data-remove-block>Usun sekcje</button>' +
                    '</div>' +
            '</div>' +
                '<div class="builder-section-preview" data-section-preview style="height:' + esc(block.min_height) + 'px">' +
                    '<div class="builder-section-overlay">' +
                        '<strong data-preview-title>' + esc(block.title || 'Sekcja bez tytulu') + '</strong>' +
                        '<span data-preview-type>' + esc(String(block.type).toUpperCase()) + '</span>' +
                        '<small data-preview-text>' + esc((String(block.text || '').trim().slice(0, 90)) || 'Brak tresci') + '</small>' +
                    '</div>' +
                    '<div class="builder-resize-handle" data-resize-handle title="Przeciagnij, aby zmienic wysokosc sekcji"></div>' +
                '</div>' +
            '<div class="builder-fields">' +
                '<div><label>Typ bloku</label><select data-field="type">' +
                    '<option value="hero">Hero</option>' +
                    '<option value="text">Text</option>' +
                    '<option value="image">Image</option>' +
                    '<option value="container">Container</option>' +
                    '<option value="gallery">Gallery</option>' +
                    '<option value="plugin_slot">Plugin Slot</option>' +
                '</select></div>' +
                '<div><label>Wyrownanie</label><select data-field="align">' +
                    '<option value="left">Lewo</option>' +
                    '<option value="center">Srodek</option>' +
                    '<option value="right">Prawo</option>' +
                '</select></div>' +
                '<div><label>Tytul</label><input type="text" data-field="title" value="' + esc(block.title) + '"></div>' +
                '<div><label>Minimalna wysokosc</label><input type="number" min="200" max="1200" step="10" data-field="min_height" value="' + esc(block.min_height) + '"></div>' +
                '<div><label>Motyw sekcji</label><select data-field="section_theme"><option value="default">Default</option><option value="ocean">Ocean</option><option value="sunset">Sunset</option><option value="forest">Forest</option><option value="mono">Mono</option></select></div>' +
                '<div><label>Skala typografii</label><select data-field="typography_scale"><option value="sm">Small</option><option value="md">Medium</option><option value="lg">Large</option><option value="xl">Extra Large</option></select></div>' +
                '<div class="full builder-style-presets">' +
                    '<label>Style sekcji</label>' +
                    '<div class="builder-style-preset-row">' +
                        '<button type="button" class="btn ghost" data-style-theme-preset="default">Clean</button>' +
                        '<button type="button" class="btn ghost" data-style-theme-preset="ocean">Ocean</button>' +
                        '<button type="button" class="btn ghost" data-style-theme-preset="sunset">Sunset</button>' +
                        '<button type="button" class="btn ghost" data-style-theme-preset="forest">Forest</button>' +
                        '<button type="button" class="btn ghost" data-style-theme-preset="mono">Mono</button>' +
                    '</div>' +
                '</div>' +
                '<div class="full builder-layout-simple"><label>Szerokosc sekcji</label><div class="builder-size-presets">' +
                    '<button type="button" class="btn ghost" data-size-preset="12">Pelna</button>' +
                    '<button type="button" class="btn ghost" data-size-preset="8">2/3</button>' +
                    '<button type="button" class="btn ghost" data-size-preset="6">1/2</button>' +
                    '<button type="button" class="btn ghost" data-size-preset="4">1/3</button>' +
                '</div></div>' +
                '<div class="builder-layout-advanced"><label>Grid X (0-11)</label><input type="number" min="0" max="11" step="1" data-field="layout_x" value="' + esc(block.layout_x) + '"></div>' +
                '<div class="builder-layout-advanced"><label>Grid Y (0-200)</label><input type="number" min="0" max="200" step="1" data-field="layout_y" value="' + esc(block.layout_y) + '"></div>' +
                '<div class="builder-layout-advanced"><label>Grid W (1-12)</label><input type="number" min="1" max="12" step="1" data-field="layout_w" value="' + esc(block.layout_w) + '"></div>' +
                '<div class="builder-layout-advanced"><label>Grid H (1-12)</label><input type="number" min="1" max="12" step="1" data-field="layout_h" value="' + esc(block.layout_h) + '"></div>' +
                '<div class="full"><label>Tekst</label><textarea data-field="text">' + esc(block.text) + '</textarea></div>' +
                '<div><label>Kolor tla</label><input type="color" data-field="background_color" value="' + esc(block.background_color) + '"></div>' +
                '<div><label>Zachowanie tla</label><select data-field="background_attachment"><option value="scroll">Przewija sie</option><option value="fixed">Nieruchome</option></select></div>' +
                '<div class="full"><label>Adres obrazka tla</label><input type="url" data-field="background_image" value="' + esc(block.background_image) + '" placeholder="https://..."></div>' +
                '<div><label>Tekst przycisku</label><input type="text" data-field="button_text" value="' + esc(block.button_text) + '"></div>' +
                '<div><label>URL przycisku</label><input type="url" data-field="button_url" value="' + esc(block.button_url) + '" placeholder="https://..."></div>' +
                '<div><label>Adres obrazka elementu</label><input type="url" data-field="image_url" value="' + esc(block.image_url) + '" placeholder="https://..."></div>' +
                '<div><label>ALT obrazka</label><input type="text" data-field="image_alt" value="' + esc(block.image_alt) + '"></div>' +
                '<div><label>Kolumny kontenera</label><select data-field="container_columns"><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select></div>' +
                '<div class="full">' +
                    '<label>Elementy kontenera (karty / przyciski)</label>' +
                    '<div class="container-editor" data-container-editor>' +
                        '<div class="container-editor-toolbar"><button type="button" class="btn ghost" data-container-add-item>+ Dodaj element</button></div>' +
                        '<div class="container-editor-empty" data-container-editor-empty>Brak elementow kontenera.</div>' +
                        '<div class="container-editor-list" data-container-editor-list></div>' +
                    '</div>' +
                    '<textarea data-field="container_items_json" data-container-raw style="display:none" placeholder="[{\"title\":\"...\",\"text\":\"...\"}]">' + esc(block.container_items_json) + '</textarea>' +
                '</div>' +
                '<div class="full"><label>URL obrazkow galerii (po jednym w linii)</label><textarea data-field="gallery_urls" placeholder="https://...\nhttps://...">' + esc(block.gallery_urls) + '</textarea></div>' +
                '<div><label>Slug pluginu (slot)</label><input type="text" data-field="plugin_slug" value="' + esc(block.plugin_slug) + '" placeholder="np. comments"></div>' +
            '</div>';

        item.querySelector('[data-field="type"]').value = block.type;
        item.querySelector('[data-field="align"]').value = block.align;
        item.querySelector('[data-field="background_attachment"]').value = block.background_attachment;
        item.querySelector('[data-field="container_columns"]').value = String(block.container_columns || '2');
        item.querySelector('[data-field="section_theme"]').value = String(block.section_theme || 'default');
        item.querySelector('[data-field="typography_scale"]').value = String(block.typography_scale || 'md');

        bindItem(item);
        return item;
    }

    function parseContainerItems(raw) {
        var parsed;
        try {
            parsed = JSON.parse(String(raw || '[]'));
        } catch (e) {
            parsed = [];
        }
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.filter(function (entry) { return entry && typeof entry === 'object'; }).map(function (entry) {
            return {
                title: String(entry.title || ''),
                text: String(entry.text || ''),
                image: String(entry.image || ''),
                button_text: String(entry.button_text || ''),
                button_url: String(entry.button_url || '')
            };
        });
    }

    function containerItemCard(data) {
        var card = document.createElement('div');
        card.className = 'container-editor-item';
        card.draggable = true;
        card.innerHTML = '' +
            '<div class="container-editor-head">' +
                '<div><span class="container-editor-handle">Przeciagnij</span> <strong>Element kontenera</strong></div>' +
                '<button type="button" class="btn danger" data-container-remove-item>Usun</button>' +
            '</div>' +
            '<div class="container-editor-fields">' +
                '<div><label>Tytul</label><input type="text" data-ci-field="title" value="' + esc(data.title) + '"></div>' +
                '<div><label>Obraz (URL)</label><input type="url" data-ci-field="image" value="' + esc(data.image) + '" placeholder="https://..."></div>' +
                '<div class="full"><label>Opis</label><textarea data-ci-field="text">' + esc(data.text) + '</textarea></div>' +
                '<div><label>Tekst przycisku</label><input type="text" data-ci-field="button_text" value="' + esc(data.button_text) + '"></div>' +
                '<div><label>URL przycisku</label><input type="url" data-ci-field="button_url" value="' + esc(data.button_url) + '" placeholder="https://..."></div>' +
            '</div>';
        return card;
    }

    function refreshContainerEmpty(item) {
        var editor = item.querySelector('[data-container-editor]');
        if (!editor) {
            return;
        }
        var empty = editor.querySelector('[data-container-editor-empty]');
        var listEl = editor.querySelector('[data-container-editor-list]');
        if (!empty || !listEl) {
            return;
        }
        empty.style.display = listEl.querySelector('.container-editor-item') ? 'none' : 'block';
    }

    function syncContainerRaw(item) {
        var editor = item.querySelector('[data-container-editor]');
        var rawField = item.querySelector('[data-container-raw]');
        if (!editor || !rawField) {
            return;
        }
        var listEl = editor.querySelector('[data-container-editor-list]');
        if (!listEl) {
            return;
        }

        var out = [];
        listEl.querySelectorAll('.container-editor-item').forEach(function (card) {
            var one = {};
            card.querySelectorAll('[data-ci-field]').forEach(function (field) {
                one[field.getAttribute('data-ci-field')] = field.value;
            });
            out.push(one);
        });
        rawField.value = JSON.stringify(out);
    }

    function initContainerEditor(item) {
        var editor = item.querySelector('[data-container-editor]');
        var rawField = item.querySelector('[data-container-raw]');
        if (!editor || !rawField || editor.getAttribute('data-init') === '1') {
            return;
        }
        editor.setAttribute('data-init', '1');

        var addBtn = editor.querySelector('[data-container-add-item]');
        var listEl = editor.querySelector('[data-container-editor-list]');
        if (!addBtn || !listEl) {
            return;
        }

        function bindCard(card) {
            card.querySelectorAll('[data-ci-field]').forEach(function (field) {
                field.addEventListener('input', function () {
                    syncContainerRaw(item);
                    sync();
                });
                field.addEventListener('change', function () {
                    syncContainerRaw(item);
                    sync();
                });
            });

            var removeBtn = card.querySelector('[data-container-remove-item]');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    card.remove();
                    refreshContainerEmpty(item);
                    syncContainerRaw(item);
                    sync();
                });
            }

            card.addEventListener('dragstart', function () {
                card.classList.add('dragging');
            });

            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                syncContainerRaw(item);
                sync();
            });
        }

        listEl.addEventListener('dragover', function (e) {
            e.preventDefault();
            var dragging = listEl.querySelector('.container-editor-item.dragging');
            if (!dragging) {
                return;
            }
            var after = Array.prototype.slice.call(listEl.querySelectorAll('.container-editor-item:not(.dragging)')).find(function (el) {
                var rect = el.getBoundingClientRect();
                return e.clientY < rect.top + rect.height / 2;
            });
            if (after) {
                listEl.insertBefore(dragging, after);
            } else {
                listEl.appendChild(dragging);
            }
        });

        addBtn.addEventListener('click', function () {
            var card = containerItemCard({ title: '', text: '', image: '', button_text: '', button_url: '' });
            listEl.appendChild(card);
            bindCard(card);
            refreshContainerEmpty(item);
            syncContainerRaw(item);
            sync();
        });

        parseContainerItems(rawField.value).forEach(function (entry) {
            var card = containerItemCard(entry);
            listEl.appendChild(card);
            bindCard(card);
        });

        refreshContainerEmpty(item);
        syncContainerRaw(item);
    }

    function readPayloadFromDom() {
        var payload = [];
        list.querySelectorAll('.builder-item').forEach(function (item) {
            syncContainerRaw(item);
            var data = {};
            item.querySelectorAll('[data-field]').forEach(function (field) {
                data[field.getAttribute('data-field')] = field.value;
            });

            data.layout_x = String(Math.max(0, Math.min(11, parseInt(String(data.layout_x || '0'), 10) || 0)));
            data.layout_y = String(Math.max(0, Math.min(200, parseInt(String(data.layout_y || '0'), 10) || 0)));
            data.layout_w = String(Math.max(1, Math.min(12, parseInt(String(data.layout_w || '12'), 10) || 12)));
            data.layout_h = String(Math.max(1, Math.min(12, parseInt(String(data.layout_h || '2'), 10) || 2)));
            payload.push(data);
        });
        return payload;
    }

    function normalizeRect(block) {
        var x = Math.max(0, Math.min(11, parseInt(String(block.layout_x || '0'), 10) || 0));
        var y = Math.max(0, Math.min(200, parseInt(String(block.layout_y || '0'), 10) || 0));
        var w = Math.max(1, Math.min(12, parseInt(String(block.layout_w || '12'), 10) || 12));
        var h = Math.max(1, Math.min(12, parseInt(String(block.layout_h || '2'), 10) || 2));
        if (x + w > 12) {
            w = 12 - x;
        }
        return { x: x, y: y, w: w, h: h };
    }

    function rectsOverlap(a, b) {
        return a.x < (b.x + b.w)
            && (a.x + a.w) > b.x
            && a.y < (b.y + b.h)
            && (a.y + a.h) > b.y;
    }

    function autoArrangePayload(payload, anchorIndex) {
        if (!Array.isArray(payload) || payload.length < 2) {
            return payload;
        }

        var arranged = payload.map(function (block) {
            var rect = normalizeRect(block || {});
            return Object.assign({}, block || {}, {
                layout_x: String(rect.x),
                layout_y: String(rect.y),
                layout_w: String(rect.w),
                layout_h: String(rect.h)
            });
        });

        var order = arranged.map(function (_, idx) { return idx; });
        order.sort(function (a, b) {
            var ra = normalizeRect(arranged[a]);
            var rb = normalizeRect(arranged[b]);
            if (ra.y !== rb.y) { return ra.y - rb.y; }
            return ra.x - rb.x;
        });

        if (anchorIndex >= 0 && anchorIndex < arranged.length) {
            order = [anchorIndex].concat(order.filter(function (idx) { return idx !== anchorIndex; }));
        }

        var placed = [];
        order.forEach(function (idx) {
            var rect = normalizeRect(arranged[idx]);
            var guard = 0;
            while (guard < 500) {
                var collides = placed.some(function (one) { return rectsOverlap(rect, one); });
                if (!collides) {
                    break;
                }
                rect.y += 1;
                if (rect.y > 200) {
                    rect.y = 200;
                    break;
                }
                guard += 1;
            }
            arranged[idx].layout_x = String(rect.x);
            arranged[idx].layout_y = String(rect.y);
            arranged[idx].layout_w = String(rect.w);
            arranged[idx].layout_h = String(rect.h);
            placed.push(rect);
        });

        return arranged;
    }

    function applySimpleStackLayout(payload) {
        if (!Array.isArray(payload) || payload.length === 0) {
            return [];
        }
        var cursorY = 0;
        return payload.map(function (block) {
            var rect = normalizeRect(block || {});
            var nextH = Math.max(2, Math.min(6, rect.h || 2));
            var nextW = Math.max(1, Math.min(12, rect.w || 12));
            var nextX = nextW >= 12 ? 0 : Math.floor((12 - nextW) / 2);
            var out = Object.assign({}, block || {}, {
                layout_x: String(nextX),
                layout_y: String(cursorY),
                layout_w: String(nextW),
                layout_h: String(nextH)
            });
            cursorY += nextH;
            return out;
        });
    }

    function payloadLayoutChanged(a, b) {
        if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length) {
            return true;
        }
        for (var i = 0; i < a.length; i += 1) {
            var aa = a[i] || {};
            var bb = b[i] || {};
            if (String(aa.layout_x || '') !== String(bb.layout_x || '')
                || String(aa.layout_y || '') !== String(bb.layout_y || '')
                || String(aa.layout_w || '') !== String(bb.layout_w || '')
                || String(aa.layout_h || '') !== String(bb.layout_h || '')) {
                return true;
            }
        }
        return false;
    }

    function syncLayoutFieldsFromPayload(payload) {
        if (!Array.isArray(payload)) {
            return;
        }
        var items = list.querySelectorAll('.builder-item');
        payload.forEach(function (block, idx) {
            var item = items[idx];
            if (!item) {
                return;
            }
            var xField = item.querySelector('[data-field="layout_x"]');
            var yField = item.querySelector('[data-field="layout_y"]');
            var wField = item.querySelector('[data-field="layout_w"]');
            var hField = item.querySelector('[data-field="layout_h"]');
            if (!xField || !yField || !wField || !hField) {
                return;
            }
            xField.value = String(block.layout_x || '0');
            yField.value = String(block.layout_y || '0');
            wField.value = String(block.layout_w || '12');
            hField.value = String(block.layout_h || '2');
        });
    }

    function moveEditorItem(index, direction) {
        var items = list.querySelectorAll('.builder-item');
        if (!items.length || index < 0 || index >= items.length) {
            return;
        }
        var toIndex = index + direction;
        if (toIndex < 0 || toIndex >= items.length) {
            return;
        }

        var current = items[index];
        var target = items[toIndex];
        if (!current || !target) {
            return;
        }

        if (direction < 0) {
            list.insertBefore(current, target);
        } else {
            list.insertBefore(target, current);
        }
        sync();
        setSelectedIndex(toIndex);
    }

    function removeEditorItem(index) {
        var items = list.querySelectorAll('.builder-item');
        if (!items.length || index < 0 || index >= items.length) {
            return;
        }
        var target = items[index];
        if (!target) {
            return;
        }
        target.remove();
        renderEmpty();
        selectedIndex = -1;
        sync();
    }

    function reorderEditorItemRelative(fromIndex, targetIndex, placeAfter) {
        var items = Array.prototype.slice.call(list.querySelectorAll('.builder-item'));
        if (!items.length || fromIndex < 0 || targetIndex < 0 || fromIndex >= items.length || targetIndex >= items.length) {
            return;
        }
        if (fromIndex === targetIndex && !placeAfter) {
            return;
        }

        var moving = items[fromIndex];
        var target = items[targetIndex];
        if (!moving || !target || moving === target) {
            return;
        }

        if (placeAfter) {
            list.insertBefore(moving, target.nextSibling);
        } else {
            list.insertBefore(moving, target);
        }

        var reordered = Array.prototype.slice.call(list.querySelectorAll('.builder-item'));
        var nextIndex = reordered.indexOf(moving);
        sync();
        setSelectedIndex(nextIndex >= 0 ? nextIndex : -1);
    }

    function duplicateEditorItem(index) {
        var items = list.querySelectorAll('.builder-item');
        if (!items.length || index < 0 || index >= items.length) {
            return;
        }
        var payload = readPayloadFromDom();
        if (!Array.isArray(payload) || !payload[index]) {
            return;
        }

        var source = payload[index];
        var rect = normalizeRect(source);
        var clone = Object.assign({}, source, {
            title: String(source.title || '').trim() ? String(source.title) + ' (kopia)' : 'Sekcja (kopia)',
            layout_x: String(rect.x),
            layout_y: String(Math.min(200, rect.y + 1)),
            layout_w: String(rect.w),
            layout_h: String(rect.h)
        });

        var node = makeItem(clone.type || 'text', clone);
        var insertAfter = items[index];
        if (insertAfter && insertAfter.nextSibling) {
            list.insertBefore(node, insertAfter.nextSibling);
        } else {
            list.appendChild(node);
        }
        renderEmpty();
        layoutAnchorIndex = index + 1;
        sync();
        setSelectedIndex(index + 1);
    }

    function setSelectedIndex(index) {
        selectedIndex = typeof index === 'number' ? index : -1;

        list.querySelectorAll('.builder-item').forEach(function (item, idx) {
            item.classList.toggle('builder-item-selected', idx === selectedIndex);
        });

        if (canvas) {
            canvas.querySelectorAll('.builder-canvas-item').forEach(function (card) {
                var idx = parseInt(String(card.getAttribute('data-canvas-index') || '-1'), 10);
                card.classList.toggle('selected', idx === selectedIndex);
            });
        }

        if (outlineList) {
            outlineList.querySelectorAll('.builder-outline-item').forEach(function (row) {
                var idx = parseInt(String(row.getAttribute('data-outline-index') || '-1'), 10);
                row.classList.toggle('selected', idx === selectedIndex);
            });
        }

        if (liveContent) {
            liveContent.querySelectorAll('.builder-live-section').forEach(function (section) {
                var idx = parseInt(String(section.getAttribute('data-live-index') || '-1'), 10);
                section.classList.toggle('selected', idx === selectedIndex);
            });
        }
    }

    function renderOutline(payload) {
        if (!outlineList) {
            return;
        }
        outlineList.innerHTML = '';

        if (!Array.isArray(payload) || payload.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'builder-empty';
            empty.textContent = 'Brak sekcji do pokazania w outline.';
            outlineList.appendChild(empty);
            return;
        }

        var ordered = payload.map(function (block, idx) {
            var rect = normalizeRect(block || {});
            return { idx: idx, block: block || {}, rect: rect };
        }).sort(function (a, b) {
            if (a.rect.y !== b.rect.y) { return a.rect.y - b.rect.y; }
            return a.rect.x - b.rect.x;
        });

        ordered.forEach(function (entry) {
            var row = document.createElement('div');
            row.className = 'builder-outline-item';
            row.setAttribute('data-outline-index', String(entry.idx));
            row.innerHTML = ''
                + '<button type="button" class="builder-outline-main">'
                + '<strong>#' + (entry.idx + 1) + ' ' + esc(String(entry.block.title || '').trim() || 'Sekcja bez tytulu') + '</strong>'
                + '<span>' + esc(String(entry.block.type || 'text').toUpperCase()) + ' · x' + entry.rect.x + ' y' + entry.rect.y + ' w' + entry.rect.w + ' h' + entry.rect.h + '</span>'
                + '</button>'
                + '<div class="builder-outline-actions">'
                + '<button type="button" class="btn ghost" data-outline-action="duplicate">⧉</button>'
                + '<button type="button" class="btn ghost" data-outline-move="up">↑</button>'
                + '<button type="button" class="btn ghost" data-outline-move="down">↓</button>'
                + '</div>';

            var mainBtn = row.querySelector('.builder-outline-main');
            if (mainBtn) {
                mainBtn.addEventListener('click', function () {
                    setSelectedIndex(entry.idx);
                    focusEditorItem(entry.idx);
                });
            }

            row.querySelectorAll('[data-outline-move]').forEach(function (btn) {
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    var dir = btn.getAttribute('data-outline-move') === 'up' ? -1 : 1;
                    moveEditorItem(entry.idx, dir);
                });
            });

            row.querySelectorAll('[data-outline-action="duplicate"]').forEach(function (btn) {
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    duplicateEditorItem(entry.idx);
                });
            });

            outlineList.appendChild(row);
        });

        setSelectedIndex(selectedIndex);
    }

    function nextPresetStartY() {
        var payload = readPayloadFromDom();
        if (!Array.isArray(payload) || payload.length === 0) {
            return 0;
        }
        var end = 0;
        payload.forEach(function (block) {
            var rect = normalizeRect(block || {});
            end = Math.max(end, rect.y + rect.h);
        });
        return Math.min(200, end + 1);
    }

    function presetBlocks(preset) {
        var startY = nextPresetStartY();
        if (preset === 'hero') {
            return [
                {
                    type: 'hero',
                    title: 'Mocny naglowek strony',
                    text: 'Krotki opis wartosci oraz CTA prowadzace do konwersji.',
                    button_text: 'Zobacz wiecej',
                    button_url: '#oferta',
                    background_color: '#eef6ff',
                    align: 'left',
                    layout_x: '0',
                    layout_y: String(startY),
                    layout_w: '12',
                    layout_h: '3'
                }
            ];
        }
        if (preset === 'faq') {
            return [
                {
                    type: 'container',
                    title: 'Najczestsze pytania',
                    text: 'Sekcja FAQ z kluczowymi odpowiedziami dla klientow.',
                    container_columns: '1',
                    container_items_json: '[{"title":"Jak dlugo trwa wdrozenie?","text":"Standardowo od 3 do 7 dni roboczych."},{"title":"Czy moge samodzielnie edytowac tresc?","text":"Tak, wszystkie sekcje sa edytowalne w builderze."}]',
                    layout_x: '0',
                    layout_y: String(startY),
                    layout_w: '12',
                    layout_h: '3'
                }
            ];
        }
        if (preset === 'cta') {
            return [
                {
                    type: 'hero',
                    title: 'Gotowy na start projektu?',
                    text: 'Skontaktuj sie z nami i odbierz darmowa konsultacje.',
                    button_text: 'Umow rozmowe',
                    button_url: '#kontakt',
                    background_color: '#f2f7ff',
                    align: 'center',
                    layout_x: '0',
                    layout_y: String(startY),
                    layout_w: '12',
                    layout_h: '3'
                }
            ];
        }
        if (preset === 'pricing') {
            return [
                {
                    type: 'container',
                    title: 'Pakiety cenowe',
                    text: 'Wybierz plan dopasowany do etapu rozwoju Twojej firmy.',
                    container_columns: '3',
                    container_items_json: '[{"title":"Start","text":"Od 199 zl / miesiac"},{"title":"Pro","text":"Od 499 zl / miesiac"},{"title":"Enterprise","text":"Wycena indywidualna"}]',
                    layout_x: '0',
                    layout_y: String(startY),
                    layout_w: '12',
                    layout_h: '4'
                }
            ];
        }
        if (preset === 'gallery') {
            return [
                {
                    type: 'gallery',
                    title: 'Galeria realizacji',
                    text: 'Wybierz najlepsze kadry i pokaz efekt koncowy projektu.',
                    gallery_urls: 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4\nhttps://images.unsplash.com/photo-1505693416388-ac5ce068fe85\nhttps://images.unsplash.com/photo-1493666438817-866a91353ca9',
                    layout_x: '0',
                    layout_y: String(startY),
                    layout_w: '12',
                    layout_h: '3'
                }
            ];
        }
        return [];
    }

    function pageTemplateBlocks(template) {
        if (template === 'landing_classic') {
            return [
                {
                    type: 'hero',
                    title: 'Zbuduj nowoczesna strone w 1 dzien',
                    text: 'MikroCMS to szybki website builder dla landing page. Uruchom projekt i skaluj sprzedaz.',
                    button_text: 'Rozpocznij teraz',
                    button_url: '#kontakt',
                    align: 'left',
                    background_color: '#e7f3ff',
                    section_theme: 'ocean',
                    typography_scale: 'xl',
                    layout_w: '12',
                    layout_h: '3'
                },
                {
                    type: 'container',
                    title: 'Features',
                    text: 'Najwazniejsze funkcje gotowe do uzycia od razu.',
                    container_columns: '3',
                    container_items_json: '[{"title":"Edycja wizualna","text":"Tworz i zmieniaj sekcje metoda drag and drop."},{"title":"Szybkie szablony","text":"Buduj landing page na gotowych ukladach."},{"title":"SEO ready","text":"Meta title, opis i przyjazne URL."}]',
                    section_theme: 'default',
                    typography_scale: 'md',
                    background_color: '#f8fbff',
                    layout_w: '12',
                    layout_h: '4'
                },
                {
                    type: 'container',
                    title: 'Testimonials',
                    text: 'Opinie klientow, ktorzy wdrozyli strony na MikroCMS.',
                    container_columns: '2',
                    container_items_json: '[{"title":"Anna, e-commerce","text":"Po wdrozeniu landing page konwersja wzrosla o 34%."},{"title":"Marek, agencja","text":"Szybko tworzymy strony dla klientow i latwo je rozwijamy."}]',
                    section_theme: 'sunset',
                    typography_scale: 'md',
                    background_color: '#fff0f6',
                    layout_w: '12',
                    layout_h: '3'
                },
                {
                    type: 'container',
                    title: 'FAQ',
                    text: 'Najczestsze pytania przed startem projektu.',
                    container_columns: '1',
                    container_items_json: '[{"title":"Czy potrzebuje programisty?","text":"Nie, podstawowy landing page zrobisz samodzielnie."},{"title":"Czy moge importowac layout?","text":"Tak, builder obsluguje import i eksport JSON."}]',
                    section_theme: 'mono',
                    typography_scale: 'md',
                    background_color: '#f3f4f6',
                    layout_w: '12',
                    layout_h: '3'
                },
                {
                    type: 'hero',
                    title: 'Gotowy na start?',
                    text: 'Umow krotka rozmowe i uruchom swoj nowy landing page.',
                    button_text: 'Skontaktuj sie',
                    button_url: '#kontakt',
                    align: 'center',
                    section_theme: 'forest',
                    typography_scale: 'lg',
                    background_color: '#eaf9ef',
                    layout_w: '12',
                    layout_h: '3'
                }
            ];
        }

        if (template === 'landing_product') {
            return [
                {
                    type: 'hero',
                    title: 'Premiera nowego produktu',
                    text: 'Pokaz wartosc produktu i zamien odwiedzajacych w klientow.',
                    button_text: 'Kup teraz',
                    button_url: '#oferta',
                    align: 'center',
                    section_theme: 'sunset',
                    typography_scale: 'xl',
                    background_color: '#ffeef6',
                    layout_w: '12',
                    layout_h: '3'
                },
                {
                    type: 'image',
                    title: 'Zobacz produkt',
                    text: 'Zdjecie hero produktu z krotkim opisem.',
                    image_url: 'https://images.unsplash.com/photo-1517336714739-489689fd1ca8',
                    image_alt: 'Produkt',
                    section_theme: 'default',
                    typography_scale: 'md',
                    background_color: '#f7fafc',
                    layout_w: '12',
                    layout_h: '4'
                },
                {
                    type: 'container',
                    title: 'Kluczowe korzysci',
                    text: 'Dlaczego ten produkt rozwiazuje problem szybciej i skuteczniej.',
                    container_columns: '3',
                    container_items_json: '[{"title":"Szybkie wdrozenie","text":"Start nawet tego samego dnia."},{"title":"Intuicyjny panel","text":"Obsluga bez szkolenia."},{"title":"Skalowalnosc","text":"Rozwijaj strone razem z biznesem."}]',
                    section_theme: 'ocean',
                    typography_scale: 'md',
                    background_color: '#e8f5ff',
                    layout_w: '12',
                    layout_h: '4'
                },
                {
                    type: 'container',
                    title: 'Testimonials',
                    text: 'Opinie uzytkownikow po wdrozeniu.',
                    container_columns: '2',
                    container_items_json: '[{"title":"Karolina","text":"W 2 tygodnie zwiekszylismy leady o 42%."},{"title":"Tomasz","text":"Najprostszy system, na jakim pracowalismy."}]',
                    section_theme: 'forest',
                    typography_scale: 'md',
                    background_color: '#ebf8f0',
                    layout_w: '12',
                    layout_h: '3'
                },
                {
                    type: 'hero',
                    title: 'Gotowe? Wlacz kampanie',
                    text: 'Przetestuj szablon, dopracuj szczegoly i publikuj landing.',
                    button_text: 'Wlacz teraz',
                    button_url: '#kontakt',
                    align: 'center',
                    section_theme: 'mono',
                    typography_scale: 'lg',
                    background_color: '#eef2f7',
                    layout_w: '12',
                    layout_h: '3'
                }
            ];
        }

        return [];
    }

    function updateHistoryButtons() {
        var undoBtn = document.getElementById('builderUndoBtn');
        var redoBtn = document.getElementById('builderRedoBtn');
        if (undoBtn) {
            undoBtn.disabled = historyIndex <= 0;
        }
        if (redoBtn) {
            redoBtn.disabled = historyIndex < 0 || historyIndex >= historyStack.length - 1;
        }
    }

    function pushHistory(payload) {
        var snapshot = JSON.stringify(payload);
        if (historyIndex >= 0 && historyStack[historyIndex] === snapshot) {
            updateHistoryButtons();
            return;
        }
        if (historyIndex < historyStack.length - 1) {
            historyStack = historyStack.slice(0, historyIndex + 1);
        }
        historyStack.push(snapshot);
        if (historyStack.length > 80) {
            historyStack.shift();
        }
        historyIndex = historyStack.length - 1;
        updateHistoryButtons();
    }

    function loadPayload(payload, opts) {
        var options = opts || {};
        list.innerHTML = '';
        if (Array.isArray(payload)) {
            payload.forEach(function (item) {
                list.appendChild(makeItem((item && item.type) || 'text', item));
            });
        }
        renderEmpty();
        sync(!options.skipHistory);
        setSelectedIndex(-1);
    }

    function sync(recordHistory) {
        var shouldRecordHistory = recordHistory !== false;
        var payload = readPayloadFromDom();
        var resolved = advancedMode
            ? autoArrangePayload(payload, layoutAnchorIndex)
            : applySimpleStackLayout(payload);
        layoutAnchorIndex = -1;

        if (payloadLayoutChanged(payload, resolved)) {
            syncLayoutFieldsFromPayload(resolved);
            payload = resolved;
        } else {
            payload = resolved;
        }

        hidden.value = JSON.stringify(payload);
        renderCanvas(payload);
        renderOutline(payload);
        renderLiveContent(payload);
        setSelectedIndex(selectedIndex);
        if (shouldRecordHistory && !isApplyingHistory) {
            pushHistory(payload);
        }
        document.dispatchEvent(new CustomEvent('cms:builder:change'));
    }

    function nl2brSafe(text) {
        return esc(String(text || '')).replace(/\n/g, '<br>');
    }

    function firstImageFromGallery(raw) {
        var lines = String(raw || '').split(/\r\n|\r|\n/).map(function (v) { return v.trim(); }).filter(Boolean);
        return lines.length ? lines[0] : '';
    }

    function tryParseJson(raw, fallback) {
        try {
            var parsed = JSON.parse(String(raw || ''));
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function focusEditorItem(index) {
        var target = list.querySelectorAll('.builder-item')[index];
        if (!target) {
            return;
        }
        setSelectedIndex(index);
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('builder-item-focus');
        setTimeout(function () {
            target.classList.remove('builder-item-focus');
        }, 900);
    }

    function setLiveMode(mode) {
        if (!liveWrap) {
            return;
        }
        var normalized = ['desktop', 'tablet', 'mobile'].indexOf(mode) >= 0 ? mode : 'desktop';
        liveWrap.classList.remove('live-mode-desktop', 'live-mode-tablet', 'live-mode-mobile');
        liveWrap.classList.add('live-mode-' + normalized);

        if (!liveBreakpoints) {
            return;
        }
        liveBreakpoints.querySelectorAll('[data-live-breakpoint]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-live-breakpoint') === normalized);
        });
    }

    var liveDragIndex = -1;

    function clearLiveDropIndicators() {
        if (!liveContent) {
            return;
        }
        liveContent.querySelectorAll('.builder-live-section').forEach(function (section) {
            section.classList.remove('drop-before', 'drop-after', 'dragging');
        });
    }

    function renderLiveContent(payload) {
        if (!liveContent) {
            return;
        }
        liveContent.innerHTML = '';

        var siteName = String(window.CMS_LIVE_SITE_NAME || 'My CMS');
        var navItems = Array.isArray(window.CMS_LIVE_NAV_ITEMS) ? window.CMS_LIVE_NAV_ITEMS : [];
        var enabledPlugins = (window.CMS_LIVE_ENABLED_PLUGINS && typeof window.CMS_LIVE_ENABLED_PLUGINS === 'object')
            ? window.CMS_LIVE_ENABLED_PLUGINS
            : {};

        var shell = document.createElement('div');
        shell.className = 'builder-live-shell';
        var header = document.createElement('div');
        header.className = 'builder-live-header';
        var navHtml = navItems.slice(0, 6).map(function (item, idx) {
            var title = String((item && item.title) || '').trim() || ('Pozycja ' + (idx + 1));
            return '<span class="builder-live-nav-item">' + esc(title) + '</span>';
        }).join('');
        if (!navHtml) {
            navHtml = '<span class="builder-live-nav-item">Start</span><span class="builder-live-nav-item">Oferta</span><span class="builder-live-nav-item">Kontakt</span>';
        }
        header.innerHTML = '<div class="builder-live-brand">' + esc(siteName) + '</div><div class="builder-live-nav">' + navHtml + '</div>';
        var main = document.createElement('div');
        main.className = 'builder-live-main';
        shell.appendChild(header);
        shell.appendChild(main);
        liveContent.appendChild(shell);

        if (!Array.isArray(payload) || payload.length === 0) {
            var empty = document.createElement('section');
            empty.className = 'builder-live-section builder-live-empty';
            var fallback = fallbackContentField ? String(fallbackContentField.value || '').trim() : '';
            empty.innerHTML = '<h4>Fallback content</h4><div class="builder-live-text">' + nl2brSafe(fallback ? fallback.slice(0, 1200) : 'Brak blokow i brak tresci fallback.') + '</div>';
            main.appendChild(empty);
            return;
        }

        var ordered = payload.map(function (block, idx) {
            var x = Math.max(0, Math.min(11, parseInt(String(block.layout_x || '0'), 10) || 0));
            var y = Math.max(0, Math.min(200, parseInt(String(block.layout_y || '0'), 10) || 0));
            return { block: block, idx: idx, x: x, y: y };
        }).sort(function (a, b) {
            if (a.y !== b.y) { return a.y - b.y; }
            return a.x - b.x;
        });

        ordered.forEach(function (entry) {
            var block = entry.block;
            var idx = entry.idx;
            var type = String(block.type || 'text');
            var title = String(block.title || '').trim() || ('Sekcja ' + (idx + 1));
            var align = String(block.align || 'left');
            var minHeight = Math.max(180, Math.min(1200, parseInt(String(block.min_height || '420'), 10) || 420));
            var bgColor = String(block.background_color || '#ffffff');
            var bgImage = String(block.background_image || '').trim();
            var bgAttachment = String(block.background_attachment || 'scroll') === 'fixed' ? 'fixed' : 'scroll';

            var section = document.createElement('section');
            section.className = 'builder-live-section builder-live-' + type;
            section.setAttribute('data-live-index', String(idx));
            section.classList.add('builder-live-theme-' + String(block.section_theme || 'default'));
            section.classList.add('builder-live-typo-' + String(block.typography_scale || 'md'));
            section.style.minHeight = Math.min(minHeight, 480) + 'px';
            section.style.textAlign = align;
            section.style.backgroundColor = bgColor;
            if (bgImage) {
                section.style.backgroundImage = 'url(' + esc(bgImage) + ')';
                section.style.backgroundSize = 'cover';
                section.style.backgroundPosition = 'center';
                section.style.backgroundAttachment = bgAttachment;
            }

            var contentHtml = '';
            if (type === 'image' && String(block.image_url || '').trim()) {
                contentHtml += '<div class="builder-live-image-wrap"><img class="builder-live-image" src="' + esc(String(block.image_url || '')) + '" alt="' + esc(String(block.image_alt || '')) + '"></div>';
            }

            if (type === 'gallery') {
                var firstGalleryImage = firstImageFromGallery(block.gallery_urls);
                if (firstGalleryImage) {
                    contentHtml += '<div class="builder-live-image-wrap"><img class="builder-live-image" src="' + esc(firstGalleryImage) + '" alt="' + esc(title) + '"></div>';
                }
            }

            if (type === 'container') {
                var items = tryParseJson(block.container_items_json, []);
                if (items.length) {
                    contentHtml += '<div class="builder-live-container-grid">';
                    items.slice(0, 4).forEach(function (card) {
                        var cardTitle = String((card && card.title) || '').trim();
                        var cardText = String((card && card.text) || '').trim();
                        contentHtml += '<article class="builder-live-container-card">'
                            + (cardTitle ? ('<strong>' + esc(cardTitle) + '</strong>') : '')
                            + (cardText ? ('<p>' + esc(cardText.slice(0, 120)) + '</p>') : '')
                            + '</article>';
                    });
                    contentHtml += '</div>';
                }
            }

            if (type === 'plugin_slot') {
                var pluginSlug = String(block.plugin_slug || '').trim();
                var pluginName = pluginSlug && enabledPlugins[pluginSlug] ? String(enabledPlugins[pluginSlug]) : '';
                contentHtml += '<div class="builder-live-plugin-note">Slot pluginu: '
                    + esc(pluginSlug || 'brak slug')
                    + (pluginName ? (' (' + esc(pluginName) + ')') : '')
                    + '</div>';
            }

            if (title) {
                contentHtml += '<h4 class="builder-live-editable" data-live-edit="title" contenteditable="true" spellcheck="false">' + esc(title) + '</h4>';
            }
            if (String(block.text || '').trim()) {
                contentHtml += '<div class="builder-live-text builder-live-editable" data-live-edit="text" contenteditable="true" spellcheck="false">' + nl2brSafe(String(block.text || '').slice(0, 900)) + '</div>';
            }
            if (String(block.button_text || '').trim() && String(block.button_url || '').trim()) {
                contentHtml += '<a class="builder-live-btn" href="' + esc(String(block.button_url || '')) + '" target="_blank" rel="noopener">' + esc(String(block.button_text || '')) + '</a>';
            }
            if (!contentHtml) {
                contentHtml = '<div class="builder-live-text">Brak tresci.</div>';
            }

            section.innerHTML = '<div class="builder-live-meta">'
                + '<span>#' + (idx + 1) + '</span>'
                + '<span>' + esc(type.toUpperCase()) + '</span>'
                + '<span>x' + entry.x + ' y' + entry.y + '</span>'
                + '<div class="builder-live-toolbar">'
                + '<button type="button" class="btn ghost" data-live-action="move-up" title="Przesun sekcje wyzej">↑</button>'
                + '<button type="button" class="btn ghost" data-live-action="move-down" title="Przesun sekcje nizej">↓</button>'
                + '<button type="button" class="btn ghost" data-live-action="duplicate" title="Duplikuj sekcje">⧉</button>'
                + '<button type="button" class="btn ghost" data-live-action="delete" title="Usun sekcje">✕</button>'
                + '<button type="button" class="btn ghost" data-live-drag-handle title="Przeciagnij sekcje">⋮⋮</button>'
                + '</div>'
                + '</div>'
                + '<div class="builder-live-inner">' + contentHtml + '</div>';

            section.querySelectorAll('[data-live-edit]').forEach(function (editable) {
                editable.addEventListener('blur', function () {
                    var fieldName = editable.getAttribute('data-live-edit');
                    if (!fieldName) {
                        return;
                    }
                    var targetItem = list.querySelectorAll('.builder-item')[idx];
                    if (!targetItem) {
                        return;
                    }
                    var targetField = targetItem.querySelector('[data-field="' + fieldName + '"]');
                    if (!targetField) {
                        return;
                    }

                    var nextValue = String(editable.innerText || '').trim();
                    if (fieldName === 'title') {
                        nextValue = nextValue.replace(/\s*\n\s*/g, ' ').trim();
                    }
                    targetField.value = nextValue;
                    sync();
                });
            });

            section.querySelectorAll('[data-live-action]').forEach(function (btn) {
                btn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var action = btn.getAttribute('data-live-action') || '';
                    if (action === 'move-up') {
                        moveEditorItem(idx, -1);
                        return;
                    }
                    if (action === 'move-down') {
                        moveEditorItem(idx, 1);
                        return;
                    }
                    if (action === 'duplicate') {
                        duplicateEditorItem(idx);
                        return;
                    }
                    if (action === 'delete') {
                        removeEditorItem(idx);
                    }
                });
            });

            section.setAttribute('draggable', 'true');
            section.addEventListener('dragstart', function (ev) {
                var target = ev.target;
                var handle = target && target.closest ? target.closest('[data-live-drag-handle]') : null;
                if (!handle) {
                    ev.preventDefault();
                    return;
                }
                liveDragIndex = idx;
                section.classList.add('dragging');
                if (ev.dataTransfer) {
                    ev.dataTransfer.effectAllowed = 'move';
                    ev.dataTransfer.setData('text/plain', String(idx));
                }
            });

            section.addEventListener('dragover', function (ev) {
                if (liveDragIndex < 0 || liveDragIndex === idx) {
                    return;
                }
                ev.preventDefault();
                var rect = section.getBoundingClientRect();
                var placeAfter = ev.clientY > rect.top + rect.height / 2;
                clearLiveDropIndicators();
                section.classList.add(placeAfter ? 'drop-after' : 'drop-before');
                if (ev.dataTransfer) {
                    ev.dataTransfer.dropEffect = 'move';
                }
            });

            section.addEventListener('drop', function (ev) {
                if (liveDragIndex < 0 || liveDragIndex === idx) {
                    return;
                }
                ev.preventDefault();
                var rect = section.getBoundingClientRect();
                var placeAfter = ev.clientY > rect.top + rect.height / 2;
                var fromIndex = liveDragIndex;
                liveDragIndex = -1;
                clearLiveDropIndicators();
                reorderEditorItemRelative(fromIndex, idx, placeAfter);
            });

            section.addEventListener('dragend', function () {
                liveDragIndex = -1;
                clearLiveDropIndicators();
            });

            section.addEventListener('click', function () {
                if (document.activeElement && section.contains(document.activeElement) && document.activeElement.hasAttribute('data-live-edit')) {
                    return;
                }
                focusEditorItem(idx);
            });

            main.appendChild(section);
        });
    }

    function setLayoutForIndex(index, nextX, nextY, nextW, nextH) {
        if (!advancedMode) {
            return;
        }
        var item = list.querySelectorAll('.builder-item')[index];
        if (!item) {
            return;
        }
        var xField = item.querySelector('[data-field="layout_x"]');
        var yField = item.querySelector('[data-field="layout_y"]');
        var wField = item.querySelector('[data-field="layout_w"]');
        var hField = item.querySelector('[data-field="layout_h"]');
        if (!xField || !yField || !wField || !hField) {
            return;
        }
        xField.value = String(Math.max(0, Math.min(11, nextX)));
        yField.value = String(Math.max(0, Math.min(200, nextY)));
        wField.value = String(Math.max(1, Math.min(12, nextW)));
        hField.value = String(Math.max(1, Math.min(12, nextH)));
        layoutAnchorIndex = index;
        setSelectedIndex(index);
        sync(false);
    }

    function canvasCellSize() {
        if (!canvas) {
            return { w: 100, h: 56 };
        }
        var styles = window.getComputedStyle(canvas);
        var rowH = parseFloat(styles.gridAutoRows || '48') || 48;
        var rect = canvas.getBoundingClientRect();
        return {
            w: rect.width / 12,
            h: rowH + 8
        };
    }

    function renderCanvas(payload) {
        if (!canvas) {
            return;
        }
        canvas.innerHTML = '';
        if (!Array.isArray(payload) || payload.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'builder-canvas-empty';
            empty.textContent = 'Canvas jest pusty. Dodaj sekcje, aby zobaczyc siatke.';
            canvas.appendChild(empty);
            return;
        }

        payload.forEach(function (block, idx) {
            var x = Math.max(0, Math.min(11, parseInt(String(block.layout_x || '0'), 10) || 0));
            var y = Math.max(0, Math.min(200, parseInt(String(block.layout_y || '0'), 10) || 0));
            var w = Math.max(1, Math.min(12, parseInt(String(block.layout_w || '12'), 10) || 12));
            var h = Math.max(1, Math.min(12, parseInt(String(block.layout_h || '2'), 10) || 2));
            if (x + w > 12) {
                w = 12 - x;
            }

            var card = document.createElement('div');
            card.className = 'builder-canvas-item';
            card.setAttribute('data-canvas-index', String(idx));
            if (idx === selectedIndex) {
                card.classList.add('selected');
            }
            card.style.gridColumn = (x + 1) + ' / span ' + w;
            card.style.gridRow = (y + 1) + ' / span ' + h;
            var cardMeta = advancedMode
                ? (esc(String(block.type || 'text').toUpperCase()) + ' • x' + x + ' y' + y + ' w' + w + ' h' + h)
                : (esc(String(block.type || 'text').toUpperCase()) + ' • auto-uklad');
            var simpleSizes = advancedMode ? '' : '<div class="builder-canvas-size-pills">'
                + '<button type="button" class="btn ghost" data-canvas-size="12">Pelna</button>'
                + '<button type="button" class="btn ghost" data-canvas-size="8">2/3</button>'
                + '<button type="button" class="btn ghost" data-canvas-size="6">1/2</button>'
                + '</div>';
            card.innerHTML = '<strong>' + esc(block.title || ('Sekcja ' + (idx + 1))) + '</strong><span>' + cardMeta + '</span>' + simpleSizes + (advancedMode ? '<i class="builder-canvas-resize" title="Przeciagnij aby zmienic rozmiar"></i>' : '');

            card.querySelectorAll('[data-canvas-size]').forEach(function (pill) {
                pill.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var value = parseInt(String(pill.getAttribute('data-canvas-size') || '12'), 10) || 12;
                    var targetItem = list.querySelectorAll('.builder-item')[idx];
                    if (!targetItem) {
                        return;
                    }
                    var widthField = targetItem.querySelector('[data-field="layout_w"]');
                    if (!widthField) {
                        return;
                    }
                    widthField.value = String(Math.max(1, Math.min(12, value)));
                    setSelectedIndex(idx);
                    sync();
                });
            });

            card.addEventListener('click', function () {
                focusEditorItem(idx);
            });

            if (!advancedMode) {
                canvas.appendChild(card);
                return;
            }

            card.addEventListener('mousedown', function (ev) {
                if (ev.target && ev.target.classList && ev.target.classList.contains('builder-canvas-resize')) {
                    return;
                }
                ev.preventDefault();
                setSelectedIndex(idx);
                var size = canvasCellSize();
                var startMouseX = ev.clientX;
                var startMouseY = ev.clientY;
                var startX = x;
                var startY = y;

                function onMove(moveEv) {
                    var nextX = Math.round(startX + (moveEv.clientX - startMouseX) / size.w);
                    var nextY = Math.round(startY + (moveEv.clientY - startMouseY) / size.h);
                    nextX = Math.max(0, Math.min(12 - w, nextX));
                    nextY = Math.max(0, Math.min(200, nextY));
                    setLayoutForIndex(idx, nextX, nextY, w, h);
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    sync();
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });

            var resizeHandle = card.querySelector('.builder-canvas-resize');
            if (resizeHandle) {
                resizeHandle.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    setSelectedIndex(idx);
                    var size = canvasCellSize();
                    var startMouseX = ev.clientX;
                    var startMouseY = ev.clientY;
                    var startW = w;
                    var startH = h;

                    function onMove(moveEv) {
                        var nextW = Math.round(startW + (moveEv.clientX - startMouseX) / size.w);
                        var nextH = Math.round(startH + (moveEv.clientY - startMouseY) / size.h);
                        nextW = Math.max(1, Math.min(12 - x, nextW));
                        nextH = Math.max(1, Math.min(12, nextH));
                        setLayoutForIndex(idx, x, y, nextW, nextH);
                    }

                    function onUp() {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        sync();
                    }

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            }
            canvas.appendChild(card);
        });
    }

    function bindItem(item) {
        initContainerEditor(item);

        var minHeightField = item.querySelector('[data-field="min_height"]');
        var preview = item.querySelector('[data-section-preview]');
        var titleField = item.querySelector('[data-field="title"]');
        var textField = item.querySelector('[data-field="text"]');
        var typeField = item.querySelector('[data-field="type"]');
        var colorField = item.querySelector('[data-field="background_color"]');
        var previewTitle = item.querySelector('[data-preview-title]');
        var previewType = item.querySelector('[data-preview-type]');
        var previewText = item.querySelector('[data-preview-text]');

        function updatePreview() {
            if (!preview || !minHeightField) {
                return;
            }
            var mh = parseInt(String(minHeightField.value || '420'), 10);
            if (isNaN(mh)) {
                mh = 420;
            }
            mh = Math.max(200, Math.min(1200, mh));
            minHeightField.value = String(mh);
            preview.style.height = mh + 'px';

            if (colorField) {
                preview.style.background = colorField.value || '#ffffff';
            }
            if (previewTitle && titleField) {
                previewTitle.textContent = titleField.value ? titleField.value : 'Sekcja bez tytulu';
            }
            if (previewType && typeField) {
                previewType.textContent = String(typeField.value || 'text').toUpperCase();
            }
            if (previewText && textField) {
                var shortText = String(textField.value || '').trim();
                previewText.textContent = shortText ? shortText.slice(0, 90) : 'Brak tresci';
            }
        }

        item.querySelectorAll('[data-field]').forEach(function (field) {
            field.addEventListener('input', function () {
                updatePreview();
                sync(false);
            });
            field.addEventListener('change', function () {
                updatePreview();
                sync();
            });
        });

        item.querySelector('[data-remove-block]').addEventListener('click', function () {
            var all = Array.prototype.slice.call(list.querySelectorAll('.builder-item'));
            var idx = all.indexOf(item);
            if (idx >= 0) {
                removeEditorItem(idx);
            }
        });

        var duplicateBtn = item.querySelector('[data-duplicate-block]');
        if (duplicateBtn) {
            duplicateBtn.addEventListener('click', function () {
                var all = Array.prototype.slice.call(list.querySelectorAll('.builder-item'));
                var idx = all.indexOf(item);
                if (idx >= 0) {
                    duplicateEditorItem(idx);
                }
            });
        }

        item.addEventListener('click', function () {
            var all = Array.prototype.slice.call(list.querySelectorAll('.builder-item'));
            var idx = all.indexOf(item);
            if (idx >= 0) {
                setSelectedIndex(idx);
            }
        });

        item.addEventListener('dragstart', function () {
            item.classList.add('dragging');
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('dragging');
            sync();
        });

        item.querySelectorAll('[data-size-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = parseInt(String(btn.getAttribute('data-size-preset') || '12'), 10) || 12;
                var widthField = item.querySelector('[data-field="layout_w"]');
                if (!widthField) {
                    return;
                }
                widthField.value = String(Math.max(1, Math.min(12, value)));
                sync();
            });
        });

        item.querySelectorAll('[data-style-theme-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var theme = String(btn.getAttribute('data-style-theme-preset') || 'default');
                var themeField = item.querySelector('[data-field="section_theme"]');
                var bgField = item.querySelector('[data-field="background_color"]');
                var typoField = item.querySelector('[data-field="typography_scale"]');
                if (!themeField || !bgField || !typoField) {
                    return;
                }
                var bgByTheme = {
                    'default': '#ffffff',
                    'ocean': '#e8f5ff',
                    'sunset': '#ffeef6',
                    'forest': '#eaf9ef',
                    'mono': '#eef2f7'
                };
                var typoByTheme = {
                    'default': 'md',
                    'ocean': 'lg',
                    'sunset': 'lg',
                    'forest': 'md',
                    'mono': 'sm'
                };
                themeField.value = theme;
                bgField.value = bgByTheme[theme] || '#ffffff';
                typoField.value = typoByTheme[theme] || 'md';
                updatePreview();
                sync();
            });
        });

        var resizeHandle = item.querySelector('[data-resize-handle]');
        if (resizeHandle && minHeightField) {
            resizeHandle.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                var startY = ev.clientY;
                var startH = parseInt(String(minHeightField.value || '420'), 10);

                function onMove(moveEv) {
                    var next = Math.max(200, Math.min(1200, startH + (moveEv.clientY - startY)));
                    minHeightField.value = String(next);
                    updatePreview();
                    sync(false);
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    sync();
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        }

        updatePreview();
    }

    function renderEmpty() {
        var empty = document.getElementById('builderEmptyV2');
        if (!empty) {
            return;
        }
        empty.style.display = list.querySelector('.builder-item') ? 'none' : 'block';
    }

    var dragNewType = null;
    var dropPlaceholder = null;

    function removePlaceholder() {
        if (dropPlaceholder && dropPlaceholder.parentNode) {
            dropPlaceholder.parentNode.removeChild(dropPlaceholder);
        }
        dropPlaceholder = null;
    }

    function getAfterElement(y) {
        return Array.prototype.slice.call(
            list.querySelectorAll('.builder-item:not(.dragging):not(.builder-drop-placeholder)')
        ).find(function (el) {
            var rect = el.getBoundingClientRect();
            return y < rect.top + rect.height / 2;
        });
    }

    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var dragging = list.querySelector('.builder-item.dragging');

        if (dragging) {
            removePlaceholder();
            var after = getAfterElement(e.clientY);
            if (after) {
                list.insertBefore(dragging, after);
            } else {
                list.appendChild(dragging);
            }
            return;
        }

        if (dragNewType) {
            e.dataTransfer.dropEffect = 'copy';
            if (!dropPlaceholder) {
                dropPlaceholder = document.createElement('div');
                dropPlaceholder.className = 'builder-drop-placeholder';
                dropPlaceholder.textContent = '+ Upusc tutaj';
            }
            var afterEl = getAfterElement(e.clientY);
            if (afterEl) {
                list.insertBefore(dropPlaceholder, afterEl);
            } else {
                list.appendChild(dropPlaceholder);
            }
        }
    });

    list.addEventListener('dragleave', function (e) {
        if (!list.contains(/** @type {Node} */ (e.relatedTarget))) {
            removePlaceholder();
        }
    });

    list.addEventListener('drop', function (e) {
        e.preventDefault();
        if (!dragNewType) { return; }
        var newItem = makeItem(dragNewType);
        if (dropPlaceholder && dropPlaceholder.parentNode) {
            list.insertBefore(newItem, dropPlaceholder);
            removePlaceholder();
        } else {
            list.appendChild(newItem);
        }
        renderEmpty();
        sync();
        dragNewType = null;
    });

    if (canvas) {
        canvas.addEventListener('dragover', function (e) {
            if (!dragNewType) {
                return;
            }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            canvas.classList.add('builder-canvas-drop-ready');
        });

        canvas.addEventListener('dragleave', function () {
            canvas.classList.remove('builder-canvas-drop-ready');
        });

        canvas.addEventListener('drop', function (e) {
            if (!dragNewType) {
                return;
            }
            e.preventDefault();
            canvas.classList.remove('builder-canvas-drop-ready');
            list.appendChild(makeItem(dragNewType));
            renderEmpty();
            sync();
            setSelectedIndex(list.querySelectorAll('.builder-item').length - 1);
            dragNewType = null;
        });
    }

    document.querySelectorAll('[data-builder2-add]').forEach(function (btn) {
        btn.draggable = true;
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-builder2-add') || 'text';
            list.appendChild(makeItem(type));
            renderEmpty();
            sync();
        });

        btn.addEventListener('dragstart', function (e) {
            dragNewType = btn.getAttribute('data-builder2-add') || 'text';
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', dragNewType);
            btn.classList.add('toolbar-dragging');
        });

        btn.addEventListener('dragend', function () {
            btn.classList.remove('toolbar-dragging');
            removePlaceholder();
            if (canvas) {
                canvas.classList.remove('builder-canvas-drop-ready');
            }
            dragNewType = null;
        });
    });

    document.querySelectorAll('[data-builder2-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var preset = btn.getAttribute('data-builder2-preset') || '';
            var blocks = presetBlocks(preset);
            if (!blocks.length) {
                return;
            }
            blocks.forEach(function (block) {
                list.appendChild(makeItem(block.type || 'text', block));
            });
            renderEmpty();
            sync();
            setSelectedIndex(list.querySelectorAll('.builder-item').length - 1);
        });
    });

    document.querySelectorAll('[data-builder2-template]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var template = btn.getAttribute('data-builder2-template') || '';
            var blocks = pageTemplateBlocks(template);
            if (!blocks.length) {
                return;
            }

            if (list.querySelector('.builder-item')) {
                var shouldReplace = confirm('Szablon strony zastapi aktualny uklad. Kontynuowac?'); // eslint-disable-line no-alert
                if (!shouldReplace) {
                    return;
                }
            }

            list.innerHTML = '';
            blocks.forEach(function (block) {
                list.appendChild(makeItem(block.type || 'text', block));
            });
            var titleInput = document.querySelector('#pageEditorForm [name="title"]');
            var excerptInput = document.querySelector('#pageEditorForm [name="excerpt"]');
            if (titleInput && !String(titleInput.value || '').trim()) {
                titleInput.value = template === 'landing_product' ? 'Landing Product' : 'Landing Classic';
            }
            if (excerptInput && !String(excerptInput.value || '').trim()) {
                excerptInput.value = 'Strona wygenerowana z gotowego szablonu landing page.';
            }
            renderEmpty();
            sync();
            setSelectedIndex(0);
        });
    });

    var exportBtn = document.getElementById('builderExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            sync();
            var payload = hidden.value || '[]';
            var blob = new Blob([payload], { type: 'application/json;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'cms-builder-layout.json';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        });
    }

    var sectionsFocusBtn = document.getElementById('builderSectionsFocusBtn');
    if (sectionsFocusBtn) {
        sectionsFocusBtn.addEventListener('click', function () {
            list.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    if (autoLayoutBtn) {
        autoLayoutBtn.addEventListener('click', function () {
            sync();
        });
    }

    if (toggleAdvancedBtn) {
        toggleAdvancedBtn.addEventListener('click', function () {
            setAdvancedMode(!advancedMode);
        });
    }

    var openBuilderWindowBtn = document.getElementById('openBuilderWindowBtn');
    var closeBuilderWindowBtn = document.getElementById('closeBuilderWindowBtn');
    var builderWindowShell = document.getElementById('builderWindowShell');
    var builderWindowBackdrop = document.getElementById('builderWindowBackdrop');

    function setBuilderWindow(open) {
        if (!builderWindowShell || !builderWindowBackdrop) {
            return;
        }
        if (open) {
            builderWindowShell.classList.add('open');
            builderWindowBackdrop.classList.add('open');
            document.body.classList.add('builder-window-open');
        } else {
            builderWindowShell.classList.remove('open');
            builderWindowBackdrop.classList.remove('open');
            document.body.classList.remove('builder-window-open');
        }
    }

    if (openBuilderWindowBtn) {
        openBuilderWindowBtn.addEventListener('click', function () {
            setBuilderWindow(true);
        });
    }

    if (closeBuilderWindowBtn) {
        closeBuilderWindowBtn.addEventListener('click', function () {
            setBuilderWindow(false);
        });
    }

    if (builderWindowBackdrop) {
        builderWindowBackdrop.addEventListener('click', function () {
            setBuilderWindow(false);
        });
    }

    var importInput = document.getElementById('builderImportFile');
    if (importInput) {
        importInput.addEventListener('change', function () {
            var file = importInput.files && importInput.files[0] ? importInput.files[0] : null;
            if (!file) {
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                try {
                    var parsed = JSON.parse(String(reader.result || '[]'));
                    if (!Array.isArray(parsed)) {
                        throw new Error('Niepoprawny format JSON.');
                    }
                    list.innerHTML = '';
                    parsed.forEach(function (item) {
                        var t = item && item.type ? item.type : 'text';
                        list.appendChild(makeItem(t, item));
                    });
                    renderEmpty();
                    sync();
                } catch (err) {
                    alert('Nie mozna zaladowac JSON buildera: ' + (err && err.message ? err.message : 'blad')); // eslint-disable-line no-alert
                }
            };
            reader.readAsText(file);
            importInput.value = '';
        });
    }

    if (initial.length) {
        initial.forEach(function (item) {
            list.appendChild(makeItem(item.type || 'text', item));
        });
    }

    setAdvancedMode(advancedMode, true);
    renderEmpty();
    sync();

    var undoBtn = document.getElementById('builderUndoBtn');
    var redoBtn = document.getElementById('builderRedoBtn');
    if (undoBtn) {
        undoBtn.addEventListener('click', function () {
            if (historyIndex <= 0) {
                return;
            }
            historyIndex -= 1;
            var snapshot = historyStack[historyIndex];
            var payload = [];
            try {
                payload = JSON.parse(snapshot);
            } catch (e) {
                payload = [];
            }
            isApplyingHistory = true;
            loadPayload(Array.isArray(payload) ? payload : [], { skipHistory: true });
            isApplyingHistory = false;
            updateHistoryButtons();
        });
    }

    if (redoBtn) {
        redoBtn.addEventListener('click', function () {
            if (historyIndex >= historyStack.length - 1) {
                return;
            }
            historyIndex += 1;
            var snapshot = historyStack[historyIndex];
            var payload = [];
            try {
                payload = JSON.parse(snapshot);
            } catch (e) {
                payload = [];
            }
            isApplyingHistory = true;
            loadPayload(Array.isArray(payload) ? payload : [], { skipHistory: true });
            isApplyingHistory = false;
            updateHistoryButtons();
        });
    }

    document.addEventListener('keydown', function (e) {
        var key = String(e.key || '').toLowerCase();
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && key === 'z') {
            if (undoBtn && !undoBtn.disabled) {
                e.preventDefault();
                undoBtn.click();
            }
        }
        if (((e.ctrlKey || e.metaKey) && e.shiftKey && key === 'z') || ((e.ctrlKey || e.metaKey) && key === 'y')) {
            if (redoBtn && !redoBtn.disabled) {
                e.preventDefault();
                redoBtn.click();
            }
        }
    });

    if (fallbackContentField) {
        fallbackContentField.addEventListener('input', function () {
            var payload = [];
            try {
                payload = JSON.parse(String(hidden.value || '[]'));
            } catch (e) {
                payload = [];
            }
            renderLiveContent(Array.isArray(payload) ? payload : []);
        });
    }

    if (liveBreakpoints) {
        liveBreakpoints.querySelectorAll('[data-live-breakpoint]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setLiveMode(btn.getAttribute('data-live-breakpoint') || 'desktop');
            });
        });
    }
    setLiveMode('desktop');

    var form = document.getElementById('pageEditorForm');
    if (form) {
        form.addEventListener('submit', sync);
    }

    // Expose for draft restore
    window.cmsBuilderLoad = function (blocks) {
        isApplyingHistory = true;
        loadPayload(Array.isArray(blocks) ? blocks : [], { skipHistory: true });
        isApplyingHistory = false;
        pushHistory(readPayloadFromDom());
    };
}());

// ── Auto-save draft + page preview ───────────────────────────────────────────
(function () {
    'use strict';
    var form = document.getElementById('pageEditorForm');
    if (!form) { return; }

    var draftKey = typeof window.CMS_DRAFT_KEY === 'string' ? window.CMS_DRAFT_KEY : null;
    var previewUrl = typeof window.CMS_PAGE_PREVIEW_URL === 'string' ? window.CMS_PAGE_PREVIEW_URL : '';
    var autosaveBadge = document.getElementById('autosaveBadge');
    var isDirty = false;

    function getFormSnapshot() {
        var snap = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name) { return; }
            snap[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
        });
        return JSON.stringify(snap);
    }

    function setBadge(state, text) {
        if (!autosaveBadge) { return; }
        autosaveBadge.className = 'autosave-badge' + (state ? ' ' + state : '');
        autosaveBadge.textContent = text;
    }

    function markDirty() {
        isDirty = true;
        if (!draftKey) { return; }
        try { localStorage.setItem(draftKey, getFormSnapshot()); } catch (e) { /* quota */ }
        setBadge('dirty', '\u25cf Niezapisane zmiany');
    }

    // ── Restore draft on page load ────────────────────────────────────────────
    if (draftKey) {
        var saved = null;
        try { saved = localStorage.getItem(draftKey); } catch (e) {}
        if (saved) {
            try {
                var snap = JSON.parse(saved);
                var skip = { csrf_token: 1, action: 1, page_id: 1, edit_lang: 1 };
                Object.keys(snap).forEach(function (k) {
                    if (skip[k] || k === 'builder_data') { return; }
                    var el = form.querySelector('[name="' + k + '"]');
                    if (!el) { return; }
                    if (el.type === 'checkbox') {
                        el.checked = snap[k] === '1';
                    } else {
                        el.value = snap[k];
                    }
                });
                if (snap.builder_data && typeof window.cmsBuilderLoad === 'function') {
                    try {
                        var blocks = JSON.parse(snap.builder_data);
                        if (Array.isArray(blocks)) { window.cmsBuilderLoad(blocks); }
                    } catch (e) {}
                }
                setBadge('dirty', '\u25cf Odzyskano szkic \u2014 zapisz aby zachowac');
                isDirty = true;
            } catch (e) {}
        }
    }

    // ── Dirty tracking ────────────────────────────────────────────────────────
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    document.addEventListener('cms:builder:change', markDirty);

    form.addEventListener('submit', function () {
        if (draftKey) { try { localStorage.removeItem(draftKey); } catch (e) {} }
        isDirty = false;
        setBadge('saved', '\u2713 Zapisano');
    });

    window.addEventListener('beforeunload', function (e) {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // Ctrl+S / Cmd+S — quick save
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
        }
    });

    // ── Preview overlay ───────────────────────────────────────────────────────
    var previewBtn     = document.getElementById('pagePreviewBtn');
    var previewOverlay = document.getElementById('pagePreviewOverlay');
    var previewFrame   = document.getElementById('pagePreviewFrame');
    var previewClose   = document.getElementById('pagePreviewClose');
    var builderWindowShellRef = document.getElementById('builderWindowShell');
    var builderWindowBackdropRef = document.getElementById('builderWindowBackdrop');

    function openPreview() {
        if (!previewUrl) {
            // eslint-disable-next-line no-alert
            alert('Zapisz strone i upewnij sie, ze ma slug, aby uzyc podgladu.');
            return;
        }
        if (previewFrame) { previewFrame.src = previewUrl; }
        if (previewOverlay) { previewOverlay.classList.add('open'); }
    }

    function closePreview() {
        if (previewOverlay) { previewOverlay.classList.remove('open'); }
        if (previewFrame) { setTimeout(function () { previewFrame.src = ''; }, 200); }
    }

    if (previewBtn) { previewBtn.addEventListener('click', openPreview); }
    if (previewClose) { previewClose.addEventListener('click', closePreview); }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && builderWindowShellRef && builderWindowShellRef.classList.contains('open')) {
            builderWindowShellRef.classList.remove('open');
            if (builderWindowBackdropRef) {
                builderWindowBackdropRef.classList.remove('open');
            }
            document.body.classList.remove('builder-window-open');
        }
        if (e.key === 'Escape' && previewOverlay && previewOverlay.classList.contains('open')) {
            closePreview();
        }
    });
}());
