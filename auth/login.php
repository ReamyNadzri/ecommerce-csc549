<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom font for a clean look, similar to the image */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7fafc; /* A very light grey background */
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-sm w-full sm:max-w-md md:max-w-lg lg:max-w-xl xl:max-w-2xl">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Welcome Back!</h2>
        <form>
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email or Username</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200 ease-in-out"
                    placeholder="you@example.com"
                    required
                >
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200 ease-in-out"
                    placeholder="********"
                    required
                >
            </div>
            <div class="flex items-center justify-between mb-6">
                <a href="#" class="inline-block align-baseline font-bold text-sm text-red-500 hover:text-red-700 transition duration-200 ease-in-out">
                    Forgot Password?
                </a>
            </div>
            <button
                type="submit"
                class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 ease-in-out transform hover:scale-105"
            >
                Login
            </button>
            <div class="text-center mt-6">
                <p class="text-gray-600 text-sm">Don't have an account? <a href="#" class="font-bold text-red-500 hover:text-red-700 transition duration-200 ease-in-out">Sign Up</a></p>
            </div>
        </form>
    </div>
</body>
</html>
