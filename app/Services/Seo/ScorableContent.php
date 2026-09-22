<?php

namespace App\Services\Seo;

/**
 * Skorlanacak içeriğin normalleştirilmiş görünümü.
 * Hangi modelden geldiği fark etmez; skorlayıcı yalnız bu alanları bilir.
 */
class ScorableContent
{
    public function __construct(
        public string $contentType,
        public int|string $contentId,
        public string $title,
        public ?string $url = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $bodyHtml = null,
        public ?string $slug = null,
        public bool $published = true,
        public bool $hasImage = false,
        public int $faqCount = 0,
        public ?string $primaryKeyword = null,
        /** Modelden gelen ek gövde parçaları (liste maddeleri, süreç adımları vb.) */
        public array $extraText = [],
        /** Render edilmiş <main> bloğu. Yapısal kontroller (H1/H2) bunun üzerinden yapılır. */
        public ?string $renderedHtml = null,
    ) {}

    /** Yöneticinin doğrudan yazdığı içerik (kelime sayısı ve bağlamsal iç link için). */
    public function fullText(): string
    {
        return trim((string) $this->bodyHtml."\n".implode("\n", array_map('strval', $this->extraText)));
    }

    /** Yapısal kontroller için kullanılacak HTML: varsa render edilmiş sayfa. */
    public function structuralHtml(): string
    {
        return $this->renderedHtml ?? $this->fullText();
    }

    public function isRendered(): bool
    {
        return $this->renderedHtml !== null;
    }
}
