<?php
declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupFeedAction
{
    private const BASE_URL = 'https://kaimeet.com';

    public function __construct(private Twig $twig, private PDO $db) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        $stmt = $this->db->prepare("
            SELECT g.id, g.slug, g.name, g.description,
                   c.name AS city_name, c.state AS city_state
            FROM user_groups g
            JOIN cities c ON c.id = g.city_id
            WHERE g.slug = ?
        ");
        $stmt->execute([$slug]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $eventsStmt = $this->db->prepare("
            SELECT e.id, e.title, e.description, e.event_date, e.event_time,
                   e.location, e.meeting_url
            FROM group_events e
            WHERE e.group_id = ?
            ORDER BY e.event_date DESC, e.event_time DESC
        ");
        $eventsStmt->execute([(int) $group['id']]);
        $events = $eventsStmt->fetchAll();

        $xml = $this->buildFeed($group, $events);

        $response->getBody()->write($xml);

        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    private function buildFeed(array $group, array $events): string
    {
        $groupUrl = self::BASE_URL . '/groups/' . $group['slug'];
        $feedUrl  = $groupUrl . '/feed.xml';
        $title    = $this->xmlEscape($group['name']) . ' — Events';
        $desc     = $group['description'] !== ''
            ? $this->xmlEscape($group['description'])
            : $this->xmlEscape($group['name'] . ' — a community group in ' . $group['city_name'] . ', ' . $group['city_state']);

        $items = '';
        foreach ($events as $ev) {
            $items .= $this->buildItem($ev);
        }

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
          <channel>
            <title>{$title}</title>
            <link>{$groupUrl}</link>
            <description>{$desc}</description>
            <atom:link href="{$feedUrl}" rel="self" type="application/rss+xml"/>
            <language>en-us</language>
        {$items}  </channel>
        </rss>
        XML;
    }

    private function buildItem(array $ev): string
    {
        $eventUrl = self::BASE_URL . '/events/' . $ev['id'];
        $title    = $this->xmlEscape($ev['title']);
        $pubDate  = $this->toPubDate($ev['event_date'], $ev['event_time']);

        $descParts = [];
        if ($ev['event_date'] !== '') {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $ev['event_date'] . ' ' . $ev['event_time']);
            if ($dt) {
                $descParts[] = $dt->format('l, F j, Y \a\t g:i A');
            }
        }
        if ($ev['location'] !== '') {
            $descParts[] = 'Location: ' . $ev['location'];
        }
        if ($ev['meeting_url'] !== '') {
            $descParts[] = 'Online meeting available';
        }
        if ($ev['description'] !== '') {
            $descParts[] = $ev['description'];
        }
        $desc = $this->xmlEscape(implode("\n", $descParts));

        return <<<XML
            <item>
              <title>{$title}</title>
              <link>{$eventUrl}</link>
              <description>{$desc}</description>
              <pubDate>{$pubDate}</pubDate>
              <guid isPermaLink="true">{$eventUrl}</guid>
            </item>

        XML;
    }

    private function toPubDate(string $date, string $time): string
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        if ($dt) {
            return $dt->format('D, d M Y H:i:s') . ' +0000';
        }
        // Fallback: date only
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($dt) {
            return $dt->format('D, d M Y') . ' 00:00:00 +0000';
        }
        return gmdate('D, d M Y H:i:s') . ' +0000';
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
