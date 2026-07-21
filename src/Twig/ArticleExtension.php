<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ArticleExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('article_content', [$this, 'renderContent'], ['is_safe' => ['html']]),
        ];
    }

    public function renderContent(string $content): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($content));
        $html = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ('' === $para) {
                continue;
            }

            // Ligne type "**1. Titre de section**"
            if (preg_match('/^\*\*(\d+\..+?)\*\*$/', $para, $m)) {
                $html .= '<h3 class="article-section-title">'.htmlspecialchars($m[1]).'</h3>';

                continue;
            }

            // Ligne qui commence par "🚩" ou "•" → liste
            $lines = explode("\n", $para);
            $isList = count(array_filter($lines, fn ($l) => preg_match('/^[🚩•·]\s/u', trim($l)))) > 0;

            if ($isList) {
                $html .= '<ul class="article-list">';
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ('' === $line) {
                        continue;
                    }
                    $line = preg_replace('/^[🚩•·]\s*/u', '', $line);
                    $line = $this->inlineFormat($line);
                    $html .= '<li>'.$line.'</li>';
                }
                $html .= '</ul>';

                continue;
            }

            // Paragraphe normal (peut contenir des **bold**)
            $text = $this->inlineFormat(nl2br(htmlspecialchars($para)));
            $html .= '<p>'.$text.'</p>';
        }

        return $html;
    }

    private function inlineFormat(string $text): string
    {
        // **bold**
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        return $text;
    }
}
