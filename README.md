# Nova Computer Academy Website
## Setup & Customization Guide

---

## 📁 Project Structure

```
nova-academy/
│
├── index.html          ← Main homepage
├── apply.html          ← Online application form
├── gallery.html        ← Photo gallery
├── contact.html        ← Contact page
├── about.html          ← About us page
│
├── send-form.php       ← Handles application form emails
├── send-contact.php    ← Handles contact form emails
│
├── css/
│   └── style.css       ← All styles (edit colors/fonts here)
│
├── js/
│   └── main.js         ← All JavaScript (nav, forms, gallery)
│
└── images/             ← Put your photos here
    ├── logo.png          ← Your academy logo
    ├── hero-bg.jpg       ← Hero banner photo
    └── gallery-*.jpg     ← Gallery photos
```

---

## ✅ Checklist: Things to Update Before Going Live

### 1. Contact Details (search for `EDIT` in all files)
In every HTML file and PHP files, search for `EDIT` and replace:
- `+94 XX XXX XXXX` → Your real phone number
- `94XXXXXXXXX` → Your WhatsApp number (digits only, no +, no spaces)
- `No. 123, Main Street, Your City` → Your real address
- `Your City` → Your real city name

### 2. Logo
- Place your logo image in `images/logo.png`
- In each HTML file, find `.nav-logo-icon` and replace with:
  ```html
  <img src="images/logo.png" alt="Nova Computer Academy Logo" style="height:44px;">
  ```

### 3. Hero Background Photo
- Save a good campus or lab photo as `images/hero-bg.jpg`
- In `css/style.css`, find `.hero-bg` and update:
  ```css
  background: url('../images/hero-bg.jpg') center/cover no-repeat;
  ```
- Also remove the `.hero-overlay` element from `index.html` (or keep it for darker effect)

### 4. Gallery Photos
- Copy your photos into the `images/` folder
- In `gallery.html`, replace `src="https://placehold.co/..."` with:
  ```html
  src="images/your-photo-name.jpg"
  ```
- Update the `data-caption` attribute with a real description

### 5. Course Dates
- In `index.html`, update the "Next Intake" and "Start Date" values in each course card

### 6. About Us Content
- Open `about.html` and replace placeholder paragraphs with your real institute history, mission, etc.

### 7. Social Media Links
- In the footer of every page, update `href="#"` for Facebook, YouTube, Instagram, etc.

### 8. Google Maps
- Get your Google Maps embed from maps.google.com → Share → Embed a map
- In `contact.html`, replace the `.map-placeholder` div with the embed iframe (see comment in file)

---

## 📧 Email Setup

### Option A: cPanel Hosting (Recommended for Sri Lanka)
- Most Sri Lankan hosts (SLT, Dialog, Hostinger LK) support PHP `mail()` natively.
- Just upload the files and the forms will work immediately.
- In `send-form.php` and `send-contact.php`, the `$to_email` is already set to `contact@novait.edu.lk`

### Option B: Cloudflare Email Routing (Free)
1. Log into your Cloudflare dashboard
2. Go to **Email → Email Routing**
3. Add a routing rule:
   - **From:** `contact@novait.edu.lk`
   - **To:** `novaitacademy@gmail.com`
4. This forwards all emails from your domain to Gmail — no extra setup needed!

### Option C: If Email Doesn't Work
- Check your hosting's PHP mail settings in cPanel
- Or use a transactional email service like **Brevo (formerly Sendinblue)** — free plan is sufficient

---

## 🚀 Deployment

### Uploading to Your Hosting
1. Log into your cPanel / hosting file manager
2. Go to `public_html` (or your domain folder)
3. Upload all files maintaining the folder structure
4. Make sure PHP files have correct permissions (644)

### Domain / Cloudflare
- Point your domain to your hosting via Cloudflare
- Enable Cloudflare's free SSL (HTTPS)
- Enable Email Routing for your custom email

---

## 🎨 Customizing Colors

Open `css/style.css` and find the `:root` block at the top:

```css
:root {
  --blue:        #1a56db;   ← Main blue color
  --blue-dark:   #1341a8;   ← Darker blue
  --yellow:      #f5b800;   ← Main yellow/gold
  --yellow-dark: #d09900;   ← Darker yellow
  ...
}
```

Change these hex values to adjust the entire color scheme instantly.

---

## 📱 Mobile Testing
Test on real devices or use Chrome DevTools (F12 → mobile icon) to check:
- iPhone SE (small screen)
- iPhone 14 / Galaxy S (regular)
- iPad (tablet)
- Desktop (1200px+)

---

## ❓ Support
If you need help, contact your web developer or refer to the inline comments in each file.
All placeholder content is marked with `<!-- EDIT: ... -->` comments for easy finding.
