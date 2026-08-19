<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataChart - Admin Login</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            /* Green/Tree themed high-quality background overlay */
            background: linear-gradient(to bottom, rgba(40, 116, 52, 0.55), rgba(15, 32, 18, 0.75)), 
                        url('https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=1920') no-repeat center center fixed;
            background-size: cover;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative">

    @if(session('status') || request()->has('logged_out'))
    <div id="toast-notification" class="absolute top-5 right-5 flex items-center w-full max-w-xs p-4 text-gray-800 bg-white rounded-lg shadow-xl border-l-4 border-green-500 transition-opacity duration-500" role="alert">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-100 rounded-lg">
            <i class="fa-solid fa-circle-check text-lg"></i>
        </div>
        <div class="ms-3 text-sm font-medium">Logged out successfully.</div>
        <button type="button" onclick="document.getElementById('toast-notification').remove()" class="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    @endif

    <div class="w-full max-w-md bg-white/95 rounded-2xl shadow-2xl p-8 backdrop-blur-md border border-white/20">
        
        <div class="text-center mb-8">
            <img src="#" alt="logo" class="mx-auto max-w-[170px] h-auto mb-2">
            <h2 class="text-xl font-bold text-gray-800 mt-4 tracking-tight">Admin Login</h2>
        </div>

        <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">Email *</label>
                <div class="relative rounded-lg shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <i class="fa-regular fa-envelope"></i>
                    </div>
                    <input type="email" name="email" id="email" required value="{{ old('email') }}"
                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-slate-50/50 text-gray-900 placeholder-gray-400 text-sm transition-all"
                        placeholder="">
                </div>
                @error('email')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">Password *</label>
                <div class="relative rounded-lg shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <input type="password" name="password" id="password" required
                        class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-slate-50/50 text-gray-900 placeholder-gray-400 text-sm transition-all"
                        placeholder="••••••••••••">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <button type="button" onclick="togglePasswordVisibility()" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <i id="password-icon" class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                @error('password')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center justify-between text-sm pt-1">
                <label class="flex items-center text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" name="remember" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500 accent-green-600">
                    <span class="ml-2 text-xs font-medium">Remember me</span>
                </label>
                <a href="#" class="text-xs font-semibold text-green-700 hover:text-green-800 hover:underline transition-colors">Forgot Password?</a>
            </div>

            <button type="submit" 
                class="w-full mt-2 bg-[#f39c12] hover:bg-[#e67e22] text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2 transition-all transform active:scale-[0.99]">
                Login
            </button>
        </form>
    </div>

    <script>
        // Password Show/Hide Toggle Logic
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-regular', 'fa-eye');
                passwordIcon.classList.add('fa-solid', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-solid', 'fa-eye-slash');
                passwordIcon.classList.add('fa-regular', 'fa-eye');
            }
        }

        // Auto-fade Notification Box after 4 seconds
        window.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('toast-notification');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }, 4000);
            }
        });
    </script>
</body>
</html>