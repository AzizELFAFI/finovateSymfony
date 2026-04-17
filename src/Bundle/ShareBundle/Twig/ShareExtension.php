<?php

namespace App\Bundle\ShareBundle\Twig;

use App\Bundle\ShareBundle\Service\ShareService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension — expose share_links() function in all templates.
 * Usage: {% set links = share_links(url, title) %}
 */
class ShareExtension extends AbstractExtension
{
    public function __construct(private ShareService $shareService) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('share_links', [$this->shareService, 'getShareLinks']),
        ];
    }
}