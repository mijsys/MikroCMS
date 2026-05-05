(function () {
    'use strict';

    var list = document.getElementById('builderListV2');
    var hidden = document.getElementById('builderDataInputV2');
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
            plugin_slug: ''
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
            payload.push(data);
        });
        hidden.value = JSON.stringify(payload);
        document.dispatchEvent(new CustomEvent('cms:builder:change'));
    }

    function bindItem(item) {
        initContainerEditor(item);

        var minHeightField = item.querySelector('[data-field="min_height"]');
        var preview = item.querySelector('[data-section-preview]');
        var titleField = item.querySelector('[data-field="title"]');
        var typeField = item.querySelector('[data-field="type"]');
        var colorField = item.querySelector('[data-field="background_color"]');
        var previewTitle = item.querySelector('[data-preview-title]');
        var previewType = item.querySelector('[data-preview-type]');

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
