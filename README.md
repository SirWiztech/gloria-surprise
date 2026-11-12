# For Gloria 💗

A private, mobile-first proposal experience for **Chinweuba Chiamaka Gloria**, from **Wiztech**.

Six gentle questions, one big final one. Every answer is saved to MySQL as
she goes, so she can close the page and pick up exactly where she left off.
When she finishes, Wiztech gets an email and can read every answer in a
password-protected dashboard.

Built with PHP 8 (PDO), MySQL, vanilla JavaScript and CSS. No frameworks.

---

## What's included

| Path | What it does |
| --- | --- |
| `public/index.php` | The whole experience: welcome, six steps, YES/NO, celebration |
| `public/assets/css/style.css` | Red-and-white glassmorphism design, mobile first |
| `public/assets/js/app.js` | View transitions, validation, autosave-per-step, confetti, hearts, music |
| `public/assets/img/chiamaka.jpg` | **Placeholder** — replace with her photo |
| `public/assets/audio/music.wav` | Original soft ambient loop used by the music button |
| `public/admin.php` | Private dashboard: every response, detail view, CSV export |
| `config.php` | Database, admin hash, notification email |
| `.env` | Local settings loaded automatically; never commit this file |
| `bootstrap.php` | Shared session, security headers, PDO and helpers |
| `database.sql` | The `responses` table |
| `public/api/save.php` | CSRF-protected JSON endpoint that saves one step at a time |
| `public/api/state.php` | Returns saved progress so she can resume |
| `tools/hash-password.php` | Generates a new admin password hash |

Only the `public/` folder is web-accessible. Configuration, secrets, SQL and
tooling live outside it.

---

## Setup

### 1. Requirements

- PHP **8.0+** with `pdo_mysql`, `mbstring` and `session`
- MySQL **5.7+** or **8.0+**
- Apache (recommended) or Nginx
- HTTPS once the site is live (strongly recommended)

### 2. Create the database

```bash
mysql -u root -p < database.sql
```

This creates the `gloria_proposal` database and the `responses` table. If your
host already created a database for you and the account cannot run
`CREATE DATABASE`, import just the `CREATE TABLE` statement into that database
and set its name in `config.php` or `.env`.

If you already imported the schema before the NOT READY option was added, run:

```sql
ALTER TABLE responses
    MODIFY `forever` ENUM('yes','no','not_ready') DEFAULT NULL;
```

### 3. Configure

The included `.env` file is read automatically. Edit it with your settings
(or set real environment variables, which take priority):

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=gloria_proposal
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

ADMIN_PASSWORD_HASH='...'
NOTIFY_EMAIL=you@example.com

