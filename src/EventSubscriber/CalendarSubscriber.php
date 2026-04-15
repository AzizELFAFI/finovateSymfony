<?php

namespace App\EventSubscriber;

use App\Entity\Goal;
use App\Entity\User;
use CalendarBundle\Entity\Event;
use CalendarBundle\Event\CalendarEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('kernel.event_subscriber')]
class CalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CalendarEvent::class => 'onCalendarEvent',
        ];
    }

    public function onCalendarEvent(CalendarEvent $calendarEvent): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $goals = $this->em->getRepository(Goal::class)->findBy(['id_user' => (int) $user->getId()]);

        foreach ($goals as $goal) {
            $target = (float) str_replace(',', '.', (string) $goal->getTarget_amount());
            $current = (float) str_replace(',', '.', (string) $goal->getCurrent_amount());
            $percent = $target > 0 ? round(($current / $target) * 100) : 0;

            // Couleur selon le statut
            $color = match ($goal->getStatus()) {
                'COMPLETED' => '#10b981', // vert
                'IN_PROGRESS' => $percent >= 75 ? '#f59e0b' : ($percent >= 50 ? '#3b82f6' : '#6366f1'),
                default => '#6b7280', // gris
            };

            $event = new Event(
                $goal->getTitle() . ' (' . $percent . '%)',
                $goal->getDeadline()
            );

            $event->addOptions([
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',
                'allDay' => true,
                'extendedProps' => [
                    'status' => (string) $goal->getStatus(),
                    'target' => number_format($target, 2, ',', ' '),
                    'current' => number_format($current, 2, ',', ' '),
                    'percent' => (int) $percent,
                ],
            ]);

            $calendarEvent->addEvent($event);
        }
    }
}
