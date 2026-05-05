<template>
    <div class="auth-bg min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
        <div class="w-11/12 sm:max-w-md  bg-white shadow-md overflow-hidden sm:rounded-lg flex flex-col items-center px-6 gap-y-4 py-10">
            <slot name="logo" />
            <slot />
        </div>
    </div>
</template>

<style scoped>
.auth-bg {
    background: linear-gradient(180deg, #0B5A8C 0%, #031826 100%) !important;
}
</style>
