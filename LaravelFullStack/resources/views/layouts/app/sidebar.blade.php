<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.header>
        <flux:text font-weight="bold"> Wasm DB Diagram Viewer</flux:text>
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group :heading="__('Diagrama')" class="grid">
            <flux:sidebar.item :href="route('extract')" :current="request()->routeIs('extract')" wire:navigate>
                {{ __('Extrair SQLite') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>

    <flux:spacer />

    <flux:sidebar.nav>

    </flux:sidebar.nav>

</flux:sidebar>


{{ $slot }}

@fluxScripts
</body>
</html>
