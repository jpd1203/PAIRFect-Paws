<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAIRfect Paws - API Tester</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #8b5cf6;
            --accent-hover: #7c3aed;
            --success: #10b981;
            --error: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: 
                radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* Glassmorphism Classes */
        .glass {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        /* Navbar */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border-color);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
        }

        nav h1 {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(to right, #a78bfa, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        #auth-status {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            display: none;
        }

        /* Typography & Utilities */
        h2 { font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; }
        .hidden { display: none !important; }
        
        /* Layout */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .module { padding: 1.5rem; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        input, select {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: white;
            outline: none;
            transition: all 0.2s;
        }
        input:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
        }

        button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            background: var(--accent);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover { background: var(--accent-hover); transform: translateY(-1px); }
        button.danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5); }
        button.danger:hover { background: rgba(239, 68, 68, 0.3); }

        /* Pet Gallery */
        #pet-gallery { display: grid; gap: 1rem; margin-top: 1rem; }
        .pet-card {
            padding: 1rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pet-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .pet-info p { color: var(--text-secondary); font-size: 0.875rem; }
        .pet-card button { width: auto; padding: 0.5rem 1rem; font-size: 0.875rem; }

        /* Toasts */
        #toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            z-index: 1000;
        }
        .toast {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            background: var(--card-bg);
            border-left: 4px solid var(--accent);
            color: white;
            backdrop-filter: blur(8px);
            animation: slideIn 0.3s ease forwards;
        }
        .toast.error { border-left-color: var(--error); }
        .toast.success { border-left-color: var(--success); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <nav>
        <h1>PAIRfect Paws</h1>
        <div id="auth-status">
            <span id="user-badge" class="badge">Logged In</span>
            <button id="logout-btn" class="danger hidden" style="width: auto;">Logout</button>
        </div>
    </nav>

    <div class="container">
        
        <!-- AUTH MODULE -->
        <div class="module glass" id="auth-module">
            <h2>Authentication</h2>
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                <button id="tab-login" style="background: rgba(255,255,255,0.1)">Login</button>
                <button id="tab-register" style="background: rgba(255,255,255,0.1)">Register</button>
            </div>

            <!-- Login Form -->
            <form id="login-form">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="login-email" value="admin@pairfectpaws.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="login-password" value="password" required>
                </div>
                <button type="submit">Login</button>
            </form>

            <!-- Register Form -->
            <form id="register-form" class="hidden">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="reg-name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="reg-email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="reg-password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" id="reg-password-conf" required>
                </div>
                <button type="submit">Create Account</button>
            </form>
        </div>

        <!-- ADMIN DASHBOARD -->
        <div class="module glass hidden auth-required" id="admin-module">
            <h2>Admin Dashboard Test</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.875rem;">
                Test Role-based access. Only Admins should see data here.
            </p>
            <button id="fetch-dashboard-btn">Fetch Stats</button>
            <pre id="dashboard-result" style="margin-top: 1rem; font-size: 0.75rem; background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 8px; overflow-x: auto; color: var(--success);"></pre>
        </div>

        <!-- PET CRUD -->
        <div class="module glass hidden auth-required" id="pet-crud-module">
            <h2>Add New Pet</h2>
            <form id="add-pet-form">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="pet-name" required>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label>Species</label>
                        <select id="pet-species">
                            <option value="Dog">Dog</option>
                            <option value="Cat">Cat</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Age (Months)</label>
                        <input type="number" id="pet-age" required>
                    </div>
                </div>
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Behavior Scores (1-5)</p>
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <input type="number" id="pet-energy" placeholder="Energy" min="1" max="5" required>
                    <input type="number" id="pet-social" placeholder="Social" min="1" max="5" required>
                    <input type="number" id="pet-train" placeholder="Trainable" min="1" max="5" required>
                    <input type="number" id="pet-adapt" placeholder="Adaptable" min="1" max="5" required>
                </div>
                <button type="submit">Save Pet Record</button>
            </form>
        </div>

        <!-- PET GALLERY -->
        <div class="module glass hidden auth-required" id="gallery-module">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Public Gallery</h2>
                <button id="refresh-gallery-btn" style="width: auto; padding: 0.25rem 0.75rem; font-size: 0.75rem;">Refresh</button>
            </div>
            <div id="pet-gallery">
                <p style="color: var(--text-secondary); font-size: 0.875rem;">No pets loaded yet.</p>
            </div>
        </div>

    </div>

    <div id="toast-container"></div>

    <script>
        // --- Core Application State ---
        let token = localStorage.getItem('pairfect_token');
        let user = JSON.parse(localStorage.getItem('pairfect_user') || 'null');

        // --- DOM Elements ---
        const authModule = document.getElementById('auth-module');
        const authRequiredModules = document.querySelectorAll('.auth-required');
        const userBadge = document.getElementById('user-badge');
        const logoutBtn = document.getElementById('logout-btn');
        const toastContainer = document.getElementById('toast-container');
        
        // Form Toggles
        document.getElementById('tab-login').onclick = () => {
            document.getElementById('login-form').classList.remove('hidden');
            document.getElementById('register-form').classList.add('hidden');
        };
        document.getElementById('tab-register').onclick = () => {
            document.getElementById('register-form').classList.remove('hidden');
            document.getElementById('login-form').classList.add('hidden');
        };

        // --- Utility Functions ---
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerText = message;
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 4000);
        }

        function updateUIState() {
            if (token) {
                authModule.classList.add('hidden');
                authRequiredModules.forEach(el => el.classList.remove('hidden'));
                logoutBtn.classList.remove('hidden');
                userBadge.classList.remove('hidden');
                const role = user?.roles?.[0]?.name || 'User';
                userBadge.innerText = `${user.name} (${role})`;
                fetchPets(); // Auto-load gallery
            } else {
                authModule.classList.remove('hidden');
                authRequiredModules.forEach(el => el.classList.add('hidden'));
                logoutBtn.classList.add('hidden');
                userBadge.classList.add('hidden');
            }
        }

        async function apiFetch(endpoint, options = {}) {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            };
            if (token) headers['Authorization'] = `Bearer ${token}`;

            try {
                const response = await fetch(`/api${endpoint}`, {
                    ...options,
                    headers: { ...headers, ...options.headers }
                });
                
                const data = await response.json().catch(() => null);
                
                if (!response.ok) {
                    showToast(data?.message || 'An error occurred', 'error');
                    throw new Error(data?.message || 'API Error');
                }
                return data;
            } catch (err) {
                console.error(err);
                throw err;
            }
        }

        // --- Event Listeners ---

        // 1. Login
        document.getElementById('login-form').onsubmit = async (e) => {
            e.preventDefault();
            try {
                const res = await apiFetch('/login', {
                    method: 'POST',
                    body: JSON.stringify({
                        email: document.getElementById('login-email').value,
                        password: document.getElementById('login-password').value
                    })
                });
                token = res.access_token;
                user = res.user;
                localStorage.setItem('pairfect_token', token);
                localStorage.setItem('pairfect_user', JSON.stringify(user));
                showToast('Successfully logged in!', 'success');
                updateUIState();
            } catch (e) {}
        };

        // 2. Register
        document.getElementById('register-form').onsubmit = async (e) => {
            e.preventDefault();
            try {
                const res = await apiFetch('/register', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: document.getElementById('reg-name').value,
                        email: document.getElementById('reg-email').value,
                        password: document.getElementById('reg-password').value,
                        password_confirmation: document.getElementById('reg-password-conf').value
                    })
                });
                token = res.access_token;
                user = res.user;
                localStorage.setItem('pairfect_token', token);
                localStorage.setItem('pairfect_user', JSON.stringify(user));
                showToast('Account created successfully!', 'success');
                updateUIState();
            } catch (e) {}
        };

        // 3. Logout
        logoutBtn.onclick = async () => {
            try {
                await apiFetch('/logout', { method: 'POST' });
                token = null; user = null;
                localStorage.removeItem('pairfect_token');
                localStorage.removeItem('pairfect_user');
                showToast('Logged out');
                updateUIState();
            } catch (e) {}
        };

        // 4. Admin Dashboard
        document.getElementById('fetch-dashboard-btn').onclick = async () => {
            const resultBox = document.getElementById('dashboard-result');
            resultBox.innerText = 'Loading...';
            try {
                const res = await apiFetch('/admin/dashboard');
                resultBox.innerText = JSON.stringify(res, null, 2);
                showToast('Fetched Admin Stats', 'success');
            } catch (e) {
                resultBox.innerText = 'Failed to fetch (Check Role Permissions)';
            }
        };

        // 5. Add Pet
        document.getElementById('add-pet-form').onsubmit = async (e) => {
            e.preventDefault();
            try {
                await apiFetch('/pets', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: document.getElementById('pet-name').value,
                        species: document.getElementById('pet-species').value,
                        age_months: document.getElementById('pet-age').value,
                        energy_level: document.getElementById('pet-energy').value,
                        sociability: document.getElementById('pet-social').value,
                        trainability: document.getElementById('pet-train').value,
                        adaptability: document.getElementById('pet-adapt').value
                    })
                });
                showToast('Pet successfully added!', 'success');
                document.getElementById('add-pet-form').reset();
                fetchPets();
            } catch (e) {}
        };

        // 6. Fetch Pets
        document.getElementById('refresh-gallery-btn').onclick = fetchPets;
        async function fetchPets() {
            const gallery = document.getElementById('pet-gallery');
            gallery.innerHTML = '<p>Loading...</p>';
            try {
                const res = await apiFetch('/pets');
                const pets = res.data || res;
                if (!pets.length) {
                    gallery.innerHTML = '<p style="color: var(--text-secondary); font-size: 0.875rem;">No pets found.</p>';
                    return;
                }
                
                gallery.innerHTML = pets.map(pet => `
                    <div class="pet-card">
                        <div class="pet-info">
                            <h3>${pet.name} (${pet.species})</h3>
                            <p>${pet.age_months} months old</p>
                        </div>
                        <button onclick="applyForPet(${pet.id})">Apply</button>
                    </div>
                `).join('');
            } catch (e) {
                gallery.innerHTML = '<p style="color: var(--error);">Error loading pets.</p>';
            }
        }

        // 7. Apply for Pet
        window.applyForPet = async (petId) => {
            try {
                await apiFetch('/applications', {
                    method: 'POST',
                    body: JSON.stringify({ pet_id: petId })
                });
                showToast('Application Submitted! Status: Pending', 'success');
            } catch (e) {}
        };

        // Initialize UI
        updateUIState();
    </script>
</body>
</html>