APP_TIMEZONE=Africa/Lagos
APP_SESSION_DAYS=30
APP_DEBUG=false
```

`NOTIFY_EMAIL` empty turns the completion email off. Notifications use PHP's
built-in `mail()`; on hosts that require SMTP, configure PHP's mail transport
or leave email off and use the dashboard. You can also edit the defaults in
`config.php`; `.env` and real environment variables override them. When
deploying from git, create `.env` on the server — it is intentionally ignored
and never committed.

### 4. Change the admin password

The dashboard ships with a default password:

```
WiztechForever2026
```

Change it before uploading anything. Generate a hash:

```bash
php tools/hash-password.php "a new private password"
```

Paste the output into `admin.password_hash` in `config.php`, or as
`ADMIN_PASSWORD_HASH` in `.env`. The dashboard
shows a warning while the default password is still in use.

### 5. Add her photo

Replace **`public/assets/img/chiamaka.jpg`** with Gloria's photo, keeping the same
filename. A portrait crop around **800 × 1000 px** works best — the frame is
arched and crops to the centre. The current file is a clearly marked
placeholder.

### 6. Music (optional)

The music button on the YES screen is off by default and never autoplays.
The folder already contains an original 48-second ambient loop
(`public/assets/audio/music.wav`).

To use your own song, replace **`public/assets/audio/music.wav`** with another WAV, or
point the `<source>` tag in `public/index.php` at an MP3 you add. If the file cannot
be played, the button hides itself instead of showing a broken control.

### 7. Run it

Point your web server's document root at the **`public/`** folder. For a quick
local test:

```bash
php -S localhost:8000 -t public
```

Then open <http://localhost:8000>. The dashboard is at
<http://localhost:8000/admin.php>.

> The PHP development server is for local testing only. Use Apache/Nginx with
> HTTPS for the real thing.

If you use Laravel Herd, open the site settings and set the document root to
`public/` for this project.

### 8. Deploy to Wasmer Edge

Wasmer detects `public/index.php` as the entry point, so no extra config is
needed. Push to the branch connected to your Wasmer app, or run this from the
project root if you deploy with the CLI:

```bash
wasmer deploy
```

Set the database and other secrets as Wasmer environment variables (or keep
`.env` on the server). After deploying, verify the assets:

```bash
curl -I https://gloria-surprise.wasmer.app/assets/css/style.css
```

The public URL maps `public/` to the site root, so files are served from
`/assets/...`, `/api/...` and `/admin.php`.

---

## The flow

1. Welcome page with her photo, name and a pulsing **Proceed** button.
2. **Do you feel comfortable around Wiztech?** — YES / NO
3. **What is it that you like about Wiztech?** — text, minimum 3 characters
4. **What is it that you don't like about Wiztech?** — text, with the honest note
5. **Do you see yourself having something serious to do with Wiztech in the future?** — YES / NO
6. **Would you want to build a lovely future with him?** — YES / NO
7. **Would you want to be his Forever? 💗** — YES / NO / NOT READY

- **YES** saves the answer, then shows the full-screen celebration with
  confetti, rising hearts, the promise message and the music toggle.
- **NO** saves the answer, then shows the honest, respectful thank-you message.
  There are no guilt-trip buttons, no tricks and no second chance prompts.
- **NOT READY** saves the answer and shows a gentle, no-pressure message. It is
  recorded as its own final answer in the dashboard.

If she leaves midway, the session token in `localStorage` (with an HttpOnly
cookie as backup) lets her resume at the next unanswered step.

---

## Dashboard

Open `/admin.php` and enter the admin password.

- Overview of sessions, completion, YES, NO and NOT READY
- Every response with all six answers
- Device / IP / timestamps for context
- Delete an individual response with a confirmation step
- One-click CSV export
- Login throttling after repeated wrong passwords

The default password warning disappears automatically once you set your own
hash.

---

## How the saving works

- `POST api/save.php` accepts JSON, validates the CSRF token from the PHP
  session, checks the step, and stores the answer.
- Answers must arrive in order, so a guessed request cannot skip ahead.
- The session token is generated server-side (`bin2hex(random_bytes(16))`) and
  stored in a `CHAR(32)` unique column.
- If the PHP session expires while she is writing, the client refreshes its
  CSRF token and retries the save once, without losing her text.
- The completion email is sent once, after the final answer is committed.
- All queries use prepared statements and parameter binding.

---

## Security and privacy

- No analytics, no tracking scripts, and no third-party requests except Google
  Fonts. The `canvas-confetti` library is bundled locally.
- `config.php`, `bootstrap.php`, `database.sql`, `README.md`, `.env` and
  `tools/` live outside the `public/` document root, so the web server never
  serves them. `config.php` and `bootstrap.php` also return `404` if requested
  directly.
- Do not put `.htaccess` inside `public/` on Wasmer: PHPix parses Apache
  directives and the deny rules break static files.
- Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` when served over
  HTTPS.
- Responses are stored in MySQL and can be exported or read only from the
  password-protected dashboard.
- Serve the site over HTTPS before sending the link to her.

---

## Notes

- Copy for every question, button and closing message matches the brief exactly.
- Fonts are **Playfair Display** (headings) and **Poppins** (body); if Google
  Fonts is unavailable the design falls back to Georgia and the system sans.
- Animations respect `prefers-reduced-motion`.
- The only colours used are the red / white / blush family:
  `#D90429`, `#8D0801`, `#FFFFFF`, `#FFF5F5`.

Made with care. Good luck, Wiztech. 💗
