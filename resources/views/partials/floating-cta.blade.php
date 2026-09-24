@if (whatsapp_number())
    <a class="wa-float" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener" aria-label="WhatsApp'tan yazın">
        <i class="fa-brands fa-whatsapp"></i>
    </a>
@endif
