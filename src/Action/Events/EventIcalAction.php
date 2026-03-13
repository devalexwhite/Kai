<?php
declare(strict_types=1);

namespace App\Action\Events;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class EventIcalAction
{
    public function __construct(private Twig $twig, private PDO $db) {}

    public function __invoke(
        Request $request,
        Response $response,
        array $args,
    ): Response {
        $id   = (int) $args["id"];
        $slug = $args["slug"];

        $stmt = $this->db->prepare("
            SELECT e.id, e.title, e.description,
                   e.event_date, e.event_time, e.location, e.meeting_url,
                   g.name AS group_name
            FROM group_events e
            JOIN user_groups g ON g.id = e.group_id
            WHERE e.id = ? AND g.slug = ?
        ");
        $stmt->execute([$id, $slug]);
        $event = $stmt->fetch();

        if (!$event) {
            return $this->twig->render(
                $response->withStatus(404),
                "404.html.twig",
                [
                    "title" => "Event not found",
                    "back" => ["href" => "/groups", "label" => "Browse groups"],
                ],
            );
        }

        $ical = $this->buildIcal($event);

        $response->getBody()->write($ical);

        $filename = "event-" . $event["id"] . ".ics";

        return $response
            ->withHeader("Content-Type", "text/calendar; charset=utf-8")
            ->withHeader(
                "Content-Disposition",
                'attachment; filename="' . $filename . '"',
            );
    }

    private function buildIcal(array $event): string
    {
        $now = gmdate("Ymd\THis\Z");

        $eventDateTime = \DateTimeImmutable::createFromFormat(
            "!Y-m-d H:i",
            $event["event_date"] . " " . $event["event_time"],
        );

        if ($eventDateTime) {
            $dtstart = $eventDateTime->format("Ymd\THis");
            // Default duration is 1 hour since no end time is stored in the database.
            $dtend = $eventDateTime->modify("+1 hour")->format("Ymd\THis");
        } else {
            $dtstart = str_replace("-", "", $event["event_date"]) . "T000000";
            $dtend = str_replace("-", "", $event["event_date"]) . "T010000";
        }

        $uid = "event-" . $event["id"] . "@kai";

        $description = "";
        if ($event["description"] !== "") {
            $description = $event["description"];
        }
        if ($event["group_name"] !== "") {
            $description = $description !== ""
                ? $description . "\\n\\nGroup: " . $event["group_name"]
                : "Group: " . $event["group_name"];
        }

        $lines = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:-//Kai//Kai Events//EN",
            "BEGIN:VEVENT",
            "UID:" . $uid,
            "DTSTAMP:" . $now,
            "DTSTART:" . $dtstart,
            "DTEND:" . $dtend,
            "SUMMARY:" . $this->escapeText($event["title"]),
        ];

        if ($description !== "") {
            $lines[] = "DESCRIPTION:" . $this->escapeText($description);
        }

        if ($event["location"] !== "") {
            $lines[] = "LOCATION:" . $this->escapeText($event["location"]);
        }

        if ($event["meeting_url"] !== "") {
            $lines[] = "URL:" . $event["meeting_url"];
        }

        $lines[] = "END:VEVENT";
        $lines[] = "END:VCALENDAR";

        return implode("\r\n", array_map([$this, "foldLine"], $lines)) . "\r\n";
    }

    private function escapeText(string $value): string
    {
        $value = str_replace("\\", "\\\\", $value);
        $value = str_replace(",", "\\,", $value);
        $value = str_replace(";", "\\;", $value);
        $value = str_replace("\n", "\\n", $value);
        return $value;
    }

    /**
     * Fold long lines per RFC 5545: lines must not exceed 75 octets.
     * Continuation lines begin with a single whitespace character.
     */
    private function foldLine(string $line): string
    {
        $result = "";
        while (strlen($line) > 75) {
            $result .= substr($line, 0, 75) . "\r\n ";
            $line = substr($line, 75);
        }
        return $result . $line;
    }
}
