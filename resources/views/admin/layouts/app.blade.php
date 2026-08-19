<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '- Admin')</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fdfbf7; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #2e7d32; border-radius: 4px; }
        .ck-editor__editable_inline { min-height: 200px; }
    </style>
    @stack('styles')
    @stack('scripts')
</head>
<body class="h-screen flex flex-col overflow-hidden">

    <header class="w-full h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 z-10 shadow-sm">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-leaf text-green-600 text-xl"></i>
            <span class="text-xl font-bold text-gray-800 tracking-tight">Admin</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full">Admin</span>
            <span class="text-sm font-medium text-gray-600">Welcome, Admin</span>
            <a href="#" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">Back</a>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">Log out</button>
            </form>
        </div>
    </header>

    <div class="flex flex-1 h-[calc(100vh-64px)] overflow-hidden">

        <aside class="w-64 bg-white border-l border-gray-200 flex flex-col h-full shadow-lg">
            <div class="p-4 border-b border-gray-100 bg-gray-50/50">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Navigation Menu</span>
            </div>

            <nav class="flex-1 overflow-y-auto p-3 space-y-1 custom-scrollbar">
    
                <a href="{{ route('admin.dashboard') }}" 
                class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-green-50 text-green-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fa-solid fa-gauge w-5 text-gray-400 mr-3"></i>
                    <span>Dashboard Home</span>
                </a>

                <div class="border-t border-gray-100 my-2"></div>

                <div x-data="{ open: {{ request()->routeIs('admin.themes.*') || request()->routeIs('admin.sources.*') ? 'true' : 'false' }} }">
                    {{-- Main Parent Link --}}
                    <button @click="open = !open" 
                            class="w-full flex items-center justify-between px-4 py-2.5 my-1 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.themes.*') || request()->routeIs('admin.sources.*') ? 'bg-green-50 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-layer-group text-base"></i>
                            <span>Themes</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                    </button>

                    {{-- Submenu Items --}}
                    <div x-show="open" x-collapse class="pl-9 pr-2 space-y-1 mt-1">
                        {{-- Themes Submenu Link --}}
                        <a href="{{ route('admin.themes.index') }}" 
                        class="block px-3 py-2 text-xs font-medium rounded-lg transition-all {{ request()->routeIs('admin.themes.*') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                            <i class="fa-solid fa-list mr-1.5 text-xs"></i> All Themes
                        </a>

                        {{-- Sources Submenu Link --}}
                        <a href="{{ route('admin.sources.index') }}" 
                        class="block px-3 py-2 text-xs font-medium rounded-lg transition-all {{ request()->routeIs('admin.sources.*') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                            <i class="fa-solid fa-folder-open mr-1.5 text-xs"></i> Sources
                        </a>
                    </div>
                </div>
                
                <a href="{{ route('admin.states.index') }}" 
                class="flex items-center gap-3 px-4 py-2.5 my-1 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.states.*') ? 'bg-green-50 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                    <i class="fa-solid fa-map-location-dot text-base"></i>
                    <span>States</span>
                </a>

                <a href="{{ route('admin.data_sources.index') }}" 
                class="flex items-center gap-3 px-4 py-2.5 my-1 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.data_sources.*') ? 'bg-green-50 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                    <i class="fa-solid fa-database text-base"></i>
                    <span>Data Sources</span>
                </a>

                <a href="{{ route('admin.indicators.index') }}" 
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.indicators.*') ? 'bg-green-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <!-- Grid/List/Settings Icon -->
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        <span>Manage Indicators</span>
                    </a>

                <a href="{{ route('admin.indicator_data.index') }}" 
                class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('admin.indicator_data.*') ? 'bg-green-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Indicator Data Import</span>
                </a>

                <!-- state -->

                <a href="{{ route('admin.states.index') }}" 
                    class="flex items-center gap-3 px-4 py-2.5 my-1 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.states.*') ? 'bg-green-50 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                    <i class="fa-solid fa-map-location-dot text-base"></i>
                    <span>Manage States</span>
                </a>
                    <!-- Chart Types Menu Link -->
                <a href="{{ route('admin.chart-types.index') }}" 
                class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.chart-types.*') ? 'bg-green-50 text-green-600' : 'text-gray-600 hover:bg-gray-50' }}">
                    <span>📊</span>
                    <span>Chart Types</span>
                </a>

                <!-- Future Add/Manage Charts Menu Link -->
                <a href="{{ route('admin.charts.index') }}" 
                class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.charts.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' }}">
                    <span>📈</span>
                    <span>Add & Manage Charts</span>
                </a>

              

            </nav>
            
            <div class="p-4 border-t border-gray-100 bg-gray-50 text-center">
                <span class="text-[10px] text-gray-400 font-medium">© 2026 IFC</span>
            </div>
        </aside>

        <main class="flex-1 p-8 overflow-y-auto bg-[#faf8f5]">
            <div class="max-w-7xl mx-auto">
                @yield('content')
            </div>
        </main>

    </div>

    <script>
        function toggleDropdown(menuId) {
            const dropdown = document.getElementById(menuId);
            const arrow = document.getElementById(menuId + '-arrow');
            dropdown.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }
    </script>
</body>
</html>