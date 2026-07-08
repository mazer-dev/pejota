import preset from '../../../../vendor/filament/filament/tailwind.config.preset'
import typography from '@tailwindcss/typography'

export default {
    presets: [preset],
    content: [
        './app/Filament/App/**/*.php',
        './resources/views/filament/app/**/*.blade.php',
        './resources/views/help/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    plugins: [typography],
}
