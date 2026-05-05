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
                '<button type="button" class="btn danger" data-remove-block>Usun blok</button>' +
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
                '<div class="full"><label>Elementy kontenera (JSON)</label><textarea data-field="container_items_json" placeholder="[{\"title\":\"...\",\"text\":\"...\"}]">' + esc(block.container_items_json) + '</textarea></div>' +
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

    function sync() {
        var payload = [];
        list.querySelectorAll('.builder-item').forEach(function (item) {
            var data = {};
            item.querySelectorAll('[data-field]').forEach(function (field) {
                data[field.getAttribute('data-field')] = field.value;
            });
            payload.push(data);
        });
        hidden.value = JSON.stringify(payload);
    }

    function bindItem(item) {
        item.querySelectorAll('[data-field]').forEach(function (field) {
            field.addEventListener('input', sync);
            field.addEventListener('change', sync);
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
    }

    function renderEmpty() {
        var empty = document.getElementById('builderEmptyV2');
        if (!empty) {
            return;
        }
        empty.style.display = list.querySelector('.builder-item') ? 'none' : 'block';
    }

    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var dragging = list.querySelector('.builder-item.dragging');
        if (!dragging) {
            return;
        }

        var after = Array.prototype.slice.call(list.querySelectorAll('.builder-item:not(.dragging)')).find(function (el) {
            var rect = el.getBoundingClientRect();
            return e.clientY < rect.top + rect.height / 2;
        });

        if (after) {
            list.insertBefore(dragging, after);
        } else {
            list.appendChild(dragging);
        }
    });

    document.querySelectorAll('[data-builder2-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-builder2-add') || 'text';
            list.appendChild(makeItem(type));
            renderEmpty();
            sync();
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
}());
