@php $accId = $accordionId ?? 'faq'.uniqid(); @endphp
<div class="accordion faq-accordion" id="{{ $accId }}">
    @foreach ($faqs as $i => $faq)
        @php
            $q = is_array($faq) ? ($faq['question'] ?? '') : $faq->question;
            $a = is_array($faq) ? ($faq['answer'] ?? '') : $faq->answer;
        @endphp
        @continue(blank($q))
        <div class="accordion-item">
            <h3 class="accordion-header">
                <button class="accordion-button {{ $i === 0 && ! empty($openFirst) ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $accId }}-{{ $i }}" aria-expanded="{{ $i === 0 && ! empty($openFirst) ? 'true' : 'false' }}">
                    {{ $q }}
                </button>
            </h3>
            <div id="{{ $accId }}-{{ $i }}" class="accordion-collapse collapse {{ $i === 0 && ! empty($openFirst) ? 'show' : '' }}" data-bs-parent="#{{ $accId }}">
                <div class="accordion-body">{!! nl2br(e($a)) !!}</div>
            </div>
        </div>
    @endforeach
</div>
