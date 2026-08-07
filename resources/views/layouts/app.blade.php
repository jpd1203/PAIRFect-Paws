<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PAIRfect Paws') — PAIRfect Paws</title>
    <meta name="description" content="@yield('meta_description', 'PAIRfect Paws — Animal Shelter Adoption & Monitoring System')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #5b6af0;
            --primary-dark: #3b4bdb;
            --accent: #f59e0b;
            --bg: #f8f9ff;
            --card: #ffffff;
            --text: #1a1a2e;
            --muted: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --shadow: 0 4px 24px rgba(91,106,240,0.08);
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        /* Nav */
        nav { background: var(--card); border-bottom: 1px solid var(--border); padding: 0 2rem; display: flex; align-items: center; height: 64px; gap: 2rem; box-shadow: var(--shadow); }
        .nav-brand { font-weight: 700; font-size: 1.25rem; color: var(--primary); text-decoration: none; letter-spacing: -0.5px; }
        .nav-links { display: flex; gap: 0.5rem; flex: 1; }
        .nav-links a { color: var(--muted); text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; transition: all 0.15s; }
        .nav-links a:hover, .nav-links a.active { color: var(--primary); background: rgba(91,106,240,0.08); }
        .nav-user { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; }
        .nav-user span { color: var(--muted); }

        /* Layout */
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--text); }
        .page-header p { color: var(--muted); margin-top: 0.25rem; }

        /* Cards */
        .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); padding: 1.5rem; }
        .card-grid { display: grid; gap: 1rem; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.2rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(91,106,240,0.3); }
        .btn-secondary { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); }
        .btn-secondary:hover { background: rgba(91,106,240,0.06); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { opacity: 0.9; }
        .btn-sm { padding: 0.35rem 0.8rem; font-size: 0.8rem; }

        /* Alerts */
        .alert { padding: 0.9rem 1.2rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        th { background: var(--bg); padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--muted); border-bottom: 2px solid var(--border); }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); }
        tr:hover td { background: rgba(91,106,240,0.02); }

        /* Forms */
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.4rem; color: var(--text); }
        input[type=text], input[type=email], input[type=password], input[type=date], input[type=number], select, textarea {
            width: 100%; padding: 0.6rem 0.9rem; border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 0.9rem; font-family: inherit; background: var(--bg); color: var(--text); transition: border-color 0.15s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(91,106,240,0.1); }
        .field-error { color: var(--danger); font-size: 0.8rem; margin-top: 0.3rem; }

        /* Badges */
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-green   { background: #d1fae5; color: #065f46; }
        .badge-yellow  { background: #fef3c7; color: #92400e; }
        .badge-red     { background: #fee2e2; color: #991b1b; }
        .badge-blue    { background: #dbeafe; color: #1e40af; }
        .badge-gray    { background: #f3f4f6; color: #374151; }
        .badge-purple  { background: #ede9fe; color: #5b21b6; }

        /* Pagination */
        .pagination { display: flex; gap: 0.5rem; justify-content: center; margin-top: 2rem; }
        .pagination a, .pagination span { padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; border: 1px solid var(--border); text-decoration: none; color: var(--text); }
        .pagination .active span { background: var(--primary); color: #fff; border-color: var(--primary); }
    </style>
</head>
<body>

<nav>
    <a href="{{ route('pets.index') }}" class="nav-brand">🐾 PAIRfect Paws</a>

    <div class="nav-links">
        <a href="{{ route('pets.index') }}" class="{{ request()->routeIs('pets.*') ? 'active' : '' }}">Browse Pets</a>

        @auth
            @if(auth()->user()->isStaff())
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.animals.index') }}" class="{{ request()->routeIs('admin.animals.*') ? 'active' : '' }}">Animals</a>
                <a href="{{ route('admin.applications.index') }}" class="{{ request()->routeIs('admin.applications.*') ? 'active' : '' }}">Applications</a>
                <a href="{{ route('admin.adoption-profiles.index') }}" class="{{ request()->routeIs('admin.adoption-profiles.*') ? 'active' : '' }}">Adopted</a>
                <a href="{{ route('admin.monitoring.index') }}" class="{{ request()->routeIs('admin.monitoring.*') ? 'active' : '' }}">Monitoring</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="{{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">Audit Logs</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.volunteers.index') }}" class="{{ request()->routeIs('admin.volunteers.*') ? 'active' : '' }}">Staff</a>
                @endif
            @elseif(auth()->user()->isAdopter())
                <a href="{{ route('applications.mine') }}" class="{{ request()->routeIs('applications.mine') ? 'active' : '' }}">My Applications</a>
                <a href="{{ route('monitoring.my-checkins') }}" class="{{ request()->routeIs('monitoring.*') ? 'active' : '' }}">My Check-ins</a>
            @endif
        @endauth
    </div>

    <div class="nav-user">
        @auth
            <span>{{ auth()->user()->first_name }}</span>
            <form action="{{ route('logout') }}" method="POST" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Log out</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">Log in</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Register</a>
        @endauth
    </div>
</nav>

<div class="container">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</div>

</body>
</html>
