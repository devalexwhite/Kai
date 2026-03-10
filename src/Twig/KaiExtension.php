<?php
declare(strict_types=1);

namespace App\Twig;

use DateTimeImmutable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class KaiExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // {{ description|nl2br }} — escapes and converts newlines to <br>
            new TwigFilter('nl2br', function (string $s): string {
                return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            }, ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            // {{ group_background(group.id) }} — deterministic pattern URL for a group cover
            new TwigFunction('group_background', function (int $id): string {
                $images = [
                    '/images/patterns/1.svg',
                    '/images/patterns/2.svg',
                    '/images/patterns/3.svg',
                    '/images/patterns/4.svg',
                    '/images/patterns/5.svg',
                ];
                return $images[$id % count($images)];
            }),

            // {{ event_countdown(ev.event_date, ev.event_time) }} — human-readable countdown
            new TwigFunction('event_countdown', function (string $date, string $time): string {
                $eventDt = new DateTimeImmutable($date . ' ' . $time);
                $now     = new DateTimeImmutable();
                $diff    = $now->diff($eventDt);

                if ($diff->invert) {
                    return 'Past';
                }

                $months = $diff->y * 12 + $diff->m;
                if ($months >= 1) {
                    return 'In ' . $months . ' ' . ($months === 1 ? 'month' : 'months');
                }
                if ($diff->d >= 1) {
                    return 'In ' . $diff->d . ' ' . ($diff->d === 1 ? 'day' : 'days');
                }
                if ($diff->h >= 1) {
                    return 'In ' . $diff->h . ' ' . ($diff->h === 1 ? 'hour' : 'hours');
                }
                $mins = max(1, $diff->i);
                return 'In ' . $mins . ' ' . ($mins === 1 ? 'minute' : 'minutes');
            }),
        ];
    }
}
