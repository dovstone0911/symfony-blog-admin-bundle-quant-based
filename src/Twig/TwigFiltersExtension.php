<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigFiltersExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('preg_replace', [$this, 'preg_replace']),
            new TwigFilter('preg_split', [$this, 'preg_split']),
            new TwigFilter('br2nl', [$this, 'br2nl']),
            new TwigFilter('array_sum', [$this, 'array_sum']),
        ];
    }

    public function preg_replace(string $subject, string $pattern = '', string $replacement = ''): string
    {
        return preg_replace($pattern, $replacement, $subject);
    }

    public function preg_split(string $subject, string $pattern = '', $limit = -1, $flags = 0): array
    {
        return preg_split($pattern, $subject, $limit, $flags);
    }

    public function br2nl(string $subject): string
    {
        return str_ireplace(array("<br />", "<br>", "<br/>"), "&#13;", $subject);
    }

    public function array_sum($array)
    {
        return array_sum($array);
    }
}
