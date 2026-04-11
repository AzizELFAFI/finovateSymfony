<?php
namespace App\Enum;

enum DeletionReason: string
{
    case MISINFORMATION     = 'Misinformation / Fausses informations';
    case TOXIC              = 'Contenu toxique ou offensant';
    case SPAM               = 'Spam ou contenu répétitif';
    case COMMUNITY_RULES    = 'Violation des règles de la communauté';
    case MISLEADING_FINANCE = 'Contenu financier trompeur';
    case OTHER              = 'Autre';
}
