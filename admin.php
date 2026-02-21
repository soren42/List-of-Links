<?php
/**
 * List of Links (LoL) - Admin Configuration Interface
 *
 * Three states:
 *   1. Setup   — No config.json exists; prompt for system password
 *   2. Login   — config.json exists but user is not authenticated
 *   3. Admin   — Authenticated; show page management dashboard + editor
 *
 * Authentication is handled via JavaScript calls to api.php.
 */
session_start();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List of Links - Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <!-- ─── Setup View ─────────────────────────────────────── -->
    <section id="view-setup" class="auth-view">
        <div class="auth-card">
            <div class="auth-logo">List of Links</div>
            <h2>Initial Setup</h2>
            <p class="auth-desc">Set a system administrator password. This password grants full access to create and manage all link pages.</p>
            <form id="setup-form" class="auth-form">
                <div class="form-group">
                    <label for="setup-password">System Password</label>
                    <input type="password" id="setup-password" placeholder="Choose a password" required minlength="4">
                </div>
                <div class="form-group">
                    <label for="setup-password-confirm">Confirm Password</label>
                    <input type="password" id="setup-password-confirm" placeholder="Confirm password" required minlength="4">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Set Password &amp; Continue</button>
                <div id="setup-error" class="auth-error"></div>
            </form>
        </div>
    </section>

    <!-- ─── Login View ─────────────────────────────────────── -->
    <section id="view-login" class="auth-view">
        <div class="auth-card">
            <div class="auth-logo">List of Links</div>
            <h2>Sign In</h2>
            <p class="auth-desc">Enter your system or page password to manage your links.</p>
            <form id="login-form" class="auth-form">
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
                <div id="login-error" class="auth-error"></div>
            </form>
            <a href="index.php" class="auth-back-link">&larr; Back to site</a>
        </div>
    </section>

    <!-- ─── Admin View ─────────────────────────────────────── -->
    <section id="view-admin" class="admin-view">
        <header class="header">
            <div class="header-inner">
                <h1 class="logo">List of Links</h1>
                <div class="header-actions">
                    <span id="auth-badge" class="auth-badge"></span>
                    <button id="btn-new-page" class="btn btn-primary">+ New Page</button>
                    <button id="btn-logout" class="btn btn-ghost">Sign Out</button>
                </div>
            </div>
        </header>

        <main class="main">
            <!-- Page List View -->
            <section id="view-list" class="view active">
                <div class="page-grid" id="page-grid">
                    <!-- Populated by JavaScript -->
                </div>
                <div id="empty-state" class="empty-state" style="display:none;">
                    <div class="empty-icon">&#128279;</div>
                    <h2>No pages yet</h2>
                    <p>Create your first link page to get started.</p>
                    <button class="btn btn-primary" onclick="window.lol.showEditorNew()">+ Create Page</button>
                </div>
            </section>

            <!-- Editor View -->
            <section id="view-editor" class="view">
                <div class="editor-layout">
                    <div class="editor-panel">
                        <div class="editor-header">
                            <button id="btn-back" class="btn btn-ghost">&larr; Back</button>
                            <h2 id="editor-title">New Page</h2>
                            <button id="btn-save" class="btn btn-primary">Save Page</button>
                        </div>

                        <form id="page-form" class="form">
                            <!-- Basic Info -->
                            <fieldset class="form-section">
                                <legend>Basic Info</legend>

                                <div class="form-group">
                                    <label for="field-slug">Page Slug</label>
                                    <input type="text" id="field-slug" name="slug" placeholder="my-page" pattern="[a-z0-9\-]+" required>
                                    <small>Lowercase letters, numbers, and hyphens only. This becomes the URL.</small>
                                </div>

                                <div class="form-group">
                                    <label for="field-title">Display Title</label>
                                    <input type="text" id="field-title" name="title" placeholder="@username" required>
                                </div>

                                <div class="form-group">
                                    <label for="field-bio">Bio / Description</label>
                                    <textarea id="field-bio" name="bio" rows="3" placeholder="A short description about yourself..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="field-avatar">Avatar</label>
                                    <div class="avatar-input">
                                        <div class="avatar-preview" id="avatar-preview">
                                            <span class="avatar-placeholder">?</span>
                                        </div>
                                        <div class="avatar-controls">
                                            <input type="text" id="field-avatar" name="avatar" placeholder="https://example.com/image.jpg">
                                            <div class="avatar-upload-row">
                                                <span class="text-muted">or</span>
                                                <label class="btn btn-small btn-ghost upload-label">
                                                    Upload Image
                                                    <input type="file" id="avatar-file" accept="image/*" hidden>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Page Password -->
                            <fieldset class="form-section">
                                <legend>Page Password</legend>
                                <div class="form-group">
                                    <label for="field-user-password">User Password</label>
                                    <input type="password" id="field-user-password" name="user_password" placeholder="Set a password for this page">
                                    <small id="password-hint">This lets the page owner sign in to edit only their own page. Leave blank to keep the current password unchanged.</small>
                                </div>
                            </fieldset>

                            <!-- Theme Settings -->
                            <fieldset class="form-section">
                                <legend>Theme</legend>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="field-bg-color">Background</label>
                                        <div class="color-input">
                                            <input type="color" id="field-bg-color-picker" value="#780016">
                                            <input type="text" id="field-bg-color" name="background_color" value="#780016" placeholder="#780016">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="field-bg-color-end">Background End</label>
                                        <div class="color-input">
                                            <input type="color" id="field-bg-color-end-picker" value="#2d0008">
                                            <input type="text" id="field-bg-color-end" name="background_color_end" value="#2d0008" placeholder="Optional gradient">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="field-text-color">Text Color</label>
                                        <div class="color-input">
                                            <input type="color" id="field-text-color-picker" value="#FFFFFF">
                                            <input type="text" id="field-text-color" name="text_color" value="#FFFFFF" placeholder="#FFFFFF">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="field-button-color">Button Color</label>
                                        <div class="color-input">
                                            <input type="color" id="field-button-color-picker" value="#FFFFFF">
                                            <input type="text" id="field-button-color" name="button_color" value="#FFFFFF" placeholder="#FFFFFF">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="field-button-text-color">Button Text</label>
                                        <div class="color-input">
                                            <input type="color" id="field-button-text-color-picker" value="#FFFFFF">
                                            <input type="text" id="field-button-text-color" name="button_text_color" value="#FFFFFF" placeholder="#FFFFFF">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="field-button-style">Button Style</label>
                                        <select id="field-button-style" name="button_style">
                                            <option value="outline">Outline</option>
                                            <option value="filled">Filled</option>
                                            <option value="shadow">Shadow</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="field-button-radius">Button Radius</label>
                                        <select id="field-button-radius" name="button_radius">
                                            <option value="50px">Fully Rounded</option>
                                            <option value="12px">Rounded</option>
                                            <option value="4px">Slightly Rounded</option>
                                            <option value="0px">Square</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="field-font-family">Font</label>
                                        <select id="field-font-family" name="font_family">
                                            <option value="'Inter', sans-serif">Inter</option>
                                            <option value="'Arial', sans-serif">Arial</option>
                                            <option value="'Georgia', serif">Georgia</option>
                                            <option value="'Courier New', monospace">Courier New</option>
                                            <option value="system-ui, sans-serif">System UI</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Links -->
                            <fieldset class="form-section">
                                <legend>Links</legend>
                                <div id="links-container">
                                    <!-- Populated by JavaScript -->
                                </div>
                                <button type="button" id="btn-add-link" class="btn btn-ghost btn-full">+ Add Link</button>
                            </fieldset>
                        </form>
                    </div>

                    <!-- Live Preview -->
                    <div class="preview-panel">
                        <div class="preview-frame">
                            <div class="preview-phone">
                                <div class="preview-content" id="preview-content">
                                    <!-- Live preview rendered by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </section>

    <div id="toast" class="toast"></div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
