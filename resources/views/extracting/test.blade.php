@extends('layouts.app')

@section('title', 'Front Testing')

@section('content')
    <div class="bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Bienvenido a tu Dashboard</h1>

        <!-- Probando Alpine dentro de la vista hija -->
        <div x-data="{ count: 0 }" class="p-4 border border-dashed rounded bg-gray-50 text-center">
            <p class="mb-2 text-gray-700">Este contador es manejado por Alpine.js de forma local:</p>
            <span x-text="count" class="text-3xl font-extrabold text-indigo-600 block mb-2"></span>
            
            <button @click="count++" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">
                Incrementar
            </button>
        </div>
    </div>
@endsection