/**
 * List of Links (LoL) - Admin JavaScript
 *
 * Handles page management, form interactions, live preview, and API calls.
 */

(function () {
    'use strict';

    // --- State ---
    let currentSlug = null; // null = new page, string = editing existing
    let linkCounter = 0;

    // --- DOM Elements ---
    const viewList = document.getElementById('view-list');
    const viewEditor = document.getElementById('view-editor');
    const pageGrid = document.getElementById('page-grid');
    const emptyState = document.getElementById('empty-state');
    const editorTitle = document.getElementById('editor-title');
    const linksContainer = document.getElementById('links-container');
    const previewContent = document.getElementById('preview-content');
    const toastEl = document.getElementById('toast');

    // Buttons
    const btnNewPage = document.getElementById('btn-new-page');
    const btnBack = document.getElementById('btn-back');
    const btnSave = document.getElementById('btn-save');
    const btnAddLink = document.getElementById('btn-add-link');

    // Form fields
    const fields = {
        slug: document.getElementById('field-slug'),
        title: document.getElementById('field-title'),
        bio: document.getElementById('field-bio'),
        avatar: document.getElementById('field-avatar'),
        bgColor: document.getElementById('field-bg-color'),
        bgColorPicker: document.getElementById('field-bg-color-picker'),
        bgColorEnd: document.getElementById('field-bg-color-end'),
        bgColorEndPicker: document.getElementById('field-bg-color-end-picker'),
        textColor: document.getElementById('field-text-color'),
        textColorPicker: document.getElementById('field-text-color-picker'),
        buttonColor: document.getElementById('field-button-color'),
        buttonColorPicker: document.getElementById('field-button-color-picker'),
        buttonTextColor: document.getElementById('field-button-text-color'),
        buttonTextColorPicker: document.getElementById('field-button-text-color-picker'),
        buttonStyle: document.getElementById('field-button-style'),
        buttonRadius: document.getElementById('field-button-radius'),
        fontFamily: document.getElementById('field-font-family'),
        avatarFile: document.getElementById('avatar-file'),
    };

    // --- Initialization ---
    btnNewPage.addEventListener('click', () => showEditor());
    btnBack.addEventListener('click', showList);
    btnSave.addEventListener('click', savePage);
    btnAddLink.addEventListener('click', () => addLinkItem('', ''));

    // Color picker sync
    syncColorPicker(fields.bgColor, fields.bgColorPicker);
    syncColorPicker(fields.bgColorEnd, fields.bgColorEndPicker);
    syncColorPicker(fields.textColor, fields.textColorPicker);
    syncColorPicker(fields.buttonColor, fields.buttonColorPicker);
    syncColorPicker(fields.buttonTextColor, fields.buttonTextColorPicker);

    // Live preview updates
    const previewInputs = [
        fields.title, fields.bio, fields.avatar,
        fields.bgColor, fields.bgColorEnd, fields.textColor,
        fields.buttonColor, fields.buttonTextColor,
        fields.buttonStyle, fields.buttonRadius, fields.fontFamily,
    ];
    previewInputs.forEach(input => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    // Avatar URL preview
    fields.avatar.addEventListener('input', updateAvatarPreview);

    // Avatar file upload
    fields.avatarFile.addEventListener('change', handleAvatarUpload);

    // Load page list on startup
    loadPages();

    // --- Functions ---

    function syncColorPicker(textInput, pickerInput) {
        pickerInput.addEventListener('input', () => {
            textInput.value = pickerInput.value;
            textInput.dispatchEvent(new Event('input'));
        });
        textInput.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
                pickerInput.value = textInput.value;
            }
        });
    }

    async function loadPages() {
        try {
            const resp = await fetch('api.php?action=list');
            const data = await resp.json();
            renderPageGrid(data.pages || []);
        } catch (err) {
            toast('Failed to load pages', 'error');
        }
    }

    function renderPageGrid(pages) {
        if (pages.length === 0) {
            pageGrid.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        pageGrid.style.display = '';
        emptyState.style.display = 'none';
        pageGrid.innerHTML = '';

        pages.forEach(page => {
            const card = document.createElement('div');
            card.className = 'page-card';

            const avatarHtml = page.avatar
                ? `<img src="${escapeHtml(page.avatar)}" alt="">`
                : '&#128100;';

            card.innerHTML = `
                <div class="page-card-header">
                    <div class="page-card-avatar">${avatarHtml}</div>
                    <div class="page-card-info">
                        <div class="page-card-title">${escapeHtml(page.title || page.slug)}</div>
                        <div class="page-card-slug">/${escapeHtml(page.slug)}</div>
                    </div>
                </div>
                <div class="page-card-bio">${escapeHtml(page.bio || 'No description')}</div>
                <div class="page-card-actions">
                    <button class="btn btn-ghost btn-small" onclick="window.lol.editPage('${escapeHtml(page.slug)}')">Edit</button>
                    <a href="index.php?page=${encodeURIComponent(page.slug)}" target="_blank" class="btn btn-ghost btn-small">View</a>
                    <button class="btn btn-danger btn-small" onclick="window.lol.deletePage('${escapeHtml(page.slug)}')">Delete</button>
                </div>
            `;

            pageGrid.appendChild(card);
        });
    }

    function showList() {
        viewEditor.classList.remove('active');
        viewList.classList.add('active');
        loadPages();
    }

    window.showEditor = showEditor;
    function showEditor(config) {
        viewList.classList.remove('active');
        viewEditor.classList.add('active');

        if (config) {
            // Editing existing page
            currentSlug = config.slug;
            editorTitle.textContent = 'Edit Page';
            fields.slug.value = config.slug;
            fields.slug.readOnly = true;
            fields.title.value = config.title || '';
            fields.bio.value = config.bio || '';
            fields.avatar.value = config.avatar || '';

            const theme = config.theme || {};
            fields.bgColor.value = theme.background_color || '#780016';
            fields.bgColorEnd.value = theme.background_color_end || '';
            fields.textColor.value = theme.text_color || '#FFFFFF';
            fields.buttonColor.value = theme.button_color || '#FFFFFF';
            fields.buttonTextColor.value = theme.button_text_color || '#FFFFFF';
            fields.buttonStyle.value = theme.button_style || 'outline';
            fields.buttonRadius.value = theme.button_radius || '50px';
            fields.fontFamily.value = theme.font_family || "'Inter', sans-serif";

            // Sync color pickers
            updatePickerFromText(fields.bgColor, fields.bgColorPicker);
            updatePickerFromText(fields.bgColorEnd, fields.bgColorEndPicker);
            updatePickerFromText(fields.textColor, fields.textColorPicker);
            updatePickerFromText(fields.buttonColor, fields.buttonColorPicker);
            updatePickerFromText(fields.buttonTextColor, fields.buttonTextColorPicker);

            // Load links
            linksContainer.innerHTML = '';
            linkCounter = 0;
            (config.links || []).forEach(link => addLinkItem(link.title, link.url));
        } else {
            // New page
            currentSlug = null;
            editorTitle.textContent = 'New Page';
            fields.slug.value = '';
            fields.slug.readOnly = false;
            fields.title.value = '';
            fields.bio.value = '';
            fields.avatar.value = '';

            fields.bgColor.value = '#780016';
            fields.bgColorEnd.value = '#2d0008';
            fields.textColor.value = '#FFFFFF';
            fields.buttonColor.value = '#FFFFFF';
            fields.buttonTextColor.value = '#FFFFFF';
            fields.buttonStyle.value = 'outline';
            fields.buttonRadius.value = '50px';
            fields.fontFamily.value = "'Inter', sans-serif";

            // Sync color pickers
            updatePickerFromText(fields.bgColor, fields.bgColorPicker);
            updatePickerFromText(fields.bgColorEnd, fields.bgColorEndPicker);
            updatePickerFromText(fields.textColor, fields.textColorPicker);
            updatePickerFromText(fields.buttonColor, fields.buttonColorPicker);
            updatePickerFromText(fields.buttonTextColor, fields.buttonTextColorPicker);

            linksContainer.innerHTML = '';
            linkCounter = 0;
            addLinkItem('', '');
        }

        updateAvatarPreview();
        updatePreview();
    }

    function updatePickerFromText(textInput, pickerInput) {
        if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
            pickerInput.value = textInput.value;
        }
    }

    function addLinkItem(title, url) {
        const id = linkCounter++;
        const item = document.createElement('div');
        item.className = 'link-item';
        item.dataset.id = id;
        item.innerHTML = `
            <div class="link-item-fields">
                <input type="text" placeholder="Link title" value="${escapeAttr(title)}" class="link-title" data-id="${id}">
                <input type="text" placeholder="https://example.com" value="${escapeAttr(url)}" class="link-url" data-id="${id}">
            </div>
            <div class="link-item-actions">
                <button type="button" title="Move up" onclick="window.lol.moveLink(${id}, -1)">&uarr;</button>
                <button type="button" title="Move down" onclick="window.lol.moveLink(${id}, 1)">&darr;</button>
                <button type="button" class="btn-remove" title="Remove" onclick="window.lol.removeLink(${id})">&times;</button>
            </div>
        `;

        linksContainer.appendChild(item);

        // Attach preview listeners
        item.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', updatePreview);
        });

        updatePreview();
    }

    function getFormData() {
        const links = [];
        linksContainer.querySelectorAll('.link-item').forEach(item => {
            const title = item.querySelector('.link-title').value.trim();
            const url = item.querySelector('.link-url').value.trim();
            if (title || url) {
                links.push({ title, url });
            }
        });

        return {
            slug: fields.slug.value.trim().toLowerCase().replace(/[^a-z0-9\-]/g, ''),
            title: fields.title.value.trim(),
            bio: fields.bio.value.trim(),
            avatar: fields.avatar.value.trim(),
            theme: {
                background_color: fields.bgColor.value.trim(),
                background_color_end: fields.bgColorEnd.value.trim(),
                text_color: fields.textColor.value.trim(),
                button_style: fields.buttonStyle.value,
                button_color: fields.buttonColor.value.trim(),
                button_text_color: fields.buttonTextColor.value.trim(),
                button_radius: fields.buttonRadius.value,
                font_family: fields.fontFamily.value,
            },
            links,
        };
    }

    async function savePage() {
        const data = getFormData();

        if (!data.slug) {
            toast('Please enter a page slug', 'error');
            fields.slug.focus();
            return;
        }

        if (!data.title) {
            toast('Please enter a display title', 'error');
            fields.title.focus();
            return;
        }

        btnSave.disabled = true;
        btnSave.textContent = 'Saving...';

        try {
            const resp = await fetch('api.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });

            const result = await resp.json();

            if (result.success) {
                toast('Page saved successfully!', 'success');
                currentSlug = data.slug;
                fields.slug.readOnly = true;
                editorTitle.textContent = 'Edit Page';
            } else {
                toast(result.error || 'Failed to save page', 'error');
            }
        } catch (err) {
            toast('Network error. Please try again.', 'error');
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = 'Save Page';
        }
    }

    async function editPage(slug) {
        try {
            const resp = await fetch(`api.php?action=get&slug=${encodeURIComponent(slug)}`);
            const data = await resp.json();

            if (data.error) {
                toast(data.error, 'error');
                return;
            }

            showEditor(data);
        } catch (err) {
            toast('Failed to load page', 'error');
        }
    }

    async function deletePage(slug) {
        if (!confirm(`Delete page "${slug}"? This cannot be undone.`)) return;

        try {
            const resp = await fetch('api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug }),
            });

            const result = await resp.json();

            if (result.success) {
                toast('Page deleted', 'success');
                loadPages();
            } else {
                toast(result.error || 'Failed to delete page', 'error');
            }
        } catch (err) {
            toast('Network error', 'error');
        }
    }

    function moveLink(id, direction) {
        const items = Array.from(linksContainer.querySelectorAll('.link-item'));
        const index = items.findIndex(el => parseInt(el.dataset.id) === id);
        const targetIndex = index + direction;

        if (targetIndex < 0 || targetIndex >= items.length) return;

        const item = items[index];
        const target = items[targetIndex];

        if (direction === -1) {
            linksContainer.insertBefore(item, target);
        } else {
            linksContainer.insertBefore(target, item);
        }

        updatePreview();
    }

    function removeLink(id) {
        const item = linksContainer.querySelector(`.link-item[data-id="${id}"]`);
        if (item) {
            item.remove();
            updatePreview();
        }
    }

    function updateAvatarPreview() {
        const preview = document.getElementById('avatar-preview');
        const url = fields.avatar.value.trim();

        if (url) {
            preview.innerHTML = `<img src="${escapeHtml(url)}" alt="Avatar" onerror="this.parentElement.innerHTML='<span class=\\'avatar-placeholder\\'>?</span>'">`;
        } else {
            preview.innerHTML = '<span class="avatar-placeholder">?</span>';
        }
    }

    async function handleAvatarUpload() {
        const file = fields.avatarFile.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('avatar', file);

        try {
            const resp = await fetch('api.php?action=upload', {
                method: 'POST',
                body: formData,
            });

            const result = await resp.json();

            if (result.success) {
                fields.avatar.value = result.url;
                updateAvatarPreview();
                updatePreview();
                toast('Image uploaded', 'success');
            } else {
                toast(result.error || 'Upload failed', 'error');
            }
        } catch (err) {
            toast('Upload failed', 'error');
        }

        // Reset file input
        fields.avatarFile.value = '';
    }

    function updatePreview() {
        const data = getFormData();
        const theme = data.theme;

        // Background
        let bg = theme.background_color || '#780016';
        if (theme.background_color_end) {
            bg = `linear-gradient(180deg, ${theme.background_color} 0%, ${theme.background_color_end} 100%)`;
        }

        previewContent.style.background = bg;
        previewContent.style.color = theme.text_color || '#FFFFFF';
        previewContent.style.fontFamily = theme.font_family || "'Inter', sans-serif";

        let html = '';

        // Avatar
        if (data.avatar) {
            html += `<div class="p-avatar"><img src="${escapeHtml(data.avatar)}" alt="" onerror="this.parentElement.style.display='none'"></div>`;
        }

        // Title
        html += `<div class="p-title">${escapeHtml(data.title || 'Page Title')}</div>`;

        // Bio
        if (data.bio) {
            html += `<div class="p-bio">${escapeHtml(data.bio)}</div>`;
        }

        // Links
        if (data.links.length > 0) {
            html += '<div class="p-links">';
            data.links.forEach(link => {
                if (!link.title && !link.url) return;

                let style = `border-radius: ${theme.button_radius || '50px'}; `;

                if (theme.button_style === 'filled') {
                    style += `background: ${theme.button_color}; color: ${theme.button_text_color}; border: 2px solid transparent;`;
                } else if (theme.button_style === 'shadow') {
                    style += `background: ${theme.button_color}; color: ${theme.button_text_color}; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15);`;
                } else {
                    // outline
                    style += `background: transparent; color: ${theme.button_text_color}; border: 2px solid ${theme.button_color};`;
                }

                html += `<div class="p-link" style="${style}">${escapeHtml(link.title || link.url)}</div>`;
            });
            html += '</div>';
        }

        previewContent.innerHTML = html;
    }

    function toast(message, type) {
        toastEl.textContent = message;
        toastEl.className = `toast ${type} show`;

        setTimeout(() => {
            toastEl.classList.remove('show');
        }, 3000);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Expose functions for inline event handlers
    window.lol = { editPage, deletePage, moveLink, removeLink };
})();
