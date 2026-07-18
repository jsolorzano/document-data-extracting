<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mi Aplicación Laravel')</title>

    <!-- Cargamos los assets compilados por Vite (CSS y JS donde está Alpine) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">

    <!-- Ejemplo de componente Alpine global (ej: un Navbar o Sidebar) -->
    <nav x-data="{ open: false }" class="bg-white shadow p-4">
        <div class="flex justify-between items-center">
            <span class="font-bold">Mi App</span>
            <button @click="open = !open" class="md:hidden p-2 border rounded">
                Menu
            </button>
        </div>

        <!-- Menú desplegable móvil controlado por Alpine -->
        <div x-show="open" @click.away="open = false" class="mt-4 md:hidden">
            <a href="#" class="block py-2 text-gray-600">Inicio</a>
            <a href="#" class="block py-2 text-gray-600">Configuración</a>
        </div>
    </nav>

    <!-- Contenedor principal donde se inyectarán las vistas hijas -->
    <main class="container mx-auto mt-6 px-4">
        @yield('content')
    </main>

</body>
</html>
