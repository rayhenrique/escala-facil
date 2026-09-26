<footer {{ $attributes->class(['system-footer']) }}>
    <p>
        Desenvolvido por
        <a href="https://kltecnologia.com" target="_blank" rel="noopener noreferrer">KL Tecnologia</a>
        <span aria-hidden="true">·</span> © {{ now()->year }}
    </p>
    <p>Versão {{ config('version.current') }}</p>
</footer>
