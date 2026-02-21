# List of Links (LoL)

A self-hosted, Linktree-style link page application. Create customizable "list of links" pages for yourself or multiple users, all managed through a web-based admin interface.

## Features

- **Customizable Link Pages** — Each page has its own avatar, bio, color theme, button style, and list of links.
- **Social Icon Detection** — Links to well-known services (Discord, Twitch, YouTube, Instagram, TikTok, GitHub, etc.) automatically get FontAwesome icons displayed in a social bar at the top of the page.
- **Web-Based Admin** — Create, edit, and delete pages from a browser-based configuration interface with a live phone-frame preview.
- **Multi-User Support** — Host link pages for multiple users from a single installation. Each page is stored as a JSON file.
- **Password Protection** — Two-tier authentication system:
  - **System password** — Full admin access to create and manage all pages.
  - **Per-page passwords** — Each page can have its own password, letting the page owner sign in to edit only their page.
- **Smart Defaults** — The root URL automatically shows the right thing:
  - **No pages configured** → Redirects to the setup/admin interface.
  - **One page** → Displays that page directly.
  - **Multiple pages** → Shows a directory listing of all pages.
- **Responsive Design** — Mobile-first layout for both link pages and the admin interface.
- **JSON Configuration** — All page data is stored as simple JSON files — no database required.

## Requirements

- **PHP 7.4+** (8.0+ recommended)
  - `json` extension (usually enabled by default)
  - `fileinfo` extension (for avatar upload MIME validation)
  - `session` support
- **Web Server** — Apache with `mod_rewrite` enabled (for optional clean URLs), or nginx, or PHP's built-in development server.
- **No database** — Everything is stored as flat JSON files.

## Installation

1. **Clone or download** the repository into your web server's document root (or a subdirectory):

   ```bash
   git clone https://github.com/soren42/List-of-Links.git /var/www/html/links
   ```

2. **Set permissions** so PHP can write to the `pages/` and `uploads/` directories:

   ```bash
   chmod 755 pages/ uploads/
   chown www-data:www-data pages/ uploads/
   ```

3. **Configure your web server** to serve the directory. For Apache, the included `.htaccess` file handles clean URL rewriting automatically. For nginx, add a rewrite rule:

   ```nginx
   location /links/ {
       try_files $uri $uri/ /links/index.php?page=$uri;
   }
   ```

4. **Open the application** in a browser. On first visit you'll be prompted to set a system administrator password.

5. **Create your first page** using the admin interface.

## Quick Start with PHP's Built-In Server

For local development or testing:

```bash
cd List-of-Links
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## Usage

### First-Time Setup

1. Navigate to the application in your browser.
2. You'll be redirected to the admin interface and prompted to set a **system password**.
3. This password grants full access to create and manage all pages.

### Creating a Link Page

1. Sign in at `admin.php` with your system password.
2. Click **+ New Page**.
3. Fill in:
   - **Page Slug** — The URL-friendly identifier (e.g., `akoriapink`).
   - **Display Title** — Shown at the top of the page (e.g., `@akoriapink`).
   - **Bio** — A short description.
   - **Avatar** — Paste an image URL or upload a file.
   - **Page Password** — Optional. Lets the page owner sign in to edit only their page.
4. Configure the **theme** (background colors, button style, button radius, font).
5. Add **links** — each with a title and URL. Well-known services will auto-detect icons.
6. Click **Save Page**.

### Viewing Pages

- **Single page:** `https://yourdomain.com/?page=slug` or `https://yourdomain.com/slug` (with `.htaccess`).
- **Directory:** If multiple pages exist and no specific page is requested, a directory listing is shown.

### Per-Page Access

Each page can have its own password, set in the admin editor. When a user signs in with a page password, they can only see and edit their own page. The system password always grants access to everything.

## File Structure

```
List-of-Links/
├── index.php              # Router: renders link pages or directory listing
├── admin.php              # Web-based admin interface (setup/login/editor)
├── api.php                # REST API for CRUD, auth, and file uploads
├── config.json            # System config (auto-created on setup; contains password hash + salt)
├── .htaccess              # Apache rewrite rules for clean URLs
├── assets/
│   ├── css/
│   │   ├── page.css       # Link page styles (social icons, buttons, directory)
│   │   └── admin.css      # Admin interface styles (auth views, editor, preview)
│   └── js/
│       └── admin.js       # Admin SPA logic (auth, forms, icon detection, live preview)
├── pages/                 # JSON configuration files (one per page)
│   └── akoriapink.json    # Example: AkoriaPink's pre-configured page
├── uploads/               # User-uploaded avatar images
│   └── .gitkeep
└── README.md
```

## Security Model

Passwords are hashed using a double-MD5 scheme with a random salt:

```
stored_hash = md5( md5(password) + md5(password) + salt )
```

- The **salt** is generated once during initial setup and stored in `config.json`.
- The **system password hash** is stored in `config.json`.
- **Per-page password hashes** are stored in each page's JSON file.
- Password hashes are never sent to the browser; the API strips them from responses.
- PHP sessions are used to track authentication state.

> **Note:** This authentication is designed for convenience, not high-security environments. For production deployments with sensitive data, consider adding HTTPS, rate limiting, and a more robust authentication layer.

## API Reference

All endpoints are accessed via `api.php`.

| Method | Endpoint | Auth Required | Description |
|--------|----------|---------------|-------------|
| GET | `?action=check-auth` | No | Check current authentication status |
| GET | `?action=needs-setup` | No | Check if initial setup is needed |
| POST | `?action=setup` | No | Set the system password (first time only) |
| POST | `?action=login` | No | Authenticate with system or page password |
| POST | `?action=logout` | No | Destroy the current session |
| GET | `?action=list` | No | List all pages (filtered by auth level) |
| GET | `?action=get&slug=X` | No | Get a page configuration (password hash excluded) |
| POST | `?action=save` | Yes | Create or update a page |
| POST | `?action=delete` | Yes | Delete a page |
| POST | `?action=upload` | Yes | Upload an avatar image (multipart form) |

## Supported Social Icons

Links to the following services automatically display FontAwesome icons:

Discord, TikTok, Twitch, YouTube, Instagram, Twitter/X, Facebook, GitHub, LinkedIn, Snapchat, Reddit, Pinterest, Spotify, SoundCloud, Patreon, Steam, Tumblr, WhatsApp, Telegram, Vimeo, Behance, Dribbble, DeviantArt, Etsy, PayPal, Ko-fi, Cash App, Venmo, Apple Music, Threads, Mastodon, Bluesky, and more.

## License

This project is provided as-is for personal and educational use.
