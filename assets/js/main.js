document.addEventListener('DOMContentLoaded', () => {
    // 1. Preloader Logic
    const preloader = document.querySelector('.preloader');
    if(preloader) {
        setTimeout(() => {
            preloader.style.opacity = '0';
            setTimeout(() => { preloader.style.display = 'none'; }, 500);
        }, 800);
    }

    // 2. Cek apakah User Login (Untuk navbar)
    checkLoginStatus();

    // 3. Setup Scroll Effect
    initScrollEffects();
});

// --- SCROLL EFFECTS ---
function initScrollEffects() {
    const navbar = document.querySelector('.navbar');
    const sections = document.querySelectorAll('section');
    const navLinks = document.querySelectorAll('.nav-links a');

    window.addEventListener('scroll', () => {
        // Navbar Effect
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }

        // Active Link Highlighter
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (pageYOffset >= (sectionTop - 150)) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active-link');
            if (link.getAttribute('href').includes(current)) {
                link.classList.add('active-link');
            }
        });
    });

    // Reveal on Scroll (Fade In Up)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    sections.forEach(section => observer.observe(section));
}

// --- MODAL & TABS LOGIC (FIXED) ---
function openModal() {
    document.getElementById('authModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('authModal').style.display = 'none';
}

// Klik di luar modal menutup modal
document.getElementById('authModal').addEventListener('click', (e) => {
    if (e.target.id === 'authModal') closeModal();
});

function switchTab(tabName) {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const tabs = document.querySelectorAll('.tab-btn');

    // Hapus kelas active dari semua
    tabs.forEach(tab => tab.classList.remove('active'));
    loginForm.classList.remove('active');
    registerForm.classList.remove('active');
    
    // Tambah kelas active ke yang dipilih
    if(tabName === 'login') {
        loginForm.classList.add('active');
        tabs[0].classList.add('active');
    } else {
        registerForm.classList.add('active');
        tabs[1].classList.add('active');
    }
}

// --- BACKEND CONNECTION ---
function checkLoginStatus() {
    fetch('check-login.php')
    .then(res => res.json())
    .then(data => {
        const btnNav = document.getElementById('btnLoginNav');
        if (data.loggedIn) {
            btnNav.innerHTML = `<i class="fas fa-user-circle"></i> ${data.user.name}`;
            btnNav.onclick = () => window.location.href = 'dashboard.php';
        } else {
            btnNav.innerHTML = `<i class="fas fa-user"></i> Masuk / Daftar`;
            btnNav.onclick = () => openModal();
        }
    })
    .catch(err => console.log('Backend belum terhubung sepenuhnya, mode tamu aktif.'));
}

// Login Process
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button');
    const originalText = btn.innerText;
    btn.innerText = 'Memproses...';
    
    const formData = new FormData(this);
    fetch('process-login.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.href = 'dashboard.php';
        } else {
            alert(data.message);
            btn.innerText = originalText;
        }
    })
    .catch(err => {
        alert('Gagal terhubung ke server database.');
        btn.innerText = originalText;
    });
});

// Register Process
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button');
    const originalText = btn.innerText;
    btn.innerText = 'Memproses...';

    const formData = new FormData(this);
    fetch('process-register.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Registrasi Berhasil! Silakan Login.');
            switchTab('login');
            this.reset();
        } else {
            alert(data.message);
        }
        btn.innerText = originalText;
    })
    .catch(err => {
        alert('Gagal terhubung ke server database.');
        btn.innerText = originalText;
    });
});