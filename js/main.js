/* ============================================================
   Nova Computer Academy - Main JavaScript
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- NAVBAR TOGGLE ---------- */
  const navToggle = document.querySelector('.nav-toggle');
  const navMenu   = document.querySelector('.nav-menu');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function () {
      this.classList.toggle('active');
      navMenu.classList.toggle('open');
    });

    // Close menu when a link is clicked
    navMenu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navToggle.classList.remove('active');
        navMenu.classList.remove('open');
      });
    });
  }

  // Mark active nav link
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-menu a').forEach(function (link) {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  /* ---------- BACK TO TOP ---------- */
  const backToTop = document.querySelector('.back-to-top');

  if (backToTop) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 350) {
        backToTop.classList.add('visible');
      } else {
        backToTop.classList.remove('visible');
      }
    });

    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------- FADE-IN ON SCROLL ---------- */
  const fadeEls = document.querySelectorAll('.fade-in');

  if (fadeEls.length > 0) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry, i) {
        if (entry.isIntersecting) {
          // Slight stagger for sibling cards
          setTimeout(function () {
            entry.target.classList.add('visible');
          }, i * 80);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    fadeEls.forEach(function (el) {
      observer.observe(el);
    });
  }

  /* ---------- GALLERY LIGHTBOX ---------- */
  const galleryItems = document.querySelectorAll('.gallery-item');
  const lightbox     = document.getElementById('lightbox');

  if (galleryItems.length > 0 && lightbox) {
    const lbImg     = lightbox.querySelector('.lightbox-img');
    const lbCaption = lightbox.querySelector('.lightbox-caption');
    const lbClose   = lightbox.querySelector('.lightbox-close');

    galleryItems.forEach(function (item) {
      item.addEventListener('click', function () {
        const imgSrc = this.querySelector('img').getAttribute('src');
        const caption = this.dataset.caption || '';
        lbImg.setAttribute('src', imgSrc);
        lbCaption.textContent = caption;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
      });
    });

    function closeLightbox() {
      lightbox.classList.remove('active');
      document.body.style.overflow = '';
    }

    lbClose.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeLightbox();
    });
  }

  /* ---------- APPLICATION FORM VALIDATION ---------- */
  const applyForm = document.getElementById('applyForm');

  if (applyForm) {
    // Real-time validation
    applyForm.querySelectorAll('input, select, textarea').forEach(function (field) {
      field.addEventListener('blur', function () { validateField(this); });
      field.addEventListener('input', function () {
        if (this.closest('.form-group').classList.contains('has-error')) {
          validateField(this);
        }
      });
    });

    applyForm.addEventListener('submit', function (e) {
      e.preventDefault();
      let isValid = true;

      this.querySelectorAll('[required]').forEach(function (field) {
        if (!validateField(field)) isValid = false;
      });

      if (isValid) {
        submitForm(applyForm, 'send-form.php', 'applyAlert');
      }
    });
  }

  /* ---------- CONTACT FORM VALIDATION ---------- */
  const contactForm = document.getElementById('contactForm');

  if (contactForm) {
    contactForm.querySelectorAll('input, select, textarea').forEach(function (field) {
      field.addEventListener('blur', function () { validateField(this); });
    });

    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      let isValid = true;

      this.querySelectorAll('[required]').forEach(function (field) {
        if (!validateField(field)) isValid = false;
      });

      if (isValid) {
        submitForm(contactForm, 'send-contact.php', 'contactAlert');
      }
    });
  }

  /* ---------- FIELD VALIDATION ---------- */
  function validateField(field) {
    const group = field.closest('.form-group');
    if (!group) return true;

    const errorMsg = group.querySelector('.error-msg');
    let valid = true;
    let message = '';

    const value = field.value.trim();
    const name  = field.name || field.id;

    // Required check
    if (field.hasAttribute('required') && !value) {
      valid = false;
      message = 'This field is required.';
    }

    // NIC: 12 digits
    else if (name === 'nic') {
      if (!/^\d{12}$/.test(value)) {
        valid = false;
        message = 'NIC must be exactly 12 digits.';
      }
    }

    // Mobile / WhatsApp: 10 digits
    else if (name === 'mobile' || name === 'whatsapp') {
      if (value && !/^0\d{9}$/.test(value)) {
        valid = false;
        message = 'Enter a valid 10-digit number (e.g. 07XXXXXXXX).';
      }
    }

    // Email
    else if (name === 'email' || field.type === 'email') {
      if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        valid = false;
        message = 'Enter a valid email address.';
      }
    }

    // DOB: not in future
    else if (field.type === 'date' && value) {
      const dob = new Date(value);
      const today = new Date();
      if (dob >= today) {
        valid = false;
        message = 'Date of birth must be in the past.';
      }
    }

    // Apply result
    if (!valid) {
      group.classList.add('has-error');
      field.classList.add('error');
      if (errorMsg) errorMsg.textContent = message;
    } else {
      group.classList.remove('has-error');
      field.classList.remove('error');
      if (errorMsg) errorMsg.textContent = '';
    }

    return valid;
  }

  /* ---------- FORM SUBMISSION (AJAX to PHP) ---------- */
  function submitForm(form, action, alertId) {
    const alertEl  = document.getElementById(alertId);
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';

    const data = new FormData(form);

    fetch(action, { method: 'POST', body: data })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;

        if (alertEl) {
          alertEl.className = 'alert ' + (json.success ? 'success' : 'error');
          alertEl.textContent = json.message;
          alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        if (json.success) form.reset();
      })
      .catch(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        if (alertEl) {
          alertEl.className = 'alert error';
          alertEl.textContent = 'Something went wrong. Please try again or contact us directly.';
        }
      });
  }

}); // end DOMContentLoaded
