(function () {
    'use strict';

    var list = document.getElementById('builderListV2');
    var hidden = document.getElementById('builderDataInputV2');
    var canvas = document.getElementById('builderCanvasGrid');
    var liveContent = document.getElementById('builderLiveContent');
    var liveWrap = liveContent ? liveContent.closest('.builder-live-wrap') : null;
    var liveBreakpoints = document.getElementById('builderLiveBreakpoints');
    var fallbackContentField = document.querySelector('#pageEditorForm [name="content"]');
    if (!list || !hidden) {
        return;
    }

    var initial = Array.isArray(window.CMS_BUILDER_BLOCKS) ? window.CMS_BUILDER_BLOCKS : [];

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
                    '<button type="button" class="btn danger" data-remove-block>Usun sekcje</button>' +
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
                '<div><label>Grid X (0-11)</label><input type="number" min="0" max="11" step="1" data-field="layout_x" value="' + esc(block.layout_x) + '"></div>' +
                '<div><label>Grid Y (0-200)</label><input type="number" min="0" max="200" step="1" data-field="layout_y" value="' + esc(block.layout_y) + '"></div>' +
                '<div><label>Grid W (1-12)</label><input type="number" min="1" max="12" step="1" data-field="layout_w" value="' + esc(block.layout_w) + '"></div>' +
                '<div><label>Grid H (1-12)</label><input type="number" min="1" max="12" step="1" data-field="layout_h" value="' + esc(block.layout_h) + '"></div>' +
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

    function sync() {
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
        hidden.value = JSON.stringify(payload);
        renderCanvas(payload);
        renderLiveContent(payload);
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

    function renderLiveContent(payload) {
        if (!liveContent) {
            return;
        }
        liveContent.innerHTML = '';

        if (!Array.isArray(payload) || payload.length === 0) {
            var empty = document.createElement('section');
            empty.className = 'builder-live-section builder-live-empty';
            var fallback = fallbackContentField ? String(fallbackContentField.value || '').trim() : '';
            empty.innerHTML = '<h4>Fallback content</h4><div class="builder-live-text">' + nl2brSafe(fallback ? fallback.slice(0, 1200) : 'Brak blokow i brak tresci fallback.') + '</div>';
            liveContent.appendChild(empty);
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
                contentHtml += '<div class="builder-live-plugin-note">Slot pluginu: ' + esc(String(block.plugin_slug || '').trim() || 'brak slug') + '</div>';
            }

            if (title) {
                contentHtml += '<h4>' + esc(title) + '</h4>';
            }
            if (String(block.text || '').trim()) {
                contentHtml += '<div class="builder-live-text">' + nl2brSafe(String(block.text || '').slice(0, 900)) + '</div>';
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
                + '</div>'
                + '<div class="builder-live-inner">' + contentHtml + '</div>';

            section.addEventListener('click', function () {
                focusEditorItem(idx);
            });

            liveContent.appendChild(section);
        });
    }

    function setLayoutForIndex(index, nextX, nextY, nextW, nextH) {
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
        sync();
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
            card.style.gridColumn = (x + 1) + ' / span ' + w;
            card.style.gridRow = (y + 1) + ' / span ' + h;
            card.innerHTML = '<strong>' + esc(block.title || ('Sekcja ' + (idx + 1))) + '</strong><span>' + esc(String(block.type || 'text').toUpperCase()) + ' • x' + x + ' y' + y + ' w' + w + ' h' + h + '</span><i class="builder-canvas-resize" title="Przeciagnij aby zmienic rozmiar"></i>';

            card.addEventListener('mousedown', function (ev) {
                if (ev.target && ev.target.classList && ev.target.classList.contains('builder-canvas-resize')) {
                    return;
                }
                ev.preventDefault();
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
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });

            var resizeHandle = card.querySelector('.builder-canvas-resize');
            if (resizeHandle) {
                resizeHandle.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
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
                sync();
            });
            field.addEventListener('change', function () {
                updatePreview();
                sync();
            });
        });

        item.querySelector('[data-remove-block]').addEventListener('click', function () {
            item.remove();
            renderEmpty();
            sync();
        });

        item.addEventListener('dragstart', function () {
            item.classList.add('dragging');
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('dragging');
            sync();
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
                    sync();
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
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
            dragNewType = null;
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

    renderEmpty();
    sync();

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
        list.innerHTML = '';
        if (Array.isArray(blocks)) {
            blocks.forEach(function (item) {
                list.appendChild(makeItem(item.type || 'text', item));
            });
        }
        renderEmpty();
        sync();
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
        if (e.key === 'Escape' && builderWindowShell && builderWindowShell.classList.contains('open')) {
            setBuilderWindow(false);
        }
        if (e.key === 'Escape' && previewOverlay && previewOverlay.classList.contains('open')) {
            closePreview();
        }
    });
}());
