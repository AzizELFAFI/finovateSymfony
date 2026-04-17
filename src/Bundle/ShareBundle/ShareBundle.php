<?php

namespace App\Bundle\ShareBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * ShareBundle — Partage de posts sur les réseaux sociaux.
 * Fournit un service, une extension Twig et des templates réutilisables.
 */
class ShareBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__) . '/ShareBundle';
    }
}