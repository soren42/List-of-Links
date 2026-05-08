/**
 * List of Links (LoL) - Admin JavaScript
 *
 * Handles authentication, page management, form interactions,
 * icon detection, live preview, Linktree import, and API calls.
 */

(function () {
    'use strict';

    // ─── Well-Known Service Icon Map ─────────────────────────
    const ICON_MAP = {
        'discord.gg':        'fa-brands fa-discord',
        'discord.com':       'fa-brands fa-discord',
        'tiktok.com':        'fa-brands fa-tiktok',
        'vm.tiktok.com':     'fa-brands fa-tiktok',
        'twitch.tv':         'fa-brands fa-twitch',
        'youtube.com':       'fa-brands fa-youtube',
        'youtu.be':          'fa-brands fa-youtube',
        'instagram.com':     'fa-brands fa-instagram',
        'twitter.com':       'fa-brands fa-x-twitter',
        'x.com':             'fa-brands fa-x-twitter',
        'facebook.com':      'fa-brands fa-facebook',
        'fb.com':            'fa-brands fa-facebook',
        'github.com':        'fa-brands fa-github',
        'linkedin.com':      'fa-brands fa-linkedin',
        'snapchat.com':      'fa-brands fa-snapchat',
        'reddit.com':        'fa-brands fa-reddit',
        'pinterest.com':     'fa-brands fa-pinterest',
        'spotify.com':       'fa-brands fa-spotify',
        'open.spotify.com':  'fa-brands fa-spotify',
        'soundcloud.com':    'fa-brands fa-soundcloud',
        'patreon.com':       'fa-brands fa-patreon',
        'steam.com':         'fa-brands fa-steam',
        'steampowered.com':  'fa-brands fa-steam',
        'store.steampowered.com': 'fa-brands fa-steam',
        'tumblr.com':        'fa-brands fa-tumblr',
        'whatsapp.com':      'fa-brands fa-whatsapp',
        'wa.me':             'fa-brands fa-whatsapp',
        'telegram.org':      'fa-brands fa-telegram',
        't.me':              'fa-brands fa-telegram',
        'kick.com':          'fa-brands fa-kickstarter',
        'vimeo.com':         'fa-brands fa-vimeo',
        'behance.net':       'fa-brands fa-behance',
        'dribbble.com':      'fa-brands fa-dribbble',
        'deviantart.com':    'fa-brands fa-deviantart',
        'etsy.com':          'fa-brands fa-etsy',
        'paypal.com':        'fa-brands fa-paypal',
        'paypal.me':         'fa-brands fa-paypal',
        'ko-fi.com':         'fa-solid fa-mug-hot',
        'cash.app':          'fa-solid fa-dollar-sign',
        'venmo.com':         'fa-solid fa-dollar-sign',
        'apple.com':         'fa-brands fa-apple',
        'music.apple.com':   'fa-brands fa-itunes-note',
        'threads.net':       'fa-brands fa-threads',
        'mastodon.social':   'fa-brands fa-mastodon',
        'bsky.app':          'fa-brands fa-bluesky',
    };

    function detectIcon(url) {
        try {
            const host = new URL(url).hostname.replace(/^www\./, '').toLowerCase();
            if (ICON_MAP[host]) return ICON_MAP[host];
            const parts = host.split('.');
            if (parts.length > 2) {
                const parent = parts.slice(-2).join('.');
                if (ICON_MAP[parent]) return ICON_MAP[parent];
            }
        } catch (e) { /* invalid URL */ }
        return null;
    }

    function normalizeUrlForComparison(url) {
        try {
            const u = new URL(url);
            return (u.hostname + u.pathname).replace(/^www\./, '').replace(/\/+$/, '').toLowerCase();
        } catch (e) {
            return url.toLowerCase().replace(/\/+$/, '');
        }
    }

    // ─── State ───────────────────────────────────────────────
    let currentSlug = null;
    let linkCounter = 0;
    let authType = null;
    let importedData = null;

    // ─── DOM Elements ────────────────────────────────────────
    const viewSetup  = document.getElementById('view-setup');
    const viewLogin  = document.getElementById('view-login');
    const viewAdmin  = document.getElementById('view-admin');
    const viewList   = document.getElementById('view-list');
    const viewEditor = document.getElementById('view-editor');

    const pageGrid       = document.getElementById('page-grid');
    const emptyState     = document.getElementById('empty-state');
    const editorTitle    = document.getElementById('editor-title');
    const linksContainer = document.getElementById('links-container');
    const previewContent = document.getElementById('preview-content');
    const toastEl        = document.getElementById('toast');
    const authBadge      = document.getElementById('auth-badge');

    // Import modal elements
    const importModal        = document.getElementById('import-modal');
    const importStepUrl      = document.getElementById('import-step-url');
    const importStepLoading  = document.getElementById('import-step-loading');
    const importStepMerge    = document.getElementById('import-step-merge');
    const importUrlInput     = document.getElementById('import-url');
    const importUrlError     = document.getElementById('import-url-error');
    const importMergeContent = document.getElementById('import-merge-content');

    // Buttons
    const btnNewPage = document.getElementById('btn-new-page');
    const btnBack    = document.getElementById('btn-back');
    const btnSave    = document.getElementById('btn-save');
    const btnAddLink = document.getElementById('btn-add-link');
    const btnLogout  = document.getElementById('btn-logout');
    const btnImport  = document.getElementById('btn-import');

    // Form fields
    const fields = {
        slug:               document.getElementById('field-slug'),
        title:              document.getElementById('field-title'),
        bio:                document.getElementById('field-bio'),
        avatar:             document.getElementById('field-avatar'),
        userPassword:       document.getElementById('field-user-password'),
        bgColor:            document.getElementById('field-bg-color'),
        bgColorPicker:      document.getElementById('field-bg-color-picker'),
        bgColorEnd:         document.getElementById('field-bg-color-end'),
        bgColorEndPicker:   document.getElementById('field-bg-color-end-picker'),
        textColor:          document.getElementById('field-text-color'),
        textColorPicker:    document.getElementById('field-text-color-picker'),
        buttonColor:        document.getElementById('field-button-color'),
        buttonColorPicker:  document.getElementById('field-button-color-picker'),
        buttonTextColor:    document.getElementById('field-button-text-color'),
        buttonTextColorPicker: document.getElementById('field-button-text-color-picker'),
        buttonStyle:        document.getElementById('field-button-style'),
        buttonRadius:       document.getElementById('field-button-radius'),
        fontFamily:         document.getElementById('field-font-family'),
        avatarFile:         document.getElementById('avatar-file'),
    };

    // ─── Initialization ──────────────────────────────────────
    btnNewPage.addEventListener('click', () => showEditor());
    btnBack.addEventListener('click', showList);
    btnSave.addEventListener('click', savePage);
    btnAddLink.addEventListener('click', () => addLinkItem('', ''));
    btnLogout.addEventListener('click', logout);
    btnImport.addEventListener('click', showImportModal);

    // Import modal buttons
    document.getElementById('import-modal-close').addEventListener('click', hideImportModal);
    document.getElementById('import-fetch-btn').addEventListener('click', fetchLinktreeProfile);
    document.getElementById('import-back-btn').addEventListener('click', () => showImportStep('url'));
    document.getElementById('import-apply-btn').addEventListener('click', applyMerge);
    importModal.addEventListener('click', (e) => { if (e.target === importModal) hideImportModal(); });
    importUrlInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); fetchLinktreeProfile(); } });

    // Auth forms
    document.getElementById('setup-form').addEventListener('submit', handleSetup);
    document.getElementById('login-form').addEventListener('submit', handleLoginSubmit);

    // Color picker sync
    syncColorPicker(fields.bgColor, fields.bgColorPicker);
    syncColorPicker(fields.bgColorEnd, fields.bgColorEndPicker);
    syncColorPicker(fields.textColor, fields.textColorPicker);
    syncColorPicker(fields.buttonColor, fields.buttonColorPicker);
    syncColorPicker(fields.buttonTextColor, fields.buttonTextColorPicker);

    // Live preview updates
    [fields.title, fields.bio, fields.avatar, fields.bgColor, fields.bgColorEnd,
     fields.textColor, fields.buttonColor, fields.buttonTextColor,
     fields.buttonStyle, fields.buttonRadius, fields.fontFamily,
    ].forEach(input => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    fields.avatar.addEventListener('input', updateAvatarPreview);
    fields.avatarFile.addEventListener('change', handleAvatarUpload);

    checkAuth();

    // ─── Auth Functions ──────────────────────────────────────

    async function checkAuth() {
        try {
            const resp = await fetch('api.php?action=check-auth');
            const data = await resp.json();
            if (data.needs_setup) showView('setup');
            else if (data.authenticated) { authType = data.auth_type; enterAdmin(data.auth_type, data.slug); }
            else showView('login');
        } catch (err) { showView('login'); }
    }

    async function handleSetup(e) {
        e.preventDefault();
        const pw = document.getElementById('setup-password').value;
        const confirm = document.getElementById('setup-password-confirm').value;
        const errorEl = document.getElementById('setup-error');
        errorEl.textContent = '';
        if (pw !== confirm) { errorEl.textContent = 'Passwords do not match.'; return; }
        if (pw.length < 4) { errorEl.textContent = 'Password must be at least 4 characters.'; return; }
        try {
            const resp = await fetch('api.php?action=setup', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: pw }),
            });
            const data = await resp.json();
            if (data.success) { authType = 'system'; enterAdmin('system'); toast('System configured successfully!', 'success'); }
            else errorEl.textContent = data.error || 'Setup failed.';
        } catch (err) { errorEl.textContent = 'Network error. Please try again.'; }
    }

    async function handleLoginSubmit(e) {
        e.preventDefault();
        const pw = document.getElementById('login-password').value;
        const errorEl = document.getElementById('login-error');
        errorEl.textContent = '';
        try {
            const resp = await fetch('api.php?action=login', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: pw }),
            });
            const data = await resp.json();
            if (data.success) { authType = data.auth_type; enterAdmin(data.auth_type, data.slug); }
            else errorEl.textContent = data.error || 'Invalid password.';
        } catch (err) { errorEl.textContent = 'Network error. Please try again.'; }
    }

    async function logout() {
        try { await fetch('api.php?action=logout', { method: 'POST' }); } catch (e) {}
        authType = null;
        showView('login');
        document.getElementById('login-password').value = '';
        document.getElementById('login-error').textContent = '';
    }

    function showView(view) {
        viewSetup.classList.remove('active');
        viewLogin.classList.remove('active');
        viewAdmin.classList.remove('active');
        if (view === 'setup') viewSetup.classList.add('active');
        else if (view === 'login') viewLogin.classList.add('active');
        else if (view === 'admin') viewAdmin.classList.add('active');
    }

    function enterAdmin(type, slug) {
        showView('admin');
        if (type === 'system') { authBadge.textContent = 'System Admin'; btnNewPage.style.display = ''; }
        else { authBadge.textContent = 'Page: ' + (slug || ''); btnNewPage.style.display = 'none'; }
        const urlParams = new URLSearchParams(window.location.search);
        const editSlug = urlParams.get('edit');
        if (editSlug) { editPage(editSlug); window.history.replaceState({}, '', 'admin.php'); }
        else if (type === 'user' && slug) editPage(slug);
        else loadPages();
    }

    // ─── View Management ─────────────────────────────────────

    function showList() {
        viewEditor.classList.remove('active');
        viewList.classList.add('active');
        loadPages();
    }

    function syncColorPicker(textInput, pickerInput) {
        pickerInput.addEventListener('input', () => { textInput.value = pickerInput.value; textInput.dispatchEvent(new Event('input')); });
        textInput.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) pickerInput.value = textInput.value; });
    }

    // ─── Page List ───────────────────────────────────────────

    async function loadPages() {
        try {
            const resp = await fetch('api.php?action=list');
            if (resp.status === 401) { showView('login'); return; }
            const data = await resp.json();
            renderPageGrid(data.pages || []);
        } catch (err) { toast('Failed to load pages', 'error'); }
    }

    function renderPageGrid(pages) {
        if (pages.length === 0) { pageGrid.style.display = 'none'; emptyState.style.display = 'block'; return; }
        pageGrid.style.display = '';
        emptyState.style.display = 'none';
        pageGrid.innerHTML = '';
        pages.forEach(page => {
            const card = document.createElement('div');
            card.className = 'page-card';
            const avatarHtml = page.avatar ? `<img src="${escapeHtml(page.avatar)}" alt="">` : '&#128100;';
            const deleteBtn = authType === 'system'
                ? `<button class="btn btn-danger btn-small" onclick="window.lol.deletePage('${escapeHtml(page.slug)}')">Delete</button>` : '';
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
                    ${deleteBtn}
                </div>`;
            pageGrid.appendChild(card);
        });
    }

    // ─── Editor ──────────────────────────────────────────────

    window.showEditor = showEditor;
    function showEditor(config) {
        viewList.classList.remove('active');
        viewEditor.classList.add('active');
        const passwordHint = document.getElementById('password-hint');

        if (config) {
            currentSlug = config.slug;
            editorTitle.textContent = 'Edit Page';
            fields.slug.value = config.slug;
            fields.slug.readOnly = true;
            fields.title.value = config.title || '';
            fields.bio.value = config.bio || '';
            fields.avatar.value = config.avatar || '';
            fields.userPassword.value = '';
            passwordHint.textContent = config.has_user_password
                ? 'A password is set. Leave blank to keep it, or enter a new one to change it.'
                : 'Set a password so this page\'s owner can sign in to edit their own page.';
            const theme = config.theme || {};
            fields.bgColor.value = theme.background_color || '#780016';
            fields.bgColorEnd.value = theme.background_color_end || '';
            fields.textColor.value = theme.text_color || '#FFFFFF';
            fields.buttonColor.value = theme.button_color || '#FFFFFF';
            fields.buttonTextColor.value = theme.button_text_color || '#FFFFFF';
            fields.buttonStyle.value = theme.button_style || 'outline';
            fields.buttonRadius.value = theme.button_radius || '50px';
            fields.fontFamily.value = theme.font_family || "'Inter', sans-serif";
            syncAllPickers();
            linksContainer.innerHTML = '';
            linkCounter = 0;
            (config.links || []).forEach(link => addLinkItem(link.title, link.url));
        } else {
            currentSlug = null;
            editorTitle.textContent = 'New Page';
            fields.slug.value = '';
            fields.slug.readOnly = false;
            fields.title.value = '';
            fields.bio.value = '';
            fields.avatar.value = '';
            fields.userPassword.value = '';
            passwordHint.textContent = 'Set a password so this page\'s owner can sign in to edit their own page.';
            fields.bgColor.value = '#780016';
            fields.bgColorEnd.value = '#2d0008';
            fields.textColor.value = '#FFFFFF';
            fields.buttonColor.value = '#FFFFFF';
            fields.buttonTextColor.value = '#FFFFFF';
            fields.buttonStyle.value = 'outline';
            fields.buttonRadius.value = '50px';
            fields.fontFamily.value = "'Inter', sans-serif";
            syncAllPickers();
            linksContainer.innerHTML = '';
            linkCounter = 0;
            addLinkItem('', '');
        }
        updateAvatarPreview();
        updatePreview();
    }

    function syncAllPickers() {
        updatePickerFromText(fields.bgColor, fields.bgColorPicker);
        updatePickerFromText(fields.bgColorEnd, fields.bgColorEndPicker);
        updatePickerFromText(fields.textColor, fields.textColorPicker);
        updatePickerFromText(fields.buttonColor, fields.buttonColorPicker);
        updatePickerFromText(fields.buttonTextColor, fields.buttonTextColorPicker);
    }

    function updatePickerFromText(textInput, pickerInput) {
        if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) pickerInput.value = textInput.value;
    }

    function addLinkItem(title, url) {
        const id = linkCounter++;
        const item = document.createElement('div');
        item.className = 'link-item';
        item.dataset.id = id;
        const icon = detectIcon(url);
        const iconHtml = icon ? `<div class="link-item-icon"><i class="${icon}"></i> detected</div>` : '';
        item.innerHTML = `
            <div class="link-item-fields">
                <input type="text" placeholder="Link title" value="${escapeAttr(title)}" class="link-title" data-id="${id}">
                <input type="text" placeholder="https://example.com" value="${escapeAttr(url)}" class="link-url" data-id="${id}">
                <div class="link-item-icon-indicator" data-id="${id}">${iconHtml}</div>
            </div>
            <div class="link-item-actions">
                <button type="button" title="Move up" onclick="window.lol.moveLink(${id}, -1)">&uarr;</button>
                <button type="button" title="Move down" onclick="window.lol.moveLink(${id}, 1)">&darr;</button>
                <button type="button" class="btn-remove" title="Remove" onclick="window.lol.removeLink(${id})">&times;</button>
            </div>`;
        linksContainer.appendChild(item);
        const urlInput = item.querySelector('.link-url');
        const titleInput = item.querySelector('.link-title');
        const iconIndicator = item.querySelector('.link-item-icon-indicator');
        urlInput.addEventListener('input', () => {
            const di = detectIcon(urlInput.value);
            iconIndicator.innerHTML = di ? `<div class="link-item-icon"><i class="${di}"></i> detected</div>` : '';
            updatePreview();
        });
        titleInput.addEventListener('input', updatePreview);
        updatePreview();
    }

    function getFormData() {
        const links = [];
        linksContainer.querySelectorAll('.link-item').forEach(item => {
            const title = item.querySelector('.link-title').value.trim();
            const url = item.querySelector('.link-url').value.trim();
            if (title || url) links.push({ title, url });
        });
        const data = {
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
        const pw = fields.userPassword.value;
        if (pw) data.user_password = pw;
        return data;
    }

    function isFormEmpty() {
        return !fields.slug.value.trim() && !fields.title.value.trim();
    }

    // ─── Import from Linktree ────────────────────────────────

    function showImportModal() {
        importedData = null;
        importUrlInput.value = '';
        importUrlError.textContent = '';
        showImportStep('url');
        importModal.classList.add('active');
        importUrlInput.focus();
    }

    function hideImportModal() {
        importModal.classList.remove('active');
    }

    function showImportStep(step) {
        importStepUrl.classList.remove('active');
        importStepLoading.classList.remove('active');
        importStepMerge.classList.remove('active');
        if (step === 'url') importStepUrl.classList.add('active');
        else if (step === 'loading') importStepLoading.classList.add('active');
        else if (step === 'merge') importStepMerge.classList.add('active');
    }

    async function fetchLinktreeProfile() {
        const url = importUrlInput.value.trim();
        if (!url) { importUrlError.textContent = 'Please enter a Linktree URL.'; return; }
        importUrlError.textContent = '';
        showImportStep('loading');

        try {
            const resp = await fetch('api.php?action=import-linktree', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url }),
            });
            if (resp.status === 401) { hideImportModal(); showView('login'); return; }
            const result = await resp.json();
            if (!result.success) {
                importUrlError.textContent = result.error || 'Import failed.';
                showImportStep('url');
                return;
            }

            importedData = result.data;

            if (isFormEmpty()) {
                applyImportDirect(importedData);
                hideImportModal();
                toast('Imported from Linktree!', 'success');
                return;
            }

            buildMergeUI(importedData);
            showImportStep('merge');
        } catch (err) {
            importUrlError.textContent = 'Network error. Please try again.';
            showImportStep('url');
        }
    }

    function applyImportDirect(data) {
        if (data.suggested_slug && !fields.slug.readOnly) fields.slug.value = data.suggested_slug;
        if (data.title) fields.title.value = data.title;
        if (data.bio) fields.bio.value = data.bio;
        if (data.avatar) fields.avatar.value = data.avatar;

        const t = data.theme || {};
        if (t.background_color) fields.bgColor.value = t.background_color;
        if (t.background_color_end) fields.bgColorEnd.value = t.background_color_end;
        if (t.text_color) fields.textColor.value = t.text_color;
        if (t.button_color) fields.buttonColor.value = t.button_color;
        if (t.button_text_color) fields.buttonTextColor.value = t.button_text_color;
        if (t.button_style) fields.buttonStyle.value = t.button_style;
        if (t.button_radius) fields.buttonRadius.value = t.button_radius;
        syncAllPickers();

        linksContainer.innerHTML = '';
        linkCounter = 0;
        (data.links || []).forEach(l => addLinkItem(l.title, l.url));

        updateAvatarPreview();
        updatePreview();
    }

    function buildMergeUI(data) {
        const current = getFormData();
        let html = '';

        // Profile fields
        html += '<div class="merge-section"><div class="merge-section-title">Profile Information</div>';
        html += buildFieldMerge('title', 'Title', current.title, data.title);
        html += buildFieldMerge('bio', 'Bio', current.bio, data.bio);
        html += buildFieldMerge('avatar', 'Avatar', current.avatar, data.avatar);
        html += '</div>';

        // Theme
        html += '<div class="merge-section"><div class="merge-section-title">Theme</div>';
        html += '<div class="merge-field">';
        const themeSame = JSON.stringify(current.theme) === JSON.stringify(data.theme);
        if (themeSame) {
            html += '<div class="merge-field-label">Themes are identical</div>';
            html += '<input type="hidden" name="merge-theme" value="keep">';
        } else {
            html += '<div class="merge-option"><label>';
            html += '<input type="radio" name="merge-theme" value="keep" checked> ';
            html += '<span class="merge-option-label">Keep current theme' + buildThemeSwatches(current.theme) + '</span>';
            html += '</label></div>';
            html += '<div class="merge-option"><label>';
            html += '<input type="radio" name="merge-theme" value="import"> ';
            html += '<span class="merge-option-label">Use imported theme' + buildThemeSwatches(data.theme) + '</span>';
            html += '</label></div>';
        }
        html += '</div></div>';

        // Links
        html += '<div class="merge-section"><div class="merge-section-title">Links</div>';
        html += '<div class="merge-field">';
        html += '<div class="merge-field-label">Merge Strategy</div>';
        html += '<div class="merge-option"><label><input type="radio" name="merge-links-strategy" value="merge" checked> ';
        html += '<span class="merge-option-label">Merge &mdash; keep current links and add new imported ones</span></label></div>';
        html += '<div class="merge-option"><label><input type="radio" name="merge-links-strategy" value="replace"> ';
        html += '<span class="merge-option-label">Replace &mdash; remove current links, use imported only</span></label></div>';
        html += '<div class="merge-option"><label><input type="radio" name="merge-links-strategy" value="keep"> ';
        html += '<span class="merge-option-label">Keep current &mdash; don\'t change links</span></label></div>';
        html += '</div>';

        const currentUrls = new Set(current.links.map(l => normalizeUrlForComparison(l.url)));
        if (data.links && data.links.length > 0) {
            html += '<div class="merge-field" id="merge-links-list">';
            html += '<div class="merge-field-label">Imported Links</div>';
            data.links.forEach((link, i) => {
                const isDuplicate = currentUrls.has(normalizeUrlForComparison(link.url));
                const badge = isDuplicate
                    ? '<span class="merge-option-badge duplicate">duplicate</span>'
                    : '<span class="merge-option-badge new">new</span>';
                html += `<div class="merge-link-item">
                    <label>
                        <input type="checkbox" name="merge-link-${i}" ${isDuplicate ? '' : 'checked'} data-index="${i}">
                        <span>
                            <span class="merge-link-title">${escapeHtml(link.title)}</span> ${badge}<br>
                            <span class="merge-link-url">${escapeHtml(link.url)}</span>
                        </span>
                    </label>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div>';

        importMergeContent.innerHTML = html;
    }

    function buildFieldMerge(name, label, currentVal, importedVal) {
        let html = '<div class="merge-field">';
        html += `<div class="merge-field-label">${escapeHtml(label)}</div>`;
        const same = currentVal === importedVal;
        const currentEmpty = !currentVal;

        if (same) {
            html += '<div class="merge-option"><span class="merge-option-label" style="color:var(--text-muted)">Values are identical</span></div>';
            html += `<input type="hidden" name="merge-${name}" value="keep">`;
        } else if (currentEmpty && importedVal) {
            html += `<input type="hidden" name="merge-${name}" value="import">`;
            html += `<div class="merge-option"><span class="merge-option-label">Will be set to: <span class="merge-option-value">${escapeHtml(truncate(importedVal, 120))}</span></span></div>`;
        } else if (!importedVal) {
            html += `<input type="hidden" name="merge-${name}" value="keep">`;
            html += '<div class="merge-option"><span class="merge-option-label" style="color:var(--text-muted)">No imported value &mdash; keeping current</span></div>';
        } else {
            html += '<div class="merge-option"><label>';
            html += `<input type="radio" name="merge-${name}" value="keep" checked> `;
            html += `<span class="merge-option-label">Keep current<div class="merge-option-value">${escapeHtml(truncate(currentVal, 120))}</div></span>`;
            html += '</label></div>';
            html += '<div class="merge-option"><label>';
            html += `<input type="radio" name="merge-${name}" value="import"> `;
            html += `<span class="merge-option-label">Use imported<div class="merge-option-value">${escapeHtml(truncate(importedVal, 120))}</div></span>`;
            html += '</label></div>';
        }
        html += '</div>';
        return html;
    }

    function buildThemeSwatches(theme) {
        const colors = [theme.background_color, theme.background_color_end, theme.text_color, theme.button_color].filter(Boolean);
        let html = '<div class="merge-theme-preview">';
        colors.forEach(c => { html += `<div class="merge-theme-swatch" style="background:${escapeHtml(c)}" title="${escapeHtml(c)}"></div>`; });
        html += ` <span style="font-size:11px;color:var(--text-muted)">${escapeHtml(theme.button_style || 'outline')}</span>`;
        html += '</div>';
        return html;
    }

    function applyMerge() {
        if (!importedData) return;

        const getRadio = (name) => {
            const el = importMergeContent.querySelector(`input[name="${name}"]:checked`);
            if (el) return el.value;
            const hidden = importMergeContent.querySelector(`input[name="${name}"][type="hidden"]`);
            return hidden ? hidden.value : 'keep';
        };

        if (getRadio('merge-title') === 'import' && importedData.title) fields.title.value = importedData.title;
        if (getRadio('merge-bio') === 'import' && importedData.bio) fields.bio.value = importedData.bio;
        if (getRadio('merge-avatar') === 'import' && importedData.avatar) fields.avatar.value = importedData.avatar;

        if (getRadio('merge-theme') === 'import' && importedData.theme) {
            const t = importedData.theme;
            if (t.background_color) fields.bgColor.value = t.background_color;
            fields.bgColorEnd.value = t.background_color_end || '';
            if (t.text_color) fields.textColor.value = t.text_color;
            if (t.button_color) fields.buttonColor.value = t.button_color;
            if (t.button_text_color) fields.buttonTextColor.value = t.button_text_color;
            if (t.button_style) fields.buttonStyle.value = t.button_style;
            if (t.button_radius) fields.buttonRadius.value = t.button_radius;
            syncAllPickers();
        }

        const strategy = getRadio('merge-links-strategy');
        if (strategy === 'replace') {
            linksContainer.innerHTML = '';
            linkCounter = 0;
            (importedData.links || []).forEach(l => addLinkItem(l.title, l.url));
        } else if (strategy === 'merge') {
            const checkboxes = importMergeContent.querySelectorAll('input[type="checkbox"][name^="merge-link-"]');
            checkboxes.forEach(cb => {
                if (!cb.checked) return;
                const idx = parseInt(cb.dataset.index);
                const link = importedData.links[idx];
                if (link) addLinkItem(link.title, link.url);
            });
        }

        hideImportModal();
        updateAvatarPreview();
        updatePreview();
        toast('Import applied!', 'success');
    }

    // ─── Save / Edit / Delete ────────────────────────────────

    async function savePage() {
        const data = getFormData();
        if (!data.slug) { toast('Please enter a page slug', 'error'); fields.slug.focus(); return; }
        if (!data.title) { toast('Please enter a display title', 'error'); fields.title.focus(); return; }
        btnSave.disabled = true;
        btnSave.textContent = 'Saving...';
        try {
            const resp = await fetch('api.php?action=save', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            if (resp.status === 401) { showView('login'); toast('Session expired. Please sign in again.', 'error'); return; }
            const result = await resp.json();
            if (result.success) {
                toast('Page saved successfully!', 'success');
                currentSlug = data.slug;
                fields.slug.readOnly = true;
                editorTitle.textContent = 'Edit Page';
                fields.userPassword.value = '';
                document.getElementById('password-hint').textContent = 'A password is set. Leave blank to keep it, or enter a new one to change it.';
            } else toast(result.error || 'Failed to save page', 'error');
        } catch (err) { toast('Network error. Please try again.', 'error'); }
        finally { btnSave.disabled = false; btnSave.textContent = 'Save Page'; }
    }

    async function editPage(slug) {
        try {
            const resp = await fetch(`api.php?action=get&slug=${encodeURIComponent(slug)}`);
            const data = await resp.json();
            if (data.error) { toast(data.error, 'error'); return; }
            showEditor(data);
        } catch (err) { toast('Failed to load page', 'error'); }
    }

    async function deletePage(slug) {
        if (!confirm(`Delete page "${slug}"? This cannot be undone.`)) return;
        try {
            const resp = await fetch('api.php?action=delete', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug }),
            });
            if (resp.status === 401) { showView('login'); return; }
            const result = await resp.json();
            if (result.success) { toast('Page deleted', 'success'); loadPages(); }
            else toast(result.error || 'Failed to delete page', 'error');
        } catch (err) { toast('Network error', 'error'); }
    }

    function moveLink(id, direction) {
        const items = Array.from(linksContainer.querySelectorAll('.link-item'));
        const index = items.findIndex(el => parseInt(el.dataset.id) === id);
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= items.length) return;
        if (direction === -1) linksContainer.insertBefore(items[index], items[targetIndex]);
        else linksContainer.insertBefore(items[targetIndex], items[index]);
        updatePreview();
    }

    function removeLink(id) {
        const item = linksContainer.querySelector(`.link-item[data-id="${id}"]`);
        if (item) { item.remove(); updatePreview(); }
    }

    // ─── Avatar ──────────────────────────────────────────────

    function updateAvatarPreview() {
        const preview = document.getElementById('avatar-preview');
        const url = fields.avatar.value.trim();
        if (url) preview.innerHTML = `<img src="${escapeHtml(url)}" alt="Avatar" onerror="this.parentElement.innerHTML='<span class=\\'avatar-placeholder\\'>?</span>'">`;
        else preview.innerHTML = '<span class="avatar-placeholder">?</span>';
    }

    async function handleAvatarUpload() {
        const file = fields.avatarFile.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('avatar', file);
        try {
            const resp = await fetch('api.php?action=upload', { method: 'POST', body: formData });
            const result = await resp.json();
            if (result.success) { fields.avatar.value = result.url; updateAvatarPreview(); updatePreview(); toast('Image uploaded', 'success'); }
            else toast(result.error || 'Upload failed', 'error');
        } catch (err) { toast('Upload failed', 'error'); }
        fields.avatarFile.value = '';
    }

    // ─── Live Preview ────────────────────────────────────────

    function updatePreview() {
        const data = getFormData();
        const theme = data.theme;
        let bg = theme.background_color || '#780016';
        if (theme.background_color_end) bg = `linear-gradient(180deg, ${theme.background_color} 0%, ${theme.background_color_end} 100%)`;
        previewContent.style.background = bg;
        previewContent.style.color = theme.text_color || '#FFFFFF';
        previewContent.style.fontFamily = theme.font_family || "'Inter', sans-serif";

        let html = '';
        if (data.avatar) html += `<div class="p-avatar"><img src="${escapeHtml(data.avatar)}" alt="" onerror="this.parentElement.style.display='none'"></div>`;
        html += `<div class="p-title">${escapeHtml(data.title || 'Page Title')}</div>`;
        if (data.bio) html += `<div class="p-bio">${escapeHtml(data.bio)}</div>`;

        const socialIcons = [];
        data.links.forEach(link => { const icon = detectIcon(link.url); if (icon) socialIcons.push(icon); });
        if (socialIcons.length > 0) {
            html += '<div class="p-social-icons">';
            socialIcons.forEach(icon => { html += `<div class="p-social-icon"><i class="${icon}"></i></div>`; });
            html += '</div>';
        }

        if (data.links.length > 0) {
            html += '<div class="p-links">';
            data.links.forEach(link => {
                if (!link.title && !link.url) return;
                let style = `border-radius: ${theme.button_radius || '50px'}; `;
                if (theme.button_style === 'filled') style += `background: ${theme.button_color}; color: ${theme.button_text_color}; border: 2px solid transparent;`;
                else if (theme.button_style === 'shadow') style += `background: ${theme.button_color}; color: ${theme.button_text_color}; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15);`;
                else style += `background: transparent; color: ${theme.button_text_color}; border: 2px solid ${theme.button_color};`;
                html += `<div class="p-link" style="${style}">${escapeHtml(link.title || link.url)}</div>`;
            });
            html += '</div>';
        }
        previewContent.innerHTML = html;
    }

    // ─── Utilities ───────────────────────────────────────────

    function toast(message, type) {
        toastEl.textContent = message;
        toastEl.className = `toast ${type} show`;
        setTimeout(() => { toastEl.classList.remove('show'); }, 3000);
    }

    function escapeHtml(str) { const div = document.createElement('div'); div.textContent = str; return div.innerHTML; }
    function escapeAttr(str) { return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function truncate(str, len) { return str.length > len ? str.substring(0, len) + '...' : str; }

    window.lol = { editPage, deletePage, moveLink, removeLink, showEditorNew: () => showEditor() };
})();
